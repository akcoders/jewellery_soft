<?php

namespace App\Controllers\Api\Mobile;

use App\Services\TaxMasterService;
use App\Services\DiamondBagTraceService;

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

    public function gstMasters()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        return $this->ok((new TaxMasterService())->options());
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

    public function diamondBagItems()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        return $this->ok((new DiamondBagTraceService(db_connect()))->availableBagItems());
    }

    public function diamondOrderAllocations()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('orders')
            ->select('id, order_no, order_name, status')
            ->whereNotIn('status', ['Cancelled', 'Completed'])
            ->orderBy('id', 'DESC')
            ->limit(1000)
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

        $builder = db_connect()->table('issue_headers ih')
            ->select('ih.id, ih.issue_date, ih.voucher_no, ih.issue_to, ih.karigar_id, k.name as karigar_name')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->orderBy('ih.id', 'DESC');

        $rows = $builder->get()->getResultArray();
        return $this->ok($rows);
    }

    public function diamondIssueLines(int $issueId)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = (new DiamondBagTraceService(db_connect()))->returnableIssueLines($issueId);
        foreach ($rows as &$row) {
            $row['id'] = (int) ($row['issue_line_id'] ?? 0);
            $row['pcs_available'] = (float) ($row['available_pcs'] ?? 0);
            $row['weight_cts_available'] = (float) ($row['available_cts'] ?? 0);
        }
        unset($row);
        return $this->ok($rows);
    }

    public function goldIssues()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $builder = db_connect()->table('gold_inventory_issue_headers ih')
            ->select('ih.id, ih.issue_date, ih.voucher_no, ih.issue_to, ih.karigar_id, k.name as karigar_name')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->orderBy('ih.id', 'DESC');

        $rows = $builder->get()->getResultArray();
        return $this->ok($rows);
    }

    public function stoneIssues()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $builder = db_connect()->table('stone_inventory_issue_headers ih')
            ->select('ih.id, ih.issue_date, ih.voucher_no, ih.issue_to, ih.karigar_id, k.name as karigar_name')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->orderBy('ih.id', 'DESC');

        $rows = $builder->get()->getResultArray();
        return $this->ok($rows);
    }
}
