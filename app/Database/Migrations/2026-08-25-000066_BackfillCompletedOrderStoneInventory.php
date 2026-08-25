<?php

namespace App\Database\Migrations;

use App\Services\KarigarMaterialAccountingService;
use App\Services\StoneInventory\StockService as StoneInventoryStockService;
use CodeIgniter\Database\Migration;
use RuntimeException;
use Throwable;

class BackfillCompletedOrderStoneInventory extends Migration
{
    public function up()
    {
        $this->assertSchema();
        $this->addStoneVoucherLink();

        $movements = $this->unlinkedStoneMovements();
        if ($movements === []) {
            return;
        }

        $stockService = new StoneInventoryStockService($this->db);
        $accounting = new KarigarMaterialAccountingService($this->db);
        $this->db->transException(true)->transStart();

        try {
            foreach ($movements as $movement) {
                $this->backfillMovement($movement, $stockService, $accounting);
            }

            $remaining = (int) $this->db->table('order_receive_details')
                ->where('component_type', 'stone')
                ->where('weight_cts >', 0)
                ->where('stone_inventory_item_id IS NULL', null, false)
                ->countAllResults();
            if ($remaining !== 0) {
                throw new RuntimeException('Some received stone rows could not be linked to Stone Inventory.');
            }

            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Historical inventory/accounting records are intentionally retained on rollback.
     */
    public function down()
    {
    }

    private function assertSchema(): void
    {
        foreach ([
            'order_receive_details',
            'order_receive_summaries',
            'order_material_movements',
            'stone_inventory_items',
            'stone_inventory_stock',
            'stone_inventory_issue_headers',
            'stone_inventory_issue_lines',
        ] as $table) {
            if (! $this->db->tableExists($table)) {
                throw new RuntimeException('Required table is missing for received-stone backfill: ' . $table);
            }
        }
        if (! $this->db->fieldExists('stone_inventory_item_id', 'order_receive_details')
            || ! $this->db->fieldExists('receive_movement_id', 'stone_inventory_issue_headers')) {
            throw new RuntimeException('Run received-stone linking migration before the backfill migration.');
        }
    }

    private function addStoneVoucherLink(): void
    {
        if ($this->db->fieldExists('stone_account_voucher_id', 'order_receive_summaries')) {
            return;
        }

        $this->forge->addColumn('order_receive_summaries', [
            'stone_account_voucher_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
                'after' => 'account_voucher_id',
            ],
        ]);
        $this->db->query(
            'ALTER TABLE `order_receive_summaries` ADD INDEX `idx_receive_summary_stone_voucher` (`stone_account_voucher_id`)'
        );
    }

    /** @return list<array<string,mixed>> */
    private function unlinkedStoneMovements(): array
    {
        return $this->db->table('order_receive_details d')
            ->select(
                'd.movement_id, m.order_id, m.karigar_id, m.location_id, m.created_by, '
                . 'm.created_at, o.order_no, COUNT(d.id) AS stone_rows, '
                . 'ROUND(SUM(d.weight_cts), 3) AS stone_cts',
                false
            )
            ->join('order_material_movements m', 'm.id = d.movement_id', 'inner')
            ->join('orders o', 'o.id = m.order_id', 'inner')
            ->where('d.component_type', 'stone')
            ->where('d.weight_cts >', 0)
            ->where('d.stone_inventory_item_id IS NULL', null, false)
            ->groupBy(['d.movement_id', 'm.order_id', 'm.karigar_id', 'm.location_id', 'm.created_by', 'm.created_at', 'o.order_no'])
            ->orderBy('m.created_at', 'ASC')
            ->orderBy('d.movement_id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string,mixed> $movement
     */
    private function backfillMovement(
        array $movement,
        StoneInventoryStockService $stockService,
        KarigarMaterialAccountingService $accounting
    ): void {
        $movementId = (int) ($movement['movement_id'] ?? 0);
        $orderId = (int) ($movement['order_id'] ?? 0);
        $karigarId = (int) ($movement['karigar_id'] ?? 0);
        $locationId = (int) ($movement['location_id'] ?? 0);
        $stoneCts = round((float) ($movement['stone_cts'] ?? 0), 3);
        if ($movementId <= 0 || $orderId <= 0 || $karigarId <= 0 || $locationId <= 0 || $stoneCts <= 0) {
            throw new RuntimeException('Received stone movement is missing order, karigar, location, or weight.');
        }

        $existingHeader = $this->db->table('stone_inventory_issue_headers')
            ->select('id')
            ->where('receive_movement_id', $movementId)
            ->get()
            ->getRowArray();
        if ($existingHeader) {
            throw new RuntimeException('Received stone movement #' . $movementId . ' already has an inventory entry.');
        }

        $details = $this->db->table('order_receive_details')
            ->where('movement_id', $movementId)
            ->where('component_type', 'stone')
            ->where('weight_cts >', 0)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
        $items = [];
        foreach ($details as $detail) {
            $name = trim((string) ($detail['component_name'] ?? ''));
            if ($name === '' || $name === '-') {
                $name = 'Stone';
            }
            $weight = round((float) ($detail['weight_cts'] ?? 0), 3);
            $rate = round(max(0, (float) ($detail['rate'] ?? 0)), 2);
            $itemId = $stockService->upsertItemFromSignature([
                'product_name' => $name,
                'stone_type' => $name,
                'default_rate' => $rate,
                'remarks' => 'Created from completed-order receiving history.',
            ]);
            $this->db->table('order_receive_details')->where('id', (int) $detail['id'])->update([
                'stone_inventory_item_id' => $itemId,
            ]);

            if (! isset($items[$itemId])) {
                $items[$itemId] = ['qty' => 0.0, 'pcs' => 0.0, 'value' => 0.0];
            }
            $items[$itemId]['qty'] += $weight;
            $items[$itemId]['pcs'] += max(0, (float) ($detail['pcs'] ?? 0));
            $items[$itemId]['value'] += max(0, (float) ($detail['line_total'] ?? 0));
        }

        $historicalDateTime = (string) (($movement['created_at'] ?? '') ?: date('Y-m-d H:i:s'));
        $voucherDate = substr($historicalDateTime, 0, 10);
        $createdBy = max(0, (int) ($movement['created_by'] ?? 0));
        $this->db->table('stone_inventory_issue_headers')->insert([
            'voucher_no' => 'SRV-' . $movementId,
            'issue_date' => $voucherDate,
            'order_id' => null,
            'receive_movement_id' => $movementId,
            'karigar_id' => $karigarId,
            'location_id' => $locationId,
            'issue_to' => 'Karigar #' . $karigarId,
            'purpose' => 'Historical receipt backflush',
            'notes' => 'Stone consumed from completed order ' . (string) ($movement['order_no'] ?? ('#' . $orderId)),
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'created_at' => $historicalDateTime,
            'updated_at' => $historicalDateTime,
        ]);
        $issueId = (int) $this->db->insertID();
        if ($issueId <= 0) {
            throw new RuntimeException('Unable to create historical Stone Inventory issue.');
        }

        foreach ($items as $itemId => $totals) {
            $qty = round((float) $totals['qty'], 3);
            $value = round((float) $totals['value'], 2);
            $rate = $qty > 0 ? round($value / $qty, 2) : 0;
            $this->db->table('stone_inventory_issue_lines')->insert([
                'issue_id' => $issueId,
                'item_id' => (int) $itemId,
                'pcs' => round((float) $totals['pcs'], 3),
                'qty' => $qty,
                'rate' => $rate,
                'line_value' => $value,
                'created_at' => $historicalDateTime,
                'updated_at' => $historicalDateTime,
            ]);
        }

        $stockService->applyReceiptBackflushIssue($issueId);
        $issueVoucherId = $accounting->postInventoryHeader('stone', 'issue', $issueId);
        $receiptVoucherId = $accounting->postFinishedJewelleryReceipt(
            $orderId,
            $karigarId,
            $locationId,
            0,
            0,
            0,
            'Historical stone settlement for completed order ' . (string) ($movement['order_no'] ?? ('#' . $orderId)),
            $createdBy,
            $voucherDate,
            $stoneCts
        );

        foreach ([$issueVoucherId, $receiptVoucherId] as $voucherId) {
            $this->db->table('vouchers')->where('id', $voucherId)->update([
                'voucher_datetime' => $historicalDateTime,
                'created_at' => $historicalDateTime,
                'updated_at' => $historicalDateTime,
            ]);
            $this->db->table('voucher_lines')->where('voucher_id', $voucherId)->update([
                'created_at' => $historicalDateTime,
                'updated_at' => $historicalDateTime,
            ]);
            $this->db->table('ledger_entries')->where('voucher_id', $voucherId)->update([
                'created_at' => $historicalDateTime,
            ]);
        }

        $this->db->table('order_receive_summaries')->where('movement_id', $movementId)->update([
            'stone_account_voucher_id' => $receiptVoucherId,
        ]);
    }
}
