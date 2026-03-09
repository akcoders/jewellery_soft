<?php

namespace App\Controllers\Api;

class PackingController extends ApiBaseController
{
    public function create()
    {
        $p = $this->payload();
        $orderId = (int) ($p['order_id'] ?? 0);
        $customerId = (int) ($p['customer_id'] ?? 0);
        $warehouseId = (int) ($p['warehouse_id'] ?? 0);
        $fgItemIds = (array) ($p['fg_item_ids'] ?? []);

        if ($customerId <= 0 || $fgItemIds === []) {
            return $this->fail('customer_id and fg_item_ids are required.', 422);
        }

        $packingNo = trim((string) ($p['packing_no'] ?? ''));
        if ($packingNo === '') {
            $packingNo = 'PK-' . date('YmdHis');
        }

        $db = db_connect();
        $db->transStart();
        $packingId = (int) $db->table('packing_lists')->insert([
            'packing_no' => $packingNo,
            'packing_date' => (string) ($p['packing_date'] ?? date('Y-m-d')),
            'order_id' => $orderId > 0 ? $orderId : null,
            'customer_id' => $customerId,
            'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
            'status' => 'Packed',
            'seal_no' => (string) ($p['seal_no'] ?? ''),
            'notes' => (string) ($p['notes'] ?? ''),
            'created_by' => (int) (session('admin_id') ?: 0),
        ], true);

        $items = [];
        foreach ($fgItemIds as $fgItemId) {
            $fg = $db->table('fg_items')->where('id', (int) $fgItemId)->get()->getRowArray();
            if (! $fg) {
                continue;
            }
            $db->table('packing_list_items')->insert([
                'packing_list_id' => $packingId,
                'fg_item_id' => (int) $fg['id'],
                'tag_no' => $fg['tag_no'],
                'qty' => (int) ($fg['qty'] ?? 1),
                'gross_wt' => (float) ($fg['gross_wt'] ?? 0),
                'net_gold_wt' => (float) ($fg['net_gold_wt'] ?? 0),
                'diamond_cts' => (float) ($fg['diamond_cts'] ?? 0),
                'stone_wt' => (float) ($fg['stone_wt'] ?? 0),
            ]);
            $db->table('fg_items')->where('id', (int) $fg['id'])->update(['status' => 'PACKED']);
            $items[] = $fg;
        }

        $db->transComplete();

        return $this->ok(['packing_list_id' => $packingId, 'packing_no' => $packingNo, 'items' => $items], 'Packing list created.', 201);
    }

    public function dispatch(int $packingListId)
    {
        $db = db_connect();
        $pack = $db->table('packing_lists')->where('id', $packingListId)->get()->getRowArray();
        if (! $pack) {
            return $this->fail('Packing list not found.', 404);
        }

        $db->table('packing_lists')->where('id', $packingListId)->update([
            'status' => 'Dispatched',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $items = $db->table('packing_list_items')->where('packing_list_id', $packingListId)->get()->getResultArray();
        foreach ($items as $row) {
            $db->table('fg_items')->where('id', (int) $row['fg_item_id'])->update(['status' => 'DISPATCHED']);
        }

        if (! empty($pack['order_id'])) {
            $db->table('orders')->where('id', (int) $pack['order_id'])->update(['status' => 'Dispatched']);
        }

        return $this->ok(['packing_list_id' => $packingListId], 'Packing list dispatched.');
    }
}
