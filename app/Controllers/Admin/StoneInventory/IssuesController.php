<?php

namespace App\Controllers\Admin\StoneInventory;

use App\Controllers\BaseController;
use App\Models\CompanySettingModel;
use App\Models\InventoryLocationModel;
use App\Models\KarigarModel;
use App\Models\StoneInventoryIssueHeaderModel;
use App\Models\StoneInventoryIssueLineModel;
use App\Models\StoneInventoryItemModel;
use App\Services\StoneInventory\StockService;
use CodeIgniter\HTTP\Files\UploadedFile;
use Throwable;

class IssuesController extends BaseController
{
    private StoneInventoryIssueHeaderModel $headerModel;
    private StoneInventoryIssueLineModel $lineModel;
    private StoneInventoryItemModel $itemModel;
    private InventoryLocationModel $locationModel;
    private KarigarModel $karigarModel;
    private CompanySettingModel $companySettingModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->headerModel = new StoneInventoryIssueHeaderModel();
        $this->lineModel = new StoneInventoryIssueLineModel();
        $this->itemModel = new StoneInventoryItemModel();
        $this->locationModel = new InventoryLocationModel();
        $this->karigarModel = new KarigarModel();
        $this->companySettingModel = new CompanySettingModel();
    }

    public function index(): string
    {
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));

        $builder = db_connect()->table('stone_inventory_issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name, COUNT(il.id) as line_count, COALESCE(SUM(il.pcs), 0) as total_pcs, COALESCE(SUM(il.qty), 0) as total_qty, COALESCE(SUM(il.line_value), 0) as total_value', false)
            ->join('stone_inventory_issue_lines il', 'il.issue_id = ih.id', 'left')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->groupBy('ih.id')
            ->orderBy('ih.id', 'DESC');

        if ($from !== '') {
            $builder->where('ih.issue_date >=', $from);
        }
        if ($to !== '') {
            $builder->where('ih.issue_date <=', $to);
        }

        return view('admin/stone_inventory/issues/index', [
            'title' => 'Stone Issues',
            'issues' => $builder->get()->getResultArray(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(): string
    {
        return view('admin/stone_inventory/issues/create', [
            'title' => 'Create Stone Issue',
            'items' => $this->itemOptions(),
            'orders' => $this->orderOptions(),
            'locations' => $this->locationOptions(),
            'karigars' => $this->karigarOptions(),
            'issue' => null,
            'lines' => [],
            'action' => site_url('admin/stone-inventory/issues'),
            'preselectedOrderId' => (int) ($this->request->getGet('order_id') ?? 0),
        ]);
    }

    public function store()
    {
        $validationError = $this->validateHeader();
        if ($validationError !== null) {
            return redirect()->back()->withInput()->with('error', $validationError);
        }

        $db = db_connect();
        $service = new StockService($db);
        $parsed = $this->collectLinesFromRequest();
        if ($parsed['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $parsed['error']);
        }
        if ($parsed['lines'] === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line is required.');
        }

        try {
            $db->transException(true)->transStart();

            $orderId = (int) $this->request->getPost('order_id');
            $karigarId = (int) $this->request->getPost('karigar_id');
            $locationId = (int) $this->request->getPost('location_id');
            $karigar = $this->karigarModel->find($karigarId);
            $attachment = $this->processAttachment(null, true);
            if ($attachment['error'] !== null) {
                throw new \RuntimeException($attachment['error']);
            }

            $issueId = (int) $this->headerModel->insert([
                'voucher_no' => $this->generateVoucherNo(),
                'issue_date' => (string) $this->request->getPost('issue_date'),
                'order_id' => $orderId,
                'karigar_id' => $karigarId,
                'location_id' => $locationId,
                'issue_to' => (string) ($karigar['name'] ?? ''),
                'purpose' => trim((string) $this->request->getPost('purpose')),
                'notes' => trim((string) $this->request->getPost('notes')) ?: null,
                'attachment_name' => $attachment['name'],
                'attachment_path' => $attachment['path'],
                'created_by' => (int) session('admin_id'),
            ], true);

            foreach ($parsed['lines'] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                if ($itemId <= 0) {
                    $itemId = $service->upsertItemFromSignature((array) ($line['signature'] ?? []));
                }

                $this->lineModel->insert([
                    'issue_id' => $issueId,
                    'item_id' => $itemId,
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
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/stone-inventory/issues'))
            ->with('success', 'Stone issue saved, stock subtracted, and voucher generated.');
    }

    public function view(int $id)
    {
        $issue = db_connect()->table('stone_inventory_issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->where('ih.id', $id)
            ->get()
            ->getRowArray();

        if (! $issue) {
            return redirect()->to(site_url('admin/stone-inventory/issues'))->with('error', 'Issue not found.');
        }

        return view('admin/stone_inventory/issues/view', [
            'title' => 'View Issue',
            'issue' => $issue,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('stone_inventory_issue_lines', 'issue_id', $id),
        ]);
    }

    public function voucher(int $id): string
    {
        $issue = db_connect()->table('stone_inventory_issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name, k.name as labour_name, k.phone as labour_phone, k.email as labour_email, k.address as labour_address, k.city as labour_city, k.state as labour_state, k.pincode as labour_pincode')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->where('ih.id', $id)
            ->get()
            ->getRowArray();
        if (! $issue) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Issue not found.');
        }

        return view('admin/vouchers/issuement', [
            'title' => 'Stone Issuement Voucher',
            'materialType' => 'Stone',
            'issue' => $issue,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('stone_inventory_issue_lines', 'issue_id', $id),
            'company' => $this->companySetting(),
        ]);
    }

    public function edit(int $id)
    {
        $issue = $this->headerModel->find($id);
        if (! $issue) {
            return redirect()->to(site_url('admin/stone-inventory/issues'))->with('error', 'Issue not found.');
        }

        return view('admin/stone_inventory/issues/edit', [
            'title' => 'Edit Stone Issue',
            'items' => $this->itemOptions(),
            'orders' => $this->orderOptions(),
            'locations' => $this->locationOptions(),
            'karigars' => $this->karigarOptions(),
            'issue' => $issue,
            'lines' => $this->lineRows($id),
            'action' => site_url('admin/stone-inventory/issues/' . $id . '/update'),
            'preselectedOrderId' => (int) ($issue['order_id'] ?? 0),
        ]);
    }

    public function update(int $id)
    {
        $issue = $this->headerModel->find($id);
        if (! $issue) {
            return redirect()->to(site_url('admin/stone-inventory/issues'))->with('error', 'Issue not found.');
        }

        $validationError = $this->validateHeader();
        if ($validationError !== null) {
            return redirect()->back()->withInput()->with('error', $validationError);
        }

        $db = db_connect();
        $service = new StockService($db);
        $parsed = $this->collectLinesFromRequest();
        if ($parsed['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $parsed['error']);
        }
        if ($parsed['lines'] === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line is required.');
        }

        try {
            $db->transException(true)->transStart();
            $service->reverseIssue($id);

            $orderId = (int) $this->request->getPost('order_id');
            $karigarId = (int) $this->request->getPost('karigar_id');
            $locationId = (int) $this->request->getPost('location_id');
            $karigar = $this->karigarModel->find($karigarId);
            $attachment = $this->processAttachment((string) ($issue['attachment_path'] ?? ''), ((string) ($issue['attachment_path'] ?? '')) === '');
            if ($attachment['error'] !== null) {
                throw new \RuntimeException($attachment['error']);
            }

            $this->headerModel->update($id, [
                'issue_date' => (string) $this->request->getPost('issue_date'),
                'order_id' => $orderId,
                'karigar_id' => $karigarId,
                'location_id' => $locationId,
                'issue_to' => (string) ($karigar['name'] ?? ''),
                'purpose' => trim((string) $this->request->getPost('purpose')),
                'notes' => trim((string) $this->request->getPost('notes')) ?: null,
                'attachment_name' => $attachment['name'] ?? (string) ($issue['attachment_name'] ?? ''),
                'attachment_path' => $attachment['path'] ?? (string) ($issue['attachment_path'] ?? ''),
            ]);

            $this->lineModel->where('issue_id', $id)->delete();
            foreach ($parsed['lines'] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                if ($itemId <= 0) {
                    $itemId = $service->upsertItemFromSignature((array) ($line['signature'] ?? []));
                }

                $this->lineModel->insert([
                    'issue_id' => $id,
                    'item_id' => $itemId,
                    'pcs' => $line['pcs'],
                    'qty' => $line['qty'],
                    'rate' => $line['rate'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyIssue($id);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/stone-inventory/issues/view/' . $id))
            ->with('success', 'Stone issue updated and voucher refreshed.');
    }

    public function delete(int $id)
    {
        $issue = $this->headerModel->find($id);
        if (! $issue) {
            return redirect()->to(site_url('admin/stone-inventory/issues'))->with('error', 'Issue not found.');
        }

        $db = db_connect();
        $service = new StockService($db);

        try {
            $db->transException(true)->transStart();
            $service->reverseIssue($id);
            $this->lineModel->where('issue_id', $id)->delete();
            $this->deleteFile((string) ($issue['attachment_path'] ?? ''));
            $this->headerModel->delete($id);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->to(site_url('admin/stone-inventory/issues'))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/stone-inventory/issues'))
            ->with('success', 'Issue deleted and stock restored.');
    }

    /**
     * @return array{lines:list<array<string,mixed>>,error:?string}
     */
    private function collectLinesFromRequest(): array
    {
        $itemIds = (array) $this->request->getPost('item_id');
        $productNames = (array) $this->request->getPost('product_name');
        $stoneTypes = (array) $this->request->getPost('stone_type');
        $pcsList = (array) $this->request->getPost('pcs');
        $qtys = (array) $this->request->getPost('qty');
        $rates = (array) $this->request->getPost('rate');

        $max = max(count($itemIds), count($productNames), count($stoneTypes), count($pcsList), count($qtys), count($rates));
        $lines = [];

        for ($i = 0; $i < $max; $i++) {
            $itemId = (int) ($itemIds[$i] ?? 0);
            $productName = trim((string) ($productNames[$i] ?? ''));
            $stoneType = trim((string) ($stoneTypes[$i] ?? ''));
            $pcsValue = (float) ($pcsList[$i] ?? 0);
            $qtyValue = (float) ($qtys[$i] ?? 0);
            $rateRaw = trim((string) ($rates[$i] ?? ''));

            $isBlank = $itemId <= 0 && $productName === '' && $stoneType === '' && $pcsValue <= 0 && $qtyValue <= 0 && $rateRaw === '';
            if ($isBlank) {
                continue;
            }

            if ($pcsValue < 0) {
                return ['lines' => [], 'error' => 'PCS cannot be negative.'];
            }
            if ($qtyValue <= 0) {
                return ['lines' => [], 'error' => 'Quantity must be greater than zero for each line.'];
            }
            $rateValue = $rateRaw === '' ? null : (float) $rateRaw;
            if ($rateValue !== null && $rateValue < 0) {
                return ['lines' => [], 'error' => 'Rate cannot be negative.'];
            }

            if ($itemId <= 0) {
                if ($productName === '') {
                    return ['lines' => [], 'error' => 'Product name is required when item is not selected.'];
                }
                $signature = [
                    'product_name' => $productName,
                    'stone_type' => $stoneType,
                    'default_rate' => $rateValue ?? 0,
                ];
            } else {
                if (! $this->itemModel->find($itemId)) {
                    return ['lines' => [], 'error' => 'Selected item does not exist.'];
                }
                $signature = [];
            }

            $lineValue = $rateValue === null ? null : round($qtyValue * $rateValue, 2);
            $lines[] = [
                'item_id' => $itemId,
                'pcs' => round($pcsValue, 3),
                'qty' => round($qtyValue, 3),
                'rate' => $rateValue === null ? null : round($rateValue, 2),
                'line_value' => $lineValue,
                'signature' => $signature,
            ];
        }

        return ['lines' => $lines, 'error' => null];
    }

    private function validateHeader(): ?string
    {
        if (! $this->validate([
            'issue_date' => 'required|valid_date',
            'order_id' => 'required|integer|greater_than[0]',
            'karigar_id' => 'required|integer|greater_than[0]',
            'location_id' => 'required|integer|greater_than[0]',
            'purpose' => 'required|max_length[50]',
            'notes' => 'permit_empty',
        ])) {
            $errors = $this->validator ? $this->validator->getErrors() : [];
            return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
        }

        $orderId = (int) $this->request->getPost('order_id');
        $karigarId = (int) $this->request->getPost('karigar_id');
        $locationId = (int) $this->request->getPost('location_id');

        $order = db_connect()->table('orders')
            ->select('id, assigned_karigar_id, status')
            ->where('id', $orderId)
            ->whereNotIn('status', ['Cancelled', 'Completed'])
            ->get()
            ->getRowArray();
        if (! $order || (int) ($order['assigned_karigar_id'] ?? 0) <= 0) {
            return 'Only karigar-assigned active orders are allowed for issuance.';
        }
        if ((int) ($order['assigned_karigar_id'] ?? 0) !== $karigarId) {
            return 'Selected karigar does not match order assignment.';
        }
        if ($this->karigarModel->where('id', $karigarId)->where('is_active', 1)->countAllResults() === 0) {
            return 'Selected karigar was not found or inactive.';
        }
        if ($this->locationModel->where('id', $locationId)->where('is_active', 1)->countAllResults() === 0) {
            return 'Selected warehouse was not found.';
        }
        return null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function itemOptions(): array
    {
        return $this->itemModel->orderBy('product_name', 'ASC')->orderBy('id', 'DESC')->findAll();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function locationOptions(): array
    {
        return $this->locationModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function karigarOptions(): array
    {
        return db_connect()->table('karigars k')
            ->select('k.id, k.name, k.phone')
            ->join('orders o', 'o.assigned_karigar_id = k.id', 'inner')
            ->where('k.is_active', 1)
            ->whereNotIn('o.status', ['Cancelled', 'Completed'])
            ->groupBy('k.id')
            ->orderBy('k.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function orderOptions(): array
    {
        $orders = db_connect()->table('orders o')
            ->select('o.id, o.order_no, o.order_type, o.assigned_karigar_id, k.name as karigar_name', false)
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left')
            ->whereNotIn('o.status', ['Cancelled', 'Completed'])
            ->where('o.assigned_karigar_id IS NOT NULL', null, false)
            ->where('o.assigned_karigar_id >', 0)
            ->orderBy('o.id', 'DESC')
            ->limit(500)
            ->get()
            ->getResultArray();

        $issueMap = [];
        $issueRows = db_connect()->table('stone_inventory_issue_headers ih')
            ->select('ih.order_id, COALESCE(SUM(il.qty),0) as issued_qty', false)
            ->join('stone_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
            ->where('ih.order_id IS NOT NULL', null, false)
            ->groupBy('ih.order_id')
            ->get()
            ->getResultArray();
        foreach ($issueRows as $row) {
            $issueMap[(int) $row['order_id']] = (float) ($row['issued_qty'] ?? 0);
        }

        $returnMap = [];
        if (db_connect()->tableExists('stone_inventory_return_headers') && db_connect()->tableExists('stone_inventory_return_lines')) {
            $returnRows = db_connect()->table('stone_inventory_return_headers rh')
                ->select('rh.order_id, COALESCE(SUM(rl.qty),0) as returned_qty', false)
                ->join('stone_inventory_return_lines rl', 'rl.return_id = rh.id', 'inner')
                ->where('rh.order_id IS NOT NULL', null, false)
                ->groupBy('rh.order_id')
                ->get()
                ->getResultArray();
            foreach ($returnRows as $row) {
                $returnMap[(int) $row['order_id']] = (float) ($row['returned_qty'] ?? 0);
            }
        }

        foreach ($orders as &$order) {
            $orderId = (int) ($order['id'] ?? 0);
            $issued = (float) ($issueMap[$orderId] ?? 0);
            $returned = (float) ($returnMap[$orderId] ?? 0);
            $order['issued_qty'] = round($issued, 3);
            $order['returned_qty'] = round($returned, 3);
            $order['pending_qty'] = round($issued - $returned, 3);
            $order['default_purpose'] = 'Jobwork';
        }
        unset($order);

        return $orders;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function lineRows(int $issueId): array
    {
        return db_connect()->table('stone_inventory_issue_lines il')
            ->select('il.*, i.product_name, i.stone_type')
            ->join('stone_inventory_items i', 'i.id = il.item_id', 'left')
            ->where('il.issue_id', $issueId)
            ->orderBy('il.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array{total_pcs:float,total_qty:float,total_value:float}
     */
    private function lineTotals(string $table, string $headerField, int $headerId): array
    {
        $row = db_connect()->table($table)
            ->select('COALESCE(SUM(pcs),0) as total_pcs, COALESCE(SUM(qty),0) as total_qty, COALESCE(SUM(line_value),0) as total_value', false)
            ->where($headerField, $headerId)
            ->get()
            ->getRowArray();

        return [
            'total_pcs' => (float) ($row['total_pcs'] ?? 0),
            'total_qty' => (float) ($row['total_qty'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }

    /**
     * @return array{name:?string,path:?string,error:?string}
     */
    private function processAttachment(?string $existingPath, bool $required): array
    {
        $file = $this->request->getFile('attachment');
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            if ($required) {
                return ['name' => null, 'path' => null, 'error' => 'Attachment is required for issuance.'];
            }
            return ['name' => null, 'path' => $existingPath, 'error' => null];
        }

        if (! $file->isValid()) {
            return ['name' => null, 'path' => null, 'error' => 'Invalid attachment upload.'];
        }
        if ($file->getSizeByUnit('kb') > 10240) {
            return ['name' => null, 'path' => null, 'error' => 'Attachment size must be 10MB or less.'];
        }
        $ext = strtolower((string) $file->getExtension());
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
            return ['name' => null, 'path' => null, 'error' => 'Attachment must be jpg, png, webp, or pdf.'];
        }

        $uploadDir = FCPATH . 'uploads/issuements/stone';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $newName = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        $file->move($uploadDir, $newName);
        $newPath = 'uploads/issuements/stone/' . $newName;

        $this->deleteFile((string) $existingPath);

        return [
            'name' => (string) $file->getClientName(),
            'path' => $newPath,
            'error' => null,
        ];
    }

    private function deleteFile(string $relativePath): void
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '') {
            return;
        }
        $full = FCPATH . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
        if (is_file($full)) {
            @unlink($full);
        }
    }

    private function generateVoucherNo(): string
    {
        $db = db_connect();
        $prefix = strtoupper(trim((string) ($this->companySetting()['issuement_suffix'] ?? 'ISS')));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?? 'ISS';
        if ($prefix === '') {
            $prefix = 'ISS';
        }

        $tables = ['gold_inventory_issue_headers', 'issue_headers', 'stone_inventory_issue_headers'];
        $maxSerial = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';
        foreach ($tables as $table) {
            if (! $db->tableExists($table)) {
                continue;
            }
            $rows = $db->table($table)->select('voucher_no')->like('voucher_no', $prefix, 'after')->get()->getResultArray();
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

    /**
     * @return array<string,mixed>
     */
    private function companySetting(): array
    {
        $row = $this->companySettingModel->orderBy('id', 'ASC')->first();
        return is_array($row) ? $row : [];
    }
}
