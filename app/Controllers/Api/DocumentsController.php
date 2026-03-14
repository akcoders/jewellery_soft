<?php

namespace App\Controllers\Api;

use App\Models\CompanySettingModel;
use App\Models\DeliveryChallanModel;
use App\Services\PdfService;
use Exception;

class DocumentsController extends ApiBaseController
{
    private PdfService $pdf;
    private CompanySettingModel $companySettingModel;
    private DeliveryChallanModel $deliveryChallanModel;

    public function __construct()
    {
        helper(['url']);
        $this->pdf = new PdfService();
        $this->companySettingModel = new CompanySettingModel();
        $this->deliveryChallanModel = new DeliveryChallanModel();
    }

    public function jobCard(int $jobCardId)
    {
        $db = db_connect();
        $job = $db->table('job_cards')->where('id', $jobCardId)->get()->getRowArray();
        if (! $job) {
            return $this->fail('Job card not found.', 404);
        }

        $order = $db->table('orders')->where('id', (int) ($job['order_id'] ?? 0))->get()->getRowArray();
        $stages = $db->table('job_card_stages')->where('job_card_id', $jobCardId)->orderBy('id', 'ASC')->get()->getResultArray();

        $pdf = $this->pdf->render('pdf/job_card', [
            'job_card' => $job,
            'order' => $order,
            'stages' => $stages,
            'karigar_name' => $this->nameFromTable('karigars', (int) ($order['assigned_karigar_id'] ?? 0)),
        ]);

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="job_card_' . $jobCardId . '.pdf"')
            ->setBody($pdf);
    }

    public function goldIssueChallan(int $voucherId)
    {
        return $this->voucherPdf($voucherId, 'pdf/gold_issue_challan', 'gold_issue_challan_' . $voucherId . '.pdf');
    }

    public function diamondIssueChallan(int $voucherId)
    {
        return $this->voucherPdf($voucherId, 'pdf/diamond_issue_challan', 'diamond_issue_challan_' . $voucherId . '.pdf');
    }

    public function returnVoucher(int $voucherId)
    {
        return $this->voucherPdf($voucherId, 'pdf/return_voucher', 'return_voucher_' . $voucherId . '.pdf');
    }

    public function packingList(int $packingListId)
    {
        $db = db_connect();
        $packing = $db->table('packing_lists')->where('id', $packingListId)->get()->getRowArray();
        if (! $packing) {
            return $this->fail('Packing list not found.', 404);
        }
        $items = $db->table('packing_list_items')->where('packing_list_id', $packingListId)->get()->getResultArray();
        $order = null;
        $orderId = (int) ($packing['order_id'] ?? 0);
        if ($orderId > 0) {
            $order = $db->table('orders o')
                ->select('o.*, c.name as customer_name')
                ->join('customers c', 'c.id = o.customer_id', 'left')
                ->where('o.id', $orderId)
                ->get()
                ->getRowArray();
        }
        $photo = null;
        if ($orderId > 0 && $db->tableExists('order_attachments')) {
            $photoRow = $db->table('order_attachments')
                ->select('file_path, file_type')
                ->where('order_id', $orderId)
                ->groupStart()
                    ->where('LOWER(file_type)', 'finish_photo')
                    ->orWhere('LOWER(file_type)', 'photo')
                ->groupEnd()
                ->orderBy("CASE WHEN LOWER(file_type) = 'finish_photo' THEN 0 ELSE 1 END", '', false)
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();
            $photo = (string) ($photoRow['file_path'] ?? '');
        }

        $detailRows = $this->packingDetailRows($orderId);
        $receive = $this->packingReceiveSummary($orderId);
        $pricing = $this->packingPricingSummary($orderId, $detailRows, $receive);

        $pdf = $this->pdf->render('pdf/packing_list', [
            'packing' => $packing,
            'items' => $items,
            'order' => $order,
            'photo' => $photo,
            'detailRows' => $detailRows,
            'receive' => $receive,
            'pricing' => $pricing,
        ], 'A4', 'landscape');

        $download = (string) ($this->request->getGet('download') ?? '');
        $disposition = $download === '1' ? 'attachment' : 'inline';
        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', $disposition . '; filename="packing_list_' . $packingListId . '.pdf"')
            ->setBody($pdf);
    }

