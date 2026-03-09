<?php

namespace App\Controllers\Admin\StoneInventory;

use App\Controllers\BaseController;
use App\Models\CompanySettingModel;
use App\Models\StoneInventoryItemModel;
use App\Models\StoneInventoryReturnHeaderModel;
use App\Models\StoneInventoryReturnLineModel;
use App\Services\StoneInventory\StockService;
use Throwable;

class ReturnsController extends BaseController
{
    private StoneInventoryReturnHeaderModel $headerModel;
    private StoneInventoryReturnLineModel $lineModel;
    private StoneInventoryItemModel $itemModel;
    private CompanySettingModel $companySettingModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->headerModel = new StoneInventoryReturnHeaderModel();
        $this->lineModel = new StoneInventoryReturnLineModel();
        $this->itemModel = new StoneInventoryItemModel();
        $this->companySettingModel = new CompanySettingModel();
    }

    public function index(): string
    {
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));

        $builder = db_connect()->table('stone_inventory_return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, k.name as karigar_name, COUNT(rl.id) as line_count, COALESCE(SUM(rl.qty), 0) as total_qty, COALESCE(SUM(rl.line_value), 0) as total_value', false)
            ->join('stone_inventory_return_lines rl', 'rl.return_id = rh.id', 'left')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('stone_inventory_issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->groupBy('rh.id')
            ->orderBy('rh.id', 'DESC');

        if ($from !== '') {
            $builder->where('rh.return_date >=', $from);
        }
        if ($to !== '') {
            $builder->where('rh.return_date <=', $to);
        }

        return view('admin/stone_inventory/returns/index', [
            'title' => 'Stone Returns',
            'returns' => $builder->get()->getResultArray(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(): string
    {
        $preselectedOrderId = (int) ($this->request->getGet('order_id') ?? 0);
        $preselectedIssueId = (int) ($this->request->getGet('issue_id') ?? 0);

        return view('admin/stone_inventory/returns/create', [
            'title' => 'Create Stone Return',
            'items' => $this->itemOptions(),
            'orders' => $this->orderOptions(),
            'issues' => $this->issueOptions(),
            'return' => null,
            'lines' => [],
            'action' => site_url('admin/stone-inventory/returns'),
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

            $returnFromInput = trim((string) $this->request->getPost('return_from'));
            $resolvedReturnFrom = $returnFromInput !== ''
                ? $returnFromInput
                : ((string) ($issue['issue_to'] ?? '') !== '' ? (string) $issue['issue_to'] : (string) ($issue['karigar_name'] ?? ''));

            $returnId = (int) $this->headerModel->insert([
                'voucher_no' => $this->generateReturnVoucherNo(),
                'return_date' => (string) $this->request->getPost('return_date'),
                'order_id' => $orderId,
                'issue_id' => $issueId,
                'karigar_id' => isset($issue['karigar_id']) ? (int) $issue['karigar_id'] : null,
                'location_id' => isset($issue['location_id']) ? (int) $issue['location_id'] : null,
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
                    'qty' => $line['qty'],
                    'rate' => $line['rate'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyReturn($returnId);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/stone-inventory/returns/view/' . $returnId))
            ->with('success', 'Return saved and return receipt generated.');
    }

    public function view(int $id)
    {
        $return = db_connect()->table('stone_inventory_return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, ih.issue_date, k.name as karigar_name, iloc.name as location_name')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('stone_inventory_issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = rh.location_id', 'left')
            ->where('rh.id', $id)
            ->get()
            ->getRowArray();

        if (! $return) {
            return redirect()->to(site_url('admin/stone-inventory/returns'))->with('error', 'Return not found.');
        }

        return view('admin/stone_inventory/returns/view', [
            'title' => 'View Return',
            'return' => $return,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('stone_inventory_return_lines', 'return_id', $id),
        ]);
    }

    public function receipt(int $id): string
    {
        $return = db_connect()->table('stone_inventory_return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, ih.issue_date, ih.issue_to, k.name as karigar_name, k.phone as karigar_phone, k.email as karigar_email, k.address as karigar_address, k.city as karigar_city, k.state as karigar_state, k.pincode as karigar_pincode, iloc.name as location_name')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('stone_inventory_issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = rh.location_id', 'left')
            ->where('rh.id', $id)
            ->get()
            ->getRowArray();

        if (! $return) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Return not found.');
        }

        return view('admin/vouchers/return_receipt', [
            'title' => 'Stone Return Receipt',
            'materialType' => 'Stone',
            'return' => $return,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('stone_inventory_return_lines', 'return_id', $id),
            'company' => $this->companySetting(),
        ]);
    }

    public function edit(int $id)
    {
        $return = $this->headerModel->find($id);
        if (! $return) {
            return redirect()->to(site_url('admin/stone-inventory/returns'))->with('error', 'Return not found.');
        }

        return view('admin/stone_inventory/returns/edit', [
            'title' => 'Edit Stone Return',
            'items' => $this->itemOptions(),
            'orders' => $this->orderOptions(),
            'issues' => $this->issueOptions(),
            'return' => $return,
            'lines' => $this->lineRows($id),
            'action' => site_url('admin/stone-inventory/returns/' . $id . '/update'),
            'preselectedOrderId' => (int) ($return['order_id'] ?? 0),
            'preselectedIssueId' => (int) ($return['issue_id'] ?? 0),
        ]);
    }

    public function update(int $id)
    {
        $return = $this->headerModel->find($id);
        if (! $return) {
            return redirect()->to(site_url('admin/stone-inventory/returns'))->with('error', 'Return not found.');
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
            $service->reverseReturn($id);

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

            $returnFromInput = trim((string) $this->request->getPost('return_from'));
            $resolvedReturnFrom = $returnFromInput !== ''
                ? $returnFromInput
                : ((string) ($issue['issue_to'] ?? '') !== '' ? (string) $issue['issue_to'] : (string) ($issue['karigar_name'] ?? ''));

            $this->headerModel->update($id, [
                'voucher_no' => (string) (($return['voucher_no'] ?? '') ?: $this->generateReturnVoucherNo()),
                'return_date' => (string) $this->request->getPost('return_date'),
                'order_id' => $orderId,
                'issue_id' => $issueId,
                'karigar_id' => isset($issue['karigar_id']) ? (int) $issue['karigar_id'] : null,
                'location_id' => isset($issue['location_id']) ? (int) $issue['location_id'] : null,
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
                    'qty' => $line['qty'],
                    'rate' => $line['rate'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyReturn($id);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/stone-inventory/returns/view/' . $id))
            ->with('success', 'Return updated and receipt refreshed.');
    }

    public function delete(int $id)
    {
        $return = $this->headerModel->find($id);
        if (! $return) {
            return redirect()->to(site_url('admin/stone-inventory/returns'))->with('error', 'Return not found.');
        }

        $db = db_connect();
        $service = new StockService($db);

        try {
            $db->transException(true)->transStart();
            $service->reverseReturn($id);
            $this->lineModel->where('return_id', $id)->delete();
            $this->deleteFile((string) ($return['attachment_path'] ?? ''));
            $this->headerModel->delete($id);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->to(site_url('admin/stone-inventory/returns'))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/stone-inventory/returns'))
            ->with('success', 'Return deleted and stock reversed.');
    }

    /**
     * @return array{lines:list<array<string,mixed>>,error:?string}
     */
    private function collectLinesFromRequest(): array
    {
        $itemIds = (array) $this->request->getPost('item_id');
        $productNames = (array) $this->request->getPost('product_name');
        $stoneTypes = (array) $this->request->getPost('stone_type');
        $qtys = (array) $this->request->getPost('qty');
        $rates = (array) $this->request->getPost('rate');

        $max = max(count($itemIds), count($productNames), count($stoneTypes), count($qtys), count($rates));
        $lines = [];
        for ($i = 0; $i < $max; $i++) {
            $itemId = (int) ($itemIds[$i] ?? 0);
            $productName = trim((string) ($productNames[$i] ?? ''));
            $stoneType = trim((string) ($stoneTypes[$i] ?? ''));
            $qtyValue = (float) ($qtys[$i] ?? 0);
            $rateRaw = trim((string) ($rates[$i] ?? ''));

            $isBlank = $itemId <= 0 && $productName === '' && $stoneType === '' && $qtyValue <= 0 && $rateRaw === '';
            if ($isBlank) {
                continue;
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
            'return_date' => 'required|valid_date',
            'order_id' => 'required|integer|greater_than[0]',
            'issue_id' => 'required|integer|greater_than[0]',
            'return_from' => 'permit_empty|max_length[120]',
            'purpose' => 'permit_empty|max_length[50]',
            'notes' => 'permit_empty',
        ])) {
            $errors = $this->validator ? $this->validator->getErrors() : [];
            return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
        }

        $orderId = (int) $this->request->getPost('order_id');
        $issueId = (int) $this->request->getPost('issue_id');
        $orderExists = db_connect()->table('orders')->where('id', $orderId)->whereNotIn('status', ['Cancelled', 'Completed'])->countAllResults();
        if ($orderExists === 0) {
            return 'Selected order was not found.';
        }
        if (! $this->resolveSelectedIssue($orderId, $issueId)) {
            return 'Selected issuance reference is invalid for selected order.';
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
    private function orderOptions(): array
    {
        return db_connect()->table('orders o')
            ->select('o.id, o.order_no')
            ->join('stone_inventory_issue_headers ih', 'ih.order_id = o.id', 'inner')
            ->whereNotIn('o.status', ['Cancelled', 'Completed'])
            ->groupBy('o.id, o.order_no')
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
        return db_connect()->table('stone_inventory_issue_headers ih')
            ->select('ih.id, ih.order_id, ih.issue_date, ih.voucher_no, ih.issue_to, ih.karigar_id, ih.location_id, k.name as karigar_name')
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
        return db_connect()->table('stone_inventory_return_lines rl')
            ->select('rl.*, i.product_name, i.stone_type')
            ->join('stone_inventory_items i', 'i.id = rl.item_id', 'left')
            ->where('rl.return_id', $returnId)
            ->orderBy('rl.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array{total_qty:float,total_value:float}
     */
    private function lineTotals(string $table, string $headerField, int $headerId): array
    {
        $row = db_connect()->table($table)
            ->select('COALESCE(SUM(qty),0) as total_qty, COALESCE(SUM(line_value),0) as total_value', false)
            ->where($headerField, $headerId)
            ->get()
            ->getRowArray();

        return [
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

        $uploadDir = FCPATH . 'uploads/returns/stone';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $newName = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        $file->move($uploadDir, $newName);
        $newPath = 'uploads/returns/stone/' . $newName;

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
        $rows = $this->headerModel->select('voucher_no')->like('voucher_no', $prefix, 'after')->findAll();
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
    private function resolveSelectedIssue(int $orderId, int $issueId): ?array
    {
        if ($orderId <= 0 || $issueId <= 0) {
            return null;
        }

        $row = db_connect()->table('stone_inventory_issue_headers ih')
            ->select('ih.id, ih.order_id, ih.karigar_id, ih.location_id, ih.issue_to, ih.voucher_no, ih.issue_date, k.name as karigar_name')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->where('ih.id', $issueId)
            ->where('ih.order_id', $orderId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }
}
