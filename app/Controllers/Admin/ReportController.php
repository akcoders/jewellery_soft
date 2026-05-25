<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\KarigarModel;
use App\Models\DepartmentModel;
use App\Models\DesignationModel;

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

    public function ordersAnalysis(): string
    {
        $filters = [
            'from' => trim((string) ($this->request->getGet('from') ?? '')),
            'to' => trim((string) ($this->request->getGet('to') ?? '')),
            'order_type' => trim((string) ($this->request->getGet('order_type') ?? '')),
            'status' => trim((string) ($this->request->getGet('status') ?? '')),
            'karigar_id' => (int) ($this->request->getGet('karigar_id') ?? 0),
            'customer_id' => (int) ($this->request->getGet('customer_id') ?? 0),
            'priority' => trim((string) ($this->request->getGet('priority') ?? '')),
        ];

        $builder = db_connect()->table('orders o')
            ->select('o.*, c.name as customer_name, k.name as karigar_name, COALESCE(SUM(oi.qty),0) as total_qty, COALESCE(SUM(oi.gold_required_gm),0) as gold_budget_gm, COALESCE(SUM(oi.diamond_required_cts),0) as diamond_budget_cts', false)
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left')
            ->join('order_items oi', 'oi.order_id = o.id', 'left')
            ->groupBy('o.id')
            ->orderBy('o.id', 'DESC');

        if ($filters['from'] !== '') {
            $builder->where('DATE(o.created_at) >=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $builder->where('DATE(o.created_at) <=', $filters['to']);
        }
        if ($filters['order_type'] !== '') {
            $builder->where('o.order_type', $filters['order_type']);
        }
        if ($filters['status'] !== '') {
            $builder->where('o.status', $filters['status']);
        }
        if ($filters['karigar_id'] > 0) {
            $builder->where('o.assigned_karigar_id', $filters['karigar_id']);
        }
        if ($filters['customer_id'] > 0) {
            $builder->where('o.customer_id', $filters['customer_id']);
        }
        if ($filters['priority'] !== '') {
            $builder->where('o.priority', $filters['priority']);
        }

        $rows = $builder->get()->getResultArray();

        $cards = [
            'total_orders' => count($rows),
            'completed_orders' => 0,
            'active_orders' => 0,
            'delayed_orders' => 0,
            'repair_orders' => 0,
            'gold_budget_gm' => 0.0,
            'diamond_budget_cts' => 0.0,
        ];
        $statusCounts = [];
        $priorityCounts = [];
        $typeCounts = [];
        $karigarCounts = [];
        $monthly = [];
        $today = strtotime(date('Y-m-d'));

        foreach ($rows as &$row) {
            $status = (string) ($row['status'] ?? '');
            $priority = (string) ($row['priority'] ?? '');
            $type = (string) ($row['order_type'] ?? '');
            $karigarName = trim((string) ($row['karigar_name'] ?? 'Unassigned'));
            $createdDate = substr((string) ($row['created_at'] ?? ''), 0, 10);
            $monthKey = $createdDate !== '' ? date('Y-m', strtotime($createdDate)) : '-';
            $dueDate = trim((string) ($row['due_date'] ?? ''));
            $isCompleted = in_array($status, ['Completed', 'Delivered', 'Dispatched'], true);
            $isDelayed = false;
            if ($dueDate !== '' && ! $isCompleted) {
                $dueTs = strtotime($dueDate);
                $isDelayed = $dueTs !== false && $dueTs < $today;
            }

            $row['is_delayed'] = $isDelayed;
            $row['delay_days'] = $isDelayed ? max(0, (int) floor(($today - strtotime($dueDate)) / 86400)) : 0;

            if ($isCompleted) {
                $cards['completed_orders']++;
            } else {
                $cards['active_orders']++;
            }
            if ($isDelayed) {
                $cards['delayed_orders']++;
            }
            if (strcasecmp($type, 'Repair') === 0) {
                $cards['repair_orders']++;
            }

            $cards['gold_budget_gm'] += (float) ($row['gold_budget_gm'] ?? 0);
            $cards['diamond_budget_cts'] += (float) ($row['diamond_budget_cts'] ?? 0);

            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $priorityCounts[$priority] = ($priorityCounts[$priority] ?? 0) + 1;
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            $karigarCounts[$karigarName] = ($karigarCounts[$karigarName] ?? 0) + 1;
            $monthly[$monthKey] = ($monthly[$monthKey] ?? 0) + 1;
        }
        unset($row);

        arsort($karigarCounts);
        ksort($monthly);

        $analysis = [];
        $analysis[] = $cards['total_orders'] > 0
            ? sprintf('%d orders matched the selected filters, with %d still active and %d completed.', $cards['total_orders'], $cards['active_orders'], $cards['completed_orders'])
            : 'No orders matched the selected filters.';
        if ($cards['delayed_orders'] > 0) {
            $analysis[] = sprintf('%d orders are past due and need follow-up.', $cards['delayed_orders']);
        }
        if ($statusCounts !== []) {
            arsort($statusCounts);
            $analysis[] = 'Highest order concentration is in status ' . (string) array_key_first($statusCounts) . '.';
        }
        if ($priorityCounts !== []) {
            arsort($priorityCounts);
            $analysis[] = 'Most common priority is ' . (string) array_key_first($priorityCounts) . '.';
        }

        return view('admin/reports/orders_analysis', [
            'title' => 'All Orders Report',
            'rows' => $rows,
            'cards' => $cards,
            'filters' => $filters,
            'statusCounts' => $statusCounts,
            'priorityCounts' => $priorityCounts,
            'typeCounts' => $typeCounts,
            'karigarCounts' => array_slice($karigarCounts, 0, 8, true),
            'monthlyCounts' => $monthly,
            'analysis' => $analysis,
            'karigars' => $this->karigarOptions(),
            'customers' => $this->customerOptions(),
            'orderTypes' => $this->orderTypeOptions(),
            'orderStatuses' => $this->orderStatusOptions(),
            'priorities' => $this->orderPriorityOptions(),
        ]);
    }

    public function transactions(): string
    {
        $filters = [
            'from' => trim((string) ($this->request->getGet('from') ?? date('Y-m-01'))),
            'to' => trim((string) ($this->request->getGet('to') ?? date('Y-m-d'))),
            'transaction_type' => trim((string) ($this->request->getGet('transaction_type') ?? '')),
            'material_type' => trim((string) ($this->request->getGet('material_type') ?? '')),
            'karigar_id' => (int) ($this->request->getGet('karigar_id') ?? 0),
            'customer_id' => (int) ($this->request->getGet('customer_id') ?? 0),
            'vendor_id' => (int) ($this->request->getGet('vendor_id') ?? 0),
            'order_no' => trim((string) ($this->request->getGet('order_no') ?? '')),
            'status' => trim((string) ($this->request->getGet('status') ?? '')),
            'search' => trim((string) ($this->request->getGet('search') ?? '')),
        ];

        $rows = array_merge(
            $this->transactionRowsOrders($filters),
            $this->transactionRowsOrderStatus($filters),
            $this->transactionRowsDiamond($filters),
            $this->transactionRowsGold($filters),
            $this->transactionRowsStone($filters),
            $this->transactionRowsOrderMovements($filters),
            $this->transactionRowsShowroom($filters),
            $this->transactionRowsAccountPayments($filters)
        );

        if ($filters['transaction_type'] !== '') {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => (string) ($row['transaction_type'] ?? '') === $filters['transaction_type']));
        }
        if ($filters['material_type'] !== '') {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => (string) ($row['material_type'] ?? '') === $filters['material_type']));
        }
        if ($filters['karigar_id'] > 0) {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => (int) ($row['karigar_id'] ?? 0) === $filters['karigar_id']));
        }
        if ($filters['customer_id'] > 0) {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => (int) ($row['customer_id'] ?? 0) === $filters['customer_id']));
        }
        if ($filters['vendor_id'] > 0) {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => (int) ($row['vendor_id'] ?? 0) === $filters['vendor_id']));
        }
        if ($filters['order_no'] !== '') {
            $needle = strtolower($filters['order_no']);
            $rows = array_values(array_filter($rows, static fn(array $row): bool => strpos(strtolower((string) ($row['order_no'] ?? '')), $needle) !== false));
        }
        if ($filters['status'] !== '') {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => (string) ($row['status'] ?? '') === $filters['status']));
        }
        if ($filters['search'] !== '') {
            $needle = strtolower($filters['search']);
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                $haystack = strtolower(implode(' | ', [
                    (string) ($row['reference_no'] ?? ''),
                    (string) ($row['order_no'] ?? ''),
                    (string) ($row['transaction_type'] ?? ''),
                    (string) ($row['material_type'] ?? ''),
                    (string) ($row['party_name'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    (string) ($row['notes'] ?? ''),
                ]));
                return strpos($haystack, $needle) !== false;
            }));
        }

        usort($rows, static function (array $a, array $b): int {
            $dateCompare = strcmp((string) ($b['transaction_date'] ?? ''), (string) ($a['transaction_date'] ?? ''));
            if ($dateCompare !== 0) {
                return $dateCompare;
            }
            return strcmp((string) ($b['reference_no'] ?? ''), (string) ($a['reference_no'] ?? ''));
        });

        $cards = [
            'row_count' => count($rows),
            'amount_total' => 0.0,
            'gold_total' => 0.0,
            'diamond_total' => 0.0,
            'stone_total' => 0.0,
        ];
        $typeCounts = [];
        foreach ($rows as $row) {
            $cards['amount_total'] += (float) ($row['amount'] ?? 0);
            $cards['gold_total'] += (float) ($row['gold_gm'] ?? 0);
            $cards['diamond_total'] += (float) ($row['diamond_cts'] ?? 0);
            $cards['stone_total'] += (float) ($row['stone_qty'] ?? 0);
            $type = (string) ($row['transaction_type'] ?? 'Unknown');
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
        }

        return view('admin/reports/transactions', [
            'title' => 'Combined Transaction Report',
            'rows' => $rows,
            'cards' => $cards,
            'filters' => $filters,
            'typeCounts' => $typeCounts,
            'karigars' => $this->karigarOptions(),
            'customers' => $this->customerOptions(),
            'vendors' => $this->vendorOptions(),
            'transactionTypes' => $this->transactionTypeOptions(),
            'materialTypes' => ['Gold', 'Diamond', 'Stone', 'FG', 'Order', 'Accounts'],
            'statuses' => $this->orderStatusOptions(),
        ]);
    }

    public function staffDirectory(): string
    {
        $filters = [
            'department_id' => (int) ($this->request->getGet('department_id') ?? 0),
            'designation_id' => (int) ($this->request->getGet('designation_id') ?? 0),
            'status' => trim((string) ($this->request->getGet('status') ?? 'active')),
            'location' => trim((string) ($this->request->getGet('location') ?? '')),
        ];

        $builder = db_connect()->table('employees e')
            ->select('e.employee_code, e.full_name, e.mobile, e.email, e.work_location, e.joining_date, e.is_active, dep.name as department_name, des.name as designation_name, rm.full_name as reporting_manager_name')
            ->join('departments dep', 'dep.id = e.department_id', 'left')
            ->join('designations des', 'des.id = e.designation_id', 'left')
            ->join('employee_hierarchies eh', 'eh.employee_id = e.id AND eh.is_active = 1', 'left', false)
            ->join('employees rm', 'rm.id = eh.reporting_manager_id', 'left')
            ->orderBy('e.full_name', 'ASC');

        if ($filters['department_id'] > 0) {
            $builder->where('e.department_id', $filters['department_id']);
        }
        if ($filters['designation_id'] > 0) {
            $builder->where('e.designation_id', $filters['designation_id']);
        }
        if ($filters['status'] === 'active') {
            $builder->where('e.is_active', 1);
        } elseif ($filters['status'] === 'inactive') {
            $builder->where('e.is_active', 0);
        }
        if ($filters['location'] !== '') {
            $builder->like('e.work_location', $filters['location']);
        }

        $rows = $builder->get()->getResultArray();

        $cards = [
            'total' => count($rows),
            'active' => 0,
            'inactive' => 0,
            'locations' => [],
        ];
        foreach ($rows as $row) {
            if ((int) ($row['is_active'] ?? 0) === 1) {
                $cards['active']++;
            } else {
                $cards['inactive']++;
            }
            $location = trim((string) ($row['work_location'] ?? ''));
            if ($location !== '') {
                $cards['locations'][$location] = true;
            }
        }
        $cards['locations'] = count($cards['locations']);

        return view('admin/reports/staff_directory', [
            'title' => 'Staff Directory',
            'rows' => $rows,
            'cards' => $cards,
            'filters' => $filters,
            'departments' => $this->departmentOptions(),
            'designations' => $this->designationOptions(),
        ]);
    }

    public function departmentStaff(): string
    {
        $filters = [
            'status' => trim((string) ($this->request->getGet('status') ?? 'active')),
        ];

        $builder = db_connect()->table('departments d')
            ->select("
                d.department_code,
                d.name,
                d.is_active,
                COUNT(e.id) as total_staff,
                SUM(CASE WHEN e.is_active = 1 THEN 1 ELSE 0 END) as active_staff,
                SUM(CASE WHEN e.is_active = 0 THEN 1 ELSE 0 END) as inactive_staff,
                SUM(CASE WHEN des.can_manage_team = 1 THEN 1 ELSE 0 END) as managers
            ", false)
            ->join('employees e', 'e.department_id = d.id', 'left')
            ->join('designations des', 'des.id = e.designation_id', 'left')
            ->groupBy('d.id')
            ->orderBy('d.sort_order', 'ASC')
            ->orderBy('d.name', 'ASC');

        if ($filters['status'] === 'active') {
            $builder->where('d.is_active', 1);
        } elseif ($filters['status'] === 'inactive') {
            $builder->where('d.is_active', 0);
        }

        $rows = $builder->get()->getResultArray();

        $cards = [
            'departments' => count($rows),
            'total_staff' => 0,
            'active_staff' => 0,
            'managers' => 0,
        ];
        foreach ($rows as $row) {
            $cards['total_staff'] += (int) ($row['total_staff'] ?? 0);
            $cards['active_staff'] += (int) ($row['active_staff'] ?? 0);
            $cards['managers'] += (int) ($row['managers'] ?? 0);
        }

        return view('admin/reports/department_staff', [
            'title' => 'Department Staff Report',
            'rows' => $rows,
            'cards' => $cards,
            'filters' => $filters,
        ]);
    }

    public function designationStaff(): string
    {
        $filters = [
            'department_id' => (int) ($this->request->getGet('department_id') ?? 0),
            'status' => trim((string) ($this->request->getGet('status') ?? 'active')),
        ];

        $builder = db_connect()->table('designations des')
            ->select("
                des.designation_code,
                des.name,
                des.level_no,
                des.can_manage_team,
                des.is_active,
                dep.name as department_name,
                rpt.name as reports_to_designation_name,
                COUNT(e.id) as total_staff,
                SUM(CASE WHEN e.is_active = 1 THEN 1 ELSE 0 END) as active_staff
            ", false)
            ->join('departments dep', 'dep.id = des.department_id', 'left')
            ->join('designations rpt', 'rpt.id = des.reports_to_designation_id', 'left')
            ->join('employees e', 'e.designation_id = des.id', 'left')
            ->groupBy('des.id')
            ->orderBy('dep.name', 'ASC')
            ->orderBy('des.level_no', 'ASC')
            ->orderBy('des.name', 'ASC');

        if ($filters['department_id'] > 0) {
            $builder->where('des.department_id', $filters['department_id']);
        }
        if ($filters['status'] === 'active') {
            $builder->where('des.is_active', 1);
        } elseif ($filters['status'] === 'inactive') {
            $builder->where('des.is_active', 0);
        }

        $rows = $builder->get()->getResultArray();

        $cards = [
            'designations' => count($rows),
            'team_designations' => 0,
            'total_staff' => 0,
            'active_staff' => 0,
        ];
        foreach ($rows as $row) {
            $cards['total_staff'] += (int) ($row['total_staff'] ?? 0);
            $cards['active_staff'] += (int) ($row['active_staff'] ?? 0);
            if ((int) ($row['can_manage_team'] ?? 0) === 1) {
                $cards['team_designations']++;
            }
        }

        return view('admin/reports/designation_staff', [
            'title' => 'Designation Staff Report',
            'rows' => $rows,
            'cards' => $cards,
            'filters' => $filters,
            'departments' => $this->departmentOptions(),
        ]);
    }

    public function staffHierarchy(): string
    {
        $filters = [
            'department_id' => (int) ($this->request->getGet('department_id') ?? 0),
            'manager_id' => (int) ($this->request->getGet('manager_id') ?? 0),
        ];

        $builder = db_connect()->table('employees m')
            ->select("
                m.id,
                m.employee_code,
                m.full_name,
                m.work_location,
                dep.name as department_name,
                des.name as designation_name,
                COUNT(DISTINCT rep.employee_id) as direct_reports,
                COUNT(DISTINCT obs.employee_id) as observing_reports,
                COUNT(DISTINCT rev.employee_id) as reviewing_reports,
                COUNT(DISTINCT app.employee_id) as approving_reports
            ", false)
            ->join('departments dep', 'dep.id = m.department_id', 'left')
            ->join('designations des', 'des.id = m.designation_id', 'left')
            ->join('employee_hierarchies rep', 'rep.reporting_manager_id = m.id AND rep.is_active = 1', 'left', false)
            ->join('employee_hierarchies obs', 'obs.observing_manager_id = m.id AND obs.is_active = 1', 'left', false)
            ->join('employee_hierarchies rev', 'rev.reviewing_manager_id = m.id AND rev.is_active = 1', 'left', false)
            ->join('employee_hierarchies app', 'app.approving_manager_id = m.id AND app.is_active = 1', 'left', false)
            ->where('m.is_active', 1)
            ->groupBy('m.id')
            ->having('(COUNT(DISTINCT rep.employee_id) + COUNT(DISTINCT obs.employee_id) + COUNT(DISTINCT rev.employee_id) + COUNT(DISTINCT app.employee_id)) >', 0, false)
            ->orderBy('direct_reports', 'DESC')
            ->orderBy('m.full_name', 'ASC');

        if ($filters['department_id'] > 0) {
            $builder->where('m.department_id', $filters['department_id']);
        }
        if ($filters['manager_id'] > 0) {
            $builder->where('m.id', $filters['manager_id']);
        }

        $rows = $builder->get()->getResultArray();

        foreach ($rows as &$row) {
            $managerId = (int) ($row['id'] ?? 0);
            $row['team_members'] = $managerId > 0 ? $this->managerTeamMembers($managerId) : [];
        }
        unset($row);

        $cards = [
            'managers' => count($rows),
            'direct_reports' => 0,
            'observing_reports' => 0,
            'reviewing_reports' => 0,
            'approving_reports' => 0,
        ];
        foreach ($rows as $row) {
            $cards['direct_reports'] += (int) ($row['direct_reports'] ?? 0);
            $cards['observing_reports'] += (int) ($row['observing_reports'] ?? 0);
            $cards['reviewing_reports'] += (int) ($row['reviewing_reports'] ?? 0);
            $cards['approving_reports'] += (int) ($row['approving_reports'] ?? 0);
        }

        return view('admin/reports/staff_hierarchy', [
            'title' => 'Staff Hierarchy Report',
            'rows' => $rows,
            'cards' => $cards,
            'filters' => $filters,
            'departments' => $this->departmentOptions(),
            'employees' => $this->employeeOptions(),
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

    private function departmentOptions(): array
    {
        return (new DepartmentModel())
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    private function designationOptions(): array
    {
        return (new DesignationModel())
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    private function employeeOptions(): array
    {
        return db_connect()->table('employees e')
            ->select('e.id, e.full_name, e.employee_code, des.name as designation_name')
            ->join('designations des', 'des.id = e.designation_id', 'left')
            ->where('e.is_active', 1)
            ->orderBy('e.full_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function managerTeamMembers(int $managerId): array
    {
        return db_connect()->table('employee_hierarchies eh')
            ->select('e.employee_code, e.full_name, dep.name as department_name, des.name as designation_name')
            ->join('employees e', 'e.id = eh.employee_id', 'inner')
            ->join('departments dep', 'dep.id = e.department_id', 'left')
            ->join('designations des', 'des.id = e.designation_id', 'left')
            ->where('eh.reporting_manager_id', $managerId)
            ->where('eh.is_active', 1)
            ->orderBy('e.full_name', 'ASC')
            ->get()
            ->getResultArray();
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

    private function customerOptions(): array
    {
        if (! db_connect()->tableExists('customers')) {
            return [];
        }

        return db_connect()->table('customers')
            ->select('id, name')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function vendorOptions(): array
    {
        if (! db_connect()->tableExists('vendors')) {
            return [];
        }

        return db_connect()->table('vendors')
            ->select('id, name')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<string>
     */
    private function orderTypeOptions(): array
    {
        return ['Fresh', 'Repair'];
    }

    /**
     * @return list<string>
     */
    private function orderStatusOptions(): array
    {
        return array_values((array) (config(\Config\Jewellery::class)->orderStatuses ?? []));
    }

    /**
     * @return list<string>
     */
    private function orderPriorityOptions(): array
    {
        return array_values((array) (config(\Config\Jewellery::class)->orderPriorities ?? []));
    }

    /**
     * @return list<string>
     */
    private function transactionTypeOptions(): array
    {
        return [
            'Order Created',
            'Order Status',
            'Diamond Purchase',
            'Diamond Issue',
            'Diamond Return',
            'Gold Purchase',
            'Gold Issue',
            'Gold Return',
            'Gold Adjustment',
            'Stone Purchase',
            'Stone Issue',
            'Stone Return',
            'Stone Adjustment',
            'Order Issue',
            'Order Receive',
            'Showroom Sale',
            'Showroom Movement',
            'Customer Receipt',
            'Purchase Payment',
            'Labour Bill',
            'Labour Payment',
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function transactionRowsOrders(array $filters): array
    {
        $builder = db_connect()->table('orders o')
            ->select("DATE(o.created_at) as transaction_date, 'Order Created' as transaction_type, 'Order' as material_type, o.order_no, CONCAT('ORDER#', o.id) as reference_no, c.name as party_name, o.status, o.customer_id, o.assigned_karigar_id as karigar_id, NULL as vendor_id, 0 as gold_gm, 0 as diamond_cts, 0 as stone_qty, 0 as amount, o.order_notes as notes", false)
            ->join('customers c', 'c.id = o.customer_id', 'left');

        return $this->fetchRowsByDate($builder, 'DATE(o.created_at)', $filters);
    }

    private function transactionRowsOrderStatus(array $filters): array
    {
        if (! db_connect()->tableExists('order_status_histories')) {
            return [];
        }

        $builder = db_connect()->table('order_status_histories h')
            ->select("DATE(h.created_at) as transaction_date, 'Order Status' as transaction_type, 'Order' as material_type, o.order_no, CONCAT('STATUS#', h.id) as reference_no, c.name as party_name, h.to_status as status, o.customer_id, o.assigned_karigar_id as karigar_id, NULL as vendor_id, 0 as gold_gm, 0 as diamond_cts, 0 as stone_qty, 0 as amount, CONCAT(COALESCE(h.from_status,'-'), ' -> ', COALESCE(h.to_status,'-'), ' | ', COALESCE(h.remarks,'')) as notes", false)
            ->join('orders o', 'o.id = h.order_id', 'inner')
            ->join('customers c', 'c.id = o.customer_id', 'left');

        return $this->fetchRowsByDate($builder, 'DATE(h.created_at)', $filters);
    }

    private function transactionRowsDiamond(array $filters): array
    {
        $rows = [];
        $db = db_connect();
        if ($db->tableExists('purchase_headers') && $db->tableExists('purchase_lines')) {
            $builder = $db->table('purchase_headers ph')
                ->select("ph.purchase_date as transaction_date, 'Diamond Purchase' as transaction_type, 'Diamond' as material_type, NULL as order_no, COALESCE(NULLIF(ph.invoice_no,''), CONCAT('DPUR#', ph.id)) as reference_no, COALESCE(v.name, ph.supplier_name, '-') as party_name, 'Posted' as status, NULL as customer_id, NULL as karigar_id, ph.vendor_id, 0 as gold_gm, COALESCE(SUM(pl.carat),0) as diamond_cts, 0 as stone_qty, COALESCE(MAX(ph.invoice_total), SUM(pl.line_value), 0) as amount, ph.notes as notes", false)
                ->join('purchase_lines pl', 'pl.purchase_id = ph.id', 'inner')
                ->join('vendors v', 'v.id = ph.vendor_id', 'left')
                ->groupBy('ph.id');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'ph.purchase_date', $filters));
        }
        if ($db->tableExists('issue_headers') && $db->tableExists('issue_lines')) {
            $builder = $db->table('issue_headers ih')
                ->select("ih.issue_date as transaction_date, 'Diamond Issue' as transaction_type, 'Diamond' as material_type, o.order_no, COALESCE(NULLIF(ih.voucher_no,''), CONCAT('DI#', ih.id)) as reference_no, COALESCE(k.name, ih.issue_to, '-') as party_name, 'Issued' as status, o.customer_id, ih.karigar_id, NULL as vendor_id, 0 as gold_gm, COALESCE(SUM(il.carat),0) as diamond_cts, 0 as stone_qty, COALESCE(SUM(il.line_value),0) as amount, ih.notes as notes", false)
                ->join('issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->join('orders o', 'o.id = ih.order_id', 'left')
                ->join('karigars k', 'k.id = ih.karigar_id', 'left')
                ->groupBy('ih.id');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'ih.issue_date', $filters));
        }
        if ($db->tableExists('return_headers') && $db->tableExists('return_lines')) {
            $builder = $db->table('return_headers rh')
                ->select("rh.return_date as transaction_date, 'Diamond Return' as transaction_type, 'Diamond' as material_type, o.order_no, COALESCE(NULLIF(rh.voucher_no,''), CONCAT('DR#', rh.id)) as reference_no, COALESCE(k.name, rh.return_from, '-') as party_name, 'Returned' as status, o.customer_id, rh.karigar_id, NULL as vendor_id, 0 as gold_gm, COALESCE(SUM(rl.carat),0) as diamond_cts, 0 as stone_qty, COALESCE(SUM(rl.line_value),0) as amount, rh.notes as notes", false)
                ->join('return_lines rl', 'rl.return_id = rh.id', 'inner')
                ->join('orders o', 'o.id = rh.order_id', 'left')
                ->join('karigars k', 'k.id = rh.karigar_id', 'left')
                ->groupBy('rh.id');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'rh.return_date', $filters));
        }
        return $rows;
    }

    private function transactionRowsGold(array $filters): array
    {
        $rows = [];
        $db = db_connect();
        if ($db->tableExists('gold_inventory_purchase_headers') && $db->tableExists('gold_inventory_purchase_lines')) {
            $builder = $db->table('gold_inventory_purchase_headers ph')
                ->select("ph.purchase_date as transaction_date, 'Gold Purchase' as transaction_type, 'Gold' as material_type, NULL as order_no, COALESCE(NULLIF(ph.invoice_no,''), CONCAT('GPUR#', ph.id)) as reference_no, COALESCE(ph.supplier_name, '-') as party_name, 'Posted' as status, NULL as customer_id, NULL as karigar_id, NULL as vendor_id, COALESCE(SUM(pl.weight_gm),0) as gold_gm, 0 as diamond_cts, 0 as stone_qty, COALESCE(SUM(pl.line_value),0) as amount, ph.notes as notes", false)
                ->join('gold_inventory_purchase_lines pl', 'pl.purchase_id = ph.id', 'inner')
                ->groupBy('ph.id');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'ph.purchase_date', $filters));
        }
        if ($db->tableExists('gold_inventory_issue_headers') && $db->tableExists('gold_inventory_issue_lines')) {
            $builder = $db->table('gold_inventory_issue_headers ih')
                ->select("ih.issue_date as transaction_date, 'Gold Issue' as transaction_type, 'Gold' as material_type, o.order_no, COALESCE(NULLIF(ih.voucher_no,''), CONCAT('GI#', ih.id)) as reference_no, COALESCE(k.name, ih.issue_to, '-') as party_name, 'Issued' as status, o.customer_id, ih.karigar_id, NULL as vendor_id, COALESCE(SUM(il.weight_gm),0) as gold_gm, 0 as diamond_cts, 0 as stone_qty, COALESCE(SUM(il.line_value),0) as amount, ih.notes as notes", false)
                ->join('gold_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->join('orders o', 'o.id = ih.order_id', 'left')
                ->join('karigars k', 'k.id = ih.karigar_id', 'left')
                ->groupBy('ih.id');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'ih.issue_date', $filters));
        }
        if ($db->tableExists('gold_inventory_return_headers') && $db->tableExists('gold_inventory_return_lines')) {
            $builder = $db->table('gold_inventory_return_headers rh')
                ->select("rh.return_date as transaction_date, 'Gold Return' as transaction_type, 'Gold' as material_type, o.order_no, COALESCE(NULLIF(rh.voucher_no,''), CONCAT('GR#', rh.id)) as reference_no, COALESCE(k.name, rh.return_from, '-') as party_name, 'Returned' as status, o.customer_id, rh.karigar_id, NULL as vendor_id, COALESCE(SUM(rl.weight_gm),0) as gold_gm, 0 as diamond_cts, 0 as stone_qty, COALESCE(SUM(rl.line_value),0) as amount, rh.notes as notes", false)
                ->join('gold_inventory_return_lines rl', 'rl.return_id = rh.id', 'inner')
                ->join('orders o', 'o.id = rh.order_id', 'left')
                ->join('karigars k', 'k.id = rh.karigar_id', 'left')
                ->groupBy('rh.id');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'rh.return_date', $filters));
        }
        if ($db->tableExists('gold_inventory_adjustment_headers') && $db->tableExists('gold_inventory_adjustment_lines')) {
            $builder = $db->table('gold_inventory_adjustment_headers ah')
                ->select("ah.adjustment_date as transaction_date, 'Gold Adjustment' as transaction_type, 'Gold' as material_type, NULL as order_no, CONCAT('GADJ#', ah.id) as reference_no, '-' as party_name, ah.adjustment_type as status, NULL as customer_id, NULL as karigar_id, NULL as vendor_id, COALESCE(SUM(al.weight_gm),0) as gold_gm, 0 as diamond_cts, 0 as stone_qty, COALESCE(SUM(al.line_value),0) as amount, ah.notes as notes", false)
                ->join('gold_inventory_adjustment_lines al', 'al.adjustment_id = ah.id', 'inner')
                ->groupBy('ah.id');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'ah.adjustment_date', $filters));
        }
        return $rows;
    }

    private function transactionRowsStone(array $filters): array
    {
        $rows = [];
        $db = db_connect();
        if ($db->tableExists('stone_inventory_purchase_headers') && $db->tableExists('stone_inventory_purchase_lines')) {
            $builder = $db->table('stone_inventory_purchase_headers ph')
                ->select("ph.purchase_date as transaction_date, 'Stone Purchase' as transaction_type, 'Stone' as material_type, NULL as order_no, COALESCE(NULLIF(ph.invoice_no,''), CONCAT('SPUR#', ph.id)) as reference_no, COALESCE(v.name, ph.supplier_name, '-') as party_name, 'Posted' as status, NULL as customer_id, NULL as karigar_id, ph.vendor_id, 0 as gold_gm, 0 as diamond_cts, COALESCE(SUM(pl.qty),0) as stone_qty, COALESCE(MAX(ph.invoice_total), SUM(pl.line_value), 0) as amount, ph.notes as notes", false)
                ->join('stone_inventory_purchase_lines pl', 'pl.purchase_id = ph.id', 'inner')
                ->join('vendors v', 'v.id = ph.vendor_id', 'left')
                ->groupBy('ph.id');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'ph.purchase_date', $filters));
        }
        if ($db->tableExists('stone_inventory_issue_headers') && $db->tableExists('stone_inventory_issue_lines')) {
            $builder = $db->table('stone_inventory_issue_headers ih')
                ->select("ih.issue_date as transaction_date, 'Stone Issue' as transaction_type, 'Stone' as material_type, o.order_no, COALESCE(NULLIF(ih.voucher_no,''), CONCAT('SI#', ih.id)) as reference_no, COALESCE(k.name, ih.issue_to, '-') as party_name, 'Issued' as status, o.customer_id, ih.karigar_id, NULL as vendor_id, 0 as gold_gm, 0 as diamond_cts, COALESCE(SUM(il.qty),0) as stone_qty, COALESCE(SUM(il.line_value),0) as amount, ih.notes as notes", false)
                ->join('stone_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->join('orders o', 'o.id = ih.order_id', 'left')
                ->join('karigars k', 'k.id = ih.karigar_id', 'left')
                ->groupBy('ih.id');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'ih.issue_date', $filters));
        }
        if ($db->tableExists('stone_inventory_return_headers') && $db->tableExists('stone_inventory_return_lines')) {
            $builder = $db->table('stone_inventory_return_headers rh')
                ->select("rh.return_date as transaction_date, 'Stone Return' as transaction_type, 'Stone' as material_type, o.order_no, COALESCE(NULLIF(rh.voucher_no,''), CONCAT('SR#', rh.id)) as reference_no, COALESCE(k.name, rh.return_from, '-') as party_name, 'Returned' as status, o.customer_id, rh.karigar_id, NULL as vendor_id, 0 as gold_gm, 0 as diamond_cts, COALESCE(SUM(rl.qty),0) as stone_qty, COALESCE(SUM(rl.line_value),0) as amount, rh.notes as notes", false)
                ->join('stone_inventory_return_lines rl', 'rl.return_id = rh.id', 'inner')
                ->join('orders o', 'o.id = rh.order_id', 'left')
                ->join('karigars k', 'k.id = rh.karigar_id', 'left')
                ->groupBy('rh.id');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'rh.return_date', $filters));
        }
        if ($db->tableExists('stone_inventory_adjustment_headers') && $db->tableExists('stone_inventory_adjustment_lines')) {
            $builder = $db->table('stone_inventory_adjustment_headers ah')
                ->select("ah.adjustment_date as transaction_date, 'Stone Adjustment' as transaction_type, 'Stone' as material_type, NULL as order_no, CONCAT('SADJ#', ah.id) as reference_no, '-' as party_name, ah.adjustment_type as status, NULL as customer_id, NULL as karigar_id, NULL as vendor_id, 0 as gold_gm, 0 as diamond_cts, COALESCE(SUM(al.qty),0) as stone_qty, COALESCE(SUM(al.line_value),0) as amount, ah.notes as notes", false)
                ->join('stone_inventory_adjustment_lines al', 'al.adjustment_id = ah.id', 'inner')
                ->groupBy('ah.id');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'ah.adjustment_date', $filters));
        }
        return $rows;
    }

    private function transactionRowsOrderMovements(array $filters): array
    {
        if (! db_connect()->tableExists('order_material_movements')) {
            return [];
        }

        $builder = db_connect()->table('order_material_movements om')
            ->select("DATE(om.created_at) as transaction_date, CASE WHEN om.movement_type='receive' THEN 'Order Receive' ELSE 'Order Issue' END as transaction_type, 'Order' as material_type, o.order_no, CONCAT('OM#', om.id) as reference_no, COALESCE(k.name, c.name, '-') as party_name, o.status, o.customer_id, om.karigar_id, NULL as vendor_id, COALESCE(om.gold_gm,0) as gold_gm, COALESCE(om.diamond_cts,0) as diamond_cts, 0 as stone_qty, 0 as amount, om.notes as notes", false)
            ->join('orders o', 'o.id = om.order_id', 'left')
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->join('karigars k', 'k.id = om.karigar_id', 'left');

        return $this->fetchRowsByDate($builder, 'DATE(om.created_at)', $filters);
    }

    private function transactionRowsShowroom(array $filters): array
    {
        $rows = [];
        $db = db_connect();
        if ($db->tableExists('showroom_sales')) {
            $builder = $db->table('showroom_sales s')
                ->select("s.sale_date as transaction_date, 'Showroom Sale' as transaction_type, 'FG' as material_type, o.order_no, COALESCE(NULLIF(s.sale_no,''), CONCAT('SALE#', s.id)) as reference_no, COALESCE(c.name, '-') as party_name, s.payment_status as status, s.customer_id, NULL as karigar_id, NULL as vendor_id, 0 as gold_gm, 0 as diamond_cts, COALESCE(s.total_qty,0) as stone_qty, COALESCE(s.total_amount,0) as amount, s.notes as notes", false)
                ->join('customers c', 'c.id = s.customer_id', 'left')
                ->join('showroom_sale_items ssi', 'ssi.showroom_sale_id = s.id', 'left')
                ->join('fg_items fg', 'fg.id = ssi.fg_item_id', 'left')
                ->join('orders o', 'o.id = fg.order_id', 'left')
                ->groupBy('s.id');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 's.sale_date', $filters));
        }
        if ($db->tableExists('showroom_fg_movements')) {
            $builder = $db->table('showroom_fg_movements m')
                ->select("DATE(m.created_at) as transaction_date, 'Showroom Movement' as transaction_type, 'FG' as material_type, o.order_no, CONCAT('MOVE#', m.id) as reference_no, COALESCE(sh.name, '-') as party_name, m.movement_type as status, NULL as customer_id, NULL as karigar_id, NULL as vendor_id, 0 as gold_gm, 0 as diamond_cts, 1 as stone_qty, 0 as amount, m.remarks as notes", false)
                ->join('fg_items fg', 'fg.id = m.fg_item_id', 'left')
                ->join('orders o', 'o.id = fg.order_id', 'left')
                ->join('showrooms sh', 'sh.id = COALESCE(m.to_showroom_id, m.from_showroom_id)', 'left', false);
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'DATE(m.created_at)', $filters));
        }
        if ($db->tableExists('customer_receipts')) {
            $builder = $db->table('customer_receipts cr')
                ->select("cr.receipt_date as transaction_date, 'Customer Receipt' as transaction_type, 'Accounts' as material_type, o.order_no, COALESCE(NULLIF(cr.receipt_no,''), CONCAT('RCPT#', cr.id)) as reference_no, COALESCE(c.name, '-') as party_name, 'Received' as status, cr.customer_id, NULL as karigar_id, NULL as vendor_id, 0 as gold_gm, 0 as diamond_cts, 0 as stone_qty, COALESCE(cr.amount,0) as amount, cr.notes as notes", false)
                ->join('customers c', 'c.id = cr.customer_id', 'left')
                ->join('invoices i', 'i.id = cr.invoice_id', 'left')
                ->join('showroom_sales s', 's.invoice_id = i.id', 'left')
                ->join('showroom_sale_items ssi', 'ssi.showroom_sale_id = s.id', 'left')
                ->join('fg_items fg', 'fg.id = ssi.fg_item_id', 'left')
                ->join('orders o', 'o.id = fg.order_id', 'left')
                ->groupBy('cr.id');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'cr.receipt_date', $filters));
        }
        return $rows;
    }

    private function transactionRowsAccountPayments(array $filters): array
    {
        $rows = [];
        $db = db_connect();
        if ($db->tableExists('purchase_bill_payments')) {
            $builder = $db->table('purchase_bill_payments p')
                ->select("p.payment_date as transaction_date, 'Purchase Payment' as transaction_type, 'Accounts' as material_type, NULL as order_no, COALESCE(NULLIF(p.reference_no,''), CONCAT('PPAY#', p.id)) as reference_no, '-' as party_name, p.source_type as status, NULL as customer_id, NULL as karigar_id, NULL as vendor_id, 0 as gold_gm, 0 as diamond_cts, 0 as stone_qty, COALESCE(p.amount,0) as amount, p.notes as notes", false);
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'p.payment_date', $filters));
        }
        if ($db->tableExists('labour_bills')) {
            $builder = $db->table('labour_bills lb')
                ->select("lb.bill_date as transaction_date, 'Labour Bill' as transaction_type, 'Accounts' as material_type, o.order_no, COALESCE(NULLIF(lb.bill_no,''), CONCAT('LB#', lb.id)) as reference_no, COALESCE(k.name, '-') as party_name, lb.payment_status as status, o.customer_id, lb.karigar_id, NULL as vendor_id, COALESCE(lb.gold_weight_gm,0) as gold_gm, 0 as diamond_cts, 0 as stone_qty, COALESCE(lb.total_amount,0) as amount, lb.notes as notes", false)
                ->join('orders o', 'o.id = lb.order_id', 'left')
                ->join('karigars k', 'k.id = lb.karigar_id', 'left');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'lb.bill_date', $filters));
        }
        if ($db->tableExists('labour_bill_payments')) {
            $builder = $db->table('labour_bill_payments lp')
                ->select("lp.payment_date as transaction_date, 'Labour Payment' as transaction_type, 'Accounts' as material_type, o.order_no, COALESCE(NULLIF(lp.reference_no,''), CONCAT('LPAY#', lp.id)) as reference_no, COALESCE(k.name, '-') as party_name, 'Paid' as status, o.customer_id, lb.karigar_id, NULL as vendor_id, 0 as gold_gm, 0 as diamond_cts, 0 as stone_qty, COALESCE(lp.amount,0) as amount, lp.notes as notes", false)
                ->join('labour_bills lb', 'lb.id = lp.labour_bill_id', 'inner')
                ->join('orders o', 'o.id = lb.order_id', 'left')
                ->join('karigars k', 'k.id = lb.karigar_id', 'left');
            $rows = array_merge($rows, $this->fetchRowsByDate($builder, 'lp.payment_date', $filters));
        }
        return $rows;
    }

    /**
     * @param \CodeIgniter\Database\BaseBuilder $builder
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function fetchRowsByDate($builder, string $dateField, array $filters): array
    {
        if (($filters['from'] ?? '') !== '') {
            $builder->where($dateField . ' >=', $filters['from'], false);
        }
        if (($filters['to'] ?? '') !== '') {
            $builder->where($dateField . ' <=', $filters['to'], false);
        }

        return $builder->get()->getResultArray();
    }
}
