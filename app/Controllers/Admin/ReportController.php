<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\KarigarModel;

class ReportController extends BaseController
{
    public function index()
    {
        return redirect()->to(site_url('admin/reports/gold-ledger'));
    }

    public function goldLedger(): string
    {
        $filters = [
            'from' => trim((string) $this->request->getGet('from')),
            'to' => trim((string) $this->request->getGet('to')),
            'karigar_id' => (int) ($this->request->getGet('karigar_id') ?? 0),
            'order_no' => trim((string) $this->request->getGet('order_no')),
            'txn_type' => trim((string) $this->request->getGet('txn_type')),
        ];

        $builder = db_connect()->table('gold_inventory_ledger_entries gle')
            ->select("gle.*, gi.purity_code, gi.color_name, gi.form_type, gp.purity_code as master_purity_code, o.order_no, k.name as karigar_name, iloc.name as location_name, COALESCE(gih.voucher_no, gph.invoice_no, grh.voucher_no, CONCAT('ADJ#', gah.id)) as reference_voucher_no", false)
            ->join('gold_inventory_items gi', 'gi.id = gle.item_id', 'left')
            ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
            ->join('orders o', 'o.id = gle.order_id', 'left')
            ->join('karigars k', 'k.id = gle.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = gle.location_id', 'left')
            ->join('gold_inventory_issue_headers gih', "gih.id = gle.reference_id AND gle.reference_table = 'gold_inventory_issue_headers'", 'left', false)
            ->join('gold_inventory_purchase_headers gph', "gph.id = gle.reference_id AND gle.reference_table = 'gold_inventory_purchase_headers'", 'left', false)
            ->join('gold_inventory_return_headers grh', "grh.id = gle.reference_id AND gle.reference_table = 'gold_inventory_return_headers'", 'left', false)
            ->join('gold_inventory_adjustment_headers gah', "gah.id = gle.reference_id AND gle.reference_table = 'gold_inventory_adjustment_headers'", 'left', false)
            ->orderBy('gle.txn_date', 'DESC')
            ->orderBy('gle.id', 'DESC');

        if ($filters['from'] !== '') {
            $builder->where('gle.txn_date >=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $builder->where('gle.txn_date <=', $filters['to']);
        }
        if ($filters['karigar_id'] > 0) {
            $builder->where('gle.karigar_id', $filters['karigar_id']);
        }
        if ($filters['order_no'] !== '') {
            $builder->like('o.order_no', $filters['order_no']);
        }
        if ($filters['txn_type'] !== '') {
            $builder->where('gle.txn_type', $filters['txn_type']);
        }

        $rows = $builder->get()->getResultArray();

        $cards = [
            'debit_weight' => 0.0,
            'credit_weight' => 0.0,
            'debit_fine' => 0.0,
            'credit_fine' => 0.0,
        ];
        foreach ($rows as $row) {
            $cards['debit_weight'] += (float) ($row['debit_weight_gm'] ?? 0);
            $cards['credit_weight'] += (float) ($row['credit_weight_gm'] ?? 0);
            $cards['debit_fine'] += (float) ($row['debit_fine_gm'] ?? 0);
            $cards['credit_fine'] += (float) ($row['credit_fine_gm'] ?? 0);
        }
        $cards['balance_weight'] = $cards['debit_weight'] - $cards['credit_weight'];
        $cards['balance_fine'] = $cards['debit_fine'] - $cards['credit_fine'];

        return view('admin/reports/gold_ledger', [
            'title' => 'Gold Ledger Report',
            'rows' => $rows,
            'cards' => $cards,
            'filters' => $filters,
            'karigars' => $this->karigarOptions(),
            'txnTypes' => $this->goldTxnTypes(),
        ]);
    }

    public function diamondLedger(): string
    {
        $filters = [
            'from' => trim((string) $this->request->getGet('from')),
            'to' => trim((string) $this->request->getGet('to')),
            'karigar_id' => (int) ($this->request->getGet('karigar_id') ?? 0),
            'order_no' => trim((string) $this->request->getGet('order_no')),
        ];

        $karigars = $this->karigarOptions();
        $karigarName = '';
        if ($filters['karigar_id'] > 0) {
            foreach ($karigars as $karigar) {
                if ((int) $karigar['id'] === $filters['karigar_id']) {
                    $karigarName = (string) $karigar['name'];
                    break;
                }
            }
        }

        $issueBuilder = db_connect()->table('issue_headers ih')
            ->select("ih.id as ref_id, ih.voucher_no as reference_no, ih.issue_date as txn_date, 'Issue' as txn_type, o.order_no, COALESCE(NULLIF(ih.issue_to,''), k.name, '-') as party_name, ih.purpose, ih.notes, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, il.pcs, il.carat, il.rate_per_carat, il.line_value", false)
            ->join('issue_lines il', 'il.issue_id = ih.id', 'inner')
            ->join('items i', 'i.id = il.item_id', 'left')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left');

        if ($filters['from'] !== '') {
            $issueBuilder->where('ih.issue_date >=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $issueBuilder->where('ih.issue_date <=', $filters['to']);
        }
        if ($filters['order_no'] !== '') {
            $issueBuilder->like('o.order_no', $filters['order_no']);
        }
        if ($filters['karigar_id'] > 0) {
            $issueBuilder->groupStart()->where('o.assigned_karigar_id', $filters['karigar_id']);
            if ($karigarName !== '') {
                $issueBuilder->orLike('ih.issue_to', $karigarName);
            }
            $issueBuilder->groupEnd();
        }
        $issueRows = $issueBuilder->get()->getResultArray();

        $returnBuilder = db_connect()->table('return_headers rh')
            ->select("rh.id as ref_id, rh.voucher_no as reference_no, rh.return_date as txn_date, 'Return' as txn_type, o.order_no, COALESCE(NULLIF(rh.return_from,''), k.name, '-') as party_name, rh.purpose, rh.notes, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, rl.pcs, rl.carat, rl.rate_per_carat, rl.line_value", false)
            ->join('return_lines rl', 'rl.return_id = rh.id', 'inner')
            ->join('items i', 'i.id = rl.item_id', 'left')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left');

        if ($filters['from'] !== '') {
            $returnBuilder->where('rh.return_date >=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $returnBuilder->where('rh.return_date <=', $filters['to']);
        }
        if ($filters['order_no'] !== '') {
            $returnBuilder->like('o.order_no', $filters['order_no']);
        }
        if ($filters['karigar_id'] > 0) {
            $returnBuilder->groupStart()->where('o.assigned_karigar_id', $filters['karigar_id']);
            if ($karigarName !== '') {
                $returnBuilder->orLike('rh.return_from', $karigarName);
            }
            $returnBuilder->groupEnd();
        }
        $returnRows = $returnBuilder->get()->getResultArray();

        $purchaseRows = [];
        if ($filters['karigar_id'] <= 0) {
            $purchaseBuilder = db_connect()->table('purchase_headers ph')
                ->select("ph.id as ref_id, COALESCE(NULLIF(ph.invoice_no,''), CONCAT('PUR#', ph.id)) as reference_no, ph.purchase_date as txn_date, 'Purchase' as txn_type, NULL as order_no, COALESCE(NULLIF(ph.supplier_name,''), '-') as party_name, '' as purpose, ph.notes, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, pl.pcs, pl.carat, pl.rate_per_carat, pl.line_value", false)
                ->join('purchase_lines pl', 'pl.purchase_id = ph.id', 'inner')
                ->join('items i', 'i.id = pl.item_id', 'left');
            if ($filters['from'] !== '') {
                $purchaseBuilder->where('ph.purchase_date >=', $filters['from']);
            }
            if ($filters['to'] !== '') {
                $purchaseBuilder->where('ph.purchase_date <=', $filters['to']);
            }
            $purchaseRows = $purchaseBuilder->get()->getResultArray();
        }

        $rows = array_merge($purchaseRows, $issueRows, $returnRows);
        usort($rows, static function (array $a, array $b): int {
            $dateCompare = strcmp((string) ($b['txn_date'] ?? ''), (string) ($a['txn_date'] ?? ''));
            if ($dateCompare !== 0) {
                return $dateCompare;
            }
            return (int) ($b['ref_id'] ?? 0) <=> (int) ($a['ref_id'] ?? 0);
        });

        $cards = [
            'issue_pcs' => 0.0,
            'issue_cts' => 0.0,
            'return_pcs' => 0.0,
            'return_cts' => 0.0,
        ];
        foreach ($rows as $row) {
            if ((string) ($row['txn_type'] ?? '') === 'Issue') {
                $cards['issue_pcs'] += (float) ($row['pcs'] ?? 0);
                $cards['issue_cts'] += (float) ($row['carat'] ?? 0);
            }
            if ((string) ($row['txn_type'] ?? '') === 'Return') {
                $cards['return_pcs'] += (float) ($row['pcs'] ?? 0);
                $cards['return_cts'] += (float) ($row['carat'] ?? 0);
            }
        }
        $cards['balance_pcs'] = $cards['issue_pcs'] - $cards['return_pcs'];
        $cards['balance_cts'] = $cards['issue_cts'] - $cards['return_cts'];

        return view('admin/reports/diamond_ledger', [
            'title' => 'Diamond Ledger Report',
            'rows' => $rows,
            'cards' => $cards,
            'filters' => $filters,
            'karigars' => $karigars,
        ]);
    }

    public function karigarPerformance(): string
    {
        $filters = [
            'from' => trim((string) ($this->request->getGet('from') ?: date('Y-m-01'))),
            'to' => trim((string) ($this->request->getGet('to') ?: date('Y-m-d'))),
            'mode' => trim((string) ($this->request->getGet('mode') ?: 'month')),
            'karigar_id' => (int) ($this->request->getGet('karigar_id') ?? 0),
        ];

        $completedStatuses = ['Ready', 'Packed', 'Dispatched', 'Delivered'];
        $ordersBuilder = db_connect()->table('orders o')
            ->select('o.id, o.order_no, o.assigned_karigar_id, o.created_at, o.updated_at, k.name as karigar_name')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left')
            ->whereIn('o.status', $completedStatuses)
            ->where('o.assigned_karigar_id IS NOT NULL', null, false)
            ->where('DATE(o.updated_at) >=', $filters['from'])
            ->where('DATE(o.updated_at) <=', $filters['to']);

        if ($filters['karigar_id'] > 0) {
            $ordersBuilder->where('o.assigned_karigar_id', $filters['karigar_id']);
        }
        $orders = $ordersBuilder->get()->getResultArray();

        $issueRows = db_connect()->table('gold_inventory_issue_headers')
            ->select('order_id, karigar_id, MIN(issue_date) as first_issue_date', false)
            ->where('order_id IS NOT NULL', null, false)
            ->where('karigar_id IS NOT NULL', null, false)
            ->groupBy('order_id, karigar_id')
            ->get()
            ->getResultArray();
        $issueMap = [];
        foreach ($issueRows as $row) {
            $issueMap[(int) $row['order_id'] . '|' . (int) $row['karigar_id']] = (string) ($row['first_issue_date'] ?? '');
        }

        $returnRows = db_connect()->table('gold_inventory_return_headers rh')
            ->select('rh.order_id, rh.karigar_id, SUM(rl.weight_gm) as delivered_gm, SUM(rl.fine_weight_gm) as delivered_fine_gm', false)
            ->join('gold_inventory_return_lines rl', 'rl.return_id = rh.id', 'inner')
            ->where('rh.order_id IS NOT NULL', null, false)
            ->where('rh.karigar_id IS NOT NULL', null, false)
            ->groupBy('rh.order_id, rh.karigar_id')
            ->get()
            ->getResultArray();
        $returnMap = [];
        foreach ($returnRows as $row) {
            $returnMap[(int) $row['order_id'] . '|' . (int) $row['karigar_id']] = [
                'gm' => (float) ($row['delivered_gm'] ?? 0),
                'fine' => (float) ($row['delivered_fine_gm'] ?? 0),
            ];
        }

        $grouped = [];
        foreach ($orders as $order) {
            $orderId = (int) ($order['id'] ?? 0);
            $karigarId = (int) ($order['assigned_karigar_id'] ?? 0);
            if ($orderId <= 0 || $karigarId <= 0) {
                continue;
            }

            $deliveredDate = substr((string) ($order['updated_at'] ?? ''), 0, 10);
            $issueDate = $issueMap[$orderId . '|' . $karigarId] ?? substr((string) ($order['created_at'] ?? ''), 0, 10);
            $days = 0;
            if ($issueDate !== '' && $deliveredDate !== '') {
                $days = max(0, (int) floor((strtotime($deliveredDate) - strtotime($issueDate)) / 86400));
            }

            $period = $filters['mode'] === 'custom'
                ? ($filters['from'] . ' to ' . $filters['to'])
                : date('Y-m', strtotime($deliveredDate !== '' ? $deliveredDate : (string) ($order['updated_at'] ?? 'now')));

            $ret = $returnMap[$orderId . '|' . $karigarId] ?? ['gm' => 0.0, 'fine' => 0.0];
            $key = $karigarId . '|' . $period;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'karigar_name' => (string) ($order['karigar_name'] ?? 'Unknown'),
                    'period' => $period,
                    'orders' => 0,
                    'total_days' => 0,
                    'delivered_gm' => 0.0,
                    'delivered_fine_gm' => 0.0,
                ];
            }

            $grouped[$key]['orders']++;
            $grouped[$key]['total_days'] += $days;
            $grouped[$key]['delivered_gm'] += (float) $ret['gm'];
            $grouped[$key]['delivered_fine_gm'] += (float) $ret['fine'];
        }

        $rows = [];
        foreach ($grouped as $entry) {
            $rows[] = [
                'karigar_name' => $entry['karigar_name'],
                'period' => $entry['period'],
                'orders' => $entry['orders'],
                'avg_days' => $entry['orders'] > 0 ? round($entry['total_days'] / $entry['orders'], 2) : 0.0,
                'delivered_gm' => round($entry['delivered_gm'], 3),
                'delivered_fine_gm' => round($entry['delivered_fine_gm'], 3),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $periodCompare = strcmp((string) ($b['period'] ?? ''), (string) ($a['period'] ?? ''));
            if ($periodCompare !== 0) {
                return $periodCompare;
            }
            return strcmp((string) ($a['karigar_name'] ?? ''), (string) ($b['karigar_name'] ?? ''));
        });

        $cards = [
            'orders' => 0,
            'delivered_gm' => 0.0,
            'delivered_fine_gm' => 0.0,
            'avg_days' => 0.0,
        ];
        $totalDays = 0.0;
        foreach ($rows as $row) {
            $cards['orders'] += (int) ($row['orders'] ?? 0);
            $cards['delivered_gm'] += (float) ($row['delivered_gm'] ?? 0);
            $cards['delivered_fine_gm'] += (float) ($row['delivered_fine_gm'] ?? 0);
            $totalDays += ((float) ($row['avg_days'] ?? 0) * (int) ($row['orders'] ?? 0));
        }
        $cards['avg_days'] = $cards['orders'] > 0 ? round($totalDays / $cards['orders'], 2) : 0.0;

        return view('admin/reports/karigar_performance', [
            'title' => 'Karigar Performance Report',
            'rows' => $rows,
            'cards' => $cards,
            'filters' => $filters,
            'karigars' => $this->karigarOptions(),
        ]);
    }

    public function inventory(): string
    {
        $filters = [
            'from' => trim((string) ($this->request->getGet('from') ?: date('Y-m-01'))),
            'to' => trim((string) ($this->request->getGet('to') ?: date('Y-m-d'))),
        ];

        $goldRows = db_connect()->table('gold_inventory_stock gs')
            ->select('gi.id as item_id, gi.purity_percent, gi.color_name, gi.form_type, gp.purity_code as master_purity_code, gi.purity_code, gs.weight_balance_gm, gs.fine_balance_gm, gs.avg_cost_per_gm, gs.stock_value')
            ->join('gold_inventory_items gi', 'gi.id = gs.item_id', 'inner')
            ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
            ->orderBy('gi.purity_percent', 'DESC')
            ->orderBy('gi.id', 'DESC')
            ->get()
            ->getResultArray();

        $diamondRows = db_connect()->table('stock ds')
            ->select('i.id as item_id, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, ds.pcs_balance, ds.carat_balance, ds.avg_cost_per_carat, ds.stock_value')
            ->join('items i', 'i.id = ds.item_id', 'inner')
            ->orderBy('i.diamond_type', 'ASC')
            ->orderBy('i.id', 'DESC')
            ->get()
            ->getResultArray();

        $cards = [
            'gold_weight' => 0.0,
            'gold_fine' => 0.0,
            'gold_value' => 0.0,
            'diamond_pcs' => 0.0,
            'diamond_cts' => 0.0,
            'diamond_value' => 0.0,
        ];
        foreach ($goldRows as $row) {
            $cards['gold_weight'] += (float) ($row['weight_balance_gm'] ?? 0);
            $cards['gold_fine'] += (float) ($row['fine_balance_gm'] ?? 0);
            $cards['gold_value'] += (float) ($row['stock_value'] ?? 0);
        }
        foreach ($diamondRows as $row) {
            $cards['diamond_pcs'] += (float) ($row['pcs_balance'] ?? 0);
            $cards['diamond_cts'] += (float) ($row['carat_balance'] ?? 0);
            $cards['diamond_value'] += (float) ($row['stock_value'] ?? 0);
        }

        $movement = [
            'gold_purchase' => $this->sumGoldMovement('gold_inventory_purchase_headers', 'purchase_date', 'gold_inventory_purchase_lines', 'purchase_id', $filters['from'], $filters['to']),
            'gold_issue' => $this->sumGoldMovement('gold_inventory_issue_headers', 'issue_date', 'gold_inventory_issue_lines', 'issue_id', $filters['from'], $filters['to']),
            'gold_return' => $this->sumGoldMovement('gold_inventory_return_headers', 'return_date', 'gold_inventory_return_lines', 'return_id', $filters['from'], $filters['to']),
            'diamond_purchase' => $this->sumDiamondMovement('purchase_headers', 'purchase_date', 'purchase_lines', 'purchase_id', $filters['from'], $filters['to']),
            'diamond_issue' => $this->sumDiamondMovement('issue_headers', 'issue_date', 'issue_lines', 'issue_id', $filters['from'], $filters['to']),
            'diamond_return' => $this->sumDiamondMovement('return_headers', 'return_date', 'return_lines', 'return_id', $filters['from'], $filters['to']),
        ];

        return view('admin/reports/inventory', [
            'title' => 'Inventory Report',
            'goldRows' => $goldRows,
            'diamondRows' => $diamondRows,
            'cards' => $cards,
            'movement' => $movement,
            'filters' => $filters,
        ]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function karigarOptions(): array
    {
        return (new KarigarModel())
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * @return list<string>
     */
    private function goldTxnTypes(): array
    {
        $rows = db_connect()->table('gold_inventory_ledger_entries')
            ->select('txn_type')
            ->distinct()
            ->orderBy('txn_type', 'ASC')
            ->get()
            ->getResultArray();

        $types = [];
        foreach ($rows as $row) {
            $type = trim((string) ($row['txn_type'] ?? ''));
            if ($type !== '') {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * @return array{gm:float,value:float}
     */
    private function sumGoldMovement(
        string $headerTable,
        string $dateField,
        string $lineTable,
        string $lineFk,
        string $from,
        string $to
    ): array {
        if (! db_connect()->tableExists($headerTable) || ! db_connect()->tableExists($lineTable)) {
            return ['gm' => 0.0, 'value' => 0.0];
        }

        $row = db_connect()->table($headerTable . ' h')
            ->select('COALESCE(SUM(l.weight_gm),0) as total_gm, COALESCE(SUM(l.line_value),0) as total_value', false)
            ->join($lineTable . ' l', 'l.' . $lineFk . ' = h.id', 'inner')
            ->where('h.' . $dateField . ' >=', $from)
            ->where('h.' . $dateField . ' <=', $to)
            ->get()
            ->getRowArray();

        return [
            'gm' => (float) ($row['total_gm'] ?? 0),
            'value' => (float) ($row['total_value'] ?? 0),
        ];
    }

    /**
     * @return array{pcs:float,cts:float,value:float}
     */
    private function sumDiamondMovement(
        string $headerTable,
        string $dateField,
        string $lineTable,
        string $lineFk,
        string $from,
        string $to
    ): array {
        if (! db_connect()->tableExists($headerTable) || ! db_connect()->tableExists($lineTable)) {
            return ['pcs' => 0.0, 'cts' => 0.0, 'value' => 0.0];
        }

        $row = db_connect()->table($headerTable . ' h')
            ->select('COALESCE(SUM(l.pcs),0) as total_pcs, COALESCE(SUM(l.carat),0) as total_cts, COALESCE(SUM(l.line_value),0) as total_value', false)
            ->join($lineTable . ' l', 'l.' . $lineFk . ' = h.id', 'inner')
            ->where('h.' . $dateField . ' >=', $from)
            ->where('h.' . $dateField . ' <=', $to)
            ->get()
            ->getRowArray();

        return [
            'pcs' => (float) ($row['total_pcs'] ?? 0),
            'cts' => (float) ($row['total_cts'] ?? 0),
            'value' => (float) ($row['total_value'] ?? 0),
        ];
    }
}
