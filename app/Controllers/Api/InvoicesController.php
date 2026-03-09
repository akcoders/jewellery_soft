<?php

namespace App\Controllers\Api;

class InvoicesController extends ApiBaseController
{
    public function create()
    {
        $p = $this->payload();
        $customerId = (int) ($p['customer_id'] ?? 0);
        if ($customerId <= 0) {
            return $this->fail('customer_id is required.', 422);
        }

        $invoiceNo = trim((string) ($p['invoice_no'] ?? ''));
        if ($invoiceNo === '') {
            $invoiceNo = 'INV-' . date('YmdHis');
        }

        $db = db_connect();
        $packingId = (int) ($p['packing_list_id'] ?? 0);
        $items = (array) ($p['items'] ?? []);

        if ($packingId > 0 && $items === []) {
            $packItems = $db->table('packing_list_items')->where('packing_list_id', $packingId)->get()->getResultArray();
            foreach ($packItems as $pi) {
                $items[] = [
                    'fg_item_id' => (int) ($pi['fg_item_id'] ?? 0),
                    'description' => 'Tag ' . (string) ($pi['tag_no'] ?? ''),
                    'qty' => (float) ($pi['qty'] ?? 1),
                    'rate' => 0,
                    'amount' => 0,
                    'gst_percent' => 0,
                    'gst_amount' => 0,
                ];
            }
        }

        $db->transStart();
        $invoiceId = (int) $db->table('invoices')->insert([
            'invoice_no' => $invoiceNo,
            'invoice_date' => (string) ($p['invoice_date'] ?? date('Y-m-d')),
            'customer_id' => $customerId,
            'order_id' => isset($p['order_id']) ? (int) $p['order_id'] : null,
            'packing_list_id' => $packingId > 0 ? $packingId : null,
            'taxable_amount' => (float) ($p['taxable_amount'] ?? 0),
            'gst_amount' => (float) ($p['gst_amount'] ?? 0),
            'total_amount' => (float) ($p['total_amount'] ?? 0),
            'status' => (string) ($p['status'] ?? 'Unpaid'),
            'created_by' => (int) (session('admin_id') ?: 0),
        ], true);

        foreach ($items as $item) {
            $db->table('invoice_items')->insert([
                'invoice_id' => $invoiceId,
                'fg_item_id' => isset($item['fg_item_id']) ? (int) $item['fg_item_id'] : null,
                'description' => (string) ($item['description'] ?? ''),
                'qty' => (float) ($item['qty'] ?? 1),
                'rate' => (float) ($item['rate'] ?? 0),
                'amount' => (float) ($item['amount'] ?? 0),
                'gst_percent' => (float) ($item['gst_percent'] ?? 0),
                'gst_amount' => (float) ($item['gst_amount'] ?? 0),
            ]);
        }

        $db->transComplete();

        return $this->ok(['invoice_id' => $invoiceId, 'invoice_no' => $invoiceNo], 'Invoice created.', 201);
    }

    public function receipt()
    {
        $p = $this->payload();
        $customerId = (int) ($p['customer_id'] ?? 0);
        $amount = (float) ($p['amount'] ?? 0);
        if ($customerId <= 0 || $amount <= 0) {
            return $this->fail('customer_id and amount are required.', 422);
        }

        $receiptNo = trim((string) ($p['receipt_no'] ?? ''));
        if ($receiptNo === '') {
            $receiptNo = 'RCPT-' . date('YmdHis');
        }

        $id = (int) db_connect()->table('customer_receipts')->insert([
            'receipt_no' => $receiptNo,
            'receipt_date' => (string) ($p['receipt_date'] ?? date('Y-m-d')),
            'customer_id' => $customerId,
            'invoice_id' => isset($p['invoice_id']) ? (int) $p['invoice_id'] : null,
            'amount' => $amount,
            'payment_mode' => (string) ($p['payment_mode'] ?? 'Cash'),
            'reference_no' => (string) ($p['reference_no'] ?? ''),
            'notes' => (string) ($p['notes'] ?? ''),
            'created_by' => (int) (session('admin_id') ?: 0),
        ], true);

        return $this->ok(['receipt_id' => $id, 'receipt_no' => $receiptNo], 'Receipt saved.', 201);
    }
}
