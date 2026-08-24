<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class FinishedJewelleryService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function createForCompletedOrder(int $orderId, int $createdBy = 0): ?int
    {
        if ($orderId <= 0 || ! $this->db->tableExists('fg_items') || ! $this->db->fieldExists('studded_details_json', 'fg_items')) {
            return null;
        }
        $existing = $this->db->table('fg_items')->select('id')->where('order_id', $orderId)->get()->getRowArray();
        if ($existing) {
            return (int) $existing['id'];
        }
        $order = $this->db->table('orders')->where('id', $orderId)->get()->getRowArray();
        if (! $order) {
            throw new RuntimeException('Completed order was not found for finished jewellery creation.');
        }

        $summary = $this->db->table('order_receive_summaries')->where('order_id', $orderId)->orderBy('id', 'DESC')->get()->getRowArray() ?? [];
        if ($summary === []) {
            $movement = $this->db->table('order_material_movements')
                ->where('order_id', $orderId)
                ->where('movement_type', 'receive')
                ->orderBy('id', 'DESC')->get()->getRowArray() ?? [];
            if ($movement !== []) {
                $summary = [
                    'movement_id' => (int) ($movement['id'] ?? 0),
                    'gross_weight_gm' => (float) (($movement['gross_weight_gm'] ?? 0) ?: ($movement['gold_gm'] ?? 0)),
                    'net_gold_weight_gm' => (float) (($movement['net_gold_weight_gm'] ?? 0) ?: ($movement['gold_gm'] ?? 0)),
                    'pure_gold_weight_gm' => (float) ($movement['pure_gold_weight_gm'] ?? 0),
                    'diamond_weight_cts' => (float) ($movement['diamond_cts'] ?? 0),
                    'stone_weight_cts' => 0,
                ];
            }
        }
        $movementId = (int) ($summary['movement_id'] ?? 0);
        $details = $movementId > 0
            ? $this->db->table('order_receive_details')->where('movement_id', $movementId)->orderBy('id', 'ASC')->get()->getResultArray()
            : [];
        $studded = [];
        foreach ($details as $detail) {
            if (! in_array(strtolower((string) ($detail['component_type'] ?? '')), ['diamond', 'stone', 'other'], true)) {
                continue;
            }
            $studded[] = [
                'type' => (string) ($detail['component_type'] ?? ''),
                'name' => (string) ($detail['component_name'] ?? ''),
                'pcs' => (float) ($detail['pcs'] ?? 0),
                'weight_cts' => (float) ($detail['weight_cts'] ?? 0),
                'weight_gm' => (float) ($detail['weight_gm'] ?? 0),
                'rate' => (float) ($detail['rate'] ?? 0),
                'amount' => (float) ($detail['line_total'] ?? 0),
            ];
        }
        $orderItem = $this->db->table('order_items')->where('order_id', $orderId)->orderBy('id', 'ASC')->get()->getRowArray() ?? [];
        $jobCard = $this->db->table('job_cards')->select('id')->where('order_id', $orderId)->orderBy('id', 'ASC')->get()->getRowArray();
        $warehouse = $this->db->table('warehouses')->whereIn('warehouse_code', ['FG_STORE', 'MAIN'])->orderBy('id', 'ASC')->get()->getRowArray()
            ?? $this->db->table('warehouses')->orderBy('id', 'ASC')->get()->getRowArray();
        $warehouseId = (int) ($warehouse['id'] ?? 0);
        $bin = $warehouseId > 0 ? $this->db->table('bins')->where('warehouse_id', $warehouseId)->orderBy('id', 'ASC')->get()->getRowArray() : null;
        $tagNo = 'FG-' . preg_replace('/[^A-Z0-9]+/', '-', strtoupper((string) $order['order_no']));
        if ($this->db->table('fg_items')->where('tag_no', $tagNo)->countAllResults() > 0) {
            $tagNo .= '-' . $orderId;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->table('fg_items')->insert([
            'tag_no' => substr($tagNo, 0, 80),
            'order_id' => $orderId,
            'job_card_id' => $jobCard ? (int) $jobCard['id'] : null,
            'production_ready_item_id' => null,
            'design_name' => (string) (($orderItem['item_description'] ?? '') ?: $order['order_no']),
            'purity_label' => null,
            'qty' => max(1, (int) ($orderItem['qty'] ?? 1)),
            'gross_wt' => (float) ($summary['gross_weight_gm'] ?? 0),
            'net_gold_wt' => (float) (($summary['net_gold_weight_gm'] ?? 0) ?: ($orderItem['gold_required_gm'] ?? 0)),
            'diamond_cts' => (float) (($summary['diamond_weight_cts'] ?? 0) ?: ($orderItem['diamond_required_cts'] ?? 0)),
            'stone_wt' => (float) ($summary['stone_weight_cts'] ?? 0),
            'studded_details_json' => json_encode($studded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'AVAILABLE',
            'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
            'bin_id' => $bin ? (int) $bin['id'] : null,
            'showroom_stock_status' => 'FG_STORE',
            'inventory_remarks' => $summary === [] ? 'Created on order completion; receive details were not supplied' : 'Created automatically from completed order receive details',
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $fgItemId = (int) $this->db->insertID();
        $this->db->table('showroom_fg_movements')->insert([
            'fg_item_id' => $fgItemId,
            'movement_type' => 'ORDER_COMPLETED_TO_FG',
            'reference_type' => 'orders',
            'reference_id' => $orderId,
            'remarks' => 'Finished jewellery created automatically when order was completed',
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $fgItemId;
    }
}
