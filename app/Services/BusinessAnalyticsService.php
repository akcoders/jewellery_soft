<?php

namespace App\Services;

use DateTimeImmutable;

class BusinessAnalyticsService
{
    public function dashboard(int $days = 90): array
    {
        $days = in_array($days, [30, 90, 180, 365], true) ? $days : 90;
        $end = new DateTimeImmutable('tomorrow 00:00:00');
        $start = $end->modify('-' . $days . ' days');
        $orders = $this->orders($start, $end);

        $status = $trend = $customers = $karigars = [];
        $orderValue = $advance = $cycleDays = 0.0;
        $completed = $overdue = $received = $receivingPending = 0;
        foreach ($orders as $order) {
            $orderStatus = trim((string) ($order['status'] ?? '')) ?: 'Unknown';
            $status[$orderStatus] = ($status[$orderStatus] ?? 0) + 1;
            $value = (float) ($order['approximate_price'] ?? 0);
            $orderValue += $value;
            $advance += (float) ($order['advance_amount'] ?? 0);
            $isComplete = in_array($orderStatus, ['Completed', 'Complete', 'Delivered'], true);
            if ($isComplete) {
                $completed++;
                $created = strtotime((string) ($order['created_at'] ?? ''));
                $closed = strtotime((string) (($order['received_at'] ?? '') ?: ($order['updated_at'] ?? '')));
                if ($created && $closed && $closed >= $created) {
                    $cycleDays += ($closed - $created) / 86400;
                }
            }
            if (! $isComplete && $orderStatus !== 'Cancelled' && ! empty($order['due_date']) && (string) $order['due_date'] < date('Y-m-d')) {
                $overdue++;
            }
            if ((string) ($order['receiving_status'] ?? '') === 'received') {
                $received++;
            } elseif ($isComplete) {
                $receivingPending++;
            }
            $bucket = $days <= 30 ? substr((string) $order['created_at'], 0, 10) : substr((string) $order['created_at'], 0, 7);
            $trend[$bucket] ??= ['period' => $bucket, 'orders' => 0, 'value' => 0.0, 'advance' => 0.0];
            $trend[$bucket]['orders']++;
            $trend[$bucket]['value'] += $value;
            $trend[$bucket]['advance'] += (float) ($order['advance_amount'] ?? 0);

            $customerKey = (int) ($order['customer_id'] ?? 0) > 0 ? 'id:' . $order['customer_id'] : 'name:' . strtolower((string) ($order['customer_name'] ?? 'Walk-in'));
            $customers[$customerKey] ??= ['name' => (string) (($order['customer_name'] ?? '') ?: 'Walk-in Customer'), 'orders' => 0, 'value' => 0.0, 'completed' => 0];
            $customers[$customerKey]['orders']++;
            $customers[$customerKey]['value'] += $value;
            $customers[$customerKey]['completed'] += $isComplete ? 1 : 0;

            $karigar = trim((string) ($order['karigar_name'] ?? ''));
            if ($karigar !== '') {
                $karigars[$karigar] ??= ['name' => $karigar, 'orders' => 0, 'completed' => 0, 'value' => 0.0];
                $karigars[$karigar]['orders']++;
                $karigars[$karigar]['completed'] += $isComplete ? 1 : 0;
                $karigars[$karigar]['value'] += $value;
            }
        }
        ksort($trend);
        arsort($status);
        usort($customers, static fn(array $a, array $b): int => [$b['value'], $b['orders']] <=> [$a['value'], $a['orders']]);
        usort($karigars, static fn(array $a, array $b): int => [$b['completed'], $b['orders']] <=> [$a['completed'], $a['orders']]);
        $repeatCustomers = count(array_filter($customers, static fn(array $row): bool => $row['orders'] > 1));
        $inventory = $this->inventory();
        $materials = $this->materialFlow($start, $end);
        $employee = (new StaffPerformanceService())->dashboardData((int) date('Y'), (int) date('n'));

        $insights = [];
        if ($receivingPending > 0) {
            $insights[] = ['severity' => 'critical', 'title' => $receivingPending . ' completed order(s) still need receiving', 'action' => 'Open Receiving and post the actual finished jewellery weights.'];
        }
        if ($overdue > 0) {
            $insights[] = ['severity' => 'warning', 'title' => $overdue . ' active order(s) are overdue', 'action' => 'Prioritise follow-ups by delivery date.'];
        }
        if ($inventory['negative_skus'] > 0) {
            $insights[] = ['severity' => 'warning', 'title' => $inventory['negative_skus'] . ' inventory SKU(s) are negative', 'action' => 'Post pending purchases or stock adjustments.'];
        }
        if ($insights === []) {
            $insights[] = ['severity' => 'positive', 'title' => 'No critical operating exception detected', 'action' => 'Continue monitoring conversion, stock and delivery performance.'];
        }

        return [
            'period' => ['days' => $days, 'from' => $start->format('Y-m-d'), 'to' => $end->modify('-1 day')->format('Y-m-d')],
            'overview' => [
                'orders' => count($orders), 'order_value' => round($orderValue, 2), 'advance' => round($advance, 2),
                'balance' => round($orderValue - $advance, 2), 'average_order_value' => count($orders) ? round($orderValue / count($orders), 2) : 0,
                'completed' => $completed, 'completion_rate' => count($orders) ? round($completed * 100 / count($orders), 1) : 0,
                'average_cycle_days' => $completed ? round($cycleDays / $completed, 1) : 0, 'overdue' => $overdue,
                'received' => $received, 'receiving_pending' => $receivingPending,
            ],
            'order_status' => array_map(static fn(string $name, int $count): array => ['status' => $name, 'count' => $count], array_keys($status), array_values($status)),
            'order_trend' => array_values($trend),
            'inventory' => $inventory,
            'material_flow' => $materials,
            'customers' => ['unique' => count($customers), 'repeat' => $repeatCustomers, 'repeat_rate' => count($customers) ? round($repeatCustomers * 100 / count($customers), 1) : 0, 'top' => array_slice($customers, 0, 8)],
            'karigars' => array_slice($karigars, 0, 8),
            'employees' => array_slice((array) ($employee['rows'] ?? []), 0, 10),
            'insights' => $insights,
        ];
    }

