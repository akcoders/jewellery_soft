<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\LabourBillModel;
use App\Services\TaxMasterService;
use App\Services\OrderThumbnailService;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

class LabourBillController extends BaseController
{
    private LabourBillModel $billModel;
    private TaxMasterService $taxService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->billModel = new LabourBillModel();
        $this->taxService = new TaxMasterService();
    }

    public function create(): string
    {
        $karigarId = (int) ($this->request->getGet('karigar_id') ?? 0);

        return view('admin/accounts/labour_bill_create', [
            'title' => 'Add Labour Bill',
            'karigars' => db_connect()->table('karigars')->where('is_active', 1)->orderBy('name')->get()->getResultArray(),
            'jobworks' => $this->availableJobworks(),
            'gstMasters' => $this->taxService->options(),
            'selectedKarigarId' => $karigarId,
        ]);
    }

    public function store()
    {
        $rules = [
            'karigar_id' => 'required|integer',
            'bill_no' => 'required|max_length[40]',
            'bill_date' => 'required|valid_date',
            'jobworks' => 'required',
            'gst_master_id' => 'required|integer',
            'other_amount' => 'permit_empty|decimal',
            'round_off_amount' => 'permit_empty|decimal',
            'due_date' => 'permit_empty|valid_date',
            'notes' => 'permit_empty',
            'attachment' => 'permit_empty|max_size[attachment,10240]|ext_in[attachment,pdf,jpg,jpeg,png,webp]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $db = db_connect();
        $karigarId = (int) $this->request->getPost('karigar_id');
        $karigar = $db->table('karigars')->where('id', $karigarId)->where('is_active', 1)->get()->getRowArray();
        if (! $karigar) {
            return redirect()->back()->withInput()->with('error', 'Selected karigar is not available.');
        }
        if ($this->billModel->where('bill_no', trim((string) $this->request->getPost('bill_no')))->first()) {
            return redirect()->back()->withInput()->with('error', 'This labour bill number already exists.');
        }

        $selectors = array_values(array_unique(array_filter(array_map('strval', (array) $this->request->getPost('jobworks')))));
        $available = [];
        foreach ($this->availableJobworks($karigarId) as $jobwork) {
            $available[(string) $jobwork['selector']] = $jobwork;
        }
        $selected = [];
        foreach ($selectors as $selector) {
            if (isset($available[$selector])) {
                $selected[] = $available[$selector];
            }
        }
        if ($selected === []) {
            return redirect()->back()->withInput()->with('error', 'Select at least one unbilled job work for this karigar.');
        }

        $labour = round(array_sum(array_map(static fn(array $row): float => (float) $row['labour_amount'], $selected)), 2);
        $other = round((float) ($this->request->getPost('other_amount') ?: 0), 2);
        $taxable = round($labour + $other, 2);
        if ($taxable < 0) {
            return redirect()->back()->withInput()->with('error', 'Taxable amount cannot be negative.');
        }
        $roundOff = round((float) ($this->request->getPost('round_off_amount') ?: 0), 2);

        try {
            $tax = $this->taxService->calculate((int) $this->request->getPost('gst_master_id'), $taxable, $roundOff);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $attachment = $this->storeAttachment();
        $db->transStart();
        $billId = (int) $this->billModel->insert([
            'bill_no' => trim((string) $this->request->getPost('bill_no')),
            'bill_date' => (string) $this->request->getPost('bill_date'),
            'order_id' => null,
            'receive_movement_id' => null,
            'karigar_id' => $karigarId,
            'gst_master_id' => (int) $this->request->getPost('gst_master_id'),
            'tax_breakup_json' => (string) $tax['tax_breakup_json'],
            'gold_weight_gm' => round(array_sum(array_map(static fn(array $row): float => (float) $row['net_weight_gm'], $selected)), 3),
            'rate_per_gm' => 0,
            'labour_amount' => $labour,
            'other_amount' => $other,
            'taxable_amount' => $taxable,
            'cgst_rate' => $tax['cgst_rate'],
            'cgst_amount' => $tax['cgst_amount'],
            'sgst_rate' => $tax['sgst_rate'],
            'sgst_amount' => $tax['sgst_amount'],
            'igst_rate' => $tax['igst_rate'],
            'igst_amount' => $tax['igst_amount'],
            'gst_amount' => $tax['gst_amount'],
            'round_off_amount' => $roundOff,
            'total_amount' => $tax['invoice_total'],
            'due_date' => trim((string) $this->request->getPost('due_date')) ?: null,
            'payment_status' => 'Pending',
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'source_type' => 'Manual',
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
            'created_by' => (int) session('admin_id'),
        ], true);

        foreach ($selected as $row) {
            $db->table('labour_bill_jobworks')->insert([
                'labour_bill_id' => $billId,
                'jobwork_type' => $row['jobwork_type'],
                'jobwork_id' => $row['jobwork_id'],
                'order_id' => $row['order_id'] ?: null,
                'receive_movement_id' => $row['receive_movement_id'] ?: null,
                'jobwork_date' => $row['jobwork_date'] ?: null,
                'description' => $row['description'],
                'gross_weight_gm' => $row['gross_weight_gm'],
                'net_weight_gm' => $row['net_weight_gm'],
                'labour_amount' => $row['labour_amount'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Unable to save labour bill.');
        }

        return redirect()->to(site_url('admin/accounts/labour-bills/' . $billId))->with('success', 'Labour bill saved with ' . count($selected) . ' job work(s).');
    }

    public function show(int $id): string
    {
        $bill = $this->bill($id);
        return view('admin/accounts/labour_bill_view', [
            'title' => 'Labour Bill ' . $bill['bill_no'],
            'bill' => $bill,
            'jobworks' => $this->jobworksForBill($id),
            'payments' => db_connect()->table('labour_bill_payments')->where('labour_bill_id', $id)->orderBy('payment_date', 'DESC')->get()->getResultArray(),
        ]);
    }

    public function attachment(int $id)
    {
        $bill = $this->bill($id);
        $relative = trim((string) ($bill['attachment_path'] ?? ''));
        if ($relative === '') {
            throw PageNotFoundException::forPageNotFound('Attachment not found.');
        }
        $path = str_starts_with($relative, 'writable/') ? ROOTPATH . $relative : ROOTPATH . ltrim($relative, '/');
        $real = realpath($path);
        $allowedRoots = [realpath(ROOTPATH . 'app/Database/Data/labour_bills'), realpath(WRITEPATH . 'uploads/labour-bills')];
        $valid = $real !== false && is_file($real) && array_filter($allowedRoots, static fn($root): bool => $root && str_starts_with($real, $root . DIRECTORY_SEPARATOR));
        if (! $valid) {
            throw PageNotFoundException::forPageNotFound('Attachment not found.');
        }

        return $this->response->download($real, null)->setFileName((string) (($bill['attachment_name'] ?? '') ?: basename($real)));
    }

    /** @return list<array<string,mixed>> */
    private function availableJobworks(int $karigarId = 0): array
    {
        $db = db_connect();
        $linked = [];
        if ($db->tableExists('labour_bill_jobworks')) {
            foreach ($db->table('labour_bill_jobworks')->select('jobwork_type, jobwork_id')->get()->getResultArray() as $row) {
                $linked[(string) $row['jobwork_type'] . ':' . (int) $row['jobwork_id']] = true;
            }
        }
        $rows = [];
        $readyOrderIds = [];
        if ($db->tableExists('production_ready_items')) {
            $builder = $db->table('production_ready_items p')
                ->select('p.*, o.order_no, o.order_name')
                ->join('orders o', 'o.id = p.order_id', 'left')
                ->where('p.karigar_id IS NOT NULL', null, false);
            if ($karigarId > 0) {
                $builder->where('p.karigar_id', $karigarId);
            }
            foreach ($builder->orderBy('p.ready_date', 'DESC')->get()->getResultArray() as $row) {
                if ((int) ($row['order_id'] ?? 0) > 0) {
                    $readyOrderIds[(int) $row['order_id']] = true;
                }
                $selector = 'ready_item:' . (int) $row['id'];
                if (isset($linked[$selector])) {
                    continue;
                }
                $rows[] = [
                    'selector' => $selector, 'jobwork_type' => 'ready_item', 'jobwork_id' => (int) $row['id'],
                    'karigar_id' => (int) $row['karigar_id'], 'order_id' => (int) ($row['order_id'] ?? 0), 'receive_movement_id' => 0,
                    'order_no' => (string) ($row['order_no'] ?? ''), 'order_name' => (string) ($row['order_name'] ?? ''),
                    'image_url' => trim((string) ($row['image_path'] ?? '')) !== '' ? site_url('admin/orders/ready-image/' . (int) $row['id']) : '',
                    'jobwork_date' => (string) ($row['ready_date'] ?? ''),
                    'description' => trim((string) (($row['order_no'] ?? '') . ' · ' . (($row['reference_no'] ?? '') ?: ($row['design_name'] ?? 'Ready jewellery'))), ' ·'),
                    'gross_weight_gm' => (float) ($row['gross_weight_gm'] ?? 0), 'net_weight_gm' => (float) ($row['net_weight_gm'] ?? 0),
                    'labour_amount' => (float) ($row['labour_charges'] ?? 0),
                ];
            }
        }
        if ($db->tableExists('order_receive_summaries')) {
            $builder = $db->table('order_receive_summaries s')
                ->select('s.*, o.order_no, o.order_name, o.assigned_karigar_id')
                ->join('orders o', 'o.id = s.order_id')
                ->where('o.assigned_karigar_id IS NOT NULL', null, false);
            if ($karigarId > 0) {
                $builder->where('o.assigned_karigar_id', $karigarId);
            }
            foreach ($builder->orderBy('s.created_at', 'DESC')->get()->getResultArray() as $row) {
                // Imported ready jewellery and its receive summary describe the
                // same job work. Prefer the richer ready-item row so it appears
                // only once in the bill selector.
                if (isset($readyOrderIds[(int) ($row['order_id'] ?? 0)])) {
                    continue;
                }
                $selector = 'order_receive:' . (int) $row['id'];
                if (isset($linked[$selector])) {
                    continue;
                }
                $rows[] = [
                    'selector' => $selector, 'jobwork_type' => 'order_receive', 'jobwork_id' => (int) $row['id'],
                    'karigar_id' => (int) $row['assigned_karigar_id'], 'order_id' => (int) $row['order_id'], 'receive_movement_id' => (int) $row['movement_id'],
                    'order_no' => (string) ($row['order_no'] ?? ''), 'order_name' => (string) ($row['order_name'] ?? ''), 'image_url' => '',
                    'jobwork_date' => substr((string) ($row['created_at'] ?? ''), 0, 10),
                    'description' => (string) (($row['order_no'] ?? '') . ' · Order receiving'),
                    'gross_weight_gm' => (float) ($row['gross_weight_gm'] ?? 0), 'net_weight_gm' => (float) ($row['net_gold_weight_gm'] ?? 0),
                    'labour_amount' => (float) ($row['labour_amount'] ?? 0),
                ];
            }
        }
        $thumbnailMap = (new OrderThumbnailService($db))->map(array_map(static fn(array $row): int => (int) ($row['order_id'] ?? 0), $rows));
        foreach ($rows as &$row) {
            if (($row['image_url'] ?? '') === '') {
                $row['image_url'] = (string) ($thumbnailMap[(int) ($row['order_id'] ?? 0)] ?? '');
            }
        }
        unset($row);
        usort($rows, static fn(array $a, array $b): int => strcmp((string) $b['jobwork_date'], (string) $a['jobwork_date']));
        return $rows;
    }

    /** @return array<string,mixed> */
    private function bill(int $id): array
    {
        $row = db_connect()->table('labour_bills lb')
            ->select('lb.*, k.name as karigar_name, gm.name as gst_master_name, COALESCE(SUM(lbp.amount),0) as paid_amount', false)
            ->join('karigars k', 'k.id = lb.karigar_id', 'left')
            ->join('gst_masters gm', 'gm.id = lb.gst_master_id', 'left')
            ->join('labour_bill_payments lbp', 'lbp.labour_bill_id = lb.id', 'left')
            ->where('lb.id', $id)->groupBy('lb.id')->get()->getRowArray();
        if (! $row) {
            throw PageNotFoundException::forPageNotFound('Labour bill not found.');
        }
        return $row;
    }

    /** @return list<array<string,mixed>> */
    private function jobworksForBill(int $id): array
    {
        return db_connect()->table('labour_bill_jobworks j')
            ->select('j.*, o.order_no, o.order_name')->join('orders o', 'o.id = j.order_id', 'left')
            ->where('j.labour_bill_id', $id)->orderBy('j.jobwork_date')->get()->getResultArray();
    }

    /** @return array{path:?string,name:?string} */
    private function storeAttachment(): array
    {
        $file = $this->request->getFile('attachment');
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return ['path' => null, 'name' => null];
        }
        $dir = WRITEPATH . 'uploads/labour-bills';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = $file->getRandomName();
        $clientName = $file->getClientName();
        $file->move($dir, $name);
        return ['path' => 'writable/uploads/labour-bills/' . $name, 'name' => $clientName];
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
