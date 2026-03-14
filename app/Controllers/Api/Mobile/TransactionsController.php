<?php

namespace App\Controllers\Api\Mobile;

use App\Models\CompanySettingModel;
use App\Models\InventoryLocationModel;
use App\Models\IssueHeaderModel;
use App\Models\IssueLineModel;
use App\Models\ItemModel;
use App\Models\KarigarModel;
use App\Models\PurchaseHeaderModel;
use App\Models\PurchaseLineModel;
use App\Models\ReturnHeaderModel;
use App\Models\ReturnLineModel;
use App\Models\VendorModel;
use App\Models\GoldInventoryIssueHeaderModel;
use App\Models\GoldInventoryIssueLineModel;
use App\Models\GoldInventoryPurchaseHeaderModel;
use App\Models\GoldInventoryPurchaseLineModel;
use App\Models\GoldInventoryReturnHeaderModel;
use App\Models\GoldInventoryReturnLineModel;
use App\Models\GoldInventoryItemModel;
use App\Models\StoneInventoryIssueHeaderModel;
use App\Models\StoneInventoryIssueLineModel;
use App\Models\StoneInventoryPurchaseHeaderModel;
use App\Models\StoneInventoryPurchaseLineModel;
use App\Models\StoneInventoryReturnHeaderModel;
use App\Models\StoneInventoryReturnLineModel;
use App\Models\StoneInventoryItemModel;
use App\Services\DiamondInventory\StockService as DiamondStockService;
use App\Services\GoldInventory\StockService as GoldStockService;
use App\Services\StoneInventory\StockService as StoneStockService;
use App\Services\PdfService;
use Throwable;

class TransactionsController extends MobileBaseController
{
    private CompanySettingModel $companySettingModel;
    private ItemModel $diamondItemModel;
    private GoldInventoryItemModel $goldItemModel;
    private StoneInventoryItemModel $stoneItemModel;

    public function __construct()
    {
        helper(['url']);
        $this->companySettingModel = new CompanySettingModel();
        $this->diamondItemModel = new ItemModel();
        $this->goldItemModel = new GoldInventoryItemModel();
        $this->stoneItemModel = new StoneInventoryItemModel();
    }

    public function createDiamondPurchase()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $payload = $this->payload();
        $purchaseDate = trim((string) ($payload['purchase_date'] ?? ''));
        if ($purchaseDate === '' || strtotime($purchaseDate) === false) {
            return $this->fail('Purchase date is required.', 422);
        }

        $linesPayload = $payload['lines'] ?? [];
        $parsed = $this->parseDiamondLines($linesPayload);
        if ($parsed['error'] !== null) {
            return $this->fail($parsed['error'], 422);
        }
        if ($parsed['lines'] === []) {
            return $this->fail('At least one purchase line is required.', 422);
        }

        $vendorId = (int) ($payload['vendor_id'] ?? 0);
        $supplierName = trim((string) ($payload['supplier_name'] ?? ''));
        if ($vendorId > 0 && $supplierName === '') {
            $vendor = (new VendorModel())->find($vendorId);
            $supplierName = $vendor ? (string) ($vendor['name'] ?? '') : '';
        }

        $taxPercent = (float) ($payload['tax_percentage'] ?? 0);
        $invoiceTotal = (float) ($payload['invoice_total'] ?? 0);
        if ($invoiceTotal <= 0) {
            $sumValue = 0.0;
            foreach ($parsed['lines'] as $line) {
                $sumValue += (float) ($line['line_value'] ?? 0);
            }
            $invoiceTotal = $sumValue + ($sumValue * $taxPercent / 100);
        }

        $db = db_connect();
        $service = new DiamondStockService($db);

