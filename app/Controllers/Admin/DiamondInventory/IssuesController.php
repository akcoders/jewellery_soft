<?php

namespace App\Controllers\Admin\DiamondInventory;

use App\Controllers\BaseController;
use App\Models\CompanySettingModel;
use App\Models\InventoryLocationModel;
use App\Models\IssueHeaderModel;
use App\Models\IssueLineModel;
use App\Models\ItemModel;
use App\Models\KarigarModel;
use App\Services\DiamondInventory\StockService;
use CodeIgniter\HTTP\Files\UploadedFile;
use Throwable;

class IssuesController extends BaseController
{
    /** @var IssueHeaderModel */
    private $headerModel;
    /** @var IssueLineModel */
    private $lineModel;
    /** @var ItemModel */
    private $itemModel;
    /** @var InventoryLocationModel */
    private $locationModel;
    /** @var KarigarModel */
    private $karigarModel;
    /** @var CompanySettingModel */
    private $companySettingModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->headerModel = new IssueHeaderModel();
        $this->lineModel = new IssueLineModel();
        $this->itemModel = new ItemModel();
        $this->locationModel = new InventoryLocationModel();
        $this->karigarModel = new KarigarModel();
        $this->companySettingModel = new CompanySettingModel();
    }

    public function index(): string
    {
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));

        $builder = db_connect()->table('issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name, COUNT(il.id) as line_count, COALESCE(SUM(il.carat), 0) as total_carat, COALESCE(SUM(il.line_value), 0) as total_value', false)
            ->join('issue_lines il', 'il.issue_id = ih.id', 'left')
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

        return view('admin/diamond_inventory/issues/index', [
            'title' => 'Diamond Issues',
            'issues' => $builder->get()->getResultArray(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(): string
    {
        return view('admin/diamond_inventory/issues/create', [
            'title' => 'Create Diamond Issue',
            'items' => $this->itemOptions(),
            'orders' => $this->orderOptions(),
            'locations' => $this->locationOptions(),
            'karigars' => $this->karigarOptions(),
            'issue' => null,
            'lines' => [],
            'action' => site_url('admin/diamond-inventory/issues'),
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
                    'carat' => $line['carat'],
                    'rate_per_carat' => $line['rate_per_carat'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyIssue($issueId);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-inventory/issues'))
            ->with('success', 'Diamond issue saved, stock subtracted, and voucher generated.');
    }

    public function view(int $id)
    {
        $issue = db_connect()->table('issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->where('ih.id', $id)
            ->get()
            ->getRowArray();

        if (! $issue) {
            return redirect()->to(site_url('admin/diamond-inventory/issues'))->with('error', 'Issue not found.');
        }

        $lines = $this->lineRows($id);
        $totals = $this->lineTotals('issue_lines', 'issue_id', $id);

        return view('admin/diamond_inventory/issues/view', [
            'title' => 'View Issue',
            'issue' => $issue,
            'lines' => $lines,
            'totals' => $totals,
        ]);
    }

    public function voucher(int $id): string
    {
        $issue = db_connect()->table('issue_headers ih')
            ->select('ih.*, o.order_no, k.name as karigar_name, iloc.name as warehouse_name, k.name as labour_name, k.phone as labour_phone, k.email as labour_email, k.address as labour_address, k.city as labour_city, k.state as labour_state, k.pincode as labour_pincode, k.department as labour_department, k.skills_text as labour_skills, k.rate_per_gm as labour_rate_per_gm, k.wastage_percentage as labour_wastage_percentage, k.aadhaar_no as labour_aadhaar_no, k.pan_no as labour_pan_no, k.joining_date as labour_joining_date, k.bank_name as labour_bank_name, k.bank_account_no as labour_bank_account_no, k.ifsc_code as labour_ifsc_code')
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
            'title' => 'Diamond Issuement Voucher',
            'materialType' => 'Diamond',
            'issue' => $issue,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('issue_lines', 'issue_id', $id),
            'company' => $this->companySetting(),
        ]);
    }

    public function edit(int $id)
    {
        $issue = $this->headerModel->find($id);
        if (! $issue) {
            return redirect()->to(site_url('admin/diamond-inventory/issues'))->with('error', 'Issue not found.');
        }

        return view('admin/diamond_inventory/issues/edit', [
            'title' => 'Edit Diamond Issue',
            'items' => $this->itemOptions(),
            'orders' => $this->orderOptions(),
            'locations' => $this->locationOptions(),
            'karigars' => $this->karigarOptions(),
            'issue' => $issue,
            'lines' => $this->lineRows($id),
            'action' => site_url('admin/diamond-inventory/issues/' . $id . '/update'),
            'preselectedOrderId' => (int) ($issue['order_id'] ?? 0),
        ]);
    }

    public function update(int $id)
    {
        $issue = $this->headerModel->find($id);
        if (! $issue) {
            return redirect()->to(site_url('admin/diamond-inventory/issues'))->with('error', 'Issue not found.');
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
                    'carat' => $line['carat'],
                    'rate_per_carat' => $line['rate_per_carat'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyIssue($id);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-inventory/issues/view/' . $id))
            ->with('success', 'Diamond issue updated and voucher refreshed.');
    }

    public function delete(int $id)
    {
        $issue = $this->headerModel->find($id);
        if (! $issue) {
            return redirect()->to(site_url('admin/diamond-inventory/issues'))->with('error', 'Issue not found.');
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
            return redirect()->to(site_url('admin/diamond-inventory/issues'))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-inventory/issues'))
            ->with('success', 'Issue deleted and stock restored.');
    }

    /**
     * @return array{lines:list<array<string,mixed>>,error:?string}
     */
    private function collectLinesFromRequest(): array
    {
        $itemIds = (array) $this->request->getPost('item_id');
        $diamondTypes = (array) $this->request->getPost('diamond_type');
        $shapes = (array) $this->request->getPost('shape');
        $chalniFroms = (array) $this->request->getPost('chalni_from');
        $chalniTos = (array) $this->request->getPost('chalni_to');
        $colors = (array) $this->request->getPost('color');
        $clarities = (array) $this->request->getPost('clarity');
        $cuts = (array) $this->request->getPost('cut');
        $pcs = (array) $this->request->getPost('pcs');
        $carats = (array) $this->request->getPost('carat');
        $rates = (array) $this->request->getPost('rate_per_carat');

        $max = max(
            count($itemIds),
            count($diamondTypes),
            count($shapes),
            count($chalniFroms),
            count($chalniTos),
            count($colors),
            count($clarities),
            count($cuts),
            count($pcs),
            count($carats),
            count($rates)
        );

        $lines = [];
        for ($i = 0; $i < $max; $i++) {
            $itemId = (int) ($itemIds[$i] ?? 0);
            $diamondType = trim((string) ($diamondTypes[$i] ?? ''));
            $shape = trim((string) ($shapes[$i] ?? ''));
            $chalniFromRaw = trim((string) ($chalniFroms[$i] ?? ''));
            $chalniToRaw = trim((string) ($chalniTos[$i] ?? ''));
            $color = trim((string) ($colors[$i] ?? ''));
            $clarity = trim((string) ($clarities[$i] ?? ''));
            $cut = trim((string) ($cuts[$i] ?? ''));
            $pcsValue = (float) ($pcs[$i] ?? 0);
            $caratValue = (float) ($carats[$i] ?? 0);
            $rateRaw = trim((string) ($rates[$i] ?? ''));

            $isBlank = $itemId <= 0
                && $diamondType === ''
                && $shape === ''
                && $chalniFromRaw === ''
                && $chalniToRaw === ''
                && $color === ''
                && $clarity === ''
                && $cut === ''
                && $pcsValue <= 0
                && $caratValue <= 0
                && $rateRaw === '';
            if ($isBlank) {
                continue;
            }

            if ($caratValue <= 0) {
                return ['lines' => [], 'error' => 'Carat must be greater than zero for each line.'];
            }
            if ($pcsValue < 0) {
                return ['lines' => [], 'error' => 'PCS cannot be negative.'];
            }

            $rateValue = $rateRaw === '' ? null : (float) $rateRaw;
            if ($rateValue !== null && $rateValue < 0) {
                return ['lines' => [], 'error' => 'Rate per carat cannot be negative.'];
            }

            if ($itemId <= 0) {
                if ($diamondType === '') {
                    return ['lines' => [], 'error' => 'Diamond type is required when item is not selected.'];
                }
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
                    'shape' => $shape,
                    'chalni_from' => $from,
                    'chalni_to' => $to,
                    'color' => $color,
                    'clarity' => $clarity,
                    'cut' => $cut,
                ];
            } else {
                if (! $this->itemModel->find($itemId)) {
                    return ['lines' => [], 'error' => 'Selected item does not exist.'];
                }
                $signature = [];
            }

            $lineValue = $rateValue === null ? null : round($caratValue * $rateValue, 2);
            $lines[] = [
                'item_id' => $itemId,
                'pcs' => round($pcsValue, 3),
                'carat' => round($caratValue, 3),
                'rate_per_carat' => $rateValue === null ? null : round($rateValue, 2),
                'line_value' => $lineValue,
                'signature' => $signature,
            ];
        }

        return ['lines' => $lines, 'error' => null];
    }

    private function validateHeader()
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

        $karigarExists = $this->karigarModel->where('id', $karigarId)->where('is_active', 1)->countAllResults();
        if ($karigarExists === 0) {
            return 'Selected karigar was not found or inactive.';
        }

        $locationExists = $this->locationModel->where('id', $locationId)->where('is_active', 1)->countAllResults();
        if ($locationExists === 0) {
            return 'Selected warehouse was not found.';
        }

        return null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function itemOptions(): array
    {
        return db_connect()->table('items i')
            ->select('i.*, COALESCE(s.avg_cost_per_carat, 0) as avg_cost_per_carat', false)
            ->join('stock s', 's.item_id = i.id', 'left')
            ->orderBy('i.diamond_type', 'ASC')
            ->orderBy('i.id', 'DESC')
            ->get()
            ->getResultArray();
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
            ->select('o.id, o.order_no, o.order_type, o.assigned_karigar_id, k.name as karigar_name, COALESCE(SUM(oi.diamond_required_cts),0) as diamond_budget_cts', false)
            ->join('order_items oi', 'oi.order_id = o.id', 'left')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left')
            ->whereNotIn('o.status', ['Cancelled', 'Completed'])
            ->where('o.assigned_karigar_id IS NOT NULL', null, false)
            ->where('o.assigned_karigar_id >', 0)
            ->groupBy('o.id')
            ->orderBy('o.id', 'DESC')
            ->limit(500)
            ->get()
            ->getResultArray();

        $issueMap = [];
        $issueRows = db_connect()->table('issue_headers ih')
            ->select('ih.order_id, COALESCE(SUM(il.carat),0) as issued_cts', false)
            ->join('issue_lines il', 'il.issue_id = ih.id', 'inner')
            ->where('ih.order_id IS NOT NULL', null, false)
            ->groupBy('ih.order_id')
            ->get()
            ->getResultArray();
        foreach ($issueRows as $row) {
            $issueMap[(int) $row['order_id']] = (float) ($row['issued_cts'] ?? 0);
        }

        $returnMap = [];
        if (db_connect()->tableExists('return_headers') && db_connect()->tableExists('return_lines')) {
            $returnRows = db_connect()->table('return_headers rh')
                ->select('rh.order_id, COALESCE(SUM(rl.carat),0) as returned_cts', false)
                ->join('return_lines rl', 'rl.return_id = rh.id', 'inner')
                ->where('rh.order_id IS NOT NULL', null, false)
                ->groupBy('rh.order_id')
                ->get()
                ->getResultArray();
            foreach ($returnRows as $row) {
                $returnMap[(int) $row['order_id']] = (float) ($row['returned_cts'] ?? 0);
            }
        }

        foreach ($orders as &$order) {
            $orderId = (int) ($order['id'] ?? 0);
            $budget = (float) ($order['diamond_budget_cts'] ?? 0);
            $issued = (float) ($issueMap[$orderId] ?? 0);
            $returned = (float) ($returnMap[$orderId] ?? 0);
            $order['issued_cts'] = round($issued, 3);
            $order['returned_cts'] = round($returned, 3);
            $order['pending_cts'] = round($budget - $issued + $returned, 3);
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
        return db_connect()->table('issue_lines il')
            ->select('il.*, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, i.cut')
            ->join('items i', 'i.id = il.item_id', 'left')
            ->where('il.issue_id', $issueId)
            ->orderBy('il.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array{total_pcs:float,total_carat:float,total_value:float}
     */
    private function lineTotals(string $table, string $headerField, int $headerId): array
    {
        $row = db_connect()->table($table)
            ->select('COALESCE(SUM(pcs),0) as total_pcs, COALESCE(SUM(carat),0) as total_carat, COALESCE(SUM(line_value),0) as total_value', false)
            ->where($headerField, $headerId)
            ->get()
            ->getRowArray();

        return [
            'total_pcs' => (float) ($row['total_pcs'] ?? 0),
            'total_carat' => (float) ($row['total_carat'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }

    /**
     * @return array{name:?string,path:?string,error:?string}
     */
    private function processAttachment($existingPath, $required): array
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

        $uploadDir = FCPATH . 'uploads/issuements/diamond';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $newName = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        $file->move($uploadDir, $newName);
        $newPath = 'uploads/issuements/diamond/' . $newName;

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

    /**
     * @return array<string,mixed>
     */
    private function companySetting(): array
    {
        $row = $this->companySettingModel->orderBy('id', 'ASC')->first();
        return is_array($row) ? $row : [];
    }
}
