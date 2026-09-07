<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;

/**
 * Read-only sales intelligence assembled from the operational bill tables.
 *
 * Purchases and labour are measured on a bill basis. Their payments are not
 * added again because that would count the same cost twice. Only posted
 * expenditure journal vouchers are treated as other operating expenses.
 */
class SalesIntelligenceService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array<string,mixed> */
    public function build(string $dateFrom = '', string $dateTo = ''): array
    {
        [$dateFrom, $dateTo] = $this->normalisePeriod($dateFrom, $dateTo);

        $sales = $this->sales($dateFrom, $dateTo);
        $purchases = $this->purchases($dateFrom, $dateTo);
        $labourBills = $this->labourBills($dateFrom, $dateTo);
        $otherExpenses = $this->otherExpenses($dateFrom, $dateTo);
        $receiptMap = $this->receiptMap(array_column($sales, 'invoice_id'));
        $customerMap = $this->customerMap(array_column($sales, 'customer_id'));

        $summary = [
            'sale_count' => 0,
            'sold_qty' => 0.0,
            'sales_total' => 0.0,
            'taxable_sales' => 0.0,
            'output_tax' => 0.0,
            'received' => 0.0,
            'outstanding' => 0.0,
            'gold_weight' => 0.0,
            'diamond_weight' => 0.0,
            'stone_weight' => 0.0,
            'purchase_count' => count($purchases),
            'purchase_total' => 0.0,
            'purchase_tax' => 0.0,
            'labour_bill_count' => count($labourBills),
            'labour_expense' => 0.0,
            'other_expense_count' => count($otherExpenses),
            'other_expense' => 0.0,
            'recorded_cost' => 0.0,
            'operating_spread' => 0.0,
            'collection_rate' => 0.0,
            'cost_to_sales_ratio' => 0.0,
        ];

        $customerRows = [];
        $paymentMix = [
            'Paid' => ['count' => 0, 'amount' => 0.0],
            'Partial' => ['count' => 0, 'amount' => 0.0],
            'Pending' => ['count' => 0, 'amount' => 0.0],
        ];
        foreach ($sales as &$sale) {
            $invoiceId = (int) ($sale['invoice_id'] ?? 0);
            $customerId = (int) ($sale['customer_id'] ?? 0);
            $total = $this->number($sale, 'total_amount');
            $received = min($total, (float) ($receiptMap[$invoiceId] ?? 0));
            $outstanding = max(0, round($total - $received, 2));
            $status = $total > 0 && $received >= $total ? 'Paid' : ($received > 0 ? 'Partial' : 'Pending');

            $sale['customer_name'] = (string) ($customerMap[$customerId] ?? 'Walk-in / Unassigned');
            $sale['received_amount'] = $received;
            $sale['outstanding_amount'] = $outstanding;
            $sale['payment_status'] = $status;

            $summary['sale_count']++;
            $summary['sold_qty'] += $this->number($sale, 'total_qty');
            $summary['sales_total'] += $total;
            $summary['taxable_sales'] += $this->number($sale, 'taxable_amount');
            $summary['output_tax'] += $this->number($sale, 'gst_amount');
            $summary['received'] += $received;
            $summary['outstanding'] += $outstanding;
            $summary['gold_weight'] += $this->number($sale, 'total_gold_weight');
            $summary['diamond_weight'] += $this->number($sale, 'total_diamond_weight');
            $summary['stone_weight'] += $this->number($sale, 'total_stone_weight');
            $paymentMix[$status]['count']++;
            $paymentMix[$status]['amount'] += $total;

            if (! isset($customerRows[$customerId])) {
                $customerRows[$customerId] = [
                    'customer_id' => $customerId,
                    'customer_name' => $sale['customer_name'],
                    'bill_count' => 0,
                    'sales_total' => 0.0,
                    'received' => 0.0,
                    'outstanding' => 0.0,
                ];
            }
            $customerRows[$customerId]['bill_count']++;
            $customerRows[$customerId]['sales_total'] += $total;
            $customerRows[$customerId]['received'] += $received;
            $customerRows[$customerId]['outstanding'] += $outstanding;
        }
        unset($sale);

        $purchaseBreakdown = [];
        foreach ($purchases as $purchase) {
            $category = (string) ($purchase['category'] ?? 'Other');
            if (! isset($purchaseBreakdown[$category])) {
                $purchaseBreakdown[$category] = ['category' => $category, 'bill_count' => 0, 'amount' => 0.0, 'tax' => 0.0];
            }
            $amount = $this->number($purchase, 'amount');
            $tax = $this->number($purchase, 'tax_amount');
            $purchaseBreakdown[$category]['bill_count']++;
            $purchaseBreakdown[$category]['amount'] += $amount;
            $purchaseBreakdown[$category]['tax'] += $tax;
            $summary['purchase_total'] += $amount;
            $summary['purchase_tax'] += $tax;
        }

        foreach ($labourBills as $bill) {
            $summary['labour_expense'] += $this->number($bill, 'total_amount');
        }

        $expenseHeads = [];
        foreach ($otherExpenses as $expense) {
            $amount = $this->number($expense, 'amount');
            $head = trim((string) ($expense['expense_head'] ?? '')) ?: 'Other expense';
            $summary['other_expense'] += $amount;
            $expenseHeads[$head] = ($expenseHeads[$head] ?? 0.0) + $amount;
        }

        $summary['recorded_cost'] = $summary['purchase_total'] + $summary['labour_expense'] + $summary['other_expense'];
        $summary['operating_spread'] = $summary['sales_total'] - $summary['recorded_cost'];
        $summary['collection_rate'] = $summary['sales_total'] > 0 ? ($summary['received'] / $summary['sales_total']) * 100 : 0.0;
        $summary['cost_to_sales_ratio'] = $summary['sales_total'] > 0 ? ($summary['recorded_cost'] / $summary['sales_total']) * 100 : 0.0;
        foreach ($summary as $key => &$value) {
            if (! is_float($value)) {
                continue;
            }
            $value = round($value, in_array($key, ['sold_qty', 'gold_weight', 'diamond_weight', 'stone_weight'], true) ? 3 : 2);
        }
        unset($value);

        $monthly = $this->monthBuckets($dateFrom, $dateTo);
        foreach ($sales as $sale) {
            $this->addToMonth($monthly, (string) ($sale['sale_date'] ?? ''), 'sales', $this->number($sale, 'total_amount'));
        }
        foreach ($purchases as $purchase) {
            $this->addToMonth($monthly, (string) ($purchase['entry_date'] ?? ''), 'purchases', $this->number($purchase, 'amount'));
        }
        foreach ($labourBills as $bill) {
            $this->addToMonth($monthly, (string) ($bill['bill_date'] ?? ''), 'expenses', $this->number($bill, 'total_amount'));
        }
        foreach ($otherExpenses as $expense) {
            $this->addToMonth($monthly, (string) ($expense['voucher_date'] ?? ''), 'expenses', $this->number($expense, 'amount'));
        }
        foreach ($monthly as &$month) {
            $month['cost'] = round($month['purchases'] + $month['expenses'], 2);
            $month['spread'] = round($month['sales'] - $month['cost'], 2);
        }
        unset($month);

        $customerRows = array_values($customerRows);
        usort($customerRows, static fn (array $a, array $b): int => $b['sales_total'] <=> $a['sales_total']);
        uasort($purchaseBreakdown, static fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);
        arsort($expenseHeads);

        return [
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo],
            'summary' => $summary,
            'monthly' => array_values($monthly),
            'payment_mix' => $paymentMix,
            'purchase_breakdown' => array_values($purchaseBreakdown),
            'expense_heads' => array_slice($expenseHeads, 0, 6, true),
            'top_customers' => array_slice($customerRows, 0, 8),
            'recent_sales' => array_slice($sales, 0, 8),
            'insights' => $this->insights($summary, $customerRows, $purchaseBreakdown),
            'method_note' => 'Purchase and labour values are bill-based; posted expenditure vouchers are included once. This is an operating comparison, not inventory COGS or audited profit.',
        ];
    }

    /** @return list<array<string,mixed>> */
    private function sales(string $dateFrom, string $dateTo): array
    {
        if (! $this->db->tableExists('showroom_sales')) {
            return [];
        }
        $rows = $this->db->table('showroom_sales')
            ->where('sale_date >=', $dateFrom)
            ->where('sale_date <=', $dateTo)
            ->orderBy('sale_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->get()->getResultArray();

        return array_values(array_filter($rows, static function (array $row): bool {
            return ! in_array(strtolower(trim((string) ($row['sale_status'] ?? ''))), ['cancelled', 'canceled', 'void'], true);
        }));
    }

    /** @return list<array<string,mixed>> */
    private function purchases(string $dateFrom, string $dateTo): array
    {
        $rows = [];
        $rows = array_merge($rows, $this->headerPurchases(
            'gold_inventory_purchase_headers', 'gold_inventory_purchase_lines', 'purchase_id', 'line_value',
            'purchase_date', 'Gold', $dateFrom, $dateTo
        ));
        $rows = array_merge($rows, $this->headerPurchases(
            'purchase_headers', 'purchase_lines', 'purchase_id', 'line_value',
            'purchase_date', 'Diamond', $dateFrom, $dateTo
        ));

        if ($this->db->tableExists('stone_inventory_purchase_headers')) {
            $rows = array_merge($rows, $this->headerPurchases(
                'stone_inventory_purchase_headers', 'stone_inventory_purchase_lines', 'purchase_id', 'line_value',
                'purchase_date', 'Stone', $dateFrom, $dateTo
            ));
        } elseif ($this->db->tableExists('purchases')) {
            $builder = $this->db->table('purchases')
                ->where('purchase_date >=', $dateFrom)
                ->where('purchase_date <=', $dateTo);
            if ($this->db->fieldExists('purchase_type', 'purchases')) {
                $builder->where('purchase_type', 'Stone');
            }
            foreach ($builder->get()->getResultArray() as $row) {
                $rows[] = [
                    'source_type' => 'stone',
                    'source_id' => (int) ($row['id'] ?? 0),
                    'entry_date' => (string) ($row['purchase_date'] ?? ''),
                    'category' => 'Stone',
                    'amount' => $this->number($row, 'invoice_amount'),
                    'tax_amount' => 0.0,
                ];
            }
        }

        // Preserve the same supplementary source used by the Accounts purchase
        // register, while excluding gold and documents already linked to a
        // structured diamond purchase header.
        if ($this->db->tableExists('production_purchase_documents')
            && $this->db->fieldExists('invoice_amount', 'production_purchase_documents')) {
            $linked = [];
            if ($this->db->tableExists('purchase_headers')
                && $this->db->fieldExists('production_document_id', 'purchase_headers')) {
                foreach ($this->db->table('purchase_headers')->select('production_document_id')->get()->getResultArray() as $header) {
                    $id = (int) ($header['production_document_id'] ?? 0);
                    if ($id > 0) {
                        $linked[$id] = true;
                    }
                }
            }
            $documents = $this->db->table('production_purchase_documents')
                ->where('document_date >=', $dateFrom)
                ->where('document_date <=', $dateTo)
                ->get()->getResultArray();
            foreach ($documents as $document) {
                $id = (int) ($document['id'] ?? 0);
                $category = strtolower(trim((string) ($document['category'] ?? 'purchase')));
                if ($category === 'gold' || isset($linked[$id])) {
                    continue;
                }
                $rows[] = [
                    'source_type' => 'production_document',
                    'source_id' => $id,
                    'entry_date' => (string) ($document['document_date'] ?? ''),
                    'category' => $category === '' ? 'Other' : ucfirst(str_replace(['_', '-'], ' ', $category)),
                    'amount' => $this->number($document, 'invoice_amount'),
                    'tax_amount' => $this->number($document, 'gst_amount'),
                ];
            }
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $b['entry_date'], (string) $a['entry_date']));
        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function headerPurchases(
        string $headerTable,
        string $lineTable,
        string $lineForeignKey,
        string $lineAmountField,
        string $dateField,
        string $category,
        string $dateFrom,
        string $dateTo
    ): array {
        if (! $this->db->tableExists($headerTable)) {
            return [];
        }
        $headers = $this->db->table($headerTable)
            ->where($dateField . ' >=', $dateFrom)
            ->where($dateField . ' <=', $dateTo)
            ->get()->getResultArray();
        if ($headers === []) {
            return [];
        }

        $lineTotals = [];
        if ($this->db->tableExists($lineTable) && $this->db->fieldExists($lineAmountField, $lineTable)) {
            $ids = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $headers);
            foreach ($this->db->table($lineTable)
                ->select($lineForeignKey . ', SUM(' . $lineAmountField . ') AS line_total', false)
                ->whereIn($lineForeignKey, $ids)
                ->groupBy($lineForeignKey)
                ->get()->getResultArray() as $line) {
                $lineTotals[(int) ($line[$lineForeignKey] ?? 0)] = (float) ($line['line_total'] ?? 0);
            }
        }

        $rows = [];
        foreach ($headers as $header) {
            $id = (int) ($header['id'] ?? 0);
            $amount = $this->number($header, 'invoice_total');
            if ($amount <= 0) {
                $amount = $this->number($header, 'taxable_amount')
                    + $this->number($header, 'gst_amount')
                    + $this->number($header, 'round_off_amount');
            }
            if ($amount <= 0) {
                $amount = (float) ($lineTotals[$id] ?? 0);
            }
            $rows[] = [
                'source_type' => strtolower($category),
                'source_id' => $id,
                'entry_date' => (string) ($header[$dateField] ?? ''),
                'category' => $category,
                'amount' => round($amount, 2),
                'tax_amount' => $this->number($header, 'gst_amount'),
            ];
        }
        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function labourBills(string $dateFrom, string $dateTo): array
    {
        if (! $this->db->tableExists('labour_bills')) {
            return [];
        }
        return $this->db->table('labour_bills')
            ->where('bill_date >=', $dateFrom)
            ->where('bill_date <=', $dateTo)
            ->orderBy('bill_date', 'DESC')
            ->get()->getResultArray();
    }

    /** @return list<array<string,mixed>> */
    private function otherExpenses(string $dateFrom, string $dateTo): array
    {
        if (! $this->db->tableExists('account_journal_vouchers')) {
            return [];
        }
        return $this->db->table('account_journal_vouchers')
            ->where('voucher_type', 'expenditure')
            ->where('status', 'Posted')
            ->where('voucher_date >=', $dateFrom)
            ->where('voucher_date <=', $dateTo)
            ->orderBy('voucher_date', 'DESC')
            ->get()->getResultArray();
    }

    /** @param list<mixed> $invoiceIds @return array<int,float> */
    private function receiptMap(array $invoiceIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $invoiceIds))));
        if ($ids === [] || ! $this->db->tableExists('customer_receipts')) {
            return [];
        }
        $map = [];
        foreach ($this->db->table('customer_receipts')->select('invoice_id, amount')->whereIn('invoice_id', $ids)->get()->getResultArray() as $receipt) {
            $id = (int) ($receipt['invoice_id'] ?? 0);
            $map[$id] = ($map[$id] ?? 0.0) + $this->number($receipt, 'amount');
        }
        return $map;
    }

    /** @param list<mixed> $customerIds @return array<int,string> */
    private function customerMap(array $customerIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $customerIds))));
        if ($ids === [] || ! $this->db->tableExists('customers')) {
            return [];
        }
        $map = [];
        foreach ($this->db->table('customers')->select('id, name')->whereIn('id', $ids)->get()->getResultArray() as $customer) {
            $map[(int) ($customer['id'] ?? 0)] = (string) ($customer['name'] ?? '');
        }
        return $map;
    }

    /** @return array<string,array<string,float|string>> */
    private function monthBuckets(string $dateFrom, string $dateTo): array
    {
        $cursor = (new DateTimeImmutable($dateFrom))->modify('first day of this month');
        $end = (new DateTimeImmutable($dateTo))->modify('first day of this month');
        $lastTwentyFourMonths = $end->modify('-23 months');
        if ($cursor < $lastTwentyFourMonths) {
            $cursor = $lastTwentyFourMonths;
        }
        $months = [];
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m');
            $months[$key] = [
                'key' => $key,
                'label' => $cursor->format('M Y'),
                'sales' => 0.0,
                'purchases' => 0.0,
                'expenses' => 0.0,
                'cost' => 0.0,
                'spread' => 0.0,
            ];
            $cursor = $cursor->modify('+1 month');
        }
        return $months;
    }

    /** @param array<string,array<string,float|string>> $months */
    private function addToMonth(array &$months, string $date, string $field, float $amount): void
    {
        $key = substr($date, 0, 7);
        if (isset($months[$key])) {
            $months[$key][$field] = round((float) $months[$key][$field] + $amount, 2);
        }
    }

    /** @param array<string,float|int> $summary @param list<array<string,mixed>> $customers @param array<string,array<string,mixed>> $purchases */
    private function insights(array $summary, array $customers, array $purchases): array
    {
        $insights = [];
        if ((int) $summary['sale_count'] === 0) {
            return [[
                'tone' => 'neutral',
                'title' => 'No sales in this period',
                'message' => 'Change the date range or create the first studded jewellery sale bill.',
            ]];
        }

        $outstandingRatio = (float) $summary['sales_total'] > 0
            ? ((float) $summary['outstanding'] / (float) $summary['sales_total']) * 100
            : 0.0;
        if ($outstandingRatio >= 30) {
            $insights[] = [
                'tone' => 'warning',
                'title' => 'Collection attention needed',
                'message' => number_format($outstandingRatio, 1) . '% of selected sales is still outstanding.',
            ];
        } else {
            $insights[] = [
                'tone' => 'success',
                'title' => 'Collection position',
                'message' => number_format((float) $summary['collection_rate'], 1) . '% of selected invoice value has been collected.',
            ];
        }

        $insights[] = (float) $summary['operating_spread'] >= 0 ? [
            'tone' => 'success',
            'title' => 'Positive operating spread',
            'message' => 'Sales are ahead of recorded purchase and expense bills by ₹' . number_format((float) $summary['operating_spread'], 2) . '.',
        ] : [
            'tone' => 'danger',
            'title' => 'Recorded cost is above sales',
            'message' => 'Purchases and expenses exceed sales by ₹' . number_format(abs((float) $summary['operating_spread']), 2) . ' for this period.',
        ];

        if ($customers !== [] && (float) $summary['sales_total'] > 0) {
            $share = ((float) $customers[0]['sales_total'] / (float) $summary['sales_total']) * 100;
            $insights[] = [
                'tone' => $share >= 60 ? 'warning' : 'neutral',
                'title' => 'Customer concentration',
                'message' => (string) $customers[0]['customer_name'] . ' contributes ' . number_format($share, 1) . '% of selected sales.',
            ];
        }

        if ($purchases === []) {
            $insights[] = [
                'tone' => 'neutral',
                'title' => 'No purchase bills found',
                'message' => 'The cost comparison currently contains labour and posted expenditure only.',
            ];
        }
        return $insights;
    }

    /** @return array{0:string,1:string} */
    private function normalisePeriod(string $dateFrom, string $dateTo): array
    {
        $today = date('Y-m-d');
        $month = (int) date('n');
        $financialYear = $month >= 4 ? (int) date('Y') : ((int) date('Y') - 1);
        $dateFrom = $this->validDate($dateFrom) ? $dateFrom : $financialYear . '-04-01';
        $dateTo = $this->validDate($dateTo) ? $dateTo : $today;
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }
        return [$dateFrom, $dateTo];
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', trim($date));
        return $parsed !== false && $parsed->format('Y-m-d') === trim($date);
    }

    /** @param array<string,mixed> $row */
    private function number(array $row, string $field): float
    {
        return (float) ($row[$field] ?? 0);
    }
}
