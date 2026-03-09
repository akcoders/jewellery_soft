<?php

namespace App\Controllers\Api;

use App\Services\PostingService;
use RuntimeException;

class DiamondBagsController extends ApiBaseController
{
    public function create()
    {
        $p = $this->payload();
        $bagNo = trim((string) ($p['bag_no'] ?? ''));
        if ($bagNo === '') {
            $bagNo = 'BAG-' . date('YmdHis');
        }

        $db = db_connect();
        if ($db->table('diamond_bags')->where('bag_no', $bagNo)->countAllResults() > 0) {
            return $this->fail('Bag no already exists.', 422);
        }

        $id = (int) $db->table('diamond_bags')->insert([
            'bag_no' => $bagNo,
            'order_id' => isset($p['order_id']) ? (int) $p['order_id'] : null,
            'warehouse_id' => isset($p['warehouse_id']) ? (int) $p['warehouse_id'] : null,
            'bin_id' => isset($p['bin_id']) ? (int) $p['bin_id'] : null,
            'shape' => (string) ($p['shape'] ?? ''),
            'chalni_size' => (string) ($p['chalni_size'] ?? ''),
            'chalni_min' => isset($p['chalni_min']) ? (float) $p['chalni_min'] : null,
            'chalni_max' => isset($p['chalni_max']) ? (float) $p['chalni_max'] : null,
            'color' => (string) ($p['color'] ?? ''),
            'clarity' => (string) ($p['clarity'] ?? ''),
            'diamond_cut' => (string) ($p['diamond_cut'] ?? ''),
            'fluorescence' => (string) ($p['fluorescence'] ?? ''),
            'pcs_balance' => (float) ($p['pcs'] ?? 0),
            'cts_balance' => (float) ($p['cts'] ?? 0),
            'notes' => (string) ($p['notes'] ?? ''),
            'created_by' => (int) (session('admin_id') ?: 0),
        ], true);

        return $this->ok(['bag_id' => $id, 'bag_no' => $bagNo], 'Diamond bag created.', 201);
    }

    public function transfer(int $bagId)
    {
        $p = $this->payload();
        $toWarehouseId = (int) ($p['to_warehouse_id'] ?? 0);
        if ($toWarehouseId <= 0) {
            return $this->fail('to_warehouse_id is required.', 422);
        }

        $db = db_connect();
        $bag = $db->table('diamond_bags')->where('id', $bagId)->get()->getRowArray();
        if (! $bag) {
            return $this->fail('Bag not found.', 404);
        }

        $fromWarehouseId = (int) ($bag['warehouse_id'] ?? 0);
        $fromBinId = (int) ($bag['bin_id'] ?? 0);
        $toBinId = (int) ($p['to_bin_id'] ?? 0);
        $issuePcs = (float) ($p['pcs'] ?? $bag['pcs_balance'] ?? 0);
        $issueCts = (float) ($p['cts'] ?? $bag['cts_balance'] ?? 0);

        if ($issuePcs <= 0 && $issueCts <= 0) {
            return $this->fail('pcs or cts must be greater than zero.', 422);
        }

        try {
            $posting = new PostingService($db);
            $fromAcc = $posting->ensureAccount('WAREHOUSE', 'WH-' . $fromWarehouseId, 'Warehouse #' . $fromWarehouseId, 'warehouses', $fromWarehouseId);
            $toAcc = $posting->ensureAccount('WAREHOUSE', 'WH-' . $toWarehouseId, 'Warehouse #' . $toWarehouseId, 'warehouses', $toWarehouseId);
            $voucher = $posting->postVoucher([
                'voucher_type' => 'DIAMOND_BAG_TRANSFER',
                'voucher_date' => (string) ($p['date'] ?? date('Y-m-d')),
                'from_warehouse_id' => $fromWarehouseId,
                'from_bin_id' => $fromBinId > 0 ? $fromBinId : null,
                'to_warehouse_id' => $toWarehouseId,
                'to_bin_id' => $toBinId > 0 ? $toBinId : null,
                'debit_account_id' => $toAcc,
                'credit_account_id' => $fromAcc,
                'remarks' => 'Diamond bag transfer ' . $bag['bag_no'],
                'created_by' => (int) (session('admin_id') ?: 0),
            ], [[
                'item_type' => 'DIAMOND_BAG',
                'item_key' => 'BAG-' . $bagId,
                'material_name' => 'Bag ' . $bag['bag_no'],
                'bag_id' => $bagId,
                'shape' => $bag['shape'] ?? null,
                'chalni_size' => $bag['chalni_size'] ?? null,
                'color' => $bag['color'] ?? null,
                'clarity' => $bag['clarity'] ?? null,
                'qty_pcs' => $issuePcs,
                'qty_cts' => $issueCts,
                'qty_weight' => 0,
            ]]);

            $db->table('diamond_bags')->where('id', $bagId)->update([
                'warehouse_id' => $toWarehouseId,
                'bin_id' => $toBinId > 0 ? $toBinId : null,
            ]);

            $db->table('diamond_bag_history')->insert([
                'bag_id' => $bagId,
                'action_type' => 'TRANSFER',
                'ref_voucher_id' => $voucher['voucher_id'],
                'from_warehouse_id' => $fromWarehouseId > 0 ? $fromWarehouseId : null,
                'from_bin_id' => $fromBinId > 0 ? $fromBinId : null,
                'to_warehouse_id' => $toWarehouseId,
                'to_bin_id' => $toBinId > 0 ? $toBinId : null,
                'pcs' => $issuePcs,
                'cts' => $issueCts,
                'remarks' => (string) ($p['remarks'] ?? ''),
                'created_by' => (int) (session('admin_id') ?: 0),
            ]);

            return $this->ok(['voucher' => $voucher], 'Bag transferred.');
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->fail('Bag transfer failed.', 500, $e->getMessage());
        }
    }

