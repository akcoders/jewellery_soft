<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use Throwable;

class BusinessAnalyticsService
{
    private BaseConnection $db;
    /** @var list<string> */
    private array $warnings = [];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array<string,mixed> */
    public function dashboard(int $days = 90): array
    {
        $days = in_array($days, [30, 90, 180, 365], true) ? $days : 90;
        $end = new DateTimeImmutable('tomorrow 00:00:00');
        $start = $end->modify('-' . $days . ' days');
        $previousStart = $start->modify('-' . $days . ' days');

        $orders = $this->safe('orders', fn(): array => $this->orders($start, $end), []);
        $previousOrders = $this->safe('order comparison', fn(): array => $this->orders($previousStart, $start), []);
        $current = $this->analyseOrders($orders, $days);
        $previous = $this->analyseOrders($previousOrders, $days);
        $inventory = $this->safe('inventory', fn(): array => $this->inventory(), $this->emptyInventory());
        $purchases = $this->safe('purchases', fn(): array => $this->purchases($start, $end, $days), $this->emptyPurchases());
        $requirements = $this->safe('requirements', fn(): array => $this->requirements($start, $end, $days), $this->emptyRequirements());
        $employee = $this->safe('employee performance', fn(): array => (new StaffPerformanceService())->dashboardData((int) date('Y'), (int) date('n')), ['rows' => [], 'totals' => []]);
        $materialFlow = $this->safe('material flow', fn(): array => $this->materialFlow($start, $end, $purchases), ['purchases' => ['count' => 0, 'value' => 0], 'issuements' => ['count' => 0]]);
        $overview = $current['overview'];
        $purchaseValue = (float) ($purchases['overview']['value'] ?? 0);
        $employees = array_values((array) ($employee['rows'] ?? []));
        $employeeTotals = (array) ($employee['totals'] ?? []);

        return [
            'generated_at' => date(DATE_ATOM),
            'period' => ['days' => $days, 'from' => $start->format('Y-m-d'), 'to' => $end->modify('-1 day')->format('Y-m-d')],
            'overview' => $overview,
            'comparison' => [
                'orders' => $this->change((float) $overview['orders'], (float) ($previous['overview']['orders'] ?? 0)),
                'order_value' => $this->change((float) $overview['order_value'], (float) ($previous['overview']['order_value'] ?? 0)),
                'completed' => $this->change((float) $overview['completed'], (float) ($previous['overview']['completed'] ?? 0)),
                'average_order_value' => $this->change((float) $overview['average_order_value'], (float) ($previous['overview']['average_order_value'] ?? 0)),
            ],
            'financial' => [
                'order_value' => (float) $overview['order_value'], 'purchase_value' => $purchaseValue,
                'advance_collected' => (float) $overview['advance'], 'order_balance' => (float) $overview['balance'],
                'advance_coverage' => (float) $overview['order_value'] > 0 ? round((float) $overview['advance'] * 100 / (float) $overview['order_value'], 1) : 0,
                'value_less_purchases' => round((float) $overview['order_value'] - $purchaseValue, 2),
            ],
            'order_status' => $current['status'],
            'order_trend' => $current['trend'],
            'orders_analysis' => [
                'types' => $current['types'], 'priorities' => $current['priorities'], 'categories' => $current['categories'],
                'due_health' => $current['due_health'], 'receiving' => $current['receiving'],
                'recent' => array_slice(array_reverse($orders), 0, 12),
            ],
            'inventory' => $inventory,
            'material_flow' => $materialFlow,
            'purchases' => $purchases,
            'requirements' => $requirements,
            'customers' => $current['customers'],
            'karigars' => array_slice($current['karigars'], 0, 12),
            'employees' => array_slice($employees, 0, 20),
            'employee_performance' => [
                'period' => date('F Y'), 'totals' => $employeeTotals,
                'ranking' => array_slice($employees, 0, 20),
                'score_rules' => (new StaffPerformanceService())->scoreRules(),
            ],
            'insights' => $this->insights($overview, $inventory, $purchases, $requirements, $employeeTotals),
            'data_warnings' => $this->warnings,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function orders(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        if (! $this->db->tableExists('orders')) return [];
        $fields = [
            'o.id', 'o.order_no', 'o.order_name', 'o.status', 'o.order_type', 'o.created_at', 'o.updated_at',
            'o.due_date', 'o.approximate_price', 'o.advance_amount', 'o.customer_id', 'o.assigned_karigar_id', 'o.priority',
            $this->db->fieldExists('receiving_status', 'orders') ? 'o.receiving_status' : "'pending' AS receiving_status",
            $this->db->fieldExists('received_at', 'orders') ? 'o.received_at' : 'NULL AS received_at',
            $this->db->fieldExists('order_category_id', 'orders') ? 'o.order_category_id' : 'NULL AS order_category_id',
            'c.name AS customer_name', 'k.name AS karigar_name',
            $this->db->tableExists('order_categories') && $this->db->fieldExists('order_category_id', 'orders') ? 'oc.name AS category_name' : "'Uncategorised' AS category_name",
        ];
        $builder = $this->db->table('orders o')->select(implode(', ', $fields), false)
            ->join('customers c', 'c.id = o.customer_id', 'left')->join('karigars k', 'k.id = o.assigned_karigar_id', 'left');
        if ($this->db->tableExists('order_categories') && $this->db->fieldExists('order_category_id', 'orders')) $builder->join('order_categories oc', 'oc.id = o.order_category_id', 'left');
        $builder->where('o.created_at >=', $start->format('Y-m-d H:i:s'))->where('o.created_at <', $end->format('Y-m-d H:i:s'));
        if ($this->db->fieldExists('deleted_at', 'orders')) $builder->where('o.deleted_at', null);
        return $builder->orderBy('o.created_at', 'ASC')->get()->getResultArray();
    }

    /** @param list<array<string,mixed>> $orders @return array<string,mixed> */
    private function analyseOrders(array $orders, int $days): array
    {
        $status = $types = $priorities = $categories = $trend = $customers = $karigars = [];
        $orderValue = $advance = $cycleDays = 0.0;
        $completed = $overdue = $dueSoon = $onTrack = $noDue = $received = $receivingPending = 0;
        $today = date('Y-m-d'); $soon = date('Y-m-d', strtotime('+7 days'));
        foreach ($orders as $order) {
            $orderStatus = trim((string) ($order['status'] ?? '')) ?: 'Unknown';
            $type = trim((string) ($order['order_type'] ?? '')) ?: 'Other';
            $priority = trim((string) ($order['priority'] ?? '')) ?: 'Normal';
            $category = trim((string) ($order['category_name'] ?? '')) ?: 'Uncategorised';
            $status[$orderStatus] = ($status[$orderStatus] ?? 0) + 1;
            $types[$type] = ($types[$type] ?? 0) + 1;
            $priorities[$priority] = ($priorities[$priority] ?? 0) + 1;
            $categories[$category] = ($categories[$category] ?? 0) + 1;
            $value = (float) ($order['approximate_price'] ?? 0);
            $orderValue += $value; $advance += (float) ($order['advance_amount'] ?? 0);
            $isComplete = in_array($orderStatus, ['Completed', 'Complete', 'Delivered'], true);
            $isActive = ! $isComplete && $orderStatus !== 'Cancelled';
            if ($isComplete) {
                $completed++;
                $created = strtotime((string) ($order['created_at'] ?? ''));
                $closed = strtotime((string) (($order['received_at'] ?? '') ?: ($order['updated_at'] ?? '')));
                if ($created && $closed && $closed >= $created) $cycleDays += ($closed - $created) / 86400;
            }
            $due = trim((string) ($order['due_date'] ?? ''));
            if ($isActive && $due === '') $noDue++; elseif ($isActive && $due < $today) $overdue++; elseif ($isActive && $due <= $soon) $dueSoon++; elseif ($isActive) $onTrack++;
            if ((string) ($order['receiving_status'] ?? '') === 'received') $received++; elseif ($isComplete) $receivingPending++;
            $bucket = $days <= 30 ? substr((string) $order['created_at'], 0, 10) : substr((string) $order['created_at'], 0, 7);
            $trend[$bucket] ??= ['period' => $bucket, 'orders' => 0, 'value' => 0.0, 'advance' => 0.0, 'completed' => 0];
            $trend[$bucket]['orders']++; $trend[$bucket]['value'] += $value; $trend[$bucket]['advance'] += (float) ($order['advance_amount'] ?? 0); $trend[$bucket]['completed'] += $isComplete ? 1 : 0;
            $customerKey = (int) ($order['customer_id'] ?? 0) > 0 ? 'id:' . $order['customer_id'] : 'name:' . strtolower((string) ($order['customer_name'] ?? 'Walk-in'));
            $customers[$customerKey] ??= ['name' => (string) (($order['customer_name'] ?? '') ?: 'Walk-in Customer'), 'orders' => 0, 'value' => 0.0, 'completed' => 0];
            $customers[$customerKey]['orders']++; $customers[$customerKey]['value'] += $value; $customers[$customerKey]['completed'] += $isComplete ? 1 : 0;
            $karigar = trim((string) ($order['karigar_name'] ?? ''));
            if ($karigar !== '') {
                $karigars[$karigar] ??= ['name' => $karigar, 'orders' => 0, 'completed' => 0, 'value' => 0.0];
                $karigars[$karigar]['orders']++; $karigars[$karigar]['completed'] += $isComplete ? 1 : 0; $karigars[$karigar]['value'] += $value;
            }
        }
        ksort($trend);
        foreach ($trend as &$row) { $row['value'] = round((float) $row['value'], 2); $row['advance'] = round((float) $row['advance'], 2); }
        unset($row);
        arsort($status); arsort($types); arsort($priorities); arsort($categories);
        usort($customers, static fn(array $a, array $b): int => [$b['value'], $b['orders']] <=> [$a['value'], $a['orders']]);
        usort($karigars, static fn(array $a, array $b): int => [$b['completed'], $b['orders']] <=> [$a['completed'], $a['orders']]);
        $repeatCustomers = count(array_filter($customers, static fn(array $row): bool => $row['orders'] > 1));
        return [
            'overview' => [
                'orders' => count($orders), 'order_value' => round($orderValue, 2), 'advance' => round($advance, 2), 'balance' => round($orderValue - $advance, 2),
                'average_order_value' => count($orders) ? round($orderValue / count($orders), 2) : 0, 'completed' => $completed,
                'completion_rate' => count($orders) ? round($completed * 100 / count($orders), 1) : 0, 'average_cycle_days' => $completed ? round($cycleDays / $completed, 1) : 0,
                'overdue' => $overdue, 'received' => $received, 'receiving_pending' => $receivingPending,
            ],
            'status' => $this->namedCounts($status, 'status'), 'types' => $this->namedCounts($types, 'name'),
            'priorities' => $this->namedCounts($priorities, 'name'), 'categories' => $this->namedCounts($categories, 'name'), 'trend' => array_values($trend),
            'due_health' => [['name' => 'Overdue', 'count' => $overdue], ['name' => 'Due in 7 days', 'count' => $dueSoon], ['name' => 'On track', 'count' => $onTrack], ['name' => 'No due date', 'count' => $noDue]],
            'receiving' => [['name' => 'Received', 'count' => $received], ['name' => 'Completion without receipt', 'count' => $receivingPending]],
            'customers' => ['unique' => count($customers), 'repeat' => $repeatCustomers, 'repeat_rate' => count($customers) ? round($repeatCustomers * 100 / count($customers), 1) : 0, 'top' => array_slice($customers, 0, 12)],
            'karigars' => $karigars,
        ];
    }

    /** @return array<string,mixed> */
    private function inventory(): array
    {
        $sets = ['gold' => ['gold_inventory_stock', 'weight_balance_gm', 'gm'], 'diamond' => ['stock', 'carat_balance', 'cts'], 'stone' => ['stone_inventory_stock', 'qty_balance', 'qty']];
        $result = ['total_value' => 0.0, 'negative_skus' => 0, 'mix' => []];
        foreach ($sets as $key => [$table, $quantity, $unit]) {
            $row = ['quantity' => 0.0, 'value' => 0.0, 'negative_skus' => 0, 'unit' => $unit, 'skus' => 0];
            if ($this->db->tableExists($table) && $this->db->fieldExists($quantity, $table)) {
                $value = $this->db->fieldExists('stock_value', $table) ? 'stock_value' : '0';
                $found = $this->db->table($table)->select('COUNT(*) AS skus, COALESCE(SUM(' . $quantity . '),0) AS quantity, COALESCE(SUM(' . $value . '),0) AS value, SUM(CASE WHEN ' . $quantity . ' < 0 THEN 1 ELSE 0 END) AS negative_skus', false)->get()->getRowArray() ?: [];
                $row = ['quantity' => round((float) ($found['quantity'] ?? 0), 3), 'value' => round((float) ($found['value'] ?? 0), 2), 'negative_skus' => (int) ($found['negative_skus'] ?? 0), 'unit' => $unit, 'skus' => (int) ($found['skus'] ?? 0)];
            }
            $result[$key] = $row; $result['mix'][] = ['name' => ucfirst($key), 'value' => $row['value'], 'quantity' => $row['quantity'], 'unit' => $unit, 'negative_skus' => $row['negative_skus']];
            $result['total_value'] += $row['value']; $result['negative_skus'] += $row['negative_skus'];
        }
        $result['finished_goods'] = $this->db->tableExists('fg_items') ? ['available' => $this->db->table('fg_items')->whereIn('status', ['AVAILABLE', 'Available', 'Completed', 'Ready'])->countAllResults(), 'total' => $this->db->table('fg_items')->countAllResults()] : ['available' => 0, 'total' => 0];
        $result['total_value'] = round((float) $result['total_value'], 2);
        return $result;
    }

    /** @return array<string,mixed> */
    private function purchases(DateTimeImmutable $start, DateTimeImmutable $end, int $days): array
    {
        $sources = ['Gold' => 'gold_inventory_purchase_headers', 'Diamond' => 'purchase_headers', 'Stone' => 'stone_inventory_purchase_headers'];
        $rows = [];
        foreach ($sources as $material => $table) {
            if (! $this->db->tableExists($table) || ! $this->db->fieldExists('purchase_date', $table)) continue;
            $fields = ['h.id', 'h.purchase_date'];
            foreach (['invoice_no', 'supplier_name', 'invoice_total', 'payment_status', 'paid_amount', 'due_date', 'vendor_id'] as $field) {
                $fields[] = $this->db->fieldExists($field, $table) ? 'h.' . $field : (in_array($field, ['invoice_total', 'paid_amount', 'vendor_id'], true) ? '0 AS ' . $field : "'' AS " . $field);
            }
            $hasVendor = $this->db->tableExists('vendors') && $this->db->fieldExists('vendor_id', $table);
            $fields[] = $hasVendor ? 'v.name AS vendor_name' : "'' AS vendor_name";
            $builder = $this->db->table($table . ' h')->select(implode(', ', $fields), false);
            if ($hasVendor) $builder->join('vendors v', 'v.id = h.vendor_id', 'left');
            foreach ($builder->where('h.purchase_date >=', $start->format('Y-m-d'))->where('h.purchase_date <', $end->format('Y-m-d'))->get()->getResultArray() as $row) {
                $row['material'] = $material; $row['value'] = (float) ($row['invoice_total'] ?? 0);
                $row['vendor'] = trim((string) (($row['vendor_name'] ?? '') ?: ($row['supplier_name'] ?? ''))) ?: 'Unspecified'; $rows[] = $row;
            }
        }
        usort($rows, static fn(array $a, array $b): int => strcmp((string) $a['purchase_date'], (string) $b['purchase_date']));
        $trend = $materials = $vendors = $payments = []; $value = $paid = 0.0;
        foreach ($rows as $row) {
            $bucket = $days <= 30 ? substr((string) $row['purchase_date'], 0, 10) : substr((string) $row['purchase_date'], 0, 7);
            $trend[$bucket] ??= ['period' => $bucket, 'count' => 0, 'value' => 0.0]; $trend[$bucket]['count']++; $trend[$bucket]['value'] += (float) $row['value'];
            $material = (string) $row['material']; $materials[$material] ??= ['name' => $material, 'count' => 0, 'value' => 0.0]; $materials[$material]['count']++; $materials[$material]['value'] += (float) $row['value'];
            $vendor = (string) $row['vendor']; $vendors[$vendor] ??= ['name' => $vendor, 'count' => 0, 'value' => 0.0]; $vendors[$vendor]['count']++; $vendors[$vendor]['value'] += (float) $row['value'];
            $state = trim((string) ($row['payment_status'] ?? '')) ?: 'Not recorded'; $payments[$state] = ($payments[$state] ?? 0) + 1;
            $value += (float) $row['value']; $paid += (float) ($row['paid_amount'] ?? 0);
        }
        foreach ($trend as &$entry) $entry['value'] = round((float) $entry['value'], 2);
        unset($entry);
        foreach ($materials as &$entry) $entry['value'] = round((float) $entry['value'], 2);
        unset($entry);
        foreach ($vendors as &$entry) $entry['value'] = round((float) $entry['value'], 2);
        unset($entry);
        usort($vendors, static fn(array $a, array $b): int => $b['value'] <=> $a['value']);
        return ['overview' => ['count' => count($rows), 'value' => round($value, 2), 'paid' => round($paid, 2), 'outstanding' => round(max(0, $value - $paid), 2), 'average_bill' => count($rows) ? round($value / count($rows), 2) : 0], 'trend' => array_values($trend), 'materials' => array_values($materials), 'vendors' => array_slice($vendors, 0, 12), 'payment_status' => $this->namedCounts($payments, 'name'), 'recent' => array_slice(array_reverse($rows), 0, 15)];
    }

    /** @return array<string,mixed> */
    private function requirements(DateTimeImmutable $start, DateTimeImmutable $end, int $days): array
    {
        if (! $this->db->tableExists('diamond_requirements')) return $this->emptyRequirements();
        $rows = $this->db->table('diamond_requirements dr')->select('dr.id, dr.requirement_no, dr.order_id, dr.required_by, dr.status, dr.created_at, dr.ready_at, o.order_no, requester.name AS requested_by_name, assignee.name AS assigned_to_name')
            ->join('orders o', 'o.id = dr.order_id', 'left')->join('admin_users requester', 'requester.id = dr.requested_by', 'left')->join('admin_users assignee', 'assignee.id = dr.assigned_to', 'left')
            ->where('dr.created_at >=', $start->format('Y-m-d H:i:s'))->where('dr.created_at <', $end->format('Y-m-d H:i:s'))->orderBy('dr.created_at', 'ASC')->get()->getResultArray();
        $status = $trend = []; $ready = $overdue = 0; $turnaround = 0.0;
        foreach ($rows as $row) {
            $state = trim((string) ($row['status'] ?? '')) ?: 'unknown'; $status[$state] = ($status[$state] ?? 0) + 1;
            $bucket = $days <= 30 ? substr((string) $row['created_at'], 0, 10) : substr((string) $row['created_at'], 0, 7);
            $trend[$bucket] ??= ['period' => $bucket, 'raised' => 0, 'ready' => 0]; $trend[$bucket]['raised']++;
            if ($state === 'ready' || ! empty($row['ready_at'])) {
                $ready++; $trend[$bucket]['ready']++; $created = strtotime((string) $row['created_at']); $closed = strtotime((string) $row['ready_at']);
                if ($created && $closed && $closed >= $created) $turnaround += ($closed - $created) / 3600;
            }
            if (! in_array($state, ['ready', 'rejected', 'cancelled'], true) && ! empty($row['required_by']) && (string) $row['required_by'] < date('Y-m-d')) $overdue++;
        }
        ksort($trend); arsort($status);
        return ['overview' => ['total' => count($rows), 'ready' => $ready, 'pending' => max(0, count($rows) - $ready), 'overdue' => $overdue, 'fulfilment_rate' => count($rows) ? round($ready * 100 / count($rows), 1) : 0, 'average_turnaround_hours' => $ready ? round($turnaround / $ready, 1) : 0], 'status' => $this->namedCounts($status, 'name'), 'trend' => array_values($trend), 'recent' => array_slice(array_reverse($rows), 0, 12)];
    }

    /** @return array<string,mixed> */
    private function materialFlow(DateTimeImmutable $start, DateTimeImmutable $end, array $purchases): array
    {
        $vouchers = [];
        foreach (['gold_inventory_issue_headers', 'issue_headers', 'stone_inventory_issue_headers'] as $table) {
            if (! $this->db->tableExists($table) || ! $this->db->fieldExists('issue_date', $table)) continue;
            $voucherField = $this->db->fieldExists('voucher_no', $table) ? 'voucher_no' : 'id';
            foreach ($this->db->table($table)->select('id, ' . $voucherField . ' AS voucher')->where('issue_date >=', $start->format('Y-m-d'))->where('issue_date <', $end->format('Y-m-d'))->get()->getResultArray() as $row) {
                $voucher = trim((string) ($row['voucher'] ?? '')); $vouchers[$voucher !== '' ? $voucher : $table . ':' . $row['id']] = true;
            }
        }
        return ['purchases' => ['count' => (int) ($purchases['overview']['count'] ?? 0), 'value' => (float) ($purchases['overview']['value'] ?? 0)], 'issuements' => ['count' => count($vouchers)]];
    }

    /** @return list<array<string,string>> */
    private function insights(array $overview, array $inventory, array $purchases, array $requirements, array $employees): array
    {
        $items = [];
        if ((int) ($overview['receiving_pending'] ?? 0) > 0) $items[] = ['severity' => 'critical', 'title' => $overview['receiving_pending'] . ' completed orders still need receiving', 'action' => 'Post actual finished jewellery weights before dispatch.'];
        if ((int) ($overview['overdue'] ?? 0) > 0) $items[] = ['severity' => 'warning', 'title' => $overview['overdue'] . ' active orders are overdue', 'action' => 'Prioritise follower action by due date.'];
        if ((int) ($requirements['overview']['overdue'] ?? 0) > 0) $items[] = ['severity' => 'warning', 'title' => $requirements['overview']['overdue'] . ' diamond requirements are overdue', 'action' => 'Escalate preparation and bag readiness.'];
        if ((int) ($inventory['negative_skus'] ?? 0) > 0) $items[] = ['severity' => 'critical', 'title' => $inventory['negative_skus'] . ' inventory SKUs are negative', 'action' => 'Post pending purchases or verified adjustments.'];
        if ((float) ($purchases['overview']['outstanding'] ?? 0) > 0) $items[] = ['severity' => 'info', 'title' => 'Purchase outstanding is Rs ' . number_format((float) $purchases['overview']['outstanding'], 0), 'action' => 'Review supplier payment dates and cash requirement.'];
        if ((float) ($employees['on_time_rate'] ?? 100) < 80) $items[] = ['severity' => 'warning', 'title' => 'Team on-time rate is ' . number_format((float) ($employees['on_time_rate'] ?? 0), 1) . '%', 'action' => 'Review overdue tasks and workload distribution.'];
        if ($items === []) $items[] = ['severity' => 'positive', 'title' => 'No critical operating exception detected', 'action' => 'Continue monitoring conversion, stock and delivery performance.'];
        return $items;
    }

    /** @param array<string,int> $values @return list<array<string,mixed>> */
    private function namedCounts(array $values, string $label): array
    {
        return array_map(static fn(string $name, int $count): array => [$label => $name, 'count' => $count], array_keys($values), array_values($values));
    }

    /** @return array<string,float> */
    private function change(float $current, float $previous): array
    {
        return ['current' => $current, 'previous' => $previous, 'percent' => $previous != 0.0 ? round(($current - $previous) * 100 / abs($previous), 1) : ($current > 0 ? 100.0 : 0.0)];
    }

    private function safe(string $section, callable $callback, array $fallback): array
    {
        try { return $callback(); }
        catch (Throwable $e) {
            log_message('error', 'Business analytics {section} unavailable: {message}', ['section' => $section, 'message' => $e->getMessage()]);
            $this->warnings[] = ucfirst($section) . ' is temporarily unavailable.'; return $fallback;
        }
    }

    /** @return array<string,mixed> */
    private function emptyInventory(): array
    {
        $row = ['quantity' => 0.0, 'value' => 0.0, 'negative_skus' => 0, 'unit' => '', 'skus' => 0];
        return ['total_value' => 0.0, 'negative_skus' => 0, 'gold' => array_replace($row, ['unit' => 'gm']), 'diamond' => array_replace($row, ['unit' => 'cts']), 'stone' => array_replace($row, ['unit' => 'qty']), 'finished_goods' => ['available' => 0, 'total' => 0], 'mix' => []];
    }

    /** @return array<string,mixed> */
    private function emptyPurchases(): array
    {
        return ['overview' => ['count' => 0, 'value' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0, 'average_bill' => 0.0], 'trend' => [], 'materials' => [], 'vendors' => [], 'payment_status' => [], 'recent' => []];
    }

    /** @return array<string,mixed> */
    private function emptyRequirements(): array
    {
        return ['overview' => ['total' => 0, 'ready' => 0, 'pending' => 0, 'overdue' => 0, 'fulfilment_rate' => 0.0, 'average_turnaround_hours' => 0.0], 'status' => [], 'trend' => [], 'recent' => []];
    }
}
