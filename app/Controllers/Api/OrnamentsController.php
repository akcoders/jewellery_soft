<?php

namespace App\Controllers\Api;

use App\Services\PostingService;

class OrnamentsController extends ApiBaseController
{
    public function receive()
    {
        $p = $this->payload();
        $orderId = (int) ($p['order_id'] ?? 0);
        $jobCardId = (int) ($p['job_card_id'] ?? 0);
        $karigarId = (int) ($p['karigar_id'] ?? 0);

        if ($orderId <= 0 || $karigarId <= 0) {
            return $this->fail('order_id and karigar_id are required.', 422);
        }

        $tagNo = trim((string) ($p['tag_no'] ?? ''));
        if ($tagNo === '') {
            $tagNo = 'TAG-' . date('YmdHis');
        }

        $db = db_connect();
        $wip = $db->table('warehouses')->where('warehouse_code', 'WIP_STORE')->get()->getRowArray();
        if (! $wip) {
            return $this->fail('WIP_STORE warehouse not configured.', 422);
        }

        $fgId = (int) $db->table('fg_items')->insert([
            'tag_no' => $tagNo,
            'order_id' => $orderId,
            'job_card_id' => $jobCardId > 0 ? $jobCardId : null,
            'product_id' => isset($p['product_id']) ? (int) $p['product_id'] : null,
            'variant_id' => isset($p['variant_id']) ? (int) $p['variant_id'] : null,
            'qty' => (int) ($p['qty'] ?? 1),
            'gross_wt' => (float) ($p['gross_wt'] ?? 0),
            'net_gold_wt' => (float) ($p['net_gold_wt'] ?? 0),
            'diamond_cts' => (float) ($p['diamond_cts_used'] ?? 0),
            'stone_wt' => (float) ($p['stone_wt_used'] ?? 0),
            'status' => 'QC_HOLD',
            'warehouse_id' => (int) $wip['id'],
            'bin_id' => isset($p['bin_id']) ? (int) $p['bin_id'] : null,
            'created_by' => (int) (session('admin_id') ?: 0),
        ], true);

        $posting = new PostingService($db);
        $qcAcc = $posting->ensureAccount('PROCESS', 'QC-HOLD', 'QC Hold Account', null, null);
        $karigarAcc = $posting->ensureAccount('KARIGAR', 'KARIGAR-' . $karigarId, 'Karigar #' . $karigarId, 'parties', $karigarId);

        $voucher = $posting->postVoucher([
            'voucher_type' => 'ORNAMENT_RECEIVE',
            'voucher_date' => (string) ($p['receive_date'] ?? date('Y-m-d')),
            'to_warehouse_id' => (int) $wip['id'],
            'to_bin_id' => isset($p['bin_id']) ? (int) $p['bin_id'] : null,
            'order_id' => $orderId,
            'job_card_id' => $jobCardId > 0 ? $jobCardId : null,
            'party_id' => $karigarId,
            'debit_account_id' => $qcAcc,
            'credit_account_id' => $karigarAcc,
            'remarks' => 'Ornament received from karigar',
            'created_by' => (int) (session('admin_id') ?: 0),
        ], [[
            'item_type' => 'FG',
            'item_key' => 'TAG-' . $tagNo,
            'tag_no' => $tagNo,
            'material_name' => 'Ornament Receive',
            'qty_pcs' => (float) ($p['qty'] ?? 1),
            'qty_cts' => (float) ($p['diamond_cts_used'] ?? 0),
            'qty_weight' => (float) ($p['gross_wt'] ?? 0),
            'fine_gold' => (float) ($p['net_gold_wt'] ?? 0),
        ]]);

        return $this->ok(['fg_item_id' => $fgId, 'tag_no' => $tagNo, 'voucher' => $voucher], 'Ornament received.', 201);
    }
}
