<?php

namespace App\Controllers\Api\Mobile;

class InventoryController extends MobileBaseController
{
    public function __construct()
    {
        helper('url');
    }

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
            ->select('ih.id, ih.voucher_no, ih.issue_date, ih.issue_to, ih.purpose, ih.notes, ih.created_at, ih.karigar_id, k.name as karigar_name')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->orderBy('ih.id', 'DESC')
            ->get(200)
            ->getResultArray();

        foreach ($rows as &$row) {
            $voucherNo = trim((string) ($row['voucher_no'] ?? ''));
            $row['voucher_url'] = $voucherNo !== ''
                ? base_url('admin/issuements/voucher/' . rawurlencode($voucherNo) . '?download=1')
                : null;
        }
        unset($row);

        return $this->ok($rows);
    }

    public function diamondReturns()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('return_headers rh')
            ->select('rh.id, rh.voucher_no, rh.return_date, rh.return_from, rh.purpose, rh.notes, rh.created_at, rh.issue_id, rh.karigar_id, k.name as karigar_name')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->orderBy('rh.id', 'DESC')
            ->get(200)
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['receipt_url'] = base_url('admin/diamond-inventory/returns/receipt/' . (int) $row['id'] . '?download=1');
        }
        unset($row);

        return $this->ok($rows);
    }

    public function diamondPurchases()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('purchase_headers ph')
            ->select('ph.id, ph.purchase_date, ph.supplier_name, ph.invoice_no, ph.payment_terms_days, ph.due_date, ph.notes, ph.created_at')
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
            ->select('gih.id, gih.voucher_no, gih.issue_date, gih.karigar_id, gih.issue_to, gih.purpose, gih.notes, gih.created_at, k.name as karigar_name')
            ->join('karigars k', 'k.id = gih.karigar_id', 'left')
            ->orderBy('gih.id', 'DESC')
            ->get(200)
            ->getResultArray();

        foreach ($rows as &$row) {
            $voucherNo = trim((string) ($row['voucher_no'] ?? ''));
            $row['voucher_url'] = $voucherNo !== ''
                ? base_url('admin/issuements/voucher/' . rawurlencode($voucherNo) . '?download=1')
                : null;
        }
        unset($row);

        return $this->ok($rows);
    }

    public function goldReturns()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('gold_inventory_return_headers grh')
            ->select('grh.id, grh.voucher_no, grh.return_date, grh.karigar_id, grh.return_from, grh.purpose, grh.notes, grh.created_at, k.name as karigar_name')
            ->join('karigars k', 'k.id = grh.karigar_id', 'left')
            ->orderBy('grh.id', 'DESC')
            ->get(200)
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['receipt_url'] = base_url('admin/gold-inventory/returns/receipt/' . (int) $row['id'] . '?download=1');
        }
        unset($row);

        return $this->ok($rows);
    }

    public function goldPurchases()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('gold_inventory_purchase_headers gph')
            ->select('gph.*, COALESCE(v.name, gph.supplier_name) as vendor_name, COUNT(gpl.id) as line_count, COALESCE(SUM(gpl.weight_gm),0) as total_weight_gm, COALESCE(SUM(gpl.line_value),0) as line_taxable_amount', false)
            ->join('gold_inventory_purchase_lines gpl', 'gpl.purchase_id = gph.id', 'left')
            ->join('vendors v', 'v.id = gph.vendor_id', 'left')
            ->groupBy('gph.id')
            ->orderBy('gph.id', 'DESC')
            ->get(200)
            ->getResultArray();

        foreach ($rows as &$row) {
            $documentId = (int) ($row['production_document_id'] ?? 0);
            $row['invoice_document_url'] = $documentId > 0
                ? base_url('admin/accounts/production-document/' . $documentId)
                : null;
        }
        unset($row);

        return $this->ok($rows);
    }

    public function stoneIssues()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('stone_inventory_issue_headers sih')
            ->select('sih.id, sih.voucher_no, sih.issue_date, sih.karigar_id, sih.issue_to, sih.purpose, sih.notes, sih.created_at, k.name as karigar_name')
            ->join('karigars k', 'k.id = sih.karigar_id', 'left')
            ->orderBy('sih.id', 'DESC')
            ->get(200)
            ->getResultArray();

        foreach ($rows as &$row) {
            $voucherNo = trim((string) ($row['voucher_no'] ?? ''));
            $row['voucher_url'] = $voucherNo !== ''
                ? base_url('admin/issuements/voucher/' . rawurlencode($voucherNo) . '?download=1')
                : null;
        }
        unset($row);

        return $this->ok($rows);
    }

    public function stoneReturns()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('stone_inventory_return_headers srh')
            ->select('srh.id, srh.voucher_no, srh.return_date, srh.karigar_id, srh.return_from, srh.purpose, srh.notes, srh.created_at, k.name as karigar_name')
            ->join('karigars k', 'k.id = srh.karigar_id', 'left')
            ->orderBy('srh.id', 'DESC')
            ->get(200)
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['receipt_url'] = base_url('admin/stone-inventory/returns/receipt/' . (int) $row['id'] . '?download=1');
        }
        unset($row);

        return $this->ok($rows);
    }

    public function stonePurchases()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $rows = db_connect()->table('stone_inventory_purchase_headers sph')
            ->select('sph.id, sph.purchase_date, sph.supplier_name, sph.invoice_no, sph.notes, sph.created_at')
            ->orderBy('sph.id', 'DESC')
            ->get(200)
            ->getResultArray();

        return $this->ok($rows);
    }

    public function issuements()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $db = db_connect();
        $sources = [
            ['table' => 'gold_inventory_issue_headers', 'lines' => 'gold_inventory_issue_lines', 'material' => 'Gold', 'qty' => 'weight_gm'],
            ['table' => 'issue_headers', 'lines' => 'issue_lines', 'material' => 'Diamond', 'qty' => 'carat'],
            ['table' => 'stone_inventory_issue_headers', 'lines' => 'stone_inventory_issue_lines', 'material' => 'Stone', 'qty' => 'qty'],
        ];
        $grouped = [];

        foreach ($sources as $source) {
            $rows = $db->table($source['table'] . ' ih')
                ->select('ih.id, ih.voucher_no, ih.issue_date, ih.issue_to, ih.purpose, ih.notes, ih.created_at, ih.karigar_id, k.name as karigar_name, iloc.name as warehouse_name, COUNT(il.id) as line_count, COALESCE(SUM(il.' . $source['qty'] . '),0) as total_qty, COALESCE(SUM(il.line_value),0) as total_value', false)
                ->join($source['lines'] . ' il', 'il.issue_id = ih.id', 'left')
                ->join('karigars k', 'k.id = ih.karigar_id', 'left')
                ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
                ->groupBy('ih.id')
                ->orderBy('ih.id', 'DESC')
                ->get(300)
                ->getResultArray();

            foreach ($rows as $row) {
                $voucher = trim((string) ($row['voucher_no'] ?? ''));
                $key = $voucher !== '' ? $voucher : $source['material'] . ':' . (int) $row['id'];
                if (! isset($grouped[$key])) {
                    $grouped[$key] = $row + [
                        'materials' => [],
                        'material_type' => $source['material'],
                        'total_lines' => 0,
                    ];
                    $grouped[$key]['total_value'] = 0.0;
                }
                $grouped[$key]['materials'][] = $source['material'];
                $grouped[$key]['total_lines'] += (int) ($row['line_count'] ?? 0);
                $grouped[$key]['total_value'] += (float) ($row['total_value'] ?? 0);
                if (strcmp((string) ($row['created_at'] ?? ''), (string) ($grouped[$key]['created_at'] ?? '')) > 0) {
                    $grouped[$key]['created_at'] = $row['created_at'];
                }
            }
        }

        $result = array_values($grouped);
        foreach ($result as &$row) {
            $row['materials'] = array_values(array_unique($row['materials']));
            $row['material_type'] = count($row['materials']) > 1 ? 'Mixed' : (string) ($row['materials'][0] ?? $row['material_type']);
            unset($row['line_count'], $row['total_qty']);
        }
        unset($row);
        usort($result, static fn(array $a, array $b): int => strcmp(
            (string) ($b['issue_date'] ?? $b['created_at'] ?? ''),
            (string) ($a['issue_date'] ?? $a['created_at'] ?? '')
        ));

        return $this->ok(array_slice($result, 0, 200));
    }
}
