<?php

namespace App\Controllers\Admin\GoldInventory;

use App\Controllers\BaseController;
use App\Models\CompanySettingModel;
use App\Models\GoldInventoryIssueHeaderModel;
use App\Models\GoldInventoryIssueLineModel;
use App\Models\GoldInventoryItemModel;
use App\Models\GoldPurityModel;
use App\Models\InventoryLocationModel;
use App\Models\KarigarModel;
use App\Services\KarigarMaterialAccountingService;
use App\Services\GoldInventory\StockService;
use App\Services\IssuementVoucherNumberService;
use Throwable;

class IssuesController extends BaseController
{
    /** @var GoldInventoryIssueHeaderModel */
    private $headerModel;
    /** @var GoldInventoryIssueLineModel */
    private $lineModel;
    /** @var GoldInventoryItemModel */
    private $itemModel;
    /** @var GoldPurityModel */
    private $purityModel;
    /** @var InventoryLocationModel */
    private $locationModel;
    /** @var KarigarModel */
    private $karigarModel;
    /** @var CompanySettingModel */
    private $companySettingModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->headerModel = new GoldInventoryIssueHeaderModel();
        $this->lineModel = new GoldInventoryIssueLineModel();
        $this->itemModel = new GoldInventoryItemModel();
        $this->purityModel = new GoldPurityModel();
        $this->locationModel = new InventoryLocationModel();
        $this->karigarModel = new KarigarModel();
        $this->companySettingModel = new CompanySettingModel();
    }

    public function index(): string
    {
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));

        $builder = db_connect()->table('gold_inventory_issue_headers ih')
            ->select('ih.*, iloc.name as warehouse_name, k.name as karigar_name, COUNT(il.id) as line_count, COALESCE(SUM(il.weight_gm), 0) as total_weight, COALESCE(SUM(il.line_value), 0) as total_value', false)
            ->join('gold_inventory_issue_lines il', 'il.issue_id = ih.id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->groupBy('ih.id')
            ->orderBy('ih.id', 'DESC');

        if ($from !== '') {
            $builder->where('ih.issue_date >=', $from);
        }
        if ($to !== '') {
            $builder->where('ih.issue_date <=', $to);
        }

        return view('admin/gold_inventory/issues/index', [
            'title' => 'Gold Issues',
            'issues' => $builder->get()->getResultArray(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(): string
    {
        $suggestedVoucherNo = (new IssuementVoucherNumberService())->next();

        return view('admin/gold_inventory/issues/create', [
            'title' => 'Create Gold Issue',
            'items' => $this->itemOptions(),
            'purities' => $this->purityOptions(),
            'locations' => $this->locationOptions(),
            'karigars' => $this->karigarOptions(),
            'issue' => null,
            'lines' => [],
            'action' => site_url('admin/gold-inventory/issues'),
            'suggestedVoucherNo' => $suggestedVoucherNo,
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
        try {
            $voucherNo = (new IssuementVoucherNumberService($db))
                ->resolveForCreate((string) $this->request->getPost('voucher_no'));
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        $parsed = $this->collectLinesFromRequest();
        if ($parsed['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $parsed['error']);
        }
        if ($parsed['lines'] === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line is required.');
        }

        try {
            $db->transException(true)->transStart();

            $issueDate = (string) $this->request->getPost('issue_date');
            $locationId = (int) $this->request->getPost('location_id');
            $karigarId = (int) $this->request->getPost('karigar_id');
            $karigar = $this->karigarModel->find($karigarId);
            $attachment = $this->processAttachment(null, true);
            if ($attachment['error'] !== null) {
                throw new \RuntimeException($attachment['error']);
            }

            $issueId = (int) $this->headerModel->insert([
                'voucher_no' => $voucherNo,
                'issue_date' => $issueDate,
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
                    'weight_gm' => $line['weight_gm'],
                    'fine_weight_gm' => $service->calculateFineWeightForItem($itemId, (float) $line['weight_gm']),
                    'rate_per_gm' => $line['rate_per_gm'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyIssue($issueId, [
                'txn_date' => $issueDate,
                'karigar_id' => $karigarId,
                'location_id' => $locationId,
                'created_by' => (int) session('admin_id'),
                'notes' => 'Gold issue posting',
            ]);
            (new KarigarMaterialAccountingService($db))->postInventoryHeader('gold', 'issue', $issueId);

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/issues'))
            ->with('success', 'Gold issue saved and voucher generated.');
    }

    public function view(int $id)
    {
        $issue = db_connect()->table('gold_inventory_issue_headers ih')
            ->select('ih.*, iloc.name as warehouse_name, k.name as karigar_name')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->where('ih.id', $id)
            ->get()
            ->getRowArray();

        if (! $issue) {
            return redirect()->to(site_url('admin/gold-inventory/issues'))->with('error', 'Issue not found.');
        }

        return view('admin/gold_inventory/issues/view', [
            'title' => 'View Gold Issue',
            'issue' => $issue,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('gold_inventory_issue_lines', 'issue_id', $id),
        ]);
    }

    public function voucher(int $id): string
    {
        $issue = db_connect()->table('gold_inventory_issue_headers ih')
            ->select('ih.*, iloc.name as warehouse_name, k.name as karigar_name, k.name as labour_name, k.phone as labour_phone, k.email as labour_email, k.address as labour_address, k.city as labour_city, k.state as labour_state, k.pincode as labour_pincode, k.department as labour_department, k.skills_text as labour_skills, k.rate_per_gm as labour_rate_per_gm, k.wastage_percentage as labour_wastage_percentage, k.aadhaar_no as labour_aadhaar_no, k.pan_no as labour_pan_no, k.joining_date as labour_joining_date, k.bank_name as labour_bank_name, k.bank_account_no as labour_bank_account_no, k.ifsc_code as labour_ifsc_code')
            ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->where('ih.id', $id)
            ->get()
            ->getRowArray();
        if (! $issue) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Issue not found.');
        }

        return view('admin/vouchers/issuement', [
            'title' => 'Gold Issuement Voucher',
            'materialType' => 'Gold',
            'issue' => $issue,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('gold_inventory_issue_lines', 'issue_id', $id),
            'company' => $this->companySetting(),
        ]);
    }

    public function edit(int $id)
    {
        $issue = $this->headerModel->find($id);
        if (! $issue) {
            return redirect()->to(site_url('admin/gold-inventory/issues'))->with('error', 'Issue not found.');
        }

        return view('admin/gold_inventory/issues/edit', [
            'title' => 'Edit Gold Issue',
            'items' => $this->itemOptions(),
            'purities' => $this->purityOptions(),
            'locations' => $this->locationOptions(),
            'karigars' => $this->karigarOptions(),
            'issue' => $issue,
            'lines' => $this->lineRows($id),
            'action' => site_url('admin/gold-inventory/issues/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        $issue = $this->headerModel->find($id);
        if (! $issue) {
            return redirect()->to(site_url('admin/gold-inventory/issues'))->with('error', 'Issue not found.');
        }

        $validationError = $this->validateHeader();
        if ($validationError !== null) {
            return redirect()->back()->withInput()->with('error', $validationError);
        }

        $db = db_connect();
        $service = new StockService($db);
        try {
            $voucherNo = (new IssuementVoucherNumberService($db))->resolveForUpdate(
                (string) $this->request->getPost('voucher_no'),
                (string) ($issue['voucher_no'] ?? '')
            );
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        $parsed = $this->collectLinesFromRequest();
        if ($parsed['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $parsed['error']);
        }
        if ($parsed['lines'] === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line is required.');
        }

        try {
            $db->transException(true)->transStart();
            $accounting = new KarigarMaterialAccountingService($db);
            $accounting->reverseHeaderVoucher('gold_inventory_issue_headers', $id, 'Gold issue edited', (int) session('admin_id'));
            $service->reverseIssue($id, [
                'txn_date' => (string) ($issue['issue_date'] ?? ''),
                'karigar_id' => isset($issue['karigar_id']) ? (int) $issue['karigar_id'] : null,
                'location_id' => isset($issue['location_id']) ? (int) $issue['location_id'] : null,
                'created_by' => (int) session('admin_id'),
                'notes' => 'Issue reversal for edit',
            ]);

            $issueDate = (string) $this->request->getPost('issue_date');
            $locationId = (int) $this->request->getPost('location_id');
            $karigarId = (int) $this->request->getPost('karigar_id');
            $karigar = $this->karigarModel->find($karigarId);
            $attachment = $this->processAttachment((string) ($issue['attachment_path'] ?? ''), ((string) ($issue['attachment_path'] ?? '')) === '');
            if ($attachment['error'] !== null) {
                throw new \RuntimeException($attachment['error']);
            }

            $this->headerModel->update($id, [
                'voucher_no' => $voucherNo,
                'issue_date' => $issueDate,
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
                    'weight_gm' => $line['weight_gm'],
                    'fine_weight_gm' => $service->calculateFineWeightForItem($itemId, (float) $line['weight_gm']),
                    'rate_per_gm' => $line['rate_per_gm'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyIssue($id, [
                'txn_date' => $issueDate,
                'karigar_id' => $karigarId,
                'location_id' => $locationId,
                'created_by' => (int) session('admin_id'),
                'notes' => 'Gold issue posting',
            ]);
            $accounting->postInventoryHeader('gold', 'issue', $id);

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/issues/view/' . $id))
            ->with('success', 'Issue updated and stock recalculated.');
    }

    public function delete(int $id)
    {
        $issue = $this->headerModel->find($id);
        if (! $issue) {
            return redirect()->to(site_url('admin/gold-inventory/issues'))->with('error', 'Issue not found.');
        }

        $db = db_connect();
        $service = new StockService($db);

        try {
            $db->transException(true)->transStart();
            (new KarigarMaterialAccountingService($db))->reverseHeaderVoucher(
                'gold_inventory_issue_headers',
                $id,
                'Gold issue deleted',
                (int) session('admin_id')
            );
            $service->reverseIssue($id, [
                'txn_date' => (string) ($issue['issue_date'] ?? ''),
                'karigar_id' => isset($issue['karigar_id']) ? (int) $issue['karigar_id'] : null,
                'location_id' => isset($issue['location_id']) ? (int) $issue['location_id'] : null,
                'created_by' => (int) session('admin_id'),
                'notes' => 'Issue deleted reversal',
            ]);
            $this->lineModel->where('issue_id', $id)->delete();
            $this->deleteFile((string) ($issue['attachment_path'] ?? ''));
            $this->headerModel->delete($id);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->to(site_url('admin/gold-inventory/issues'))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/issues'))
            ->with('success', 'Issue deleted and stock restored.');
    }

    /**
     * @return array{lines:list<array<string,mixed>>,error:?string}
     */
    private function collectLinesFromRequest(): array
    {
        $itemIds = (array) $this->request->getPost('item_id');
        $purityIds = (array) $this->request->getPost('gold_purity_id');
        $colors = (array) $this->request->getPost('color_name');
        $forms = (array) $this->request->getPost('form_type');
        $weights = (array) $this->request->getPost('weight_gm');
        $rates = (array) $this->request->getPost('rate_per_gm');

        $max = max(count($itemIds), count($purityIds), count($colors), count($forms), count($weights), count($rates));
        $lines = [];

        for ($i = 0; $i < $max; $i++) {
            $itemId = (int) ($itemIds[$i] ?? 0);
            $purityId = (int) ($purityIds[$i] ?? 0);
            $colorName = trim((string) ($colors[$i] ?? ''));
            $formType = trim((string) ($forms[$i] ?? ''));
            $weight = (float) ($weights[$i] ?? 0);
            $rateRaw = trim((string) ($rates[$i] ?? ''));

            $isBlank = $itemId <= 0
                && $purityId <= 0
                && $colorName === ''
                && $formType === ''
                && $weight <= 0
                && $rateRaw === '';
            if ($isBlank) {
                continue;
            }

            if ($weight <= 0) {
                return ['lines' => [], 'error' => 'Weight must be greater than zero for each line.'];
            }

            $rate = $rateRaw === '' ? null : (float) $rateRaw;
            if ($rate !== null && $rate < 0) {
                return ['lines' => [], 'error' => 'Rate per gram cannot be negative.'];
            }

            if ($itemId <= 0) {
                if ($purityId <= 0) {
                    return ['lines' => [], 'error' => 'Select gold purity when existing item is not selected.'];
                }
                $signature = [
                    'gold_purity_id' => $purityId,
                    'color_name' => $colorName,
                    'form_type' => $formType,
                ];
            } else {
                if (! $this->itemModel->find($itemId)) {
                    return ['lines' => [], 'error' => 'Selected gold item does not exist.'];
                }
                $signature = [];
            }

            $lineValue = $rate === null ? null : round($weight * $rate, 2);
            $lines[] = [
                'item_id' => $itemId,
                'weight_gm' => round($weight, 3),
                'rate_per_gm' => $rate === null ? null : round($rate, 2),
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
            'voucher_no' => 'required|max_length[80]',
            'karigar_id' => 'required|integer|greater_than[0]',
            'location_id' => 'required|integer|greater_than[0]',
            'purpose' => 'required|max_length[50]',
            'notes' => 'permit_empty',
        ])) {
            $errors = $this->validator ? $this->validator->getErrors() : [];
            return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
        }

        $locationId = (int) $this->request->getPost('location_id');
        if (! $this->locationModel->where('is_active', 1)->find($locationId)) {
            return 'Selected warehouse was not found.';
        }

        $karigarId = (int) $this->request->getPost('karigar_id');
        $karigarExists = $this->karigarModel->where('id', $karigarId)->where('is_active', 1)->countAllResults();
        if ($karigarExists === 0) {
            return 'Selected karigar was not found or inactive.';
        }

        return null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function itemOptions(): array
    {
        return db_connect()->table('gold_inventory_items gi')
            ->select('gi.*, gp.purity_code as master_purity_code, gp.color_name as master_color_name, COALESCE(gs.avg_cost_per_gm, 0) as avg_cost_per_gm', false)
            ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
            ->join('gold_inventory_stock gs', 'gs.item_id = gi.id', 'left')
            ->orderBy('gi.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function purityOptions(): array
    {
        return $this->purityModel
            ->where('is_active', 1)
            ->orderBy('purity_percent', 'DESC')
            ->findAll();
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
        return $this->karigarModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function lineRows(int $issueId): array
    {
        return db_connect()->table('gold_inventory_issue_lines il')
            ->select('il.*, gi.gold_purity_id, gi.purity_code, gi.purity_percent, gi.color_name, gi.form_type, gp.purity_code as master_purity_code')
            ->join('gold_inventory_items gi', 'gi.id = il.item_id', 'left')
            ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
            ->where('il.issue_id', $issueId)
            ->orderBy('il.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array{total_weight:float,total_fine:float,total_value:float}
     */
    private function lineTotals(string $table, string $headerField, int $headerId): array
    {
        $row = db_connect()->table($table)
            ->select('COALESCE(SUM(weight_gm),0) as total_weight, COALESCE(SUM(fine_weight_gm),0) as total_fine, COALESCE(SUM(line_value),0) as total_value', false)
            ->where($headerField, $headerId)
            ->get()
            ->getRowArray();

        return [
            'total_weight' => (float) ($row['total_weight'] ?? 0),
            'total_fine' => (float) ($row['total_fine'] ?? 0),
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

        $uploadDir = FCPATH . 'uploads/issuements/gold';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $newName = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        $file->move($uploadDir, $newName);
        $newPath = 'uploads/issuements/gold/' . $newName;

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

    /**
     * @return array<string,mixed>
     */
    private function companySetting(): array
    {
        $row = $this->companySettingModel->orderBy('id', 'ASC')->first();
        return is_array($row) ? $row : [];
    }
}
