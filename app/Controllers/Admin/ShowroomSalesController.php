<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CustomerModel;
use App\Models\CustomerReceiptModel;
use App\Models\FgItemModel;
use App\Models\InvoiceItemModel;
use App\Models\InvoiceModel;
use App\Models\PackingListItemModel;
use App\Models\PackingListModel;
use App\Models\ShowroomFgMovementModel;
use App\Models\ShowroomSaleItemModel;
use App\Models\ShowroomSaleModel;
use App\Services\PdfService;
use App\Services\SalesIntelligenceService;
use App\Services\TaxMasterService;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

/**
 * Wholesale studded-jewellery billing.
 *
 * Historical table names are retained so existing sale data and account
 * reports remain compatible after the retail showroom UI removal.
 */
class ShowroomSalesController extends BaseController
{
    private ShowroomSaleModel $saleModel;
    private ShowroomSaleItemModel $saleItemModel;
    private CustomerModel $customerModel;
    private FgItemModel $fgItemModel;
    private ShowroomFgMovementModel $movementModel;
    private InvoiceModel $invoiceModel;
    private InvoiceItemModel $invoiceItemModel;
    private CustomerReceiptModel $customerReceiptModel;
    private PackingListModel $packingListModel;
    private PackingListItemModel $packingListItemModel;
    private TaxMasterService $taxMasterService;
    private PdfService $pdfService;
    private SalesIntelligenceService $salesIntelligenceService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->saleModel = new ShowroomSaleModel();
        $this->saleItemModel = new ShowroomSaleItemModel();
        $this->customerModel = new CustomerModel();
        $this->fgItemModel = new FgItemModel();
        $this->movementModel = new ShowroomFgMovementModel();
        $this->invoiceModel = new InvoiceModel();
        $this->invoiceItemModel = new InvoiceItemModel();
        $this->customerReceiptModel = new CustomerReceiptModel();
        $this->packingListModel = new PackingListModel();
        $this->packingListItemModel = new PackingListItemModel();
        $this->taxMasterService = new TaxMasterService();
        $this->pdfService = new PdfService();
        $this->salesIntelligenceService = new SalesIntelligenceService();
    }

    public function dashboard(): string
    {
        $data = $this->salesIntelligenceService->build(
            trim((string) $this->request->getGet('date_from')),
            trim((string) $this->request->getGet('date_to'))
        );

        return view('admin/showroom_sales/dashboard', [
            'title' => 'Sales Intelligence Dashboard',
        ] + $data);
    }

    public function index(): string
    {
        $rows = db_connect()->table('showroom_sales s')
            ->select('s.*, cust.name as customer_name, i.invoice_no, pl.packing_no, COALESCE(SUM(cr.amount),0) as paid_amount', false)
            ->join('customers cust', 'cust.id = s.customer_id', 'left')
            ->join('invoices i', 'i.id = s.invoice_id', 'left')
            ->join('packing_lists pl', 'pl.id = s.packing_list_id', 'left')
            ->join('customer_receipts cr', 'cr.invoice_id = s.invoice_id', 'left')
            ->groupBy('s.id')
            ->orderBy('s.id', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $paid = (float) ($row['paid_amount'] ?? 0);
            $total = (float) ($row['total_amount'] ?? 0);
            $row['payment_status'] = $this->paymentStatus($total, $paid);
            $row['pending_amount'] = max(0, round($total - $paid, 2));
        }
        unset($row);

        return view('admin/showroom_sales/index', [
            'title' => 'Studded Jewellery Sale Bills',
            'rows' => $rows,
            'summary' => [
                'sale_count' => count($rows),
                'invoice_value' => array_sum(array_column($rows, 'total_amount')),
                'paid_amount' => array_sum(array_column($rows, 'paid_amount')),
                'pending_amount' => array_sum(array_column($rows, 'pending_amount')),
            ],
        ]);
    }

    public function create(): string
    {
        return view('admin/showroom_sales/form', [
            'title' => 'Create Studded Jewellery Sale',
            'formAction' => site_url('admin/studded-jewellery/sale-bills'),
            'customers' => $this->activeCustomers(),
            'fgItems' => $this->saleableFgItems(),
            'gstMasters' => $this->taxMasterService->options(),
        ]);
    }

    public function store()
    {
        $rules = [
            'sale_date' => 'required|valid_date',
            'customer_id' => 'required|integer|greater_than[0]',
            'gst_master_id' => 'required|integer|greater_than[0]',
            'hsn_sac' => 'required|max_length[30]',
            'gold_rate' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'diamond_rate' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'stone_rate' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'other_amount' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'round_off_amount' => 'permit_empty|decimal',
            'received_amount' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'payment_mode' => 'permit_empty|max_length[30]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $customerId = (int) $this->request->getPost('customer_id');
        if (! $this->customerModel->where('id', $customerId)->where('is_active', 1)->first()) {
            return redirect()->back()->withInput()->with('error', 'Please select a valid active customer.');
        }

        $fgItemIds = array_values(array_filter(array_unique(array_map(
            'intval',
            (array) ($this->request->getPost('fg_item_ids') ?? [])
        ))));
        if ($fgItemIds === []) {
            return redirect()->back()->withInput()->with('error', 'Select at least one jewellery item for this sale.');
        }

        $db = db_connect();
        $fgRows = $this->fgRowsByIds($fgItemIds);
        if (count($fgRows) !== count($fgItemIds)) {
            return redirect()->back()->withInput()->with('error', 'One or more selected jewellery items were not found.');
        }
        foreach ($fgRows as $fg) {
            if (! $this->isSaleable($fg)) {
                return redirect()->back()->withInput()->with('error', 'Tag ' . ($fg['tag_no'] ?? '-') . ' is no longer available for sale.');
            }
        }

        $goldRate = round((float) ($this->request->getPost('gold_rate') ?: 0), 2);
        $diamondRate = round((float) ($this->request->getPost('diamond_rate') ?: 0), 2);
        $stoneRate = round((float) ($this->request->getPost('stone_rate') ?: 0), 2);
        $otherAmount = round((float) ($this->request->getPost('other_amount') ?: 0), 2);
        $roundOff = round((float) ($this->request->getPost('round_off_amount') ?: 0), 2);
        $receivedAmount = round((float) ($this->request->getPost('received_amount') ?: 0), 2);

        $totals = ['qty' => 0.0, 'gold_weight' => 0.0, 'diamond_weight' => 0.0, 'stone_weight' => 0.0];
        foreach ($fgRows as $fg) {
            $totals['qty'] += max(1, (float) ($fg['qty'] ?? 1));
            $totals['gold_weight'] += (float) ($fg['net_gold_wt'] ?? 0);
            $totals['diamond_weight'] += (float) ($fg['diamond_cts'] ?? 0);
            $totals['stone_weight'] += (float) ($fg['stone_wt'] ?? 0);
        }
        foreach ($totals as &$total) {
            $total = round($total, 3);
        }
        unset($total);

        $goldAmount = round($totals['gold_weight'] * $goldRate, 2);
        $diamondAmount = round($totals['diamond_weight'] * $diamondRate, 2);
        $stoneAmount = round($totals['stone_weight'] * $stoneRate, 2);
        $taxableAmount = round($goldAmount + $diamondAmount + $stoneAmount + $otherAmount, 2);
        if ($taxableAmount <= 0) {
            return redirect()->back()->withInput()->with('error', 'Enter rates or other charges so the invoice value is greater than zero.');
        }

        try {
            $tax = $this->taxMasterService->calculate((int) $this->request->getPost('gst_master_id'), $taxableAmount, $roundOff);
        } catch (RuntimeException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
        $totalAmount = (float) $tax['invoice_total'];
        if ($receivedAmount > $totalAmount) {
            return redirect()->back()->withInput()->with('error', 'Received amount cannot be greater than the invoice value.');
        }

        $saleDate = (string) $this->request->getPost('sale_date');
        $saleNo = $this->nextSaleNumber();
        $packingNo = $this->nextNumber('packing_lists', 'PK');
        $paymentStatus = $this->paymentStatus($totalAmount, $receivedAmount);
        $createdBy = (int) (session('admin_id') ?? 0);
        $orderIds = array_values(array_unique(array_filter(array_map(static fn (array $row): int => (int) ($row['order_id'] ?? 0), $fgRows))));
        $warehouseIds = array_values(array_unique(array_filter(array_map(static fn (array $row): int => (int) ($row['warehouse_id'] ?? 0), $fgRows))));

        $db->transStart();
        $this->packingListModel->insert([
            'packing_no' => $packingNo,
            'packing_date' => $saleDate,
            'order_id' => count($orderIds) === 1 ? $orderIds[0] : null,
            'customer_id' => $customerId,
            'warehouse_id' => count($warehouseIds) === 1 ? $warehouseIds[0] : null,
            'status' => 'Packed',
            'notes' => 'Auto-generated for wholesale sale ' . $saleNo,
            'created_by' => $createdBy,
        ]);
        $packingListId = (int) $this->packingListModel->getInsertID();

        $this->invoiceModel->insert([
            'invoice_no' => $saleNo,
            'invoice_date' => $saleDate,
            'customer_id' => $customerId,
            'order_id' => count($orderIds) === 1 ? $orderIds[0] : null,
            'packing_list_id' => $packingListId,
            'taxable_amount' => $taxableAmount,
            'gst_amount' => (float) $tax['gst_amount'],
            'total_amount' => $totalAmount,
            'status' => $paymentStatus,
            'created_by' => $createdBy,
        ]);
        $invoiceId = (int) $this->invoiceModel->getInsertID();

        $totalTaxRate = (float) (($tax['cgst_rate'] ?? 0) + ($tax['sgst_rate'] ?? 0) + ($tax['igst_rate'] ?? 0));
        $this->saleModel->insert([
            'sale_no' => $saleNo,
            'sale_date' => $saleDate,
            'showroom_id' => null,
            'showroom_counter_id' => null,
            'salesperson_employee_id' => null,
            'customer_id' => $customerId,
            'reservation_id' => null,
            'invoice_id' => $invoiceId,
            'packing_list_id' => $packingListId,
            'gst_master_id' => (int) $tax['gst_master_id'],
            'tax_breakup_json' => (string) $tax['tax_breakup_json'],
            'hsn_sac' => strtoupper(trim((string) $this->request->getPost('hsn_sac'))),
            'total_qty' => $totals['qty'],
            'total_gold_weight' => $totals['gold_weight'],
            'total_diamond_weight' => $totals['diamond_weight'],
            'total_stone_weight' => $totals['stone_weight'],
            'gold_rate' => $goldRate,
            'gold_amount' => $goldAmount,
            'diamond_rate' => $diamondRate,
            'diamond_amount' => $diamondAmount,
            'stone_rate' => $stoneRate,
            'stone_amount' => $stoneAmount,
            'other_amount' => $otherAmount,
            'taxable_amount' => $taxableAmount,
            'gst_percent' => $totalTaxRate,
            'gst_amount' => (float) $tax['gst_amount'],
            'round_off_amount' => $roundOff,
            'total_amount' => $totalAmount,
            'received_amount' => $receivedAmount,
            'payment_status' => $paymentStatus,
            'sale_status' => 'Completed',
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
            'created_by' => $createdBy,
        ]);
        $saleId = (int) $this->saleModel->getInsertID();

        $otherPerItem = round($otherAmount / count($fgRows), 2);
        $allocatedOther = 0.0;
        foreach ($fgRows as $index => $fg) {
            $fgId = (int) $fg['id'];
            $lineGoldAmount = round((float) $fg['net_gold_wt'] * $goldRate, 2);
            $lineDiamondAmount = round((float) $fg['diamond_cts'] * $diamondRate, 2);
            $lineStoneAmount = round((float) $fg['stone_wt'] * $stoneRate, 2);
            $lineOtherAmount = $index === count($fgRows) - 1 ? round($otherAmount - $allocatedOther, 2) : $otherPerItem;
            $allocatedOther += $lineOtherAmount;
            $lineAmount = round($lineGoldAmount + $lineDiamondAmount + $lineStoneAmount + $lineOtherAmount, 2);
            $lineGst = round($lineAmount * (float) ($tax['gst_amount'] ?? 0) / $taxableAmount, 2);
            $description = trim((string) ($fg['design_name'] ?? '')) ?: ('Studded Jewellery ' . ($fg['tag_no'] ?? $fgId));

            $this->invoiceItemModel->insert([
                'invoice_id' => $invoiceId,
                'fg_item_id' => $fgId,
                'description' => $description,
                'qty' => max(1, (float) ($fg['qty'] ?? 1)),
                'rate' => $lineAmount,
                'amount' => $lineAmount,
                'gst_percent' => $totalTaxRate,
                'gst_amount' => $lineGst,
            ]);
            $invoiceItemId = (int) $this->invoiceItemModel->getInsertID();

            $this->saleItemModel->insert([
                'showroom_sale_id' => $saleId,
                'fg_item_id' => $fgId,
                'order_id' => (int) ($fg['order_id'] ?? 0) ?: null,
                'invoice_item_id' => $invoiceItemId,
                'description' => $description,
                'image_path' => trim((string) ($fg['source_image_path'] ?? '')) ?: null,
                'qty' => max(1, (float) ($fg['qty'] ?? 1)),
                'rate' => $lineAmount,
                'amount' => $lineAmount,
                'gross_wt' => (float) ($fg['gross_wt'] ?? 0),
                'net_gold_wt' => (float) ($fg['net_gold_wt'] ?? 0),
                'diamond_cts' => (float) ($fg['diamond_cts'] ?? 0),
                'stone_wt' => (float) ($fg['stone_wt'] ?? 0),
                'gold_rate' => $goldRate,
                'gold_amount' => $lineGoldAmount,
                'diamond_rate' => $diamondRate,
                'diamond_amount' => $lineDiamondAmount,
                'stone_rate' => $stoneRate,
                'stone_amount' => $lineStoneAmount,
                'other_amount' => $lineOtherAmount,
                'gst_percent' => $totalTaxRate,
                'gst_amount' => $lineGst,
            ]);

            $this->packingListItemModel->insert([
                'packing_list_id' => $packingListId,
                'fg_item_id' => $fgId,
                'tag_no' => (string) ($fg['tag_no'] ?? ('FG-' . $fgId)),
                'qty' => max(1, (int) round((float) ($fg['qty'] ?? 1))),
                'gross_wt' => (float) ($fg['gross_wt'] ?? 0),
                'net_gold_wt' => (float) ($fg['net_gold_wt'] ?? 0),
                'diamond_cts' => (float) ($fg['diamond_cts'] ?? 0),
                'stone_wt' => (float) ($fg['stone_wt'] ?? 0),
            ]);

            $this->fgItemModel->update($fgId, [
                'status' => 'Sold',
                'showroom_stock_status' => 'SOLD',
                'reserved_order_id' => null,
                'inventory_remarks' => 'Sold through wholesale invoice ' . $saleNo,
                'terminal_at' => date('Y-m-d H:i:s'),
            ]);
            $this->movementModel->insert([
                'fg_item_id' => $fgId,
                'movement_type' => 'SOLD',
                'from_showroom_id' => $fg['showroom_id'] ?: null,
                'from_counter_id' => $fg['showroom_counter_id'] ?: null,
                'reference_type' => 'studded_jewellery_sale',
                'reference_id' => $saleId,
                'remarks' => 'Wholesale sale ' . $saleNo . ' / Invoice ' . $saleNo,
                'created_by' => $createdBy,
            ]);

            if ($db->tableExists('showroom_reservations')) {
                $db->table('showroom_reservations')
                    ->where('fg_item_id', $fgId)
                    ->where('reservation_status', 'Reserved')
                    ->update(['reservation_status' => 'Billed', 'released_on' => date('Y-m-d H:i:s')]);
            }
        }

        if ($receivedAmount > 0) {
            $this->customerReceiptModel->insert([
                'receipt_no' => $this->nextNumber('customer_receipts', 'SREC'),
                'receipt_date' => $saleDate,
                'customer_id' => $customerId,
                'invoice_id' => $invoiceId,
                'amount' => $receivedAmount,
                'payment_mode' => trim((string) $this->request->getPost('payment_mode')) ?: 'Bank Transfer',
                'reference_no' => trim((string) $this->request->getPost('reference_no')) ?: null,
                'notes' => 'Receipt against wholesale sale ' . $saleNo,
                'created_by' => $createdBy,
            ]);
        }

        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Unable to create the sale bill right now. No inventory was changed.');
        }

        return redirect()->to(site_url('admin/studded-jewellery/sale-bills/' . $saleId))
            ->with('success', 'Sale bill and packing list created successfully.');
    }

    public function show(int $id): string
    {
        $payload = $this->salePayload($id);
        if ($payload === null) {
            throw PageNotFoundException::forPageNotFound('Sale bill not found.');
        }
        return view('admin/showroom_sales/show', ['title' => 'Sale Bill Details'] + $payload);
    }

    public function invoice(int $id)
    {
        $payload = $this->salePayload($id);
        if ($payload === null) {
            throw PageNotFoundException::forPageNotFound('Sale bill not found.');
        }
        $payload['amountInWords'] = $this->amountInWords((float) ($payload['sale']['total_amount'] ?? 0));
        $pdf = $this->pdfService->render('pdf/wholesale_sale_invoice', $payload);
        return $this->pdfResponse($pdf, 'tax_invoice_' . ($payload['sale']['invoice_no'] ?? $id) . '.pdf');
    }

    public function packingList(int $id)
    {
        $payload = $this->salePayload($id);
        if ($payload === null) {
            throw PageNotFoundException::forPageNotFound('Sale bill not found.');
        }
        foreach ($payload['items'] as &$item) {
            $item['embedded_image'] = $this->imageDataUri((string) ($item['image_path'] ?? ''));
        }
        unset($item);
        $pdf = $this->pdfService->render('pdf/wholesale_sale_packing_list', $payload, 'A4', 'landscape');
        return $this->pdfResponse($pdf, 'packing_list_' . ($payload['sale']['packing_no'] ?? $id) . '.pdf');
    }

    /** @return array<string,mixed>|null */
    private function salePayload(int $id): ?array
    {
        $db = db_connect();
        $sale = $db->table('showroom_sales s')
            ->select('s.*, cust.name as customer_name, cust.customer_code, cust.phone as customer_phone, cust.email as customer_email, cust.gstin as customer_gstin, cust.terms_text, i.invoice_no, i.invoice_date, pl.packing_no, pl.packing_date, gm.name as gst_master_name, COALESCE(SUM(cr.amount),0) as paid_amount', false)
            ->join('customers cust', 'cust.id = s.customer_id', 'left')
            ->join('invoices i', 'i.id = s.invoice_id', 'left')
            ->join('packing_lists pl', 'pl.id = s.packing_list_id', 'left')
            ->join('gst_masters gm', 'gm.id = s.gst_master_id', 'left')
            ->join('customer_receipts cr', 'cr.invoice_id = s.invoice_id', 'left')
            ->where('s.id', $id)
            ->groupBy('s.id')
            ->get()
            ->getRowArray();
        if (! $sale) {
            return null;
        }
        $sale['payment_status'] = $this->paymentStatus((float) ($sale['total_amount'] ?? 0), (float) ($sale['paid_amount'] ?? 0));
        $sale['pending_amount'] = max(0, round((float) ($sale['total_amount'] ?? 0) - (float) ($sale['paid_amount'] ?? 0), 2));
        $sale['tax_components'] = json_decode((string) ($sale['tax_breakup_json'] ?? '[]'), true) ?: [];

        $items = $db->table('showroom_sale_items si')
            ->select('si.*, fg.tag_no, fg.production_ready_item_id, fg.purity_label, o.order_no, o.order_name')
            ->join('fg_items fg', 'fg.id = si.fg_item_id', 'left')
            ->join('orders o', 'o.id = si.order_id', 'left')
            ->where('si.showroom_sale_id', $id)
            ->orderBy('si.id', 'ASC')
            ->get()
            ->getResultArray();
        $receipts = $db->table('customer_receipts')
            ->where('invoice_id', (int) ($sale['invoice_id'] ?? 0))
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();

        return [
            'sale' => $sale,
            'items' => $items,
            'receipts' => $receipts,
            'company' => $db->table('company_settings')->orderBy('id', 'ASC')->get()->getRowArray() ?? [],
            'customerAddress' => $this->customerAddress((int) ($sale['customer_id'] ?? 0)),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function activeCustomers(): array
    {
        return $this->customerModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    /** @return list<array<string,mixed>> */
    private function saleableFgItems(): array
    {
        $rows = db_connect()->table('fg_items fg')
            ->select('fg.*, o.order_no, o.order_name, p.image_path as ready_image_path')
            ->join('orders o', 'o.id = fg.order_id', 'left')
            ->join('production_ready_items p', 'p.id = fg.production_ready_item_id', 'left')
            ->orderBy('fg.id', 'DESC')
            ->get()
            ->getResultArray();
        $rows = array_values(array_filter($rows, fn (array $row): bool => $this->isSaleable($row)));
        foreach ($rows as &$row) {
            $row['source_image_path'] = trim((string) ($row['source_image_path'] ?? '')) ?: (trim((string) ($row['ready_image_path'] ?? '')) ?: null);
            $row['image_url'] = (int) ($row['production_ready_item_id'] ?? 0) > 0 && $row['source_image_path']
                ? site_url('admin/jewellery-inventory/image/' . (int) $row['production_ready_item_id'])
                : '';
        }
        unset($row);
        return $rows;
    }

    /** @param list<int> $ids @return list<array<string,mixed>> */
    private function fgRowsByIds(array $ids): array
    {
        $rows = db_connect()->table('fg_items fg')
            ->select('fg.*, o.order_no, o.order_name, p.image_path as ready_image_path')
            ->join('orders o', 'o.id = fg.order_id', 'left')
            ->join('production_ready_items p', 'p.id = fg.production_ready_item_id', 'left')
            ->whereIn('fg.id', $ids)
            ->get()
            ->getResultArray();
        foreach ($rows as &$row) {
            $row['source_image_path'] = trim((string) ($row['source_image_path'] ?? '')) ?: (trim((string) ($row['ready_image_path'] ?? '')) ?: null);
        }
        unset($row);
        return $rows;
    }

    /** @param array<string,mixed> $row */
    private function isSaleable(array $row): bool
    {
        $status = strtoupper(trim((string) (($row['showroom_stock_status'] ?? '') ?: ($row['status'] ?? 'AVAILABLE'))));
        return ! in_array($status, ['TRANSFERRED', 'DELIVERED', 'SOLD', 'DISPATCHED', 'CANCELLED'], true);
    }

    /** @return array<string,mixed> */
    private function customerAddress(int $customerId): array
    {
        if ($customerId <= 0 || ! db_connect()->tableExists('customer_addresses')) {
            return [];
        }
        return db_connect()->table('customer_addresses')
            ->where('customer_id', $customerId)
            ->orderBy('is_default', 'DESC')
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray() ?? [];
    }

    private function nextSaleNumber(): string
    {
        $setting = db_connect()->table('company_settings')->select('sale_bill_suffix')->orderBy('id', 'ASC')->get()->getRowArray();
        $prefix = strtoupper(trim((string) ($setting['sale_bill_suffix'] ?? 'SB'))) ?: 'SB';
        return $this->nextNumber('showroom_sales', $prefix);
    }

    private function nextNumber(string $table, string $prefix): string
    {
        $last = db_connect()->table($table)->select('id')->orderBy('id', 'DESC')->get(1)->getRowArray();
        $next = ((int) ($last['id'] ?? 0)) + 1;
        return strtoupper($prefix) . '-' . date('ymd') . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function paymentStatus(float $total, float $paid): string
    {
        if ($total > 0 && $paid >= $total) {
            return 'Paid';
        }
        return $paid > 0 ? 'Partial' : 'Pending';
    }

    private function pdfResponse(string $pdf, string $filename)
    {
        $disposition = (string) $this->request->getGet('download') === '1' ? 'attachment' : 'inline';
        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', $disposition . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"')
            ->setBody($pdf);
    }

    private function imageDataUri(string $relativePath): string
    {
        $relativePath = ltrim(str_replace(['\\', '..'], ['/', ''], trim($relativePath)), '/');
        if ($relativePath === '') {
            return '';
        }
        foreach ([FCPATH . $relativePath, WRITEPATH . $relativePath] as $candidate) {
            $resolved = realpath($candidate);
            if (! $resolved || ! is_file($resolved)) {
                continue;
            }
            $mime = mime_content_type($resolved) ?: '';
            if (! str_starts_with($mime, 'image/')) {
                continue;
            }
            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($resolved));
        }
        return '';
    }

    private function amountInWords(float $amount): string
    {
        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter('en_IN', \NumberFormatter::SPELLOUT);
            $rupees = (int) floor($amount);
            $paise = (int) round(($amount - $rupees) * 100);
            $words = ucfirst((string) $formatter->format($rupees)) . ' rupees';
            if ($paise > 0) {
                $words .= ' and ' . (string) $formatter->format($paise) . ' paise';
            }
            return $words . ' only';
        }
        return 'INR ' . number_format($amount, 2) . ' only';
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