    public function packingListByOrder(int $orderId)
    {
        $order = $this->eligibleOrderForDocuments($orderId);
        if (! $order) {
            return $this->fail('Order is not ready for packing list.', 422);
        }

        try {
            $packing = $this->ensurePackingListForOrder($orderId);
        } catch (Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $packingId = (int) ($packing['id'] ?? 0);
        if ($packingId <= 0) {
            return $this->fail('Packing list not found.', 404);
        }

        return $this->packingList($packingId);
    }

    public function deliveryChallan(int $orderId)
    {
        $order = $this->eligibleOrderForDocuments($orderId);
        if (! $order) {
            return $this->fail('Order is not ready for delivery challan.', 422);
        }

        $db = db_connect();
        if (! $db->tableExists('delivery_challans')) {
            return $this->fail('Delivery challan table is not available.', 500);
        }

        try {
            $packing = $this->ensurePackingListForOrder($orderId);
        } catch (Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $packingId = (int) ($packing['id'] ?? 0);
        if ($packingId <= 0) {
            return $this->fail('Packing list is required before delivery challan.', 422);
        }

        $setting = $this->companySetting();
        $detailRows = $this->packingDetailRows($orderId);
        $receive = $this->packingReceiveSummary($orderId);
        $pricing = $this->packingPricingSummary($orderId, $detailRows, $receive);
        $challan = $this->saveDeliveryChallanSnapshot($orderId, $packingId, $setting, $receive, $pricing);

        $pdf = $this->pdf->render('pdf/delivery_challan', [
            'company' => $setting,
            'order' => $order,
            'packing' => $packing,
            'challan' => $challan,
            'receive' => $receive,
            'pricing' => $pricing,
            'challan_no' => (string) ($challan['challan_no'] ?? '-'),
        ]);

        $download = (string) ($this->request->getGet('download') ?? '');
        $disposition = $download === '0' ? 'inline' : 'attachment';

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', $disposition . '; filename="delivery_challan_' . $orderId . '.pdf"')
            ->setBody($pdf);
    }

    public function mobileDiamondIssue(int $id)
    {
        return $this->mobileIssueVoucherPdf(
            'issue_headers',
            'issue_lines',
            'inventory_locations',
            'item_id',
            $id,
            'Diamond',
            'diamond_issue_' . $id . '.pdf'
        );
    }

    public function mobileDiamondReturn(int $id)
    {
        return $this->mobileReturnVoucherPdf(
            'return_headers',
            'return_lines',
            'issue_headers',
            'inventory_locations',
            'item_id',
            $id,
            'Diamond',
            'diamond_return_' . $id . '.pdf'
        );
    }

    public function mobileGoldIssue(int $id)
    {
        return $this->mobileIssueVoucherPdf(
            'gold_inventory_issue_headers',
            'gold_inventory_issue_lines',
            'inventory_locations',
            'item_id',
            $id,
            'Gold',
            'gold_issue_' . $id . '.pdf'
        );
    }

    public function mobileGoldReturn(int $id)
    {
        return $this->mobileReturnVoucherPdf(
            'gold_inventory_return_headers',
            'gold_inventory_return_lines',
            'gold_inventory_issue_headers',
            'inventory_locations',
            'item_id',
            $id,
            'Gold',
            'gold_return_' . $id . '.pdf'
        );
    }

    public function mobileStoneIssue(int $id)
    {
        return $this->mobileIssueVoucherPdf(
            'stone_inventory_issue_headers',
            'stone_inventory_issue_lines',
            'inventory_locations',
            'item_id',
            $id,
            'Stone',
            'stone_issue_' . $id . '.pdf'
        );
    }

    public function mobileStoneReturn(int $id)
    {
        return $this->mobileReturnVoucherPdf(
            'stone_inventory_return_headers',
            'stone_inventory_return_lines',
            'stone_inventory_issue_headers',
            'inventory_locations',
            'item_id',
            $id,
            'Stone',
            'stone_return_' . $id . '.pdf'
        );
    }

    public function invoice(int $invoiceId)
    {
        $db = db_connect();
        $invoice = $db->table('invoices')->where('id', $invoiceId)->get()->getRowArray();
        if (! $invoice) {
            return $this->fail('Invoice not found.', 404);
        }
        $items = $db->table('invoice_items')->where('invoice_id', $invoiceId)->get()->getResultArray();

        $pdf = $this->pdf->render('pdf/gst_invoice', [
            'invoice' => $invoice,
            'items' => $items,
            'customer_name' => $this->nameFromTable('customers', (int) ($invoice['customer_id'] ?? 0)),
        ]);

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="invoice_' . $invoiceId . '.pdf"')
            ->setBody($pdf);
    }

    public function ledgerStatement(int $accountId)
    {
        $db = db_connect();
        $account = $db->table('accounts')->where('id', $accountId)->get()->getRowArray();
        if (! $account) {
            return $this->fail('Account not found.', 404);
        }

        $entries = $db->query(
            "SELECT v.voucher_date, v.voucher_no, le.item_key,
                    CASE WHEN le.debit_account_id = ? THEN le.qty_weight ELSE -le.qty_weight END AS delta_weight
             FROM ledger_entries le
             JOIN vouchers v ON v.id = le.voucher_id
             WHERE le.debit_account_id = ? OR le.credit_account_id = ?
             ORDER BY le.id ASC",
            [$accountId, $accountId, $accountId]
        )->getResultArray();

        $pdf = $this->pdf->render('pdf/ledger_statement', [
            'account' => $account,
            'entries' => $entries,
        ]);

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="ledger_' . $accountId . '.pdf"')
            ->setBody($pdf);
    }

    private function voucherPdf(int $voucherId, string $template, string $filename)
    {
        $db = db_connect();
        $voucher = $db->table('vouchers')->where('id', $voucherId)->get()->getRowArray();
        if (! $voucher) {
            return $this->fail('Voucher not found.', 404);
        }
        $lines = $db->table('voucher_lines')->where('voucher_id', $voucherId)->orderBy('line_no', 'ASC')->get()->getResultArray();

        $pdf = $this->pdf->render($template, [
            'voucher' => $voucher,
            'lines' => $lines,
            'karigar_name' => $this->nameFromTable('karigars', (int) ($voucher['party_id'] ?? 0)),
        ]);

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setBody($pdf);
    }

    private function mobileIssueVoucherPdf(
        string $headerTable,
        string $lineTable,
        string $locationTable,
        string $lineItemField,
        int $id,
        string $materialType,
        string $filename
    ) {
        $db = db_connect();
        $header = $db->table($headerTable . ' ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name, k.name as labour_name, k.phone as labour_phone, k.email as labour_email, k.address as labour_address, k.city as labour_city, k.state as labour_state, k.pincode as labour_pincode')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join($locationTable . ' iloc', 'iloc.id = ih.location_id', 'left')
            ->where('ih.id', $id)
            ->get()
            ->getRowArray();

        if (! is_array($header)) {
            return $this->fail('Issue not found.', 404);
        }

        $material = strtolower($materialType);
        $lines = match ($material) {
            'diamond' => $db->table($lineTable . ' l')
                ->select('l.*, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, i.cut')
                ->join('items i', 'i.id = l.' . $lineItemField, 'left')
                ->where('l.issue_id', $id)
                ->orderBy('l.id', 'ASC')
                ->get()
                ->getResultArray(),
            'gold' => $db->table($lineTable . ' l')
                ->select('l.*, gi.purity_code, gi.purity_percent, gi.color_name, gi.form_type')
                ->join('gold_inventory_items gi', 'gi.id = l.' . $lineItemField, 'left')
                ->where('l.issue_id', $id)
                ->orderBy('l.id', 'ASC')
                ->get()
                ->getResultArray(),
            default => $db->table($lineTable . ' l')
                ->select('l.*, si.product_name, si.stone_type')
                ->join('stone_inventory_items si', 'si.id = l.' . $lineItemField, 'left')
                ->where('l.issue_id', $id)
                ->orderBy('l.id', 'ASC')
                ->get()
                ->getResultArray(),
        };

        $totalValue = 0.0;
        foreach ($lines as $line) {
            $totalValue += (float) ($line['line_value'] ?? 0);
        }

        $pdf = $this->pdf->render('pdf/mobile_issue_voucher', [
            'materialType' => $materialType,
            'issue' => $header,
            'lines' => $lines,
            'totals' => ['total_value' => $totalValue],
            'company' => $this->companySetting(),
        ]);

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($pdf);
    }

    private function mobileReturnVoucherPdf(
        string $returnHeaderTable,
        string $returnLineTable,
        string $issueHeaderTable,
        string $locationTable,
        string $lineItemField,
        int $id,
        string $materialType,
        string $filename
    ) {
        $db = db_connect();
        $header = $db->table($returnHeaderTable . ' rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, ih.issue_date, ih.issue_to, iloc.name as warehouse_name, k.name as karigar_name, k.phone as karigar_phone, k.email as karigar_email, k.address as karigar_address, k.city as karigar_city, k.state as karigar_state, k.pincode as karigar_pincode')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join($issueHeaderTable . ' ih', 'ih.id = rh.issue_id', 'left')
            ->join($locationTable . ' iloc', 'iloc.id = ih.location_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->where('rh.id', $id)
            ->get()
            ->getRowArray();

        if (! is_array($header)) {
            return $this->fail('Return not found.', 404);
        }

        $material = strtolower($materialType);
        $lines = match ($material) {
            'diamond' => $db->table($returnLineTable . ' l')
                ->select('l.*, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, i.cut')
                ->join('items i', 'i.id = l.' . $lineItemField, 'left')
                ->where('l.return_id', $id)
                ->orderBy('l.id', 'ASC')
                ->get()
                ->getResultArray(),
            'gold' => $db->table($returnLineTable . ' l')
                ->select('l.*, gi.purity_code, gi.purity_percent, gi.color_name, gi.form_type')
                ->join('gold_inventory_items gi', 'gi.id = l.' . $lineItemField, 'left')
                ->where('l.return_id', $id)
                ->orderBy('l.id', 'ASC')
                ->get()
                ->getResultArray(),
            default => $db->table($returnLineTable . ' l')
                ->select('l.*, si.product_name, si.stone_type')
                ->join('stone_inventory_items si', 'si.id = l.' . $lineItemField, 'left')
                ->where('l.return_id', $id)
                ->orderBy('l.id', 'ASC')
                ->get()
                ->getResultArray(),
        };

        $totalValue = 0.0;
        foreach ($lines as $line) {
            $totalValue += (float) ($line['line_value'] ?? 0);
        }

        $pdf = $this->pdf->render('pdf/mobile_return_receipt', [
            'materialType' => $materialType,
            'return' => $header,
            'lines' => $lines,
            'totals' => ['total_value' => $totalValue],
            'company' => $this->companySetting(),
        ]);

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($pdf);
    }

    private function eligibleOrderForDocuments(int $orderId): ?array
    {
        if ($orderId <= 0) {
            return null;
        }

        $order = db_connect()->table('orders o')
            ->select('o.*, c.name as customer_name')
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->where('o.id', $orderId)
            ->get()
            ->getRowArray();
        if (! is_array($order)) {
            return null;
        }

        $status = (string) ($order['status'] ?? '');
        return $this->isDocumentEligibleStatus($status) ? $order : null;
    }

    private function isDocumentEligibleStatus(string $status): bool
    {
        return in_array($status, ['Ready', 'Packed', 'Dispatched', 'Completed'], true);
    }

    private function ensurePackingListForOrder(int $orderId): array
    {
        $db = db_connect();
        $existing = $db->table('packing_lists')
            ->where('order_id', $orderId)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        if (is_array($existing) && $existing !== []) {
            return $existing;
        }

        $order = $db->table('orders')->where('id', $orderId)->get()->getRowArray();
        if (! $order) {
            throw new Exception('Order not found.');
        }
        if (! $this->isDocumentEligibleStatus((string) ($order['status'] ?? ''))) {
            throw new Exception('Packing list can be generated only for ready orders.');
        }

        $rows = $db->table('order_items')
            ->select('id, qty, gold_required_gm, diamond_required_cts')
            ->where('order_id', $orderId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
        if ($rows === []) {
            throw new Exception('No order items found to create packing list.');
        }

        $packingNo = $this->nextPackingNo();
        $db->transException(true)->transStart();

        $packingId = (int) $db->table('packing_lists')->insert([
            'packing_no' => $packingNo,
            'packing_date' => date('Y-m-d'),
            'order_id' => $orderId,
            'customer_id' => (int) ($order['customer_id'] ?? 0) > 0 ? (int) ($order['customer_id'] ?? 0) : null,
            'warehouse_id' => null,
            'status' => 'Packed',
            'notes' => 'Auto-generated from mobile document request.',
            'created_by' => null,
        ], true);

        foreach ($rows as $index => $line) {
            $qty = (int) max(1, (int) ($line['qty'] ?? 1));
            $netGold = round((float) ($line['gold_required_gm'] ?? 0), 3);
            $diamondCts = round((float) ($line['diamond_required_cts'] ?? 0), 3);
            $gross = round($netGold + ($diamondCts * 0.2), 3);

            $db->table('packing_list_items')->insert([
                'packing_list_id' => $packingId,
                'fg_item_id' => 0,
                'tag_no' => (string) ($order['order_no'] ?? 'ORD') . '-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'qty' => $qty,
                'gross_wt' => $gross,
                'net_gold_wt' => $netGold,
                'diamond_cts' => $diamondCts,
                'stone_wt' => 0,
            ]);
        }

        $db->transComplete();

        $created = $db->table('packing_lists')->where('id', $packingId)->get()->getRowArray();
        return is_array($created) ? $created : [];
    }

    private function nextPackingNo(): string
    {
        $db = db_connect();
        $prefix = 'PK' . date('ymd');
        $rows = $db->table('packing_lists')
            ->select('packing_no')
            ->like('packing_no', $prefix, 'after')
            ->get()
            ->getResultArray();

        $max = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d{3,})$/';
        foreach ($rows as $row) {
            $no = (string) ($row['packing_no'] ?? '');
            if ($no !== '' && preg_match($pattern, $no, $m) === 1) {
                $serial = (int) ($m[1] ?? 0);
                if ($serial > $max) {
                    $max = $serial;
                }
            }
        }

        return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    private function companySetting(): array
    {
        $row = $this->companySettingModel->orderBy('id', 'ASC')->first();
        return is_array($row) ? $row : [];
    }

    private function saveDeliveryChallanSnapshot(int $orderId, int $packingId, array $setting, array $receive, array $pricing): array
    {
        $prefix = strtoupper(trim((string) ($setting['delivery_challan_suffix'] ?? 'DC')));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?: 'DC';
        $taxPercent = 3.0;
        $taxable = round((float) ($pricing['total'] ?? 0), 2);
        $taxAmount = round($taxable * ($taxPercent / 100), 2);
        $totalAmount = round($taxable + $taxAmount, 2);

        $existing = $this->deliveryChallanModel
            ->where('order_id', $orderId)
            ->where('packing_list_id', $packingId)
            ->orderBy('id', 'DESC')
            ->first();

        $challanNo = (string) ($existing['challan_no'] ?? '');
        if ($challanNo === '') {
            $challanNo = $this->nextDeliveryChallanNo($prefix);
        }

        $payload = [
            'challan_no' => $challanNo,
            'challan_date' => date('Y-m-d'),
            'order_id' => $orderId,
            'packing_list_id' => $packingId > 0 ? $packingId : null,
            'receive_movement_id' => (int) ($receive['movement_id'] ?? 0) > 0 ? (int) ($receive['movement_id'] ?? 0) : null,
            'gross_weight_gm' => round((float) ($receive['gross'] ?? 0), 3),
            'net_gold_weight_gm' => round((float) ($receive['net'] ?? 0), 3),
            'diamond_weight_cts' => round((float) ($receive['diamond_cts'] ?? 0), 3),
            'color_stone_weight_cts' => round((float) ($receive['stone_cts'] ?? 0), 3),
            'other_weight_gm' => round((float) ($receive['other_gm'] ?? 0), 3),
            'taxable_value' => $taxable,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'summary_json' => json_encode(
                ['receive' => $receive, 'pricing' => $pricing],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'created_by' => null,
        ];

        if ($existing) {
            $id = (int) ($existing['id'] ?? 0);
            if ($id > 0) {
                $this->deliveryChallanModel->update($id, $payload);
                $updated = $this->deliveryChallanModel->find($id);
                return is_array($updated) ? $updated : $payload;
            }
        }

        $newId = (int) $this->deliveryChallanModel->insert($payload, true);
        if ($newId > 0) {
            $saved = $this->deliveryChallanModel->find($newId);
            if (is_array($saved)) {
                return $saved;
            }
        }

        return $payload;
    }

    private function nextDeliveryChallanNo(string $prefix): string
    {
        $rows = $this->deliveryChallanModel
            ->select('challan_no')
            ->like('challan_no', $prefix, 'after')
            ->findAll();

        $max = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d{3,})$/';
        foreach ($rows as $row) {
            $no = (string) ($row['challan_no'] ?? '');
            if ($no !== '' && preg_match($pattern, $no, $m) === 1) {
                $serial = (int) ($m[1] ?? 0);
                if ($serial > $max) {
                    $max = $serial;
                }
            }
        }

        return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function packingDetailRows(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }
        $db = db_connect();

        if ($db->tableExists('order_receive_details')) {
            $receiveRows = $db->table('order_receive_details')
                ->select('component_type, component_name, pcs, weight_cts, weight_gm, rate, line_total')
                ->where('order_id', $orderId)
                ->orderBy('movement_id', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            if ($receiveRows !== []) {
                $rows = [];
                foreach ($receiveRows as $row) {
                    $componentType = strtolower((string) ($row['component_type'] ?? ''));
                    $weight = $componentType === 'other'
                        ? (float) ($row['weight_gm'] ?? 0)
                        : (float) ($row['weight_cts'] ?? 0);
                    $pcs = (float) ($row['pcs'] ?? 0);
                    $amt = (float) ($row['line_total'] ?? 0);
                    if ($pcs <= 0 && $weight <= 0 && $amt <= 0) {
                        continue;
                    }
                    $name = trim((string) ($row['component_name'] ?? ''));
                    if ($name === '') {
                        $name = ucfirst($componentType !== '' ? $componentType : 'detail');
                    }
                    $rows[] = [
                        'name' => $name,
                        'grade' => ucfirst($componentType !== '' ? $componentType : '-'),
                        'pcs' => round($pcs, 3),
                        'wt' => round($weight, 3),
                        'rate' => round((float) ($row['rate'] ?? 0), 2),
                        'amt' => round($amt, 2),
                    ];
                }
                if ($rows !== []) {
                    return $rows;
                }
            }
        }

        $rows = [];

        if ($db->tableExists('issue_headers') && $db->tableExists('issue_lines') && $db->tableExists('items')) {
            $issueRows = $db->table('issue_headers ih')
                ->select('il.item_id, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, COALESCE(SUM(il.pcs),0) as issue_pcs, COALESCE(SUM(il.carat),0) as issue_carat, COALESCE(SUM(il.line_value),0) as issue_amount', false)
                ->join('issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->join('items i', 'i.id = il.item_id', 'left')
                ->where('ih.order_id', $orderId)
                ->groupBy('il.item_id, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity')
                ->get()
                ->getResultArray();

            $returnMap = [];
            if ($db->tableExists('return_headers') && $db->tableExists('return_lines')) {
                $returnRows = $db->table('return_headers rh')
                    ->select('rl.item_id, COALESCE(SUM(rl.pcs),0) as return_pcs, COALESCE(SUM(rl.carat),0) as return_carat, COALESCE(SUM(rl.line_value),0) as return_amount', false)
                    ->join('return_lines rl', 'rl.return_id = rh.id', 'inner')
                    ->where('rh.order_id', $orderId)
                    ->groupBy('rl.item_id')
                    ->get()
                    ->getResultArray();
                foreach ($returnRows as $row) {
                    $returnMap[(int) ($row['item_id'] ?? 0)] = $row;
                }
            }

            foreach ($issueRows as $row) {
                $itemId = (int) ($row['item_id'] ?? 0);
                $r = $returnMap[$itemId] ?? ['return_pcs' => 0, 'return_carat' => 0, 'return_amount' => 0];
                $pcs = max(0.0, (float) ($row['issue_pcs'] ?? 0) - (float) ($r['return_pcs'] ?? 0));
                $wt = max(0.0, (float) ($row['issue_carat'] ?? 0) - (float) ($r['return_carat'] ?? 0));
                $amt = max(0.0, (float) ($row['issue_amount'] ?? 0) - (float) ($r['return_amount'] ?? 0));
                if ($pcs <= 0 && $wt <= 0 && $amt <= 0) {
                    continue;
                }
                $name = trim((string) ($row['diamond_type'] ?? 'Diamond'));
                $grade = trim(implode('/', array_filter([
                    (string) ($row['shape'] ?? ''),
                    (string) ($row['color'] ?? ''),
                    (string) ($row['clarity'] ?? ''),
                ], static fn(string $v): bool => trim($v) !== '')));
                $rate = $wt > 0 ? $amt / $wt : 0.0;
                $rows[] = [
                    'name' => $name,
                    'grade' => $grade === '' ? '-' : $grade,
                    'pcs' => round($pcs, 3),
                    'wt' => round($wt, 3),
                    'rate' => round($rate, 2),
                    'amt' => round($amt, 2),
                ];
            }
        }

        if ($db->tableExists('stone_inventory_issue_headers') && $db->tableExists('stone_inventory_issue_lines') && $db->tableExists('stone_inventory_items')) {
            $issueRows = $db->table('stone_inventory_issue_headers ih')
                ->select('il.item_id, i.product_name, i.stone_type, COALESCE(SUM(il.pcs),0) as issue_pcs, COALESCE(SUM(il.qty),0) as issue_wt, COALESCE(SUM(il.line_value),0) as issue_amount', false)
                ->join('stone_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->join('stone_inventory_items i', 'i.id = il.item_id', 'left')
                ->where('ih.order_id', $orderId)
                ->groupBy('il.item_id, i.product_name, i.stone_type')
                ->get()
                ->getResultArray();

            $returnMap = [];
            if ($db->tableExists('stone_inventory_return_headers') && $db->tableExists('stone_inventory_return_lines')) {
                $returnRows = $db->table('stone_inventory_return_headers rh')
                    ->select('rl.item_id, COALESCE(SUM(rl.qty),0) as return_pcs, COALESCE(SUM(rl.qty),0) as return_wt, COALESCE(SUM(rl.line_value),0) as return_amount', false)
                    ->join('stone_inventory_return_lines rl', 'rl.return_id = rh.id', 'inner')
                    ->where('rh.order_id', $orderId)
                    ->groupBy('rl.item_id')
                    ->get()
                    ->getResultArray();
                foreach ($returnRows as $row) {
                    $returnMap[(int) ($row['item_id'] ?? 0)] = $row;
                }
            }

            foreach ($issueRows as $row) {
                $itemId = (int) ($row['item_id'] ?? 0);
                $r = $returnMap[$itemId] ?? ['return_pcs' => 0, 'return_wt' => 0, 'return_amount' => 0];
                $pcs = max(0.0, (float) ($row['issue_pcs'] ?? 0) - (float) ($r['return_pcs'] ?? 0));
                $wt = max(0.0, (float) ($row['issue_wt'] ?? 0) - (float) ($r['return_wt'] ?? 0));
                $amt = max(0.0, (float) ($row['issue_amount'] ?? 0) - (float) ($r['return_amount'] ?? 0));
                if ($pcs <= 0 && $wt <= 0 && $amt <= 0) {
                    continue;
                }
                $name = trim((string) ($row['product_name'] ?? 'Stone'));
                $grade = trim((string) ($row['stone_type'] ?? '-'));
                $rate = $wt > 0 ? $amt / $wt : 0.0;
                $rows[] = [
                    'name' => $name,
                    'grade' => $grade === '' ? '-' : $grade,
                    'pcs' => round($pcs, 3),
                    'wt' => round($wt, 3),
                    'rate' => round($rate, 2),
                    'amt' => round($amt, 2),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array<string,float>
     */
    private function packingReceiveSummary(int $orderId): array
    {
        if ($orderId <= 0) {
            return [
                'gross' => 0.0,
                'net' => 0.0,
                'pure' => 0.0,
                'diamond_cts' => 0.0,
                'diamond_gm' => 0.0,
                'stone_cts' => 0.0,
                'stone_gm' => 0.0,
                'other_gm' => 0.0,
                'movement_id' => 0,
            ];
        }

        $db = db_connect();
        if ($db->tableExists('order_receive_summaries')) {
            $row = $db->table('order_receive_summaries')
                ->select('COALESCE(SUM(gross_weight_gm),0) as gross, COALESCE(SUM(net_gold_weight_gm),0) as net, COALESCE(SUM(pure_gold_weight_gm),0) as pure, COALESCE(SUM(diamond_weight_cts),0) as diamond_cts, COALESCE(SUM(diamond_weight_gm),0) as diamond_gm, COALESCE(SUM(stone_weight_cts),0) as stone_cts, COALESCE(SUM(stone_weight_gm),0) as stone_gm, COALESCE(SUM(other_weight_gm),0) as other_gm, COALESCE(MAX(movement_id),0) as movement_id', false)
                ->where('order_id', $orderId)
                ->get()
                ->getRowArray();
            if ($row && ((float) ($row['gross'] ?? 0) > 0 || (float) ($row['net'] ?? 0) > 0)) {
                return [
                    'gross' => round((float) ($row['gross'] ?? 0), 3),
                    'net' => round((float) ($row['net'] ?? 0), 3),
                    'pure' => round((float) ($row['pure'] ?? 0), 3),
                    'diamond_cts' => round((float) ($row['diamond_cts'] ?? 0), 3),
                    'diamond_gm' => round((float) ($row['diamond_gm'] ?? 0), 3),
                    'stone_cts' => round((float) ($row['stone_cts'] ?? 0), 3),
                    'stone_gm' => round((float) ($row['stone_gm'] ?? 0), 3),
                    'other_gm' => round((float) ($row['other_gm'] ?? 0), 3),
                    'movement_id' => (int) ($row['movement_id'] ?? 0),
                ];
            }
        }

        $row = $db->table('order_material_movements')
            ->select('COALESCE(SUM(gross_weight_gm),0) as gross, COALESCE(SUM(net_gold_weight_gm),0) as net, COALESCE(SUM(pure_gold_weight_gm),0) as pure, COALESCE(SUM(diamond_cts),0) as diamond_cts, COALESCE(SUM(diamond_weight_gm),0) as diamond_gm, COALESCE(SUM(other_weight_gm),0) as other_gm, COALESCE(MAX(id),0) as movement_id', false)
            ->where('order_id', $orderId)
            ->where('movement_type', 'receive')
            ->get()
            ->getRowArray();

        return [
            'gross' => round((float) ($row['gross'] ?? 0), 3),
            'net' => round((float) ($row['net'] ?? 0), 3),
            'pure' => round((float) ($row['pure'] ?? 0), 3),
            'diamond_cts' => round((float) ($row['diamond_cts'] ?? 0), 3),
            'diamond_gm' => round((float) ($row['diamond_gm'] ?? 0), 3),
            'stone_cts' => 0.0,
            'stone_gm' => 0.0,
            'other_gm' => round((float) ($row['other_gm'] ?? 0), 3),
            'movement_id' => (int) ($row['movement_id'] ?? 0),
        ];
    }

    /**
     * @param list<array<string,mixed>> $detailRows
     * @return array<string,float>
     */
    private function packingPricingSummary(int $orderId, array $detailRows, array $receive): array
    {
        $db = db_connect();
        if ($orderId > 0 && $db->tableExists('order_receive_summaries')) {
            $sum = $db->table('order_receive_summaries')
                ->select('COALESCE(SUM(diamond_amount),0) as diamond_amount, COALESCE(SUM(stone_amount),0) as stone_amount, COALESCE(SUM(other_amount),0) as other_amount, COALESCE(SUM(gold_amount),0) as gold_amount, COALESCE(SUM(labour_amount),0) as labour_amount, COALESCE(SUM(total_valuation),0) as total_valuation', false)
                ->where('order_id', $orderId)
                ->get()
                ->getRowArray();

            $diamondAmount = round((float) ($sum['diamond_amount'] ?? 0), 2);
            $stoneAmount = round((float) ($sum['stone_amount'] ?? 0), 2);
            $otherAmount = round((float) ($sum['other_amount'] ?? 0), 2);
            $goldAmount = round((float) ($sum['gold_amount'] ?? 0), 2);
            $labourAmount = round((float) ($sum['labour_amount'] ?? 0), 2);
            $studdedAmount = round($diamondAmount + $stoneAmount + $otherAmount, 2);
            $totalValuation = round((float) ($sum['total_valuation'] ?? 0), 2);
            if ($totalValuation <= 0) {
                $totalValuation = round($studdedAmount + $goldAmount + $labourAmount, 2);
            }

            if ($studdedAmount > 0 || $goldAmount > 0 || $labourAmount > 0 || $totalValuation > 0) {
                return [
                    'diamond' => $diamondAmount,
                    'stone' => $stoneAmount,
                    'other' => $otherAmount,
                    'studded' => $studdedAmount,
                    'gold' => $goldAmount,
                    'labour' => $labourAmount,
                    'total' => $totalValuation,
                ];
            }
        }

        $studdedAmount = 0.0;
        foreach ($detailRows as $row) {
            $studdedAmount += (float) ($row['amt'] ?? 0);
        }

        $goldAmount = 0.0;
        if ($orderId > 0 && $db->tableExists('gold_inventory_issue_headers') && $db->tableExists('gold_inventory_issue_lines')) {
            $avg = $db->table('gold_inventory_issue_headers ih')
                ->select('COALESCE(SUM(il.line_value),0) as amount, COALESCE(SUM(il.weight_gm),0) as wt', false)
                ->join('gold_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->where('ih.order_id', $orderId)
                ->get()
                ->getRowArray();
            $wt = (float) ($avg['wt'] ?? 0);
            if ($wt > 0) {
                $rate = (float) ($avg['amount'] ?? 0) / $wt;
                $goldAmount = max(0.0, (float) ($receive['pure'] ?? 0) * $rate);
            }
        }

        $labour = 0.0;
        if ($orderId > 0 && $db->tableExists('labour_bills')) {
            $row = $db->table('labour_bills')
                ->select('COALESCE(SUM(total_amount),0) as total', false)
                ->where('order_id', $orderId)
                ->get()
                ->getRowArray();
            $labour = (float) ($row['total'] ?? 0);
        }

        return [
            'diamond' => round($studdedAmount, 2),
            'stone' => 0.0,
            'other' => 0.0,
            'studded' => round($studdedAmount, 2),
            'gold' => round($goldAmount, 2),
            'labour' => round($labour, 2),
            'total' => round($studdedAmount + $goldAmount + $labour, 2),
        ];
    }

    private function nameFromTable(string $table, int $id): string
    {
        if ($id <= 0) {
            return '-';
        }
        $row = db_connect()->table($table)->select('name')->where('id', $id)->get()->getRowArray();
        return (string) ($row['name'] ?? '-');
    }
}
