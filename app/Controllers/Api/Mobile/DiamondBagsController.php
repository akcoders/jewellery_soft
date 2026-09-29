<?php

namespace App\Controllers\Api\Mobile;

class DiamondBagsController extends MobileBaseController
{
    public function index()
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }

        $db = db_connect();
        if (! $db->tableExists('diamond_bags')) {
            return $this->ok(['items' => []]);
        }

        $items = $db->table('diamond_bags b')
            ->select('b.*, dr.requirement_no, o.order_no, COUNT(DISTINCT bi.id) AS item_count, COUNT(DISTINCT il.allocation_order_id) AS order_count, COUNT(DISTINCT il.id) AS issue_line_count', false)
            ->join('diamond_bag_items bi', 'bi.bag_id = b.id', 'left')
            ->join('issue_lines il', 'il.bag_id = b.id', 'left')
            ->join('diamond_requirements dr', 'dr.id = b.requirement_id', 'left')
            ->join('orders o', 'o.id = b.order_id', 'left')
            ->groupBy('b.id')
            ->orderBy('b.id', 'DESC')
            ->get()->getResultArray();

        foreach ($items as &$item) {
            $item['status'] = $this->bagStatus($item);
            $item['audit_image_url'] = ! empty($item['audit_image_path'])
                ? base_url(ltrim((string) $item['audit_image_path'], '/'))
                : null;
        }
        unset($item);

        return $this->ok(['items' => $items]);
    }

    public function show(int $id)
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }

        $db = db_connect();
        $bag = $db->table('diamond_bags b')
            ->select('b.*, dr.requirement_no, dr.status AS requirement_status, o.order_no, creator.name AS prepared_by_name')
            ->join('diamond_requirements dr', 'dr.id = b.requirement_id', 'left')
            ->join('orders o', 'o.id = b.order_id', 'left')
            ->join('admin_users creator', 'creator.id = b.created_by', 'left')
            ->where('b.id', $id)->get()->getRowArray();
        if (! is_array($bag)) {
            return $this->fail('Diamond bag not found.', 404);
        }

        $items = $db->table('diamond_bag_items bi')
            ->select('bi.*, i.diamond_type, i.color, i.clarity, i.cut, sm.name AS shape_name, sz.size_code, sz.size_label')
            ->join('items i', 'i.id = bi.inventory_item_id', 'left')
            ->join('diamond_shape_masters sm', 'sm.id = bi.shape_master_id', 'left')
            ->join('diamond_size_masters sz', 'sz.id = bi.size_master_id', 'left')
            ->where('bi.bag_id', $id)
            ->orderBy('bi.id', 'ASC')->get()->getResultArray();

        $movements = $db->table('diamond_bag_movements bm')
            ->select('bm.*, ih.voucher_no, o.order_no, k.name AS karigar_name, i.diamond_type, sm.name AS shape_name, sz.size_label')
            ->join('issue_lines il', 'il.id = bm.issue_line_id', 'left')
            ->join('issue_headers ih', 'ih.id = il.issue_id', 'left')
            ->join('diamond_bag_items bi', 'bi.id = bm.bag_item_id', 'left')
            ->join('items i', 'i.id = bi.inventory_item_id', 'left')
            ->join('diamond_shape_masters sm', 'sm.id = bi.shape_master_id', 'left')
            ->join('diamond_size_masters sz', 'sz.id = bi.size_master_id', 'left')
            ->join('orders o', 'o.id = bm.order_id', 'left')
            ->join('karigars k', 'k.id = bm.karigar_id', 'left')
            ->where('bm.bag_id', $id)
            ->orderBy('bm.id', 'DESC')->get()->getResultArray();

        $bag['issue_line_count'] = $db->table('issue_lines')->where('bag_id', $id)->countAllResults();
        $bag['status'] = $this->bagStatus($bag);
        $bag['audit_image_url'] = ! empty($bag['audit_image_path'])
            ? base_url(ltrim((string) $bag['audit_image_path'], '/'))
            : null;

        return $this->ok([
            'bag' => $bag,
            'items' => $items,
            'movements' => $movements,
        ]);
    }

    /** @param array<string,mixed> $bag */
    private function bagStatus(array $bag): string
    {
        if ((float) ($bag['cts_balance'] ?? 0) <= .0005) {
            return 'consumed';
        }
        if ((int) ($bag['issue_line_count'] ?? 0) > 0) {
            return 'partly_issued';
        }
        return 'ready';
    }
}
