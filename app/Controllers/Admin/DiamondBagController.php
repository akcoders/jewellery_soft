<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\DiamondBagItemModel;
use App\Models\DiamondBagModel;
use App\Models\InventoryItemModel;
use App\Models\InventoryLocationModel;
use App\Models\OrderAttachmentModel;
use App\Models\OrderModel;
use App\Services\AdminPostingService;
use App\Services\PostingService;
use Exception;
use Throwable;

class DiamondBagController extends BaseController
{
    private DiamondBagModel $bagModel;
    private DiamondBagItemModel $bagItemModel;
    private OrderModel $orderModel;
    private InventoryItemModel $inventoryModel;
    private InventoryLocationModel $locationModel;
    private OrderAttachmentModel $attachmentModel;
    private AdminPostingService $adminPostingService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->bagModel = new DiamondBagModel();
        $this->bagItemModel = new DiamondBagItemModel();
        $this->orderModel = new OrderModel();
        $this->inventoryModel = new InventoryItemModel();
        $this->locationModel = new InventoryLocationModel();
        $this->attachmentModel = new OrderAttachmentModel();
        $this->adminPostingService = new AdminPostingService();
    }

    public function index(): string
    {
        $bags = $this->bagModel
            ->select('diamond_bags.*, orders.order_no')
            ->join('orders', 'orders.id = diamond_bags.order_id', 'left')
            ->orderBy('diamond_bags.id', 'DESC')
            ->findAll();

        $bagIds = array_map(static fn(array $r): int => (int) ($r['id'] ?? 0), $bags);
        $issuedIds = $this->issuedBagIds($bagIds);
        foreach ($bags as &$bag) {
            $bag['has_issue'] = isset($issuedIds[(int) ($bag['id'] ?? 0)]);
        }
        unset($bag);

        return view('admin/diamond_bags/index', [
            'title' => 'Diamond Bagging',
            'bags'  => $bags,
        ]);
    }

    public function create(): string
    {
        return view('admin/diamond_bags/create', [
            'title'  => 'Create Diamond Bag',
            'orders' => $this->orderModel->orderBy('id', 'DESC')->findAll(),
            'locations' => $this->locationModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'diamondTypeOptions' => $this->distinctInventoryValues('material_name'),
            'sizeOptions' => $this->distinctInventoryValues('diamond_sieve'),
            'itemTypeOptions' => $this->distinctInventoryValues('diamond_shape'),
            'colorOptions' => $this->distinctInventoryValues('diamond_color'),
            'qualityOptions' => $this->distinctInventoryValues('diamond_clarity'),
        ]);
    }

    public function store()
    {
        $adminId = $this->currentAuditUserId();
        if ($adminId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Audit user is required. Please login again.');
        }

        $rules = [
            'order_id' => 'required|integer',
            'location_id' => 'required|integer',
            'audit_image' => 'uploaded[audit_image]|is_image[audit_image]|max_size[audit_image,4096]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $orderId = (int) $this->request->getPost('order_id');
        $locationId = (int) $this->request->getPost('location_id');
        $order = $this->orderModel->find($orderId);
        if (! $order) {
            return redirect()->back()->withInput()->with('error', 'Order not found.');
        }
        if (! $this->locationModel->where('is_active', 1)->find($locationId)) {
            return redirect()->back()->withInput()->with('error', 'Location not found.');
        }

        $items = $this->collectBagItemsFromRequest();
        if ($items === []) {
            return redirect()->back()->withInput()->with('error', 'At least one diamond row is required.');
        }

        $db = \Config\Database::connect();
        try {
            $db->transException(true)->transStart();

            $bagNo = 'BAG' . date('ymdHis') . random_int(10, 99);
            $notes = trim((string) $this->request->getPost('notes'));
            $warehouseInfo = $this->adminPostingService->resolveWarehouseBinByLocation($locationId);

            $totalPcs = 0.0;
            $totalCts = 0.0;
            $first = $items[0];

            $bagId = $this->bagModel->insert([
                'bag_no'     => $bagNo,
                'order_id'   => $orderId,
                'warehouse_id' => (int) $warehouseInfo['warehouse_id'],
                'bin_id' => (int) $warehouseInfo['bin_id'],
                'shape' => $first['diamond_type'],
                'chalni_size' => $first['size'],
                'color' => $first['color'],
                'clarity' => $first['quality'],
                'pcs_balance' => 0,
                'cts_balance' => 0,
                'notes'      => $notes,
                'created_by' => $adminId,
            ], true);

            foreach ($items as $row) {
                $this->bagItemModel->insert([
                    'bag_id'                => (int) $bagId,
                    'diamond_type'          => $row['diamond_type'],
                    'size'                  => $row['size'],
                    'color'                 => $row['color'],
                    'quality'               => $row['quality'],
                    'pcs_total'             => $row['pcs'],
                    'weight_cts_total'      => $row['weight_cts'],
                    'pcs_available'         => $row['pcs'],
                    'weight_cts_available'  => $row['weight_cts'],
                ]);
                $totalPcs += (float) $row['pcs'];
                $totalCts += (float) $row['weight_cts'];
            }

            $this->bagModel->update((int) $bagId, [
                'pcs_balance' => round($totalPcs, 3),
                'cts_balance' => round($totalCts, 3),
            ]);

            $posting = new PostingService($db);
            $warehouseAccId = $posting->ensureAccount(
                'WAREHOUSE',
                'WH-' . $warehouseInfo['warehouse_id'],
                (string) $warehouseInfo['warehouse_name'] . ' Warehouse',
                'warehouses',
                (int) $warehouseInfo['warehouse_id']
            );
            $sortingAccId = $posting->ensureAccount('PROCESS', 'DIAMOND_SORTING', 'Diamond Sorting Pool');

            $posting->postVoucher([
                'voucher_type' => 'DIAMOND_BAG_CREATE',
                'voucher_date' => date('Y-m-d'),
                'to_warehouse_id' => (int) $warehouseInfo['warehouse_id'],
                'to_bin_id' => (int) $warehouseInfo['bin_id'],
                'order_id' => $orderId,
                'debit_account_id' => $warehouseAccId,
                'credit_account_id' => $sortingAccId,
                'remarks' => 'Bag ' . $bagNo . ' created for order ' . $order['order_no'],
                'created_by' => $adminId,
            ], [[
                'item_type' => 'DIAMOND_BAG',
                'item_key' => 'BAG-' . (int) $bagId,
                'material_name' => 'Diamond Bag ' . $bagNo,
                'bag_id' => (int) $bagId,
                'shape' => $first['diamond_type'],
                'chalni_size' => $first['size'],
                'color' => $first['color'],
                'clarity' => $first['quality'],
                'qty_pcs' => round($totalPcs, 3),
                'qty_cts' => round($totalCts, 3),
                'qty_weight' => 0,
                'remarks' => $notes,
            ]]);

            $this->storeAuditImageAttachment($orderId, 'audit_image', 'diamond_bag_create_audit', $adminId);

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-bags/' . $bagId))->with('success', 'Diamond bag created. Status: Not Issued.');
    }

    public function edit(int $id): string
    {
        $bag = $this->bagModel
            ->select('diamond_bags.*, orders.order_no')
            ->join('orders', 'orders.id = diamond_bags.order_id', 'left')
            ->find($id);

        if (! $bag) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Bag not found.');
        }
        if ($this->bagHasIssue($id)) {
            return redirect()->to(site_url('admin/diamond-bags/' . $id))
                ->with('error', 'Bag cannot be edited after issue.');
        }

        return view('admin/diamond_bags/edit', [
            'title'  => 'Edit Diamond Bag',
            'bag'    => $bag,
            'items'  => $this->bagItemModel->where('bag_id', $id)->orderBy('id', 'ASC')->findAll(),
            'orders' => $this->orderModel->orderBy('id', 'DESC')->findAll(),
            'locations' => $this->locationModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'selectedLocationId' => $this->resolveLocationIdForBag((int) ($bag['warehouse_id'] ?? 0)),
            'diamondTypeOptions' => $this->distinctInventoryValues('material_name'),
            'sizeOptions' => $this->distinctInventoryValues('diamond_sieve'),
            'itemTypeOptions' => $this->distinctInventoryValues('diamond_shape'),
            'colorOptions' => $this->distinctInventoryValues('diamond_color'),
            'qualityOptions' => $this->distinctInventoryValues('diamond_clarity'),
        ]);
    }

    public function update(int $id)
    {
        $bag = $this->bagModel->find($id);
        if (! $bag) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Bag not found.');
        }
        if ($this->bagHasIssue($id)) {
            return redirect()->to(site_url('admin/diamond-bags/' . $id))
                ->with('error', 'Bag cannot be edited after issue.');
        }

        $rules = [
            'order_id' => 'required|integer',
            'location_id' => 'required|integer',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $orderId = (int) $this->request->getPost('order_id');
        $locationId = (int) $this->request->getPost('location_id');
        $order = $this->orderModel->find($orderId);
        if (! $order) {
            return redirect()->back()->withInput()->with('error', 'Order not found.');
        }
        if (! $this->locationModel->where('is_active', 1)->find($locationId)) {
            return redirect()->back()->withInput()->with('error', 'Location not found.');
        }

        $items = $this->collectBagItemsFromRequest();
        if ($items === []) {
            return redirect()->back()->withInput()->with('error', 'At least one diamond row is required.');
        }

        $db = \Config\Database::connect();
        try {
            $db->transException(true)->transStart();

            $notes = trim((string) $this->request->getPost('notes'));
            $warehouseInfo = $this->adminPostingService->resolveWarehouseBinByLocation($locationId);
            $first = $items[0];
            $totalPcs = 0.0;
            $totalCts = 0.0;

            $this->bagItemModel->where('bag_id', $id)->delete();
            foreach ($items as $row) {
                $this->bagItemModel->insert([
                    'bag_id'               => $id,
                    'diamond_type'         => $row['diamond_type'],
                    'size'                 => $row['size'],
                    'color'                => $row['color'],
                    'quality'              => $row['quality'],
                    'pcs_total'            => $row['pcs'],
                    'weight_cts_total'     => $row['weight_cts'],
                    'pcs_available'        => $row['pcs'],
                    'weight_cts_available' => $row['weight_cts'],
                ]);
                $totalPcs += (float) $row['pcs'];
                $totalCts += (float) $row['weight_cts'];
            }

            $this->bagModel->update($id, [
                'order_id' => $orderId,
                'warehouse_id' => (int) $warehouseInfo['warehouse_id'],
                'bin_id' => (int) $warehouseInfo['bin_id'],
                'shape' => $first['diamond_type'],
                'chalni_size' => $first['size'],
                'color' => $first['color'],
                'clarity' => $first['quality'],
                'pcs_balance' => round($totalPcs, 3),
                'cts_balance' => round($totalCts, 3),
                'notes' => $notes,
            ]);

            $posting = new PostingService($db);
            $warehouseAccId = $posting->ensureAccount(
                'WAREHOUSE',
                'WH-' . $warehouseInfo['warehouse_id'],
                (string) $warehouseInfo['warehouse_name'] . ' Warehouse',
                'warehouses',
                (int) $warehouseInfo['warehouse_id']
            );
            $sortingAccId = $posting->ensureAccount('PROCESS', 'DIAMOND_SORTING', 'Diamond Sorting Pool');

            $header = [
                'voucher_type' => 'DIAMOND_BAG_CREATE',
                'voucher_date' => date('Y-m-d'),
                'to_warehouse_id' => (int) $warehouseInfo['warehouse_id'],
                'to_bin_id' => (int) $warehouseInfo['bin_id'],
                'order_id' => $orderId,
                'debit_account_id' => $warehouseAccId,
                'credit_account_id' => $sortingAccId,
                'remarks' => 'Bag ' . $bag['bag_no'] . ' created for order ' . $order['order_no'],
                'created_by' => (int) session('admin_id'),
            ];
            $lines = [[
                'item_type' => 'DIAMOND_BAG',
                'item_key' => 'BAG-' . $id,
                'material_name' => 'Diamond Bag ' . $bag['bag_no'],
                'bag_id' => $id,
                'shape' => $first['diamond_type'],
                'chalni_size' => $first['size'],
                'color' => $first['color'],
                'clarity' => $first['quality'],
                'qty_pcs' => round($totalPcs, 3),
                'qty_cts' => round($totalCts, 3),
                'qty_weight' => 0,
                'remarks' => $notes,
            ]];

            $existingCreateVoucherId = $this->findActiveBagCreateVoucherId($id);
            if ($existingCreateVoucherId > 0) {
                $posting->reverseAndRepost(
                    $existingCreateVoucherId,
                    'Diamond bag edited before issue',
                    $header,
                    $lines
                );
            } else {
                $posting->postVoucher($header, $lines);
            }

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-bags/' . $id))->with('success', 'Diamond bag updated.');
    }

    public function delete(int $id)
    {
        $bag = $this->bagModel->find($id);
        if (! $bag) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Bag not found.');
        }
        if ($this->bagHasIssue($id)) {
            return redirect()->to(site_url('admin/diamond-bags/' . $id))
                ->with('error', 'Bag cannot be deleted after issue.');
        }

        $db = \Config\Database::connect();
        try {
            $db->transException(true)->transStart();

            $existingCreateVoucherId = $this->findActiveBagCreateVoucherId($id);
            if ($existingCreateVoucherId > 0) {
                $posting = new PostingService($db);
                $posting->reverseVoucher(
                    $existingCreateVoucherId,
                    'Diamond bag deleted before issue',
                    (int) session('admin_id'),
                    true
                );
            }

            $this->bagItemModel->where('bag_id', $id)->delete();
            $this->bagModel->delete($id);

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-bags'))->with('success', 'Diamond bag deleted.');
    }

    public function show(int $id): string
    {
        $bag = $this->bagModel
            ->select('diamond_bags.*, orders.order_no')
            ->join('orders', 'orders.id = diamond_bags.order_id', 'left')
            ->find($id);

        if (! $bag) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Bag not found.');
        }

        $bag['has_issue'] = $this->bagHasIssue($id);

        return view('admin/diamond_bags/show', [
            'title' => 'Diamond Bag Details',
            'bag'   => $bag,
            'items' => $this->bagItemModel->where('bag_id', $id)->findAll(),
        ]);
    }

    /**
     * @param list<int> $bagIds
     * @return array<int, true>
     */
    private function issuedBagIds(array $bagIds): array
    {
        $ids = [];
        if ($bagIds === []) {
            return $ids;
        }

        $db = db_connect();

        if ($db->tableExists('voucher_lines') && $db->tableExists('vouchers')) {
            $rows = $db->table('voucher_lines vl')
                ->select('DISTINCT vl.bag_id as bag_id', false)
                ->join('vouchers v', 'v.id = vl.voucher_id', 'inner')
                ->whereIn('vl.bag_id', $bagIds)
                ->where('v.voucher_type', 'DIAMOND_BAG_ISSUE')
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $bid = (int) ($row['bag_id'] ?? 0);
                if ($bid > 0) {
                    $ids[$bid] = true;
                }
            }
        }

        if ($db->tableExists('diamond_ledger_entries')) {
            $rows = $db->table('diamond_ledger_entries')
                ->select('DISTINCT bag_id', false)
                ->whereIn('bag_id', $bagIds)
                ->where('entry_type', 'issue')
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $bid = (int) ($row['bag_id'] ?? 0);
                if ($bid > 0) {
                    $ids[$bid] = true;
                }
            }
        }

        if ($db->tableExists('diamond_bag_items')) {
            $rows = $db->table('diamond_bag_items')
                ->select('DISTINCT bag_id', false)
                ->whereIn('bag_id', $bagIds)
                ->groupStart()
                    ->where('pcs_available < pcs_total', null, false)
                    ->orWhere('weight_cts_available < weight_cts_total', null, false)
                ->groupEnd()
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $bid = (int) ($row['bag_id'] ?? 0);
                if ($bid > 0) {
                    $ids[$bid] = true;
                }
            }
        }

        return $ids;
    }

    private function bagHasIssue(int $bagId): bool
    {
        if ($bagId <= 0) {
            return false;
        }

        $ids = $this->issuedBagIds([$bagId]);
        return isset($ids[$bagId]);
    }

    private function findActiveBagCreateVoucherId(int $bagId): int
    {
        if ($bagId <= 0) {
            return 0;
        }

        $db = db_connect();
        if (! $db->tableExists('voucher_lines') || ! $db->tableExists('vouchers')) {
            return 0;
        }

        $row = $db->table('voucher_lines vl')
            ->select('v.id')
            ->join('vouchers v', 'v.id = vl.voucher_id', 'inner')
            ->where('vl.bag_id', $bagId)
            ->where('v.voucher_type', 'DIAMOND_BAG_CREATE')
            ->where('v.status', 'Posted')
            ->orderBy('v.id', 'DESC')
            ->get()
            ->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    private function resolveLocationIdForBag(int $warehouseId): ?int
    {
        if ($warehouseId <= 0) {
            return null;
        }

        $db = db_connect();
        if (! $db->tableExists('warehouses') || ! $db->tableExists('inventory_locations')) {
            return null;
        }

        $warehouse = $db->table('warehouses')->where('id', $warehouseId)->get()->getRowArray();
        if (! $warehouse) {
            return null;
        }

        $warehouseCode = strtoupper(trim((string) ($warehouse['warehouse_code'] ?? '')));
        if (preg_match('/^LOC-(\d+)$/', $warehouseCode, $m) === 1) {
            $locationId = (int) $m[1];
            if ($locationId > 0 && $this->locationModel->find($locationId)) {
                return $locationId;
            }
        }

        $locationTypeMap = [
            'VAULT' => 'VAULT',
            'STORE' => 'STORE',
            'WIP_STORE' => 'WIP',
            'FG_STORE' => 'FG',
            'SHOWROOM' => 'SHOWROOM',
            'BRANCH_STORE' => 'BRANCH',
        ];
        $locationType = $locationTypeMap[$warehouseCode] ?? null;
        if ($locationType === null) {
            return null;
        }

        $matchByName = $this->locationModel
            ->where('is_active', 1)
            ->where('location_type', $locationType)
            ->where('name', (string) ($warehouse['name'] ?? ''))
            ->first();
        if ($matchByName) {
            return (int) ($matchByName['id'] ?? 0);
        }

        $firstByType = $this->locationModel
            ->where('is_active', 1)
            ->where('location_type', $locationType)
            ->orderBy('id', 'ASC')
            ->first();

        return $firstByType ? (int) ($firstByType['id'] ?? 0) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectBagItemsFromRequest(): array
    {
        $types   = (array) $this->request->getPost('diamond_type');
        $sizes   = (array) $this->request->getPost('size');
        $colors  = (array) $this->request->getPost('color');
        $quality = (array) $this->request->getPost('quality');
        $pcs     = (array) $this->request->getPost('pcs');
        $weights = (array) $this->request->getPost('weight_cts');

        $max = max(count($types), count($sizes), count($colors), count($quality), count($pcs), count($weights));
        $rows = [];

        for ($i = 0; $i < $max; $i++) {
            $type = trim((string) ($types[$i] ?? ''));
            $size = trim((string) ($sizes[$i] ?? ''));
            $clr  = trim((string) ($colors[$i] ?? ''));
            $qlt  = trim((string) ($quality[$i] ?? ''));
            $pcsVal = (int) ($pcs[$i] ?? 0);
            $ctsVal = (float) ($weights[$i] ?? 0);

            if ($type === '' && $size === '' && $clr === '' && $qlt === '' && $pcsVal <= 0 && $ctsVal <= 0) {
                continue;
            }

            if ($type === '' || $size === '' || $clr === '' || $qlt === '' || $pcsVal <= 0 || $ctsVal <= 0) {
                continue;
            }

            $rows[] = [
                'diamond_type' => $type,
                'size'         => $size,
                'color'        => $clr,
                'quality'      => $qlt,
                'pcs'          => $pcsVal,
                'weight_cts'   => $ctsVal,
            ];
        }

        return $rows;
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }

    private function currentAuditUserId(): int
    {
        return (int) (session('admin_id') ?? 0);
    }

    private function storeAuditImageAttachment(int $orderId, string $fileField, string $fileType, int $adminId): void
    {
        $file = $this->request->getFile($fileField);
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            throw new Exception('Valid audit image is required.');
        }

        $uploadDir = FCPATH . 'uploads/orders';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $newName = $file->getRandomName();
        $file->move($uploadDir, $newName);

        $this->attachmentModel->insert([
            'order_id' => $orderId,
            'file_type' => $fileType,
            'file_name' => $file->getClientName(),
            'file_path' => 'uploads/orders/' . $newName,
            'uploaded_by' => $adminId,
        ]);
    }

    /**
     * @return list<string>
     */
    private function distinctInventoryValues(string $column): array
    {
        $rows = $this->inventoryModel
            ->select($column)
            ->distinct()
            ->where('item_type', 'Diamond')
            ->where($column . ' IS NOT NULL', null, false)
            ->where($column . ' <>', '')
            ->orderBy($column, 'ASC')
            ->findAll();

        $values = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }
}
