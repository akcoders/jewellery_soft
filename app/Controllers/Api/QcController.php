<?php

namespace App\Controllers\Api;

use App\Services\PostingService;

class QcController extends ApiBaseController
{
    public function check(int $fgItemId)
    {
        $p = $this->payload();
        $status = strtoupper(trim((string) ($p['qc_status'] ?? 'PASS')));
        if (! in_array($status, ['PASS', 'FAIL'], true)) {
            return $this->fail('qc_status must be PASS or FAIL.', 422);
        }

        $db = db_connect();
        $fg = $db->table('fg_items')->where('id', $fgItemId)->get()->getRowArray();
        if (! $fg) {
            return $this->fail('FG item not found.', 404);
        }

        $db->table('qc_checks')->insert([
            'fg_item_id' => $fgItemId,
            'tag_no' => $fg['tag_no'],
            'qc_status' => $status,
            'reason_code' => (string) ($p['reason_code'] ?? ''),
            'remarks' => (string) ($p['remarks'] ?? ''),
            'created_by' => (int) (session('admin_id') ?: 0),
        ]);

        if ($status === 'FAIL') {
            $db->table('fg_items')->where('id', $fgItemId)->update(['status' => 'REWORK']);
            return $this->ok(['fg_item_id' => $fgItemId, 'status' => 'REWORK'], 'QC failed, moved to rework.');
        }

        $fgStore = $db->table('warehouses')->where('warehouse_code', 'FG_STORE')->get()->getRowArray();
        if (! $fgStore) {
            return $this->fail('FG_STORE warehouse not configured.', 422);
        }

        $oldWarehouseId = (int) ($fg['warehouse_id'] ?? 0);
        $oldBinId = (int) ($fg['bin_id'] ?? 0);

        $db->table('fg_items')->where('id', $fgItemId)->update([
            'status' => 'AVAILABLE',
            'warehouse_id' => (int) $fgStore['id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $posting = new PostingService($db);
        $qcAcc = $posting->ensureAccount('PROCESS', 'QC-HOLD', 'QC Hold Account');
        $fgAcc = $posting->ensureAccount('WAREHOUSE', 'WH-' . (int) $fgStore['id'], 'Warehouse #' . (int) $fgStore['id'], 'warehouses', (int) $fgStore['id']);

        $voucher = $posting->postVoucher([
            'voucher_type' => 'QC_PASS_MOVE',
            'voucher_date' => date('Y-m-d'),
            'from_warehouse_id' => $oldWarehouseId > 0 ? $oldWarehouseId : null,
            'from_bin_id' => $oldBinId > 0 ? $oldBinId : null,
            'to_warehouse_id' => (int) $fgStore['id'],
            'debit_account_id' => $fgAcc,
            'credit_account_id' => $qcAcc,
            'order_id' => (int) ($fg['order_id'] ?? 0),
            'job_card_id' => (int) ($fg['job_card_id'] ?? 0),
            'remarks' => 'QC pass FG move',
            'created_by' => (int) (session('admin_id') ?: 0),
        ], [[
            'item_type' => 'FG',
            'item_key' => 'TAG-' . $fg['tag_no'],
            'tag_no' => $fg['tag_no'],
            'qty_pcs' => (float) ($fg['qty'] ?? 1),
            'qty_weight' => (float) ($fg['gross_wt'] ?? 0),
            'qty_cts' => (float) ($fg['diamond_cts'] ?? 0),
            'fine_gold' => (float) ($fg['net_gold_wt'] ?? 0),
        ]]);

        return $this->ok(['fg_item_id' => $fgItemId, 'status' => 'AVAILABLE', 'voucher' => $voucher], 'QC passed and FG created.');
    }
}
