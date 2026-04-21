<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CreditNoteModel;
use App\Models\DebitNoteModel;
use App\Models\LabourBillModel;
use App\Models\LabourBillPaymentModel;
use App\Models\PurchaseBillPaymentModel;

class AccountsController extends BaseController
{
    private PurchaseBillPaymentModel $purchaseBillPaymentModel;
    private LabourBillModel $labourBillModel;
    private LabourBillPaymentModel $labourBillPaymentModel;
    private DebitNoteModel $debitNoteModel;
    private CreditNoteModel $creditNoteModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->purchaseBillPaymentModel = new PurchaseBillPaymentModel();
        $this->labourBillModel = new LabourBillModel();
        $this->labourBillPaymentModel = new LabourBillPaymentModel();
        $this->debitNoteModel = new DebitNoteModel();
        $this->creditNoteModel = new CreditNoteModel();
    }

    public function dashboard(): string
    {
        $db = db_connect();
        $purchaseRows = $this->purchaseBillsDataset();
        $labourRows = $this->labourBillsDataset();
        $saleRows = $this->saleBillsDataset();
        $debitRows = $this->debitNotesDataset();
        $creditRows = $this->creditNotesDataset();
        $outstanding = $this->outstandingSummaryDataset();
        $gstData = $this->gstReportDataset('', '');

        $cards = [
            'purchase_total' => 0.0,
            'purchase_pending' => 0.0,
            'purchase_overdue' => 0.0,
            'labour_total' => 0.0,
            'labour_pending' => 0.0,
            'labour_overdue' => 0.0,
            'sales_total' => 0.0,
            'sales_received' => 0.0,
            'sales_pending' => 0.0,
            'debit_total' => 0.0,
            'credit_total' => 0.0,
            'net_gst_payable' => (float) ($gstData['summary']['net_gst_payable'] ?? 0),
            'input_gst' => (float) ($gstData['summary']['purchase_gst'] ?? 0),
            'output_gst' => (float) ($gstData['summary']['sales_gst'] ?? 0),
            'customer_outstanding' => (float) ($outstanding['summary']['customer_outstanding'] ?? 0),
            'vendor_outstanding' => (float) ($outstanding['summary']['vendor_outstanding'] ?? 0),
            'karigar_outstanding' => (float) ($outstanding['summary']['karigar_outstanding'] ?? 0),
        ];

        foreach ($purchaseRows as $row) {
            $cards['purchase_total'] += (float) ($row['amount'] ?? 0);
            $cards['purchase_pending'] += (float) ($row['pending_amount'] ?? 0);
            if (stripos((string) ($row['days_left'] ?? ''), 'overdue') !== false) {
                $cards['purchase_overdue'] += (float) ($row['pending_amount'] ?? 0);
            }
        }

        foreach ($labourRows as $row) {
            $cards['labour_total'] += (float) ($row['total_amount'] ?? 0);
            $cards['labour_pending'] += (float) ($row['pending_amount'] ?? 0);
            if (stripos((string) ($row['days_left'] ?? ''), 'overdue') !== false) {
                $cards['labour_overdue'] += (float) ($row['pending_amount'] ?? 0);
            }
        }

        foreach ($saleRows as $row) {
            $cards['sales_total'] += (float) ($row['total_amount'] ?? 0);
            $cards['sales_received'] += (float) ($row['paid_amount'] ?? 0);
            $cards['sales_pending'] += (float) ($row['pending_amount'] ?? 0);
        }

        foreach ($debitRows as $row) {
            $cards['debit_total'] += (float) ($row['total_amount'] ?? 0);
        }

        foreach ($creditRows as $row) {
            $cards['credit_total'] += (float) ($row['total_amount'] ?? 0);
        }

        $monthlySales = [];
        if ($db->tableExists('showroom_sales')) {
            $monthlySales = $db->table('showroom_sales')
                ->select("DATE_FORMAT(sale_date, '%Y-%m') as ym, COUNT(*) as sale_count, COALESCE(SUM(total_amount),0) as total_amount, COALESCE(SUM(received_amount),0) as received_amount", false)
                ->groupBy("DATE_FORMAT(sale_date, '%Y-%m')", false)
                ->orderBy('ym', 'DESC')
                ->get(6)
                ->getResultArray();
            $monthlySales = array_reverse($monthlySales);
        }

        return view('admin/accounts/dashboard', [
            'title' => 'Accounts Dashboard',
            'cards' => $cards,
            'purchaseRows' => array_slice($purchaseRows, 0, 8),
            'labourRows' => array_slice($labourRows, 0, 8),
            'saleRows' => array_slice($saleRows, 0, 8),
            'debitRows' => array_slice($debitRows, 0, 5),
            'creditRows' => array_slice($creditRows, 0, 5),
            'monthlySales' => $monthlySales,
        ]);
    }

    public function purchaseBills(): string
    {
        $db = db_connect();
        $rows = $this->purchaseBillsDataset();
        return view('admin/accounts/purchase_bills', [
            'title' => 'Purchase Bills',
            'rows' => $rows,
            'paymentTableEnabled' => $db->tableExists('purchase_bill_payments'),
        ]);
    }

    public function updatePurchaseBillPayment()
    {
        $db = db_connect();
        if (! $db->tableExists('purchase_bill_payments')) {
            return redirect()->back()->with('error', 'Purchase payment table not available. Run migration.');
        }

        $rules = [
            'source_type' => 'required|in_list[diamond,gold,stone]',
            'source_id' => 'required|integer|greater_than[0]',
            'payment_date' => 'required|valid_date',
            'amount' => 'required|decimal|greater_than[0]',
            'reference_no' => 'permit_empty|max_length[80]',
            'notes' => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', $this->firstValidationError());
        }

        $sourceType = trim((string) $this->request->getPost('source_type'));
        $sourceId = (int) $this->request->getPost('source_id');
        $payAmount = round((float) $this->request->getPost('amount'), 2);
        $totals = $this->resolvePurchaseBillTotals($sourceType, $sourceId);
        if (! $totals['found']) {
            return redirect()->back()->with('error', 'Purchase bill not found.');
        }

        $pending = max(0, round($totals['total_amount'] - $totals['paid_amount'], 2));
        if ($pending <= 0) {
            return redirect()->back()->with('error', 'This bill is already fully paid.');
        }
        if ($payAmount > $pending + 0.001) {
            return redirect()->back()->with('error', 'Payment amount exceeds pending amount.');
        }

        $db->transStart();
        $this->purchaseBillPaymentModel->insert([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'payment_date' => (string) $this->request->getPost('payment_date'),
            'amount' => $payAmount,
            'reference_no' => $this->nullableString($this->request->getPost('reference_no')),
            'notes' => $this->nullableString($this->request->getPost('notes')),
            'created_by' => (int) session('admin_id'),
        ]);

        if ($sourceType === 'stone' && $db->tableExists('purchases') && $db->fieldExists('payment_status', 'purchases')) {
            $newPaid = round($totals['paid_amount'] + $payAmount, 2);
            $status = 'Pending';
            if ($totals['total_amount'] <= 0 || $newPaid >= $totals['total_amount']) {
                $status = 'Paid';
            } elseif ($newPaid > 0) {
                $status = 'Partial';
            }

            $db->table('purchases')->where('id', $sourceId)->update([
                'payment_status' => $status,
            ]);
        }

        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->with('error', 'Unable to update payment right now.');
        }

        return redirect()->back()->with('success', 'Purchase payment updated.');
    }

    public function labourBills(): string
    {
        $db = db_connect();
        $rows = $this->labourBillsDataset();
        return view('admin/accounts/labour_bills', [
            'title' => 'Labour Bills',
            'rows' => $rows,
            'labourTableEnabled' => $db->tableExists('labour_bills'),
        ]);
    }

    public function updateLabourBillPayment()
    {
        $db = db_connect();
        if (! $db->tableExists('labour_bills') || ! $db->tableExists('labour_bill_payments')) {
            return redirect()->back()->with('error', 'Labour bill tables not available. Run migration.');
        }

        $rules = [
            'labour_bill_id' => 'required|integer|greater_than[0]',
            'payment_date' => 'required|valid_date',
            'amount' => 'required|decimal|greater_than[0]',
            'reference_no' => 'permit_empty|max_length[80]',
            'notes' => 'permit_empty',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', $this->firstValidationError());
        }

        $billId = (int) $this->request->getPost('labour_bill_id');
        $payAmount = round((float) $this->request->getPost('amount'), 2);

        $bill = $db->table('labour_bills lb')
            ->select('lb.*, k.name as karigar_name, o.order_no, COALESCE(SUM(lbp.amount),0) as paid_amount', false)
            ->join('karigars k', 'k.id = lb.karigar_id', 'left')
            ->join('orders o', 'o.id = lb.order_id', 'left')
            ->join('labour_bill_payments lbp', 'lbp.labour_bill_id = lb.id', 'left')
            ->where('lb.id', $billId)
            ->groupBy('lb.id')
            ->get()
            ->getRowArray();

        if (! $bill) {
            return redirect()->back()->with('error', 'Labour bill not found.');
        }

        $totalAmount = (float) ($bill['total_amount'] ?? 0);
        $paidAmount = (float) ($bill['paid_amount'] ?? 0);
        $pending = max(0, round($totalAmount - $paidAmount, 2));
        if ($pending <= 0) {
            return redirect()->back()->with('error', 'This labour bill is already fully paid.');
        }
        if ($payAmount > $pending + 0.001) {
            return redirect()->back()->with('error', 'Payment amount exceeds pending amount.');
        }

        $newPaid = round($paidAmount + $payAmount, 2);
        $status = 'Pending';
        if ($totalAmount <= 0 || $newPaid >= $totalAmount) {
            $status = 'Paid';
        } elseif ($newPaid > 0) {
            $status = 'Partial';
        }

        $db->transStart();
        $this->labourBillPaymentModel->insert([
            'labour_bill_id' => $billId,
            'payment_date' => (string) $this->request->getPost('payment_date'),
            'amount' => $payAmount,
            'reference_no' => $this->nullableString($this->request->getPost('reference_no')),
            'notes' => $this->nullableString($this->request->getPost('notes')),
            'created_by' => (int) session('admin_id'),
        ]);

        $this->labourBillModel->update($billId, [
            'payment_status' => $status,
        ]);

        if ($db->tableExists('karigar_payment_ledgers')) {
            $db->table('karigar_payment_ledgers')->insert([
                'karigar_id' => (int) ($bill['karigar_id'] ?? 0),
                'order_id' => isset($bill['order_id']) ? (int) $bill['order_id'] : null,
                'entry_type' => 'payment',
                'amount' => $payAmount,
                'reference_no' => $this->nullableString($this->request->getPost('reference_no')),
                'notes' => 'Labour Bill Payment ' . (string) ($bill['bill_no'] ?? ''),
                'created_by' => (int) session('admin_id'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->with('error', 'Unable to update labour payment right now.');
        }

        return redirect()->back()->with('success', 'Labour payment updated.');
    }

    public function saleBills(): string
    {
        $db = db_connect();
        $rows = $this->saleBillsDataset();
        return view('admin/accounts/sale_bills', [
            'title' => 'Sale Bills',
            'rows' => $rows,
            'showroomSalesEnabled' => $db->tableExists('showroom_sales'),
        ]);
    }

    public function debitNotes(): string
    {
        return view('admin/accounts/debit_notes', [
            'title' => 'Debit Notes',
            'rows' => $this->debitNotesDataset(),
            'customers' => $this->customerOptions(),
            'vendors' => $this->vendorOptions(),
            'orders' => $this->orderOptions(),
            'invoices' => $this->invoiceOptions(),
            'tableEnabled' => db_connect()->tableExists('debit_notes'),
        ]);
    }

    public function storeDebitNote()
    {
        return $this->storeNote('debit');
    }

    public function creditNotes(): string
    {
        return view('admin/accounts/credit_notes', [
            'title' => 'Credit Notes',
            'rows' => $this->creditNotesDataset(),
            'customers' => $this->customerOptions(),
            'vendors' => $this->vendorOptions(),
            'orders' => $this->orderOptions(),
            'invoices' => $this->invoiceOptions(),
            'tableEnabled' => db_connect()->tableExists('credit_notes'),
        ]);
    }

    public function storeCreditNote()
    {
        return $this->storeNote('credit');
    }

    public function gstReport(): string
    {
        $dateFrom = trim((string) $this->request->getGet('date_from'));
        $dateTo = trim((string) $this->request->getGet('date_to'));
        $data = $this->gstReportDataset($dateFrom, $dateTo);

        return view('admin/accounts/gst_report', [
            'title' => 'GST Report',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'summary' => $data['summary'],
            'salesRows' => $data['sales_rows'],
            'purchaseRows' => $data['purchase_rows'],
            'adjustmentRows' => $data['adjustment_rows'],
        ]);
    }

    public function outstandingSummary(): string
    {
        $data = $this->outstandingSummaryDataset();

        return view('admin/accounts/outstanding_summary', [
            'title' => 'Outstanding Summary',
            'summary' => $data['summary'],
            'customerRows' => $data['customer_rows'],
            'vendorRows' => $data['vendor_rows'],
            'karigarRows' => $data['karigar_rows'],
        ]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function purchaseBillsDataset(): array
    {
        $db = db_connect();
        $rows = [];
        $paymentMap = $this->purchasePaymentMap();
        $diamondAttachmentMap = $this->diamondAttachmentMap();
        $stoneAttachmentMap = $this->stoneAttachmentMap();

        if ($db->tableExists('purchase_headers') && $db->tableExists('purchase_lines')) {
            $diamondRows = $db->table('purchase_headers ph')
                ->select('ph.id, ph.purchase_date, ph.due_date, MAX(ph.invoice_no) as invoice_no, MAX(v.name) as vendor_name, MAX(ph.supplier_name) as supplier_name, COUNT(pl.id) as qty, COALESCE(SUM(pl.carat), 0) as total_weight, COALESCE(SUM(pl.line_value), 0) as subtotal, MAX(ph.invoice_total) as invoice_total', false)
                ->join('purchase_lines pl', 'pl.purchase_id = ph.id', 'left')
                ->join('vendors v', 'v.id = ph.vendor_id', 'left')
                ->groupBy('ph.id')
                ->orderBy('ph.id', 'DESC')
                ->get()
                ->getResultArray();

            foreach ($diamondRows as $row) {
                $sourceId = (int) ($row['id'] ?? 0);
                $total = (float) ($row['invoice_total'] ?? 0);
                if ($total <= 0) {
                    $total = (float) ($row['subtotal'] ?? 0);
                }
                $paid = (float) ($paymentMap['diamond:' . $sourceId] ?? 0);
                $statusInfo = $this->paymentStatusInfo($total, $paid, false);
                $attachment = $diamondAttachmentMap[$sourceId] ?? null;

                $rows[] = [
                    'source_type' => 'diamond',
                    'source_id' => $sourceId,
                    'supplier_name' => trim((string) ($row['vendor_name'] ?: $row['supplier_name'] ?: '-')),
                    'purchase_date' => (string) ($row['purchase_date'] ?? ''),
                    'category' => 'Diamond',
                    'qty' => (float) ($row['qty'] ?? 0),
                    'weight_value' => (float) ($row['total_weight'] ?? 0),
                    'weight_unit' => 'cts',
                    'amount' => round($total, 2),
                    'due_date' => (string) ($row['due_date'] ?? ''),
                    'days_left' => $this->daysLeftLabel((string) ($row['due_date'] ?? ''), $statusInfo['status']),
                    'payment_status' => $statusInfo['status'],
                    'paid_amount' => $statusInfo['paid_amount'],
                    'pending_amount' => $statusInfo['pending_amount'],
                    'attachment' => $attachment,
                    'view_url' => site_url('admin/diamond-inventory/purchases/view/' . $sourceId),
                ];
            }
        }

        if ($db->tableExists('gold_inventory_purchase_headers') && $db->tableExists('gold_inventory_purchase_lines')) {
            $goldRows = $db->table('gold_inventory_purchase_headers ph')
                ->select('ph.id, ph.purchase_date, MAX(ph.invoice_no) as invoice_no, MAX(ph.supplier_name) as supplier_name, COUNT(pl.id) as qty, COALESCE(SUM(pl.weight_gm), 0) as total_weight, COALESCE(SUM(pl.line_value), 0) as total_value', false)
                ->join('gold_inventory_purchase_lines pl', 'pl.purchase_id = ph.id', 'left')
                ->groupBy('ph.id')
                ->orderBy('ph.id', 'DESC')
                ->get()
                ->getResultArray();

            foreach ($goldRows as $row) {
                $sourceId = (int) ($row['id'] ?? 0);
                $total = (float) ($row['total_value'] ?? 0);
                $paid = (float) ($paymentMap['gold:' . $sourceId] ?? 0);
                $statusInfo = $this->paymentStatusInfo($total, $paid, true);

                $rows[] = [
                    'source_type' => 'gold',
                    'source_id' => $sourceId,
                    'supplier_name' => trim((string) ($row['supplier_name'] ?: '-')),
                    'purchase_date' => (string) ($row['purchase_date'] ?? ''),
                    'category' => 'Gold',
                    'qty' => (float) ($row['qty'] ?? 0),
                    'weight_value' => (float) ($row['total_weight'] ?? 0),
                    'weight_unit' => 'gm',
                    'amount' => round($total, 2),
                    'due_date' => '',
                    'days_left' => '-',
                    'payment_status' => $statusInfo['status'],
                    'paid_amount' => $statusInfo['paid_amount'],
                    'pending_amount' => $statusInfo['pending_amount'],
                    'attachment' => null,
                    'view_url' => site_url('admin/gold-inventory/purchases/view/' . $sourceId),
                ];
            }
        }

        if ($db->tableExists('stone_inventory_purchase_headers') && $db->tableExists('stone_inventory_purchase_lines')) {
            $stoneRows = $db->table('stone_inventory_purchase_headers ph')
                ->select('ph.id, ph.purchase_date, ph.due_date, MAX(ph.invoice_no) as invoice_no, MAX(v.name) as vendor_name, MAX(ph.supplier_name) as supplier_name, COUNT(pl.id) as qty, COALESCE(SUM(pl.qty), 0) as total_weight, COALESCE(SUM(pl.line_value), 0) as subtotal, MAX(ph.invoice_total) as invoice_total', false)
                ->join('stone_inventory_purchase_lines pl', 'pl.purchase_id = ph.id', 'left')
                ->join('vendors v', 'v.id = ph.vendor_id', 'left')
                ->groupBy('ph.id')
                ->orderBy('ph.id', 'DESC')
                ->get()
                ->getResultArray();

            foreach ($stoneRows as $row) {
                $sourceId = (int) ($row['id'] ?? 0);
                $total = (float) ($row['invoice_total'] ?? 0);
                if ($total <= 0) {
                    $total = (float) ($row['subtotal'] ?? 0);
                }
                $paid = (float) ($paymentMap['stone:' . $sourceId] ?? 0);
                $statusInfo = $this->paymentStatusInfo($total, $paid, false);
                $attachment = $stoneAttachmentMap[$sourceId] ?? null;

                $rows[] = [
                    'source_type' => 'stone',
                    'source_id' => $sourceId,
                    'supplier_name' => trim((string) ($row['vendor_name'] ?: $row['supplier_name'] ?: '-')),
                    'purchase_date' => (string) ($row['purchase_date'] ?? ''),
                    'category' => 'Stone',
                    'qty' => (float) ($row['qty'] ?? 0),
                    'weight_value' => (float) ($row['total_weight'] ?? 0),
                    'weight_unit' => 'qty',
                    'amount' => round($total, 2),
                    'due_date' => (string) ($row['due_date'] ?? ''),
                    'days_left' => $this->daysLeftLabel((string) ($row['due_date'] ?? ''), $statusInfo['status']),
                    'payment_status' => $statusInfo['status'],
                    'paid_amount' => $statusInfo['paid_amount'],
                    'pending_amount' => $statusInfo['pending_amount'],
                    'attachment' => $attachment,
                    'view_url' => site_url('admin/stone-inventory/purchases/view/' . $sourceId),
                ];
            }
        } elseif ($db->tableExists('purchases')) {
            $stoneRows = $db->table('purchases p')
                ->select('p.id, p.purchase_date, p.payment_due_date as due_date, p.invoice_no, p.invoice_amount, p.payment_status as legacy_payment_status, MAX(v.name) as vendor_name, COUNT(pi.id) as qty, COALESCE(SUM(pi.cts), 0) as total_weight', false)
                ->join('purchase_items pi', 'pi.purchase_id = p.id', 'left')
                ->join('vendors v', 'v.id = p.vendor_id', 'left')
                ->where('p.purchase_type', 'Stone')
                ->groupBy('p.id')
                ->orderBy('p.id', 'DESC')
                ->get()
                ->getResultArray();

            foreach ($stoneRows as $row) {
                $sourceId = (int) ($row['id'] ?? 0);
                $total = (float) ($row['invoice_amount'] ?? 0);
                $paid = (float) ($paymentMap['stone:' . $sourceId] ?? 0);
                if ($paid <= 0 && strcasecmp((string) ($row['legacy_payment_status'] ?? ''), 'paid') === 0) {
                    $paid = $total;
                }
                $statusInfo = $this->paymentStatusInfo($total, $paid, false);

                $rows[] = [
                    'source_type' => 'stone',
                    'source_id' => $sourceId,
                    'supplier_name' => trim((string) ($row['vendor_name'] ?: '-')),
                    'purchase_date' => (string) ($row['purchase_date'] ?? ''),
                    'category' => 'Stone',
                    'qty' => (float) ($row['qty'] ?? 0),
                    'weight_value' => (float) ($row['total_weight'] ?? 0),
                    'weight_unit' => 'cts',
                    'amount' => round($total, 2),
                    'due_date' => (string) ($row['due_date'] ?? ''),
                    'days_left' => $this->daysLeftLabel((string) ($row['due_date'] ?? ''), $statusInfo['status']),
                    'payment_status' => $statusInfo['status'],
                    'paid_amount' => $statusInfo['paid_amount'],
                    'pending_amount' => $statusInfo['pending_amount'],
                    'attachment' => null,
                    'view_url' => null,
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $dateCmp = strcmp((string) ($b['purchase_date'] ?? ''), (string) ($a['purchase_date'] ?? ''));
            if ($dateCmp !== 0) {
                return $dateCmp;
            }
            return (int) ($b['source_id'] ?? 0) <=> (int) ($a['source_id'] ?? 0);
        });

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function labourBillsDataset(): array
    {
        $db = db_connect();
        $rows = [];

        if ($db->tableExists('labour_bills')) {
            $list = $db->table('labour_bills lb')
                ->select('lb.*, k.name as karigar_name, o.order_no, COALESCE(SUM(lbp.amount),0) as paid_amount', false)
                ->join('karigars k', 'k.id = lb.karigar_id', 'left')
                ->join('orders o', 'o.id = lb.order_id', 'left')
                ->join('labour_bill_payments lbp', 'lbp.labour_bill_id = lb.id', 'left')
                ->groupBy('lb.id')
                ->orderBy('lb.id', 'DESC')
                ->get()
                ->getResultArray();

            foreach ($list as $row) {
                $totalAmount = (float) ($row['total_amount'] ?? 0);
                $paidAmount = (float) ($row['paid_amount'] ?? 0);
                $statusInfo = $this->paymentStatusInfo($totalAmount, $paidAmount, false);

                $rows[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'bill_no' => (string) ($row['bill_no'] ?? ''),
                    'bill_date' => (string) ($row['bill_date'] ?? ''),
                    'order_no' => (string) ($row['order_no'] ?? '-'),
                    'karigar_name' => (string) ($row['karigar_name'] ?? '-'),
                    'gold_weight_gm' => (float) ($row['gold_weight_gm'] ?? 0),
                    'rate_per_gm' => (float) ($row['rate_per_gm'] ?? 0),
                    'labour_amount' => (float) ($row['labour_amount'] ?? 0),
                    'other_amount' => (float) ($row['other_amount'] ?? 0),
                    'total_amount' => $totalAmount,
                    'paid_amount' => $statusInfo['paid_amount'],
                    'pending_amount' => $statusInfo['pending_amount'],
                    'due_date' => (string) ($row['due_date'] ?? ''),
                    'days_left' => $this->daysLeftLabel((string) ($row['due_date'] ?? ''), $statusInfo['status']),
                    'payment_status' => $statusInfo['status'],
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function saleBillsDataset(): array
    {
        $db = db_connect();
        $rows = [];

        if ($db->tableExists('showroom_sales')) {
            $list = $db->table('showroom_sales s')
                ->select('s.*, sh.name as showroom_name, c.counter_name, e.full_name as salesperson_name, cust.name as customer_name, i.invoice_no, i.invoice_date, COALESCE(SUM(cr.amount),0) as paid_amount', false)
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

            foreach ($list as $row) {
                $totalAmount = (float) ($row['total_amount'] ?? 0);
                $paidAmount = (float) ($row['paid_amount'] ?? 0);
                $statusInfo = $this->paymentStatusInfo($totalAmount, $paidAmount, false);

                $rows[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'sale_no' => (string) ($row['sale_no'] ?? ''),
                    'sale_date' => (string) ($row['sale_date'] ?? ''),
                    'showroom_name' => (string) ($row['showroom_name'] ?? '-'),
                    'counter_name' => (string) ($row['counter_name'] ?? '-'),
                    'salesperson_name' => (string) ($row['salesperson_name'] ?? '-'),
                    'customer_name' => (string) ($row['customer_name'] ?? '-'),
                    'invoice_no' => (string) ($row['invoice_no'] ?? '-'),
                    'total_qty' => (float) ($row['total_qty'] ?? 0),
                    'total_amount' => $totalAmount,
                    'paid_amount' => $statusInfo['paid_amount'],
                    'pending_amount' => $statusInfo['pending_amount'],
                    'payment_status' => $statusInfo['status'],
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function debitNotesDataset(): array
    {
        return $this->noteDataset('debit');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function creditNotesDataset(): array
    {
        return $this->noteDataset('credit');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function noteDataset(string $type): array
    {
        $db = db_connect();
        $table = $type === 'credit' ? 'credit_notes' : 'debit_notes';
        if (! $db->tableExists($table)) {
            return [];
        }

        $rows = $db->table($table . ' n')
            ->select('n.*, c.name as customer_name, v.name as vendor_name, o.order_no, i.invoice_no', false)
            ->join('customers c', 'c.id = n.customer_id', 'left')
            ->join('vendors v', 'v.id = n.vendor_id', 'left')
            ->join('orders o', 'o.id = n.order_id', 'left')
            ->join('invoices i', 'i.id = n.invoice_id', 'left')
            ->orderBy('n.note_date', 'DESC')
            ->orderBy('n.id', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['party_name'] = $row['party_type'] === 'vendor'
                ? (string) ($row['vendor_name'] ?? '-')
                : (string) ($row['customer_name'] ?? '-');
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array{
     *     summary:array<string,float>,
     *     sales_rows:list<array<string,mixed>>,
     *     purchase_rows:list<array<string,mixed>>,
     *     adjustment_rows:list<array<string,mixed>>
     * }
     */
    private function gstReportDataset(string $dateFrom, string $dateTo): array
    {
        $db = db_connect();
        $salesRows = [];
        $purchaseRows = [];
        $adjustmentRows = [];

        if ($db->tableExists('invoices')) {
            $builder = $db->table('invoices i')
                ->select('i.id, i.invoice_no, i.invoice_date, i.taxable_amount, i.gst_amount, i.total_amount, c.name as customer_name, c.gstin, MAX(ss.sale_no) as sale_no, COALESCE(SUM(cr.amount),0) as received_amount', false)
                ->join('customers c', 'c.id = i.customer_id', 'left')
                ->join('showroom_sales ss', 'ss.invoice_id = i.id', 'left')
                ->join('customer_receipts cr', 'cr.invoice_id = i.id', 'left')
                ->groupBy('i.id');
            $this->applyDateFilter($builder, 'i.invoice_date', $dateFrom, $dateTo);
            $salesRows = $builder->orderBy('i.invoice_date', 'DESC')->get()->getResultArray();
        }

        if ($db->tableExists('purchase_headers') && $db->tableExists('purchase_lines')) {
            $builder = $db->table('purchase_headers ph')
                ->select("'Diamond Purchase' as source_label, ph.invoice_no, ph.purchase_date as invoice_date, COALESCE(MAX(v.name), MAX(ph.supplier_name), '-') as party_name, MAX(v.gstin) as gstin, COALESCE(SUM(pl.line_value),0) as taxable_amount, MAX(ph.tax_percentage) as gst_percent, CASE WHEN MAX(ph.invoice_total) > 0 THEN MAX(ph.invoice_total) - COALESCE(SUM(pl.line_value),0) ELSE 0 END as gst_amount, MAX(ph.invoice_total) as total_amount", false)
                ->join('purchase_lines pl', 'pl.purchase_id = ph.id', 'left')
                ->join('vendors v', 'v.id = ph.vendor_id', 'left')
                ->groupBy('ph.id');
            $this->applyDateFilter($builder, 'ph.purchase_date', $dateFrom, $dateTo);
            $purchaseRows = array_merge($purchaseRows, $builder->orderBy('ph.purchase_date', 'DESC')->get()->getResultArray());
        }

        if ($db->tableExists('stone_inventory_purchase_headers') && $db->tableExists('stone_inventory_purchase_lines')) {
            $builder = $db->table('stone_inventory_purchase_headers ph')
                ->select("'Stone Purchase' as source_label, ph.invoice_no, ph.purchase_date as invoice_date, COALESCE(MAX(v.name), MAX(ph.supplier_name), '-') as party_name, MAX(v.gstin) as gstin, COALESCE(SUM(pl.line_value),0) as taxable_amount, MAX(ph.tax_percentage) as gst_percent, CASE WHEN MAX(ph.invoice_total) > 0 THEN MAX(ph.invoice_total) - COALESCE(SUM(pl.line_value),0) ELSE 0 END as gst_amount, MAX(ph.invoice_total) as total_amount", false)
                ->join('stone_inventory_purchase_lines pl', 'pl.purchase_id = ph.id', 'left')
                ->join('vendors v', 'v.id = ph.vendor_id', 'left')
                ->groupBy('ph.id');
            $this->applyDateFilter($builder, 'ph.purchase_date', $dateFrom, $dateTo);
            $purchaseRows = array_merge($purchaseRows, $builder->orderBy('ph.purchase_date', 'DESC')->get()->getResultArray());
        }

        if ($db->tableExists('purchase_invoices')) {
            $builder = $db->table('purchase_invoices pi')
                ->select("'Purchase Invoice' as source_label, pi.invoice_no, pi.invoice_date, COALESCE(v.name, '-') as party_name, v.gstin, pi.taxable_amount, CASE WHEN pi.taxable_amount > 0 THEN ROUND((pi.gst_amount / pi.taxable_amount) * 100, 2) ELSE 0 END as gst_percent, pi.gst_amount, pi.total_amount", false)
                ->join('vendors v', 'v.id = pi.vendor_id', 'left');
            $this->applyDateFilter($builder, 'pi.invoice_date', $dateFrom, $dateTo);
            $purchaseRows = array_merge($purchaseRows, $builder->orderBy('pi.invoice_date', 'DESC')->get()->getResultArray());
        }

        $adjustmentRows = array_merge(
            $this->gstAdjustmentRows('debit', $dateFrom, $dateTo),
            $this->gstAdjustmentRows('credit', $dateFrom, $dateTo)
        );

        usort($purchaseRows, static function (array $a, array $b): int {
            return strcmp((string) ($b['invoice_date'] ?? ''), (string) ($a['invoice_date'] ?? ''));
        });
        usort($adjustmentRows, static function (array $a, array $b): int {
            return strcmp((string) ($b['note_date'] ?? ''), (string) ($a['note_date'] ?? ''));
        });

        $summary = [
            'sales_taxable' => 0.0,
            'sales_gst' => 0.0,
            'purchase_taxable' => 0.0,
            'purchase_gst' => 0.0,
            'sales_debit_gst' => 0.0,
            'sales_credit_gst' => 0.0,
            'purchase_debit_gst' => 0.0,
            'purchase_credit_gst' => 0.0,
            'net_gst_payable' => 0.0,
        ];

        foreach ($salesRows as $row) {
            $summary['sales_taxable'] += (float) ($row['taxable_amount'] ?? 0);
            $summary['sales_gst'] += (float) ($row['gst_amount'] ?? 0);
        }
        foreach ($purchaseRows as $row) {
            $summary['purchase_taxable'] += (float) ($row['taxable_amount'] ?? 0);
            $summary['purchase_gst'] += (float) ($row['gst_amount'] ?? 0);
        }
        foreach ($adjustmentRows as $row) {
            $bucket = (string) ($row['bucket'] ?? '');
            $summary[$bucket] += (float) ($row['gst_amount'] ?? 0);
        }

        $summary['net_gst_payable'] = round(
            ($summary['sales_gst'] + $summary['sales_debit_gst'] - $summary['sales_credit_gst'])
            - ($summary['purchase_gst'] + $summary['purchase_credit_gst'] - $summary['purchase_debit_gst']),
            2
        );

        return [
            'summary' => $summary,
            'sales_rows' => $salesRows,
            'purchase_rows' => $purchaseRows,
            'adjustment_rows' => $adjustmentRows,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function gstAdjustmentRows(string $type, string $dateFrom, string $dateTo): array
    {
        $db = db_connect();
        $table = $type === 'credit' ? 'credit_notes' : 'debit_notes';
        if (! $db->tableExists($table)) {
            return [];
        }

        $builder = $db->table($table . ' n')
            ->select('n.note_no, n.note_date, n.party_type, n.taxable_amount, n.gst_percent, n.gst_amount, n.total_amount, COALESCE(c.name, v.name, "-") as party_name', false)
            ->join('customers c', 'c.id = n.customer_id', 'left')
            ->join('vendors v', 'v.id = n.vendor_id', 'left');
        $this->applyDateFilter($builder, 'n.note_date', $dateFrom, $dateTo);

        $rows = $builder->orderBy('n.note_date', 'DESC')->get()->getResultArray();
        foreach ($rows as &$row) {
            $bucket = 'sales_credit_gst';
            if ((string) ($row['party_type'] ?? '') === 'vendor') {
                $bucket = $type === 'credit' ? 'purchase_credit_gst' : 'purchase_debit_gst';
            } else {
                $bucket = $type === 'credit' ? 'sales_credit_gst' : 'sales_debit_gst';
            }
            $row['note_type'] = ucfirst($type);
            $row['bucket'] = $bucket;
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array{
     *     summary:array<string,float>,
     *     customer_rows:list<array<string,mixed>>,
     *     vendor_rows:list<array<string,mixed>>,
     *     karigar_rows:list<array<string,mixed>>
     * }
     */
    private function outstandingSummaryDataset(): array
    {
        $customerMap = [];
        foreach ($this->saleBillsDataset() as $row) {
            $key = trim((string) ($row['customer_name'] ?? '-'));
            if (! isset($customerMap[$key])) {
                $customerMap[$key] = ['party_name' => $key, 'bill_count' => 0, 'amount' => 0.0, 'paid' => 0.0, 'pending' => 0.0];
            }
            $customerMap[$key]['bill_count']++;
            $customerMap[$key]['amount'] += (float) ($row['total_amount'] ?? 0);
            $customerMap[$key]['paid'] += (float) ($row['paid_amount'] ?? 0);
            $customerMap[$key]['pending'] += (float) ($row['pending_amount'] ?? 0);
        }

        $vendorMap = [];
        foreach ($this->purchaseBillsDataset() as $row) {
            $key = trim((string) ($row['supplier_name'] ?? '-'));
            if (! isset($vendorMap[$key])) {
                $vendorMap[$key] = ['party_name' => $key, 'bill_count' => 0, 'amount' => 0.0, 'paid' => 0.0, 'pending' => 0.0];
            }
            $vendorMap[$key]['bill_count']++;
            $vendorMap[$key]['amount'] += (float) ($row['amount'] ?? 0);
            $vendorMap[$key]['paid'] += (float) ($row['paid_amount'] ?? 0);
            $vendorMap[$key]['pending'] += (float) ($row['pending_amount'] ?? 0);
        }

        $karigarMap = [];
        foreach ($this->labourBillsDataset() as $row) {
            $key = trim((string) ($row['karigar_name'] ?? '-'));
            if (! isset($karigarMap[$key])) {
                $karigarMap[$key] = ['party_name' => $key, 'bill_count' => 0, 'amount' => 0.0, 'paid' => 0.0, 'pending' => 0.0];
            }
            $karigarMap[$key]['bill_count']++;
            $karigarMap[$key]['amount'] += (float) ($row['total_amount'] ?? 0);
            $karigarMap[$key]['paid'] += (float) ($row['paid_amount'] ?? 0);
            $karigarMap[$key]['pending'] += (float) ($row['pending_amount'] ?? 0);
        }

        $customerRows = array_values($customerMap);
        $vendorRows = array_values($vendorMap);
        $karigarRows = array_values($karigarMap);

        usort($customerRows, static fn(array $a, array $b): int => $b['pending'] <=> $a['pending']);
        usort($vendorRows, static fn(array $a, array $b): int => $b['pending'] <=> $a['pending']);
        usort($karigarRows, static fn(array $a, array $b): int => $b['pending'] <=> $a['pending']);

        return [
            'summary' => [
                'customer_outstanding' => array_sum(array_column($customerRows, 'pending')),
                'vendor_outstanding' => array_sum(array_column($vendorRows, 'pending')),
                'karigar_outstanding' => array_sum(array_column($karigarRows, 'pending')),
            ],
            'customer_rows' => $customerRows,
            'vendor_rows' => $vendorRows,
            'karigar_rows' => $karigarRows,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function purchasePaymentMap(): array
    {
        $db = db_connect();
        if (! $db->tableExists('purchase_bill_payments')) {
            return [];
        }

        $rows = $db->table('purchase_bill_payments')
            ->select('source_type, source_id, COALESCE(SUM(amount),0) as total_paid', false)
            ->groupBy('source_type, source_id')
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $key = strtolower((string) ($row['source_type'] ?? '')) . ':' . (int) ($row['source_id'] ?? 0);
            $map[$key] = (float) ($row['total_paid'] ?? 0);
        }

        return $map;
    }

    /**
     * @return array<int, array{count:int,file_path:string,file_name:string}>
     */
    private function diamondAttachmentMap(): array
    {
        $db = db_connect();
        if (! $db->tableExists('diamond_purchase_attachments')) {
            return [];
        }

        $rows = $db->table('diamond_purchase_attachments')
            ->select('purchase_id, file_path, file_name')
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $purchaseId = (int) ($row['purchase_id'] ?? 0);
            if ($purchaseId <= 0) {
                continue;
            }

            if (! isset($map[$purchaseId])) {
                $map[$purchaseId] = [
                    'count' => 0,
                    'file_path' => (string) ($row['file_path'] ?? ''),
                    'file_name' => (string) ($row['file_name'] ?? ''),
                ];
            }
            $map[$purchaseId]['count']++;
        }

        return $map;
    }

    /**
     * @return array<int, array{count:int,file_path:string,file_name:string}>
     */
    private function stoneAttachmentMap(): array
    {
        $db = db_connect();
        if (! $db->tableExists('stone_purchase_attachments')) {
            return [];
        }

        $rows = $db->table('stone_purchase_attachments')
            ->select('purchase_id, file_path, file_name')
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $purchaseId = (int) ($row['purchase_id'] ?? 0);
            if ($purchaseId <= 0) {
                continue;
            }

            if (! isset($map[$purchaseId])) {
                $map[$purchaseId] = [
                    'count' => 0,
                    'file_path' => (string) ($row['file_path'] ?? ''),
                    'file_name' => (string) ($row['file_name'] ?? ''),
                ];
            }
            $map[$purchaseId]['count']++;
        }

        return $map;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function customerOptions(): array
    {
        $db = db_connect();
        if (! $db->tableExists('customers')) {
            return [];
        }

        return $db->table('customers')
            ->select('id, name, phone, gstin')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function vendorOptions(): array
    {
        $db = db_connect();
        if (! $db->tableExists('vendors')) {
            return [];
        }

        return $db->table('vendors')
            ->select('id, name, phone, gstin')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function orderOptions(): array
    {
        $db = db_connect();
        if (! $db->tableExists('orders')) {
            return [];
        }

        return $db->table('orders')
            ->select('id, order_no')
            ->orderBy('id', 'DESC')
            ->get(300)
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function invoiceOptions(): array
    {
        $db = db_connect();
        if (! $db->tableExists('invoices')) {
            return [];
        }

        return $db->table('invoices')
            ->select('id, invoice_no')
            ->orderBy('id', 'DESC')
            ->get(300)
            ->getResultArray();
    }

    private function storeNote(string $type)
    {
        $table = $type === 'credit' ? 'credit_notes' : 'debit_notes';
        $db = db_connect();
        if (! $db->tableExists($table)) {
            return redirect()->back()->with('error', ucfirst($type) . ' note table not available. Run migration.');
        }

        $rules = [
            'note_date' => 'required|valid_date',
            'party_type' => 'required|in_list[customer,vendor]',
            'customer_id' => 'permit_empty|integer',
            'vendor_id' => 'permit_empty|integer',
            'order_id' => 'permit_empty|integer',
            'invoice_id' => 'permit_empty|integer',
            'reference_no' => 'permit_empty|max_length[80]',
            'reason' => 'required|max_length[255]',
            'taxable_amount' => 'required|decimal|greater_than_equal_to[0]',
            'gst_percent' => 'required|decimal|greater_than_equal_to[0]',
            'gst_amount' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'total_amount' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'status' => 'permit_empty|max_length[20]',
            'notes' => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $partyType = (string) $this->request->getPost('party_type');
        $customerId = (int) $this->request->getPost('customer_id');
        $vendorId = (int) $this->request->getPost('vendor_id');
        if ($partyType === 'customer' && $customerId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Customer is required for customer note.');
        }
        if ($partyType === 'vendor' && $vendorId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Vendor is required for vendor note.');
        }

        $taxableAmount = round((float) $this->request->getPost('taxable_amount'), 2);
        $gstPercent = round((float) $this->request->getPost('gst_percent'), 2);
        $gstAmount = $this->request->getPost('gst_amount');
        $gstAmount = $gstAmount === null || $gstAmount === '' ? round(($taxableAmount * $gstPercent) / 100, 2) : round((float) $gstAmount, 2);
        $totalAmount = $this->request->getPost('total_amount');
        $totalAmount = $totalAmount === null || $totalAmount === '' ? round($taxableAmount + $gstAmount, 2) : round((float) $totalAmount, 2);

        $payload = [
            'note_no' => $this->generateNoteNumber($type),
            'note_date' => (string) $this->request->getPost('note_date'),
            'party_type' => $partyType,
            'customer_id' => $partyType === 'customer' ? $customerId : null,
            'vendor_id' => $partyType === 'vendor' ? $vendorId : null,
            'order_id' => $this->nullableInt($this->request->getPost('order_id')),
            'invoice_id' => $this->nullableInt($this->request->getPost('invoice_id')),
            'reference_no' => $this->nullableString($this->request->getPost('reference_no')),
            'reason' => $this->nullableString($this->request->getPost('reason')),
            'taxable_amount' => $taxableAmount,
            'gst_percent' => $gstPercent,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'status' => $this->nullableString($this->request->getPost('status')) ?: 'Posted',
            'notes' => $this->nullableString($this->request->getPost('notes')),
            'created_by' => (int) session('admin_id'),
        ];

        $ok = $type === 'credit'
            ? $this->creditNoteModel->insert($payload)
            : $this->debitNoteModel->insert($payload);

        if (! $ok) {
            return redirect()->back()->withInput()->with('error', 'Unable to save ' . $type . ' note right now.');
        }

        return redirect()->back()->with('success', ucfirst($type) . ' note saved successfully.');
    }

    private function generateNoteNumber(string $type): string
    {
        $model = $type === 'credit' ? $this->creditNoteModel : $this->debitNoteModel;
        $prefix = $type === 'credit' ? 'CN' : 'DN';
        $count = $model->countAllResults() + 1;

        return sprintf('%s-%s-%04d', $prefix, date('Ymd'), $count);
    }

    private function applyDateFilter($builder, string $column, string $dateFrom, string $dateTo): void
    {
        if ($dateFrom !== '') {
            $builder->where($column . ' >=', $dateFrom);
        }
        if ($dateTo !== '') {
            $builder->where($column . ' <=', $dateTo);
        }
    }

    /**
     * @return array{status:string,paid_amount:float,pending_amount:float}
     */
    private function paymentStatusInfo(float $totalAmount, float $paidAmount, bool $defaultPaid): array
    {
        $totalAmount = round(max(0, $totalAmount), 2);
        $paidAmount = round(max(0, $paidAmount), 2);

        if ($defaultPaid && $paidAmount <= 0 && $totalAmount > 0) {
            $paidAmount = $totalAmount;
        }

        $pending = max(0, round($totalAmount - $paidAmount, 2));
        $status = 'Pending';
        if ($totalAmount <= 0 || $pending <= 0) {
            $status = 'Paid';
        } elseif ($paidAmount > 0) {
            $status = 'Partial';
        }

        return [
            'status' => $status,
            'paid_amount' => $paidAmount,
            'pending_amount' => $pending,
        ];
    }

    /**
     * @return array{found:bool,total_amount:float,paid_amount:float}
     */
    private function resolvePurchaseBillTotals(string $sourceType, int $sourceId): array
    {
        $sourceType = strtolower(trim($sourceType));
        $db = db_connect();

        $total = 0.0;
        $found = false;
        $defaultPaid = false;

        if ($sourceType === 'diamond' && $db->tableExists('purchase_headers') && $db->tableExists('purchase_lines')) {
            $row = $db->table('purchase_headers ph')
                ->select('ph.id, MAX(ph.invoice_total) as invoice_total, COALESCE(SUM(pl.line_value),0) as subtotal', false)
                ->join('purchase_lines pl', 'pl.purchase_id = ph.id', 'left')
                ->where('ph.id', $sourceId)
                ->groupBy('ph.id')
                ->get()
                ->getRowArray();
            if ($row) {
                $found = true;
                $total = (float) ($row['invoice_total'] ?? 0);
                if ($total <= 0) {
                    $total = (float) ($row['subtotal'] ?? 0);
                }
            }
        } elseif ($sourceType === 'gold' && $db->tableExists('gold_inventory_purchase_headers') && $db->tableExists('gold_inventory_purchase_lines')) {
            $row = $db->table('gold_inventory_purchase_headers ph')
                ->select('ph.id, COALESCE(SUM(pl.line_value),0) as total_value', false)
                ->join('gold_inventory_purchase_lines pl', 'pl.purchase_id = ph.id', 'left')
                ->where('ph.id', $sourceId)
                ->groupBy('ph.id')
                ->get()
                ->getRowArray();
            if ($row) {
                $found = true;
                $total = (float) ($row['total_value'] ?? 0);
                $defaultPaid = true;
            }
        } elseif ($sourceType === 'stone' && $db->tableExists('stone_inventory_purchase_headers') && $db->tableExists('stone_inventory_purchase_lines')) {
            $row = $db->table('stone_inventory_purchase_headers ph')
                ->select('ph.id, MAX(ph.invoice_total) as invoice_total, COALESCE(SUM(pl.line_value),0) as subtotal', false)
                ->join('stone_inventory_purchase_lines pl', 'pl.purchase_id = ph.id', 'left')
                ->where('ph.id', $sourceId)
                ->groupBy('ph.id')
                ->get()
                ->getRowArray();
            if ($row) {
                $found = true;
                $total = (float) ($row['invoice_total'] ?? 0);
                if ($total <= 0) {
                    $total = (float) ($row['subtotal'] ?? 0);
                }
            }
        } elseif ($sourceType === 'stone' && $db->tableExists('purchases')) {
            $row = $db->table('purchases')
                ->select('id, invoice_amount, payment_status')
                ->where('id', $sourceId)
                ->where('purchase_type', 'Stone')
                ->get()
                ->getRowArray();
            if ($row) {
                $found = true;
                $total = (float) ($row['invoice_amount'] ?? 0);
                if (strcasecmp((string) ($row['payment_status'] ?? ''), 'paid') === 0) {
                    $defaultPaid = true;
                }
            }
        }

        if (! $found) {
            return ['found' => false, 'total_amount' => 0, 'paid_amount' => 0];
        }

        $paid = 0.0;
        if ($db->tableExists('purchase_bill_payments')) {
            $paid = (float) ($db->table('purchase_bill_payments')
                ->select('COALESCE(SUM(amount),0) as paid_amount', false)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->get()
                ->getRowArray()['paid_amount'] ?? 0);
        }

        if ($defaultPaid && $paid <= 0 && $total > 0) {
            $paid = $total;
        }

        return [
            'found' => true,
            'total_amount' => round($total, 2),
            'paid_amount' => round($paid, 2),
        ];
    }

    private function daysLeftLabel(string $dueDate, string $status): string
    {
        $dueDate = trim($dueDate);
        if ($dueDate === '') {
            return '-';
        }

        $dueTs = strtotime($dueDate);
        if ($dueTs === false) {
            return '-';
        }

        if (strcasecmp($status, 'Paid') === 0) {
            return 'Paid';
        }

        $today = strtotime(date('Y-m-d'));
        $days = (int) floor(($dueTs - $today) / 86400);
        if ($days < 0) {
            return abs($days) . ' overdue';
        }
        if ($days === 0) {
            return 'Due today';
        }
        return $days . ' left';
    }

    private function nullableString($value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    private function nullableInt($value): ?int
    {
        $v = (int) $value;
        return $v > 0 ? $v : null;
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
