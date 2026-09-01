<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AccountJournalVoucherModel;
use App\Models\AccountPaymentModel;
use App\Models\CreditNoteModel;
use App\Models\DebitNoteModel;
use App\Models\LabourBillModel;
use App\Models\LabourBillPaymentModel;
use App\Models\PurchaseBillPaymentModel;
use App\Models\VendorPaymentModel;

class AccountsController extends BaseController
{
    private AccountPaymentModel $accountPaymentModel;
    private AccountJournalVoucherModel $accountJournalVoucherModel;
    private PurchaseBillPaymentModel $purchaseBillPaymentModel;
    private LabourBillModel $labourBillModel;
    private LabourBillPaymentModel $labourBillPaymentModel;
    private VendorPaymentModel $vendorPaymentModel;
    private DebitNoteModel $debitNoteModel;
    private CreditNoteModel $creditNoteModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->accountPaymentModel = new AccountPaymentModel();
        $this->accountJournalVoucherModel = new AccountJournalVoucherModel();
        $this->purchaseBillPaymentModel = new PurchaseBillPaymentModel();
        $this->labourBillModel = new LabourBillModel();
        $this->labourBillPaymentModel = new LabourBillPaymentModel();
        $this->vendorPaymentModel = new VendorPaymentModel();
        $this->debitNoteModel = new DebitNoteModel();
        $this->creditNoteModel = new CreditNoteModel();
    }

    public function dashboard(): string
    {
        $outstanding = $this->outstandingSummaryDataset();
        $journalSummary = $this->journalVoucherSummary();

        return view('admin/accounts/dashboard', [
            'title' => 'Accounts Dashboard',
            'summary' => $outstanding['summary'],
            'vendorRows' => array_slice($outstanding['vendor_rows'], 0, 8),
            'karigarRows' => array_slice($outstanding['karigar_rows'], 0, 8),
            'customerRows' => array_slice($outstanding['customer_rows'], 0, 8),
            'journalSummary' => $journalSummary,
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
            'source_type' => 'required|in_list[diamond,gold,stone,production_document]',
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
        } elseif ($sourceType === 'diamond' && $db->tableExists('purchase_headers')) {
            $newPaid = round($totals['paid_amount'] + $payAmount, 2);
            $status = $newPaid >= $totals['total_amount'] ? 'Paid' : 'Partial';
            $paymentDate = (string) $this->request->getPost('payment_date');
            $header = $db->table('purchase_headers')->select('production_document_id')->where('id', $sourceId)->get()->getRowArray();
            $db->table('purchase_headers')->where('id', $sourceId)->update([
                'paid_amount' => $newPaid,
                'payment_status' => $status,
                'payment_date' => $paymentDate,
            ]);
            if ((int) ($header['production_document_id'] ?? 0) > 0 && $db->tableExists('production_purchase_documents')) {
                $db->table('production_purchase_documents')->where('id', (int) $header['production_document_id'])->update([
                    'paid_amount' => $newPaid,
                    'payment_status' => $status,
                    'payment_date' => $paymentDate,
                    'reconciliation_status' => 'Payment updated in application',
                ]);
            }
        } elseif ($sourceType === 'gold' && $db->tableExists('gold_inventory_purchase_headers')) {
            $newPaid = round($totals['paid_amount'] + $payAmount, 2);
            $status = $newPaid >= $totals['total_amount'] ? 'Paid' : 'Partial';
            $paymentDate = (string) $this->request->getPost('payment_date');
            $header = $db->table('gold_inventory_purchase_headers')->select('production_document_id')->where('id', $sourceId)->get()->getRowArray();
            $db->table('gold_inventory_purchase_headers')->where('id', $sourceId)->update([
                'paid_amount' => $newPaid,
                'payment_status' => $status,
                'payment_date' => $paymentDate,
            ]);
            if ((int) ($header['production_document_id'] ?? 0) > 0 && $db->tableExists('production_purchase_documents')) {
                $db->table('production_purchase_documents')->where('id', (int) $header['production_document_id'])->update([
                    'paid_amount' => $newPaid,
                    'payment_status' => $status,
                    'payment_date' => $paymentDate,
                    'reconciliation_status' => 'Payment updated in application',
                ]);
            }
        } elseif ($sourceType === 'production_document' && $db->tableExists('production_purchase_documents')) {
            $newPaid = round($totals['paid_amount'] + $payAmount, 2);
            $status = $newPaid >= $totals['total_amount'] ? 'Paid' : 'Partial';
            $db->table('production_purchase_documents')->where('id', $sourceId)->update([
                'paid_amount' => $newPaid,
                'payment_status' => $status,
                'payment_date' => (string) $this->request->getPost('payment_date'),
                'reconciliation_status' => 'Payment updated in application',
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

    public function labourLedger(): string
    {
        $filters = [
            'karigar_id' => (int) ($this->request->getGet('karigar_id') ?? 0),
            'status' => trim((string) ($this->request->getGet('status') ?? 'all')),
            'entry_type' => trim((string) ($this->request->getGet('entry_type') ?? 'all')),
            'date_from' => trim((string) ($this->request->getGet('date_from') ?? '')),
            'date_to' => trim((string) ($this->request->getGet('date_to') ?? '')),
        ];

        $data = $this->labourLedgerDataset($filters);

        return view('admin/accounts/labour_ledger', [
            'title' => 'Labour Ledger',
            'filters' => $filters,
            'karigars' => $this->karigarOptions(),
            'rows' => $data['rows'],
            'summary' => $data['summary'],
        ]);
    }

    public function payments(): string
    {
        $db = db_connect();
        $labourBills = array_values(array_filter(
            $this->labourBillsDataset(),
            static fn(array $row): bool => (float) ($row['pending_amount'] ?? 0) > 0
        ));
        $purchaseBills = array_values(array_filter(
            $this->purchaseBillsDataset(),
            static fn(array $row): bool => (float) ($row['pending_amount'] ?? 0) > 0
        ));

        $paymentRows = $this->accountPaymentsDataset();
        foreach ($this->productionDocumentPaymentRows() as $payment) {
            $paymentRows[] = [
                'payment_no' => $payment['reference_no'],
                'payment_date' => $payment['payment_date'],
                'party_type' => 'vendor',
                'vendor_id' => $payment['vendor_id'],
                'vendor_name' => $payment['vendor_name'],
                'karigar_id' => null,
                'karigar_name' => null,
                'amount' => $payment['amount'],
                'amount_available' => $payment['amount'] > 0,
                'payment_mode' => 'Source Record',
                'reference_no' => $payment['reference_no'],
                'bill_type' => 'purchase',
                'bill_source_type' => 'production document',
                'bill_source_id' => (int) substr(strrchr($payment['reference_no'], '#') ?: '#0', 1),
                'notes' => $payment['details'],
            ];
        }

        return view('admin/accounts/payments', [
            'title' => 'Payments',
            'tableEnabled' => $db->tableExists('account_payments'),
            'karigars' => $this->karigarOptionsWithBalance(),
            'vendors' => $this->vendorOptionsWithBalance(),
            'labourBills' => $labourBills,
            'purchaseBills' => $purchaseBills,
            'rows' => $paymentRows,
        ]);
    }

    public function journalVouchers(): string
    {
        return view('admin/accounts/journal_vouchers', [
            'title' => 'Journal Vouchers',
            'tableEnabled' => db_connect()->tableExists('account_journal_vouchers'),
            'customers' => $this->customerOptions(),
            'vendors' => $this->vendorOptions(),
            'karigars' => $this->karigarOptions(),
            'rows' => $this->journalVouchersDataset(),
        ]);
    }

    public function storeJournalVoucher()
    {
        $db = db_connect();
        if (! $db->tableExists('account_journal_vouchers')) {
            return redirect()->back()->withInput()->with('error', 'Journal voucher table not available. Run migration.');
        }

        $rules = [
            'voucher_date' => 'required|valid_date',
            'voucher_type' => 'required|in_list[party_to_party,expenditure]',
            'from_party_type' => 'permit_empty|in_list[customer,vendor,karigar]',
            'from_party_id' => 'permit_empty|integer',
            'to_party_type' => 'permit_empty|in_list[customer,vendor,karigar]',
            'to_party_id' => 'permit_empty|integer',
            'expense_head' => 'permit_empty|max_length[120]',
            'amount' => 'required|decimal|greater_than[0]',
            'payment_mode' => 'permit_empty|max_length[30]',
            'reference_no' => 'permit_empty|max_length[80]',
            'status' => 'permit_empty|in_list[Posted,Draft]',
            'notes' => 'permit_empty',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $voucherType = (string) $this->request->getPost('voucher_type');
        $fromPartyType = $this->nullableString($this->request->getPost('from_party_type'));
        $fromPartyId = $this->nullableInt($this->request->getPost('from_party_id'));
        $toPartyType = $this->nullableString($this->request->getPost('to_party_type'));
        $toPartyId = $this->nullableInt($this->request->getPost('to_party_id'));
        $expenseHead = $this->nullableString($this->request->getPost('expense_head'));

        if ($voucherType === 'party_to_party') {
            if ($fromPartyType === null || $fromPartyId === null || $toPartyType === null || $toPartyId === null) {
                return redirect()->back()->withInput()->with('error', 'From party and to party are required for party-to-party voucher.');
            }
            if ($fromPartyType === $toPartyType && $fromPartyId === $toPartyId) {
                return redirect()->back()->withInput()->with('error', 'From party and to party must be different.');
            }
        }

        if ($voucherType === 'expenditure' && $expenseHead === null) {
            return redirect()->back()->withInput()->with('error', 'Expense head is required for expenditure voucher.');
        }

        $id = (int) $this->accountJournalVoucherModel->insert([
            'voucher_no' => $this->generateJournalVoucherNumber(),
            'voucher_date' => (string) $this->request->getPost('voucher_date'),
            'voucher_type' => $voucherType,
            'from_party_type' => $fromPartyType,
            'from_party_id' => $fromPartyId,
            'to_party_type' => $toPartyType,
            'to_party_id' => $toPartyId,
            'expense_head' => $expenseHead,
            'amount' => round((float) $this->request->getPost('amount'), 2),
            'payment_mode' => $this->nullableString($this->request->getPost('payment_mode')),
            'reference_no' => $this->nullableString($this->request->getPost('reference_no')),
            'status' => $this->nullableString($this->request->getPost('status')) ?: 'Posted',
            'notes' => $this->nullableString($this->request->getPost('notes')),
            'created_by' => (int) session('admin_id'),
        ], true);

        if ($id <= 0) {
            return redirect()->back()->withInput()->with('error', 'Unable to save journal voucher right now.');
        }

        return redirect()->to(site_url('admin/accounts/journal-vouchers'))->with('success', 'Journal voucher saved.');
    }

    public function partyBalances(string $type): string
    {
        $type = strtolower(trim($type));
        if (! in_array($type, ['vendor', 'karigar', 'customer'], true)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Invalid party balance type.');
        }

        $data = $this->outstandingSummaryDataset();
        $rows = $data[$type . '_rows'] ?? [];
        $rows = array_values(array_filter($rows, static fn(array $row): bool => (float) ($row['pending'] ?? 0) > 0));

        return view('admin/accounts/party_balances', [
            'title' => $this->partyTypeLabel($type) . ' Pending Balances',
            'type' => $type,
            'rows' => $rows,
            'summary' => $this->summarizePartyRows($rows),
        ]);
    }

    public function partyLedger(string $type, int $id): string
    {
        $type = strtolower(trim($type));
        if (! in_array($type, ['vendor', 'karigar', 'customer'], true) || $id <= 0) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Invalid party ledger.');
        }

        $filters = [
            'date_from' => trim((string) ($this->request->getGet('date_from') ?? '')),
            'date_to' => trim((string) ($this->request->getGet('date_to') ?? '')),
            'transaction_type' => '',
            'party_type' => $type,
            'customer_id' => $type === 'customer' ? $id : 0,
            'vendor_id' => $type === 'vendor' ? $id : 0,
            'karigar_id' => $type === 'karigar' ? $id : 0,
            'status' => '',
            'reference_no' => '',
            'search' => '',
        ];
        $data = $this->generalLedgerDataset($filters);
        $partyName = $this->partyName($type, $id);

        return view('admin/accounts/party_ledger', [
            'title' => $partyName . ' Ledger',
            'type' => $type,
            'partyId' => $id,
            'partyName' => $partyName,
            'filters' => $filters,
            'rows' => $data['rows'],
            'summary' => $data['summary'],
        ]);
    }

    public function generalLedger(): string
    {
        $filters = [
            'date_from' => trim((string) ($this->request->getGet('date_from') ?? '')),
            'date_to' => trim((string) ($this->request->getGet('date_to') ?? '')),
            'transaction_type' => trim((string) ($this->request->getGet('transaction_type') ?? '')),
            'party_type' => trim((string) ($this->request->getGet('party_type') ?? '')),
            'customer_id' => (int) ($this->request->getGet('customer_id') ?? 0),
            'vendor_id' => (int) ($this->request->getGet('vendor_id') ?? 0),
            'karigar_id' => (int) ($this->request->getGet('karigar_id') ?? 0),
            'status' => trim((string) ($this->request->getGet('status') ?? '')),
            'reference_no' => trim((string) ($this->request->getGet('reference_no') ?? '')),
            'search' => trim((string) ($this->request->getGet('search') ?? '')),
        ];
        $data = $this->generalLedgerDataset($filters);

        return view('admin/accounts/general_ledger', [
            'title' => 'General Ledger',
            'filters' => $filters,
            'rows' => $data['rows'],
            'summary' => $data['summary'],
            'transactionTypes' => $data['transaction_types'],
            'statuses' => $data['statuses'],
            'customers' => $this->customerOptions(),
            'vendors' => $this->vendorOptions(),
            'karigars' => $this->karigarOptions(),
        ]);
    }

    public function vendorTransactionLedger(): string
    {
        $filters = [
            'date_from' => trim((string) ($this->request->getGet('date_from') ?? '')),
            'date_to' => trim((string) ($this->request->getGet('date_to') ?? '')),
            'party_type' => trim((string) ($this->request->getGet('party_type') ?? '')),
            'vendor_id' => (int) ($this->request->getGet('vendor_id') ?? 0),
            'karigar_id' => (int) ($this->request->getGet('karigar_id') ?? 0),
            'category' => trim((string) ($this->request->getGet('category') ?? '')),
            'transaction_type' => trim((string) ($this->request->getGet('transaction_type') ?? '')),
            'material_type' => trim((string) ($this->request->getGet('material_type') ?? '')),
            'reference_no' => trim((string) ($this->request->getGet('reference_no') ?? '')),
            'search' => trim((string) ($this->request->getGet('search') ?? '')),
        ];
        $data = $this->vendorTransactionLedgerDataset($filters);

        return view('admin/accounts/vendor_transaction_ledger', [
            'title' => 'Issue Receive Ledger',
            'filters' => $filters,
            'rows' => $data['rows'],
            'summary' => $data['summary'],
            'categories' => $data['categories'],
            'transactionTypes' => $data['transaction_types'],
            'materialTypes' => $data['material_types'],
            'vendors' => $this->vendorOptions(),
            'karigars' => $this->karigarOptions(),
        ]);
    }

    public function storePayment()
    {
        $db = db_connect();
        if (! $db->tableExists('account_payments')) {
            return redirect()->back()->withInput()->with('error', 'Payment table not available. Run migration.');
        }

        $rules = [
            'party_type' => 'required|in_list[karigar,vendor]',
            'karigar_id' => 'permit_empty|integer',
            'vendor_id' => 'permit_empty|integer',
            'payment_date' => 'required|valid_date',
            'amount' => 'required|decimal|greater_than[0]',
            'payment_mode' => 'permit_empty|max_length[30]',
            'reference_no' => 'permit_empty|max_length[80]',
            'bill_ref' => 'permit_empty|max_length[80]',
            'notes' => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $partyType = (string) $this->request->getPost('party_type');
        $karigarId = (int) $this->request->getPost('karigar_id');
        $vendorId = (int) $this->request->getPost('vendor_id');
        if ($partyType === 'karigar' && $karigarId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Karigar is required for karigar payment.');
        }
        if ($partyType === 'vendor' && $vendorId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Vendor is required for vendor payment.');
        }

        $amount = round((float) $this->request->getPost('amount'), 2);
        $billRef = trim((string) $this->request->getPost('bill_ref'));
        $bill = $this->resolvePaymentBill($partyType, $billRef);
        if ($bill['selected'] && ! $bill['found']) {
            return redirect()->back()->withInput()->with('error', 'Selected bill was not found.');
        }
        if ($bill['selected'] && $amount > ((float) $bill['pending_amount'] + 0.001)) {
            return redirect()->back()->withInput()->with('error', 'Payment amount exceeds selected bill pending amount.');
        }

        $upload = $this->storePaymentReferenceUpload();
        if (! $upload['ok']) {
            return redirect()->back()->withInput()->with('error', $upload['message']);
        }

        $db->transStart();

        $paymentNo = $this->generateAccountPaymentNumber();
        $paymentDate = (string) $this->request->getPost('payment_date');
        $referenceNo = $this->nullableString($this->request->getPost('reference_no'));
        $notes = $this->nullableString($this->request->getPost('notes'));

        $accountPaymentId = (int) $this->accountPaymentModel->insert([
            'payment_no' => $paymentNo,
            'payment_date' => $paymentDate,
            'party_type' => $partyType,
            'karigar_id' => $partyType === 'karigar' ? $karigarId : null,
            'vendor_id' => $partyType === 'vendor' ? $vendorId : null,
            'amount' => $amount,
            'payment_mode' => $this->nullableString($this->request->getPost('payment_mode')),
            'reference_no' => $referenceNo,
            'reference_file_path' => $upload['file_path'],
            'reference_file_name' => $upload['file_name'],
            'bill_type' => $bill['bill_type'],
            'bill_source_type' => $bill['bill_source_type'],
            'bill_source_id' => $bill['bill_source_id'],
            'labour_bill_id' => $bill['labour_bill_id'],
            'notes' => $notes,
            'created_by' => (int) session('admin_id'),
        ], true);

        if ($partyType === 'karigar') {
            if ((int) $bill['labour_bill_id'] > 0) {
                $this->postLabourBillPayment((int) $bill['labour_bill_id'], $amount, $paymentDate, $referenceNo, $notes, false);
            }
            if ($db->tableExists('karigar_payment_ledgers')) {
                $db->table('karigar_payment_ledgers')->insert([
                    'karigar_id' => $karigarId,
                    'order_id' => $bill['order_id'],
                    'entry_type' => 'payment',
                    'amount' => $amount,
                    'reference_no' => $referenceNo ?: $paymentNo,
                    'notes' => $notes ?: 'Account payment ' . $paymentNo,
                    'created_by' => (int) session('admin_id'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } elseif ($partyType === 'vendor') {
            if ((string) $bill['bill_source_type'] !== '' && (int) $bill['bill_source_id'] > 0) {
                $this->postPurchaseBillPayment((string) $bill['bill_source_type'], (int) $bill['bill_source_id'], $amount, $paymentDate, $referenceNo, $notes, false);
            }
            if ($db->tableExists('vendor_payments')) {
                $this->vendorPaymentModel->insert([
                    'payment_no' => $paymentNo,
                    'payment_date' => $paymentDate,
                    'vendor_id' => $vendorId,
                    'purchase_invoice_id' => null,
                    'amount' => $amount,
                    'payment_mode' => $this->nullableString($this->request->getPost('payment_mode')),
                    'reference_no' => $referenceNo,
                    'notes' => $notes,
                    'created_by' => (int) session('admin_id'),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $db->transComplete();
        if (! $db->transStatus() || $accountPaymentId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Unable to save payment right now.');
        }

        return redirect()->to(site_url('admin/accounts/payments'))->with('success', 'Payment saved successfully.');
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
                ->select('ph.id, ph.vendor_id, ph.purchase_date, ph.due_date, MAX(ph.production_document_id) as production_document_id, MAX(ph.invoice_no) as invoice_no, MAX(v.name) as vendor_name, MAX(ph.supplier_name) as supplier_name, MAX(ph.supplier_address) as supplier_address, MAX(ph.supplier_gstin) as supplier_gstin, MAX(ph.taxable_amount) as taxable_amount, MAX(ph.cgst_amount) as cgst_amount, MAX(ph.sgst_amount) as sgst_amount, MAX(ph.igst_amount) as igst_amount, MAX(ph.gst_amount) as gst_amount, MAX(ph.round_off_amount) as round_off_amount, MAX(ph.paid_amount) as header_paid_amount, MAX(d.original_name) as invoice_file_name, COUNT(pl.id) as qty, COALESCE(SUM(pl.carat), 0) as total_weight, COALESCE(SUM(pl.line_value), 0) as subtotal, MAX(ph.invoice_total) as invoice_total', false)
                ->join('purchase_lines pl', 'pl.purchase_id = ph.id', 'left')
                ->join('vendors v', 'v.id = ph.vendor_id', 'left')
                ->join('production_purchase_documents d', 'd.id = ph.production_document_id', 'left')
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
                $paid = max((float) ($row['header_paid_amount'] ?? 0), (float) ($paymentMap['diamond:' . $sourceId] ?? 0));
                $statusInfo = $this->paymentStatusInfo($total, $paid, false);
                $documentId = (int) ($row['production_document_id'] ?? 0);
                $attachment = $documentId > 0 ? [
                    'count' => 1,
                    'url' => site_url('admin/accounts/production-document/' . $documentId),
                    'file_name' => (string) (($row['invoice_file_name'] ?? '') ?: 'Diamond purchase invoice.pdf'),
                ] : ($diamondAttachmentMap[$sourceId] ?? null);

                $rows[] = [
                    'source_type' => 'diamond',
                    'source_id' => $sourceId,
                    'vendor_id' => (int) ($row['vendor_id'] ?? 0),
                    'supplier_name' => trim((string) ($row['vendor_name'] ?: $row['supplier_name'] ?: '-')),
                    'vendor_address' => (string) ($row['supplier_address'] ?? ''),
                    'vendor_gstin' => (string) ($row['supplier_gstin'] ?? ''),
                    'purchase_date' => (string) ($row['purchase_date'] ?? ''),
                    'invoice_no' => (string) ($row['invoice_no'] ?? ''),
                    'category' => 'Diamond',
                    'qty' => (float) ($row['qty'] ?? 0),
                    'weight_value' => (float) ($row['total_weight'] ?? 0),
                    'weight_unit' => 'cts',
                    'amount' => round($total, 2),
                    'taxable_amount' => (float) ($row['taxable_amount'] ?? 0),
                    'cgst_amount' => (float) ($row['cgst_amount'] ?? 0),
                    'sgst_amount' => (float) ($row['sgst_amount'] ?? 0),
                    'igst_amount' => (float) ($row['igst_amount'] ?? 0),
                    'gst_amount' => (float) ($row['gst_amount'] ?? 0),
                    'round_off_amount' => (float) ($row['round_off_amount'] ?? 0),
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
                ->select('ph.*, COALESCE(MAX(v.name), ph.supplier_name) as resolved_vendor_name, MAX(d.original_name) as invoice_file_name, COUNT(pl.id) as qty, COALESCE(SUM(pl.weight_gm), 0) as total_weight, COALESCE(SUM(pl.line_value), 0) as total_value', false)
                ->join('gold_inventory_purchase_lines pl', 'pl.purchase_id = ph.id', 'left')
                ->join('vendors v', 'v.id = ph.vendor_id', 'left')
                ->join('production_purchase_documents d', 'd.id = ph.production_document_id', 'left')
                ->groupBy('ph.id')
                ->orderBy('ph.id', 'DESC')
                ->get()
                ->getResultArray();

            foreach ($goldRows as $row) {
                $sourceId = (int) ($row['id'] ?? 0);
                $total = (float) ($row['invoice_total'] ?? 0);
                if ($total <= 0) {
                    $total = (float) ($row['total_value'] ?? 0);
                }
                $paid = max((float) ($row['paid_amount'] ?? 0), (float) ($paymentMap['gold:' . $sourceId] ?? 0));
                $statusInfo = $this->paymentStatusInfo($total, $paid, false);
                $documentId = (int) ($row['production_document_id'] ?? 0);

                $rows[] = [
                    'source_type' => 'gold',
                    'source_id' => $sourceId,
                    'vendor_id' => (int) ($row['vendor_id'] ?? 0),
                    'supplier_name' => trim((string) ($row['resolved_vendor_name'] ?: $row['supplier_name'] ?: '-')),
                    'vendor_address' => (string) ($row['supplier_address'] ?? ''),
                    'vendor_gstin' => (string) ($row['supplier_gstin'] ?? ''),
                    'purchase_date' => (string) ($row['purchase_date'] ?? ''),
                    'invoice_no' => (string) ($row['invoice_no'] ?? ''),
                    'category' => 'Gold',
                    'qty' => (float) ($row['qty'] ?? 0),
                    'weight_value' => (float) ($row['total_weight'] ?? 0),
                    'weight_unit' => 'gm',
                    'amount' => round($total, 2),
                    'taxable_amount' => (float) ($row['taxable_amount'] ?? 0),
                    'cgst_amount' => (float) ($row['cgst_amount'] ?? 0),
                    'sgst_amount' => (float) ($row['sgst_amount'] ?? 0),
                    'igst_amount' => (float) ($row['igst_amount'] ?? 0),
                    'gst_amount' => (float) ($row['gst_amount'] ?? 0),
                    'round_off_amount' => (float) ($row['round_off_amount'] ?? 0),
                    'due_date' => (string) ($row['due_date'] ?? ''),
                    'days_left' => $this->daysLeftLabel((string) ($row['due_date'] ?? ''), $statusInfo['status']),
                    'payment_status' => $statusInfo['status'],
                    'paid_amount' => $statusInfo['paid_amount'],
                    'pending_amount' => $statusInfo['pending_amount'],
                    'attachment' => $documentId > 0 ? [
                        'count' => 1,
                        'url' => site_url('admin/accounts/production-document/' . $documentId),
                        'file_name' => (string) (($row['invoice_file_name'] ?? '') ?: 'Gold purchase invoice.pdf'),
                    ] : null,
                    'view_url' => site_url('admin/gold-inventory/purchases/view/' . $sourceId),
                ];
            }
        }

        if ($db->tableExists('stone_inventory_purchase_headers') && $db->tableExists('stone_inventory_purchase_lines')) {
            $stoneRows = $db->table('stone_inventory_purchase_headers ph')
                ->select('ph.id, ph.vendor_id, ph.purchase_date, ph.due_date, MAX(ph.invoice_no) as invoice_no, MAX(v.name) as vendor_name, MAX(ph.supplier_name) as supplier_name, COUNT(pl.id) as qty, COALESCE(SUM(pl.qty), 0) as total_weight, COALESCE(SUM(pl.line_value), 0) as subtotal, MAX(ph.invoice_total) as invoice_total', false)
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
                    'vendor_id' => (int) ($row['vendor_id'] ?? 0),
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
                ->select('p.id, p.vendor_id, p.purchase_date, p.payment_due_date as due_date, p.invoice_no, p.invoice_amount, p.payment_status as legacy_payment_status, MAX(v.name) as vendor_name, COUNT(pi.id) as qty, COALESCE(SUM(pi.cts), 0) as total_weight', false)
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
                    'vendor_id' => (int) ($row['vendor_id'] ?? 0),
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

        if ($db->tableExists('production_purchase_documents') && $db->fieldExists('invoice_amount', 'production_purchase_documents')) {
            $documentBuilder = $db->table('production_purchase_documents d')
                ->select('d.*, v.name as resolved_vendor_name', false)
                ->join('vendors v', 'v.id = d.vendor_id', 'left')
                ->where('d.category !=', 'gold');
            if ($db->fieldExists('production_document_id', 'purchase_headers')) {
                $documentBuilder->where('NOT EXISTS (SELECT 1 FROM purchase_headers linked_ph WHERE linked_ph.production_document_id = d.id)', null, false);
            }
            $documentRows = $documentBuilder
                ->orderBy('d.document_date', 'DESC')
                ->orderBy('d.id', 'DESC')
                ->get()->getResultArray();
            foreach ($documentRows as $row) {
                $lineItems = json_decode((string) ($row['line_items_json'] ?? ''), true);
                if (! is_array($lineItems)) {
                    $lineItems = [];
                }
                $weight = 0.0;
                $units = [];
                foreach ($lineItems as $line) {
                    $unit = trim((string) ($line['unit'] ?? ''));
                    if ($unit !== '' && strcasecmp($unit, 'service') !== 0) {
                        $weight += (float) ($line['quantity'] ?? 0);
                        $units[strtolower($unit)] = $unit;
                    }
                }
                $amountAvailable = $row['invoice_amount'] !== null;
                $amount = $amountAvailable ? (float) $row['invoice_amount'] : 0.0;
                $paidAvailable = $row['paid_amount'] !== null;
                $paid = $paidAvailable ? (float) $row['paid_amount'] : 0.0;
                $status = (string) ($row['payment_status'] ?? 'Unverified');
                $rows[] = [
                    'source_type' => 'production_document',
                    'source_id' => (int) $row['id'],
                    'vendor_id' => (int) ($row['vendor_id'] ?? 0),
                    'supplier_name' => (string) (($row['resolved_vendor_name'] ?? '') ?: ($row['vendor_name'] ?? '-')),
                    'vendor_address' => (string) ($row['vendor_address'] ?? ''),
                    'vendor_gstin' => (string) ($row['vendor_gstin'] ?? ''),
                    'purchase_date' => (string) ($row['document_date'] ?? ''),
                    'invoice_no' => (string) (($row['invoice_no'] ?? '') ?: ($row['original_name'] ?? '')),
                    'category' => ucfirst((string) ($row['category'] ?? 'Purchase')),
                    'qty' => count($lineItems),
                    'weight_value' => round($weight, 3),
                    'weight_unit' => count($units) === 1 ? (string) reset($units) : (count($units) > 1 ? 'mixed' : ''),
                    'amount' => $amount,
                    'amount_available' => $amountAvailable,
                    'taxable_amount' => (float) ($row['taxable_amount'] ?? 0),
                    'cgst_amount' => (float) ($row['cgst_amount'] ?? 0),
                    'sgst_amount' => (float) ($row['sgst_amount'] ?? 0),
                    'igst_amount' => (float) ($row['igst_amount'] ?? 0),
                    'gst_amount' => (float) ($row['gst_amount'] ?? 0),
                    'round_off_amount' => (float) ($row['round_off_amount'] ?? 0),
                    'due_date' => (string) ($row['due_date'] ?? ''),
                    'days_left' => $this->daysLeftLabel((string) ($row['due_date'] ?? ''), $status),
                    'payment_status' => $status,
                    'paid_amount' => $paid,
                    'paid_amount_available' => $paidAvailable,
                    'pending_amount' => $amountAvailable ? max(0, $amount - $paid) : 0,
                    'attachment' => [
                        'count' => 1,
                        'url' => site_url('admin/accounts/production-document/' . (int) $row['id']),
                        'file_name' => (string) ($row['original_name'] ?? 'Purchase invoice.pdf'),
                    ],
                    'view_url' => site_url('admin/accounts/production-document/' . (int) $row['id']),
                    'reconciliation_status' => (string) ($row['reconciliation_status'] ?? ''),
                    'verification_status' => (string) ($row['verification_status'] ?? ''),
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
                    'karigar_id' => (int) ($row['karigar_id'] ?? 0),
                    'order_id' => isset($row['order_id']) ? (int) $row['order_id'] : null,
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
                    'customer_id' => (int) ($row['customer_id'] ?? 0),
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
                ->select("'Diamond Purchase' as source_label, ph.invoice_no, ph.purchase_date as invoice_date, COALESCE(MAX(v.name), MAX(ph.supplier_name), '-') as party_name, COALESCE(MAX(ph.supplier_gstin), MAX(v.gstin)) as gstin, CASE WHEN MAX(ph.taxable_amount) > 0 THEN MAX(ph.taxable_amount) ELSE COALESCE(SUM(pl.line_value),0) END as taxable_amount, MAX(ph.tax_percentage) as gst_percent, MAX(ph.gst_amount) as gst_amount, MAX(ph.invoice_total) as total_amount", false)
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

        if ($db->tableExists('production_purchase_documents') && $db->fieldExists('taxable_amount', 'production_purchase_documents')) {
            $builder = $db->table('production_purchase_documents d')
                ->select("'Verified Production Purchase' as source_label, d.invoice_no, d.document_date as invoice_date, d.vendor_name as party_name, d.vendor_gstin as gstin, d.taxable_amount, COALESCE(d.cgst_rate,0) + COALESCE(d.sgst_rate,0) + COALESCE(d.igst_rate,0) as gst_percent, d.gst_amount, d.invoice_amount as total_amount", false);
            if ($db->fieldExists('production_document_id', 'purchase_headers')) {
                $builder->where('NOT EXISTS (SELECT 1 FROM purchase_headers linked_ph WHERE linked_ph.production_document_id = d.id)', null, false);
            }
            if ($db->fieldExists('production_document_id', 'gold_inventory_purchase_headers')) {
                $builder->where('NOT EXISTS (SELECT 1 FROM gold_inventory_purchase_headers linked_gph WHERE linked_gph.production_document_id = d.id)', null, false);
            }
            $this->applyDateFilter($builder, 'd.document_date', $dateFrom, $dateTo);
            $purchaseRows = array_merge($purchaseRows, $builder->orderBy('d.document_date', 'DESC')->get()->getResultArray());
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
            $partyId = (int) ($row['customer_id'] ?? 0);
            $key = $partyId > 0 ? (string) $partyId : trim((string) ($row['customer_name'] ?? '-'));
            if (! isset($customerMap[$key])) {
                $customerMap[$key] = ['party_id' => $partyId, 'party_type' => 'customer', 'party_name' => trim((string) ($row['customer_name'] ?? '-')), 'bill_count' => 0, 'amount' => 0.0, 'paid' => 0.0, 'pending' => 0.0];
            }
            $customerMap[$key]['bill_count']++;
            $customerMap[$key]['amount'] += (float) ($row['total_amount'] ?? 0);
            $customerMap[$key]['paid'] += (float) ($row['paid_amount'] ?? 0);
            $customerMap[$key]['pending'] += (float) ($row['pending_amount'] ?? 0);
        }

        $vendorMap = [];
        foreach ($this->purchaseBillsDataset() as $row) {
            $partyId = (int) ($row['vendor_id'] ?? 0);
            $key = $partyId > 0 ? (string) $partyId : trim((string) ($row['supplier_name'] ?? '-'));
            if (! isset($vendorMap[$key])) {
                $vendorMap[$key] = ['party_id' => $partyId, 'party_type' => 'vendor', 'party_name' => trim((string) ($row['supplier_name'] ?? '-')), 'bill_count' => 0, 'amount' => 0.0, 'paid' => 0.0, 'pending' => 0.0];
            }
            $vendorMap[$key]['bill_count']++;
            $vendorMap[$key]['amount'] += (float) ($row['amount'] ?? 0);
            $vendorMap[$key]['paid'] += (float) ($row['paid_amount'] ?? 0);
            $vendorMap[$key]['pending'] += (float) ($row['pending_amount'] ?? 0);
        }

        $karigarMap = [];
        foreach ($this->labourBillsDataset() as $row) {
            $partyId = (int) ($row['karigar_id'] ?? 0);
            $key = $partyId > 0 ? (string) $partyId : trim((string) ($row['karigar_name'] ?? '-'));
            if (! isset($karigarMap[$key])) {
                $karigarMap[$key] = ['party_id' => $partyId, 'party_type' => 'karigar', 'party_name' => trim((string) ($row['karigar_name'] ?? '-')), 'bill_count' => 0, 'amount' => 0.0, 'paid' => 0.0, 'pending' => 0.0];
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

    /** @return list<array<string,mixed>> */
    private function productionDocumentPaymentRows(): array
    {
        $db = db_connect();
        if (! $db->tableExists('production_purchase_documents') || ! $db->fieldExists('paid_amount', 'production_purchase_documents')) {
            return [];
        }
        $rows = $db->table('production_purchase_documents d')
            ->select('d.*, v.name as resolved_vendor_name', false)
            ->join('vendors v', 'v.id = d.vendor_id', 'left')
            ->where('d.payment_status', 'Paid')
            ->where('d.account_payment_id IS NULL', null, false)
            ->orderBy('d.payment_date', 'DESC')
            ->get()->getResultArray();
        $result = [];
        foreach ($rows as $row) {
            $amountAvailable = $row['paid_amount'] !== null;
            $result[] = [
                'payment_date' => (string) (($row['payment_date'] ?? '') ?: ($row['document_date'] ?? '')),
                'reference_no' => 'SOURCE-PAID#' . (int) $row['id'],
                'vendor_id' => (int) ($row['vendor_id'] ?? 0),
                'vendor_name' => (string) (($row['resolved_vendor_name'] ?? '') ?: ($row['vendor_name'] ?? '-')),
                'invoice_no' => (string) (($row['invoice_no'] ?? '') ?: ($row['original_name'] ?? '')),
                'amount' => $amountAvailable ? (float) $row['paid_amount'] : 0.0,
                'details' => $amountAvailable
                    ? 'Paid amount imported from source document'
                    : 'Source document is marked PAID; amount is not present in the supplied data',
            ];
        }
        return $result;
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
        $sourcePaid = 0.0;
        $found = false;
        $defaultPaid = false;

        if ($sourceType === 'diamond' && $db->tableExists('purchase_headers') && $db->tableExists('purchase_lines')) {
            $row = $db->table('purchase_headers ph')
                ->select('ph.id, MAX(ph.invoice_total) as invoice_total, MAX(ph.paid_amount) as paid_amount, COALESCE(SUM(pl.line_value),0) as subtotal', false)
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
                $sourcePaid = (float) ($row['paid_amount'] ?? 0);
            }
        } elseif ($sourceType === 'gold' && $db->tableExists('gold_inventory_purchase_headers') && $db->tableExists('gold_inventory_purchase_lines')) {
            $row = $db->table('gold_inventory_purchase_headers ph')
                ->select('ph.id, MAX(ph.invoice_total) as invoice_total, MAX(ph.paid_amount) as paid_amount, COALESCE(SUM(pl.line_value),0) as total_value', false)
                ->join('gold_inventory_purchase_lines pl', 'pl.purchase_id = ph.id', 'left')
                ->where('ph.id', $sourceId)
                ->groupBy('ph.id')
                ->get()
                ->getRowArray();
            if ($row) {
                $found = true;
                $total = (float) ($row['invoice_total'] ?? 0);
                if ($total <= 0) {
                    $total = (float) ($row['total_value'] ?? 0);
                }
                $sourcePaid = (float) ($row['paid_amount'] ?? 0);
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
        } elseif ($sourceType === 'production_document' && $db->tableExists('production_purchase_documents')) {
            $row = $db->table('production_purchase_documents')
                ->select('id, invoice_amount, paid_amount')
                ->where('id', $sourceId)
                ->get()->getRowArray();
            if ($row) {
                return [
                    'found' => true,
                    'total_amount' => round((float) ($row['invoice_amount'] ?? 0), 2),
                    'paid_amount' => round((float) ($row['paid_amount'] ?? 0), 2),
                ];
            }
        }

        if (! $found) {
            return ['found' => false, 'total_amount' => 0, 'paid_amount' => 0];
        }

        $paid = $sourcePaid;
        if ($db->tableExists('purchase_bill_payments')) {
            $recordedPaid = (float) ($db->table('purchase_bill_payments')
                ->select('COALESCE(SUM(amount),0) as paid_amount', false)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->get()
                ->getRowArray()['paid_amount'] ?? 0);
            $paid = max($paid, $recordedPaid);
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

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,summary:array<string,float>}
     */
    private function labourLedgerDataset(array $filters): array
    {
        $rows = [];
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');
        $karigarId = (int) ($filters['karigar_id'] ?? 0);
        $status = strtolower((string) ($filters['status'] ?? 'all'));
        $entryType = strtolower((string) ($filters['entry_type'] ?? 'all'));

        foreach ($this->labourBillsDataset() as $bill) {
            if ($karigarId > 0 && (int) ($bill['karigar_id'] ?? 0) !== $karigarId) {
                continue;
            }
            if ($entryType !== 'all' && $entryType !== 'bill') {
                continue;
            }
            if (! $this->dateInRange((string) ($bill['bill_date'] ?? ''), $dateFrom, $dateTo)) {
                continue;
            }
            if ($status !== 'all' && strtolower((string) ($bill['payment_status'] ?? '')) !== $status) {
                continue;
            }

            $rows[] = [
                'entry_date' => (string) ($bill['bill_date'] ?? ''),
                'entry_type' => 'Bill',
                'karigar_id' => (int) ($bill['karigar_id'] ?? 0),
                'karigar_name' => (string) ($bill['karigar_name'] ?? '-'),
                'reference_no' => (string) ($bill['bill_no'] ?? ''),
                'order_no' => (string) ($bill['order_no'] ?? '-'),
                'bill_amount' => (float) ($bill['total_amount'] ?? 0),
                'payment_amount' => 0.0,
                'pending_amount' => (float) ($bill['pending_amount'] ?? 0),
                'status' => (string) ($bill['payment_status'] ?? 'Pending'),
                'notes' => '',
                'file_path' => '',
                'file_name' => '',
            ];
        }

        $db = db_connect();
        if ($db->tableExists('labour_bill_payments')) {
            $builder = $db->table('labour_bill_payments lp')
                ->select('lp.*, lb.bill_no, lb.order_id, lb.karigar_id, o.order_no, k.name as karigar_name', false)
                ->join('labour_bills lb', 'lb.id = lp.labour_bill_id', 'left')
                ->join('orders o', 'o.id = lb.order_id', 'left')
                ->join('karigars k', 'k.id = lb.karigar_id', 'left');
            if ($karigarId > 0) {
                $builder->where('lb.karigar_id', $karigarId);
            }
            $payments = $builder->orderBy('lp.payment_date', 'DESC')->get()->getResultArray();
            foreach ($payments as $payment) {
                if ($entryType !== 'all' && $entryType !== 'payment') {
                    continue;
                }
                if ($status !== 'all' && $status !== 'paid') {
                    continue;
                }
                if (! $this->dateInRange((string) ($payment['payment_date'] ?? ''), $dateFrom, $dateTo)) {
                    continue;
                }

                $rows[] = [
                    'entry_date' => (string) ($payment['payment_date'] ?? ''),
                    'entry_type' => 'Payment',
                    'karigar_id' => (int) ($payment['karigar_id'] ?? 0),
                    'karigar_name' => (string) ($payment['karigar_name'] ?? '-'),
                    'reference_no' => (string) (($payment['reference_no'] ?? '') ?: ('PAY#' . (int) ($payment['id'] ?? 0))),
                    'order_no' => (string) ($payment['order_no'] ?? '-'),
                    'bill_amount' => 0.0,
                    'payment_amount' => (float) ($payment['amount'] ?? 0),
                    'pending_amount' => 0.0,
                    'status' => 'Paid',
                    'notes' => (string) ($payment['notes'] ?? ''),
                    'file_path' => '',
                    'file_name' => '',
                ];
            }
        }

        if ($db->tableExists('account_payments')) {
            $builder = $db->table('account_payments ap')
                ->select('ap.*, k.name as karigar_name', false)
                ->join('karigars k', 'k.id = ap.karigar_id', 'left')
                ->where('ap.party_type', 'karigar')
                ->where('ap.labour_bill_id IS NULL', null, false);
            if ($karigarId > 0) {
                $builder->where('ap.karigar_id', $karigarId);
            }
            $payments = $builder->orderBy('ap.payment_date', 'DESC')->get()->getResultArray();
            foreach ($payments as $payment) {
                if ($entryType !== 'all' && $entryType !== 'payment') {
                    continue;
                }
                if ($status !== 'all' && $status !== 'paid') {
                    continue;
                }
                if (! $this->dateInRange((string) ($payment['payment_date'] ?? ''), $dateFrom, $dateTo)) {
                    continue;
                }

                $rows[] = [
                    'entry_date' => (string) ($payment['payment_date'] ?? ''),
                    'entry_type' => 'Payment',
                    'karigar_id' => (int) ($payment['karigar_id'] ?? 0),
                    'karigar_name' => (string) ($payment['karigar_name'] ?? '-'),
                    'reference_no' => (string) (($payment['reference_no'] ?? '') ?: ($payment['payment_no'] ?? '')),
                    'order_no' => '-',
                    'bill_amount' => 0.0,
                    'payment_amount' => (float) ($payment['amount'] ?? 0),
                    'pending_amount' => 0.0,
                    'status' => 'Paid',
                    'notes' => (string) ($payment['notes'] ?? ''),
                    'file_path' => (string) ($payment['reference_file_path'] ?? ''),
                    'file_name' => (string) ($payment['reference_file_name'] ?? ''),
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $dateCmp = strcmp((string) ($b['entry_date'] ?? ''), (string) ($a['entry_date'] ?? ''));
            if ($dateCmp !== 0) {
                return $dateCmp;
            }
            return strcmp((string) ($a['entry_type'] ?? ''), (string) ($b['entry_type'] ?? ''));
        });

        $openingAmount = 0.0;
        if ($dateFrom !== '') {
            $allFilters = $filters;
            $allFilters['date_from'] = '';
            $allFilters['date_to'] = '';
            foreach ($this->labourLedgerDataset($allFilters)['rows'] as $historic) {
                if ((string) ($historic['entry_date'] ?? '') >= $dateFrom) continue;
                $openingAmount += (float) ($historic['bill_amount'] ?? 0) - (float) ($historic['payment_amount'] ?? 0);
            }
        }
        $billAmount = array_sum(array_column($rows, 'bill_amount'));
        $paymentAmount = array_sum(array_column($rows, 'payment_amount'));

        return [
            'rows' => $rows,
            'summary' => [
                'opening_amount' => $openingAmount,
                'bill_amount' => $billAmount,
                'payment_amount' => $paymentAmount,
                'closing_amount' => $openingAmount + $billAmount - $paymentAmount,
                'pending_amount' => array_sum(array_column($rows, 'pending_amount')),
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function accountPaymentsDataset(): array
    {
        $db = db_connect();
        if (! $db->tableExists('account_payments')) {
            return [];
        }

        return $db->table('account_payments ap')
            ->select('ap.*, k.name as karigar_name, v.name as vendor_name, lb.bill_no', false)
            ->join('karigars k', 'k.id = ap.karigar_id', 'left')
            ->join('vendors v', 'v.id = ap.vendor_id', 'left')
            ->join('labour_bills lb', 'lb.id = ap.labour_bill_id', 'left')
            ->orderBy('ap.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function journalVouchersDataset(): array
    {
        $db = db_connect();
        if (! $db->tableExists('account_journal_vouchers')) {
            return [];
        }

        $rows = $db->table('account_journal_vouchers')
            ->orderBy('voucher_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['from_party_name'] = $this->partyName((string) ($row['from_party_type'] ?? ''), (int) ($row['from_party_id'] ?? 0));
            $row['to_party_name'] = $this->partyName((string) ($row['to_party_type'] ?? ''), (int) ($row['to_party_id'] ?? 0));
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string,float|int>
     */
    private function journalVoucherSummary(): array
    {
        $summary = [
            'voucher_count' => 0,
            'party_to_party_amount' => 0.0,
            'expenditure_amount' => 0.0,
        ];

        foreach ($this->journalVouchersDataset() as $row) {
            $summary['voucher_count']++;
            if ((string) ($row['status'] ?? 'Posted') !== 'Posted') {
                continue;
            }
            if ((string) ($row['voucher_type'] ?? '') === 'expenditure') {
                $summary['expenditure_amount'] += (float) ($row['amount'] ?? 0);
            } else {
                $summary['party_to_party_amount'] += (float) ($row['amount'] ?? 0);
            }
        }

        return $summary;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function journalVoucherLedgerRows(): array
    {
        $rows = [];
        foreach ($this->journalVouchersDataset() as $voucher) {
            $amount = (float) ($voucher['amount'] ?? 0);
            $referenceNo = (string) (($voucher['reference_no'] ?? '') ?: ($voucher['voucher_no'] ?? ''));
            $status = (string) ($voucher['status'] ?? 'Posted');
            if ($status !== 'Posted') {
                continue;
            }

            if ((string) ($voucher['voucher_type'] ?? '') === 'party_to_party') {
                $rows[] = $this->ledgerRow([
                    'transaction_date' => $voucher['voucher_date'] ?? '',
                    'transaction_type' => 'Journal Voucher',
                    'reference_no' => $referenceNo,
                    'party_type' => (string) ($voucher['to_party_type'] ?? ''),
                    'party_id' => (int) ($voucher['to_party_id'] ?? 0),
                    'party_name' => (string) ($voucher['to_party_name'] ?? '-'),
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                    'balance_amount' => 0,
                    'status' => $status,
                    'payment_mode' => (string) ($voucher['payment_mode'] ?? ''),
                    'material_type' => 'Accounts',
                    'details' => 'Party transfer from ' . (string) ($voucher['from_party_name'] ?? '-'),
                    'notes' => (string) ($voucher['notes'] ?? ''),
                ]);
                $rows[] = $this->ledgerRow([
                    'transaction_date' => $voucher['voucher_date'] ?? '',
                    'transaction_type' => 'Journal Voucher',
                    'reference_no' => $referenceNo,
                    'party_type' => (string) ($voucher['from_party_type'] ?? ''),
                    'party_id' => (int) ($voucher['from_party_id'] ?? 0),
                    'party_name' => (string) ($voucher['from_party_name'] ?? '-'),
                    'debit_amount' => 0,
                    'credit_amount' => $amount,
                    'balance_amount' => 0,
                    'status' => $status,
                    'payment_mode' => (string) ($voucher['payment_mode'] ?? ''),
                    'material_type' => 'Accounts',
                    'details' => 'Party transfer to ' . (string) ($voucher['to_party_name'] ?? '-'),
                    'notes' => (string) ($voucher['notes'] ?? ''),
                ]);
                continue;
            }

            $rows[] = $this->ledgerRow([
                'transaction_date' => $voucher['voucher_date'] ?? '',
                'transaction_type' => 'Expenditure',
                'reference_no' => $referenceNo,
                'party_type' => 'expense',
                'party_id' => 0,
                'party_name' => (string) (($voucher['expense_head'] ?? '') ?: 'Expenditure'),
                'debit_amount' => $amount,
                'credit_amount' => 0,
                'balance_amount' => 0,
                'status' => $status,
                'payment_mode' => (string) ($voucher['payment_mode'] ?? ''),
                'material_type' => 'Expense',
                'details' => (string) ($voucher['to_party_name'] ?? ''),
                'notes' => (string) ($voucher['notes'] ?? ''),
            ]);
        }

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function karigarOptionsWithBalance(): array
    {
        $balances = [];
        foreach ($this->labourBillsDataset() as $row) {
            $id = (int) ($row['karigar_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $balances[$id] = ($balances[$id] ?? 0) + (float) ($row['pending_amount'] ?? 0);
        }

        $rows = $this->karigarOptions();
        foreach ($rows as &$row) {
            $row['balance_amount'] = round((float) ($balances[(int) ($row['id'] ?? 0)] ?? 0), 2);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function vendorOptionsWithBalance(): array
    {
        $balances = [];
        foreach ($this->purchaseBillsDataset() as $row) {
            $id = (int) ($row['vendor_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $balances[$id] = ($balances[$id] ?? 0) + (float) ($row['pending_amount'] ?? 0);
        }

        $rows = $this->vendorOptions();
        foreach ($rows as &$row) {
            $row['balance_amount'] = round((float) ($balances[(int) ($row['id'] ?? 0)] ?? 0), 2);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function karigarOptions(): array
    {
        $db = db_connect();
        if (! $db->tableExists('karigars')) {
            return [];
        }

        return $db->table('karigars')
            ->select('id, name, phone, is_active')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,summary:array<string,float|int>,transaction_types:list<string>,statuses:list<string>}
     */
    private function generalLedgerDataset(array $filters): array
    {
        $rows = [];

        foreach ($this->purchaseBillsDataset() as $bill) {
            $amountAvailable = (bool) ($bill['amount_available'] ?? true);
            $rows[] = $this->ledgerRow([
                'transaction_date' => $bill['purchase_date'] ?? '',
                'transaction_type' => 'Purchase Bill',
                'reference_no' => strtoupper((string) ($bill['source_type'] ?? 'purchase')) . '#' . (int) ($bill['source_id'] ?? 0),
                'party_type' => 'vendor',
                'party_id' => (int) ($bill['vendor_id'] ?? 0),
                'party_name' => (string) ($bill['supplier_name'] ?? '-'),
                'bill_no' => strtoupper((string) ($bill['source_type'] ?? 'purchase')) . '#' . (int) ($bill['source_id'] ?? 0),
                'order_no' => '',
                'debit_amount' => 0,
                'credit_amount' => (float) ($bill['amount'] ?? 0),
                'balance_amount' => (float) ($bill['pending_amount'] ?? 0),
                'status' => (string) ($bill['payment_status'] ?? 'Pending'),
                'payment_mode' => '',
                'material_type' => (string) ($bill['category'] ?? 'Purchase'),
                'details' => $amountAvailable
                    ? 'Paid: Rs ' . number_format((float) ($bill['paid_amount'] ?? 0), 2)
                    : ((string) ($bill['reconciliation_status'] ?? '') ?: 'Source bill recorded; amount not supplied'),
                'notes' => '',
                'file_path' => '',
            ]);
        }

        foreach ($this->labourBillsDataset() as $bill) {
            $rows[] = $this->ledgerRow([
                'transaction_date' => $bill['bill_date'] ?? '',
                'transaction_type' => 'Labour Bill',
                'reference_no' => (string) ($bill['bill_no'] ?? ''),
                'party_type' => 'karigar',
                'party_id' => (int) ($bill['karigar_id'] ?? 0),
                'party_name' => (string) ($bill['karigar_name'] ?? '-'),
                'bill_no' => (string) ($bill['bill_no'] ?? ''),
                'order_no' => (string) ($bill['order_no'] ?? ''),
                'debit_amount' => 0,
                'credit_amount' => (float) ($bill['total_amount'] ?? 0),
                'balance_amount' => (float) ($bill['pending_amount'] ?? 0),
                'status' => (string) ($bill['payment_status'] ?? 'Pending'),
                'payment_mode' => '',
                'material_type' => 'Labour',
                'details' => 'Gold: ' . number_format((float) ($bill['gold_weight_gm'] ?? 0), 3) . ' gm',
                'notes' => '',
                'file_path' => '',
            ]);
        }

        foreach ($this->saleBillsDataset() as $sale) {
            $rows[] = $this->ledgerRow([
                'transaction_date' => $sale['sale_date'] ?? '',
                'transaction_type' => 'Sale Bill',
                'reference_no' => (string) (($sale['invoice_no'] ?? '') ?: ($sale['sale_no'] ?? '')),
                'party_type' => 'customer',
                'party_id' => (int) ($sale['customer_id'] ?? 0),
                'party_name' => (string) ($sale['customer_name'] ?? '-'),
                'bill_no' => (string) (($sale['invoice_no'] ?? '') ?: ($sale['sale_no'] ?? '')),
                'order_no' => '',
                'debit_amount' => (float) ($sale['total_amount'] ?? 0),
                'credit_amount' => 0,
                'balance_amount' => (float) ($sale['pending_amount'] ?? 0),
                'status' => (string) ($sale['payment_status'] ?? 'Pending'),
                'payment_mode' => '',
                'material_type' => 'Sale',
                'details' => 'Paid: Rs ' . number_format((float) ($sale['paid_amount'] ?? 0), 2),
                'notes' => '',
                'file_path' => '',
            ]);
        }

        $rows = array_merge($rows, $this->customerReceiptLedgerRows());

        foreach ($this->debitNotesDataset() as $note) {
            $partyType = (string) ($note['party_type'] ?? '');
            $rows[] = $this->ledgerRow([
                'transaction_date' => $note['note_date'] ?? '',
                'transaction_type' => 'Debit Note',
                'reference_no' => (string) ($note['note_no'] ?? ''),
                'party_type' => $partyType,
                'party_id' => $partyType === 'vendor' ? (int) ($note['vendor_id'] ?? 0) : (int) ($note['customer_id'] ?? 0),
                'party_name' => (string) ($note['party_name'] ?? '-'),
                'bill_no' => (string) (($note['invoice_no'] ?? '') ?: ($note['reference_no'] ?? '')),
                'order_no' => (string) ($note['order_no'] ?? ''),
                'debit_amount' => (float) ($note['total_amount'] ?? 0),
                'credit_amount' => 0,
                'balance_amount' => 0,
                'status' => (string) ($note['status'] ?? 'Posted'),
                'payment_mode' => '',
                'material_type' => 'Adjustment',
                'details' => (string) ($note['reason'] ?? ''),
                'notes' => (string) ($note['notes'] ?? ''),
                'file_path' => '',
            ]);
        }

        foreach ($this->creditNotesDataset() as $note) {
            $partyType = (string) ($note['party_type'] ?? '');
            $rows[] = $this->ledgerRow([
                'transaction_date' => $note['note_date'] ?? '',
                'transaction_type' => 'Credit Note',
                'reference_no' => (string) ($note['note_no'] ?? ''),
                'party_type' => $partyType,
                'party_id' => $partyType === 'vendor' ? (int) ($note['vendor_id'] ?? 0) : (int) ($note['customer_id'] ?? 0),
                'party_name' => (string) ($note['party_name'] ?? '-'),
                'bill_no' => (string) (($note['invoice_no'] ?? '') ?: ($note['reference_no'] ?? '')),
                'order_no' => (string) ($note['order_no'] ?? ''),
                'debit_amount' => 0,
                'credit_amount' => (float) ($note['total_amount'] ?? 0),
                'balance_amount' => 0,
                'status' => (string) ($note['status'] ?? 'Posted'),
                'payment_mode' => '',
                'material_type' => 'Adjustment',
                'details' => (string) ($note['reason'] ?? ''),
                'notes' => (string) ($note['notes'] ?? ''),
                'file_path' => '',
            ]);
        }

        foreach ($this->accountPaymentsDataset() as $payment) {
            $partyType = (string) ($payment['party_type'] ?? '');
            $partyName = $partyType === 'vendor' ? (string) ($payment['vendor_name'] ?? '-') : (string) ($payment['karigar_name'] ?? '-');
            $billNo = '-';
            if ((string) ($payment['bill_type'] ?? '') === 'labour') {
                $billNo = (string) (($payment['bill_no'] ?? '') ?: ('Labour #' . (int) ($payment['labour_bill_id'] ?? 0)));
            } elseif ((string) ($payment['bill_type'] ?? '') === 'purchase') {
                $billNo = ucfirst((string) ($payment['bill_source_type'] ?? 'Purchase')) . ' #' . (int) ($payment['bill_source_id'] ?? 0);
            }

            $rows[] = $this->ledgerRow([
                'transaction_date' => $payment['payment_date'] ?? '',
                'transaction_type' => 'Payment',
                'reference_no' => (string) (($payment['reference_no'] ?? '') ?: ($payment['payment_no'] ?? '')),
                'party_type' => $partyType,
                'party_id' => $partyType === 'vendor' ? (int) ($payment['vendor_id'] ?? 0) : (int) ($payment['karigar_id'] ?? 0),
                'party_name' => $partyName,
                'bill_no' => $billNo,
                'order_no' => '',
                'debit_amount' => (float) ($payment['amount'] ?? 0),
                'credit_amount' => 0,
                'balance_amount' => 0,
                'status' => 'Paid',
                'payment_mode' => (string) ($payment['payment_mode'] ?? ''),
                'material_type' => 'Accounts',
                'details' => (string) ($payment['payment_no'] ?? ''),
                'notes' => (string) ($payment['notes'] ?? ''),
                'file_path' => (string) ($payment['reference_file_path'] ?? ''),
            ]);
        }

        foreach ($this->productionDocumentPaymentRows() as $payment) {
            $rows[] = $this->ledgerRow([
                'transaction_date' => $payment['payment_date'],
                'transaction_type' => 'Vendor Payment',
                'reference_no' => $payment['reference_no'],
                'party_type' => 'vendor',
                'party_id' => $payment['vendor_id'],
                'party_name' => $payment['vendor_name'],
                'bill_no' => $payment['invoice_no'],
                'order_no' => '',
                'debit_amount' => $payment['amount'],
                'credit_amount' => 0,
                'balance_amount' => 0,
                'status' => 'Paid',
                'payment_mode' => 'Source Record',
                'material_type' => 'Accounts',
                'details' => $payment['details'],
                'notes' => $payment['details'],
                'file_path' => '',
            ]);
        }

        $rows = array_merge($rows, $this->journalVoucherLedgerRows());

        $transactionTypes = array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string) ($row['transaction_type'] ?? ''), $rows))));
        sort($transactionTypes);
        $statuses = array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string) ($row['status'] ?? ''), $rows))));
        sort($statuses);

        $allFilteredRows = $this->filterGeneralLedgerRows($rows, array_merge($filters, ['date_from' => '', 'date_to' => '']));
        $openingAmount = 0.0;
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            foreach ($allFilteredRows as $historic) {
                if ((string) ($historic['transaction_date'] ?? '') >= $dateFrom) continue;
                $openingAmount += (float) ($historic['debit_amount'] ?? 0) - (float) ($historic['credit_amount'] ?? 0);
            }
        }
        $rows = $this->filterGeneralLedgerRows($rows, $filters);
        usort($rows, static function (array $a, array $b): int {
            $dateCmp = strcmp((string) ($b['transaction_date'] ?? ''), (string) ($a['transaction_date'] ?? ''));
            return $dateCmp !== 0 ? $dateCmp : strcmp((string) ($b['reference_no'] ?? ''), (string) ($a['reference_no'] ?? ''));
        });

        $debitAmount = array_sum(array_column($rows, 'debit_amount'));
        $creditAmount = array_sum(array_column($rows, 'credit_amount'));
        return [
            'rows' => $rows,
            'summary' => [
                'row_count' => count($rows),
                'opening_amount' => $openingAmount,
                'debit_amount' => $debitAmount,
                'credit_amount' => $creditAmount,
                'closing_amount' => $openingAmount + $debitAmount - $creditAmount,
                'balance_amount' => array_sum(array_column($rows, 'balance_amount')),
            ],
            'transaction_types' => $transactionTypes,
            'statuses' => $statuses,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function customerReceiptLedgerRows(): array
    {
        $db = db_connect();
        if (! $db->tableExists('customer_receipts')) {
            return [];
        }

        $rows = $db->table('customer_receipts cr')
            ->select('cr.*, c.name as customer_name, i.invoice_no, o.order_no', false)
            ->join('customers c', 'c.id = cr.customer_id', 'left')
            ->join('invoices i', 'i.id = cr.invoice_id', 'left')
            ->join('orders o', 'o.id = i.order_id', 'left')
            ->orderBy('cr.receipt_date', 'DESC')
            ->get()
            ->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->ledgerRow([
                'transaction_date' => $row['receipt_date'] ?? '',
                'transaction_type' => 'Customer Receipt',
                'reference_no' => (string) (($row['reference_no'] ?? '') ?: ($row['receipt_no'] ?? '')),
                'party_type' => 'customer',
                'party_id' => (int) ($row['customer_id'] ?? 0),
                'party_name' => (string) ($row['customer_name'] ?? '-'),
                'bill_no' => (string) ($row['invoice_no'] ?? ''),
                'order_no' => (string) ($row['order_no'] ?? ''),
                'debit_amount' => 0,
                'credit_amount' => (float) ($row['amount'] ?? 0),
                'balance_amount' => 0,
                'status' => 'Received',
                'payment_mode' => (string) ($row['payment_mode'] ?? ''),
                'material_type' => 'Accounts',
                'details' => (string) ($row['receipt_no'] ?? ''),
                'notes' => (string) ($row['notes'] ?? ''),
                'file_path' => '',
            ]);
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,summary:array<string,float|int>,categories:list<string>,transaction_types:list<string>,material_types:list<string>}
     */
    private function vendorTransactionLedgerDataset(array $filters): array
    {
        $rows = [];
        foreach ($this->purchaseBillsDataset() as $bill) {
            $rows[] = $this->vendorTransactionRow([
                'transaction_date' => $bill['purchase_date'] ?? '',
                'category' => 'Purchase',
                'transaction_type' => 'Purchase Bill',
                'material_type' => (string) ($bill['category'] ?? 'Purchase'),
                'reference_no' => strtoupper((string) ($bill['source_type'] ?? 'purchase')) . '#' . (int) ($bill['source_id'] ?? 0),
                'source_label' => (string) (($bill['invoice_no'] ?? '') ?: strtoupper((string) ($bill['source_type'] ?? 'purchase')) . '#' . (int) ($bill['source_id'] ?? 0)),
                'party_type' => 'vendor',
                'party_id' => (int) ($bill['vendor_id'] ?? 0),
                'vendor_id' => (int) ($bill['vendor_id'] ?? 0),
                'party_name' => (string) ($bill['supplier_name'] ?? '-'),
                'payable_amount' => (float) ($bill['amount'] ?? 0),
                'balance_amount' => (float) ($bill['pending_amount'] ?? 0),
                'status' => (string) ($bill['payment_status'] ?? 'Pending'),
                'details' => 'Paid: Rs ' . number_format((float) ($bill['paid_amount'] ?? 0), 2),
            ]);
        }

        foreach ($this->accountPaymentsDataset() as $payment) {
            $partyType = (string) ($payment['party_type'] ?? '');
            $rows[] = $this->vendorTransactionRow([
                'transaction_date' => $payment['payment_date'] ?? '',
                'category' => 'Payment',
                'transaction_type' => $partyType === 'vendor' ? 'Vendor Payment' : 'Karigar Payment',
                'material_type' => 'Money',
                'reference_no' => (string) (($payment['reference_no'] ?? '') ?: ($payment['payment_no'] ?? '')),
                'source_label' => (string) ($payment['payment_no'] ?? ''),
                'party_type' => $partyType,
                'party_id' => $partyType === 'vendor' ? (int) ($payment['vendor_id'] ?? 0) : (int) ($payment['karigar_id'] ?? 0),
                'vendor_id' => $partyType === 'vendor' ? (int) ($payment['vendor_id'] ?? 0) : 0,
                'karigar_id' => $partyType === 'karigar' ? (int) ($payment['karigar_id'] ?? 0) : 0,
                'party_name' => $partyType === 'vendor' ? (string) ($payment['vendor_name'] ?? '-') : (string) ($payment['karigar_name'] ?? '-'),
                'paid_amount' => (float) ($payment['amount'] ?? 0),
                'payment_mode' => (string) ($payment['payment_mode'] ?? ''),
                'status' => 'Paid',
                'details' => $this->paymentBillLabel($payment),
                'notes' => (string) ($payment['notes'] ?? ''),
                'file_path' => (string) ($payment['reference_file_path'] ?? ''),
            ]);
        }

        foreach ($this->productionDocumentPaymentRows() as $payment) {
            $rows[] = $this->vendorTransactionRow([
                'transaction_date' => $payment['payment_date'],
                'category' => 'Payment',
                'transaction_type' => 'Vendor Payment',
                'material_type' => 'Money',
                'reference_no' => $payment['reference_no'],
                'source_label' => $payment['invoice_no'],
                'party_type' => 'vendor',
                'party_id' => $payment['vendor_id'],
                'vendor_id' => $payment['vendor_id'],
                'party_name' => $payment['vendor_name'],
                'paid_amount' => $payment['amount'],
                'payment_mode' => 'Source Record',
                'status' => 'Paid',
                'details' => $payment['details'],
                'notes' => $payment['details'],
            ]);
        }

        $rows = array_merge(
            $rows,
            $this->materialMovementRows(),
            $this->goldInventoryMovementRows(),
            $this->diamondInventoryMovementRows(),
            $this->stoneInventoryMovementRows(),
            $this->productionDetailedIssueRows()
        );

        $categories = $this->uniqueColumnValues($rows, 'category');
        $transactionTypes = $this->uniqueColumnValues($rows, 'transaction_type');
        $materialTypes = $this->uniqueColumnValues($rows, 'material_type');

        $rows = $this->filterVendorTransactionRows($rows, $filters);
        usort($rows, static function (array $a, array $b): int {
            $dateCmp = strcmp((string) ($b['transaction_date'] ?? ''), (string) ($a['transaction_date'] ?? ''));
            return $dateCmp !== 0 ? $dateCmp : strcmp((string) ($b['reference_no'] ?? ''), (string) ($a['reference_no'] ?? ''));
        });

        $opening = ['gold' => 0.0, 'cts' => 0.0, 'money' => 0.0];
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $allFilters = $filters;
            $allFilters['date_from'] = '';
            $allFilters['date_to'] = '';
            foreach ($this->vendorTransactionLedgerDataset($allFilters)['rows'] as $historic) {
                if ((string) ($historic['transaction_date'] ?? '') >= $dateFrom) continue;
                $opening['gold'] += (float) ($historic['issue_gold_gm'] ?? 0) - (float) ($historic['receive_gold_gm'] ?? 0);
                $opening['cts'] += (float) ($historic['issue_cts'] ?? 0) - (float) ($historic['receive_cts'] ?? 0);
                $opening['money'] += (float) ($historic['payable_amount'] ?? 0) - (float) ($historic['paid_amount'] ?? 0);
            }
        }
        $issueGold = array_sum(array_column($rows, 'issue_gold_gm'));
        $receiveGold = array_sum(array_column($rows, 'receive_gold_gm'));
        $issueCts = array_sum(array_column($rows, 'issue_cts'));
        $receiveCts = array_sum(array_column($rows, 'receive_cts'));
        $payable = array_sum(array_column($rows, 'payable_amount'));
        $paid = array_sum(array_column($rows, 'paid_amount'));
        return [
            'rows' => $rows,
            'summary' => [
                'row_count' => count($rows),
                'opening_gold_gm' => $opening['gold'],
                'issue_gold_gm' => $issueGold,
                'receive_gold_gm' => $receiveGold,
                'closing_gold_gm' => $opening['gold'] + $issueGold - $receiveGold,
                'opening_cts' => $opening['cts'],
                'issue_cts' => $issueCts,
                'receive_cts' => $receiveCts,
                'closing_cts' => $opening['cts'] + $issueCts - $receiveCts,
                'opening_amount' => $opening['money'],
                'payable_amount' => $payable,
                'paid_amount' => $paid,
                'closing_amount' => $opening['money'] + $payable - $paid,
                'balance_amount' => array_sum(array_column($rows, 'balance_amount')),
            ],
            'categories' => $categories,
            'transaction_types' => $transactionTypes,
            'material_types' => $materialTypes,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function materialMovementRows(): array
    {
        $db = db_connect();
        if (! $db->tableExists('order_material_movements')) {
            return [];
        }

        $rows = $db->table('order_material_movements om')
            ->select('om.*, o.order_no, k.name as karigar_name', false)
            ->join('orders o', 'o.id = om.order_id', 'left')
            ->join('karigars k', 'k.id = om.karigar_id', 'left')
            ->orderBy('om.created_at', 'DESC')
            ->get()
            ->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $isIssue = strtolower((string) ($row['movement_type'] ?? '')) === 'issue';
            $result[] = $this->vendorTransactionRow([
                'transaction_date' => substr((string) ($row['created_at'] ?? ''), 0, 10),
                'category' => 'Jobwork Material',
                'transaction_type' => $isIssue ? 'Material Issue' : 'Material Receive',
                'material_type' => 'Mixed',
                'reference_no' => 'MOV#' . (int) ($row['id'] ?? 0),
                'source_label' => 'Order Movement',
                'order_no' => (string) ($row['order_no'] ?? ''),
                'party_type' => 'karigar',
                'party_id' => (int) ($row['karigar_id'] ?? 0),
                'karigar_id' => (int) ($row['karigar_id'] ?? 0),
                'party_name' => (string) ($row['karigar_name'] ?? '-'),
                'issue_gold_gm' => $isIssue ? (float) ($row['gold_gm'] ?? 0) : 0,
                'receive_gold_gm' => $isIssue ? 0 : (float) ($row['gold_gm'] ?? 0),
                'issue_cts' => $isIssue ? (float) ($row['diamond_cts'] ?? 0) : 0,
                'receive_cts' => $isIssue ? 0 : (float) ($row['diamond_cts'] ?? 0),
                'details' => (string) ($row['notes'] ?? ''),
                'notes' => (string) ($row['notes'] ?? ''),
            ]);
        }

        return $result;
    }

    /** @return list<array<string,mixed>> */
    private function productionDetailedIssueRows(): array
    {
        $db = db_connect();
        if (! $db->tableExists('production_diamond_issue_lines')) {
            return [];
        }
        $list = $db->table('production_diamond_issue_lines p')
            ->select('p.*, k.name as karigar_name', false)
            ->join('karigars k', 'k.id = p.karigar_id', 'left')
            ->orderBy('p.issue_date', 'DESC')
            ->orderBy('p.id', 'DESC')
            ->get()->getResultArray();
        $rows = [];
        foreach ($list as $row) {
            $rows[] = $this->vendorTransactionRow([
                'transaction_date' => (string) ($row['issue_date'] ?? ''),
                'category' => 'Production Source Detail',
                'transaction_type' => 'Diamond Issue Detail',
                'material_type' => 'Diamond',
                'reference_no' => (string) (($row['issue_group'] ?? '') . ':' . (int) ($row['source_row'] ?? 0)),
                'source_label' => (string) ($row['bag_label'] ?? 'Issuement workbook'),
                'party_type' => 'karigar',
                'party_id' => (int) ($row['karigar_id'] ?? 0),
                'karigar_id' => (int) ($row['karigar_id'] ?? 0),
                'party_name' => (string) ($row['karigar_name'] ?? '-'),
                'issue_cts' => (float) ($row['weight_cts'] ?? 0),
                'details' => sprintf(
                    '%s | %s | %s | %s pcs',
                    (string) ($row['design_no'] ?? '-'),
                    trim((string) (($row['quality'] ?? '') . ' ' . ($row['shade'] ?? '') . ' ' . ($row['size_label'] ?? ''))),
                    (string) ($row['bag_label'] ?? '-'),
                    number_format((float) ($row['pcs'] ?? 0), 0)
                ),
                'notes' => 'Exact source row ' . (string) ($row['source_sheet'] ?? '') . ':' . (int) ($row['source_row'] ?? 0),
            ]);
        }
        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function goldInventoryMovementRows(): array
    {
        $rows = [];
        $db = db_connect();
        if ($db->tableExists('gold_inventory_issue_headers') && $db->tableExists('gold_inventory_issue_lines')) {
            $issueRows = $db->table('gold_inventory_issue_headers h')
                ->select('h.*, k.name as karigar_name, COALESCE(SUM(l.weight_gm),0) as weight_gm, COALESCE(SUM(l.fine_weight_gm),0) as fine_weight_gm, COALESCE(SUM(l.line_value),0) as amount', false)
                ->join('gold_inventory_issue_lines l', 'l.issue_id = h.id', 'left')
                ->join('karigars k', 'k.id = h.karigar_id', 'left')
                ->groupBy('h.id')
                ->get()
                ->getResultArray();
            foreach ($issueRows as $row) {
                $rows[] = $this->vendorTransactionRow([
                    'transaction_date' => $row['issue_date'] ?? '',
                    'category' => 'Issue',
                    'transaction_type' => 'Gold Issue',
                    'material_type' => 'Gold',
                    'reference_no' => (string) (($row['voucher_no'] ?? '') ?: 'GI#' . (int) ($row['id'] ?? 0)),
                    'source_label' => 'Gold Issue',
                    'party_type' => 'karigar',
                    'party_id' => (int) ($row['karigar_id'] ?? 0),
                    'karigar_id' => (int) ($row['karigar_id'] ?? 0),
                    'party_name' => (string) (($row['karigar_name'] ?? '') ?: ($row['issue_to'] ?? '-')),
                    'issue_gold_gm' => (float) ($row['weight_gm'] ?? 0),
                    'payable_amount' => (float) ($row['amount'] ?? 0),
                    'details' => 'Fine: ' . number_format((float) ($row['fine_weight_gm'] ?? 0), 3) . ' gm',
                    'notes' => (string) ($row['notes'] ?? ''),
                    'file_path' => (string) ($row['attachment_path'] ?? ''),
                ]);
            }
        }

        if ($db->tableExists('gold_inventory_return_headers') && $db->tableExists('gold_inventory_return_lines')) {
            $returnRows = $db->table('gold_inventory_return_headers h')
                ->select('h.*, k.name as karigar_name, COALESCE(SUM(l.weight_gm),0) as weight_gm, COALESCE(SUM(l.fine_weight_gm),0) as fine_weight_gm, COALESCE(SUM(l.line_value),0) as amount', false)
                ->join('gold_inventory_return_lines l', 'l.return_id = h.id', 'left')
                ->join('karigars k', 'k.id = h.karigar_id', 'left')
                ->groupBy('h.id')
                ->get()
                ->getResultArray();
            foreach ($returnRows as $row) {
                $rows[] = $this->vendorTransactionRow([
                    'transaction_date' => $row['return_date'] ?? '',
                    'category' => 'Receive',
                    'transaction_type' => 'Gold Receive',
                    'material_type' => 'Gold',
                    'reference_no' => (string) (($row['voucher_no'] ?? '') ?: 'GR#' . (int) ($row['id'] ?? 0)),
                    'source_label' => 'Gold Return',
                    'party_type' => 'karigar',
                    'party_id' => (int) ($row['karigar_id'] ?? 0),
                    'karigar_id' => (int) ($row['karigar_id'] ?? 0),
                    'party_name' => (string) (($row['karigar_name'] ?? '') ?: ($row['return_from'] ?? '-')),
                    'receive_gold_gm' => (float) ($row['weight_gm'] ?? 0),
                    'paid_amount' => (float) ($row['amount'] ?? 0),
                    'details' => 'Fine: ' . number_format((float) ($row['fine_weight_gm'] ?? 0), 3) . ' gm',
                    'notes' => (string) ($row['notes'] ?? ''),
                    'file_path' => (string) ($row['attachment_path'] ?? ''),
                ]);
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function diamondInventoryMovementRows(): array
    {
        $rows = [];
        $db = db_connect();
        if ($db->tableExists('issue_headers') && $db->tableExists('issue_lines')) {
            $issueRows = $db->table('issue_headers h')
                ->select('h.*, k.name as karigar_name, COALESCE(SUM(l.pcs),0) as pcs, COALESCE(SUM(l.carat),0) as cts, COALESCE(SUM(l.line_value),0) as amount', false)
                ->join('issue_lines l', 'l.issue_id = h.id', 'left')
                ->join('karigars k', 'k.id = h.karigar_id', 'left')
                ->groupBy('h.id')
                ->get()
                ->getResultArray();
            foreach ($issueRows as $row) {
                $rows[] = $this->vendorTransactionRow([
                    'transaction_date' => $row['issue_date'] ?? '',
                    'category' => 'Issue',
                    'transaction_type' => 'Diamond Issue',
                    'material_type' => 'Diamond',
                    'reference_no' => (string) (($row['voucher_no'] ?? '') ?: 'DI#' . (int) ($row['id'] ?? 0)),
                    'source_label' => 'Diamond Issue',
                    'party_type' => 'karigar',
                    'party_id' => (int) ($row['karigar_id'] ?? 0),
                    'karigar_id' => (int) ($row['karigar_id'] ?? 0),
                    'party_name' => (string) (($row['karigar_name'] ?? '') ?: ($row['issue_to'] ?? '-')),
                    'issue_pcs' => (float) ($row['pcs'] ?? 0),
                    'issue_cts' => (float) ($row['cts'] ?? 0),
                    'payable_amount' => (float) ($row['amount'] ?? 0),
                    'details' => 'Diamond pcs: ' . number_format((float) ($row['pcs'] ?? 0), 0),
                    'notes' => (string) ($row['notes'] ?? ''),
                    'file_path' => (string) ($row['attachment_path'] ?? ''),
                ]);
            }
        }

        if ($db->tableExists('return_headers') && $db->tableExists('return_lines')) {
            $returnRows = $db->table('return_headers h')
                ->select('h.*, k.name as karigar_name, COALESCE(SUM(l.pcs),0) as pcs, COALESCE(SUM(l.carat),0) as cts, COALESCE(SUM(l.line_value),0) as amount', false)
                ->join('return_lines l', 'l.return_id = h.id', 'left')
                ->join('karigars k', 'k.id = h.karigar_id', 'left')
                ->groupBy('h.id')
                ->get()
                ->getResultArray();
            foreach ($returnRows as $row) {
                $rows[] = $this->vendorTransactionRow([
                    'transaction_date' => $row['return_date'] ?? '',
                    'category' => 'Receive',
                    'transaction_type' => 'Diamond Receive',
                    'material_type' => 'Diamond',
                    'reference_no' => (string) (($row['voucher_no'] ?? '') ?: 'DR#' . (int) ($row['id'] ?? 0)),
                    'source_label' => 'Diamond Return',
                    'party_type' => 'karigar',
                    'party_id' => (int) ($row['karigar_id'] ?? 0),
                    'karigar_id' => (int) ($row['karigar_id'] ?? 0),
                    'party_name' => (string) (($row['karigar_name'] ?? '') ?: ($row['return_from'] ?? '-')),
                    'receive_pcs' => (float) ($row['pcs'] ?? 0),
                    'receive_cts' => (float) ($row['cts'] ?? 0),
                    'paid_amount' => (float) ($row['amount'] ?? 0),
                    'details' => 'Diamond pcs: ' . number_format((float) ($row['pcs'] ?? 0), 0),
                    'notes' => (string) ($row['notes'] ?? ''),
                    'file_path' => (string) ($row['attachment_path'] ?? ''),
                ]);
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function stoneInventoryMovementRows(): array
    {
        $rows = [];
        $db = db_connect();
        if ($db->tableExists('stone_inventory_issue_headers') && $db->tableExists('stone_inventory_issue_lines')) {
            $issueRows = $db->table('stone_inventory_issue_headers h')
                ->select('h.*, k.name as karigar_name, COALESCE(SUM(l.qty),0) as qty, COALESCE(SUM(l.line_value),0) as amount', false)
                ->join('stone_inventory_issue_lines l', 'l.issue_id = h.id', 'left')
                ->join('karigars k', 'k.id = h.karigar_id', 'left')
                ->groupBy('h.id')
                ->get()
                ->getResultArray();
            foreach ($issueRows as $row) {
                $rows[] = $this->vendorTransactionRow([
                    'transaction_date' => $row['issue_date'] ?? '',
                    'category' => 'Issue',
                    'transaction_type' => 'Stone Issue',
                    'material_type' => 'Stone',
                    'reference_no' => (string) (($row['voucher_no'] ?? '') ?: 'SI#' . (int) ($row['id'] ?? 0)),
                    'source_label' => 'Stone Issue',
                    'party_type' => 'karigar',
                    'party_id' => (int) ($row['karigar_id'] ?? 0),
                    'karigar_id' => (int) ($row['karigar_id'] ?? 0),
                    'party_name' => (string) (($row['karigar_name'] ?? '') ?: ($row['issue_to'] ?? '-')),
                    'issue_pcs' => (float) ($row['qty'] ?? 0),
                    'payable_amount' => (float) ($row['amount'] ?? 0),
                    'details' => 'Stone qty: ' . number_format((float) ($row['qty'] ?? 0), 0),
                    'notes' => (string) ($row['notes'] ?? ''),
                    'file_path' => (string) ($row['attachment_path'] ?? ''),
                ]);
            }
        }

        if ($db->tableExists('stone_inventory_return_headers') && $db->tableExists('stone_inventory_return_lines')) {
            $returnRows = $db->table('stone_inventory_return_headers h')
                ->select('h.*, k.name as karigar_name, COALESCE(SUM(l.qty),0) as qty, COALESCE(SUM(l.line_value),0) as amount', false)
                ->join('stone_inventory_return_lines l', 'l.return_id = h.id', 'left')
                ->join('karigars k', 'k.id = h.karigar_id', 'left')
                ->groupBy('h.id')
                ->get()
                ->getResultArray();
            foreach ($returnRows as $row) {
                $rows[] = $this->vendorTransactionRow([
                    'transaction_date' => $row['return_date'] ?? '',
                    'category' => 'Receive',
                    'transaction_type' => 'Stone Receive',
                    'material_type' => 'Stone',
                    'reference_no' => (string) (($row['voucher_no'] ?? '') ?: 'SR#' . (int) ($row['id'] ?? 0)),
                    'source_label' => 'Stone Return',
                    'party_type' => 'karigar',
                    'party_id' => (int) ($row['karigar_id'] ?? 0),
                    'karigar_id' => (int) ($row['karigar_id'] ?? 0),
                    'party_name' => (string) (($row['karigar_name'] ?? '') ?: ($row['return_from'] ?? '-')),
                    'receive_pcs' => (float) ($row['qty'] ?? 0),
                    'paid_amount' => (float) ($row['amount'] ?? 0),
                    'details' => 'Stone qty: ' . number_format((float) ($row['qty'] ?? 0), 0),
                    'notes' => (string) ($row['notes'] ?? ''),
                    'file_path' => (string) ($row['attachment_path'] ?? ''),
                ]);
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function vendorTransactionRow(array $data): array
    {
        return [
            'transaction_date' => (string) ($data['transaction_date'] ?? ''),
            'category' => (string) ($data['category'] ?? ''),
            'transaction_type' => (string) ($data['transaction_type'] ?? ''),
            'material_type' => (string) ($data['material_type'] ?? ''),
            'reference_no' => (string) ($data['reference_no'] ?? ''),
            'source_label' => (string) ($data['source_label'] ?? ''),
            'order_no' => (string) ($data['order_no'] ?? ''),
            'party_type' => (string) ($data['party_type'] ?? ''),
            'party_id' => (int) ($data['party_id'] ?? 0),
            'vendor_id' => (int) ($data['vendor_id'] ?? 0),
            'karigar_id' => (int) ($data['karigar_id'] ?? 0),
            'party_name' => (string) ($data['party_name'] ?? '-'),
            'issue_gold_gm' => round((float) ($data['issue_gold_gm'] ?? 0), 3),
            'receive_gold_gm' => round((float) ($data['receive_gold_gm'] ?? 0), 3),
            'issue_pcs' => round((float) ($data['issue_pcs'] ?? 0), 3),
            'receive_pcs' => round((float) ($data['receive_pcs'] ?? 0), 3),
            'issue_cts' => round((float) ($data['issue_cts'] ?? 0), 3),
            'receive_cts' => round((float) ($data['receive_cts'] ?? 0), 3),
            'payable_amount' => round((float) ($data['payable_amount'] ?? 0), 2),
            'paid_amount' => round((float) ($data['paid_amount'] ?? 0), 2),
            'balance_amount' => round((float) ($data['balance_amount'] ?? 0), 2),
            'payment_mode' => (string) ($data['payment_mode'] ?? ''),
            'status' => (string) ($data['status'] ?? ''),
            'details' => (string) ($data['details'] ?? ''),
            'notes' => (string) ($data['notes'] ?? ''),
            'file_path' => (string) ($data['file_path'] ?? ''),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private function uniqueColumnValues(array $rows, string $column): array
    {
        $values = array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string) ($row[$column] ?? ''), $rows))));
        sort($values);
        return $values;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function filterVendorTransactionRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, function (array $row) use ($filters): bool {
            if (! $this->dateInRangeOrEmpty((string) ($row['transaction_date'] ?? ''), (string) ($filters['date_from'] ?? ''), (string) ($filters['date_to'] ?? ''))) {
                return false;
            }
            foreach (['party_type', 'category', 'transaction_type', 'material_type'] as $key) {
                if ((string) ($filters[$key] ?? '') !== '' && (string) ($row[$key] ?? '') !== (string) $filters[$key]) {
                    return false;
                }
            }
            if ((int) ($filters['vendor_id'] ?? 0) > 0 && (int) ($row['vendor_id'] ?? 0) !== (int) $filters['vendor_id']) {
                return false;
            }
            if ((int) ($filters['karigar_id'] ?? 0) > 0 && (int) ($row['karigar_id'] ?? 0) !== (int) $filters['karigar_id']) {
                return false;
            }
            if ((string) ($filters['reference_no'] ?? '') !== '' && stripos((string) ($row['reference_no'] ?? ''), (string) $filters['reference_no']) === false) {
                return false;
            }
            if ((string) ($filters['search'] ?? '') !== '') {
                $needle = strtolower((string) $filters['search']);
                $haystack = strtolower(implode(' | ', [
                    $row['category'] ?? '',
                    $row['transaction_type'] ?? '',
                    $row['material_type'] ?? '',
                    $row['reference_no'] ?? '',
                    $row['source_label'] ?? '',
                    $row['order_no'] ?? '',
                    $row['party_name'] ?? '',
                    $row['details'] ?? '',
                    $row['notes'] ?? '',
                ]));
                if (strpos($haystack, $needle) === false) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * @param array<string,mixed> $payment
     */
    private function paymentBillLabel(array $payment): string
    {
        if ((string) ($payment['bill_type'] ?? '') === 'labour') {
            return (string) (($payment['bill_no'] ?? '') ?: ('Labour #' . (int) ($payment['labour_bill_id'] ?? 0)));
        }
        if ((string) ($payment['bill_type'] ?? '') === 'purchase') {
            return ucfirst((string) ($payment['bill_source_type'] ?? 'purchase')) . ' #' . (int) ($payment['bill_source_id'] ?? 0);
        }
        return '';
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function ledgerRow(array $data): array
    {
        return [
            'transaction_date' => (string) ($data['transaction_date'] ?? ''),
            'transaction_type' => (string) ($data['transaction_type'] ?? ''),
            'reference_no' => (string) ($data['reference_no'] ?? ''),
            'party_type' => (string) ($data['party_type'] ?? ''),
            'party_id' => (int) ($data['party_id'] ?? 0),
            'party_name' => (string) ($data['party_name'] ?? '-'),
            'bill_no' => (string) ($data['bill_no'] ?? ''),
            'order_no' => (string) ($data['order_no'] ?? ''),
            'debit_amount' => round((float) ($data['debit_amount'] ?? 0), 2),
            'credit_amount' => round((float) ($data['credit_amount'] ?? 0), 2),
            'balance_amount' => round((float) ($data['balance_amount'] ?? 0), 2),
            'status' => (string) ($data['status'] ?? ''),
            'payment_mode' => (string) ($data['payment_mode'] ?? ''),
            'material_type' => (string) ($data['material_type'] ?? ''),
            'details' => (string) ($data['details'] ?? ''),
            'notes' => (string) ($data['notes'] ?? ''),
            'file_path' => (string) ($data['file_path'] ?? ''),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function filterGeneralLedgerRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, function (array $row) use ($filters): bool {
            if (! $this->dateInRangeOrEmpty((string) ($row['transaction_date'] ?? ''), (string) ($filters['date_from'] ?? ''), (string) ($filters['date_to'] ?? ''))) {
                return false;
            }
            foreach (['transaction_type', 'party_type', 'status'] as $key) {
                if ((string) ($filters[$key] ?? '') !== '' && (string) ($row[$key] ?? '') !== (string) $filters[$key]) {
                    return false;
                }
            }
            if ((int) ($filters['customer_id'] ?? 0) > 0 && ((string) ($row['party_type'] ?? '') !== 'customer' || (int) ($row['party_id'] ?? 0) !== (int) $filters['customer_id'])) {
                return false;
            }
            if ((int) ($filters['vendor_id'] ?? 0) > 0 && ((string) ($row['party_type'] ?? '') !== 'vendor' || (int) ($row['party_id'] ?? 0) !== (int) $filters['vendor_id'])) {
                return false;
            }
            if ((int) ($filters['karigar_id'] ?? 0) > 0 && ((string) ($row['party_type'] ?? '') !== 'karigar' || (int) ($row['party_id'] ?? 0) !== (int) $filters['karigar_id'])) {
                return false;
            }
            if ((string) ($filters['reference_no'] ?? '') !== '' && stripos((string) ($row['reference_no'] ?? ''), (string) $filters['reference_no']) === false) {
                return false;
            }
            if ((string) ($filters['search'] ?? '') !== '') {
                $needle = strtolower((string) $filters['search']);
                $haystack = strtolower(implode(' | ', [
                    $row['transaction_type'] ?? '',
                    $row['reference_no'] ?? '',
                    $row['party_name'] ?? '',
                    $row['bill_no'] ?? '',
                    $row['order_no'] ?? '',
                    $row['details'] ?? '',
                    $row['notes'] ?? '',
                ]));
                if (strpos($haystack, $needle) === false) {
                    return false;
                }
            }
            return true;
        }));
    }

    private function dateInRangeOrEmpty(string $date, string $dateFrom, string $dateTo): bool
    {
        if ($date === '') {
            return $dateFrom === '' && $dateTo === '';
        }
        return $this->dateInRange($date, $dateFrom, $dateTo);
    }

    /**
     * @return array<string,mixed>
     */
    private function resolvePaymentBill(string $partyType, string $billRef): array
    {
        $empty = [
            'selected' => $billRef !== '',
            'found' => false,
            'bill_type' => null,
            'bill_source_type' => null,
            'bill_source_id' => null,
            'labour_bill_id' => null,
            'order_id' => null,
            'pending_amount' => 0.0,
        ];

        if ($billRef === '') {
            $empty['found'] = true;
            return $empty;
        }

        $parts = explode(':', $billRef);
        if (count($parts) < 2) {
            return $empty;
        }

        if ($partyType === 'karigar' && $parts[0] === 'labour') {
            $billId = (int) $parts[1];
            foreach ($this->labourBillsDataset() as $row) {
                if ((int) ($row['id'] ?? 0) === $billId) {
                    return [
                        'selected' => true,
                        'found' => true,
                        'bill_type' => 'labour',
                        'bill_source_type' => null,
                        'bill_source_id' => null,
                        'labour_bill_id' => $billId,
                        'order_id' => $row['order_id'] ?? null,
                        'pending_amount' => (float) ($row['pending_amount'] ?? 0),
                    ];
                }
            }
        }

        if ($partyType === 'vendor' && $parts[0] === 'purchase' && count($parts) >= 3) {
            $sourceType = (string) $parts[1];
            $sourceId = (int) $parts[2];
            foreach ($this->purchaseBillsDataset() as $row) {
                if ((string) ($row['source_type'] ?? '') === $sourceType && (int) ($row['source_id'] ?? 0) === $sourceId) {
                    return [
                        'selected' => true,
                        'found' => true,
                        'bill_type' => 'purchase',
                        'bill_source_type' => $sourceType,
                        'bill_source_id' => $sourceId,
                        'labour_bill_id' => null,
                        'order_id' => null,
                        'pending_amount' => (float) ($row['pending_amount'] ?? 0),
                    ];
                }
            }
        }

        return $empty;
    }

    private function postLabourBillPayment(int $billId, float $amount, string $paymentDate, ?string $referenceNo, ?string $notes, bool $withLedger = true): void
    {
        $bill = db_connect()->table('labour_bills lb')
            ->select('lb.*, COALESCE(SUM(lbp.amount),0) as paid_amount', false)
            ->join('labour_bill_payments lbp', 'lbp.labour_bill_id = lb.id', 'left')
            ->where('lb.id', $billId)
            ->groupBy('lb.id')
            ->get()
            ->getRowArray();
        if (! $bill) {
            return;
        }

        $totalAmount = (float) ($bill['total_amount'] ?? 0);
        $newPaid = round((float) ($bill['paid_amount'] ?? 0) + $amount, 2);
        $status = $totalAmount <= 0 || $newPaid >= $totalAmount ? 'Paid' : ($newPaid > 0 ? 'Partial' : 'Pending');

        $this->labourBillPaymentModel->insert([
            'labour_bill_id' => $billId,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'reference_no' => $referenceNo,
            'notes' => $notes,
            'created_by' => (int) session('admin_id'),
        ]);
        $this->labourBillModel->update($billId, ['payment_status' => $status]);

        if ($withLedger && db_connect()->tableExists('karigar_payment_ledgers')) {
            db_connect()->table('karigar_payment_ledgers')->insert([
                'karigar_id' => (int) ($bill['karigar_id'] ?? 0),
                'order_id' => isset($bill['order_id']) ? (int) $bill['order_id'] : null,
                'entry_type' => 'payment',
                'amount' => $amount,
                'reference_no' => $referenceNo,
                'notes' => $notes ?: 'Labour Bill Payment ' . (string) ($bill['bill_no'] ?? ''),
                'created_by' => (int) session('admin_id'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function postPurchaseBillPayment(string $sourceType, int $sourceId, float $amount, string $paymentDate, ?string $referenceNo, ?string $notes, bool $updateLegacyStatus = true): void
    {
        $totals = $this->resolvePurchaseBillTotals($sourceType, $sourceId);
        if (! $totals['found']) {
            return;
        }

        $this->purchaseBillPaymentModel->insert([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'reference_no' => $referenceNo,
            'notes' => $notes,
            'created_by' => (int) session('admin_id'),
        ]);

        $db = db_connect();
        if ($updateLegacyStatus && $sourceType === 'stone' && $db->tableExists('purchases') && $db->fieldExists('payment_status', 'purchases')) {
            $newPaid = round((float) $totals['paid_amount'] + $amount, 2);
            $status = $newPaid >= (float) $totals['total_amount'] ? 'Paid' : ($newPaid > 0 ? 'Partial' : 'Pending');
            $db->table('purchases')->where('id', $sourceId)->update(['payment_status' => $status]);
        } elseif ($sourceType === 'diamond' && $db->tableExists('purchase_headers')) {
            $newPaid = round((float) $totals['paid_amount'] + $amount, 2);
            $status = $newPaid >= (float) $totals['total_amount'] ? 'Paid' : ($newPaid > 0 ? 'Partial' : 'Pending');
            $header = $db->table('purchase_headers')->select('production_document_id')->where('id', $sourceId)->get()->getRowArray();
            $db->table('purchase_headers')->where('id', $sourceId)->update([
                'paid_amount' => $newPaid,
                'payment_status' => $status,
                'payment_date' => $paymentDate,
            ]);
            if ((int) ($header['production_document_id'] ?? 0) > 0 && $db->tableExists('production_purchase_documents')) {
                $db->table('production_purchase_documents')->where('id', (int) $header['production_document_id'])->update([
                    'paid_amount' => $newPaid,
                    'payment_status' => $status,
                    'payment_date' => $paymentDate,
                    'reconciliation_status' => 'Payment updated in application',
                ]);
            }
        } elseif ($sourceType === 'gold' && $db->tableExists('gold_inventory_purchase_headers')) {
            $newPaid = round((float) $totals['paid_amount'] + $amount, 2);
            $status = $newPaid >= (float) $totals['total_amount'] ? 'Paid' : ($newPaid > 0 ? 'Partial' : 'Pending');
            $header = $db->table('gold_inventory_purchase_headers')->select('production_document_id')->where('id', $sourceId)->get()->getRowArray();
            $db->table('gold_inventory_purchase_headers')->where('id', $sourceId)->update([
                'paid_amount' => $newPaid,
                'payment_status' => $status,
                'payment_date' => $paymentDate,
            ]);
            if ((int) ($header['production_document_id'] ?? 0) > 0 && $db->tableExists('production_purchase_documents')) {
                $db->table('production_purchase_documents')->where('id', (int) $header['production_document_id'])->update([
                    'paid_amount' => $newPaid,
                    'payment_status' => $status,
                    'payment_date' => $paymentDate,
                    'reconciliation_status' => 'Payment updated in application',
                ]);
            }
        } elseif ($sourceType === 'production_document' && $db->tableExists('production_purchase_documents')) {
            $newPaid = round((float) $totals['paid_amount'] + $amount, 2);
            $status = $newPaid >= (float) $totals['total_amount'] ? 'Paid' : ($newPaid > 0 ? 'Partial' : 'Pending');
            $db->table('production_purchase_documents')->where('id', $sourceId)->update([
                'paid_amount' => $newPaid,
                'payment_status' => $status,
                'payment_date' => $paymentDate,
                'reconciliation_status' => 'Payment updated in application',
            ]);
        }
    }

    /**
     * @return array{ok:bool,message:string,file_path:?string,file_name:?string}
     */
    private function storePaymentReferenceUpload(): array
    {
        $file = $this->request->getFile('reference_file');
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'message' => '', 'file_path' => null, 'file_name' => null];
        }
        if (! $file->isValid()) {
            return ['ok' => false, 'message' => 'Payment reference upload failed.', 'file_path' => null, 'file_name' => null];
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'Payment reference file must be 5 MB or smaller.', 'file_path' => null, 'file_name' => null];
        }

        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower($file->getClientExtension());
        if (! in_array($extension, $allowed, true)) {
            return ['ok' => false, 'message' => 'Upload PDF, JPG, PNG, or WEBP payment reference only.', 'file_path' => null, 'file_name' => null];
        }

        $dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'account_payments';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $newName = $file->getRandomName();
        $file->move($dir, $newName);

        return [
            'ok' => true,
            'message' => '',
            'file_path' => 'uploads/account_payments/' . $newName,
            'file_name' => $file->getClientName(),
        ];
    }

    private function generateAccountPaymentNumber(): string
    {
        return 'PAY-' . date('Ymd') . '-' . str_pad((string) ($this->accountPaymentModel->countAllResults() + 1), 5, '0', STR_PAD_LEFT);
    }

    private function generateJournalVoucherNumber(): string
    {
        return 'JV-' . date('Ymd') . '-' . str_pad((string) ($this->accountJournalVoucherModel->countAllResults() + 1), 5, '0', STR_PAD_LEFT);
    }

    private function partyTypeLabel(string $type): string
    {
        return [
            'vendor' => 'Vendor',
            'karigar' => 'Karigar',
            'customer' => 'Customer',
            'expense' => 'Expense',
        ][$type] ?? ucfirst($type);
    }

    private function partyName(string $type, int $id): string
    {
        if ($id <= 0) {
            return $type === 'expense' ? 'Expense' : '-';
        }

        $table = [
            'customer' => 'customers',
            'vendor' => 'vendors',
            'karigar' => 'karigars',
        ][$type] ?? null;
        if ($table === null || ! db_connect()->tableExists($table)) {
            return '-';
        }

        $field = $type === 'karigar' ? 'name' : 'name';
        $row = db_connect()->table($table)->select($field)->where('id', $id)->get()->getRowArray();
        return (string) (($row[$field] ?? '') ?: '-');
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,float|int>
     */
    private function summarizePartyRows(array $rows): array
    {
        return [
            'party_count' => count($rows),
            'bill_count' => array_sum(array_column($rows, 'bill_count')),
            'amount' => array_sum(array_column($rows, 'amount')),
            'paid' => array_sum(array_column($rows, 'paid')),
            'pending' => array_sum(array_column($rows, 'pending')),
        ];
    }

    private function dateInRange(string $date, string $dateFrom, string $dateTo): bool
    {
        if ($date === '') {
            return false;
        }
        if ($dateFrom !== '' && $date < $dateFrom) {
            return false;
        }
        if ($dateTo !== '' && $date > $dateTo) {
            return false;
        }
        return true;
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
