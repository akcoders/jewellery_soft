<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Throwable;

class AdminAlertCenterService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @return array{
     *     total_count:int,
     *     payments:array{count:int,amount:float,items:list<array<string,mixed>>},
     *     orders:array{count:int,overdue_count:int,items:list<array<string,mixed>>},
     *     followups:array{count:int,overdue_count:int,items:list<array<string,mixed>>},
     *     generated_at:string
     * }
     */
    public function build(bool $includeOrders = true, bool $includePayments = true): array
    {
        $orders = $includeOrders ? $this->orderAlerts() : $this->emptySection(true);
        $followups = $includeOrders ? $this->followupAlerts($orders['_active_orders'] ?? []) : $this->emptySection(true);
        unset($orders['_active_orders']);

        $payments = $includePayments ? $this->paymentAlerts() : $this->emptySection();

        return [
            'total_count' => (int) $payments['count'] + (int) $orders['count'] + (int) $followups['count'],
            'payments' => $payments,
            'orders' => $orders,
            'followups' => $followups,
            'generated_at' => date('d M Y, h:i A'),
        ];
    }

    /** @return array<string,mixed> */
    private function orderAlerts(): array
    {
        $empty = $this->emptySection(true);
        $empty['_active_orders'] = [];
        if (! $this->db->tableExists('orders')) {
            return $empty;
        }

        try {
            $builder = $this->db->table('orders o')
                ->select('o.id, o.order_no, o.status, o.due_date, o.created_at');
            if ($this->db->fieldExists('order_name', 'orders')) {
                $builder->select('o.order_name');
            }
            if ($this->db->fieldExists('followup_due_at', 'orders')) {
                $builder->select('o.followup_due_at');
            }
            if ($this->db->fieldExists('deleted_at', 'orders')) {
                $builder->where('o.deleted_at IS NULL', null, false);
            }

            $activeOrders = $builder
                ->whereNotIn('o.status', ['Completed', 'Dispatched', 'Cancelled'])
                ->orderBy('CASE WHEN o.due_date IS NULL THEN 1 ELSE 0 END', 'ASC', false)
                ->orderBy('o.due_date', 'ASC')
                ->orderBy('o.id', 'DESC')
                ->get()
                ->getResultArray();
        } catch (Throwable $exception) {
            log_message('error', 'Unable to load admin order alerts: {message}', ['message' => $exception->getMessage()]);
            return $empty;
        }

        $today = date('Y-m-d');
        $overdueCount = 0;
        $orderIds = [];
        foreach ($activeOrders as &$order) {
            $orderId = (int) ($order['id'] ?? 0);
            if ($orderId > 0) {
                $orderIds[] = $orderId;
            }
            $dueDate = substr(trim((string) ($order['due_date'] ?? '')), 0, 10);
            $order['is_overdue'] = $dueDate !== '' && $dueDate < $today;
            if ($order['is_overdue']) {
                $overdueCount++;
            }
            $order['url'] = site_url('admin/orders/' . $orderId);
            $order['thumbnail_url'] = '';
        }
        unset($order);

        $thumbnails = (new OrderThumbnailService($this->db))->map($orderIds);
        foreach ($activeOrders as &$order) {
            $order['thumbnail_url'] = (string) ($thumbnails[(int) ($order['id'] ?? 0)] ?? '');
        }
        unset($order);

        $items = $activeOrders;
        usort($items, static function (array $left, array $right): int {
            $leftRank = ! empty($left['is_overdue']) ? 0 : (($left['due_date'] ?? '') !== '' ? 1 : 2);
            $rightRank = ! empty($right['is_overdue']) ? 0 : (($right['due_date'] ?? '') !== '' ? 1 : 2);
            return [$leftRank, (string) ($left['due_date'] ?? '9999-12-31'), -(int) ($left['id'] ?? 0)]
                <=> [$rightRank, (string) ($right['due_date'] ?? '9999-12-31'), -(int) ($right['id'] ?? 0)];
        });

        return [
            'count' => count($activeOrders),
            'overdue_count' => $overdueCount,
            'items' => array_slice($items, 0, 8),
            '_active_orders' => $activeOrders,
        ];
    }

    /**
     * @param list<array<string,mixed>> $activeOrders
     * @return array{count:int,overdue_count:int,items:list<array<string,mixed>>}
     */
    private function followupAlerts(array $activeOrders): array
    {
        $empty = $this->emptySection(true);
        if ($activeOrders === [] || ! $this->db->tableExists('order_followups')) {
            return $empty;
        }

        $orderIds = array_values(array_filter(array_map(
            static fn(array $order): int => (int) ($order['id'] ?? 0),
            $activeOrders
        )));
        if ($orderIds === []) {
            return $empty;
        }

        try {
            $subquery = $this->db->table('order_followups')
                ->select('MAX(id) AS id')
                ->whereIn('order_id', $orderIds)
                ->groupBy('order_id')
                ->getCompiledSelect();
            $latestRows = $this->db->table('order_followups ofu')
                ->select('ofu.order_id, ofu.stage, ofu.next_followup_date, ofu.followup_taken_on')
                ->join('(' . $subquery . ') latest', 'latest.id = ofu.id', 'inner', false)
                ->get()
                ->getResultArray();
        } catch (Throwable $exception) {
            log_message('error', 'Unable to load admin follow-up alerts: {message}', ['message' => $exception->getMessage()]);
            return $empty;
        }

        $latestByOrder = [];
        foreach ($latestRows as $row) {
            $latestByOrder[(int) ($row['order_id'] ?? 0)] = $row;
        }

        $endOfToday = strtotime(date('Y-m-d 23:59:59')) ?: time();
        $items = [];
        $overdueCount = 0;
        foreach ($activeOrders as $order) {
            if (in_array((string) ($order['status'] ?? ''), ['Ready', 'Packed'], true)) {
                continue;
            }
            $orderId = (int) ($order['id'] ?? 0);
            $latest = $latestByOrder[$orderId] ?? [];
            $dueAt = trim((string) (($order['followup_due_at'] ?? '') ?: ($latest['next_followup_date'] ?? '')));
            $dueTimestamp = $dueAt !== '' ? strtotime($dueAt) : false;
            if ($dueTimestamp === false || $dueTimestamp > $endOfToday) {
                continue;
            }

            $isOverdue = $dueTimestamp < strtotime(date('Y-m-d 00:00:00'));
            if ($isOverdue) {
                $overdueCount++;
            }
            $items[] = [
                'id' => $orderId,
                'order_no' => (string) ($order['order_no'] ?? ''),
                'order_name' => (string) ($order['order_name'] ?? ''),
                'status' => (string) ($order['status'] ?? ''),
                'stage' => (string) ($latest['stage'] ?? ''),
                'due_at' => $dueAt,
                'is_overdue' => $isOverdue,
                'thumbnail_url' => (string) ($order['thumbnail_url'] ?? ''),
                'url' => site_url('admin/orders/' . $orderId),
            ];
        }

        usort($items, static fn(array $left, array $right): int => strcmp((string) $left['due_at'], (string) $right['due_at']));

        return [
            'count' => count($items),
            'overdue_count' => $overdueCount,
            'items' => array_slice($items, 0, 8),
        ];
    }

    /** @return array{count:int,amount:float,items:list<array<string,mixed>>} */
    private function paymentAlerts(): array
    {
        $items = [];
        try {
            $purchasePayments = $this->purchasePaymentMap();
            $this->appendInventoryPurchaseAlerts($items, 'purchase_headers', 'purchase_lines', 'Diamond', 'diamond-inventory/purchases/view/', $purchasePayments);
            $this->appendInventoryPurchaseAlerts($items, 'gold_inventory_purchase_headers', 'gold_inventory_purchase_lines', 'Gold', 'gold-inventory/purchases/view/', $purchasePayments);
            $this->appendInventoryPurchaseAlerts($items, 'stone_inventory_purchase_headers', 'stone_inventory_purchase_lines', 'Stone', 'stone-inventory/purchases/view/', $purchasePayments);
            $this->appendProductionDocumentAlerts($items, $purchasePayments);
            $this->appendLabourPaymentAlerts($items);
        } catch (Throwable $exception) {
            log_message('error', 'Unable to load admin payment alerts: {message}', ['message' => $exception->getMessage()]);
        }

        usort($items, static function (array $left, array $right): int {
            $leftDue = trim((string) ($left['due_date'] ?? ''));
            $rightDue = trim((string) ($right['due_date'] ?? ''));
            return [$leftDue === '' ? 1 : 0, $leftDue ?: '9999-12-31', -(int) ($left['source_id'] ?? 0)]
                <=> [$rightDue === '' ? 1 : 0, $rightDue ?: '9999-12-31', -(int) ($right['source_id'] ?? 0)];
        });

        return [
            'count' => count($items),
            'amount' => round(array_sum(array_column($items, 'pending_amount')), 2),
            'items' => array_slice($items, 0, 8),
        ];
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param array<string,float> $paymentMap
     */
    private function appendInventoryPurchaseAlerts(
        array &$items,
        string $headerTable,
        string $lineTable,
        string $category,
        string $viewPath,
        array $paymentMap
    ): void {
        if (! $this->db->tableExists($headerTable) || ! $this->db->tableExists($lineTable)) {
            return;
        }

        $sourceType = strtolower($category);
        $builder = $this->db->table($headerTable . ' ph')
            ->select('ph.id, MAX(ph.vendor_id) AS vendor_id, MAX(ph.purchase_date) AS purchase_date, MAX(ph.due_date) AS due_date, MAX(ph.invoice_no) AS invoice_no, MAX(ph.supplier_name) AS supplier_name, MAX(ph.invoice_total) AS invoice_total, COALESCE(SUM(pl.line_value), 0) AS line_total, MAX(v.name) AS vendor_name', false)
            ->join($lineTable . ' pl', 'pl.purchase_id = ph.id', 'left')
            ->join('vendors v', 'v.id = ph.vendor_id', 'left')
            ->groupBy('ph.id')
            ->orderBy('ph.id', 'DESC');
        if ($this->db->fieldExists('paid_amount', $headerTable)) {
            $builder->select('MAX(ph.paid_amount) AS paid_amount', false);
        }
        $rows = $builder->get()->getResultArray();

        foreach ($rows as $row) {
            $sourceId = (int) ($row['id'] ?? 0);
            $total = (float) ($row['invoice_total'] ?? 0);
            if ($total <= 0) {
                $total = (float) ($row['line_total'] ?? 0);
            }
            $paid = max((float) ($row['paid_amount'] ?? 0), (float) ($paymentMap[$sourceType . ':' . $sourceId] ?? 0));
            $pending = max(0, round($total - $paid, 2));
            if ($pending <= 0.009) {
                continue;
            }

            $dueDate = substr(trim((string) ($row['due_date'] ?? '')), 0, 10);
            $items[] = [
                'source_id' => $sourceId,
                'type' => $category . ' Purchase',
                'party' => trim((string) (($row['vendor_name'] ?? '') ?: ($row['supplier_name'] ?? '') ?: 'Supplier')),
                'reference' => trim((string) (($row['invoice_no'] ?? '') ?: ('Purchase #' . $sourceId))),
                'date' => (string) ($row['purchase_date'] ?? ''),
                'due_date' => $dueDate,
                'is_overdue' => $dueDate !== '' && $dueDate < date('Y-m-d'),
                'pending_amount' => $pending,
                'url' => site_url('admin/' . $viewPath . $sourceId),
            ];
        }
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param array<string,float> $paymentMap
     */
    private function appendProductionDocumentAlerts(array &$items, array $paymentMap): void
    {
        $table = 'production_purchase_documents';
        if (! $this->db->tableExists($table)
            || ! $this->db->fieldExists('invoice_amount', $table)
            || ! $this->db->fieldExists('paid_amount', $table)
            || ! $this->db->fieldExists('due_date', $table)) {
            return;
        }

        $linkedDocumentIds = [];
        foreach (['purchase_headers', 'gold_inventory_purchase_headers'] as $headerTable) {
            if (! $this->db->tableExists($headerTable) || ! $this->db->fieldExists('production_document_id', $headerTable)) {
                continue;
            }
            foreach ($this->db->table($headerTable)->select('production_document_id')->where('production_document_id IS NOT NULL', null, false)->get()->getResultArray() as $row) {
                $documentId = (int) ($row['production_document_id'] ?? 0);
                if ($documentId > 0) {
                    $linkedDocumentIds[$documentId] = true;
                }
            }
        }

        $builder = $this->db->table($table . ' d')
            ->select('d.id, d.category, d.vendor_name, d.document_date, d.invoice_no, d.original_name, d.invoice_amount, d.paid_amount, d.due_date')
            ->where('d.category !=', 'gold')
            ->where('d.invoice_amount IS NOT NULL', null, false)
            ->orderBy('d.id', 'DESC');
        foreach ($builder->get()->getResultArray() as $row) {
            $sourceId = (int) ($row['id'] ?? 0);
            if ($sourceId <= 0 || isset($linkedDocumentIds[$sourceId])) {
                continue;
            }
            $total = (float) ($row['invoice_amount'] ?? 0);
            $paid = max((float) ($row['paid_amount'] ?? 0), (float) ($paymentMap['production_document:' . $sourceId] ?? 0));
            $pending = max(0, round($total - $paid, 2));
            if ($pending <= 0.009) {
                continue;
            }

            $dueDate = substr(trim((string) ($row['due_date'] ?? '')), 0, 10);
            $category = ucfirst(trim((string) ($row['category'] ?? 'Purchase')) ?: 'Purchase');
            $items[] = [
                'source_id' => $sourceId,
                'type' => $category . ' Purchase',
                'party' => trim((string) (($row['vendor_name'] ?? '') ?: 'Supplier')),
                'reference' => trim((string) (($row['invoice_no'] ?? '') ?: ($row['original_name'] ?? '') ?: ('Document #' . $sourceId))),
                'date' => (string) ($row['document_date'] ?? ''),
                'due_date' => $dueDate,
                'is_overdue' => $dueDate !== '' && $dueDate < date('Y-m-d'),
                'pending_amount' => $pending,
                'url' => site_url('admin/accounts/production-document/' . $sourceId),
            ];
        }
    }

    /** @param list<array<string,mixed>> $items */
    private function appendLabourPaymentAlerts(array &$items): void
    {
        if (! $this->db->tableExists('labour_bills')) {
            return;
        }

        $builder = $this->db->table('labour_bills lb')
            ->select('lb.id, MAX(lb.bill_no) AS bill_no, MAX(lb.bill_date) AS bill_date, MAX(lb.due_date) AS due_date, MAX(lb.total_amount) AS total_amount, MAX(k.name) AS karigar_name', false)
            ->join('karigars k', 'k.id = lb.karigar_id', 'left')
            ->groupBy('lb.id');
        if ($this->db->tableExists('labour_bill_payments')) {
            $builder->select('COALESCE(SUM(lbp.amount), 0) AS paid_amount', false)
                ->join('labour_bill_payments lbp', 'lbp.labour_bill_id = lb.id', 'left');
        } else {
            $builder->select('0 AS paid_amount', false);
        }

        foreach ($builder->orderBy('lb.id', 'DESC')->get()->getResultArray() as $row) {
            $pending = max(0, round((float) ($row['total_amount'] ?? 0) - (float) ($row['paid_amount'] ?? 0), 2));
            if ($pending <= 0.009) {
                continue;
            }
            $dueDate = substr(trim((string) ($row['due_date'] ?? '')), 0, 10);
            $items[] = [
                'source_id' => (int) ($row['id'] ?? 0),
                'type' => 'Labour Bill',
                'party' => trim((string) (($row['karigar_name'] ?? '') ?: 'Karigar')),
                'reference' => trim((string) (($row['bill_no'] ?? '') ?: ('Bill #' . (int) ($row['id'] ?? 0)))),
                'date' => (string) ($row['bill_date'] ?? ''),
                'due_date' => $dueDate,
                'is_overdue' => $dueDate !== '' && $dueDate < date('Y-m-d'),
                'pending_amount' => $pending,
                'url' => site_url('admin/accounts/labour-bills'),
            ];
        }
    }

    /** @return array<string,float> */
    private function purchasePaymentMap(): array
    {
        if (! $this->db->tableExists('purchase_bill_payments')) {
            return [];
        }

        $map = [];
        $rows = $this->db->table('purchase_bill_payments')
            ->select('source_type, source_id, COALESCE(SUM(amount), 0) AS paid_amount', false)
            ->groupBy(['source_type', 'source_id'])
            ->get()
            ->getResultArray();
        foreach ($rows as $row) {
            $map[strtolower((string) ($row['source_type'] ?? '')) . ':' . (int) ($row['source_id'] ?? 0)] = (float) ($row['paid_amount'] ?? 0);
        }
        return $map;
    }

    /** @return array<string,mixed> */
    private function emptySection(bool $withOverdue = false): array
    {
        $section = ['count' => 0, 'amount' => 0.0, 'items' => []];
        if ($withOverdue) {
            $section['overdue_count'] = 0;
        }
        return $section;
    }
}
