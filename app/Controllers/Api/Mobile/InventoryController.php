<?php

namespace App\Controllers\Api\Mobile;

class InventoryController extends MobileBaseController
{
    public function summary()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $db = db_connect();

        $diamond = $db->table('stock')
            ->select('COALESCE(SUM(pcs_balance),0) as total_pcs, COALESCE(SUM(carat_balance),0) as total_carat, COALESCE(SUM(stock_value),0) as total_value')
            ->get()
            ->getRowArray() ?: [];

        $gold = $db->table('gold_inventory_stock')
            ->select('COALESCE(SUM(weight_balance_gm),0) as total_weight_gm, COALESCE(SUM(fine_balance_gm),0) as total_fine_gm, COALESCE(SUM(stock_value),0) as total_value')
            ->get()
            ->getRowArray() ?: [];

        $stone = $db->table('stone_inventory_stock')
            ->select('COALESCE(SUM(qty_balance),0) as total_qty, COALESCE(SUM(stock_value),0) as total_value')
            ->get()
            ->getRowArray() ?: [];

        return $this->ok([
            'diamond' => [
                'total_pcs' => (float) ($diamond['total_pcs'] ?? 0),
                'total_carat' => (float) ($diamond['total_carat'] ?? 0),
                'total_value' => (float) ($diamond['total_value'] ?? 0),
            ],
            'gold' => [
                'total_weight_gm' => (float) ($gold['total_weight_gm'] ?? 0),
                'total_fine_gm' => (float) ($gold['total_fine_gm'] ?? 0),
                'total_value' => (float) ($gold['total_value'] ?? 0),
            ],
            'stone' => [
                'total_qty' => (float) ($stone['total_qty'] ?? 0),
                'total_value' => (float) ($stone['total_value'] ?? 0),
            ],
        ]);
    }

    public function diamonds()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $search = trim((string) $this->request->getGet('q'));
        $builder = db_connect()->table('stock s')
            ->select('i.id as item_id, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, i.cut, s.pcs_balance, s.carat_balance, s.avg_cost_per_carat, s.stock_value, s.updated_at')
            ->join('items i', 'i.id = s.item_id', 'inner');

        if ($search !== '') {
            $builder->groupStart()
                ->like('i.diamond_type', $search)
                ->orLike('i.shape', $search)
                ->orLike('i.chalni_from', $search)
                ->orLike('i.chalni_to', $search)
                ->orLike('i.color', $search)
                ->orLike('i.clarity', $search)
                ->groupEnd();
        }

        $rows = $builder->orderBy('i.diamond_type', 'ASC')->orderBy('i.id', 'DESC')->get()->getResultArray();
        return $this->ok($rows);
    }

    public function gold()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $search = trim((string) $this->request->getGet('q'));
        $builder = db_connect()->table('gold_inventory_stock s')
            ->select('gi.id as item_id, gi.purity_code, gi.purity_percent, gi.color_name, gi.form_type, s.weight_balance_gm, s.fine_balance_gm, s.avg_cost_per_gm, s.stock_value, s.updated_at')
            ->join('gold_inventory_items gi', 'gi.id = s.item_id', 'inner');

        if ($search !== '') {
            $builder->groupStart()
                ->like('gi.purity_code', $search)
                ->orLike('gi.color_name', $search)
                ->orLike('gi.form_type', $search)
                ->groupEnd();
        }

        $rows = $builder->orderBy('gi.purity_percent', 'DESC')->orderBy('gi.id', 'DESC')->get()->getResultArray();
        return $this->ok($rows);
    }

    public function stones()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $search = trim((string) $this->request->getGet('q'));
        $builder = db_connect()->table('stone_inventory_stock s')
            ->select('si.id as item_id, si.product_name, si.stone_type, si.default_rate, s.qty_balance, s.avg_rate, s.stock_value, s.updated_at')
            ->join('stone_inventory_items si', 'si.id = s.item_id', 'inner');

        if ($search !== '') {
            $builder->groupStart()
                ->like('si.product_name', $search)
                ->orLike('si.stone_type', $search)
                ->groupEnd();
        }

        $rows = $builder->orderBy('si.product_name', 'ASC')->orderBy('si.id', 'DESC')->get()->getResultArray();
        return $this->ok($rows);
    }

    public function diamondIssues()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('issue_headers ih')
            ->select('ih.id, ih.issue_date, ih.issue_to, ih.purpose, ih.notes, ih.created_at')
            ->orderBy('ih.id', 'DESC')
            ->get(200)
            ->getResultArray();

        return $this->ok($rows);
    }

    public function diamondReturns()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('return_headers rh')
            ->select('rh.id, rh.return_date, rh.return_from, rh.purpose, rh.notes, rh.created_at, rh.order_id, rh.issue_id')
            ->orderBy('rh.id', 'DESC')
            ->get(200)
            ->getResultArray();

        return $this->ok($rows);
    }

    public function diamondPurchases()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('purchase_headers ph')
            ->select('ph.id, ph.purchase_date, ph.supplier_name, ph.invoice_no, ph.notes, ph.created_at')
            ->orderBy('ph.id', 'DESC')
            ->get(200)
            ->getResultArray();

        return $this->ok($rows);
    }

    public function goldIssues()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('gold_inventory_issue_headers gih')
            ->select('gih.id, gih.issue_date, gih.order_id, gih.karigar_id, gih.issue_to, gih.purpose, gih.notes, gih.created_at, k.name as karigar_name')
            ->join('karigars k', 'k.id = gih.karigar_id', 'left')
            ->orderBy('gih.id', 'DESC')
            ->get(200)
            ->getResultArray();

        return $this->ok($rows);
    }

    public function goldReturns()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('gold_inventory_return_headers grh')
            ->select('grh.id, grh.return_date, grh.order_id, grh.karigar_id, grh.return_from, grh.purpose, grh.notes, grh.created_at, k.name as karigar_name')
            ->join('karigars k', 'k.id = grh.karigar_id', 'left')
            ->orderBy('grh.id', 'DESC')
            ->get(200)
            ->getResultArray();

        return $this->ok($rows);
    }

    public function goldPurchases()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('gold_inventory_purchase_headers gph')
            ->select('gph.id, gph.purchase_date, gph.supplier_name, gph.invoice_no, gph.notes, gph.created_at')
            ->orderBy('gph.id', 'DESC')
            ->get(200)
            ->getResultArray();

        return $this->ok($rows);
    }
}
