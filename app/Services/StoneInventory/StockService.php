<?php

namespace App\Services\StoneInventory;

use App\Models\StoneInventoryItemModel;
use App\Models\StoneInventoryStockModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class StockService
{
    private BaseConnection $db;
    private StoneInventoryItemModel $itemModel;
    private StoneInventoryStockModel $stockModel;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->itemModel = new StoneInventoryItemModel();
        $this->stockModel = new StoneInventoryStockModel();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function upsertItemFromSignature(array $data): int
    {
        $signature = $this->normalizeSignature($data);
        if ($signature['product_name'] === '') {
            throw new RuntimeException('Product name is required.');
        }

        $existing = $this->db->query(
            'SELECT id FROM stone_inventory_items
             WHERE product_name = ?
               AND stone_type <=> ?
             LIMIT 1',
            [$signature['product_name'], $signature['stone_type']]
        )->getRowArray();

        if ($existing) {
            $itemId = (int) $existing['id'];
            $this->ensureStockRow($itemId);
            return $itemId;
        }

        $itemId = (int) $this->itemModel->insert([
            'product_name' => $signature['product_name'],
            'stone_type' => $signature['stone_type'],
            'default_rate' => (float) ($data['default_rate'] ?? 0),
            'remarks' => $this->stringOrNull($data['remarks'] ?? null),
        ], true);

        if ($itemId <= 0) {
            throw new RuntimeException('Unable to create stone item.');
        }

        $this->ensureStockRow($itemId);
        return $itemId;
    }

    public function applyPurchase(int $purchaseId): void
    {
        foreach ($this->groupedPurchaseLines($purchaseId) as $row) {
            $itemId = (int) $row['item_id'];
            $addQty = (float) $row['qty'];
            $addValue = (float) $row['line_value'];

            $stock = $this->lockStockRow($itemId);
            $oldQty = (float) $stock['qty_balance'];
            $oldAvg = (float) $stock['avg_rate'];

            $newQty = $oldQty + $addQty;
            if ($newQty < -0.0005) {
                throw new RuntimeException('Stock cannot go negative while applying purchase.');
            }

            $oldValue = $oldQty * $oldAvg;
            $newValue = $oldValue + $addValue;
            $newAvg = $newQty > 0 ? ($newValue / $newQty) : 0.0;
            $this->updateStock($itemId, $newQty, $newAvg);
        }
    }

    public function reversePurchase(int $purchaseId): void
    {
        foreach ($this->groupedPurchaseLines($purchaseId) as $row) {
            $itemId = (int) $row['item_id'];
            $removeQty = (float) $row['qty'];
            $removeValue = (float) $row['line_value'];

            $stock = $this->lockStockRow($itemId);
            $oldQty = (float) $stock['qty_balance'];
            $oldAvg = (float) $stock['avg_rate'];

            $newQty = $oldQty - $removeQty;
            if ($newQty < -0.0005) {
                throw new RuntimeException('Cannot reverse purchase; stock would become negative.');
            }

            $oldValue = $oldQty * $oldAvg;
            $newValue = $oldValue - $removeValue;
            if ($newValue < 0 && abs($newValue) < 0.01) {
                $newValue = 0;
            }
            $newAvg = $newQty > 0 ? ($newValue / $newQty) : 0.0;
            if ($newAvg < 0) {
                $newAvg = 0;
            }

            $this->updateStock($itemId, $newQty, $newAvg);
        }
    }

    public function applyIssue(int $issueId): void
    {
        foreach ($this->groupedIssueLines($issueId) as $row) {
            $itemId = (int) $row['item_id'];
            $issueQty = (float) $row['qty'];

            $stock = $this->lockStockRow($itemId);
            $oldQty = (float) $stock['qty_balance'];
            $oldAvg = (float) $stock['avg_rate'];

            $newQty = $oldQty - $issueQty;
            if ($newQty < -0.0005) {
                throw new RuntimeException('Insufficient stock.');
            }

            $this->updateStock($itemId, $newQty, $oldAvg);
        }
    }

    public function reverseIssue(int $issueId): void
    {
        foreach ($this->groupedIssueLines($issueId) as $row) {
            $itemId = (int) $row['item_id'];
            $addQty = (float) $row['qty'];

            $stock = $this->lockStockRow($itemId);
            $oldQty = (float) $stock['qty_balance'];
            $oldAvg = (float) $stock['avg_rate'];

            $newQty = $oldQty + $addQty;
            if ($newQty < -0.0005) {
                throw new RuntimeException('Stock cannot go negative while reversing issue.');
            }

            $this->updateStock($itemId, $newQty, $oldAvg);
        }
    }

    public function applyReturn(int $returnId): void
    {
        $rows = $this->groupedReturnLines($returnId);
        if ($rows === []) {
            return;
        }
        $this->assertReturnNotMoreThanIssue($returnId, $rows);

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $addQty = (float) $row['qty'];

            $stock = $this->lockStockRow($itemId);
            $oldQty = (float) $stock['qty_balance'];
            $oldAvg = (float) $stock['avg_rate'];

            $newQty = $oldQty + $addQty;
            if ($newQty < -0.0005) {
                throw new RuntimeException('Stock cannot go negative while applying return.');
            }

            $this->updateStock($itemId, $newQty, $oldAvg);
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private function assertReturnNotMoreThanIssue(int $returnId, array $rows): void
    {
        $issueId = $this->getReturnIssueId($returnId);

        $issuedMap = [];
        foreach ($this->groupedIssueLines($issueId) as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $issuedMap[$itemId] = (float) ($row['qty'] ?? 0);
        }

        $returnedMap = [];
        $returnedRows = $this->db->table('stone_inventory_return_lines rl')
            ->select('rl.item_id, SUM(rl.qty) as qty', false)
            ->join('stone_inventory_return_headers rh', 'rh.id = rl.return_id', 'inner')
            ->where('rh.issue_id', $issueId)
            ->where('rl.return_id !=', $returnId)
            ->groupBy('rl.item_id')
            ->get()
            ->getResultArray();

        foreach ($returnedRows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $returnedMap[$itemId] = (float) ($row['qty'] ?? 0);
        }

        foreach ($rows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $returnQty = (float) ($row['qty'] ?? 0);
            $issuedQty = (float) ($issuedMap[$itemId] ?? 0);
            $prevReturnedQty = (float) ($returnedMap[$itemId] ?? 0);
            $availableQty = $issuedQty - $prevReturnedQty;

            if ($returnQty > ($availableQty + 0.0005)) {
                $itemLabel = $this->stoneItemLabel($itemId);
                throw new RuntimeException(
                    sprintf(
                        'Return exceeds issue for item %s. Available: %s.',
                        $itemLabel,
                        number_format(max(0, $availableQty), 3)
                    )
                );
            }
        }
    }

    private function getReturnIssueId(int $returnId): int
    {
        $row = $this->db->table('stone_inventory_return_headers')
            ->select('issue_id')
            ->where('id', $returnId)
            ->get()
            ->getRowArray();

        $issueId = (int) ($row['issue_id'] ?? 0);
        if ($issueId <= 0) {
            throw new RuntimeException('Return must reference a valid issue.');
        }

        return $issueId;
    }

    private function stoneItemLabel(int $itemId): string
    {
        if ($itemId <= 0) {
            return '#0';
        }
        $item = $this->itemModel->find($itemId);
        if (! $item) {
            return '#' . $itemId;
        }

        $product = trim((string) ($item['product_name'] ?? 'Stone'));
        $type = trim((string) ($item['stone_type'] ?? ''));
        $label = trim($product . ' ' . $type);

        return $label !== '' ? $label : ('#' . $itemId);
    }

    public function reverseReturn(int $returnId): void
    {
        foreach ($this->groupedReturnLines($returnId) as $row) {
            $itemId = (int) $row['item_id'];
            $removeQty = (float) $row['qty'];

            $stock = $this->lockStockRow($itemId);
            $oldQty = (float) $stock['qty_balance'];
            $oldAvg = (float) $stock['avg_rate'];

            $newQty = $oldQty - $removeQty;
            if ($newQty < -0.0005) {
                throw new RuntimeException('Cannot reverse return; stock would become negative.');
            }

            $this->updateStock($itemId, $newQty, $oldAvg);
        }
    }

    public function applyAdjustment(int $adjustmentId, string $adjustmentType): void
    {
        $type = strtolower(trim($adjustmentType));
        if (! in_array($type, ['add', 'subtract'], true)) {
            throw new RuntimeException('Invalid adjustment type.');
        }

        foreach ($this->groupedAdjustmentLines($adjustmentId) as $row) {
            $itemId = (int) $row['item_id'];
            $deltaQty = (float) $row['qty'];
            $deltaValue = (float) $row['line_value'];

            $stock = $this->lockStockRow($itemId);
            $oldQty = (float) $stock['qty_balance'];
            $oldAvg = (float) $stock['avg_rate'];

            if ($type === 'add') {
                $newQty = $oldQty + $deltaQty;
                if ($newQty < -0.0005) {
                    throw new RuntimeException('Stock cannot go negative while applying adjustment.');
                }

                $oldValue = $oldQty * $oldAvg;
                $newValue = $oldValue + $deltaValue;
                $newAvg = $newQty > 0 ? ($newValue / $newQty) : 0.0;
                if ($newAvg < 0) {
                    $newAvg = 0;
                }
                $this->updateStock($itemId, $newQty, $newAvg);
                continue;
            }

            $newQty = $oldQty - $deltaQty;
            if ($newQty < -0.0005) {
                throw new RuntimeException('Insufficient stock for subtract adjustment.');
            }
            $this->updateStock($itemId, $newQty, $oldAvg);
        }
    }

    public function reverseAdjustment(int $adjustmentId, string $adjustmentType): void
    {
        $type = strtolower(trim($adjustmentType));
        if (! in_array($type, ['add', 'subtract'], true)) {
            throw new RuntimeException('Invalid adjustment type.');
        }

        foreach ($this->groupedAdjustmentLines($adjustmentId) as $row) {
            $itemId = (int) $row['item_id'];
            $deltaQty = (float) $row['qty'];
            $deltaValue = (float) $row['line_value'];

            $stock = $this->lockStockRow($itemId);
            $oldQty = (float) $stock['qty_balance'];
            $oldAvg = (float) $stock['avg_rate'];

            if ($type === 'add') {
                $newQty = $oldQty - $deltaQty;
                if ($newQty < -0.0005) {
                    throw new RuntimeException('Cannot reverse add adjustment; stock would become negative.');
                }

                $oldValue = $oldQty * $oldAvg;
                $newValue = $oldValue - $deltaValue;
                if ($newValue < 0 && abs($newValue) < 0.01) {
                    $newValue = 0;
                }
                $newAvg = $newQty > 0 ? ($newValue / $newQty) : 0.0;
                if ($newAvg < 0) {
                    $newAvg = 0;
                }
                $this->updateStock($itemId, $newQty, $newAvg);
                continue;
            }

            $newQty = $oldQty + $deltaQty;
            if ($newQty < -0.0005) {
                throw new RuntimeException('Cannot reverse subtract adjustment; stock would become negative.');
            }
            $this->updateStock($itemId, $newQty, $oldAvg);
        }
    }

    private function ensureStockRow(int $itemId): void
    {
        $this->db->query(
            'INSERT INTO stone_inventory_stock (item_id, qty_balance, avg_rate, stock_value, updated_at)
             VALUES (?, 0, 0, 0, NOW())
             ON DUPLICATE KEY UPDATE item_id = item_id',
            [$itemId]
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function lockStockRow(int $itemId): array
    {
        $this->ensureStockRow($itemId);

        $row = $this->db->query(
            'SELECT item_id, qty_balance, avg_rate, stock_value
             FROM stone_inventory_stock
             WHERE item_id = ?
             FOR UPDATE',
            [$itemId]
        )->getRowArray();

        if (! $row) {
            throw new RuntimeException('Unable to lock stock row.');
        }

        return $row;
    }

    private function updateStock(int $itemId, float $qty, float $avgRate): void
    {
        $qty = round(max(0, $qty), 3);
        $avgRate = round(max(0, $avgRate), 2);
        $stockValue = round($qty * $avgRate, 2);

        $this->stockModel->update($itemId, [
            'qty_balance' => $qty,
            'avg_rate' => $avgRate,
            'stock_value' => $stockValue,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function groupedPurchaseLines(int $purchaseId): array
    {
        return $this->db->table('stone_inventory_purchase_lines')
            ->select('item_id, SUM(qty) as qty, SUM(line_value) as line_value', false)
            ->where('purchase_id', $purchaseId)
            ->groupBy('item_id')
            ->orderBy('item_id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function groupedIssueLines(int $issueId): array
    {
        return $this->db->table('stone_inventory_issue_lines')
            ->select('item_id, SUM(qty) as qty', false)
            ->where('issue_id', $issueId)
            ->groupBy('item_id')
            ->orderBy('item_id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function groupedReturnLines(int $returnId): array
    {
        return $this->db->table('stone_inventory_return_lines')
            ->select('item_id, SUM(qty) as qty', false)
            ->where('return_id', $returnId)
            ->groupBy('item_id')
            ->orderBy('item_id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function groupedAdjustmentLines(int $adjustmentId): array
    {
        return $this->db->table('stone_inventory_adjustment_lines')
            ->select('item_id, SUM(qty) as qty, SUM(line_value) as line_value', false)
            ->where('adjustment_id', $adjustmentId)
            ->groupBy('item_id')
            ->orderBy('item_id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string,mixed> $data
     * @return array{product_name:string,stone_type:?string}
     */
    private function normalizeSignature(array $data): array
    {
        $productName = trim((string) ($data['product_name'] ?? ''));
        $stoneType = $this->stringOrNull($data['stone_type'] ?? null);

        if ($productName !== '') {
            $productName = ucwords(strtolower($productName));
        }
        if ($stoneType !== null) {
            $stoneType = ucwords(strtolower($stoneType));
        }

        return [
            'product_name' => $productName,
            'stone_type' => $stoneType,
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }
}
