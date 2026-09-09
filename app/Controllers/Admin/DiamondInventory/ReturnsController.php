<?php

namespace App\Controllers\Admin\DiamondInventory;

use App\Controllers\BaseController;
use App\Models\CompanySettingModel;
use App\Models\ItemModel;
use App\Models\ReturnHeaderModel;
use App\Models\ReturnLineModel;
use App\Services\DiamondBagTraceService;
use App\Services\DiamondInventory\StockService;
use App\Services\KarigarMaterialAccountingService;
use App\Services\MobileNotificationEventService;
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
            ->select('rh.*, ih.voucher_no as issue_voucher_no, k.name as karigar_name, COUNT(rl.id) as line_count, COALESCE(SUM(rl.carat), 0) as total_carat, COALESCE(SUM(rl.line_value), 0) as total_value', false)
            ->join('return_lines rl', 'rl.return_id = rh.id', 'left')
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
        $preselectedIssueId = (int) ($this->request->getGet('issue_id') ?? 0);

        return view('admin/diamond_inventory/returns/create', [
            'title' => 'Create Diamond Return',
            'items' => $this->itemOptions(),
            'issueLines' => (new DiamondBagTraceService(db_connect()))->returnableIssueLines(),
            'issues' => $this->issueOptions(),
            'return' => null,
            'lines' => [],
            'action' => site_url('admin/diamond-inventory/returns'),
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
        $parsed = $this->collectLinesFromRequest(0);
        if ($parsed['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $parsed['error']);
        }
        if ($parsed['lines'] === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line is required.');
        }

        try {
            $db->transException(true)->transStart();

            $issueId = (int) $this->request->getPost('issue_id');
            $issue = $this->resolveSelectedIssue($issueId);
            if (! $issue) {
                throw new \RuntimeException('Selected issuance reference was not found.');
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
                    'issue_line_id' => (int) ($line['issue_line_id'] ?? 0) ?: null,
                    'bag_id' => (int) ($line['bag_id'] ?? 0) ?: null,
                    'bag_item_id' => (int) ($line['bag_item_id'] ?? 0) ?: null,
                    'allocation_order_id' => (int) ($line['allocation_order_id'] ?? 0) ?: null,
                    'pcs' => $line['pcs'],
                    'carat' => $line['carat'],
                    'rate_per_carat' => $line['rate_per_carat'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyReturn($returnId);
            (new DiamondBagTraceService($db))->applyReturn($returnId);
            (new KarigarMaterialAccountingService($db))->postInventoryHeader('diamond', 'return', $returnId);
            $db->transComplete();
            (new MobileNotificationEventService())->notifyInventoryTransactionCreated(
                'return', 'Diamond', 'return_headers', $returnId, 'admin'
            );
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
            ->select('rh.*, ih.voucher_no as issue_voucher_no, ih.issue_date, k.name as karigar_name')
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
            ->select('rh.*, ih.voucher_no as issue_voucher_no, ih.issue_date, ih.issue_to, k.name as karigar_name, k.phone as karigar_phone, k.email as karigar_email, k.address as karigar_address, k.city as karigar_city, k.state as karigar_state, k.pincode as karigar_pincode')
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
            'issueLines' => (new DiamondBagTraceService(db_connect()))->returnableIssueLines(0, $id),
            'issues' => $this->issueOptions(),
            'return' => $return,
            'lines' => $this->lineRows($id),
            'action' => site_url('admin/diamond-inventory/returns/' . $id . '/update'),
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
        $parsed = $this->collectLinesFromRequest($id);
        if ($parsed['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $parsed['error']);
        }
        if ($parsed['lines'] === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line is required.');
        }

        try {
            $db->transException(true)->transStart();
            (new KarigarMaterialAccountingService($db))->reverseHeaderVoucher('return_headers', $id, 'Diamond return updated', (int) session('admin_id'));
            $service->reverseReturn($id);
            (new DiamondBagTraceService($db))->reverseReturn($id);

            $issueId = (int) $this->request->getPost('issue_id');
            $issue = $this->resolveSelectedIssue($issueId);
            if (! $issue) {
                throw new \RuntimeException('Selected issuance reference was not found.');
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
                    'issue_line_id' => (int) ($line['issue_line_id'] ?? 0) ?: null,
                    'bag_id' => (int) ($line['bag_id'] ?? 0) ?: null,
                    'bag_item_id' => (int) ($line['bag_item_id'] ?? 0) ?: null,
                    'allocation_order_id' => (int) ($line['allocation_order_id'] ?? 0) ?: null,
                    'pcs' => $line['pcs'],
                    'carat' => $line['carat'],
                    'rate_per_carat' => $line['rate_per_carat'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyReturn($id);
            (new DiamondBagTraceService($db))->applyReturn($id);
            (new KarigarMaterialAccountingService($db))->postInventoryHeader('diamond', 'return', $id);
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
            (new KarigarMaterialAccountingService($db))->reverseHeaderVoucher('return_headers', $id, 'Diamond return deleted', (int) session('admin_id'));
            $service->reverseReturn($id);
            (new DiamondBagTraceService($db))->reverseReturn($id);
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
    private function collectLinesFromRequest(int $excludeReturnId = 0): array
    {
        $issueId = (int) $this->request->getPost('issue_id');
        $issueLineIds = (array) $this->request->getPost('issue_line_id');
        $pcs = (array) $this->request->getPost('pcs');
        $carats = (array) $this->request->getPost('carat');
        $rates = (array) $this->request->getPost('rate_per_carat');
        $max = max(count($issueLineIds), count($pcs), count($carats), count($rates));

        $availableRows = (new DiamondBagTraceService(db_connect()))
            ->returnableIssueLines($issueId, $excludeReturnId, true);
        $available = [];
        foreach ($availableRows as $row) {
            $available[(int) $row['issue_line_id']] = $row;
        }

        $lines = [];
        $requested = [];
        for ($i = 0; $i < $max; $i++) {
            $issueLineId = (int) ($issueLineIds[$i] ?? 0);
            $pcsValue = (float) ($pcs[$i] ?? 0);
            $caratValue = (float) ($carats[$i] ?? 0);
            $rateRaw = trim((string) ($rates[$i] ?? ''));

            $isBlank = $issueLineId <= 0 && $pcsValue <= 0 && $caratValue <= 0 && $rateRaw === '';
            if ($isBlank) {
                continue;
            }
            if ($issueLineId <= 0 || ! isset($available[$issueLineId])) {
                return ['lines' => [], 'error' => 'Select a valid line from the selected issue voucher.'];
            }
            if ($caratValue <= 0 || $pcsValue <= 0 || floor($pcsValue) !== $pcsValue) {
                return ['lines' => [], 'error' => 'Whole-number PCS and carat greater than zero are mandatory for every diamond return line.'];
            }

            $rateValue = $rateRaw === '' ? null : (float) $rateRaw;
            if ($rateValue !== null && $rateValue < 0) {
                return ['lines' => [], 'error' => 'Rate per carat cannot be negative.'];
            }

            $source = $available[$issueLineId];
            $requested[$issueLineId]['pcs'] = (float) ($requested[$issueLineId]['pcs'] ?? 0) + $pcsValue;
            $requested[$issueLineId]['carat'] = (float) ($requested[$issueLineId]['carat'] ?? 0) + $caratValue;
            if ($requested[$issueLineId]['pcs'] > ((float) $source['available_pcs'] + 0.0005)
                || $requested[$issueLineId]['carat'] > ((float) $source['available_cts'] + 0.0005)) {
                return ['lines' => [], 'error' => 'Return PCS/CTS exceeds the available balance for ' . (string) $source['label'] . '.'];
            }

            $lineValue = $rateValue === null ? null : round($caratValue * $rateValue, 2);
            $lines[] = [
                'item_id' => (int) $source['item_id'],
                'issue_line_id' => $issueLineId,
                'bag_id' => (int) ($source['bag_id'] ?? 0) ?: null,
                'bag_item_id' => (int) ($source['bag_item_id'] ?? 0) ?: null,
                'allocation_order_id' => (int) ($source['allocation_order_id'] ?? 0) ?: null,
                'pcs' => (int) $pcsValue,
                'carat' => round($caratValue, 3),
                'rate_per_carat' => $rateValue === null ? null : round($rateValue, 2),
                'line_value' => $lineValue,
            ];
        }

        return ['lines' => $lines, 'error' => null];
    }

    private function validateHeader()
    {
        if (! $this->validate([
            'return_date' => 'required|valid_date',
            'issue_id' => 'required|integer|greater_than[0]',
            'return_from' => 'permit_empty|max_length[120]',
            'purpose' => 'permit_empty|max_length[50]',
            'notes' => 'permit_empty',
        ])) {
            $errors = $this->validator ? $this->validator->getErrors() : [];
            return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
        }

        $issueId = (int) $this->request->getPost('issue_id');
        $issue = $this->resolveSelectedIssue($issueId);
        if (! $issue) {
            return 'Selected issuance reference is invalid.';
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
    private function issueOptions(): array
    {
        return db_connect()->table('issue_headers ih')
            ->select('ih.id, ih.issue_date, ih.voucher_no, ih.issue_to, ih.karigar_id, k.name as karigar_name')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
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
            ->select('rl.*, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, i.cut, b.bag_no, sm.name AS bag_shape, sz.size_label AS bag_size, o.order_no AS allocation_order_no')
            ->join('items i', 'i.id = rl.item_id', 'left')
            ->join('diamond_bags b', 'b.id = rl.bag_id', 'left')
            ->join('diamond_bag_items bi', 'bi.id = rl.bag_item_id', 'left')
            ->join('diamond_shape_masters sm', 'sm.id = bi.shape_master_id', 'left')
            ->join('diamond_size_masters sz', 'sz.id = bi.size_master_id', 'left')
            ->join('orders o', 'o.id = rl.allocation_order_id', 'left')
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
    private function resolveSelectedIssue(int $issueId)
    {
        if ($issueId <= 0) {
            return null;
        }

        $row = db_connect()->table('issue_headers ih')
            ->select('ih.id, ih.karigar_id, ih.issue_to, ih.voucher_no, ih.issue_date, k.name as karigar_name')
            ->join('karigars k', 'k.id = ih.karigar_id', 'left')
            ->where('ih.id', $issueId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }
}
