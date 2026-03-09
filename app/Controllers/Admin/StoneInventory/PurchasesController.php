<?php

namespace App\Controllers\Admin\StoneInventory;

use App\Controllers\BaseController;
use App\Models\StoneInventoryItemModel;
use App\Models\StoneInventoryPurchaseHeaderModel;
use App\Models\StoneInventoryPurchaseLineModel;
use App\Models\StonePurchaseAttachmentModel;
use App\Models\VendorModel;
use App\Services\StoneInventory\StockService;
use CodeIgniter\HTTP\Files\UploadedFile;
use Throwable;

class PurchasesController extends BaseController
{
    private StoneInventoryPurchaseHeaderModel $headerModel;
    private StoneInventoryPurchaseLineModel $lineModel;
    private StoneInventoryItemModel $itemModel;
    private VendorModel $vendorModel;
    private StonePurchaseAttachmentModel $attachmentModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->headerModel = new StoneInventoryPurchaseHeaderModel();
        $this->lineModel = new StoneInventoryPurchaseLineModel();
        $this->itemModel = new StoneInventoryItemModel();
        $this->vendorModel = new VendorModel();
        $this->attachmentModel = new StonePurchaseAttachmentModel();
    }

    public function index(): string
    {
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));

        $builder = db_connect()->table('stone_inventory_purchase_headers ph')
            ->select('ph.*, MAX(v.name) as vendor_name, COUNT(pl.id) as line_count, COALESCE(SUM(pl.qty), 0) as total_qty, COALESCE(SUM(pl.line_value), 0) as total_value', false)
            ->join('stone_inventory_purchase_lines pl', 'pl.purchase_id = ph.id', 'left')
            ->join('vendors v', 'v.id = ph.vendor_id', 'left')
            ->groupBy('ph.id')
            ->orderBy('ph.id', 'DESC');

        if ($from !== '') {
            $builder->where('ph.purchase_date >=', $from);
        }
        if ($to !== '') {
            $builder->where('ph.purchase_date <=', $to);
        }

        return view('admin/stone_inventory/purchases/index', [
            'title' => 'Stone Purchases',
            'purchases' => $builder->get()->getResultArray(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(): string
    {
        return view('admin/stone_inventory/purchases/create', [
            'title' => 'Create Stone Purchase',
            'items' => $this->itemOptions(),
            'vendors' => $this->vendorOptions(),
            'purchase' => null,
            'lines' => [],
            'attachments' => [],
            'action' => site_url('admin/stone-inventory/purchases'),
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
        $parsed = $this->collectLinesFromRequest(true);
        if ($parsed['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $parsed['error']);
        }
        if ($parsed['lines'] === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line is required.');
        }

        try {
            $db->transException(true)->transStart();

            $vendorId = (int) $this->request->getPost('vendor_id');
            $vendor = $this->vendorModel->where('is_active', 1)->find($vendorId);
            if (! $vendor) {
                throw new \RuntimeException('Please select a valid active vendor.');
            }
            $taxPercentage = round((float) ($this->request->getPost('tax_percentage') ?? 0), 3);
            $totals = $this->calculateInvoiceTotals($parsed['lines'], $taxPercentage);

            $purchaseId = (int) $this->headerModel->insert([
                'purchase_date' => (string) $this->request->getPost('purchase_date'),
                'vendor_id' => $vendorId,
                'supplier_name' => (string) ($vendor['name'] ?? ''),
                'invoice_no' => trim((string) $this->request->getPost('invoice_no')) ?: null,
                'due_date' => trim((string) $this->request->getPost('due_date')) ?: null,
                'tax_percentage' => $taxPercentage,
                'invoice_total' => $totals['invoice_total'],
                'notes' => trim((string) $this->request->getPost('notes')) ?: null,
            ], true);

            foreach ($parsed['lines'] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                if ($itemId <= 0) {
                    $itemId = $service->upsertItemFromSignature((array) ($line['signature'] ?? []));
                }

                $this->lineModel->insert([
                    'purchase_id' => $purchaseId,
                    'item_id' => $itemId,
                    'qty' => $line['qty'],
                    'rate' => $line['rate'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyPurchase($purchaseId);
            $uploadError = $this->saveAttachmentsFromRequest($purchaseId);
            if ($uploadError !== null) {
                throw new \RuntimeException($uploadError);
            }
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/stone-inventory/purchases'))
            ->with('success', 'Purchase saved and stock updated.');
    }

    public function view(int $id)
    {
        $purchase = db_connect()->table('stone_inventory_purchase_headers ph')
            ->select('ph.*, v.name as vendor_name')
            ->join('vendors v', 'v.id = ph.vendor_id', 'left')
            ->where('ph.id', $id)
            ->get()
            ->getRowArray();
        if (! $purchase) {
            return redirect()->to(site_url('admin/stone-inventory/purchases'))->with('error', 'Purchase not found.');
        }

        return view('admin/stone_inventory/purchases/view', [
            'title' => 'View Purchase',
            'purchase' => $purchase,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('stone_inventory_purchase_lines', 'purchase_id', $id),
            'attachments' => $this->attachmentRows($id),
        ]);
    }

    public function edit(int $id)
    {
        $purchase = $this->headerModel->find($id);
        if (! $purchase) {
            return redirect()->to(site_url('admin/stone-inventory/purchases'))->with('error', 'Purchase not found.');
        }

        return view('admin/stone_inventory/purchases/edit', [
            'title' => 'Edit Stone Purchase',
            'items' => $this->itemOptions(),
            'vendors' => $this->vendorOptions(),
            'purchase' => $purchase,
            'lines' => $this->lineRows($id),
            'attachments' => $this->attachmentRows($id),
            'action' => site_url('admin/stone-inventory/purchases/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        $purchase = $this->headerModel->find($id);
        if (! $purchase) {
            return redirect()->to(site_url('admin/stone-inventory/purchases'))->with('error', 'Purchase not found.');
        }

        $validationError = $this->validateHeader();
        if ($validationError !== null) {
            return redirect()->back()->withInput()->with('error', $validationError);
        }

        $db = db_connect();
        $service = new StockService($db);
        $parsed = $this->collectLinesFromRequest(true);
        if ($parsed['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $parsed['error']);
        }
        if ($parsed['lines'] === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line is required.');
        }

        try {
            $db->transException(true)->transStart();
            $service->reversePurchase($id);

            $vendorId = (int) $this->request->getPost('vendor_id');
            $vendor = $this->vendorModel->where('is_active', 1)->find($vendorId);
            if (! $vendor) {
                throw new \RuntimeException('Please select a valid active vendor.');
            }
            $taxPercentage = round((float) ($this->request->getPost('tax_percentage') ?? 0), 3);
            $totals = $this->calculateInvoiceTotals($parsed['lines'], $taxPercentage);

            $this->headerModel->update($id, [
                'purchase_date' => (string) $this->request->getPost('purchase_date'),
                'vendor_id' => $vendorId,
                'supplier_name' => (string) ($vendor['name'] ?? ''),
                'invoice_no' => trim((string) $this->request->getPost('invoice_no')) ?: null,
                'due_date' => trim((string) $this->request->getPost('due_date')) ?: null,
                'tax_percentage' => $taxPercentage,
                'invoice_total' => $totals['invoice_total'],
                'notes' => trim((string) $this->request->getPost('notes')) ?: null,
            ]);

            $this->lineModel->where('purchase_id', $id)->delete();
            foreach ($parsed['lines'] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                if ($itemId <= 0) {
                    $itemId = $service->upsertItemFromSignature((array) ($line['signature'] ?? []));
                }

                $this->lineModel->insert([
                    'purchase_id' => $id,
                    'item_id' => $itemId,
                    'qty' => $line['qty'],
                    'rate' => $line['rate'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyPurchase($id);
            $uploadError = $this->saveAttachmentsFromRequest($id);
            if ($uploadError !== null) {
                throw new \RuntimeException($uploadError);
            }
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/stone-inventory/purchases/view/' . $id))
            ->with('success', 'Purchase updated and stock recalculated.');
    }

    public function delete(int $id)
    {
        $purchase = $this->headerModel->find($id);
        if (! $purchase) {
            return redirect()->to(site_url('admin/stone-inventory/purchases'))->with('error', 'Purchase not found.');
        }

        $db = db_connect();
        $service = new StockService($db);

        try {
            $db->transException(true)->transStart();
            $service->reversePurchase($id);
            $this->deleteAttachmentsForPurchase($id);
            $this->lineModel->where('purchase_id', $id)->delete();
            $this->headerModel->delete($id);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->to(site_url('admin/stone-inventory/purchases'))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/stone-inventory/purchases'))
            ->with('success', 'Purchase deleted and stock reversed.');
    }

    /**
     * @return array{lines:list<array<string,mixed>>,error:?string}
     */
    private function collectLinesFromRequest(bool $rateRequired): array
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
            if ($rateRequired && $rateRaw === '') {
                return ['lines' => [], 'error' => 'Rate is required for purchase lines.'];
            }

            $rateValue = $rateRaw === '' ? 0.0 : (float) $rateRaw;
            if ($rateValue < 0) {
                return ['lines' => [], 'error' => 'Rate cannot be negative.'];
            }

            if ($itemId <= 0) {
                if ($productName === '') {
                    return ['lines' => [], 'error' => 'Product name is required when item is not selected.'];
                }
                $signature = [
                    'product_name' => $productName,
                    'stone_type' => $stoneType,
                    'default_rate' => $rateValue,
                ];
            } else {
                if (! $this->itemModel->find($itemId)) {
                    return ['lines' => [], 'error' => 'Selected item does not exist.'];
                }
                $signature = [];
            }

            $lines[] = [
                'item_id' => $itemId,
                'qty' => round($qtyValue, 3),
                'rate' => round($rateValue, 2),
                'line_value' => round($qtyValue * $rateValue, 2),
                'signature' => $signature,
            ];
        }

        return ['lines' => $lines, 'error' => null];
    }

    private function validateHeader(): ?string
    {
        if (! $this->validate([
            'purchase_date' => 'required|valid_date',
            'vendor_id' => 'required|integer|greater_than[0]',
            'invoice_no' => 'permit_empty|max_length[80]',
            'due_date' => 'permit_empty|valid_date',
            'tax_percentage' => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
            'invoice_total' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'notes' => 'permit_empty',
        ])) {
            $errors = $this->validator ? $this->validator->getErrors() : [];
            return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
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
    private function vendorOptions(): array
    {
        return $this->vendorModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function lineRows(int $purchaseId): array
    {
        return db_connect()->table('stone_inventory_purchase_lines pl')
            ->select('pl.*, i.product_name, i.stone_type')
            ->join('stone_inventory_items i', 'i.id = pl.item_id', 'left')
            ->where('pl.purchase_id', $purchaseId)
            ->orderBy('pl.id', 'ASC')
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
     * @param list<array<string,mixed>> $lines
     * @return array{subtotal:float,tax_value:float,invoice_total:float}
     */
    private function calculateInvoiceTotals(array $lines, float $taxPercentage): array
    {
        $subtotal = 0.0;
        foreach ($lines as $line) {
            $subtotal += (float) ($line['line_value'] ?? 0);
        }
        $taxPercentage = max(0, min(100, $taxPercentage));
        $taxValue = $subtotal * ($taxPercentage / 100);
        $invoiceTotal = $subtotal + $taxValue;

        return [
            'subtotal' => round($subtotal, 2),
            'tax_value' => round($taxValue, 2),
            'invoice_total' => round($invoiceTotal, 2),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function attachmentRows(int $purchaseId): array
    {
        if (! db_connect()->tableExists('stone_purchase_attachments')) {
            return [];
        }
        return $this->attachmentModel->where('purchase_id', $purchaseId)->orderBy('id', 'DESC')->findAll();
    }

    private function saveAttachmentsFromRequest(int $purchaseId): ?string
    {
        $files = $this->request->getFiles();
        $incoming = $files['attachments'] ?? null;
        if ($incoming === null) {
            return null;
        }

        $attachments = is_array($incoming) ? $incoming : ($incoming instanceof UploadedFile ? [$incoming] : []);
        $hasActualFile = false;
        foreach ($attachments as $file) {
            if ($file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                $hasActualFile = true;
                break;
            }
        }
        if (! $hasActualFile) {
            return null;
        }
        if (! db_connect()->tableExists('stone_purchase_attachments')) {
            return 'Attachment table missing. Please run migration.';
        }

        $uploadDir = FCPATH . 'uploads/stone-purchases';
        if (! is_dir($uploadDir) && ! mkdir($uploadDir, 0775, true) && ! is_dir($uploadDir)) {
            return 'Unable to create upload directory.';
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];
        foreach ($attachments as $file) {
            if (! $file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (! $file->isValid()) {
                return 'Invalid attachment uploaded.';
            }
            if ($file->getSizeByUnit('kb') > 10240) {
                return 'Attachment size must be 10MB or less.';
            }

            $ext = strtolower((string) $file->getExtension());
            if ($ext === '' || ! in_array($ext, $allowedExt, true)) {
                return 'Attachment type is not allowed.';
            }

            $newName = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
            $file->move($uploadDir, $newName);

            $this->attachmentModel->insert([
                'purchase_id' => $purchaseId,
                'file_name' => (string) $file->getClientName(),
                'file_path' => 'uploads/stone-purchases/' . $newName,
                'mime_type' => (string) $file->getClientMimeType(),
                'file_size' => (int) $file->getSize(),
                'uploaded_by' => (int) session('admin_id'),
            ]);
        }

        return null;
    }

    private function deleteAttachmentsForPurchase(int $purchaseId): void
    {
        if (! db_connect()->tableExists('stone_purchase_attachments')) {
            return;
        }
        $rows = $this->attachmentModel->where('purchase_id', $purchaseId)->findAll();
        foreach ($rows as $row) {
            $path = trim((string) ($row['file_path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $full = FCPATH . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
            if (is_file($full)) {
                @unlink($full);
            }
        }
        $this->attachmentModel->where('purchase_id', $purchaseId)->delete();
    }
}

