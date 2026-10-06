<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\DiamondBagItemModel;
use App\Models\DiamondBagModel;
use App\Models\InventoryLocationModel;
use App\Services\AdminPostingService;
use App\Services\DiamondBagService;
use App\Services\DiamondChalniStockService;
use RuntimeException;
use Throwable;

class DiamondBagController extends BaseController
{
    private DiamondBagModel $bagModel;
    private DiamondBagItemModel $bagItemModel;
    private InventoryLocationModel $locationModel;
    private AdminPostingService $adminPostingService;
    private DiamondBagService $diamondBagService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->bagModel = new DiamondBagModel();
        $this->bagItemModel = new DiamondBagItemModel();
        $this->locationModel = new InventoryLocationModel();
        $this->adminPostingService = new AdminPostingService();
        $this->diamondBagService = new DiamondBagService();
    }

    public function index(): string
    {
        $bags = db_connect()->table('diamond_bags b')
            ->select('b.*, COUNT(DISTINCT bi.id) AS item_count, COUNT(DISTINCT il.allocation_order_id) AS order_count, COUNT(DISTINCT il.id) AS issue_line_count', false)
            ->join('diamond_bag_items bi', 'bi.bag_id = b.id', 'left')
            ->join('issue_lines il', 'il.bag_id = b.id', 'left')
            ->groupBy('b.id')->orderBy('b.id', 'DESC')->get()->getResultArray();

        return view('admin/diamond_bags/index', ['title' => 'Diamond Bags', 'bags' => $bags]);
    }

    public function create(): string
    {
        return view('admin/diamond_bags/create', $this->formData(null));
    }

    public function store()
    {
        if (! $this->validate([
            'prepared_date' => 'required|valid_date',
            'order_id' => 'required|integer|greater_than[0]',
            'location_id' => 'required|integer|greater_than[0]',
            'audit_image' => 'permit_empty|is_image[audit_image]|max_size[audit_image,4096]',
        ])) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }
        $locationId = (int) $this->request->getPost('location_id');
        $orderId = (int) $this->request->getPost('order_id');
        if (! $this->diamondBagService->canCreateForOrder($orderId, (int) session('admin_id'), true)) {
            return redirect()->back()->withInput()->with('error', 'Select a valid open Diamond or Jadau order.');
        }
        if (! $this->locationModel->where('is_active', 1)->find($locationId)) {
            return redirect()->back()->withInput()->with('error', 'Select a valid inventory location.');
        }
        $parsed = $this->collectBagItemsFromRequest();
        if ($parsed['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $parsed['error']);
        }

        $db = db_connect();
        try {
            $db->transException(true)->transStart();
            $this->assertPackable($parsed['rows']);
            $image = $this->storeAuditImage();
            $warehouse = $this->adminPostingService->resolveWarehouseBinByLocation($locationId);
            $bagId = (int) $this->bagModel->insert([
                'bag_no' => $this->nextBagNumber(),
                'prepared_date' => (string) $this->request->getPost('prepared_date'),
                'order_id' => $orderId,
                'warehouse_id' => (int) $warehouse['warehouse_id'],
                'bin_id' => (int) $warehouse['bin_id'],
                'pcs_balance' => 0,
                'cts_balance' => 0,
                'notes' => trim((string) $this->request->getPost('notes')) ?: null,
                'audit_image_name' => $image['name'],
                'audit_image_path' => $image['path'],
                'created_by' => (int) session('admin_id') ?: null,
            ], true);
            $this->replaceBagItems($bagId, $parsed['rows']);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-inventory/bags/' . $bagId))
            ->with('success', 'Diamond bag prepared. It can now be split across multiple order allocations during issuance.');
    }

    public function edit(int $id): string
    {
        $bag = $this->bagModel->find($id);
        if (! $bag) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Bag not found.');
        }
        if ($this->bagHasIssue($id)) {
            return redirect()->to(site_url('admin/diamond-inventory/bags/' . $id))->with('error', 'A bag cannot be edited after any quantity has been issued.');
        }
        return view('admin/diamond_bags/edit', $this->formData($bag));
    }

    public function update(int $id)
    {
        $bag = $this->bagModel->find($id);
        if (! $bag) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Bag not found.');
        }
        if ($this->bagHasIssue($id)) {
            return redirect()->to(site_url('admin/diamond-inventory/bags/' . $id))->with('error', 'Issued bag cannot be edited.');
        }
        if (! $this->validate([
            'prepared_date' => 'required|valid_date',
            'order_id' => 'required|integer|greater_than[0]',
            'location_id' => 'required|integer|greater_than[0]',
            'audit_image' => 'permit_empty|is_image[audit_image]|max_size[audit_image,4096]',
        ])) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }
        $locationId = (int) $this->request->getPost('location_id');
        $orderId = (int) $this->request->getPost('order_id');
        if (! $this->diamondBagService->canCreateForOrder($orderId, (int) session('admin_id'), true)) {
            return redirect()->back()->withInput()->with('error', 'Select a valid open Diamond or Jadau order.');
        }
        if (! $this->locationModel->where('is_active', 1)->find($locationId)) {
            return redirect()->back()->withInput()->with('error', 'Select a valid inventory location.');
        }
        $parsed = $this->collectBagItemsFromRequest();
        if ($parsed['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $parsed['error']);
        }

        $db = db_connect();
        try {
            $db->transException(true)->transStart();
            $this->assertPackable($parsed['rows'], $id);
            $image = $this->storeAuditImage((string) ($bag['audit_image_path'] ?? ''), (string) ($bag['audit_image_name'] ?? ''));
            $warehouse = $this->adminPostingService->resolveWarehouseBinByLocation($locationId);
            $this->bagModel->update($id, [
                'prepared_date' => (string) $this->request->getPost('prepared_date'),
                'order_id' => $orderId,
                'warehouse_id' => (int) $warehouse['warehouse_id'],
                'bin_id' => (int) $warehouse['bin_id'],
                'notes' => trim((string) $this->request->getPost('notes')) ?: null,
                'audit_image_name' => $image['name'],
                'audit_image_path' => $image['path'],
            ]);
            $this->replaceBagItems($id, $parsed['rows']);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-inventory/bags/' . $id))->with('success', 'Diamond bag updated.');
    }

    public function delete(int $id)
    {
        $bag = $this->bagModel->find($id);
        if (! $bag) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Bag not found.');
        }
        if ((int) ($bag['requirement_id'] ?? 0) > 0) {
            return redirect()->to(site_url('admin/diamond-inventory/bags/' . $id))
                ->with('error', 'An approved requirement bag cannot be deleted from the generic bag register.');
        }
        if ($this->bagHasIssue($id)) {
            return redirect()->to(site_url('admin/diamond-inventory/bags/' . $id))->with('error', 'Issued bag cannot be deleted.');
        }

        $db = db_connect();
        try {
            $db->transException(true)->transStart();
            $this->bagItemModel->where('bag_id', $id)->delete();
            $this->bagModel->delete($id);
            $db->transComplete();
            $this->deleteFile((string) ($bag['audit_image_path'] ?? ''));
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-inventory/bags'))->with('success', 'Unissued bag deleted.');
    }

    public function show(int $id): string
    {
        $bag = $this->bagModel->find($id);
        if (! $bag) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Bag not found.');
        }
        $db = db_connect();
        $items = $db->table('diamond_bag_items bi')
            ->select('bi.*, i.diamond_type, i.color, i.clarity, i.cut, sm.name AS shape_name, sz.size_code, sz.size_label')
            ->join('items i', 'i.id = bi.inventory_item_id', 'left')
            ->join('diamond_shape_masters sm', 'sm.id = bi.shape_master_id', 'left')
            ->join('diamond_size_masters sz', 'sz.id = bi.size_master_id', 'left')
            ->where('bi.bag_id', $id)->orderBy('bi.id', 'ASC')->get()->getResultArray();
        $movements = $db->table('diamond_bag_movements bm')
            ->select('bm.*, ih.voucher_no, o.order_no, k.name AS karigar_name, i.diamond_type, sm.name AS shape_name, sz.size_label')
            ->join('issue_lines il', 'il.id = bm.issue_line_id', 'left')
            ->join('issue_headers ih', 'ih.id = il.issue_id', 'left')
            ->join('diamond_bag_items bi', 'bi.id = bm.bag_item_id', 'left')
            ->join('items i', 'i.id = bi.inventory_item_id', 'left')
            ->join('diamond_shape_masters sm', 'sm.id = bi.shape_master_id', 'left')
            ->join('diamond_size_masters sz', 'sz.id = bi.size_master_id', 'left')
            ->join('orders o', 'o.id = bm.order_id', 'left')
            ->join('karigars k', 'k.id = bm.karigar_id', 'left')
            ->where('bm.bag_id', $id)->orderBy('bm.id', 'DESC')->get()->getResultArray();

        $bag['has_issue'] = $this->bagHasIssue($id);
        return view('admin/diamond_bags/show', ['title' => 'Diamond Bag Details', 'bag' => $bag, 'items' => $items, 'movements' => $movements]);
    }

    /** @param array<string,mixed>|null $bag */
    private function formData(?array $bag): array
    {
        $db = db_connect();
        return [
            'title' => $bag ? 'Edit Diamond Bag' : 'Prepare Diamond Bag',
            'bag' => $bag,
            'items' => $bag ? $this->bagItemModel->where('bag_id', (int) $bag['id'])->orderBy('id', 'ASC')->findAll() : [],
            'inventoryItems' => $db->table('items i')
                ->select('i.*, COALESCE(s.pcs_balance,0) AS pcs_balance, COALESCE(s.carat_balance,0) AS carat_balance, COALESCE(s.avg_cost_per_carat,0) AS avg_cost_per_carat', false)
                ->join('stock s', 's.item_id = i.id', 'left')->orderBy('i.diamond_type', 'ASC')->get()->getResultArray(),
            'shapes' => $db->table('diamond_shape_masters')->where('is_active', 1)->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->get()->getResultArray(),
            'sizes' => $db->table('diamond_size_masters')->where('is_active', 1)->orderBy('sort_order', 'ASC')->orderBy('size_label', 'ASC')->get()->getResultArray(),
            'locations' => $this->locationModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'orders' => $this->diamondBagService->availableOrders((int) session('admin_id'), true),
            'selectedOrderId' => $bag
                ? (int) ($bag['order_id'] ?? 0)
                : (int) ($this->request->getGet('order_id') ?? 0),
            'selectedLocationId' => $bag ? $this->resolveLocationIdForBag((int) ($bag['warehouse_id'] ?? 0)) : null,
        ];
    }

    /** @return array{rows:list<array<string,mixed>>,error:?string} */
    private function collectBagItemsFromRequest(): array
    {
        $itemIds = (array) $this->request->getPost('inventory_item_id');
        $shapeIds = (array) $this->request->getPost('shape_master_id');
        $sizeIds = (array) $this->request->getPost('size_master_id');
        $pcsList = (array) $this->request->getPost('pcs');
        $weights = (array) $this->request->getPost('weight_cts');
        $max = max(count($itemIds), count($shapeIds), count($sizeIds), count($pcsList), count($weights));
        $rows = [];
        $db = db_connect();

        for ($i = 0; $i < $max; $i++) {
            $itemId = (int) ($itemIds[$i] ?? 0);
            $shapeId = (int) ($shapeIds[$i] ?? 0);
            $sizeId = (int) ($sizeIds[$i] ?? 0);
            $pcs = (float) ($pcsList[$i] ?? 0);
            $cts = (float) ($weights[$i] ?? 0);
            if ($itemId <= 0 && $shapeId <= 0 && $sizeId <= 0 && $pcs <= 0 && $cts <= 0) {
                continue;
            }
            if ($itemId <= 0 || $shapeId <= 0 || $sizeId <= 0 || $pcs <= 0 || floor($pcs) !== $pcs || $cts <= 0) {
                return ['rows' => [], 'error' => 'Every bag row requires Diamond Item, Shape, whole-number PCS and positive CTS.'];
            }
            $item = $db->table('items')->where('id', $itemId)->get()->getRowArray();
            $shape = $db->table('diamond_shape_masters')->where('id', $shapeId)->where('is_active', 1)->get()->getRowArray();
            $size = $db->table('diamond_size_masters')->where('id', $sizeId)->where('shape_id', $shapeId)->where('is_active', 1)->get()->getRowArray();
            if (! $item || ! $shape || ! $size) {
                return ['rows' => [], 'error' => 'Selected diamond item, shape or shape-wise size is invalid.'];
            }
            $rows[] = [
                'inventory_item_id' => $itemId, 'shape_master_id' => $shapeId, 'size_master_id' => $sizeId,
                'diamond_type' => (string) ($item['diamond_type'] ?? 'Diamond'),
                'size' => (string) (($size['size_label'] ?? '') ?: $size['size_code']),
                'color' => (string) (($item['color'] ?? '') ?: '-'), 'quality' => (string) (($item['clarity'] ?? '') ?: '-'),
                'pcs' => (int) $pcs, 'weight_cts' => round($cts, 3),
            ];
        }
        if ($rows === []) {
            return ['rows' => [], 'error' => 'Add at least one complete diamond bag row.'];
        }
        return ['rows' => $rows, 'error' => null];
    }

    /** @param list<array<string,mixed>> $rows */
    private function assertPackable(array $rows, int $excludeBagId = 0): void
    {
        $requested = [];
        foreach ($rows as $row) {
            $itemId = (int) $row['inventory_item_id'];
            $requested[$itemId] = round((float) ($requested[$itemId] ?? 0) + (float) $row['weight_cts'], 3);
        }
        foreach ($requested as $itemId => $cts) {
            $stock = db_connect()->table('stock')->select('carat_balance')->where('item_id', $itemId)->get()->getRowArray();
            $reservedBuilder = db_connect()->table('diamond_bag_items')->select('COALESCE(SUM(weight_cts_available),0) AS cts', false)->where('inventory_item_id', $itemId);
            if ($excludeBagId > 0) {
                $reservedBuilder->where('bag_id !=', $excludeBagId);
            }
            $reserved = $reservedBuilder->get()->getRowArray();
            $available = round((float) ($stock['carat_balance'] ?? 0) - (float) ($reserved['cts'] ?? 0), 3);
            if ($cts > ($available + 0.0005)) {
                throw new RuntimeException('Bag quantity exceeds unbagged diamond stock. Available: ' . number_format(max(0, $available), 3) . ' cts.');
            }
        }
        (new DiamondChalniStockService(db_connect()))->assertPackable($rows, $excludeBagId);
    }

    /** @param list<array<string,mixed>> $rows */
    private function replaceBagItems(int $bagId, array $rows): void
    {
        $this->bagItemModel->where('bag_id', $bagId)->delete();
        $pcs = 0.0;
        $cts = 0.0;
        foreach ($rows as $row) {
            $this->bagItemModel->insert([
                'bag_id' => $bagId, 'inventory_item_id' => $row['inventory_item_id'],
                'shape_master_id' => $row['shape_master_id'], 'size_master_id' => $row['size_master_id'],
                'diamond_type' => $row['diamond_type'], 'size' => $row['size'], 'color' => $row['color'], 'quality' => $row['quality'],
                'pcs_total' => $row['pcs'], 'weight_cts_total' => $row['weight_cts'],
                'pcs_available' => $row['pcs'], 'weight_cts_available' => $row['weight_cts'],
            ]);
            $pcs += (float) $row['pcs'];
            $cts += (float) $row['weight_cts'];
        }
        $this->bagModel->update($bagId, ['pcs_balance' => round($pcs, 3), 'cts_balance' => round($cts, 3)]);
    }

    private function bagHasIssue(int $bagId): bool
    {
        return db_connect()->table('issue_lines')->where('bag_id', $bagId)->countAllResults() > 0;
    }

    private function nextBagNumber(): string
    {
        $prefix = 'DBAG-' . date('ymd') . '-';
        $rows = $this->bagModel->select('bag_no')->like('bag_no', $prefix, 'after')->findAll();
        $max = 0;
        foreach ($rows as $row) {
            if (preg_match('/(\d+)$/', (string) ($row['bag_no'] ?? ''), $match) === 1) {
                $max = max($max, (int) $match[1]);
            }
        }
        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** @return array{name:?string,path:?string} */
    private function storeAuditImage(string $existingPath = '', string $existingName = ''): array
    {
        $file = $this->request->getFile('audit_image');
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return ['name' => $existingName ?: null, 'path' => $existingPath ?: null];
        }
        if (! $file->isValid()) {
            throw new RuntimeException('Bag audit image is invalid.');
        }
        $dir = FCPATH . 'uploads/diamond-bags';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = $file->getRandomName();
        $original = $file->getClientName();
        $file->move($dir, $name);
        $this->deleteFile($existingPath);
        return ['name' => $original, 'path' => 'uploads/diamond-bags/' . $name];
    }

    private function deleteFile(string $path): void
    {
        if ($path !== '' && is_file(FCPATH . ltrim($path, '/'))) {
            @unlink(FCPATH . ltrim($path, '/'));
        }
    }

    private function resolveLocationIdForBag(int $warehouseId): ?int
    {
        if ($warehouseId <= 0) {
            return null;
        }
        $warehouse = db_connect()->table('warehouses')->where('id', $warehouseId)->get()->getRowArray();
        if (! $warehouse || preg_match('/^LOC-(\d+)$/', strtoupper((string) ($warehouse['warehouse_code'] ?? '')), $match) !== 1) {
            return null;
        }
        return (int) $match[1] ?: null;
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