        try {
            $db->transException(true)->transStart();

            $headerId = (int) (new PurchaseHeaderModel())->insert([
                'purchase_date' => $purchaseDate,
                'vendor_id' => $vendorId > 0 ? $vendorId : null,
                'supplier_name' => $supplierName !== '' ? $supplierName : null,
                'invoice_no' => trim((string) ($payload['invoice_no'] ?? '')) ?: null,
                'due_date' => trim((string) ($payload['due_date'] ?? '')) ?: null,
                'tax_percentage' => $taxPercent,
                'invoice_total' => $invoiceTotal,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
            ], true);

            $lineModel = new PurchaseLineModel();
            foreach ($parsed['lines'] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                if ($itemId <= 0) {
                    $itemId = $service->upsertItemFromSignature((array) ($line['signature'] ?? []));
                }

                $lineModel->insert([
                    'purchase_id' => $headerId,
                    'item_id' => $itemId,
                    'pcs' => $line['pcs'],
                    'carat' => $line['carat'],
                    'rate_per_carat' => $line['rate_per_carat'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyPurchase($headerId);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return $this->fail('Unable to save purchase: ' . $e->getMessage(), 500);
        }

        return $this->ok(['id' => $headerId], 'Diamond purchase saved.');
    }

    public function createDiamondIssue()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $payload = $this->payload();
        $issueDate = trim((string) ($payload['issue_date'] ?? ''));
        $orderId = (int) ($payload['order_id'] ?? 0);
        $karigarId = (int) ($payload['karigar_id'] ?? 0);
        $locationId = (int) ($payload['location_id'] ?? 0);
        $purpose = trim((string) ($payload['purpose'] ?? ''));

        if ($issueDate === '' || strtotime($issueDate) === false) {
            return $this->fail('Issue date is required.', 422);
        }
        if ($orderId <= 0 || $karigarId <= 0 || $locationId <= 0) {
            return $this->fail('Order, karigar and location are required.', 422);
        }
        if ($purpose === '') {
            return $this->fail('Purpose is required.', 422);
        }

        $order = db_connect()->table('orders')
            ->select('id, assigned_karigar_id, status')
            ->where('id', $orderId)
            ->whereNotIn('status', ['Cancelled', 'Completed'])
            ->get()
            ->getRowArray();
        if (! $order || (int) ($order['assigned_karigar_id'] ?? 0) <= 0) {
            return $this->fail('Only karigar-assigned active orders are allowed for issuance.', 422);
        }
        if ((int) ($order['assigned_karigar_id'] ?? 0) !== $karigarId) {
            return $this->fail('Selected karigar does not match order assignment.', 422);
        }

        $karigar = (new KarigarModel())->where('id', $karigarId)->where('is_active', 1)->first();
        if (! $karigar) {
            return $this->fail('Selected karigar not found.', 422);
        }

        $location = (new InventoryLocationModel())->where('id', $locationId)->where('is_active', 1)->first();
        if (! $location) {
            return $this->fail('Selected warehouse not found.', 422);
        }

        $parsed = $this->parseDiamondLines($payload['lines'] ?? []);
        if ($parsed['error'] !== null) {
            return $this->fail($parsed['error'], 422);
        }
        if ($parsed['lines'] === []) {
            return $this->fail('At least one issue line is required.', 422);
        }

        $attachment = $this->saveBase64Attachment(
            (string) ($payload['attachment_base64'] ?? ''),
            FCPATH . 'uploads/issuements/diamond',
            true,
            'uploads/issuements/diamond'
        );
        if (! $attachment['ok']) {
            return $this->fail((string) $attachment['message'], 422);
        }

        $db = db_connect();
        $service = new DiamondStockService($db);

        try {
            $db->transException(true)->transStart();

            $issueId = (int) (new IssueHeaderModel())->insert([
                'voucher_no' => $this->generateIssueVoucherNo($db),
                'issue_date' => $issueDate,
                'order_id' => $orderId,
                'karigar_id' => $karigarId,
                'location_id' => $locationId,
                'issue_to' => (string) ($karigar['name'] ?? ''),
                'purpose' => $purpose,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                'attachment_name' => $attachment['name'],
                'attachment_path' => $attachment['path'],
                'created_by' => (int) ($this->mobileAdmin['id'] ?? 0),
            ], true);

            $lineModel = new IssueLineModel();
            foreach ($parsed['lines'] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                if ($itemId <= 0) {
                    $itemId = $service->upsertItemFromSignature((array) ($line['signature'] ?? []));
                }

                $lineModel->insert([
                    'issue_id' => $issueId,
                    'item_id' => $itemId,
                    'pcs' => $line['pcs'],
                    'carat' => $line['carat'],
                    'rate_per_carat' => $line['rate_per_carat'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyIssue($issueId);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return $this->fail('Unable to save issue: ' . $e->getMessage(), 500);
        }

        return $this->ok(['id' => $issueId], 'Diamond issue saved.');
    }

    public function createDiamondReturn()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $payload = $this->payload();
        $returnDate = trim((string) ($payload['return_date'] ?? ''));
        $orderId = (int) ($payload['order_id'] ?? 0);
        $issueId = (int) ($payload['issue_id'] ?? 0);
        $karigarId = (int) ($payload['karigar_id'] ?? 0);
        $purpose = trim((string) ($payload['purpose'] ?? ''));

        if ($returnDate === '' || strtotime($returnDate) === false) {
            return $this->fail('Return date is required.', 422);
        }
        if ($orderId <= 0 || $issueId <= 0) {
            return $this->fail('Order and issue reference are required.', 422);
        }

        $issue = db_connect()->table('issue_headers')
            ->select('id, order_id, karigar_id, issue_to')
            ->where('id', $issueId)
            ->where('order_id', $orderId)
            ->get()
            ->getRowArray();
        if (! $issue) {
            return $this->fail('Selected issue reference not found for this order.', 422);
        }

        $parsed = $this->parseDiamondLines($payload['lines'] ?? []);
        if ($parsed['error'] !== null) {
            return $this->fail($parsed['error'], 422);
        }
        if ($parsed['lines'] === []) {
            return $this->fail('At least one return line is required.', 422);
        }

        $attachment = $this->saveBase64Attachment(
            (string) ($payload['attachment_base64'] ?? ''),
            FCPATH . 'uploads/returns/diamond',
            true,
            'uploads/returns/diamond'
        );
        if (! $attachment['ok']) {
            return $this->fail((string) $attachment['message'], 422);
        }

        $returnFrom = trim((string) ($payload['return_from'] ?? ''));
        if ($returnFrom === '') {
            $returnFrom = (string) ($issue['issue_to'] ?? '');
        }

        $db = db_connect();
        $service = new DiamondStockService($db);

        try {
            $db->transException(true)->transStart();

            $returnId = (int) (new ReturnHeaderModel())->insert([
                'voucher_no' => $this->generateReturnVoucherNo($db, 'return_headers'),
                'return_date' => $returnDate,
                'order_id' => $orderId,
                'issue_id' => $issueId,
                'karigar_id' => $karigarId > 0 ? $karigarId : (int) ($issue['karigar_id'] ?? 0),
                'return_from' => $returnFrom !== '' ? $returnFrom : null,
                'purpose' => $purpose !== '' ? $purpose : null,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                'attachment_name' => $attachment['name'],
                'attachment_path' => $attachment['path'],
                'created_by' => (int) ($this->mobileAdmin['id'] ?? 0),
            ], true);

            $lineModel = new ReturnLineModel();
            foreach ($parsed['lines'] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                if ($itemId <= 0) {
                    $itemId = $service->upsertItemFromSignature((array) ($line['signature'] ?? []));
                }

                $lineModel->insert([
                    'return_id' => $returnId,
                    'item_id' => $itemId,
                    'pcs' => $line['pcs'],
                    'carat' => $line['carat'],
                    'rate_per_carat' => $line['rate_per_carat'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyReturn($returnId);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return $this->fail('Unable to save return: ' . $e->getMessage(), 500);
        }

        return $this->ok(['id' => $returnId], 'Diamond return saved.');
    }

    public function diamondIssueDetail(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $issue = db_connect()->table('issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->where('ih.id', $id)
            ->get()
            ->getRowArray();
        if (! $issue) {
            return $this->fail('Issue not found.', 404);
        }

        $lines = $this->diamondLineRows('issue_lines', 'issue_id', $id);
        $totals = $this->diamondLineTotals('issue_lines', 'issue_id', $id);

        return $this->ok([
            'issue' => $issue,
            'lines' => $lines,
            'totals' => $totals,
            'document_path' => '',
            'document_url' => base_url('api/documents/mobile/diamond/issues/' . $id),
        ]);
    }

    public function diamondReturnDetail(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $return = db_connect()->table('return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, ih.issue_date, k.name as karigar_name')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->where('rh.id', $id)
            ->get()
            ->getRowArray();
        if (! $return) {
            return $this->fail('Return not found.', 404);
        }

        $lines = $this->diamondLineRows('return_lines', 'return_id', $id);
        $totals = $this->diamondLineTotals('return_lines', 'return_id', $id);
        $issueLines = $this->diamondLineRows('issue_lines', 'issue_id', (int) ($return['issue_id'] ?? 0));

        return $this->ok([
            'return' => $return,
            'lines' => $lines,
            'totals' => $totals,
            'issue_lines' => $issueLines,
            'document_path' => '',
            'document_url' => base_url('api/documents/mobile/diamond/returns/' . $id),
        ]);
    }

    public function diamondPurchaseDetail(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $purchase = db_connect()->table('purchase_headers ph')
            ->select('ph.*, v.name as vendor_name')
            ->join('vendors v', 'v.id = ph.vendor_id', 'left')
            ->where('ph.id', $id)
            ->get()
            ->getRowArray();
        if (! $purchase) {
            return $this->fail('Purchase not found.', 404);
        }

        $lines = $this->diamondLineRows('purchase_lines', 'purchase_id', $id);
        $totals = $this->diamondLineTotals('purchase_lines', 'purchase_id', $id);

        return $this->ok([
            'purchase' => $purchase,
            'lines' => $lines,
            'totals' => $totals,
        ]);
    }

    public function diamondIssuePdf(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $issue = db_connect()->table('issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name, k.name as labour_name, k.phone as labour_phone, k.email as labour_email, k.address as labour_address, k.city as labour_city, k.state as labour_state, k.pincode as labour_pincode')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->where('ih.id', $id)
            ->get()
            ->getRowArray();
        if (! $issue) {
            return $this->fail('Issue not found.', 404);
        }

        $pdf = (new PdfService())->render('pdf/mobile_issue_voucher', [
            'title' => 'Diamond Issuement Voucher',
            'materialType' => 'Diamond',
            'issue' => $issue,
            'lines' => $this->diamondLineRows('issue_lines', 'issue_id', $id),
            'totals' => $this->diamondLineTotals('issue_lines', 'issue_id', $id),
            'company' => $this->companySetting(),
        ]);

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="diamond_issue_' . $id . '.pdf"')
            ->setBody($pdf);
    }

    public function diamondReturnPdf(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $return = db_connect()->table('return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, ih.issue_date, ih.issue_to, iloc.name as warehouse_name, k.name as karigar_name, k.phone as karigar_phone, k.email as karigar_email, k.address as karigar_address, k.city as karigar_city, k.state as karigar_state, k.pincode as karigar_pincode')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->where('rh.id', $id)
            ->get()
            ->getRowArray();
        if (! $return) {
            return $this->fail('Return not found.', 404);
        }

        $pdf = (new PdfService())->render('pdf/mobile_return_receipt', [
            'title' => 'Diamond Return Receipt',
            'materialType' => 'Diamond',
            'return' => $return,
            'lines' => $this->diamondLineRows('return_lines', 'return_id', $id),
            'totals' => $this->diamondLineTotals('return_lines', 'return_id', $id),
            'company' => $this->companySetting(),
        ]);

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="diamond_return_' . $id . '.pdf"')
            ->setBody($pdf);
    }

    public function createGoldPurchase()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $payload = $this->payload();
        $purchaseDate = trim((string) ($payload['purchase_date'] ?? ''));
        if ($purchaseDate === '' || strtotime($purchaseDate) === false) {
            return $this->fail('Purchase date is required.', 422);
        }

        $linesPayload = $payload['lines'] ?? [];
        $parsed = $this->parseGoldLines($linesPayload);
        if ($parsed['error'] !== null) {
            return $this->fail($parsed['error'], 422);
        }
        if ($parsed['lines'] === []) {
            return $this->fail('At least one purchase line is required.', 422);
        }

        $vendorId = (int) ($payload['vendor_id'] ?? 0);
        $supplierName = trim((string) ($payload['supplier_name'] ?? ''));
        if ($vendorId > 0 && $supplierName === '') {
            $vendor = (new VendorModel())->find($vendorId);
            $supplierName = $vendor ? (string) ($vendor['name'] ?? '') : '';
        }

        $db = db_connect();
        $service = new GoldStockService($db);

        try {
            $db->transException(true)->transStart();

            $headerId = (int) (new GoldInventoryPurchaseHeaderModel())->insert([
                'purchase_date' => $purchaseDate,
                'vendor_id' => $vendorId > 0 ? $vendorId : null,
                'supplier_name' => $supplierName !== '' ? $supplierName : null,
                'invoice_no' => trim((string) ($payload['invoice_no'] ?? '')) ?: null,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
            ], true);

            $lineModel = new GoldInventoryPurchaseLineModel();
            foreach ($parsed['lines'] as $line) {
                $lineModel->insert([
                    'purchase_id' => $headerId,
                    'item_id' => $line['item_id'],
                    'weight_gm' => $line['weight_gm'],
                    'fine_weight_gm' => $line['fine_weight_gm'],
                    'rate_per_gm' => $line['rate_per_gm'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyPurchase($headerId);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return $this->fail('Unable to save purchase: ' . $e->getMessage(), 500);
        }

        return $this->ok(['id' => $headerId], 'Gold purchase saved.');
    }

    public function createGoldIssue()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $payload = $this->payload();
        $issueDate = trim((string) ($payload['issue_date'] ?? ''));
        $orderId = (int) ($payload['order_id'] ?? 0);
        $karigarId = (int) ($payload['karigar_id'] ?? 0);
        $locationId = (int) ($payload['location_id'] ?? 0);
        $purpose = trim((string) ($payload['purpose'] ?? ''));

        if ($issueDate === '' || strtotime($issueDate) === false) {
            return $this->fail('Issue date is required.', 422);
        }
        if ($orderId <= 0 || $karigarId <= 0 || $locationId <= 0) {
            return $this->fail('Order, karigar and location are required.', 422);
        }
        if ($purpose === '') {
            return $this->fail('Purpose is required.', 422);
        }

        $order = db_connect()->table('orders')
            ->select('id, assigned_karigar_id, status')
            ->where('id', $orderId)
            ->whereNotIn('status', ['Cancelled', 'Completed'])
            ->get()
            ->getRowArray();
        if (! $order || (int) ($order['assigned_karigar_id'] ?? 0) <= 0) {
            return $this->fail('Only karigar-assigned active orders are allowed for issuance.', 422);
        }
        if ((int) ($order['assigned_karigar_id'] ?? 0) !== $karigarId) {
            return $this->fail('Selected karigar does not match order assignment.', 422);
        }

        $karigar = (new KarigarModel())->where('id', $karigarId)->where('is_active', 1)->first();
        if (! $karigar) {
            return $this->fail('Selected karigar not found.', 422);
        }

        $location = (new InventoryLocationModel())->where('id', $locationId)->where('is_active', 1)->first();
        if (! $location) {
            return $this->fail('Selected warehouse not found.', 422);
        }

        $parsed = $this->parseGoldLines($payload['lines'] ?? []);
        if ($parsed['error'] !== null) {
            return $this->fail($parsed['error'], 422);
        }
        if ($parsed['lines'] === []) {
            return $this->fail('At least one issue line is required.', 422);
        }

        $attachment = $this->saveBase64Attachment(
            (string) ($payload['attachment_base64'] ?? ''),
            FCPATH . 'uploads/issuements/gold',
            true,
            'uploads/issuements/gold'
        );
        if (! $attachment['ok']) {
            return $this->fail((string) $attachment['message'], 422);
        }

        $db = db_connect();
        $service = new GoldStockService($db);

        try {
            $db->transException(true)->transStart();

            $issueId = (int) (new GoldInventoryIssueHeaderModel())->insert([
                'voucher_no' => $this->generateIssueVoucherNo($db),
                'issue_date' => $issueDate,
                'order_id' => $orderId,
                'karigar_id' => $karigarId,
                'location_id' => $locationId,
                'issue_to' => (string) ($karigar['name'] ?? ''),
                'purpose' => $purpose,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                'attachment_name' => $attachment['name'],
                'attachment_path' => $attachment['path'],
                'created_by' => (int) ($this->mobileAdmin['id'] ?? 0),
            ], true);

            $lineModel = new GoldInventoryIssueLineModel();
            foreach ($parsed['lines'] as $line) {
                $lineModel->insert([
                    'issue_id' => $issueId,
                    'item_id' => $line['item_id'],
                    'weight_gm' => $line['weight_gm'],
                    'fine_weight_gm' => $line['fine_weight_gm'],
                    'rate_per_gm' => $line['rate_per_gm'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyIssue($issueId);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return $this->fail('Unable to save issue: ' . $e->getMessage(), 500);
        }

        return $this->ok(['id' => $issueId], 'Gold issue saved.');
    }

    public function createGoldReturn()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $payload = $this->payload();
        $returnDate = trim((string) ($payload['return_date'] ?? ''));
        $orderId = (int) ($payload['order_id'] ?? 0);
        $issueId = (int) ($payload['issue_id'] ?? 0);
        $karigarId = (int) ($payload['karigar_id'] ?? 0);
        $purpose = trim((string) ($payload['purpose'] ?? ''));

        if ($returnDate === '' || strtotime($returnDate) === false) {
            return $this->fail('Return date is required.', 422);
        }
        if ($orderId <= 0 || $issueId <= 0) {
            return $this->fail('Order and issue reference are required.', 422);
        }

        $issue = db_connect()->table('gold_inventory_issue_headers')
            ->select('id, order_id, karigar_id, issue_to')
            ->where('id', $issueId)
            ->where('order_id', $orderId)
            ->get()
            ->getRowArray();
        if (! $issue) {
            return $this->fail('Selected issue reference not found for this order.', 422);
        }

        $parsed = $this->parseGoldLines($payload['lines'] ?? []);
        if ($parsed['error'] !== null) {
            return $this->fail($parsed['error'], 422);
        }
        if ($parsed['lines'] === []) {
            return $this->fail('At least one return line is required.', 422);
        }

        $attachment = $this->saveBase64Attachment(
            (string) ($payload['attachment_base64'] ?? ''),
            FCPATH . 'uploads/returns/gold',
            true,
            'uploads/returns/gold'
        );
        if (! $attachment['ok']) {
            return $this->fail((string) $attachment['message'], 422);
        }

        $returnFrom = trim((string) ($payload['return_from'] ?? ''));
        if ($returnFrom === '') {
            $returnFrom = (string) ($issue['issue_to'] ?? '');
        }

        $db = db_connect();
        $service = new GoldStockService($db);

        try {
            $db->transException(true)->transStart();

            $returnId = (int) (new GoldInventoryReturnHeaderModel())->insert([
                'voucher_no' => $this->generateReturnVoucherNo($db, 'gold_inventory_return_headers'),
                'return_date' => $returnDate,
                'order_id' => $orderId,
                'issue_id' => $issueId,
                'karigar_id' => $karigarId > 0 ? $karigarId : (int) ($issue['karigar_id'] ?? 0),
                'return_from' => $returnFrom !== '' ? $returnFrom : null,
                'purpose' => $purpose !== '' ? $purpose : null,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                'attachment_name' => $attachment['name'],
                'attachment_path' => $attachment['path'],
                'created_by' => (int) ($this->mobileAdmin['id'] ?? 0),
            ], true);

            $lineModel = new GoldInventoryReturnLineModel();
            foreach ($parsed['lines'] as $line) {
                $lineModel->insert([
                    'return_id' => $returnId,
                    'item_id' => $line['item_id'],
                    'weight_gm' => $line['weight_gm'],
                    'fine_weight_gm' => $line['fine_weight_gm'],
                    'rate_per_gm' => $line['rate_per_gm'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyReturn($returnId);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return $this->fail('Unable to save return: ' . $e->getMessage(), 500);
        }

        return $this->ok(['id' => $returnId], 'Gold return saved.');
    }

    public function goldIssueDetail(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $issue = db_connect()->table('gold_inventory_issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->where('ih.id', $id)
            ->get()
            ->getRowArray();
        if (! $issue) {
            return $this->fail('Issue not found.', 404);
        }

        $lines = $this->goldLineRows('gold_inventory_issue_lines', 'issue_id', $id);
        $totals = $this->goldLineTotals('gold_inventory_issue_lines', 'issue_id', $id);

        return $this->ok([
            'issue' => $issue,
            'lines' => $lines,
            'totals' => $totals,
            'document_path' => '',
            'document_url' => base_url('api/documents/mobile/gold/issues/' . $id),
        ]);
    }

    public function goldReturnDetail(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $return = db_connect()->table('gold_inventory_return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, ih.issue_date, k.name as karigar_name')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('gold_inventory_issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->where('rh.id', $id)
            ->get()
            ->getRowArray();
        if (! $return) {
            return $this->fail('Return not found.', 404);
        }

        $lines = $this->goldLineRows('gold_inventory_return_lines', 'return_id', $id);
        $totals = $this->goldLineTotals('gold_inventory_return_lines', 'return_id', $id);
        $issueLines = $this->goldLineRows('gold_inventory_issue_lines', 'issue_id', (int) ($return['issue_id'] ?? 0));

        return $this->ok([
            'return' => $return,
            'lines' => $lines,
            'totals' => $totals,
            'issue_lines' => $issueLines,
            'document_path' => '',
            'document_url' => base_url('api/documents/mobile/gold/returns/' . $id),
        ]);
    }

    public function goldPurchaseDetail(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $purchase = db_connect()->table('gold_inventory_purchase_headers ph')
            ->select('ph.*, v.name as vendor_name')
            ->join('vendors v', 'v.id = ph.vendor_id', 'left')
            ->where('ph.id', $id)
            ->get()
            ->getRowArray();
        if (! $purchase) {
            return $this->fail('Purchase not found.', 404);
        }

        $lines = $this->goldLineRows('gold_inventory_purchase_lines', 'purchase_id', $id);
        $totals = $this->goldLineTotals('gold_inventory_purchase_lines', 'purchase_id', $id);

        return $this->ok([
            'purchase' => $purchase,
            'lines' => $lines,
            'totals' => $totals,
        ]);
    }

    public function goldIssuePdf(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $issue = db_connect()->table('gold_inventory_issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name, k.name as labour_name, k.phone as labour_phone, k.email as labour_email, k.address as labour_address, k.city as labour_city, k.state as labour_state, k.pincode as labour_pincode')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->where('ih.id', $id)
            ->get()
            ->getRowArray();
        if (! $issue) {
            return $this->fail('Issue not found.', 404);
        }

        $pdf = (new PdfService())->render('pdf/mobile_issue_voucher', [
            'title' => 'Gold Issuement Voucher',
            'materialType' => 'Gold',
            'issue' => $issue,
            'lines' => $this->goldLineRows('gold_inventory_issue_lines', 'issue_id', $id),
            'totals' => $this->goldLineTotals('gold_inventory_issue_lines', 'issue_id', $id),
            'company' => $this->companySetting(),
        ]);

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="gold_issue_' . $id . '.pdf"')
            ->setBody($pdf);
    }

    public function goldReturnPdf(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $return = db_connect()->table('gold_inventory_return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, ih.issue_date, ih.issue_to, iloc.name as warehouse_name, k.name as karigar_name, k.phone as karigar_phone, k.email as karigar_email, k.address as karigar_address, k.city as karigar_city, k.state as karigar_state, k.pincode as karigar_pincode')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('gold_inventory_issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->where('rh.id', $id)
            ->get()
            ->getRowArray();
        if (! $return) {
            return $this->fail('Return not found.', 404);
        }

        $pdf = (new PdfService())->render('pdf/mobile_return_receipt', [
            'title' => 'Gold Return Receipt',
            'materialType' => 'Gold',
            'return' => $return,
            'lines' => $this->goldLineRows('gold_inventory_return_lines', 'return_id', $id),
            'totals' => $this->goldLineTotals('gold_inventory_return_lines', 'return_id', $id),
            'company' => $this->companySetting(),
        ]);

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="gold_return_' . $id . '.pdf"')
            ->setBody($pdf);
    }

    public function createStonePurchase()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $payload = $this->payload();
        $purchaseDate = trim((string) ($payload['purchase_date'] ?? ''));
        if ($purchaseDate === '' || strtotime($purchaseDate) === false) {
            return $this->fail('Purchase date is required.', 422);
        }

        $linesPayload = $payload['lines'] ?? [];
        $parsed = $this->parseStoneLines($linesPayload, false);
        if ($parsed['error'] !== null) {
            return $this->fail($parsed['error'], 422);
        }
        if ($parsed['lines'] === []) {
            return $this->fail('At least one purchase line is required.', 422);
        }

        $vendorId = (int) ($payload['vendor_id'] ?? 0);
        $supplierName = trim((string) ($payload['supplier_name'] ?? ''));
        if ($vendorId > 0 && $supplierName === '') {
            $vendor = (new VendorModel())->find($vendorId);
            $supplierName = $vendor ? (string) ($vendor['name'] ?? '') : '';
        }

        $db = db_connect();
        $service = new StoneStockService($db);

        try {
            $db->transException(true)->transStart();

            $headerId = (int) (new StoneInventoryPurchaseHeaderModel())->insert([
                'purchase_date' => $purchaseDate,
                'vendor_id' => $vendorId > 0 ? $vendorId : null,
                'supplier_name' => $supplierName !== '' ? $supplierName : null,
                'invoice_no' => trim((string) ($payload['invoice_no'] ?? '')) ?: null,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
            ], true);

            $lineModel = new StoneInventoryPurchaseLineModel();
            foreach ($parsed['lines'] as $line) {
                $lineModel->insert([
                    'purchase_id' => $headerId,
                    'item_id' => $line['item_id'],
                    'qty' => $line['qty'],
                    'rate' => $line['rate'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyPurchase($headerId);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return $this->fail('Unable to save purchase: ' . $e->getMessage(), 500);
        }

        return $this->ok(['id' => $headerId], 'Stone purchase saved.');
    }

    public function createStoneIssue()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $payload = $this->payload();
        $issueDate = trim((string) ($payload['issue_date'] ?? ''));
        $orderId = (int) ($payload['order_id'] ?? 0);
        $karigarId = (int) ($payload['karigar_id'] ?? 0);
        $locationId = (int) ($payload['location_id'] ?? 0);
        $purpose = trim((string) ($payload['purpose'] ?? ''));

        if ($issueDate === '' || strtotime($issueDate) === false) {
            return $this->fail('Issue date is required.', 422);
        }
        if ($orderId <= 0 || $karigarId <= 0 || $locationId <= 0) {
            return $this->fail('Order, karigar and location are required.', 422);
        }
        if ($purpose === '') {
            return $this->fail('Purpose is required.', 422);
        }

        $order = db_connect()->table('orders')
            ->select('id, assigned_karigar_id, status')
            ->where('id', $orderId)
            ->whereNotIn('status', ['Cancelled', 'Completed'])
            ->get()
            ->getRowArray();
        if (! $order || (int) ($order['assigned_karigar_id'] ?? 0) <= 0) {
            return $this->fail('Only karigar-assigned active orders are allowed for issuance.', 422);
        }
        if ((int) ($order['assigned_karigar_id'] ?? 0) !== $karigarId) {
            return $this->fail('Selected karigar does not match order assignment.', 422);
        }

        $karigar = (new KarigarModel())->where('id', $karigarId)->where('is_active', 1)->first();
        if (! $karigar) {
            return $this->fail('Selected karigar not found.', 422);
        }

        $location = (new InventoryLocationModel())->where('id', $locationId)->where('is_active', 1)->first();
        if (! $location) {
            return $this->fail('Selected warehouse not found.', 422);
        }

        $parsed = $this->parseStoneLines($payload['lines'] ?? [], true);
        if ($parsed['error'] !== null) {
            return $this->fail($parsed['error'], 422);
        }
        if ($parsed['lines'] === []) {
            return $this->fail('At least one issue line is required.', 422);
        }

        $attachment = $this->saveBase64Attachment(
            (string) ($payload['attachment_base64'] ?? ''),
            FCPATH . 'uploads/issuements/stone',
            true,
            'uploads/issuements/stone'
        );
        if (! $attachment['ok']) {
            return $this->fail((string) $attachment['message'], 422);
        }

        $db = db_connect();
        $service = new StoneStockService($db);

        try {
            $db->transException(true)->transStart();

            $issueId = (int) (new StoneInventoryIssueHeaderModel())->insert([
                'voucher_no' => $this->generateIssueVoucherNo($db),
                'issue_date' => $issueDate,
                'order_id' => $orderId,
                'karigar_id' => $karigarId,
                'location_id' => $locationId,
                'issue_to' => (string) ($karigar['name'] ?? ''),
                'purpose' => $purpose,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                'attachment_name' => $attachment['name'],
                'attachment_path' => $attachment['path'],
                'created_by' => (int) ($this->mobileAdmin['id'] ?? 0),
            ], true);

            $lineModel = new StoneInventoryIssueLineModel();
            foreach ($parsed['lines'] as $line) {
                $lineModel->insert([
                    'issue_id' => $issueId,
                    'item_id' => $line['item_id'],
                    'pcs' => $line['pcs'],
                    'qty' => $line['qty'],
                    'rate' => $line['rate'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyIssue($issueId);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return $this->fail('Unable to save issue: ' . $e->getMessage(), 500);
        }

        return $this->ok(['id' => $issueId], 'Stone issue saved.');
    }

    public function createStoneReturn()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $payload = $this->payload();
        $returnDate = trim((string) ($payload['return_date'] ?? ''));
        $orderId = (int) ($payload['order_id'] ?? 0);
        $issueId = (int) ($payload['issue_id'] ?? 0);
        $karigarId = (int) ($payload['karigar_id'] ?? 0);
        $purpose = trim((string) ($payload['purpose'] ?? ''));

        if ($returnDate === '' || strtotime($returnDate) === false) {
            return $this->fail('Return date is required.', 422);
        }
        if ($orderId <= 0 || $issueId <= 0) {
            return $this->fail('Order and issue reference are required.', 422);
        }

        $issue = db_connect()->table('stone_inventory_issue_headers')
            ->select('id, order_id, karigar_id, issue_to')
            ->where('id', $issueId)
            ->where('order_id', $orderId)
            ->get()
            ->getRowArray();
        if (! $issue) {
            return $this->fail('Selected issue reference not found for this order.', 422);
        }

        $parsed = $this->parseStoneLines($payload['lines'] ?? [], false);
        if ($parsed['error'] !== null) {
            return $this->fail($parsed['error'], 422);
        }
        if ($parsed['lines'] === []) {
            return $this->fail('At least one return line is required.', 422);
        }

        $attachment = $this->saveBase64Attachment(
            (string) ($payload['attachment_base64'] ?? ''),
            FCPATH . 'uploads/returns/stone',
            true,
            'uploads/returns/stone'
        );
        if (! $attachment['ok']) {
            return $this->fail((string) $attachment['message'], 422);
        }

        $returnFrom = trim((string) ($payload['return_from'] ?? ''));
        if ($returnFrom === '') {
            $returnFrom = (string) ($issue['issue_to'] ?? '');
        }

        $db = db_connect();
        $service = new StoneStockService($db);

        try {
            $db->transException(true)->transStart();

            $returnId = (int) (new StoneInventoryReturnHeaderModel())->insert([
                'voucher_no' => $this->generateReturnVoucherNo($db, 'stone_inventory_return_headers'),
                'return_date' => $returnDate,
                'order_id' => $orderId,
                'issue_id' => $issueId,
                'karigar_id' => $karigarId > 0 ? $karigarId : (int) ($issue['karigar_id'] ?? 0),
                'return_from' => $returnFrom !== '' ? $returnFrom : null,
                'purpose' => $purpose !== '' ? $purpose : null,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                'attachment_name' => $attachment['name'],
                'attachment_path' => $attachment['path'],
                'created_by' => (int) ($this->mobileAdmin['id'] ?? 0),
            ], true);

            $lineModel = new StoneInventoryReturnLineModel();
            foreach ($parsed['lines'] as $line) {
                $lineModel->insert([
                    'return_id' => $returnId,
                    'item_id' => $line['item_id'],
                    'qty' => $line['qty'],
                    'rate' => $line['rate'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyReturn($returnId);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return $this->fail('Unable to save return: ' . $e->getMessage(), 500);
        }

        return $this->ok(['id' => $returnId], 'Stone return saved.');
    }

    public function stoneIssueDetail(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $issue = db_connect()->table('stone_inventory_issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->where('ih.id', $id)
            ->get()
            ->getRowArray();
        if (! $issue) {
            return $this->fail('Issue not found.', 404);
        }

        $lines = $this->stoneLineRows('stone_inventory_issue_lines', 'issue_id', $id);
        $totals = $this->stoneLineTotals('stone_inventory_issue_lines', 'issue_id', $id);

        return $this->ok([
            'issue' => $issue,
            'lines' => $lines,
            'totals' => $totals,
            'document_path' => '',
            'document_url' => base_url('api/documents/mobile/stone/issues/' . $id),
        ]);
    }

    public function stoneReturnDetail(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $return = db_connect()->table('stone_inventory_return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, ih.issue_date, k.name as karigar_name')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('stone_inventory_issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->where('rh.id', $id)
            ->get()
            ->getRowArray();
        if (! $return) {
            return $this->fail('Return not found.', 404);
        }

        $lines = $this->stoneLineRows('stone_inventory_return_lines', 'return_id', $id);
        $totals = $this->stoneLineTotals('stone_inventory_return_lines', 'return_id', $id);
        $issueLines = $this->stoneLineRows('stone_inventory_issue_lines', 'issue_id', (int) ($return['issue_id'] ?? 0));

        return $this->ok([
            'return' => $return,
            'lines' => $lines,
            'totals' => $totals,
            'issue_lines' => $issueLines,
            'document_path' => '',
            'document_url' => base_url('api/documents/mobile/stone/returns/' . $id),
        ]);
    }

    public function stonePurchaseDetail(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $purchase = db_connect()->table('stone_inventory_purchase_headers ph')
            ->select('ph.*, v.name as vendor_name')
            ->join('vendors v', 'v.id = ph.vendor_id', 'left')
            ->where('ph.id', $id)
            ->get()
            ->getRowArray();
        if (! $purchase) {
            return $this->fail('Purchase not found.', 404);
        }

        $lines = $this->stoneLineRows('stone_inventory_purchase_lines', 'purchase_id', $id);
        $totals = $this->stoneLineTotals('stone_inventory_purchase_lines', 'purchase_id', $id);

        return $this->ok([
            'purchase' => $purchase,
            'lines' => $lines,
            'totals' => $totals,
        ]);
    }

    public function stoneIssuePdf(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $issue = db_connect()->table('stone_inventory_issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name, k.name as labour_name, k.phone as labour_phone, k.email as labour_email, k.address as labour_address, k.city as labour_city, k.state as labour_state, k.pincode as labour_pincode')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->where('ih.id', $id)
            ->get()
            ->getRowArray();
        if (! $issue) {
            return $this->fail('Issue not found.', 404);
        }

        $pdf = (new PdfService())->render('pdf/mobile_issue_voucher', [
            'title' => 'Stone Issuement Voucher',
            'materialType' => 'Stone',
            'issue' => $issue,
            'lines' => $this->stoneLineRows('stone_inventory_issue_lines', 'issue_id', $id),
            'totals' => $this->stoneLineTotals('stone_inventory_issue_lines', 'issue_id', $id),
            'company' => $this->companySetting(),
        ]);

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="stone_issue_' . $id . '.pdf"')
            ->setBody($pdf);
    }

    public function stoneReturnPdf(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $return = db_connect()->table('stone_inventory_return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, ih.issue_date, ih.issue_to, iloc.name as warehouse_name, k.name as karigar_name, k.phone as karigar_phone, k.email as karigar_email, k.address as karigar_address, k.city as karigar_city, k.state as karigar_state, k.pincode as karigar_pincode')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('stone_inventory_issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->where('rh.id', $id)
            ->get()
            ->getRowArray();
        if (! $return) {
            return $this->fail('Return not found.', 404);
        }

        $pdf = (new PdfService())->render('pdf/mobile_return_receipt', [
            'title' => 'Stone Return Receipt',
            'materialType' => 'Stone',
            'return' => $return,
            'lines' => $this->stoneLineRows('stone_inventory_return_lines', 'return_id', $id),
            'totals' => $this->stoneLineTotals('stone_inventory_return_lines', 'return_id', $id),
            'company' => $this->companySetting(),
        ]);

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="stone_return_' . $id . '.pdf"')
            ->setBody($pdf);
    }

    private function parseDiamondLines($linesPayload): array
    {
        $lines = [];
        if (! is_array($linesPayload)) {
            return ['lines' => [], 'error' => 'Invalid lines payload.'];
        }

        foreach ($linesPayload as $line) {
            if (! is_array($line)) {
                continue;
            }
            $itemId = (int) ($line['item_id'] ?? 0);
            $pcs = (float) ($line['pcs'] ?? 0);
            $carat = (float) ($line['carat'] ?? 0);
            $rate = $line['rate_per_carat'] ?? null;
            $rateValue = $rate === null || $rate === '' ? null : (float) $rate;

            if ($carat <= 0) {
                return ['lines' => [], 'error' => 'Carat must be greater than zero for each line.'];
            }
            if ($pcs < 0) {
                return ['lines' => [], 'error' => 'PCS cannot be negative.'];
            }
            if ($rateValue !== null && $rateValue < 0) {
                return ['lines' => [], 'error' => 'Rate per carat cannot be negative.'];
            }

            $signature = [];
            if ($itemId <= 0) {
                $diamondType = trim((string) ($line['diamond_type'] ?? ''));
                if ($diamondType === '') {
                    return ['lines' => [], 'error' => 'Diamond type is required when item is not selected.'];
                }

                $chalniFromRaw = trim((string) ($line['chalni_from'] ?? ''));
                $chalniToRaw = trim((string) ($line['chalni_to'] ?? ''));
                $from = $chalniFromRaw === '' ? null : $chalniFromRaw;
                $to = $chalniToRaw === '' ? null : $chalniToRaw;
                if (($from === null && $to !== null) || ($from !== null && $to === null)) {
                    return ['lines' => [], 'error' => 'Both chalni from and chalni to are required when chalni is used.'];
                }
                if ($from !== null && ! ctype_digit($from)) {
                    return ['lines' => [], 'error' => 'Chalni from must contain digits only.'];
                }
                if ($to !== null && ! ctype_digit($to)) {
                    return ['lines' => [], 'error' => 'Chalni to must contain digits only.'];
                }
                if ($from !== null && $to !== null && ((int) ltrim($from, '0')) > ((int) ltrim($to, '0'))) {
                    return ['lines' => [], 'error' => 'Chalni from must be less than or equal to chalni to.'];
                }

                $signature = [
                    'diamond_type' => $diamondType,
                    'shape' => trim((string) ($line['shape'] ?? '')),
                    'chalni_from' => $from,
                    'chalni_to' => $to,
                    'color' => trim((string) ($line['color'] ?? '')),
                    'clarity' => trim((string) ($line['clarity'] ?? '')),
                    'cut' => trim((string) ($line['cut'] ?? '')),
                ];
            } else {
                if (! $this->diamondItemModel->find($itemId)) {
                    return ['lines' => [], 'error' => 'Selected item not found.'];
                }
            }

            $lineValue = $rateValue === null ? null : round($carat * $rateValue, 2);
            $lines[] = [
                'item_id' => $itemId,
                'pcs' => round($pcs, 3),
                'carat' => round($carat, 3),
                'rate_per_carat' => $rateValue === null ? null : round($rateValue, 2),
                'line_value' => $lineValue,
                'signature' => $signature,
            ];
        }

        return ['lines' => $lines, 'error' => null];
    }

    private function parseGoldLines($linesPayload): array
    {
        $lines = [];
        if (! is_array($linesPayload)) {
            return ['lines' => [], 'error' => 'Invalid lines payload.'];
        }

        $itemIds = [];
        foreach ($linesPayload as $line) {
            $itemIds[] = (int) ($line['item_id'] ?? 0);
        }
        $itemIds = array_values(array_filter($itemIds));

        $purityMap = [];
        if ($itemIds !== []) {
            $rows = db_connect()->table('gold_inventory_items')
                ->select('id, purity_percent')
                ->whereIn('id', $itemIds)
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $purityMap[(int) ($row['id'] ?? 0)] = (float) ($row['purity_percent'] ?? 0);
            }
        }

        foreach ($linesPayload as $line) {
            if (! is_array($line)) {
                continue;
            }

            $itemId = (int) ($line['item_id'] ?? 0);
            if ($itemId <= 0) {
                return ['lines' => [], 'error' => 'Gold item is required.'];
            }
            if (! isset($purityMap[$itemId])) {
                return ['lines' => [], 'error' => 'Selected gold item not found.'];
            }

            $weight = (float) ($line['weight_gm'] ?? 0);
            if ($weight <= 0) {
                return ['lines' => [], 'error' => 'Weight must be greater than zero for each line.'];
            }

            $rateRaw = $line['rate_per_gm'] ?? null;
            $rateValue = $rateRaw === null || $rateRaw === '' ? null : (float) $rateRaw;
            if ($rateValue !== null && $rateValue < 0) {
                return ['lines' => [], 'error' => 'Rate per gm cannot be negative.'];
            }

            $purity = (float) $purityMap[$itemId];
            $fine = round($weight * $purity / 100, 3);
            $lineValue = $rateValue === null ? null : round($weight * $rateValue, 2);

            $lines[] = [
                'item_id' => $itemId,
                'weight_gm' => round($weight, 3),
                'fine_weight_gm' => $fine,
                'rate_per_gm' => $rateValue === null ? null : round($rateValue, 2),
                'line_value' => $lineValue,
            ];
        }

        return ['lines' => $lines, 'error' => null];
    }

    private function parseStoneLines($linesPayload, bool $includePcs): array
    {
        $lines = [];
        if (! is_array($linesPayload)) {
            return ['lines' => [], 'error' => 'Invalid lines payload.'];
        }

        foreach ($linesPayload as $line) {
            if (! is_array($line)) {
                continue;
            }

            $itemId = (int) ($line['item_id'] ?? 0);
            if ($itemId <= 0) {
                return ['lines' => [], 'error' => 'Stone item is required.'];
            }
            if (! $this->stoneItemModel->find($itemId)) {
                return ['lines' => [], 'error' => 'Selected stone item not found.'];
            }

            $qty = (float) ($line['qty'] ?? 0);
            if ($qty <= 0) {
                return ['lines' => [], 'error' => 'Quantity must be greater than zero for each line.'];
            }

            $rateRaw = $line['rate'] ?? null;
            $rateValue = $rateRaw === null || $rateRaw === '' ? null : (float) $rateRaw;
            if ($rateValue !== null && $rateValue < 0) {
                return ['lines' => [], 'error' => 'Rate cannot be negative.'];
            }

            $lineValue = $rateValue === null ? null : round($qty * $rateValue, 2);

            $payload = [
                'item_id' => $itemId,
                'qty' => round($qty, 3),
                'rate' => $rateValue === null ? null : round($rateValue, 2),
                'line_value' => $lineValue,
            ];

            if ($includePcs) {
                $payload['pcs'] = round((float) ($line['pcs'] ?? 0), 3);
            }

            $lines[] = $payload;
        }

        return ['lines' => $lines, 'error' => null];
    }

    private function diamondLineRows(string $table, string $field, int $headerId): array
    {
        if ($headerId <= 0) {
            return [];
        }

        return db_connect()->table($table . ' l')
            ->select('l.*, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, i.cut')
            ->join('items i', 'i.id = l.item_id', 'left')
            ->where('l.' . $field, $headerId)
            ->orderBy('l.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function diamondLineTotals(string $table, string $field, int $headerId): array
    {
        $row = db_connect()->table($table)
            ->select('COALESCE(SUM(pcs),0) as total_pcs, COALESCE(SUM(carat),0) as total_carat, COALESCE(SUM(line_value),0) as total_value', false)
            ->where($field, $headerId)
            ->get()
            ->getRowArray();

        return [
            'total_pcs' => (float) ($row['total_pcs'] ?? 0),
            'total_carat' => (float) ($row['total_carat'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }

    private function goldLineRows(string $table, string $field, int $headerId): array
    {
        if ($headerId <= 0) {
            return [];
        }

        return db_connect()->table($table . ' l')
            ->select('l.*, gi.purity_code, gi.purity_percent, gi.color_name, gi.form_type')
            ->join('gold_inventory_items gi', 'gi.id = l.item_id', 'left')
            ->where('l.' . $field, $headerId)
            ->orderBy('l.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function goldLineTotals(string $table, string $field, int $headerId): array
    {
        $row = db_connect()->table($table)
            ->select('COALESCE(SUM(weight_gm),0) as total_weight, COALESCE(SUM(fine_weight_gm),0) as total_fine, COALESCE(SUM(line_value),0) as total_value', false)
            ->where($field, $headerId)
            ->get()
            ->getRowArray();

        return [
            'total_weight' => (float) ($row['total_weight'] ?? 0),
            'total_fine' => (float) ($row['total_fine'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }

    private function stoneLineRows(string $table, string $field, int $headerId): array
    {
        if ($headerId <= 0) {
            return [];
        }

        return db_connect()->table($table . ' l')
            ->select('l.*, si.product_name, si.stone_type')
            ->join('stone_inventory_items si', 'si.id = l.item_id', 'left')
            ->where('l.' . $field, $headerId)
            ->orderBy('l.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function stoneLineTotals(string $table, string $field, int $headerId): array
    {
        $row = db_connect()->table($table)
            ->select('COALESCE(SUM(qty),0) as total_qty, COALESCE(SUM(line_value),0) as total_value', false)
            ->where($field, $headerId)
            ->get()
            ->getRowArray();

        return [
            'total_qty' => (float) ($row['total_qty'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }

    private function saveBase64Attachment(string $input, string $uploadDir, bool $required, string $relativeRoot): array
    {
        $raw = trim($input);
        if ($raw === '') {
            return $required
                ? ['ok' => false, 'message' => 'Attachment is required.']
                : ['ok' => true, 'name' => null, 'path' => null];
        }

        $extension = 'jpg';
        if (preg_match('/^data:([^;]+);base64,/', $raw, $m)) {
            $mime = strtolower((string) ($m[1] ?? 'image/jpeg'));
            $extension = match ($mime) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                'application/pdf' => 'pdf',
                default => 'jpg',
            };
            $raw = substr($raw, strpos($raw, ',') + 1);
        }

        $binary = base64_decode(str_replace(' ', '+', $raw), true);
        if ($binary === false) {
            return ['ok' => false, 'message' => 'Invalid attachment payload.'];
        }

        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $name = 'mob_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $path = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
        if (file_put_contents($path, $binary) === false) {
            return ['ok' => false, 'message' => 'Unable to save attachment.'];
        }

        return [
            'ok' => true,
            'name' => $name,
            'path' => trim($relativeRoot, '/') . '/' . $name,
        ];
    }

    private function generateIssueVoucherNo($db): string
    {
        $prefix = strtoupper(trim((string) ($this->companySetting()['issuement_suffix'] ?? 'ISS')));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?: 'ISS';

        $tables = ['gold_inventory_issue_headers', 'issue_headers', 'stone_inventory_issue_headers'];
        $maxSerial = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';

        foreach ($tables as $table) {
            if (! $db->tableExists($table)) {
                continue;
            }
            $rows = $db->table($table)
                ->select('voucher_no')
                ->like('voucher_no', $prefix, 'after')
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $voucherNo = (string) ($row['voucher_no'] ?? '');
                if (preg_match($pattern, $voucherNo, $m) === 1) {
                    $n = (int) $m[1];
                    if ($n > $maxSerial) {
                        $maxSerial = $n;
                    }
                }
            }
        }

        do {
            $maxSerial++;
            $voucher = $prefix . str_pad((string) $maxSerial, 3, '0', STR_PAD_LEFT);
            $exists = false;
            foreach ($tables as $table) {
                if (! $db->tableExists($table)) {
                    continue;
                }
                if ($db->table($table)->where('voucher_no', $voucher)->countAllResults() > 0) {
                    $exists = true;
                    break;
                }
            }
        } while ($exists);

        return $voucher;
    }

    private function generateReturnVoucherNo($db, string $table): string
    {
        $prefix = strtoupper(trim((string) ($this->companySetting()['issuement_suffix'] ?? 'RET')));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?: 'RET';

        $maxSerial = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';
        $rows = $db->table($table)
            ->select('voucher_no')
            ->like('voucher_no', $prefix, 'after')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $voucherNo = (string) ($row['voucher_no'] ?? '');
            if (preg_match($pattern, $voucherNo, $m) === 1) {
                $n = (int) $m[1];
                if ($n > $maxSerial) {
                    $maxSerial = $n;
                }
            }
        }

        do {
            $maxSerial++;
            $voucher = $prefix . str_pad((string) $maxSerial, 3, '0', STR_PAD_LEFT);
            $exists = $db->table($table)->where('voucher_no', $voucher)->countAllResults() > 0;
        } while ($exists);

        return $voucher;
    }

    private function companySetting(): array
    {
        $row = $this->companySettingModel->orderBy('id', 'DESC')->first();
        return is_array($row) ? $row : [];
    }
}
