<?php

namespace App\Services\DiamondInventory;

use App\Models\IssueLineModel;
use App\Models\ItemModel;
use App\Models\PurchaseLineModel;
use App\Models\StockModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class StockService
{
    private BaseConnection $db;
    private ItemModel $itemModel;
    private StockModel $stockModel;
    private PurchaseLineModel $purchaseLineModel;
    private IssueLineModel $issueLineModel;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->itemModel = new ItemModel();
        $this->stockModel = new StockModel();
        $this->purchaseLineModel = new PurchaseLineModel();
        $this->issueLineModel = new IssueLineModel();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function upsertItemFromSignature(array $data): int
    {
        $signature = $this->normalizeSignature($data);
        $this->validateSignature($signature);

        $existing = $this->db->query(
            'SELECT id FROM items
             WHERE diamond_type = ?
               AND shape <=> ?
               AND chalni_from <=> ?
               AND chalni_to <=> ?
               AND color <=> ?
               AND clarity <=> ?
               AND cut <=> ?
             LIMIT 1',
            [
                $signature['diamond_type'],
                $signature['shape'],
                $signature['chalni_from'],
                $signature['chalni_to'],
                $signature['color'],
                $signature['clarity'],
                $signature['cut'],
            ]
        )->getRowArray();

        if ($existing) {
            $itemId = (int) $existing['id'];
            $this->ensureStockRow($itemId);
            return $itemId;
        }

        $itemId = (int) $this->itemModel->insert([
            'diamond_type' => $signature['diamond_type'],
            'shape'        => $signature['shape'],
            'chalni_from'  => $signature['chalni_from'],
            'chalni_to'    => $signature['chalni_to'],
            'color'        => $signature['color'],
            'clarity'      => $signature['clarity'],
            'cut'          => $signature['cut'],
            'remarks'      => $this->stringOrNull($data['remarks'] ?? null),
        ], true);

        if ($itemId <= 0) {
            throw new RuntimeException('Unable to create item.');
        }

        $this->ensureStockRow($itemId);
        return $itemId;
    }

    public function applyPurchase(int $purchaseId): void
    {
        $rows = $this->groupedPurchaseLines($purchaseId);
        if ($rows === []) {
            return;
        }

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $addPcs = (float) $row['pcs'];
            $addCarat = (float) $row['carat'];
            $addValue = (float) $row['line_value'];

            $stock = $this->lockStockRow($itemId);
            $oldPcs = (float) $stock['pcs_balance'];
            $oldCarat = (float) $stock['carat_balance'];
            $oldAvg = (float) $stock['avg_cost_per_carat'];

            $newPcs = $oldPcs + $addPcs;
            $newCarat = $oldCarat + $addCarat;
            if ($newPcs < -0.0005 || $newCarat < -0.0005) {
                throw new RuntimeException('Stock cannot go negative while applying purchase.');
            }

            $oldValue = $oldCarat * $oldAvg;
            $newValue = $oldValue + $addValue;
            $newAvg = $newCarat > 0 ? ($newValue / $newCarat) : 0.0;
            if ($newAvg < 0) {
                $newAvg = 0;
            }
            $stockValue = $newCarat * $newAvg;

            $this->updateStock($itemId, $newPcs, $newCarat, $newAvg, $stockValue);
        }
    }

    public function reversePurchase(int $purchaseId): void
    {
        $rows = $this->groupedPurchaseLines($purchaseId);
        if ($rows === []) {
            return;
        }

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $removePcs = (float) $row['pcs'];
            $removeCarat = (float) $row['carat'];
            $removeValue = (float) $row['line_value'];

            $stock = $this->lockStockRow($itemId);
            $oldPcs = (float) $stock['pcs_balance'];
            $oldCarat = (float) $stock['carat_balance'];
            $oldAvg = (float) $stock['avg_cost_per_carat'];

            $newPcs = $oldPcs - $removePcs;
            $newCarat = $oldCarat - $removeCarat;
            if ($newCarat < -0.0005) {
                throw new RuntimeException('Cannot reverse purchase; stock would become negative.');
            }

            $oldValue = $oldCarat * $oldAvg;
            $newValue = $oldValue - $removeValue;
            if ($newValue < 0 && abs($newValue) < 0.01) {
                $newValue = 0;
            }
            $newAvg = $newCarat > 0 ? ($newValue / $newCarat) : 0.0;
            if ($newAvg < 0) {
                $newAvg = 0;
            }
            $stockValue = $newCarat * $newAvg;

            $this->updateStock($itemId, $newPcs, $newCarat, $newAvg, $stockValue);
        }
    }

    public function applyIssue(int $issueId): void
    {
        $rows = $this->groupedIssueLines($issueId);
        if ($rows === []) {
            return;
        }

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $issuePcs = (float) $row['pcs'];
            $issueCarat = (float) $row['carat'];

            $stock = $this->lockStockRow($itemId);
            $oldPcs = (float) $stock['pcs_balance'];
            $oldCarat = (float) $stock['carat_balance'];
            $oldAvg = (float) $stock['avg_cost_per_carat'];

            $newPcs = $oldPcs - $issuePcs;
            $newCarat = $oldCarat - $issueCarat;
            if ($newCarat < -0.0005) {
                throw new RuntimeException('Insufficient stock.');
            }

            $stockValue = $newCarat * $oldAvg;
            $this->updateStock($itemId, $newPcs, $newCarat, $oldAvg, $stockValue);
        }
    }

    public function reverseIssue(int $issueId): void
    {
        $rows = $this->groupedIssueLines($issueId);
        if ($rows === []) {
            return;
        }

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $addPcs = (float) $row['pcs'];
            $addCarat = (float) $row['carat'];

            $stock = $this->lockStockRow($itemId);
            $oldPcs = (float) $stock['pcs_balance'];
            $oldCarat = (float) $stock['carat_balance'];
            $oldAvg = (float) $stock['avg_cost_per_carat'];

            $newPcs = $oldPcs + $addPcs;
            $newCarat = $oldCarat + $addCarat;
            if ($newCarat < -0.0005) {
                throw new RuntimeException('Stock cannot go negative while reversing issue.');
            }

            $stockValue = $newCarat * $oldAvg;
            $this->updateStock($itemId, $newPcs, $newCarat, $oldAvg, $stockValue);
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
            $addPcs = (float) $row['pcs'];
            $addCarat = (float) $row['carat'];

            $stock = $this->lockStockRow($itemId);
            $oldPcs = (float) $stock['pcs_balance'];
            $oldCarat = (float) $stock['carat_balance'];
            $oldAvg = (float) $stock['avg_cost_per_carat'];

            $newPcs = $oldPcs + $addPcs;
            $newCarat = $oldCarat + $addCarat;
            if ($newCarat < -0.0005) {
                throw new RuntimeException('Stock cannot go negative while applying return.');
            }

            $stockValue = $newCarat * $oldAvg;
            $this->updateStock($itemId, $newPcs, $newCarat, $oldAvg, $stockValue);
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
            $issuedMap[$itemId] = [
                'pcs' => (float) ($row['pcs'] ?? 0),
                'carat' => (float) ($row['carat'] ?? 0),
            ];
        }

        $returnedMap = [];
        $returnedRows = $this->db->table('return_lines rl')
            ->select('rl.item_id, SUM(rl.pcs) as pcs, SUM(rl.carat) as carat', false)
            ->join('return_headers rh', 'rh.id = rl.return_id', 'inner')
            ->where('rh.issue_id', $issueId)
            ->where('rl.return_id !=', $returnId)
            ->groupBy('rl.item_id')
            ->get()
            ->getResultArray();

        foreach ($returnedRows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $returnedMap[$itemId] = [
                'pcs' => (float) ($row['pcs'] ?? 0),
                'carat' => (float) ($row['carat'] ?? 0),
            ];
        }

        foreach ($rows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $returnPcs = (float) ($row['pcs'] ?? 0);
            $returnCarat = (float) ($row['carat'] ?? 0);

            $issuedPcs = (float) ($issuedMap[$itemId]['pcs'] ?? 0);
            $issuedCarat = (float) ($issuedMap[$itemId]['carat'] ?? 0);
            $prevReturnedPcs = (float) ($returnedMap[$itemId]['pcs'] ?? 0);
            $prevReturnedCarat = (float) ($returnedMap[$itemId]['carat'] ?? 0);

            $availablePcs = $issuedPcs - $prevReturnedPcs;
            $availableCarat = $issuedCarat - $prevReturnedCarat;

            if ($returnPcs > ($availablePcs + 0.0005) || $returnCarat > ($availableCarat + 0.0005)) {
                $itemLabel = $this->diamondItemLabel($itemId);
                throw new RuntimeException(
                    sprintf(
                        'Return exceeds issue for item %s. Available: %s pcs / %s cts.',
                        $itemLabel,
                        number_format(max(0, $availablePcs), 3),
                        number_format(max(0, $availableCarat), 3)
                    )
                );
            }
        }
    }

    private function getReturnIssueId(int $returnId): int
    {
        $row = $this->db->table('return_headers')
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

    private function diamondItemLabel(int $itemId): string
    {
        if ($itemId <= 0) {
            return '#0';
        }
        $item = $this->itemModel->find($itemId);
        if (! $item) {
            return '#' . $itemId;
        }

        $type = trim((string) ($item['diamond_type'] ?? 'Diamond'));
        $shape = trim((string) ($item['shape'] ?? ''));
        return trim($type . ' ' . $shape) !== '' ? trim($type . ' ' . $shape) : ('#' . $itemId);
    }

    public function reverseReturn(int $returnId): void
    {
        $rows = $this->groupedReturnLines($returnId);
        if ($rows === []) {
            return;
        }

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $removePcs = (float) $row['pcs'];
            $removeCarat = (float) $row['carat'];

            $stock = $this->lockStockRow($itemId);
            $oldPcs = (float) $stock['pcs_balance'];
            $oldCarat = (float) $stock['carat_balance'];
            $oldAvg = (float) $stock['avg_cost_per_carat'];

            $newPcs = $oldPcs - $removePcs;
            $newCarat = $oldCarat - $removeCarat;
            if ($newCarat < -0.0005) {
                throw new RuntimeException('Cannot reverse return; stock would become negative.');
            }

            $stockValue = $newCarat * $oldAvg;
            $this->updateStock($itemId, $newPcs, $newCarat, $oldAvg, $stockValue);
        }
    }

    public function applyAdjustment(int $adjustmentId, string $adjustmentType): void
    {
        $rows = $this->groupedAdjustmentLines($adjustmentId);
        if ($rows === []) {
            return;
        }

        $type = strtolower(trim($adjustmentType));
        if (! in_array($type, ['add', 'subtract'], true)) {
            throw new RuntimeException('Invalid adjustment type.');
        }

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $deltaPcs = (float) $row['pcs'];
            $deltaCarat = (float) $row['carat'];
            $deltaValue = (float) $row['line_value'];

            $stock = $this->lockStockRow($itemId);
            $oldPcs = (float) $stock['pcs_balance'];
            $oldCarat = (float) $stock['carat_balance'];
            $oldAvg = (float) $stock['avg_cost_per_carat'];

            if ($type === 'add') {
                $newPcs = $oldPcs + $deltaPcs;
                $newCarat = $oldCarat + $deltaCarat;
                if ($newCarat < -0.0005) {
                    throw new RuntimeException('Stock cannot go negative while applying adjustment.');
                }

                $oldValue = $oldCarat * $oldAvg;
                $newValue = $oldValue + $deltaValue;
                $newAvg = $newCarat > 0 ? ($newValue / $newCarat) : 0.0;
                if ($newAvg < 0) {
                    $newAvg = 0;
                }
                $stockValue = $newCarat * $newAvg;
                $this->updateStock($itemId, $newPcs, $newCarat, $newAvg, $stockValue);
                continue;
            }

            $newPcs = $oldPcs - $deltaPcs;
            $newCarat = $oldCarat - $deltaCarat;
            if ($newCarat < -0.0005) {
                throw new RuntimeException('Insufficient stock for subtract adjustment.');
            }

            $stockValue = $newCarat * $oldAvg;
            $this->updateStock($itemId, $newPcs, $newCarat, $oldAvg, $stockValue);
        }
    }

    public function reverseAdjustment(int $adjustmentId, string $adjustmentType): void
    {
        $rows = $this->groupedAdjustmentLines($adjustmentId);
        if ($rows === []) {
            return;
        }

        $type = strtolower(trim($adjustmentType));
        if (! in_array($type, ['add', 'subtract'], true)) {
            throw new RuntimeException('Invalid adjustment type.');
        }

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $deltaPcs = (float) $row['pcs'];
            $deltaCarat = (float) $row['carat'];
            $deltaValue = (float) $row['line_value'];

            $stock = $this->lockStockRow($itemId);
            $oldPcs = (float) $stock['pcs_balance'];
            $oldCarat = (float) $stock['carat_balance'];
            $oldAvg = (float) $stock['avg_cost_per_carat'];

            if ($type === 'add') {
                $newPcs = $oldPcs - $deltaPcs;
                $newCarat = $oldCarat - $deltaCarat;
                if ($newCarat < -0.0005) {
                    throw new RuntimeException('Cannot reverse add adjustment; stock would go negative.');
                }

                $oldValue = $oldCarat * $oldAvg;
                $newValue = $oldValue - $deltaValue;
                if ($newValue < 0 && abs($newValue) < 0.01) {
                    $newValue = 0;
                }
                $newAvg = $newCarat > 0 ? ($newValue / $newCarat) : 0.0;
                if ($newAvg < 0) {
                    $newAvg = 0;
                }
                $stockValue = $newCarat * $newAvg;
                $this->updateStock($itemId, $newPcs, $newCarat, $newAvg, $stockValue);
                continue;
            }

            $newPcs = $oldPcs + $deltaPcs;
            $newCarat = $oldCarat + $deltaCarat;
            if ($newCarat < -0.0005) {
                throw new RuntimeException('Cannot reverse subtract adjustment; stock would go negative.');
            }

            $stockValue = $newCarat * $oldAvg;
            $this->updateStock($itemId, $newPcs, $newCarat, $oldAvg, $stockValue);
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function normalizeSignature(array $data): array
    {
        $diamondType = trim((string) ($data['diamond_type'] ?? ''));
        $shape = $this->stringOrNull($data['shape'] ?? null);
        $color = $this->stringOrNull($data['color'] ?? null);
        $clarity = $this->stringOrNull($data['clarity'] ?? null);
        $cut = $this->stringOrNull($data['cut'] ?? null);
        $chalniFrom = $this->chalniOrNull($data['chalni_from'] ?? null);
        $chalniTo = $this->chalniOrNull($data['chalni_to'] ?? null);

        if ($diamondType !== '') {
            $diamondType = ucwords(strtolower($diamondType));
        }
        if ($shape !== null) {
            $shape = ucwords(strtolower($shape));
        }
        if ($color !== null) {
            $color = strtoupper($color);
        }
        if ($clarity !== null) {
            $clarity = strtoupper($clarity);
        }
        if ($cut !== null) {
            $cut = ucwords(strtolower($cut));
        }

        return [
            'diamond_type' => $diamondType,
            'shape' => $shape,
            'chalni_from' => $chalniFrom,
            'chalni_to' => $chalniTo,
            'color' => $color,
            'clarity' => $clarity,
            'cut' => $cut,
        ];
    }

    /**
     * @param array<string,mixed> $signature
     */
    private function validateSignature(array $signature): void
    {
        if ((string) $signature['diamond_type'] === '') {
            throw new RuntimeException('Diamond type is required.');
        }

        $from = $signature['chalni_from'];
        $to = $signature['chalni_to'];
        if (($from === null && $to !== null) || ($from !== null && $to === null)) {
            throw new RuntimeException('Both chalni from and chalni to are required when chalni is used.');
        }
        if ($from !== null && ! ctype_digit((string) $from)) {
            throw new RuntimeException('Chalni from must contain digits only.');
        }
        if ($to !== null && ! ctype_digit((string) $to)) {
            throw new RuntimeException('Chalni to must contain digits only.');
        }
        if ($from !== null && $to !== null && $this->chalniNumericValue($from) > $this->chalniNumericValue($to)) {
            throw new RuntimeException('Chalni from must be less than or equal to chalni to.');
        }
    }

    private function ensureStockRow(int $itemId): void
    {
        $this->db->query(
            'INSERT INTO stock (item_id, pcs_balance, carat_balance, avg_cost_per_carat, stock_value, updated_at)
             VALUES (?, 0, 0, 0, 0, NOW())
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
            'SELECT item_id, pcs_balance, carat_balance, avg_cost_per_carat, stock_value FROM stock WHERE item_id = ? FOR UPDATE',
            [$itemId]
        )->getRowArray();

        if (! $row) {
            throw new RuntimeException('Unable to lock stock row.');
        }

        return $row;
    }

    private function updateStock(int $itemId, float $pcs, float $carat, float $avg, float $stockValue): void
    {
        $pcs = round(max(0, $pcs), 3);
        $carat = round(max(0, $carat), 3);
        $avg = round(max(0, $avg), 2);
        $stockValue = round(max(0, $stockValue), 2);

        $this->stockModel->update($itemId, [
            'pcs_balance' => $pcs,
            'carat_balance' => $carat,
            'avg_cost_per_carat' => $avg,
            'stock_value' => $stockValue,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function groupedPurchaseLines(int $purchaseId): array
    {
        $rows = $this->db->table('purchase_lines')
            ->select('item_id, SUM(pcs) as pcs, SUM(carat) as carat, SUM(line_value) as line_value', false)
            ->where('purchase_id', $purchaseId)
            ->groupBy('item_id')
            ->orderBy('item_id', 'ASC')
            ->get()
            ->getResultArray();

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function groupedIssueLines(int $issueId): array
    {
        $rows = $this->db->table('issue_lines')
            ->select('item_id, SUM(pcs) as pcs, SUM(carat) as carat', false)
            ->where('issue_id', $issueId)
            ->groupBy('item_id')
            ->orderBy('item_id', 'ASC')
            ->get()
            ->getResultArray();

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function groupedReturnLines(int $returnId): array
    {
        $rows = $this->db->table('return_lines')
            ->select('item_id, SUM(pcs) as pcs, SUM(carat) as carat', false)
            ->where('return_id', $returnId)
            ->groupBy('item_id')
            ->orderBy('item_id', 'ASC')
            ->get()
            ->getResultArray();

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function groupedAdjustmentLines(int $adjustmentId): array
    {
        $rows = $this->db->table('diamond_inventory_adjustment_lines')
            ->select('item_id, SUM(pcs) as pcs, SUM(carat) as carat, SUM(line_value) as line_value', false)
            ->where('adjustment_id', $adjustmentId)
            ->groupBy('item_id')
            ->orderBy('item_id', 'ASC')
            ->get()
            ->getResultArray();

        return $rows;
    }

    private function stringOrNull(mixed $value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    private function chalniOrNull(mixed $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }

        return $v;
    }

    private function chalniNumericValue(string $value): int
    {
        return (int) ltrim($value, '0');
    }
}
