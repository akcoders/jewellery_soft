<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\KarigarDocumentModel;
use App\Models\KarigarModel;
use App\Models\KarigarPaymentLedgerModel;
use App\Models\OrderModel;
use App\Services\PostingService;

class KarigarController extends BaseController
{
    private KarigarModel $karigarModel;
    private KarigarDocumentModel $documentModel;
    private KarigarPaymentLedgerModel $paymentLedgerModel;
    private OrderModel $orderModel;
    private PostingService $postingService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->karigarModel = new KarigarModel();
        $this->documentModel = new KarigarDocumentModel();
        $this->paymentLedgerModel = new KarigarPaymentLedgerModel();
        $this->orderModel = new OrderModel();
        $this->postingService = new PostingService();
    }

    public function index(): string
    {
        $rows = $this->karigarModel
            ->select('karigars.*, COUNT(karigar_documents.id) as document_count')
            ->join('karigar_documents', 'karigar_documents.karigar_id = karigars.id', 'left')
            ->groupBy('karigars.id')
            ->orderBy('karigars.id', 'DESC')
            ->findAll();

        return view('admin/karigars/index', [
            'title' => 'Karigars',
            'karigars' => $rows,
        ]);
    }

    public function create(): string
    {
        return view('admin/karigars/create', [
            'title' => 'Add Karigar',
            'docTypes' => $this->documentTypes(),
        ]);
    }

    public function store()
    {
        $rules = $this->validationRules();

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $karigarId = $this->karigarModel->insert([
            'name'            => trim((string) $this->request->getPost('name')),
            'phone'           => $this->nullableString($this->request->getPost('phone')),
            'email'           => $this->nullableString($this->request->getPost('email')),
            'address'         => $this->nullableString($this->request->getPost('address')),
            'city'            => $this->nullableString($this->request->getPost('city')),
            'state'           => $this->nullableString($this->request->getPost('state')),
            'pincode'         => $this->nullableString($this->request->getPost('pincode')),
            'aadhaar_no'      => $this->nullableString($this->request->getPost('aadhaar_no')),
            'pan_no'          => $this->nullableString($this->request->getPost('pan_no')),
            'joining_date'    => $this->nullableDate((string) $this->request->getPost('joining_date')),
            'bank_name'       => $this->nullableString($this->request->getPost('bank_name')),
            'bank_account_no' => $this->nullableString($this->request->getPost('bank_account_no')),
            'ifsc_code'       => $this->nullableString($this->request->getPost('ifsc_code')),
            'department'      => $this->nullableString($this->request->getPost('department')),
            'skills_text'     => $this->nullableString($this->request->getPost('skills_text')),
            'rate_per_gm'     => (float) $this->request->getPost('rate_per_gm'),
            'wastage_percentage' => (float) $this->request->getPost('wastage_percentage'),
            'notes'           => $this->nullableString($this->request->getPost('notes')),
            'is_active'       => (int) ($this->request->getPost('is_active') ?? 1),
        ], true);

        $this->storeDocuments((int) $karigarId);

        $db->transComplete();

        return redirect()->to(site_url('admin/karigars/' . $karigarId))->with('success', 'Karigar created successfully.');
    }

    public function edit(int $id): string
    {
        $karigar = $this->karigarModel->find($id);
        if (! $karigar) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Karigar not found.');
        }

        $docs = $this->documentModel
            ->where('karigar_id', $id)
            ->orderBy('id', 'DESC')
            ->findAll();

        return view('admin/karigars/edit', [
            'title' => 'Edit Karigar',
            'karigar' => $karigar,
            'documents' => $docs,
            'docTypes' => $this->documentTypes(),
        ]);
    }

    public function update(int $id)
    {
        $karigar = $this->karigarModel->find($id);
        if (! $karigar) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Karigar not found.');
        }

        $rules = $this->validationRules();
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $isActive = (int) ($this->request->getPost('is_active') ?? 1);
        if ($isActive === 0 && ! $this->canDeactivate($id)) {
            return redirect()->back()->withInput()->with('error', $this->deactivateBlockedMessage($id));
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $this->karigarModel->update($id, [
            'name'            => trim((string) $this->request->getPost('name')),
            'phone'           => $this->nullableString($this->request->getPost('phone')),
            'email'           => $this->nullableString($this->request->getPost('email')),
            'address'         => $this->nullableString($this->request->getPost('address')),
            'city'            => $this->nullableString($this->request->getPost('city')),
            'state'           => $this->nullableString($this->request->getPost('state')),
            'pincode'         => $this->nullableString($this->request->getPost('pincode')),
            'aadhaar_no'      => $this->nullableString($this->request->getPost('aadhaar_no')),
            'pan_no'          => $this->nullableString($this->request->getPost('pan_no')),
            'joining_date'    => $this->nullableDate((string) $this->request->getPost('joining_date')),
            'bank_name'       => $this->nullableString($this->request->getPost('bank_name')),
            'bank_account_no' => $this->nullableString($this->request->getPost('bank_account_no')),
            'ifsc_code'       => $this->nullableString($this->request->getPost('ifsc_code')),
            'department'      => $this->nullableString($this->request->getPost('department')),
            'skills_text'     => $this->nullableString($this->request->getPost('skills_text')),
            'rate_per_gm'     => (float) $this->request->getPost('rate_per_gm'),
            'wastage_percentage' => (float) $this->request->getPost('wastage_percentage'),
            'notes'           => $this->nullableString($this->request->getPost('notes')),
            'is_active'       => $isActive,
        ]);

        $this->storeDocuments($id);

        $db->transComplete();

        return redirect()->to(site_url('admin/karigars/' . $id))->with('success', 'Karigar updated successfully.');
    }

    public function updateStatus(int $id)
    {
        $karigar = $this->karigarModel->find($id);
        if (! $karigar) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Karigar not found.');
        }

        $isActive = (int) ($this->request->getPost('is_active') ?? -1);
        if (! in_array($isActive, [0, 1], true)) {
            return redirect()->back()->with('error', 'Invalid status value.');
        }

        if ($isActive === 0 && ! $this->canDeactivate($id)) {
            return redirect()->back()->with('error', $this->deactivateBlockedMessage($id));
        }

        $this->karigarModel->update($id, ['is_active' => $isActive]);

        return redirect()->back()->with('success', $isActive === 1 ? 'Karigar activated successfully.' : 'Karigar deactivated successfully.');
    }

    public function show(int $id): string
    {
        $karigar = $this->karigarModel->find($id);
        if (! $karigar) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Karigar not found.');
        }

        $docs = $this->documentModel
            ->where('karigar_id', $id)
            ->orderBy('id', 'DESC')
            ->findAll();

        $db = db_connect();
        $today = date('Y-m-d');
        $pendingStatuses = ['Confirmed', 'In Production', 'QC', 'Ready', 'Packed'];

        $assignedOrders = $db->table('orders')
            ->select('orders.id, orders.order_no, orders.status, orders.priority, orders.due_date, orders.created_at, customers.name as customer_name')
            ->join('customers', 'customers.id = orders.customer_id', 'left')
            ->where('orders.assigned_karigar_id', $id)
            ->orderBy('orders.id', 'DESC')
            ->get()
            ->getResultArray();

        $orderStats = $this->buildOrderStats($assignedOrders, $pendingStatuses, $today);

        $karigarAccountId = $this->postingService->ensureAccount(
            'KARIGAR',
            'KARIGAR-' . $id,
            'Karigar - ' . (string) ($karigar['name'] ?? ('#' . $id)),
            'karigars',
            $id
        );

        $materialRows = $this->fetchKarigarMaterialRows($karigarAccountId);
        $goldLedgers = [];
        $diamondLedgers = [];
        $stoneLedgers = [];
        foreach ($materialRows as $row) {
            $itemType = strtoupper((string) ($row['item_type'] ?? ''));
            if ($itemType === 'GOLD') {
                $goldLedgers[] = $row;
                continue;
            }
            if ($itemType === 'STONE') {
                $stoneLedgers[] = $row;
                continue;
            }
            if ($itemType === 'DIAMOND' || $itemType === 'DIAMOND_BAG') {
                $diamondLedgers[] = $row;
            }
        }
        $materialMovements = $this->buildMovementRowsFromLedgers($goldLedgers, $diamondLedgers);

        $paymentLedgerEnabled = $db->tableExists('karigar_payment_ledgers');
        $paymentLedgers = [];
        if ($paymentLedgerEnabled) {
            $paymentLedgers = $db->table('karigar_payment_ledgers')
                ->select('karigar_payment_ledgers.*, orders.order_no')
                ->join('orders', 'orders.id = karigar_payment_ledgers.order_id', 'left')
                ->where('karigar_payment_ledgers.karigar_id', $id)
                ->orderBy('karigar_payment_ledgers.id', 'DESC')
                ->get()
                ->getResultArray();
        }

        $goldStatement = $this->buildGoldLedgerStatement($goldLedgers);
        $diamondStatement = $this->buildQtyLedgerStatement($diamondLedgers, 'weight_cts');
        $stoneStatement = $this->buildQtyLedgerStatement($stoneLedgers, 'weight_cts');
        $paymentStatement = $this->buildPaymentLedgerStatement($paymentLedgers);

        $allActivity = array_merge(
            array_column($materialRows, 'created_at'),
            array_column($paymentLedgers, 'created_at')
        );
        $lastActivity = '-';
        if ($allActivity !== []) {
            $allActivity = array_values(array_filter(array_map(static fn($v): string => (string) $v, $allActivity), static fn($v): bool => trim($v) !== ''));
            if ($allActivity !== []) {
                rsort($allActivity);
                $lastActivity = (string) $allActivity[0];
            }
        }

        $ledgerEntryCount = count($materialRows) + count($paymentLedgers);

        return view('admin/karigars/show', [
            'title' => 'Karigar Profile',
            'karigar' => $karigar,
            'documents' => $docs,
            'assignedOrders' => $assignedOrders,
            'orderStats' => $orderStats,
            'materialMovements' => $materialMovements,
            'movementSummary' => $this->buildMovementSummary($materialMovements),
            'goldLedgers' => $goldLedgers,
            'goldSummary' => $this->buildGoldSummary($goldLedgers),
            'diamondLedgers' => $diamondLedgers,
            'diamondSummary' => $this->buildQtyWeightSummary($diamondLedgers, 'weight_cts'),
            'stoneLedgers' => $stoneLedgers,
            'stoneSummary' => $this->buildQtyWeightSummary($stoneLedgers, 'weight_cts'),
            'paymentLedgers' => $paymentLedgers,
            'paymentSummary' => $this->buildPaymentSummary($paymentLedgers),
            'paymentLedgerEnabled' => $paymentLedgerEnabled,
            'goldStatement' => $goldStatement,
            'diamondStatement' => $diamondStatement,
            'stoneStatement' => $stoneStatement,
            'paymentStatement' => $paymentStatement,
            'profileStats' => [
                'documents' => count($docs),
                'ledger_entries' => $ledgerEntryCount,
                'overdue_orders' => (int) ($orderStats['overdue_orders'] ?? 0),
                'last_activity' => $lastActivity,
            ],
        ]);
    }

    public function addPaymentEntry(int $id)
    {
        $karigar = $this->karigarModel->find($id);
        if (! $karigar) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Karigar not found.');
        }

        $db = db_connect();
        if (! $db->tableExists('karigar_payment_ledgers')) {
            return redirect()->back()->with('error', 'Payment ledger table not available. Run migration first.');
        }

        $rules = [
            'entry_type'   => 'required|in_list[charge,payment]',
            'amount'       => 'required|decimal|greater_than[0]',
            'order_id'     => 'permit_empty|integer',
            'reference_no' => 'permit_empty|max_length[80]',
            'notes'        => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $orderId = $this->nullableInt($this->request->getPost('order_id'));
        if ($orderId !== null) {
            $order = $this->orderModel->find($orderId);
            if (! $order) {
                return redirect()->back()->withInput()->with('error', 'Selected order not found.');
            }
            if ((int) ($order['assigned_karigar_id'] ?? 0) !== $id) {
                return redirect()->back()->withInput()->with('error', 'Selected order is not assigned to this karigar.');
            }
        }

        $this->paymentLedgerModel->insert([
            'karigar_id'   => $id,
            'order_id'     => $orderId,
            'entry_type'   => trim((string) $this->request->getPost('entry_type')),
            'amount'       => (float) $this->request->getPost('amount'),
            'reference_no' => $this->nullableString($this->request->getPost('reference_no')),
            'notes'        => $this->nullableString($this->request->getPost('notes')),
            'created_by'   => (int) session('admin_id'),
        ]);

        return redirect()->to(site_url('admin/karigars/' . $id))->with('success', 'Payment ledger entry added successfully.');
    }

    private function storeDocuments(int $karigarId): void
    {
        $files = $this->request->getFileMultiple('doc_files');
        if (! is_array($files)) {
            return;
        }

        $types = (array) $this->request->getPost('doc_types');
        $remarks = (array) $this->request->getPost('doc_remarks');

        $uploadDir = FCPATH . 'uploads/karigars';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        foreach ($files as $idx => $file) {
            if (! $file || ! $file->isValid() || $file->hasMoved()) {
                continue;
            }

            $docType = trim((string) ($types[$idx] ?? 'Other'));
            if ($docType === '') {
                $docType = 'Other';
            }

            $newName = $file->getRandomName();
            $file->move($uploadDir, $newName);

            $this->documentModel->insert([
                'karigar_id'    => $karigarId,
                'document_type' => $docType,
                'file_name'     => $file->getClientName(),
                'file_path'     => 'uploads/karigars/' . $newName,
                'remarks'       => trim((string) ($remarks[$idx] ?? '')),
                'uploaded_by'   => (int) session('admin_id'),
            ]);
        }
    }

    private function nullableString($value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    private function nullableDate(string $value): ?string
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }
        return date('Y-m-d', strtotime($v));
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }

    private function validationRules(): array
    {
        return [
            'name'            => 'required|max_length[150]',
            'phone'           => 'permit_empty|max_length[20]',
            'email'           => 'permit_empty|valid_email|max_length[120]',
            'department'      => 'permit_empty|max_length[100]',
            'rate_per_gm'     => 'required|decimal|greater_than_equal_to[0]',
            'wastage_percentage' => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
            'joining_date'    => 'permit_empty|valid_date',
            'city'            => 'permit_empty|max_length[80]',
            'state'           => 'permit_empty|max_length[80]',
            'pincode'         => 'permit_empty|max_length[12]',
            'aadhaar_no'      => 'permit_empty|max_length[20]',
            'pan_no'          => 'permit_empty|max_length[20]',
            'bank_name'       => 'permit_empty|max_length[120]',
            'bank_account_no' => 'permit_empty|max_length[40]',
            'ifsc_code'       => 'permit_empty|max_length[20]',
            'skills_text'     => 'permit_empty',
            'notes'           => 'permit_empty',
            'is_active'       => 'permit_empty|in_list[0,1]',
        ];
    }

    private function documentTypes(): array
    {
        return [
            'Aadhaar',
            'PAN',
            'Bank Passbook',
            'Agreement',
            'Photo',
            'Other',
        ];
    }

    private function canDeactivate(int $karigarId): bool
    {
        $snapshot = $this->karigarLedgerSnapshot($karigarId);

        if ($snapshot['total_transactions'] > 0) {
            return false;
        }

        $epsilon = 0.0005;
        if (abs($snapshot['gold_balance_gm']) > $epsilon) {
            return false;
        }
        if (abs($snapshot['diamond_balance_cts']) > $epsilon || abs($snapshot['diamond_balance_pcs']) > 0.5) {
            return false;
        }
        if (abs($snapshot['stone_balance_cts']) > $epsilon || abs($snapshot['stone_balance_pcs']) > 0.5) {
            return false;
        }
        if (abs($snapshot['payment_balance']) > 0.005) {
            return false;
        }

        return true;
    }

    private function deactivateBlockedMessage(int $karigarId): string
    {
        $s = $this->karigarLedgerSnapshot($karigarId);

        return sprintf(
            'Karigar cannot be deactivated. Transactions: %d | Gold balance: %s gm | Diamond balance: %s cts / %d pcs | Stone balance: %s cts / %d pcs | Payment balance: %s. Clear all accounts first.',
            $s['total_transactions'],
            number_format($s['gold_balance_gm'], 3),
            number_format($s['diamond_balance_cts'], 3),
            (int) round($s['diamond_balance_pcs']),
            number_format($s['stone_balance_cts'], 3),
            (int) round($s['stone_balance_pcs']),
            number_format($s['payment_balance'], 2)
        );
    }

    /**
     * @return array<string, float|int>
     */
    private function karigarLedgerSnapshot(int $karigarId): array
    {
        $db = db_connect();
        $karigar = $this->karigarModel->find($karigarId);
        $accountId = $this->postingService->ensureAccount(
            'KARIGAR',
            'KARIGAR-' . $karigarId,
            'Karigar - ' . (string) ($karigar['name'] ?? ('#' . $karigarId)),
            'karigars',
            $karigarId
        );

        $materialCount = $db->table('ledger_entries')
            ->groupStart()
                ->where('debit_account_id', $accountId)
                ->orWhere('credit_account_id', $accountId)
            ->groupEnd()
            ->whereIn('item_type', ['GOLD', 'DIAMOND', 'DIAMOND_BAG', 'STONE'])
            ->countAllResults();

        $goldRow = $db->table('account_balances')
            ->select('SUM(qty_weight) as balance_weight')
            ->where('account_id', $accountId)
            ->where('item_type', 'GOLD')
            ->get()
            ->getRowArray();
        $goldBalance = round((float) ($goldRow['balance_weight'] ?? 0), 3);

        $diamondRow = $db->table('account_balances')
            ->select('SUM(qty_pcs) as balance_pcs, SUM(qty_cts) as balance_cts')
            ->where('account_id', $accountId)
            ->whereIn('item_type', ['DIAMOND', 'DIAMOND_BAG'])
            ->get()
            ->getRowArray();
        $diamondBalancePcs = round((float) ($diamondRow['balance_pcs'] ?? 0), 3);
        $diamondBalanceCts = round((float) ($diamondRow['balance_cts'] ?? 0), 3);

        $stoneRow = $db->table('account_balances')
            ->select('SUM(qty_pcs) as balance_pcs, SUM(qty_cts) as balance_cts')
            ->where('account_id', $accountId)
            ->where('item_type', 'STONE')
            ->get()
            ->getRowArray();
        $stoneBalancePcs = round((float) ($stoneRow['balance_pcs'] ?? 0), 3);
        $stoneBalanceCts = round((float) ($stoneRow['balance_cts'] ?? 0), 3);

        $paymentCount = 0;
        $paymentCharge = 0.0;
        $paymentPaid = 0.0;
        if ($db->tableExists('karigar_payment_ledgers')) {
            $paymentCount = $db->table('karigar_payment_ledgers')->where('karigar_id', $karigarId)->countAllResults();
            $paymentRow = $db->table('karigar_payment_ledgers')
                ->select(
                    "SUM(CASE WHEN entry_type = 'charge' THEN amount ELSE 0 END) as total_charge,
                     SUM(CASE WHEN entry_type = 'payment' THEN amount ELSE 0 END) as total_paid",
                    false
                )
                ->where('karigar_id', $karigarId)
                ->get()
                ->getRowArray();
            $paymentCharge = (float) ($paymentRow['total_charge'] ?? 0);
            $paymentPaid = (float) ($paymentRow['total_paid'] ?? 0);
        }

        return [
            'total_transactions' => (int) $materialCount + (int) $paymentCount,
            'gold_balance_gm' => $goldBalance,
            'diamond_balance_pcs' => $diamondBalancePcs,
            'diamond_balance_cts' => $diamondBalanceCts,
            'stone_balance_pcs' => $stoneBalancePcs,
            'stone_balance_cts' => $stoneBalanceCts,
            'payment_balance' => round($paymentCharge - $paymentPaid, 2),
        ];
    }

    /**
     * @param list<array<string, mixed>> $orders
     * @param list<string> $pendingStatuses
     * @return array<string, int>
     */
    private function buildOrderStats(array $orders, array $pendingStatuses, string $today): array
    {
        $stats = [
            'total_orders' => count($orders),
            'pending_orders' => 0,
            'overdue_orders' => 0,
            'dispatched_orders' => 0,
            'cancelled_orders' => 0,
        ];

        foreach ($orders as $order) {
            $status = (string) ($order['status'] ?? '');
            if (in_array($status, $pendingStatuses, true)) {
                $stats['pending_orders']++;
                $dueDate = trim((string) ($order['due_date'] ?? ''));
                if ($dueDate !== '' && $dueDate < $today) {
                    $stats['overdue_orders']++;
                }
            }
            if ($status === 'Dispatched') {
                $stats['dispatched_orders']++;
            }
            if ($status === 'Cancelled') {
                $stats['cancelled_orders']++;
            }
        }

        return $stats;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchKarigarMaterialRows(int $karigarAccountId): array
    {
        if ($karigarAccountId <= 0) {
            return [];
        }

        $db = db_connect();
        $list = $db->table('ledger_entries le')
            ->select(
                'le.id as ledger_id, le.debit_account_id, le.credit_account_id, le.item_type, le.qty_pcs, le.qty_cts, le.qty_weight, le.fine_gold_qty,
                 v.id as voucher_id, v.voucher_no, v.voucher_type, v.voucher_date, v.voucher_datetime, v.remarks as voucher_remarks,
                 vl.remarks as line_remarks, vl.bag_id, vl.gold_purity_id,
                 o.order_no, wf.name as from_warehouse_name, wt.name as to_warehouse_name,
                 gp.purity_code, gp.color_name, dbag.bag_no'
            )
            ->join('vouchers v', 'v.id = le.voucher_id', 'inner')
            ->join('voucher_lines vl', 'vl.voucher_id = le.voucher_id AND vl.line_no = le.line_no', 'left')
            ->join('orders o', 'o.id = v.order_id', 'left')
            ->join('warehouses wf', 'wf.id = v.from_warehouse_id', 'left')
            ->join('warehouses wt', 'wt.id = v.to_warehouse_id', 'left')
            ->join('gold_purities gp', 'gp.id = vl.gold_purity_id', 'left')
            ->join('diamond_bags dbag', 'dbag.id = vl.bag_id', 'left')
            ->whereIn('le.item_type', ['GOLD', 'DIAMOND', 'DIAMOND_BAG', 'STONE'])
            ->groupStart()
                ->where('le.debit_account_id', $karigarAccountId)
                ->orWhere('le.credit_account_id', $karigarAccountId)
            ->groupEnd()
            ->orderBy('v.voucher_date', 'DESC')
            ->orderBy('le.id', 'DESC')
            ->get()
            ->getResultArray();

        $rows = [];
        foreach ($list as $row) {
            $isDebit = (int) ($row['debit_account_id'] ?? 0) === $karigarAccountId;
            $entryType = $isDebit ? 'issue' : 'receive';
            $locationName = $entryType === 'issue'
                ? trim((string) ($row['from_warehouse_name'] ?? ''))
                : trim((string) ($row['to_warehouse_name'] ?? ''));
            if ($locationName === '') {
                $locationName = $entryType === 'issue'
                    ? trim((string) ($row['to_warehouse_name'] ?? ''))
                    : trim((string) ($row['from_warehouse_name'] ?? ''));
            }

            $weightCts = (float) ($row['qty_cts'] ?? 0);
            if ($weightCts <= 0) {
                $weightCts = (float) ($row['qty_weight'] ?? 0);
            }

            $rows[] = [
                'id' => (int) ($row['ledger_id'] ?? 0),
                'item_type' => strtoupper((string) ($row['item_type'] ?? '')),
                'created_at' => (string) (($row['voucher_datetime'] ?? '') ?: ($row['voucher_date'] ?? '')),
                'order_no' => (string) ($row['order_no'] ?? ''),
                'entry_type' => $entryType,
                'location_name' => $locationName === '' ? '-' : $locationName,
                'reference_type' => (string) ($row['voucher_type'] ?? ''),
                'reference_id' => (int) ($row['voucher_id'] ?? 0),
                'voucher_no' => (string) ($row['voucher_no'] ?? ''),
                'bag_no' => (string) ($row['bag_no'] ?? ''),
                'purity_code' => (string) ($row['purity_code'] ?? ''),
                'color_name' => (string) ($row['color_name'] ?? ''),
                'weight_gm' => round((float) ($row['qty_weight'] ?? 0), 3),
                'pure_gold_weight_gm' => round((float) ($row['fine_gold_qty'] ?? 0), 3),
                'pcs' => round((float) ($row['qty_pcs'] ?? 0), 3),
                'weight_cts' => round($weightCts, 3),
                'notes' => (string) (($row['line_remarks'] ?? '') ?: ($row['voucher_remarks'] ?? '')),
            ];

            $last = count($rows) - 1;
            if ((string) $rows[$last]['item_type'] === 'GOLD' && (float) $rows[$last]['pure_gold_weight_gm'] <= 0) {
                $rows[$last]['pure_gold_weight_gm'] = (float) $rows[$last]['weight_gm'];
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $goldRows
     * @param list<array<string, mixed>> $diamondRows
     * @return list<array<string, mixed>>
     */
    private function buildMovementRowsFromLedgers(array $goldRows, array $diamondRows): array
    {
        $rows = [];

        foreach ($goldRows as $row) {
            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'order_no' => (string) ($row['order_no'] ?? ''),
                'movement_type' => (string) ($row['entry_type'] ?? ''),
                'location_name' => (string) ($row['location_name'] ?? ''),
                'purity_code' => (string) ($row['purity_code'] ?? ''),
                'color_name' => (string) ($row['color_name'] ?? ''),
                'gold_gm' => (float) ($row['weight_gm'] ?? 0),
                'diamond_cts' => 0.0,
                'pure_gold_weight_gm' => (float) ($row['pure_gold_weight_gm'] ?? 0),
                'notes' => (string) ($row['notes'] ?? ''),
            ];
        }

        foreach ($diamondRows as $row) {
            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'order_no' => (string) ($row['order_no'] ?? ''),
                'movement_type' => (string) ($row['entry_type'] ?? ''),
                'location_name' => (string) ($row['location_name'] ?? ''),
                'purity_code' => '',
                'color_name' => '',
                'gold_gm' => 0.0,
                'diamond_cts' => (float) ($row['weight_cts'] ?? 0),
                'pure_gold_weight_gm' => 0.0,
                'notes' => (string) ($row['notes'] ?? ''),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $ta = strtotime((string) ($a['created_at'] ?? '1970-01-01 00:00:00'));
            $tb = strtotime((string) ($b['created_at'] ?? '1970-01-01 00:00:00'));
            if ($ta === $tb) {
                return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
            }
            return $tb <=> $ta;
        });

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, float>
     */
    private function buildMovementSummary(array $rows): array
    {
        $issueGold = 0.0;
        $receiveGold = 0.0;
        $issueDiamond = 0.0;
        $receiveDiamond = 0.0;

        foreach ($rows as $row) {
            $type = strtolower(trim((string) ($row['movement_type'] ?? '')));
            if ($type === 'issue') {
                $issueGold += (float) ($row['gold_gm'] ?? 0);
                $issueDiamond += (float) ($row['diamond_cts'] ?? 0);
            } elseif ($type === 'receive') {
                $receiveGold += (float) ($row['gold_gm'] ?? 0);
                $receiveDiamond += (float) ($row['diamond_cts'] ?? 0);
            }
        }

        return [
            'issue_gold' => round($issueGold, 3),
            'receive_gold' => round($receiveGold, 3),
            'balance_gold' => round($issueGold - $receiveGold, 3),
            'issue_diamond' => round($issueDiamond, 3),
            'receive_diamond' => round($receiveDiamond, 3),
            'balance_diamond' => round($issueDiamond - $receiveDiamond, 3),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, float>
     */
    private function buildGoldSummary(array $rows): array
    {
        $issueWeight = 0.0;
        $receiveWeight = 0.0;
        $issuePure = 0.0;
        $receivePure = 0.0;

        foreach ($rows as $row) {
            $type = strtolower(trim((string) ($row['entry_type'] ?? '')));
            if ($type === 'issue') {
                $issueWeight += (float) ($row['weight_gm'] ?? 0);
                $issuePure += (float) ($row['pure_gold_weight_gm'] ?? 0);
            } elseif ($type === 'receive') {
                $receiveWeight += (float) ($row['weight_gm'] ?? 0);
                $receivePure += (float) ($row['pure_gold_weight_gm'] ?? 0);
            }
        }

        return [
            'issue_weight' => round($issueWeight, 3),
            'receive_weight' => round($receiveWeight, 3),
            'balance_weight' => round($issueWeight - $receiveWeight, 3),
            'issue_pure' => round($issuePure, 3),
            'receive_pure' => round($receivePure, 3),
            'balance_pure' => round($issuePure - $receivePure, 3),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, float>
     */
    private function buildQtyWeightSummary(array $rows, string $weightField): array
    {
        $issuePcs = 0.0;
        $receivePcs = 0.0;
        $issueWeight = 0.0;
        $receiveWeight = 0.0;

        foreach ($rows as $row) {
            $type = strtolower(trim((string) ($row['entry_type'] ?? '')));
            if ($type === 'issue') {
                $issuePcs += (float) ($row['pcs'] ?? 0);
                $issueWeight += (float) ($row[$weightField] ?? 0);
            } elseif ($type === 'receive') {
                $receivePcs += (float) ($row['pcs'] ?? 0);
                $receiveWeight += (float) ($row[$weightField] ?? 0);
            }
        }

        return [
            'issue_pcs' => round($issuePcs, 3),
            'receive_pcs' => round($receivePcs, 3),
            'balance_pcs' => round($issuePcs - $receivePcs, 3),
            'issue_weight' => round($issueWeight, 3),
            'receive_weight' => round($receiveWeight, 3),
            'balance_weight' => round($issueWeight - $receiveWeight, 3),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, float>
     */
    private function buildPaymentSummary(array $rows): array
    {
        $charge = 0.0;
        $paid = 0.0;

        foreach ($rows as $row) {
            $type = strtolower(trim((string) ($row['entry_type'] ?? '')));
            if ($type === 'charge') {
                $charge += (float) ($row['amount'] ?? 0);
            } elseif ($type === 'payment') {
                $paid += (float) ($row['amount'] ?? 0);
            }
        }

        return [
            'charge' => round($charge, 2),
            'paid' => round($paid, 2),
            'balance' => round($charge - $paid, 2),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function buildGoldLedgerStatement(array $rows): array
    {
        $ordered = $this->sortRowsAscByTime($rows);
        $running = 0.0;
        $statement = [];

        foreach ($ordered as $row) {
            $type = strtolower(trim((string) ($row['entry_type'] ?? '')));
            $debit = $type === 'issue' ? (float) ($row['weight_gm'] ?? 0) : 0.0;
            $credit = $type === 'receive' ? (float) ($row['weight_gm'] ?? 0) : 0.0;
            $opening = $running;
            $closing = $opening + $debit - $credit;
            $running = $closing;

            $row['opening_gm'] = round($opening, 3);
            $row['debit_gm'] = round($debit, 3);
            $row['credit_gm'] = round($credit, 3);
            $row['closing_gm'] = round($closing, 3);
            $statement[] = $row;
        }

        return array_reverse($statement);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function buildQtyLedgerStatement(array $rows, string $weightField): array
    {
        $ordered = $this->sortRowsAscByTime($rows);
        $runningPcs = 0.0;
        $runningWeight = 0.0;
        $statement = [];

        foreach ($ordered as $row) {
            $type = strtolower(trim((string) ($row['entry_type'] ?? '')));
            $debitPcs = $type === 'issue' ? (float) ($row['pcs'] ?? 0) : 0.0;
            $creditPcs = $type === 'receive' ? (float) ($row['pcs'] ?? 0) : 0.0;
            $debitWeight = $type === 'issue' ? (float) ($row[$weightField] ?? 0) : 0.0;
            $creditWeight = $type === 'receive' ? (float) ($row[$weightField] ?? 0) : 0.0;

            $openingPcs = $runningPcs;
            $openingWeight = $runningWeight;
            $closingPcs = $openingPcs + $debitPcs - $creditPcs;
            $closingWeight = $openingWeight + $debitWeight - $creditWeight;
            $runningPcs = $closingPcs;
            $runningWeight = $closingWeight;

            $row['opening_pcs'] = round($openingPcs, 3);
            $row['debit_pcs'] = round($debitPcs, 3);
            $row['credit_pcs'] = round($creditPcs, 3);
            $row['closing_pcs'] = round($closingPcs, 3);

            $row['opening_weight'] = round($openingWeight, 3);
            $row['debit_weight'] = round($debitWeight, 3);
            $row['credit_weight'] = round($creditWeight, 3);
            $row['closing_weight'] = round($closingWeight, 3);
            $statement[] = $row;
        }

        return array_reverse($statement);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function buildPaymentLedgerStatement(array $rows): array
    {
        $ordered = $this->sortRowsAscByTime($rows);
        $running = 0.0;
        $statement = [];

        foreach ($ordered as $row) {
            $type = strtolower(trim((string) ($row['entry_type'] ?? '')));
            $debit = $type === 'charge' ? (float) ($row['amount'] ?? 0) : 0.0;
            $credit = $type === 'payment' ? (float) ($row['amount'] ?? 0) : 0.0;
            $opening = $running;
            $closing = $opening + $debit - $credit;
            $running = $closing;

            $row['opening_amount'] = round($opening, 2);
            $row['debit_amount'] = round($debit, 2);
            $row['credit_amount'] = round($credit, 2);
            $row['closing_amount'] = round($closing, 2);
            $statement[] = $row;
        }

        return array_reverse($statement);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function sortRowsAscByTime(array $rows): array
    {
        usort($rows, static function (array $a, array $b): int {
            $ta = strtotime((string) ($a['created_at'] ?? '1970-01-01 00:00:00'));
            $tb = strtotime((string) ($b['created_at'] ?? '1970-01-01 00:00:00'));
            if ($ta === $tb) {
                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
            return $ta <=> $tb;
        });

        return $rows;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
