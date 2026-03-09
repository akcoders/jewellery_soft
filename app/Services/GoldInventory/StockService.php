<?php

namespace App\Services\GoldInventory;

use App\Models\GoldInventoryItemModel;
use App\Models\GoldInventoryStockModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class StockService
{
    private BaseConnection $db;
    private GoldInventoryItemModel $itemModel;
    private GoldInventoryStockModel $stockModel;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->itemModel = new GoldInventoryItemModel();
        $this->stockModel = new GoldInventoryStockModel();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function upsertItemFromSignature(array $data): int
    {
        $signature = $this->normalizeSignature($data);

        $existing = $this->db->query(
            'SELECT id FROM gold_inventory_items
             WHERE gold_purity_id <=> ?
               AND purity_code <=> ?
               AND color_name <=> ?
               AND form_type <=> ?
             LIMIT 1',
            [
                $signature['gold_purity_id'],
                $signature['purity_code'],
                $signature['color_name'],
                $signature['form_type'],
            ]
        )->getRowArray();

        if ($existing) {
            $itemId = (int) $existing['id'];
            $this->ensureStockRow($itemId);
            return $itemId;
        }

        $itemId = (int) $this->itemModel->insert([
            'gold_purity_id' => $signature['gold_purity_id'],
            'purity_code' => $signature['purity_code'],
            'purity_percent' => $signature['purity_percent'],
            'color_name' => $signature['color_name'],
            'form_type' => $signature['form_type'],
            'remarks' => $this->stringOrNull($data['remarks'] ?? null),
        ], true);

        if ($itemId <= 0) {
            throw new RuntimeException('Unable to create gold inventory item.');
        }

        $this->ensureStockRow($itemId);
        return $itemId;
    }

    public function applyPurchase(int $purchaseId, array $meta = []): void
    {
        $rows = $this->groupedPurchaseLines($purchaseId);
        if ($rows === []) {
            return;
        }

        $txnDate = $this->resolveTxnDate($meta['txn_date'] ?? null);
        $referenceTable = (string) ($meta['reference_table'] ?? 'gold_inventory_purchase_headers');
        $txnType = (string) ($meta['txn_type'] ?? 'purchase');

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $addWeight = (float) $row['weight_gm'];
            $addFine = (float) $row['fine_weight_gm'];
            $addValue = (float) $row['line_value'];

            $stock = $this->lockStockRow($itemId);
            $oldWeight = (float) $stock['weight_balance_gm'];
            $oldFine = (float) $stock['fine_balance_gm'];
            $oldAvg = (float) $stock['avg_cost_per_gm'];

            $newWeight = $oldWeight + $addWeight;
            $newFine = $oldFine + $addFine;
            if ($newWeight < -0.0005 || $newFine < -0.0005) {
                throw new RuntimeException('Stock cannot go negative while applying purchase.');
            }

            $oldValue = $oldWeight * $oldAvg;
            $newValue = $oldValue + $addValue;
            $newAvg = $newWeight > 0 ? ($newValue / $newWeight) : 0.0;
            if ($newAvg < 0) {
                $newAvg = 0;
            }
            $stockValue = $newWeight * $newAvg;

            $this->updateStock($itemId, $newWeight, $newFine, $newAvg, $stockValue);
            $this->insertLedgerEntry([
                'txn_date' => $txnDate,
                'txn_type' => $txnType,
                'reference_table' => $referenceTable,
                'reference_id' => $purchaseId,
                'order_id' => $this->nullableInt($meta['order_id'] ?? null),
                'karigar_id' => $this->nullableInt($meta['karigar_id'] ?? null),
                'location_id' => $this->nullableInt($meta['location_id'] ?? null),
                'item_id' => $itemId,
                'debit_weight_gm' => round($addWeight, 3),
                'credit_weight_gm' => 0,
                'debit_fine_gm' => round($addFine, 3),
                'credit_fine_gm' => 0,
                'balance_weight_gm' => round($newWeight, 3),
                'balance_fine_gm' => round($newFine, 3),
                'rate_per_gm' => $addWeight > 0 ? round($addValue / $addWeight, 2) : null,
                'line_value' => round($addValue, 2),
                'notes' => $this->stringOrNull($meta['notes'] ?? null),
                'created_by' => $this->nullableInt($meta['created_by'] ?? null),
            ]);
        }
    }

    public function reversePurchase(int $purchaseId, array $meta = []): void
    {
        $rows = $this->groupedPurchaseLines($purchaseId);
        if ($rows === []) {
            return;
        }

        $txnDate = $this->resolveTxnDate($meta['txn_date'] ?? null);
        $referenceTable = (string) ($meta['reference_table'] ?? 'gold_inventory_purchase_headers');
        $txnType = (string) ($meta['txn_type'] ?? 'purchase_reverse');

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $removeWeight = (float) $row['weight_gm'];
            $removeFine = (float) $row['fine_weight_gm'];
            $removeValue = (float) $row['line_value'];

            $stock = $this->lockStockRow($itemId);
            $oldWeight = (float) $stock['weight_balance_gm'];
            $oldFine = (float) $stock['fine_balance_gm'];
            $oldAvg = (float) $stock['avg_cost_per_gm'];

            $newWeight = $oldWeight - $removeWeight;
            $newFine = $oldFine - $removeFine;
            if ($newWeight < -0.0005 || $newFine < -0.0005) {
                throw new RuntimeException('Cannot reverse purchase; stock would become negative.');
            }

            $oldValue = $oldWeight * $oldAvg;
            $newValue = $oldValue - $removeValue;
            if ($newValue < 0 && abs($newValue) < 0.01) {
                $newValue = 0;
            }

            $newAvg = $newWeight > 0 ? ($newValue / $newWeight) : 0.0;
            if ($newAvg < 0) {
                $newAvg = 0;
            }
            $stockValue = $newWeight * $newAvg;

            $this->updateStock($itemId, $newWeight, $newFine, $newAvg, $stockValue);
            $this->insertLedgerEntry([
                'txn_date' => $txnDate,
                'txn_type' => $txnType,
                'reference_table' => $referenceTable,
                'reference_id' => $purchaseId,
                'order_id' => $this->nullableInt($meta['order_id'] ?? null),
                'karigar_id' => $this->nullableInt($meta['karigar_id'] ?? null),
                'location_id' => $this->nullableInt($meta['location_id'] ?? null),
                'item_id' => $itemId,
                'debit_weight_gm' => 0,
                'credit_weight_gm' => round($removeWeight, 3),
                'debit_fine_gm' => 0,
                'credit_fine_gm' => round($removeFine, 3),
                'balance_weight_gm' => round($newWeight, 3),
                'balance_fine_gm' => round($newFine, 3),
                'rate_per_gm' => $removeWeight > 0 ? round($removeValue / $removeWeight, 2) : null,
                'line_value' => round($removeValue, 2),
                'notes' => $this->stringOrNull($meta['notes'] ?? null),
                'created_by' => $this->nullableInt($meta['created_by'] ?? null),
            ]);
        }
    }

    public function applyIssue(int $issueId, array $meta = []): void
    {
        $rows = $this->groupedIssueLines($issueId);
        if ($rows === []) {
            return;
        }

        $txnDate = $this->resolveTxnDate($meta['txn_date'] ?? null);
        $referenceTable = (string) ($meta['reference_table'] ?? 'gold_inventory_issue_headers');
        $txnType = (string) ($meta['txn_type'] ?? 'issue');

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $issueWeight = (float) $row['weight_gm'];
            $issueFine = (float) $row['fine_weight_gm'];

            $stock = $this->lockStockRow($itemId);
            $oldWeight = (float) $stock['weight_balance_gm'];
            $oldFine = (float) $stock['fine_balance_gm'];
            $oldAvg = (float) $stock['avg_cost_per_gm'];

            $newWeight = $oldWeight - $issueWeight;
            $newFine = $oldFine - $issueFine;
            if ($newWeight < -0.0005 || $newFine < -0.0005) {
                throw new RuntimeException('Insufficient gold stock.');
            }

            $stockValue = $newWeight * $oldAvg;
            $this->updateStock($itemId, $newWeight, $newFine, $oldAvg, $stockValue);

            $lineValue = $issueWeight * $oldAvg;
            $this->insertLedgerEntry([
                'txn_date' => $txnDate,
                'txn_type' => $txnType,
                'reference_table' => $referenceTable,
                'reference_id' => $issueId,
                'order_id' => $this->nullableInt($meta['order_id'] ?? null),
                'karigar_id' => $this->nullableInt($meta['karigar_id'] ?? null),
                'location_id' => $this->nullableInt($meta['location_id'] ?? null),
                'item_id' => $itemId,
                'debit_weight_gm' => 0,
                'credit_weight_gm' => round($issueWeight, 3),
                'debit_fine_gm' => 0,
                'credit_fine_gm' => round($issueFine, 3),
                'balance_weight_gm' => round($newWeight, 3),
                'balance_fine_gm' => round($newFine, 3),
                'rate_per_gm' => round($oldAvg, 2),
                'line_value' => round($lineValue, 2),
                'notes' => $this->stringOrNull($meta['notes'] ?? null),
                'created_by' => $this->nullableInt($meta['created_by'] ?? null),
            ]);
        }
    }

    public function reverseIssue(int $issueId, array $meta = []): void
    {
        $rows = $this->groupedIssueLines($issueId);
        if ($rows === []) {
            return;
        }

        $txnDate = $this->resolveTxnDate($meta['txn_date'] ?? null);
        $referenceTable = (string) ($meta['reference_table'] ?? 'gold_inventory_issue_headers');
        $txnType = (string) ($meta['txn_type'] ?? 'issue_reverse');

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $addWeight = (float) $row['weight_gm'];
            $addFine = (float) $row['fine_weight_gm'];

            $stock = $this->lockStockRow($itemId);
            $oldWeight = (float) $stock['weight_balance_gm'];
            $oldFine = (float) $stock['fine_balance_gm'];
            $oldAvg = (float) $stock['avg_cost_per_gm'];

            $newWeight = $oldWeight + $addWeight;
            $newFine = $oldFine + $addFine;
            if ($newWeight < -0.0005 || $newFine < -0.0005) {
                throw new RuntimeException('Stock cannot go negative while reversing issue.');
            }

            $stockValue = $newWeight * $oldAvg;
            $this->updateStock($itemId, $newWeight, $newFine, $oldAvg, $stockValue);

            $lineValue = $addWeight * $oldAvg;
            $this->insertLedgerEntry([
                'txn_date' => $txnDate,
                'txn_type' => $txnType,
                'reference_table' => $referenceTable,
                'reference_id' => $issueId,
                'order_id' => $this->nullableInt($meta['order_id'] ?? null),
                'karigar_id' => $this->nullableInt($meta['karigar_id'] ?? null),
                'location_id' => $this->nullableInt($meta['location_id'] ?? null),
                'item_id' => $itemId,
                'debit_weight_gm' => round($addWeight, 3),
                'credit_weight_gm' => 0,
                'debit_fine_gm' => round($addFine, 3),
                'credit_fine_gm' => 0,
                'balance_weight_gm' => round($newWeight, 3),
                'balance_fine_gm' => round($newFine, 3),
                'rate_per_gm' => round($oldAvg, 2),
                'line_value' => round($lineValue, 2),
                'notes' => $this->stringOrNull($meta['notes'] ?? null),
                'created_by' => $this->nullableInt($meta['created_by'] ?? null),
            ]);
        }
    }

    public function applyReturn(int $returnId, array $meta = []): void
    {
        $rows = $this->groupedReturnLines($returnId);
        if ($rows === []) {
            return;
        }
        $this->assertReturnNotMoreThanIssue($returnId, $rows);

        $txnDate = $this->resolveTxnDate($meta['txn_date'] ?? null);
        $referenceTable = (string) ($meta['reference_table'] ?? 'gold_inventory_return_headers');
        $txnType = (string) ($meta['txn_type'] ?? 'return');

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $addWeight = (float) $row['weight_gm'];
            $addFine = (float) $row['fine_weight_gm'];

            $stock = $this->lockStockRow($itemId);
            $oldWeight = (float) $stock['weight_balance_gm'];
            $oldFine = (float) $stock['fine_balance_gm'];
            $oldAvg = (float) $stock['avg_cost_per_gm'];

            $newWeight = $oldWeight + $addWeight;
            $newFine = $oldFine + $addFine;
            if ($newWeight < -0.0005 || $newFine < -0.0005) {
                throw new RuntimeException('Stock cannot go negative while applying return.');
            }

            $stockValue = $newWeight * $oldAvg;
            $this->updateStock($itemId, $newWeight, $newFine, $oldAvg, $stockValue);

            $lineValue = $addWeight * $oldAvg;
            $this->insertLedgerEntry([
                'txn_date' => $txnDate,
                'txn_type' => $txnType,
                'reference_table' => $referenceTable,
                'reference_id' => $returnId,
                'order_id' => $this->nullableInt($meta['order_id'] ?? null),
                'karigar_id' => $this->nullableInt($meta['karigar_id'] ?? null),
                'location_id' => $this->nullableInt($meta['location_id'] ?? null),
                'item_id' => $itemId,
                'debit_weight_gm' => round($addWeight, 3),
                'credit_weight_gm' => 0,
                'debit_fine_gm' => round($addFine, 3),
                'credit_fine_gm' => 0,
                'balance_weight_gm' => round($newWeight, 3),
                'balance_fine_gm' => round($newFine, 3),
                'rate_per_gm' => round($oldAvg, 2),
                'line_value' => round($lineValue, 2),
                'notes' => $this->stringOrNull($meta['notes'] ?? null),
                'created_by' => $this->nullableInt($meta['created_by'] ?? null),
            ]);
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
                'weight_gm' => (float) ($row['weight_gm'] ?? 0),
                'fine_weight_gm' => (float) ($row['fine_weight_gm'] ?? 0),
            ];
        }

        $returnedMap = [];
        $returnedRows = $this->db->table('gold_inventory_return_lines rl')
            ->select('rl.item_id, SUM(rl.weight_gm) as weight_gm, SUM(rl.fine_weight_gm) as fine_weight_gm', false)
            ->join('gold_inventory_return_headers rh', 'rh.id = rl.return_id', 'inner')
            ->where('rh.issue_id', $issueId)
            ->where('rl.return_id !=', $returnId)
            ->groupBy('rl.item_id')
            ->get()
            ->getResultArray();

        foreach ($returnedRows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $returnedMap[$itemId] = [
                'weight_gm' => (float) ($row['weight_gm'] ?? 0),
                'fine_weight_gm' => (float) ($row['fine_weight_gm'] ?? 0),
            ];
        }

        foreach ($rows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $returnWeight = (float) ($row['weight_gm'] ?? 0);
            $returnFine = (float) ($row['fine_weight_gm'] ?? 0);

            $issuedWeight = (float) ($issuedMap[$itemId]['weight_gm'] ?? 0);
            $issuedFine = (float) ($issuedMap[$itemId]['fine_weight_gm'] ?? 0);
            $prevReturnedWeight = (float) ($returnedMap[$itemId]['weight_gm'] ?? 0);
            $prevReturnedFine = (float) ($returnedMap[$itemId]['fine_weight_gm'] ?? 0);

            $availableWeight = $issuedWeight - $prevReturnedWeight;
            $availableFine = $issuedFine - $prevReturnedFine;

            if ($returnWeight > ($availableWeight + 0.0005) || $returnFine > ($availableFine + 0.0005)) {
                $itemLabel = $this->goldItemLabel($itemId);
                throw new RuntimeException(
                    sprintf(
                        'Return exceeds issue for item %s. Available: %s gm.',
                        $itemLabel,
                        number_format(max(0, $availableWeight), 3)
                    )
                );
            }
        }
    }

    private function getReturnIssueId(int $returnId): int
    {
        $row = $this->db->table('gold_inventory_return_headers')
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

    private function goldItemLabel(int $itemId): string
    {
        if ($itemId <= 0) {
            return '#0';
        }
        $item = $this->itemModel->find($itemId);
        if (! $item) {
            return '#' . $itemId;
        }
        $purity = trim((string) ($item['purity_code'] ?? 'Gold'));
        $color = trim((string) ($item['color_name'] ?? ''));
        $form = trim((string) ($item['form_type'] ?? ''));
        $label = trim($purity . ' ' . $color . ' ' . $form);

        return $label !== '' ? $label : ('#' . $itemId);
    }

    public function reverseReturn(int $returnId, array $meta = []): void
    {
        $rows = $this->groupedReturnLines($returnId);
        if ($rows === []) {
            return;
        }

        $txnDate = $this->resolveTxnDate($meta['txn_date'] ?? null);
        $referenceTable = (string) ($meta['reference_table'] ?? 'gold_inventory_return_headers');
        $txnType = (string) ($meta['txn_type'] ?? 'return_reverse');

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $removeWeight = (float) $row['weight_gm'];
            $removeFine = (float) $row['fine_weight_gm'];

            $stock = $this->lockStockRow($itemId);
            $oldWeight = (float) $stock['weight_balance_gm'];
            $oldFine = (float) $stock['fine_balance_gm'];
            $oldAvg = (float) $stock['avg_cost_per_gm'];

            $newWeight = $oldWeight - $removeWeight;
            $newFine = $oldFine - $removeFine;
            if ($newWeight < -0.0005 || $newFine < -0.0005) {
                throw new RuntimeException('Cannot reverse return; stock would become negative.');
            }

            $stockValue = $newWeight * $oldAvg;
            $this->updateStock($itemId, $newWeight, $newFine, $oldAvg, $stockValue);

            $lineValue = $removeWeight * $oldAvg;
            $this->insertLedgerEntry([
                'txn_date' => $txnDate,
                'txn_type' => $txnType,
                'reference_table' => $referenceTable,
                'reference_id' => $returnId,
                'order_id' => $this->nullableInt($meta['order_id'] ?? null),
                'karigar_id' => $this->nullableInt($meta['karigar_id'] ?? null),
                'location_id' => $this->nullableInt($meta['location_id'] ?? null),
                'item_id' => $itemId,
                'debit_weight_gm' => 0,
                'credit_weight_gm' => round($removeWeight, 3),
                'debit_fine_gm' => 0,
                'credit_fine_gm' => round($removeFine, 3),
                'balance_weight_gm' => round($newWeight, 3),
                'balance_fine_gm' => round($newFine, 3),
                'rate_per_gm' => round($oldAvg, 2),
                'line_value' => round($lineValue, 2),
                'notes' => $this->stringOrNull($meta['notes'] ?? null),
                'created_by' => $this->nullableInt($meta['created_by'] ?? null),
            ]);
        }
    }

    public function applyAdjustment(int $adjustmentId, string $adjustmentType, array $meta = []): void
    {
        $rows = $this->groupedAdjustmentLines($adjustmentId);
        if ($rows === []) {
            return;
        }

        $isAdd = strtolower(trim($adjustmentType)) !== 'subtract';
        $txnDate = $this->resolveTxnDate($meta['txn_date'] ?? null);
        $referenceTable = (string) ($meta['reference_table'] ?? 'gold_inventory_adjustment_headers');
        $txnType = (string) ($meta['txn_type'] ?? ($isAdd ? 'adjustment_add' : 'adjustment_subtract'));

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $weight = (float) $row['weight_gm'];
            $fine = (float) $row['fine_weight_gm'];
            $lineValue = (float) $row['line_value'];

            $stock = $this->lockStockRow($itemId);
            $oldWeight = (float) $stock['weight_balance_gm'];
            $oldFine = (float) $stock['fine_balance_gm'];
            $oldAvg = (float) $stock['avg_cost_per_gm'];

            if ($isAdd) {
                $newWeight = $oldWeight + $weight;
                $newFine = $oldFine + $fine;
                if ($newWeight < -0.0005 || $newFine < -0.0005) {
                    throw new RuntimeException('Stock cannot go negative while applying adjustment.');
                }

                if ($lineValue > 0) {
                    $oldValue = $oldWeight * $oldAvg;
                    $newValue = $oldValue + $lineValue;
                    $newAvg = $newWeight > 0 ? ($newValue / $newWeight) : 0.0;
                } else {
                    $newAvg = $oldAvg;
                }
                if ($newAvg < 0) {
                    $newAvg = 0;
                }

                $stockValue = $newWeight * $newAvg;
                $this->updateStock($itemId, $newWeight, $newFine, $newAvg, $stockValue);
                $this->insertLedgerEntry([
                    'txn_date' => $txnDate,
                    'txn_type' => $txnType,
                    'reference_table' => $referenceTable,
                    'reference_id' => $adjustmentId,
                    'order_id' => $this->nullableInt($meta['order_id'] ?? null),
                    'karigar_id' => $this->nullableInt($meta['karigar_id'] ?? null),
                    'location_id' => $this->nullableInt($meta['location_id'] ?? null),
                    'item_id' => $itemId,
                    'debit_weight_gm' => round($weight, 3),
                    'credit_weight_gm' => 0,
                    'debit_fine_gm' => round($fine, 3),
                    'credit_fine_gm' => 0,
                    'balance_weight_gm' => round($newWeight, 3),
                    'balance_fine_gm' => round($newFine, 3),
                    'rate_per_gm' => $lineValue > 0 && $weight > 0 ? round($lineValue / $weight, 2) : round($newAvg, 2),
                    'line_value' => round($lineValue, 2),
                    'notes' => $this->stringOrNull($meta['notes'] ?? null),
                    'created_by' => $this->nullableInt($meta['created_by'] ?? null),
                ]);
            } else {
                $newWeight = $oldWeight - $weight;
                $newFine = $oldFine - $fine;
                if ($newWeight < -0.0005 || $newFine < -0.0005) {
                    throw new RuntimeException('Insufficient stock for adjustment.');
                }

                $stockValue = $newWeight * $oldAvg;
                $this->updateStock($itemId, $newWeight, $newFine, $oldAvg, $stockValue);

                $autoValue = $weight * $oldAvg;
                $this->insertLedgerEntry([
                    'txn_date' => $txnDate,
                    'txn_type' => $txnType,
                    'reference_table' => $referenceTable,
                    'reference_id' => $adjustmentId,
                    'order_id' => $this->nullableInt($meta['order_id'] ?? null),
                    'karigar_id' => $this->nullableInt($meta['karigar_id'] ?? null),
                    'location_id' => $this->nullableInt($meta['location_id'] ?? null),
                    'item_id' => $itemId,
                    'debit_weight_gm' => 0,
                    'credit_weight_gm' => round($weight, 3),
                    'debit_fine_gm' => 0,
                    'credit_fine_gm' => round($fine, 3),
                    'balance_weight_gm' => round($newWeight, 3),
                    'balance_fine_gm' => round($newFine, 3),
                    'rate_per_gm' => round($oldAvg, 2),
                    'line_value' => round($autoValue, 2),
                    'notes' => $this->stringOrNull($meta['notes'] ?? null),
                    'created_by' => $this->nullableInt($meta['created_by'] ?? null),
                ]);
            }
        }
    }

    public function reverseAdjustment(int $adjustmentId, string $adjustmentType, array $meta = []): void
    {
        $rows = $this->groupedAdjustmentLines($adjustmentId);
        if ($rows === []) {
            return;
        }

        $wasAdd = strtolower(trim($adjustmentType)) !== 'subtract';
        $txnDate = $this->resolveTxnDate($meta['txn_date'] ?? null);
        $referenceTable = (string) ($meta['reference_table'] ?? 'gold_inventory_adjustment_headers');
        $txnType = (string) ($meta['txn_type'] ?? ($wasAdd ? 'adjustment_add_reverse' : 'adjustment_subtract_reverse'));

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $weight = (float) $row['weight_gm'];
            $fine = (float) $row['fine_weight_gm'];
            $lineValue = (float) $row['line_value'];

            $stock = $this->lockStockRow($itemId);
            $oldWeight = (float) $stock['weight_balance_gm'];
            $oldFine = (float) $stock['fine_balance_gm'];
            $oldAvg = (float) $stock['avg_cost_per_gm'];

            if ($wasAdd) {
                $newWeight = $oldWeight - $weight;
                $newFine = $oldFine - $fine;
                if ($newWeight < -0.0005 || $newFine < -0.0005) {
                    throw new RuntimeException('Cannot reverse adjustment; stock would become negative.');
                }

                if ($lineValue > 0) {
                    $oldValue = $oldWeight * $oldAvg;
                    $newValue = $oldValue - $lineValue;
                    $newAvg = $newWeight > 0 ? ($newValue / $newWeight) : 0.0;
                } else {
                    $newAvg = $oldAvg;
                }
                if ($newAvg < 0) {
                    $newAvg = 0;
                }

                $stockValue = $newWeight * $newAvg;
                $this->updateStock($itemId, $newWeight, $newFine, $newAvg, $stockValue);
                $this->insertLedgerEntry([
                    'txn_date' => $txnDate,
                    'txn_type' => $txnType,
                    'reference_table' => $referenceTable,
                    'reference_id' => $adjustmentId,
                    'order_id' => $this->nullableInt($meta['order_id'] ?? null),
                    'karigar_id' => $this->nullableInt($meta['karigar_id'] ?? null),
                    'location_id' => $this->nullableInt($meta['location_id'] ?? null),
                    'item_id' => $itemId,
                    'debit_weight_gm' => 0,
                    'credit_weight_gm' => round($weight, 3),
                    'debit_fine_gm' => 0,
                    'credit_fine_gm' => round($fine, 3),
                    'balance_weight_gm' => round($newWeight, 3),
                    'balance_fine_gm' => round($newFine, 3),
                    'rate_per_gm' => $lineValue > 0 && $weight > 0 ? round($lineValue / $weight, 2) : round($newAvg, 2),
                    'line_value' => round($lineValue, 2),
                    'notes' => $this->stringOrNull($meta['notes'] ?? null),
                    'created_by' => $this->nullableInt($meta['created_by'] ?? null),
                ]);
            } else {
                $newWeight = $oldWeight + $weight;
                $newFine = $oldFine + $fine;
                if ($newWeight < -0.0005 || $newFine < -0.0005) {
                    throw new RuntimeException('Stock cannot go negative while reversing adjustment.');
                }

                $stockValue = $newWeight * $oldAvg;
                $this->updateStock($itemId, $newWeight, $newFine, $oldAvg, $stockValue);
                $autoValue = $weight * $oldAvg;
                $this->insertLedgerEntry([
                    'txn_date' => $txnDate,
                    'txn_type' => $txnType,
                    'reference_table' => $referenceTable,
                    'reference_id' => $adjustmentId,
                    'order_id' => $this->nullableInt($meta['order_id'] ?? null),
                    'karigar_id' => $this->nullableInt($meta['karigar_id'] ?? null),
                    'location_id' => $this->nullableInt($meta['location_id'] ?? null),
                    'item_id' => $itemId,
                    'debit_weight_gm' => round($weight, 3),
                    'credit_weight_gm' => 0,
                    'debit_fine_gm' => round($fine, 3),
                    'credit_fine_gm' => 0,
                    'balance_weight_gm' => round($newWeight, 3),
                    'balance_fine_gm' => round($newFine, 3),
                    'rate_per_gm' => round($oldAvg, 2),
                    'line_value' => round($autoValue, 2),
                    'notes' => $this->stringOrNull($meta['notes'] ?? null),
                    'created_by' => $this->nullableInt($meta['created_by'] ?? null),
                ]);
            }
        }
    }

    /**
     * @param array<string,mixed> $movement
     */
    public function postExternalMovement(array $movement): int
    {
        $direction = strtolower(trim((string) ($movement['direction'] ?? '')));
        if (! in_array($direction, ['in', 'out'], true)) {
            throw new RuntimeException('Invalid movement direction.');
        }

        $weight = (float) ($movement['weight_gm'] ?? 0);
        if ($weight <= 0) {
            throw new RuntimeException('Weight must be greater than zero.');
        }

        $itemId = (int) ($movement['item_id'] ?? 0);
        if ($itemId <= 0) {
            $itemId = $this->upsertItemFromSignature([
                'gold_purity_id' => $this->nullableInt($movement['gold_purity_id'] ?? null),
                'purity_code' => $movement['purity_code'] ?? null,
                'purity_percent' => $movement['purity_percent'] ?? null,
                'color_name' => $movement['color_name'] ?? null,
                'form_type' => $movement['form_type'] ?? 'Ornament',
            ]);
        } else {
            $this->ensureStockRow($itemId);
        }

        $item = $this->itemModel->find($itemId);
        if (! $item) {
            throw new RuntimeException('Gold item not found for movement.');
        }

        $fine = (float) ($movement['fine_weight_gm'] ?? 0);
        if ($fine <= 0) {
            $percent = (float) ($item['purity_percent'] ?? 0);
            $fine = round($weight * ($percent / 100), 3);
        }

        $stock = $this->lockStockRow($itemId);
        $oldWeight = (float) $stock['weight_balance_gm'];
        $oldFine = (float) $stock['fine_balance_gm'];
        $oldAvg = (float) $stock['avg_cost_per_gm'];

        if ($direction === 'out') {
            $newWeight = $oldWeight - $weight;
            $newFine = $oldFine - $fine;
            if ($newWeight < -0.0005 || $newFine < -0.0005) {
                throw new RuntimeException('Insufficient gold inventory stock.');
            }
            $debitWeight = 0.0;
            $creditWeight = $weight;
            $debitFine = 0.0;
            $creditFine = $fine;
        } else {
            $newWeight = $oldWeight + $weight;
            $newFine = $oldFine + $fine;
            if ($newWeight < -0.0005 || $newFine < -0.0005) {
                throw new RuntimeException('Stock cannot go negative.');
            }
            $debitWeight = $weight;
            $creditWeight = 0.0;
            $debitFine = $fine;
            $creditFine = 0.0;
        }

        $stockValue = $newWeight * $oldAvg;
        $this->updateStock($itemId, $newWeight, $newFine, $oldAvg, $stockValue);

        $txnType = (string) ($movement['txn_type'] ?? ($direction === 'out' ? 'order_issue' : 'order_receive'));
        $lineValue = $weight * $oldAvg;
        $this->insertLedgerEntry([
            'txn_date' => $this->resolveTxnDate($movement['txn_date'] ?? null),
            'txn_type' => $txnType,
            'reference_table' => $this->stringOrNull($movement['reference_table'] ?? null),
            'reference_id' => $this->nullableInt($movement['reference_id'] ?? null),
            'order_id' => $this->nullableInt($movement['order_id'] ?? null),
            'karigar_id' => $this->nullableInt($movement['karigar_id'] ?? null),
            'location_id' => $this->nullableInt($movement['location_id'] ?? null),
            'item_id' => $itemId,
            'debit_weight_gm' => round($debitWeight, 3),
            'credit_weight_gm' => round($creditWeight, 3),
            'debit_fine_gm' => round($debitFine, 3),
            'credit_fine_gm' => round($creditFine, 3),
            'balance_weight_gm' => round($newWeight, 3),
            'balance_fine_gm' => round($newFine, 3),
            'rate_per_gm' => round($oldAvg, 2),
            'line_value' => round($lineValue, 2),
            'notes' => $this->stringOrNull($movement['notes'] ?? null),
            'created_by' => $this->nullableInt($movement['created_by'] ?? null),
        ]);

        return $itemId;
    }

    public function calculateFineWeightForItem(int $itemId, float $weightGm): float
    {
        if ($weightGm <= 0) {
            return 0.0;
        }

        $item = $this->itemModel->find($itemId);
        if (! $item) {
            throw new RuntimeException('Gold item not found.');
        }

        $percent = (float) ($item['purity_percent'] ?? 0);
        return round($weightGm * ($percent / 100), 3);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function groupedPurchaseLines(int $purchaseId): array
    {
        return $this->db->table('gold_inventory_purchase_lines')
            ->select('item_id, SUM(weight_gm) as weight_gm, SUM(fine_weight_gm) as fine_weight_gm, SUM(line_value) as line_value', false)
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
        return $this->db->table('gold_inventory_issue_lines')
            ->select('item_id, SUM(weight_gm) as weight_gm, SUM(fine_weight_gm) as fine_weight_gm', false)
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
        return $this->db->table('gold_inventory_return_lines')
            ->select('item_id, SUM(weight_gm) as weight_gm, SUM(fine_weight_gm) as fine_weight_gm', false)
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
        return $this->db->table('gold_inventory_adjustment_lines')
            ->select('item_id, SUM(weight_gm) as weight_gm, SUM(fine_weight_gm) as fine_weight_gm, SUM(COALESCE(line_value,0)) as line_value', false)
            ->where('adjustment_id', $adjustmentId)
            ->groupBy('item_id')
            ->orderBy('item_id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string,mixed> $entry
     */
    private function insertLedgerEntry(array $entry): void
    {
        $payload = [
            'txn_date' => $entry['txn_date'] ?? date('Y-m-d'),
            'txn_type' => $entry['txn_type'] ?? 'movement',
            'reference_table' => $entry['reference_table'] ?? null,
            'reference_id' => $entry['reference_id'] ?? null,
            'order_id' => $entry['order_id'] ?? null,
            'karigar_id' => $entry['karigar_id'] ?? null,
            'location_id' => $entry['location_id'] ?? null,
            'item_id' => (int) ($entry['item_id'] ?? 0),
            'debit_weight_gm' => round((float) ($entry['debit_weight_gm'] ?? 0), 3),
            'credit_weight_gm' => round((float) ($entry['credit_weight_gm'] ?? 0), 3),
            'debit_fine_gm' => round((float) ($entry['debit_fine_gm'] ?? 0), 3),
            'credit_fine_gm' => round((float) ($entry['credit_fine_gm'] ?? 0), 3),
            'balance_weight_gm' => round((float) ($entry['balance_weight_gm'] ?? 0), 3),
            'balance_fine_gm' => round((float) ($entry['balance_fine_gm'] ?? 0), 3),
            'rate_per_gm' => isset($entry['rate_per_gm']) ? round((float) $entry['rate_per_gm'], 2) : null,
            'line_value' => isset($entry['line_value']) ? round((float) $entry['line_value'], 2) : null,
            'notes' => $this->stringOrNull($entry['notes'] ?? null),
            'created_by' => $this->nullableInt($entry['created_by'] ?? null),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($payload['item_id'] <= 0) {
            throw new RuntimeException('Invalid ledger item for gold inventory posting.');
        }

        $this->db->table('gold_inventory_ledger_entries')->insert($payload);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function normalizeSignature(array $data): array
    {
        $goldPurityId = $this->nullableInt($data['gold_purity_id'] ?? null);
        $purityCode = $this->stringOrNull($data['purity_code'] ?? null);
        $purityPercent = (float) ($data['purity_percent'] ?? 0);
        $colorName = $this->stringOrNull($data['color_name'] ?? null);
        $formType = $this->stringOrNull($data['form_type'] ?? null);

        if ($goldPurityId !== null) {
            $purity = $this->db->table('gold_purities')
                ->select('id, purity_code, purity_percent, color_name')
                ->where('id', $goldPurityId)
                ->get()
                ->getRowArray();
            if (! $purity) {
                throw new RuntimeException('Selected gold purity was not found.');
            }

            if ($purityCode === null) {
                $purityCode = $this->stringOrNull($purity['purity_code'] ?? null);
            }
            if ($purityPercent <= 0) {
                $purityPercent = (float) ($purity['purity_percent'] ?? 0);
            }
            if ($colorName === null) {
                $colorName = $this->stringOrNull($purity['color_name'] ?? null);
            }
        }

        if ($purityCode === null) {
            $purityCode = 'NA';
        }
        if ($colorName === null) {
            $colorName = 'NA';
        }
        if ($formType === null) {
            $formType = 'Raw';
        }
        if ($purityPercent < 0) {
            $purityPercent = 0;
        }
        if ($purityPercent > 100) {
            throw new RuntimeException('Purity percent cannot be more than 100.');
        }

        return [
            'gold_purity_id' => $goldPurityId,
            'purity_code' => strtoupper($purityCode),
            'purity_percent' => round($purityPercent, 3),
            'color_name' => strtoupper($colorName),
            'form_type' => ucwords(strtolower($formType)),
        ];
    }

    private function ensureStockRow(int $itemId): void
    {
        $this->db->query(
            'INSERT INTO gold_inventory_stock (item_id, weight_balance_gm, fine_balance_gm, avg_cost_per_gm, stock_value, updated_at)
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
            'SELECT item_id, weight_balance_gm, fine_balance_gm, avg_cost_per_gm, stock_value
             FROM gold_inventory_stock
             WHERE item_id = ? FOR UPDATE',
            [$itemId]
        )->getRowArray();

        if (! $row) {
            throw new RuntimeException('Unable to lock gold inventory stock row.');
        }

        return $row;
    }

    private function updateStock(int $itemId, float $weight, float $fine, float $avg, float $stockValue): void
    {
        $weight = round(max(0, $weight), 3);
        $fine = round(max(0, $fine), 3);
        $avg = round(max(0, $avg), 2);
        $stockValue = round(max(0, $stockValue), 2);

        $this->stockModel->update($itemId, [
            'weight_balance_gm' => $weight,
            'fine_balance_gm' => $fine,
            'avg_cost_per_gm' => $avg,
            'stock_value' => $stockValue,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function resolveTxnDate(mixed $value): string
    {
        $v = trim((string) $value);
        return $v === '' ? date('Y-m-d') : $v;
    }

    private function stringOrNull(mixed $value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $i = (int) $value;
        return $i > 0 ? $i : null;
    }
}
