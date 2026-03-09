<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\LabourBillModel;
use App\Models\LabourBillPaymentModel;
use App\Models\PurchaseBillPaymentModel;

class AccountsController extends BaseController
{
    private PurchaseBillPaymentModel $purchaseBillPaymentModel;
    private LabourBillModel $labourBillModel;
    private LabourBillPaymentModel $labourBillPaymentModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->purchaseBillPaymentModel = new PurchaseBillPaymentModel();
        $this->labourBillModel = new LabourBillModel();
        $this->labourBillPaymentModel = new LabourBillPaymentModel();
    }

    public function purchaseBills(): string
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
            $stoneBuilder = $db->table('purchases p')
                ->select('p.id, p.purchase_date, p.payment_due_date as due_date, p.invoice_no, p.invoice_amount, p.payment_status as legacy_payment_status, MAX(v.name) as vendor_name, COUNT(pi.id) as qty, COALESCE(SUM(pi.cts), 0) as total_weight', false)
                ->join('purchase_items pi', 'pi.purchase_id = p.id', 'left')
                ->join('vendors v', 'v.id = p.vendor_id', 'left')
                ->where('p.purchase_type', 'Stone')
                ->groupBy('p.id')
                ->orderBy('p.id', 'DESC');

            $stoneRows = $stoneBuilder->get()->getResultArray();
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
            ->select('lb.*, COALESCE(SUM(lbp.amount),0) as paid_amount', false)
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
        return view('admin/accounts/sale_bills', [
            'title' => 'Sale Bills',
        ]);
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

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
