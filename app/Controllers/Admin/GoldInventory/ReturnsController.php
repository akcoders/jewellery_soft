<?php

namespace App\Controllers\Admin\GoldInventory;

use App\Controllers\BaseController;
use App\Models\CompanySettingModel;
use App\Models\GoldInventoryItemModel;
use App\Models\GoldInventoryReturnHeaderModel;
use App\Models\GoldInventoryReturnLineModel;
use App\Models\GoldPurityModel;
use App\Models\InventoryLocationModel;
use App\Models\OrderModel;
use App\Services\GoldInventory\StockService;
use Throwable;

class ReturnsController extends BaseController
{
    private $headerModel;
    private $lineModel;
    private $itemModel;
    private $purityModel;
    private $locationModel;
    private $orderModel;
    private $companySettingModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->headerModel = new GoldInventoryReturnHeaderModel();
        $this->lineModel = new GoldInventoryReturnLineModel();
        $this->itemModel = new GoldInventoryItemModel();
        $this->purityModel = new GoldPurityModel();
        $this->locationModel = new InventoryLocationModel();
        $this->orderModel = new OrderModel();
        $this->companySettingModel = new CompanySettingModel();
    }

    public function index(): string
    {
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));

        $builder = db_connect()->table('gold_inventory_return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, iloc.name as location_name, k.name as karigar_name, COUNT(rl.id) as line_count, COALESCE(SUM(rl.weight_gm), 0) as total_weight, COALESCE(SUM(rl.line_value), 0) as total_value', false)
            ->join('gold_inventory_return_lines rl', 'rl.return_id = rh.id', 'left')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('gold_inventory_issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = rh.location_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->groupBy('rh.id')
            ->orderBy('rh.id', 'DESC');

        if ($from !== '') {
            $builder->where('rh.return_date >=', $from);
        }
        if ($to !== '') {
            $builder->where('rh.return_date <=', $to);
        }

        return view('admin/gold_inventory/returns/index', [
            'title' => 'Gold Returns',
            'returns' => $builder->get()->getResultArray(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(): string
    {
        $preselectedOrderId = (int) ($this->request->getGet('order_id') ?? 0);
        $preselectedIssueId = (int) ($this->request->getGet('issue_id') ?? 0);

        return view('admin/gold_inventory/returns/create', [
            'title' => 'Create Gold Return',
            'items' => $this->itemOptions(),
            'purities' => $this->purityOptions(),
            'locations' => $this->locationOptions(),
            'orders' => $this->orderOptions(),
            'issues' => $this->issueOptions(),
            'return' => null,
            'lines' => [],
            'action' => site_url('admin/gold-inventory/returns'),
            'preselectedOrderId' => $preselectedOrderId,
            'preselectedIssueId' => $preselectedIssueId,
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

            $returnDate = (string) $this->request->getPost('return_date');
            $locationId = (int) $this->request->getPost('location_id');
            $orderId = (int) $this->request->getPost('order_id');
            $issueId = (int) $this->request->getPost('issue_id');
            $issue = $this->resolveSelectedIssue($orderId, $issueId);
            if (! $issue) {
                throw new \RuntimeException('Selected issuance reference was not found for this order.');
            }

            $attachment = $this->processAttachment(null, true);
            if ($attachment['error'] !== null) {
                throw new \RuntimeException($attachment['error']);
            }

            $karigarId = isset($issue['karigar_id']) ? (int) $issue['karigar_id'] : null;
            $returnFromInput = trim((string) $this->request->getPost('return_from'));
            $resolvedReturnFrom = $returnFromInput !== ''
                ? $returnFromInput
                : ((string) ($issue['issue_to'] ?? '') !== '' ? (string) $issue['issue_to'] : (string) ($issue['karigar_name'] ?? ''));

            $returnId = (int) $this->headerModel->insert([
                'voucher_no' => $this->generateReturnVoucherNo(),
                'return_date' => $returnDate,
                'order_id' => $orderId,
                'issue_id' => $issueId,
                'karigar_id' => $karigarId,
                'location_id' => $locationId,
                'return_from' => $resolvedReturnFrom !== '' ? $resolvedReturnFrom : null,
                'purpose' => trim((string) $this->request->getPost('purpose')) ?: null,
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
                    'return_id' => $returnId,
                    'item_id' => $itemId,
                    'weight_gm' => $line['weight_gm'],
                    'fine_weight_gm' => $service->calculateFineWeightForItem($itemId, (float) $line['weight_gm']),
                    'rate_per_gm' => $line['rate_per_gm'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyReturn($returnId, [
                'txn_date' => $returnDate,
                'order_id' => $orderId,
                'karigar_id' => $karigarId,
                'location_id' => $locationId,
                'created_by' => (int) session('admin_id'),
                'notes' => 'Gold return posting',
            ]);

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/returns/view/' . $returnId))
            ->with('success', 'Return saved and return receipt generated.');
    }

    public function view(int $id)
    {
        $return = db_connect()->table('gold_inventory_return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, ih.issue_date, iloc.name as location_name, k.name as karigar_name')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('gold_inventory_issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = rh.location_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->where('rh.id', $id)
            ->get()
            ->getRowArray();

        if (! $return) {
            return redirect()->to(site_url('admin/gold-inventory/returns'))->with('error', 'Return not found.');
        }

        return view('admin/gold_inventory/returns/view', [
            'title' => 'View Gold Return',
            'return' => $return,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('gold_inventory_return_lines', 'return_id', $id),
        ]);
    }

    public function receipt(int $id): string
    {
        $return = db_connect()->table('gold_inventory_return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, ih.issue_date, ih.issue_to, iloc.name as location_name, k.name as karigar_name, k.phone as karigar_phone, k.email as karigar_email, k.address as karigar_address, k.city as karigar_city, k.state as karigar_state, k.pincode as karigar_pincode')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('gold_inventory_issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = rh.location_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->where('rh.id', $id)
            ->get()
            ->getRowArray();

        if (! $return) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Return not found.');
        }

        return view('admin/vouchers/return_receipt', [
            'title' => 'Gold Return Receipt',
            'materialType' => 'Gold',
            'return' => $return,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('gold_inventory_return_lines', 'return_id', $id),
            'company' => $this->companySetting(),
        ]);
    }

    public function edit(int $id)
    {
        $return = $this->headerModel->find($id);
        if (! $return) {
            return redirect()->to(site_url('admin/gold-inventory/returns'))->with('error', 'Return not found.');
        }

        return view('admin/gold_inventory/returns/edit', [
            'title' => 'Edit Gold Return',
            'items' => $this->itemOptions(),
            'purities' => $this->purityOptions(),
            'locations' => $this->locationOptions(),
            'orders' => $this->orderOptions(),
            'issues' => $this->issueOptions(),
            'return' => $return,
            'lines' => $this->lineRows($id),
            'action' => site_url('admin/gold-inventory/returns/' . $id . '/update'),
            'preselectedOrderId' => (int) ($return['order_id'] ?? 0),
            'preselectedIssueId' => (int) ($return['issue_id'] ?? 0),
        ]);
    }

    public function update(int $id)
    {
        $return = $this->headerModel->find($id);
        if (! $return) {
            return redirect()->to(site_url('admin/gold-inventory/returns'))->with('error', 'Return not found.');
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
            $service->reverseReturn($id, [
                'txn_date' => (string) ($return['return_date'] ?? ''),
                'order_id' => isset($return['order_id']) ? (int) $return['order_id'] : null,
                'karigar_id' => isset($return['karigar_id']) ? (int) $return['karigar_id'] : null,
                'location_id' => isset($return['location_id']) ? (int) $return['location_id'] : null,
                'created_by' => (int) session('admin_id'),
                'notes' => 'Return reversal for edit',
            ]);

            $returnDate = (string) $this->request->getPost('return_date');
            $locationId = (int) $this->request->getPost('location_id');
            $orderId = (int) $this->request->getPost('order_id');
            $issueId = (int) $this->request->getPost('issue_id');
            $issue = $this->resolveSelectedIssue($orderId, $issueId);
            if (! $issue) {
                throw new \RuntimeException('Selected issuance reference was not found for this order.');
            }

            $attachment = $this->processAttachment((string) ($return['attachment_path'] ?? ''), ((string) ($return['attachment_path'] ?? '')) === '');
            if ($attachment['error'] !== null) {
                throw new \RuntimeException($attachment['error']);
            }

            $karigarId = isset($issue['karigar_id']) ? (int) $issue['karigar_id'] : null;
            $returnFromInput = trim((string) $this->request->getPost('return_from'));
            $resolvedReturnFrom = $returnFromInput !== ''
                ? $returnFromInput
                : ((string) ($issue['issue_to'] ?? '') !== '' ? (string) $issue['issue_to'] : (string) ($issue['karigar_name'] ?? ''));

            $this->headerModel->update($id, [
                'voucher_no' => (string) (($return['voucher_no'] ?? '') ?: $this->generateReturnVoucherNo()),
                'return_date' => $returnDate,
                'order_id' => $orderId,
                'issue_id' => $issueId,
                'karigar_id' => $karigarId,
                'location_id' => $locationId,
                'return_from' => $resolvedReturnFrom !== '' ? $resolvedReturnFrom : null,
                'purpose' => trim((string) $this->request->getPost('purpose')) ?: null,
                'notes' => trim((string) $this->request->getPost('notes')) ?: null,
                'attachment_name' => $attachment['name'] ?? (string) ($return['attachment_name'] ?? ''),
                'attachment_path' => $attachment['path'] ?? (string) ($return['attachment_path'] ?? ''),
            ]);

            $this->lineModel->where('return_id', $id)->delete();
            foreach ($parsed['lines'] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                if ($itemId <= 0) {
                    $itemId = $service->upsertItemFromSignature((array) ($line['signature'] ?? []));
                }

                $this->lineModel->insert([
                    'return_id' => $id,
                    'item_id' => $itemId,
                    'weight_gm' => $line['weight_gm'],
                    'fine_weight_gm' => $service->calculateFineWeightForItem($itemId, (float) $line['weight_gm']),
                    'rate_per_gm' => $line['rate_per_gm'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyReturn($id, [
                'txn_date' => $returnDate,
                'order_id' => $orderId,
                'karigar_id' => $karigarId,
                'location_id' => $locationId,
                'created_by' => (int) session('admin_id'),
                'notes' => 'Gold return posting',
            ]);

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/returns/view/' . $id))
            ->with('success', 'Return updated and receipt refreshed.');
    }

    public function delete(int $id)
    {
        $return = $this->headerModel->find($id);
        if (! $return) {
            return redirect()->to(site_url('admin/gold-inventory/returns'))->with('error', 'Return not found.');
        }

        $db = db_connect();
        $service = new StockService($db);

        try {
            $db->transException(true)->transStart();
            $service->reverseReturn($id, [
                'txn_date' => (string) ($return['return_date'] ?? ''),
                'order_id' => isset($return['order_id']) ? (int) $return['order_id'] : null,
                'karigar_id' => isset($return['karigar_id']) ? (int) $return['karigar_id'] : null,
                'location_id' => isset($return['location_id']) ? (int) $return['location_id'] : null,
                'created_by' => (int) session('admin_id'),
                'notes' => 'Return deleted reversal',
            ]);
            $this->lineModel->where('return_id', $id)->delete();
            $this->deleteFile((string) ($return['attachment_path'] ?? ''));
            $this->headerModel->delete($id);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->to(site_url('admin/gold-inventory/returns'))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/returns'))
            ->with('success', 'Return deleted and stock reversed.');
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
            'return_date' => 'required|valid_date',
            'order_id' => 'required|integer|greater_than[0]',
            'issue_id' => 'required|integer|greater_than[0]',
            'location_id' => 'required|integer|greater_than[0]',
            'return_from' => 'permit_empty|max_length[120]',
            'purpose' => 'permit_empty|max_length[50]',
            'notes' => 'permit_empty',
        ])) {
            $errors = $this->validator ? $this->validator->getErrors() : [];
            return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
        }

        $locationId = (int) $this->request->getPost('location_id');
        if (! $this->locationModel->where('is_active', 1)->find($locationId)) {
            return 'Selected location was not found.';
        }

        $orderId = (int) $this->request->getPost('order_id');
        $issueId = (int) $this->request->getPost('issue_id');

        $orderExists = $this->orderModel
            ->where('id', $orderId)
            ->whereNotIn('status', ['Cancelled', 'Completed'])
            ->countAllResults();
        if ($orderExists === 0) {
            return 'Selected order was not found.';
        }

        $issue = $this->resolveSelectedIssue($orderId, $issueId);
        if (! $issue) {
            return 'Selected issuance reference is invalid for selected order.';
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
    private function orderOptions(): array
    {
        return db_connect()->table('orders o')
            ->select('o.id, o.order_no, o.assigned_karigar_id')
            ->join('gold_inventory_issue_headers ih', 'ih.order_id = o.id', 'inner')
            ->whereNotIn('o.status', ['Cancelled', 'Completed'])
            ->groupBy('o.id, o.order_no, o.assigned_karigar_id')
            ->orderBy('o.id', 'DESC')
            ->limit(300)
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function issueOptions(): array
    {
        return db_connect()->table('gold_inventory_issue_headers ih')
            ->select('ih.id, ih.order_id, ih.issue_date, ih.voucher_no, ih.issue_to, ih.karigar_id, k.name as karigar_name')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->join('orders o', 'o.id = ih.order_id', 'left')
            ->where('ih.order_id IS NOT NULL', null, false)
            ->whereNotIn('o.status', ['Cancelled', 'Completed'])
            ->orderBy('ih.id', 'DESC')
            ->limit(500)
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function lineRows(int $returnId): array
    {
        return db_connect()->table('gold_inventory_return_lines rl')
            ->select('rl.*, gi.gold_purity_id, gi.purity_code, gi.purity_percent, gi.color_name, gi.form_type, gp.purity_code as master_purity_code')
            ->join('gold_inventory_items gi', 'gi.id = rl.item_id', 'left')
            ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
            ->where('rl.return_id', $returnId)
            ->orderBy('rl.id', 'ASC')
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
                return ['name' => null, 'path' => null, 'error' => 'Attachment is required for return receipt.'];
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

        $uploadDir = FCPATH . 'uploads/returns/gold';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $newName = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        $file->move($uploadDir, $newName);
        $newPath = 'uploads/returns/gold/' . $newName;

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

    private function generateReturnVoucherNo(): string
    {
        $prefix = strtoupper(trim((string) ($this->companySetting()['issuement_suffix'] ?? 'RET')));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?: 'RET';

        $maxSerial = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';
        $rows = $this->headerModel
            ->select('voucher_no')
            ->like('voucher_no', $prefix, 'after')
            ->findAll();

        foreach ($rows as $row) {
            $voucherNo = (string) ($row['voucher_no'] ?? '');
            if (preg_match($pattern, $voucherNo, $m) === 1) {
                $n = (int) $m[1];
                if ($n > $maxSerial) {
                    $maxSerial = $n;
                }
            }
        }

        do {
            $maxSerial++;
            $voucher = $prefix . str_pad((string) $maxSerial, 3, '0', STR_PAD_LEFT);
            $exists = $this->headerModel->where('voucher_no', $voucher)->countAllResults();
        } while ($exists > 0);

        return $voucher;
    }

    /**
     * @return array<string,mixed>
     */
    private function companySetting(): array
    {
        $row = $this->companySettingModel->orderBy('id', 'DESC')->first();
        return is_array($row) ? $row : [];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolveSelectedIssue(int $orderId, int $issueId)
    {
        if ($orderId <= 0 || $issueId <= 0) {
            return null;
        }

        $row = db_connect()->table('gold_inventory_issue_headers ih')
            ->select('ih.id, ih.order_id, ih.karigar_id, ih.issue_to, ih.voucher_no, ih.issue_date, k.name as karigar_name')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->where('ih.id', $issueId)
            ->where('ih.order_id', $orderId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }
}