    private function orders(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $db = db_connect();
        if (! $db->tableExists('orders')) {
            return [];
        }
        $receiving = $db->fieldExists('receiving_status', 'orders') ? 'o.receiving_status, o.received_at,' : "'pending' AS receiving_status, NULL AS received_at,";
        $builder = $db->table('orders o')->select('o.id, o.order_no, o.status, o.order_type, o.created_at, o.updated_at, o.due_date, o.approximate_price, o.advance_amount, o.customer_id, o.assigned_karigar_id, ' . $receiving . ' c.name AS customer_name, k.name AS karigar_name', false)
            ->join('customers c', 'c.id = o.customer_id', 'left')->join('karigars k', 'k.id = o.assigned_karigar_id', 'left')
            ->where('o.created_at >=', $start->format('Y-m-d H:i:s'))->where('o.created_at <', $end->format('Y-m-d H:i:s'));
        if ($db->fieldExists('deleted_at', 'orders')) {
            $builder->where('o.deleted_at', null);
        }
        return $builder->orderBy('o.created_at', 'ASC')->get()->getResultArray();
    }

    private function inventory(): array
    {
        $db = db_connect();
        $sets = [
            'gold' => ['table' => 'gold_inventory_stock', 'quantity' => 'weight_balance_gm', 'label' => 'gm'],
            'diamond' => ['table' => 'stock', 'quantity' => 'carat_balance', 'label' => 'cts'],
            'stone' => ['table' => 'stone_inventory_stock', 'quantity' => 'qty_balance', 'label' => 'qty'],
        ];
        $result = ['total_value' => 0.0, 'negative_skus' => 0];
        foreach ($sets as $key => $set) {
            $row = ['quantity' => 0.0, 'value' => 0.0, 'negative_skus' => 0, 'unit' => $set['label']];
            if ($db->tableExists($set['table'])) {
                $found = $db->table($set['table'])->select('COALESCE(SUM(' . $set['quantity'] . '),0) AS quantity, COALESCE(SUM(stock_value),0) AS value, SUM(CASE WHEN ' . $set['quantity'] . ' < 0 THEN 1 ELSE 0 END) AS negative_skus', false)->get()->getRowArray() ?: [];
                $row = ['quantity' => round((float) ($found['quantity'] ?? 0), 3), 'value' => round((float) ($found['value'] ?? 0), 2), 'negative_skus' => (int) ($found['negative_skus'] ?? 0), 'unit' => $set['label']];
            }
            $result[$key] = $row;
            $result['total_value'] += $row['value'];
            $result['negative_skus'] += $row['negative_skus'];
        }
        if ($db->tableExists('fg_items')) {
            $result['finished_goods'] = ['available' => $db->table('fg_items')->where('status', 'AVAILABLE')->countAllResults(), 'total' => $db->table('fg_items')->countAllResults()];
        } else {
            $result['finished_goods'] = ['available' => 0, 'total' => 0];
        }
        $result['total_value'] = round($result['total_value'], 2);
        return $result;
    }

    private function materialFlow(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $db = db_connect();
        $purchases = ['count' => 0, 'value' => 0.0];
        foreach (['gold_inventory_purchase_headers', 'purchase_headers', 'stone_inventory_purchase_headers'] as $table) {
            if (! $db->tableExists($table)) continue;
            $valueField = $db->fieldExists('invoice_total', $table) ? 'invoice_total' : '0';
            $row = $db->table($table)->select('COUNT(*) AS rows, COALESCE(SUM(' . $valueField . '),0) AS value', false)
                ->where('purchase_date >=', $start->format('Y-m-d'))->where('purchase_date <', $end->format('Y-m-d'))->get()->getRowArray() ?: [];
            $purchases['count'] += (int) ($row['rows'] ?? 0); $purchases['value'] += (float) ($row['value'] ?? 0);
        }
        $issueVouchers = [];
        foreach (['gold_inventory_issue_headers', 'issue_headers', 'stone_inventory_issue_headers'] as $table) {
            if (! $db->tableExists($table)) continue;
            foreach ($db->table($table)->select('id, voucher_no')->where('issue_date >=', $start->format('Y-m-d'))->where('issue_date <', $end->format('Y-m-d'))->get()->getResultArray() as $row) {
                $voucher = trim((string) ($row['voucher_no'] ?? ''));
                $issueVouchers[$voucher !== '' ? $voucher : $table . ':' . $row['id']] = true;
            }
        }
        return ['purchases' => ['count' => $purchases['count'], 'value' => round($purchases['value'], 2)], 'issuements' => ['count' => count($issueVouchers)]];
    }
}
