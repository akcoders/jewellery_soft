<?php

namespace App\Controllers\Api;

use App\Services\PostingService;
use RuntimeException;

class PurchasesController extends ApiBaseController
{
    public function grn()
    {
        $payload = $this->payload();
        $vendorId = (int) ($payload['vendor_id'] ?? 0);
        $warehouseId = (int) ($payload['warehouse_id'] ?? 0);
        $binId = (int) ($payload['bin_id'] ?? 0);
        $items = (array) ($payload['items'] ?? []);

        if ($vendorId <= 0 || $warehouseId <= 0 || $items === []) {
            return $this->fail('vendor_id, warehouse_id and items are required.', 422);
        }

        $db = db_connect();
        $grnNo = trim((string) ($payload['grn_no'] ?? ''));
        if ($grnNo === '') {
            $grnNo = 'GRN-' . date('YmdHis');
        }

        $db->transStart();
        $grnId = (int) $db->table('grns')->insert([
            'grn_no' => $grnNo,
            'grn_date' => (string) ($payload['grn_date'] ?? date('Y-m-d')),
            'vendor_id' => $vendorId,
            'warehouse_id' => $warehouseId,
            'bin_id' => $binId > 0 ? $binId : null,
            'status' => 'Posted',
            'notes' => (string) ($payload['notes'] ?? ''),
            'created_by' => (int) (session('admin_id') ?: 0),
        ], true);

        $postingLines = [];
        $lineNo = 0;
        foreach ($items as $item) {
            $lineNo++;
            $line = [
                'item_type' => strtoupper((string) ($item['item_type'] ?? 'GOLD')),
                'item_key' => (string) ($item['item_key'] ?? ''),
                'material_name' => (string) ($item['material_name'] ?? ''),
                'bag_id' => isset($item['bag_id']) ? (int) $item['bag_id'] : null,
                'gold_purity_id' => isset($item['gold_purity_id']) ? (int) $item['gold_purity_id'] : null,
                'shape' => $item['shape'] ?? null,
                'chalni_size' => $item['chalni_size'] ?? null,
                'color' => $item['color'] ?? null,
                'clarity' => $item['clarity'] ?? null,
                'stone_type' => $item['stone_type'] ?? null,
                'qty_pcs' => (float) ($item['qty_pcs'] ?? 0),
                'qty_cts' => (float) ($item['qty_cts'] ?? 0),
                'qty_weight' => (float) ($item['qty_weight'] ?? 0),
                'fine_gold' => (float) ($item['fine_gold'] ?? 0),
                'rate' => (float) ($item['rate'] ?? 0),
                'amount' => (float) ($item['amount'] ?? 0),
                'remarks' => (string) ($item['remarks'] ?? ''),
            ];

            $db->table('grn_items')->insert([
                'grn_id' => $grnId,
                'item_type' => $line['item_type'],
                'item_key' => $line['item_key'],
                'material_name' => $line['material_name'],
                'qty_pcs' => $line['qty_pcs'],
                'qty_cts' => $line['qty_cts'],
                'qty_weight' => $line['qty_weight'],
                'rate' => $line['rate'],
                'amount' => $line['amount'],
            ]);

            $postingLines[] = $line;
        }

        $posting = new PostingService($db);
        $warehouseAccountId = $posting->ensureAccount('WAREHOUSE', 'WH-' . $warehouseId, 'Warehouse #' . $warehouseId, 'warehouses', $warehouseId);
        $vendorAccountId = $posting->ensureAccount('VENDOR', 'VENDOR-' . $vendorId, 'Vendor #' . $vendorId, 'parties', $vendorId);

        $voucher = $posting->postVoucher([
            'voucher_type' => 'GRN',
            'voucher_date' => (string) ($payload['grn_date'] ?? date('Y-m-d')),
            'to_warehouse_id' => $warehouseId,
            'to_bin_id' => $binId > 0 ? $binId : null,
            'party_id' => $vendorId,
            'debit_account_id' => $warehouseAccountId,
            'credit_account_id' => $vendorAccountId,
            'remarks' => 'GRN ' . $grnNo,
            'created_by' => (int) (session('admin_id') ?: 0),
        ], $postingLines);

        $db->transComplete();

        return $this->ok(['grn_id' => $grnId, 'grn_no' => $grnNo, 'voucher' => $voucher], 'GRN posted.', 201);
    }

    public function invoice()
    {
        $payload = $this->payload();
        $vendorId = (int) ($payload['vendor_id'] ?? 0);
        if ($vendorId <= 0) {
            return $this->fail('vendor_id is required.', 422);
        }

        $invoiceNo = trim((string) ($payload['invoice_no'] ?? ''));
        if ($invoiceNo === '') {
            $invoiceNo = 'PINV-' . date('YmdHis');
        }

        $id = (int) db_connect()->table('purchase_invoices')->insert([
            'invoice_no' => $invoiceNo,
            'invoice_date' => (string) ($payload['invoice_date'] ?? date('Y-m-d')),
            'vendor_id' => $vendorId,
            'grn_id' => isset($payload['grn_id']) ? (int) $payload['grn_id'] : null,
            'taxable_amount' => (float) ($payload['taxable_amount'] ?? 0),
            'gst_amount' => (float) ($payload['gst_amount'] ?? 0),
            'total_amount' => (float) ($payload['total_amount'] ?? 0),
            'payment_due_date' => $payload['payment_due_date'] ?? null,
            'status' => (string) ($payload['status'] ?? 'Pending'),
            'created_by' => (int) (session('admin_id') ?: 0),
        ], true);

        return $this->ok(['purchase_invoice_id' => $id, 'invoice_no' => $invoiceNo], 'Purchase invoice created.', 201);
    }

    public function vendorPayment()
    {
        $payload = $this->payload();
        $vendorId = (int) ($payload['vendor_id'] ?? 0);
        $amount = (float) ($payload['amount'] ?? 0);
        if ($vendorId <= 0 || $amount <= 0) {
            return $this->fail('vendor_id and amount are required.', 422);
        }

        $paymentNo = trim((string) ($payload['payment_no'] ?? ''));
        if ($paymentNo === '') {
            $paymentNo = 'VPAY-' . date('YmdHis');
        }

        $id = (int) db_connect()->table('vendor_payments')->insert([
            'payment_no' => $paymentNo,
            'payment_date' => (string) ($payload['payment_date'] ?? date('Y-m-d')),
            'vendor_id' => $vendorId,
            'purchase_invoice_id' => isset($payload['purchase_invoice_id']) ? (int) $payload['purchase_invoice_id'] : null,
            'amount' => $amount,
            'payment_mode' => (string) ($payload['payment_mode'] ?? 'Cash'),
            'reference_no' => (string) ($payload['reference_no'] ?? ''),
            'notes' => (string) ($payload['notes'] ?? ''),
            'created_by' => (int) (session('admin_id') ?: 0),
        ], true);

        return $this->ok(['vendor_payment_id' => $id, 'payment_no' => $paymentNo], 'Vendor payment saved.', 201);
    }
}
