<?php

namespace App\Controllers\Admin\DiamondInventory;

use App\Controllers\BaseController;
use App\Models\CompanySettingModel;
use App\Models\ItemModel;
use App\Models\ReturnHeaderModel;
use App\Models\ReturnLineModel;
use App\Services\DiamondInventory\StockService;
use Throwable;

class ReturnsController extends BaseController
{
    private $headerModel;
    private $lineModel;
    private $itemModel;
    private $companySettingModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->headerModel = new ReturnHeaderModel();
        $this->lineModel = new ReturnLineModel();
        $this->itemModel = new ItemModel();
        $this->companySettingModel = new CompanySettingModel();
    }

    public function index(): string
    {
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));

        $builder = db_connect()->table('return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, k.name as karigar_name, COUNT(rl.id) as line_count, COALESCE(SUM(rl.carat), 0) as total_carat, COALESCE(SUM(rl.line_value), 0) as total_value', false)
            ->join('return_lines rl', 'rl.return_id = rh.id', 'left')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->groupBy('rh.id')
            ->orderBy('rh.id', 'DESC');

        if ($from !== '') {
            $builder->where('rh.return_date >=', $from);
        }
        if ($to !== '') {
            $builder->where('rh.return_date <=', $to);
        }

        return view('admin/diamond_inventory/returns/index', [
            'title' => 'Diamond Returns',
            'returns' => $builder->get()->getResultArray(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(): string
    {
        $preselectedOrderId = (int) ($this->request->getGet('order_id') ?? 0);
        $preselectedIssueId = (int) ($this->request->getGet('issue_id') ?? 0);

        return view('admin/diamond_inventory/returns/create', [
            'title' => 'Create Diamond Return',
            'items' => $this->itemOptions(),
            'orders' => $this->orderOptions(),
            'issues' => $this->issueOptions(),
            'return' => null,
            'lines' => [],
            'action' => site_url('admin/diamond-inventory/returns'),
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
                    'pcs' => $line['pcs'],
                    'carat' => $line['carat'],
                    'rate_per_carat' => $line['rate_per_carat'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyReturn($returnId);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-inventory/returns/view/' . $returnId))
            ->with('success', 'Return saved and return receipt generated.');
    }

    public function view(int $id)
    {
        $return = db_connect()->table('return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, ih.issue_date, k.name as karigar_name')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->where('rh.id', $id)
            ->get()
            ->getRowArray();

        if (! $return) {
            return redirect()->to(site_url('admin/diamond-inventory/returns'))->with('error', 'Return not found.');
        }

        return view('admin/diamond_inventory/returns/view', [
            'title' => 'View Return',
            'return' => $return,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('return_lines', 'return_id', $id),
        ]);
    }

    public function receipt(int $id): string
    {
        $return = db_connect()->table('return_headers rh')
            ->select('rh.*, o.order_no, ih.voucher_no as issue_voucher_no, ih.issue_date, ih.issue_to, k.name as karigar_name, k.phone as karigar_phone, k.email as karigar_email, k.address as karigar_address, k.city as karigar_city, k.state as karigar_state, k.pincode as karigar_pincode')
            ->join('orders o', 'o.id = rh.order_id', 'left')
            ->join('issue_headers ih', 'ih.id = rh.issue_id', 'left')
            ->join('karigars k', 'k.id = rh.karigar_id', 'left')
            ->where('rh.id', $id)
            ->get()
            ->getRowArray();

        if (! $return) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Return not found.');
        }

        return view('admin/vouchers/return_receipt', [
            'title' => 'Diamond Return Receipt',
            'materialType' => 'Diamond',
            'return' => $return,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('return_lines', 'return_id', $id),
            'company' => $this->companySetting(),
        ]);
    }

    public function edit(int $id)
    {
        $return = $this->headerModel->find($id);
        if (! $return) {
            return redirect()->to(site_url('admin/diamond-inventory/returns'))->with('error', 'Return not found.');
        }

        return view('admin/diamond_inventory/returns/edit', [
            'title' => 'Edit Diamond Return',
            'items' => $this->itemOptions(),
            'orders' => $this->orderOptions(),
            'issues' => $this->issueOptions(),
            'return' => $return,
            'lines' => $this->lineRows($id),
            'action' => site_url('admin/diamond-inventory/returns/' . $id . '/update'),
            'preselectedOrderId' => (int) ($return['order_id'] ?? 0),
            'preselectedIssueId' => (int) ($return['issue_id'] ?? 0),
        ]);
    }

    public function update(int $id)
    {
        $return = $this->headerModel->find($id);
        if (! $return) {
            return redirect()->to(site_url('admin/diamond-inventory/returns'))->with('error', 'Return not found.');
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
                    'pcs' => $line['pcs'],
                    'carat' => $line['carat'],
                    'rate_per_carat' => $line['rate_per_carat'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyReturn($id);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-inventory/returns/view/' . $id))
            ->with('success', 'Return updated and receipt refreshed.');
    }

    public function delete(int $id)
    {
        $return = $this->headerModel->find($id);
        if (! $return) {
            return redirect()->to(site_url('admin/diamond-inventory/returns'))->with('error', 'Return not found.');
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
            return redirect()->to(site_url('admin/diamond-inventory/returns'))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-inventory/returns'))
            ->with('success', 'Return deleted and stock reversed.');
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

        $orderExists = db_connect()->table('orders')
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
    private function orderOptions(): array
    {
        return db_connect()->table('orders o')
            ->select('o.id, o.order_no')
            ->join('issue_headers ih', 'ih.order_id = o.id', 'inner')
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
        return db_connect()->table('issue_headers ih')
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
        return db_connect()->table('return_lines rl')
            ->select('rl.*, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, i.cut')
            ->join('items i', 'i.id = rl.item_id', 'left')
            ->where('rl.return_id', $returnId)
            ->orderBy('rl.id', 'ASC')
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

        $uploadDir = FCPATH . 'uploads/returns/diamond';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $newName = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        $file->move($uploadDir, $newName);
        $newPath = 'uploads/returns/diamond/' . $newName;

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

        $row = db_connect()->table('issue_headers ih')
            ->select('ih.id, ih.order_id, ih.karigar_id, ih.issue_to, ih.voucher_no, ih.issue_date, k.name as karigar_name')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->where('ih.id', $issueId)
            ->where('ih.order_id', $orderId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }
}
