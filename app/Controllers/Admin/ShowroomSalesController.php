<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CustomerModel;
use App\Models\CustomerReceiptModel;
use App\Models\EmployeeModel;
use App\Models\FgItemModel;
use App\Models\InvoiceItemModel;
use App\Models\InvoiceModel;
use App\Models\ShowroomCounterModel;
use App\Models\ShowroomFgMovementModel;
use App\Models\ShowroomModel;
use App\Models\ShowroomReservationModel;
use App\Models\ShowroomSaleItemModel;
use App\Models\ShowroomSaleModel;

class ShowroomSalesController extends BaseController
{
    private ShowroomSaleModel $saleModel;
    private ShowroomSaleItemModel $saleItemModel;
    private ShowroomModel $showroomModel;
    private ShowroomCounterModel $counterModel;
    private CustomerModel $customerModel;
    private EmployeeModel $employeeModel;
    private FgItemModel $fgItemModel;
    private ShowroomReservationModel $reservationModel;
    private ShowroomFgMovementModel $movementModel;
    private InvoiceModel $invoiceModel;
    private InvoiceItemModel $invoiceItemModel;
    private CustomerReceiptModel $customerReceiptModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->saleModel = new ShowroomSaleModel();
        $this->saleItemModel = new ShowroomSaleItemModel();
        $this->showroomModel = new ShowroomModel();
        $this->counterModel = new ShowroomCounterModel();
        $this->customerModel = new CustomerModel();
        $this->employeeModel = new EmployeeModel();
        $this->fgItemModel = new FgItemModel();
        $this->reservationModel = new ShowroomReservationModel();
        $this->movementModel = new ShowroomFgMovementModel();
        $this->invoiceModel = new InvoiceModel();
        $this->invoiceItemModel = new InvoiceItemModel();
        $this->customerReceiptModel = new CustomerReceiptModel();
    }

    public function index(): string
    {
        $rows = db_connect()->table('showroom_sales s')
            ->select('s.*, sh.name as showroom_name, c.counter_name, e.full_name as salesperson_name, cust.name as customer_name, i.invoice_no, COALESCE(SUM(cr.amount),0) as paid_amount', false)
            ->join('showrooms sh', 'sh.id = s.showroom_id', 'left')
            ->join('showroom_counters c', 'c.id = s.showroom_counter_id', 'left')
            ->join('employees e', 'e.id = s.salesperson_employee_id', 'left')
            ->join('customers cust', 'cust.id = s.customer_id', 'left')
            ->join('invoices i', 'i.id = s.invoice_id', 'left')
            ->join('customer_receipts cr', 'cr.invoice_id = s.invoice_id', 'left')
            ->groupBy('s.id')
            ->orderBy('s.id', 'DESC')
            ->get()
            ->getResultArray();

        $summary = db_connect()->table('showroom_sales')
            ->select('COUNT(*) as sale_count, COALESCE(SUM(total_amount),0) as total_amount, COALESCE(SUM(received_amount),0) as received_amount, COALESCE(SUM(total_qty),0) as total_qty', false)
            ->get()
            ->getRowArray() ?? [];

        return view('admin/showroom_sales/index', [
            'title' => 'Showroom Sales',
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }

    public function create(): string
    {
        return view('admin/showroom_sales/form', [
            'title' => 'Create Showroom Sale',
            'formAction' => site_url('admin/showroom-sales'),
            'showrooms' => $this->activeShowrooms(),
            'counters' => $this->activeCounters(),
            'customers' => $this->activeCustomers(),
            'salesEmployees' => $this->salesEmployees(),
            'fgItems' => $this->saleableFgItems(),
        ]);
    }

    public function store()
    {
        $rules = [
            'sale_date' => 'required|valid_date',
            'showroom_id' => 'required|integer|greater_than[0]',
            'customer_id' => 'required|integer|greater_than[0]',
            'salesperson_employee_id' => 'required|integer|greater_than[0]',
            'gst_percent' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'received_amount' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'payment_mode' => 'permit_empty|max_length[30]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $showroomId = (int) $this->request->getPost('showroom_id');
        $counterId = $this->nullableInt($this->request->getPost('showroom_counter_id'));
        $customerId = (int) $this->request->getPost('customer_id');
        $salespersonId = (int) $this->request->getPost('salesperson_employee_id');
        $gstPercent = round((float) ($this->request->getPost('gst_percent') ?: 3), 2);
        $receivedAmount = round((float) ($this->request->getPost('received_amount') ?: 0), 2);
        $lineRates = (array) ($this->request->getPost('line_rates') ?? []);
        $fgItemIds = array_values(array_filter(array_unique(array_map('intval', (array) ($this->request->getPost('fg_item_ids') ?? [])))));

        if ($fgItemIds === []) {
            return redirect()->back()->withInput()->with('error', 'Select at least one FG item for sale.');
        }

        $db = db_connect();
        $fgRows = $db->table('fg_items fg')
            ->select('fg.*, o.order_no, sr.id as active_reservation_id, sr.customer_id as reserved_customer_id, sr.reservation_status, sh.name as showroom_name, sc.counter_name')
            ->join('orders o', 'o.id = fg.order_id', 'left')
            ->join('showroom_reservations sr', "sr.fg_item_id = fg.id AND sr.reservation_status = 'Reserved'", 'left', false)
            ->join('showrooms sh', 'sh.id = fg.showroom_id', 'left')
            ->join('showroom_counters sc', 'sc.id = fg.showroom_counter_id', 'left')
            ->whereIn('fg.id', $fgItemIds)
            ->get()
            ->getResultArray();

        if (count($fgRows) !== count($fgItemIds)) {
            return redirect()->back()->withInput()->with('error', 'One or more selected FG items were not found.');
        }

        $saleLines = [];
        $taxableAmount = 0.0;
        $totalQty = 0.0;
        $reservationId = null;

        foreach ($fgRows as $fg) {
            $fgId = (int) ($fg['id'] ?? 0);
            $status = strtoupper(trim((string) ($fg['showroom_stock_status'] ?? '')));
            if ((int) ($fg['showroom_id'] ?? 0) !== $showroomId || ! in_array($status, ['SHOWROOM_AVAILABLE', 'COUNTER_AVAILABLE', 'RESERVED'], true)) {
                return redirect()->back()->withInput()->with('error', 'Selected FG item is not saleable from the chosen showroom.');
            }
            if ($counterId !== null && (int) ($fg['showroom_counter_id'] ?? 0) !== $counterId) {
                return redirect()->back()->withInput()->with('error', 'Selected FG item does not belong to the chosen counter.');
            }
            if ($status === 'RESERVED' && (int) ($fg['reserved_customer_id'] ?? 0) > 0 && (int) ($fg['reserved_customer_id'] ?? 0) !== $customerId) {
                return redirect()->back()->withInput()->with('error', 'A reserved item is linked to another customer and cannot be billed here.');
            }

            $rate = round((float) ($lineRates[$fgId] ?? 0), 2);
            if ($rate <= 0) {
                return redirect()->back()->withInput()->with('error', 'Enter a valid sale amount for every selected FG item.');
            }

            $qty = max(1, (float) ($fg['qty'] ?? 1));
            $amount = round($rate * $qty, 2);
            $lineGst = round($amount * $gstPercent / 100, 2);
            $description = 'Tag ' . (string) ($fg['tag_no'] ?? ('FG-' . $fgId));
            if (! empty($fg['order_no'])) {
                $description .= ' / Order ' . (string) $fg['order_no'];
            }

            $saleLines[] = [
                'fg' => $fg,
                'description' => $description,
                'qty' => $qty,
                'rate' => $rate,
                'amount' => $amount,
                'gst_amount' => $lineGst,
            ];
            $taxableAmount += $amount;
            $totalQty += $qty;
            if ((int) ($fg['active_reservation_id'] ?? 0) > 0 && $reservationId === null) {
                $reservationId = (int) $fg['active_reservation_id'];
            }
        }

        $gstAmount = round($taxableAmount * $gstPercent / 100, 2);
        $totalAmount = round($taxableAmount + $gstAmount, 2);
        if ($receivedAmount > $totalAmount) {
            return redirect()->back()->withInput()->with('error', 'Received amount cannot be greater than total bill amount.');
        }

        $paymentStatus = 'Pending';
        if ($receivedAmount >= $totalAmount && $totalAmount > 0) {
            $paymentStatus = 'Paid';
        } elseif ($receivedAmount > 0) {
            $paymentStatus = 'Partial';
        }

        $saleNo = $this->nextNumber('showroom_sales', 'sale_no', 'SRS');
        $invoiceNo = $this->nextNumber('invoices', 'invoice_no', 'SINV');

        $db->transStart();
        $this->invoiceModel->insert([
            'invoice_no' => $invoiceNo,
            'invoice_date' => (string) $this->request->getPost('sale_date'),
            'customer_id' => $customerId,
            'order_id' => null,
            'packing_list_id' => null,
            'taxable_amount' => round($taxableAmount, 2),
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'status' => $paymentStatus,
            'created_by' => (int) (session('admin_id') ?? 0),
        ]);
        $invoiceId = (int) $this->invoiceModel->getInsertID();

        $this->saleModel->insert([
            'sale_no' => $saleNo,
            'sale_date' => (string) $this->request->getPost('sale_date'),
            'showroom_id' => $showroomId,
            'showroom_counter_id' => $counterId,
            'salesperson_employee_id' => $salespersonId,
            'customer_id' => $customerId,
            'reservation_id' => $reservationId,
            'invoice_id' => $invoiceId,
            'total_qty' => $totalQty,
            'taxable_amount' => round($taxableAmount, 2),
            'gst_percent' => $gstPercent,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'received_amount' => $receivedAmount,
            'payment_status' => $paymentStatus,
            'sale_status' => 'Completed',
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
            'created_by' => (int) (session('admin_id') ?? 0),
        ]);
        $saleId = (int) $this->saleModel->getInsertID();

        foreach ($saleLines as $line) {
            $invoiceItemData = [
                'invoice_id' => $invoiceId,
                'fg_item_id' => (int) ($line['fg']['id'] ?? 0),
                'description' => $line['description'],
                'qty' => $line['qty'],
                'rate' => $line['rate'],
                'amount' => $line['amount'],
                'gst_percent' => $gstPercent,
                'gst_amount' => $line['gst_amount'],
            ];
            $this->invoiceItemModel->insert($invoiceItemData);
            $invoiceItemId = (int) $this->invoiceItemModel->getInsertID();

            $this->saleItemModel->insert([
                'showroom_sale_id' => $saleId,
                'fg_item_id' => (int) ($line['fg']['id'] ?? 0),
                'invoice_item_id' => $invoiceItemId,
                'description' => $line['description'],
                'qty' => $line['qty'],
                'rate' => $line['rate'],
                'amount' => $line['amount'],
                'gross_wt' => (float) ($line['fg']['gross_wt'] ?? 0),
                'net_gold_wt' => (float) ($line['fg']['net_gold_wt'] ?? 0),
                'diamond_cts' => (float) ($line['fg']['diamond_cts'] ?? 0),
                'stone_wt' => (float) ($line['fg']['stone_wt'] ?? 0),
                'gst_percent' => $gstPercent,
                'gst_amount' => $line['gst_amount'],
            ]);

            $this->fgItemModel->update((int) ($line['fg']['id'] ?? 0), [
                'status' => 'Sold',
                'showroom_stock_status' => 'SOLD',
                'reserved_order_id' => null,
            ]);

            $this->movementModel->insert([
                'fg_item_id' => (int) ($line['fg']['id'] ?? 0),
                'movement_type' => 'SHOWROOM_SOLD',
                'from_showroom_id' => $showroomId,
                'to_showroom_id' => $showroomId,
                'from_counter_id' => $counterId,
                'to_counter_id' => $counterId,
                'reference_type' => 'showroom_sale',
                'reference_id' => $saleId,
                'remarks' => 'Retail sale ' . $saleNo,
                'created_by' => (int) (session('admin_id') ?? 0),
            ]);

            $reservation = $this->reservationModel->where('fg_item_id', (int) ($line['fg']['id'] ?? 0))->where('reservation_status', 'Reserved')->orderBy('id', 'DESC')->first();
            if ($reservation) {
                $this->reservationModel->update((int) $reservation['id'], [
                    'reservation_status' => 'Billed',
                    'released_on' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        if ($receivedAmount > 0) {
            $this->customerReceiptModel->insert([
                'receipt_no' => $this->nextNumber('customer_receipts', 'receipt_no', 'SREC'),
                'receipt_date' => (string) $this->request->getPost('sale_date'),
                'customer_id' => $customerId,
                'invoice_id' => $invoiceId,
                'amount' => $receivedAmount,
                'payment_mode' => trim((string) $this->request->getPost('payment_mode')) ?: 'Cash',
                'reference_no' => trim((string) $this->request->getPost('reference_no')) ?: null,
                'notes' => 'Retail sale receipt ' . $saleNo,
                'created_by' => (int) (session('admin_id') ?? 0),
            ]);
        }

        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Unable to save showroom sale right now.');
        }

        return redirect()->to(site_url('admin/showroom-sales/' . $saleId))->with('success', 'Showroom sale created successfully.');
    }

    public function show(int $id): string
    {
        $sale = db_connect()->table('showroom_sales s')
            ->select('s.*, sh.name as showroom_name, c.counter_name, e.full_name as salesperson_name, cust.name as customer_name, cust.phone as customer_phone, i.invoice_no, i.invoice_date')
            ->join('showrooms sh', 'sh.id = s.showroom_id', 'left')
            ->join('showroom_counters c', 'c.id = s.showroom_counter_id', 'left')
            ->join('employees e', 'e.id = s.salesperson_employee_id', 'left')
            ->join('customers cust', 'cust.id = s.customer_id', 'left')
            ->join('invoices i', 'i.id = s.invoice_id', 'left')
            ->where('s.id', $id)
            ->get()
            ->getRowArray();
        if (! $sale) {
            return redirect()->to(site_url('admin/showroom-sales'))->with('error', 'Showroom sale not found.');
        }

        $items = db_connect()->table('showroom_sale_items si')
            ->select('si.*, fg.tag_no')
            ->join('fg_items fg', 'fg.id = si.fg_item_id', 'left')
            ->where('si.showroom_sale_id', $id)
            ->get()
            ->getResultArray();

        $receipts = db_connect()->table('customer_receipts')
            ->where('invoice_id', (int) ($sale['invoice_id'] ?? 0))
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();

        return view('admin/showroom_sales/show', [
            'title' => 'Showroom Sale Details',
            'sale' => $sale,
            'items' => $items,
            'receipts' => $receipts,
        ]);
    }

    private function activeShowrooms(): array
    {
        return $this->showroomModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    private function activeCounters(): array
    {
        return db_connect()->table('showroom_counters c')
            ->select('c.*, s.name as showroom_name')
            ->join('showrooms s', 's.id = c.showroom_id', 'left')
            ->where('c.is_active', 1)
            ->orderBy('s.name', 'ASC')
            ->orderBy('c.counter_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function activeCustomers(): array
    {
        return $this->customerModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    private function salesEmployees(): array
    {
        $rows = db_connect()->table('employees e')
            ->select('e.id, e.full_name, e.employee_code, d.name as designation_name, dep.name as department_name')
            ->join('designations d', 'd.id = e.designation_id', 'left')
            ->join('departments dep', 'dep.id = e.department_id', 'left')
            ->where('e.is_active', 1)
            ->groupStart()
                ->like('d.name', 'Sales')
                ->orLike('dep.name', 'Sales')
            ->groupEnd()
            ->orderBy('e.full_name', 'ASC')
            ->get()
            ->getResultArray();

        if ($rows !== []) {
            return $rows;
        }

        return db_connect()->table('employees e')
            ->select('e.id, e.full_name, e.employee_code, d.name as designation_name, dep.name as department_name')
            ->join('designations d', 'd.id = e.designation_id', 'left')
            ->join('departments dep', 'dep.id = e.department_id', 'left')
            ->where('e.is_active', 1)
            ->orderBy('e.full_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function saleableFgItems(): array
    {
        return db_connect()->table('fg_items fg')
            ->select('fg.id, fg.tag_no, fg.qty, fg.gross_wt, fg.net_gold_wt, fg.diamond_cts, fg.stone_wt, fg.showroom_id, fg.showroom_counter_id, fg.showroom_stock_status, sh.name as showroom_name, sc.counter_name, o.order_no, cust.name as reserved_customer_name')
            ->join('showrooms sh', 'sh.id = fg.showroom_id', 'left')
            ->join('showroom_counters sc', 'sc.id = fg.showroom_counter_id', 'left')
            ->join('orders o', 'o.id = fg.order_id', 'left')
            ->join('showroom_reservations sr', "sr.fg_item_id = fg.id AND sr.reservation_status = 'Reserved'", 'left', false)
            ->join('customers cust', 'cust.id = sr.customer_id', 'left')
            ->where('fg.showroom_id IS NOT NULL', null, false)
            ->whereIn('fg.showroom_stock_status', ['SHOWROOM_AVAILABLE', 'COUNTER_AVAILABLE', 'RESERVED'])
            ->orderBy('sh.name', 'ASC')
            ->orderBy('sc.counter_name', 'ASC')
            ->orderBy('fg.tag_no', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    private function nextNumber(string $table, string $column, string $prefix): string
    {
        $last = db_connect()->table($table)->select('id')->orderBy('id', 'DESC')->get(1)->getRowArray();
        $next = ((int) ($last['id'] ?? 0)) + 1;
        return strtoupper($prefix) . '-' . date('ymd') . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
