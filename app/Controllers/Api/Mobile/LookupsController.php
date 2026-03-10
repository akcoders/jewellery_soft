<?php

namespace App\Controllers\Api\Mobile;

class LookupsController extends MobileBaseController
{
    public function karigars()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('karigars')
            ->select('id, name, phone')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        return $this->ok($rows);
    }

    public function vendors()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('vendors')
            ->select('id, name, phone')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        return $this->ok($rows);
    }

    public function locations()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('inventory_locations')
            ->select('id, name')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        return $this->ok($rows);
    }

    public function orders()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('orders o')
            ->select('o.id, o.order_no, o.order_type, o.status, o.due_date, o.assigned_karigar_id, k.name as karigar_name')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left')
            ->whereNotIn('o.status', ['Cancelled', 'Completed'])
            ->where('o.assigned_karigar_id IS NOT NULL', null, false)
            ->where('o.assigned_karigar_id >', 0)
            ->orderBy('o.id', 'DESC')
            ->get()
            ->getResultArray();

        return $this->ok($rows);
    }

    public function diamondItems()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('items i')
            ->select('i.*, COALESCE(s.pcs_balance,0) as pcs_balance, COALESCE(s.carat_balance,0) as carat_balance, COALESCE(s.avg_cost_per_carat,0) as avg_cost_per_carat', false)
            ->join('stock s', 's.item_id = i.id', 'left')
            ->orderBy('i.diamond_type', 'ASC')
            ->orderBy('i.id', 'DESC')
            ->get()
            ->getResultArray();

        return $this->ok($rows);
    }

    public function goldItems()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('gold_inventory_items gi')
            ->select('gi.*, COALESCE(s.weight_balance_gm,0) as weight_balance_gm, COALESCE(s.fine_balance_gm,0) as fine_balance_gm, COALESCE(s.avg_cost_per_gm,0) as avg_cost_per_gm', false)
            ->join('gold_inventory_stock s', 's.item_id = gi.id', 'left')
            ->orderBy('gi.purity_percent', 'DESC')
            ->orderBy('gi.id', 'DESC')
            ->get()
            ->getResultArray();

        return $this->ok($rows);
    }

    public function stoneItems()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('stone_inventory_items si')
            ->select('si.*, COALESCE(s.qty_balance,0) as qty_balance, COALESCE(s.avg_rate,0) as avg_rate', false)
            ->join('stone_inventory_stock s', 's.item_id = si.id', 'left')
            ->orderBy('si.product_name', 'ASC')
            ->orderBy('si.id', 'DESC')
            ->get()
            ->getResultArray();

        return $this->ok($rows);
    }

    public function diamondIssues()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $orderId = (int) $this->request->getGet('order_id');
        $builder = db_connect()->table('issue_headers ih')
            ->select('ih.id, ih.order_id, ih.issue_date, ih.voucher_no, ih.issue_to, ih.karigar_id, k.name as karigar_name')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->orderBy('ih.id', 'DESC');

        if ($orderId > 0) {
            $builder->where('ih.order_id', $orderId);
        }

        $rows = $builder->get()->getResultArray();
        return $this->ok($rows);
    }

    public function goldIssues()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $orderId = (int) $this->request->getGet('order_id');
        $builder = db_connect()->table('gold_inventory_issue_headers ih')
            ->select('ih.id, ih.order_id, ih.issue_date, ih.voucher_no, ih.issue_to, ih.karigar_id, k.name as karigar_name')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->orderBy('ih.id', 'DESC');

        if ($orderId > 0) {
            $builder->where('ih.order_id', $orderId);
        }

        $rows = $builder->get()->getResultArray();
        return $this->ok($rows);
    }

    public function stoneIssues()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $orderId = (int) $this->request->getGet('order_id');
        $builder = db_connect()->table('stone_inventory_issue_headers ih')
            ->select('ih.id, ih.order_id, ih.issue_date, ih.voucher_no, ih.issue_to, ih.karigar_id, k.name as karigar_name')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->orderBy('ih.id', 'DESC');

        if ($orderId > 0) {
            $builder->where('ih.order_id', $orderId);
        }

        $rows = $builder->get()->getResultArray();
        return $this->ok($rows);
    }
}