    public function split(int $bagId)
    {
        $p = $this->payload();
        $splitPcs = (float) ($p['pcs'] ?? 0);
        $splitCts = (float) ($p['cts'] ?? 0);
        if ($splitPcs <= 0 && $splitCts <= 0) {
            return $this->fail('pcs or cts required for split.', 422);
        }

        $db = db_connect();
        $bag = $db->table('diamond_bags')->where('id', $bagId)->get()->getRowArray();
        if (! $bag) {
            return $this->fail('Bag not found.', 404);
        }
        if ($splitPcs > (float) ($bag['pcs_balance'] ?? 0) || $splitCts > (float) ($bag['cts_balance'] ?? 0)) {
            return $this->fail('Split exceeds bag balance.', 422);
        }

        $newBagNo = trim((string) ($p['new_bag_no'] ?? ('BAG-' . date('YmdHis'))));

        $db->transStart();
        $newBagId = (int) $db->table('diamond_bags')->insert([
            'bag_no' => $newBagNo,
            'warehouse_id' => $bag['warehouse_id'] ?? null,
            'bin_id' => $bag['bin_id'] ?? null,
            'shape' => $bag['shape'] ?? null,
            'chalni_size' => $bag['chalni_size'] ?? null,
            'chalni_min' => $bag['chalni_min'] ?? null,
            'chalni_max' => $bag['chalni_max'] ?? null,
            'color' => $bag['color'] ?? null,
            'clarity' => $bag['clarity'] ?? null,
            'diamond_cut' => $bag['diamond_cut'] ?? null,
            'fluorescence' => $bag['fluorescence'] ?? null,
            'pcs_balance' => $splitPcs,
            'cts_balance' => $splitCts,
            'parent_bag_id' => $bagId,
            'created_by' => (int) (session('admin_id') ?: 0),
        ], true);

        $db->table('diamond_bags')->where('id', $bagId)->update([
            'pcs_balance' => round((float) $bag['pcs_balance'] - $splitPcs, 3),
            'cts_balance' => round((float) $bag['cts_balance'] - $splitCts, 3),
        ]);

        $db->table('diamond_bag_history')->insertBatch([
            [
                'bag_id' => $bagId,
                'action_type' => 'SPLIT_OUT',
                'pcs' => $splitPcs,
                'cts' => $splitCts,
                'remarks' => 'Split to bag #' . $newBagNo,
                'created_by' => (int) (session('admin_id') ?: 0),
            ],
            [
                'bag_id' => $newBagId,
                'action_type' => 'SPLIT_IN',
                'pcs' => $splitPcs,
                'cts' => $splitCts,
                'remarks' => 'Split from bag #' . $bag['bag_no'],
                'created_by' => (int) (session('admin_id') ?: 0),
            ],
        ]);

        $db->transComplete();

        return $this->ok(['new_bag_id' => $newBagId, 'new_bag_no' => $newBagNo], 'Bag split completed.');
    }

    public function merge()
    {
        $p = $this->payload();
        $sourceId = (int) ($p['source_bag_id'] ?? 0);
        $targetId = (int) ($p['target_bag_id'] ?? 0);
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            return $this->fail('source_bag_id and target_bag_id are required and must differ.', 422);
        }

        $db = db_connect();
        $src = $db->table('diamond_bags')->where('id', $sourceId)->get()->getRowArray();
        $tgt = $db->table('diamond_bags')->where('id', $targetId)->get()->getRowArray();
        if (! $src || ! $tgt) {
            return $this->fail('Bags not found.', 404);
        }

        $db->transStart();
        $db->table('diamond_bags')->where('id', $targetId)->update([
            'pcs_balance' => round((float) $tgt['pcs_balance'] + (float) $src['pcs_balance'], 3),
            'cts_balance' => round((float) $tgt['cts_balance'] + (float) $src['cts_balance'], 3),
        ]);

        $db->table('diamond_bags')->where('id', $sourceId)->update([
            'pcs_balance' => 0,
            'cts_balance' => 0,
            'notes' => trim((string) ($src['notes'] ?? '') . ' | Merged into bag ' . $tgt['bag_no']),
        ]);

        $db->table('diamond_bag_history')->insert([
            'bag_id' => $targetId,
            'action_type' => 'MERGE_IN',
            'pcs' => (float) $src['pcs_balance'],
            'cts' => (float) $src['cts_balance'],
            'remarks' => 'Merged from bag #' . $src['bag_no'],
            'created_by' => (int) (session('admin_id') ?: 0),
        ]);

        $db->transComplete();

        return $this->ok(['source_bag_id' => $sourceId, 'target_bag_id' => $targetId], 'Bags merged.');
    }

    public function history(int $bagId)
    {
        $rows = db_connect()->table('diamond_bag_history')->where('bag_id', $bagId)->orderBy('id', 'DESC')->get()->getResultArray();
        return $this->ok($rows);
    }
}
