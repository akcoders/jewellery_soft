<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\GoldPurityModel;
use App\Models\InventoryItemModel;
use App\Models\InventoryLocationModel;
use App\Models\InventoryTransactionModel;
use App\Models\PurchaseItemModel;
use App\Models\PurchaseModel;
use App\Models\VendorModel;
use App\Services\AdminPostingService;
use App\Services\PostingService;
use Throwable;

class PurchaseController extends BaseController
{
    private PurchaseModel $purchaseModel;
    private PurchaseItemModel $purchaseItemModel;
    private VendorModel $vendorModel;
    private InventoryLocationModel $locationModel;
    private InventoryItemModel $inventoryModel;
    private GoldPurityModel $goldPurityModel;
    private InventoryTransactionModel $inventoryTxnModel;
    private AdminPostingService $adminPostingService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->purchaseModel = new PurchaseModel();
        $this->purchaseItemModel = new PurchaseItemModel();
        $this->vendorModel = new VendorModel();
        $this->locationModel = new InventoryLocationModel();
        $this->inventoryModel = new InventoryItemModel();
        $this->goldPurityModel = new GoldPurityModel();
        $this->inventoryTxnModel = new InventoryTransactionModel();
        $this->adminPostingService = new AdminPostingService();
    }

    public function index(): string
    {
        $purchases = $this->purchaseModel
            ->select('purchases.*, vendors.name as vendor_name, inventory_locations.name as location_name')
            ->join('vendors', 'vendors.id = purchases.vendor_id', 'left')
            ->join('inventory_locations', 'inventory_locations.id = purchases.location_id', 'left')
            ->orderBy('purchases.id', 'DESC')
            ->findAll();

        return view('admin/purchases/index', [
            'title'     => 'Purchase Entries',
            'purchases' => $purchases,
        ]);
    }

    public function create()
    {
        return redirect()->to(site_url('admin/purchases/gold/create'));
    }

    public function createGold(): string
    {
        return $this->renderCreateForm('Gold');
    }

    public function createDiamond(): string
    {
        return $this->renderCreateForm('Diamond');
    }

    public function createStone(): string
    {
        return $this->renderCreateForm('Stone');
    }

    public function store()
    {
        return $this->storeGold();
    }

    public function storeGold()
    {
        return $this->storeByType('Gold');
    }

    public function storeDiamond()
    {
        return $this->storeByType('Diamond');
    }

    public function storeStone()
    {
        return $this->storeByType('Stone');
    }

    private function renderCreateForm(string $type): string
    {
        return view('admin/purchases/create_type', [
            'title'            => 'Create ' . $type . ' Purchase',
            'purchaseType'     => $type,
            'vendors'          => $this->vendorModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'locations'        => $this->locationModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'goldPurities'     => $this->goldPurityModel->where('is_active', 1)->orderBy('purity_percent', 'DESC')->findAll(),
            'materialOptions'  => $this->distinctInventoryValues('material_name'),
            'shapeOptions'     => $this->distinctInventoryValues('diamond_shape'),
            'sizeOptions'      => $this->distinctInventoryValues('diamond_sieve'),
            'colorOptions'     => $this->distinctInventoryValues('diamond_color'),
            'qualityOptions'   => $this->distinctInventoryValues('diamond_clarity'),
        ]);
    }

    private function storeByType(string $type)
    {
        $isGold = $type === 'Gold';

        $rules = [
            'vendor_id'      => 'required|integer',
            'purchase_date'  => 'required|valid_date',
            'location_id'    => 'required|integer',
            'invoice_no'     => 'required|max_length[80]',
            'invoice_amount' => 'required|decimal|greater_than[0]',
            'notes'          => 'permit_empty',
        ];

        if (! $isGold) {
            $rules['payment_due_date'] = 'required|valid_date';
        } else {
            $rules['payment_due_date'] = 'permit_empty|valid_date';
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $vendorId = (int) $this->request->getPost('vendor_id');
        $locationId = (int) $this->request->getPost('location_id');
        $vendor = $this->vendorModel->where('is_active', 1)->find($vendorId);
        if (! $vendor) {
            return redirect()->back()->withInput()->with('error', 'Vendor not found.');
        }
        if (! $this->locationModel->where('is_active', 1)->find($locationId)) {
            return redirect()->back()->withInput()->with('error', 'Location not found.');
        }

        $items = $this->collectItemsFromRequest($type);
        if ($items === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid item row is required.');
        }

        $paymentStatus = $isGold ? 'Paid' : 'Pending';
        $paymentDueDate = $isGold ? null : trim((string) $this->request->getPost('payment_due_date'));
        if ($paymentDueDate === '') {
            $paymentDueDate = null;
        }

        $db = \Config\Database::connect();
        try {
            $db->transException(true)->transStart();

            $purchaseNo = 'PO' . date('ymdHis') . random_int(10, 99);
            $purchaseDate = (string) $this->request->getPost('purchase_date');
            $invoiceNo = trim((string) $this->request->getPost('invoice_no'));
            $notes = trim((string) $this->request->getPost('notes'));

            $purchaseId = $this->purchaseModel->insert([
                'purchase_no'      => $purchaseNo,
                'purchase_type'    => $type,
                'vendor_id'        => $vendorId,
                'purchase_date'    => $purchaseDate,
                'invoice_no'       => $invoiceNo,
                'invoice_amount'   => (float) $this->request->getPost('invoice_amount'),
                'payment_due_date' => $paymentDueDate,
                'payment_status'   => $paymentStatus,
                'location_id'      => $locationId,
                'notes'            => $notes,
                'created_by'       => (int) session('admin_id'),
            ], true);

            $postingLines = [];
            foreach ($items as $row) {
                $itemId = $this->purchaseItemModel->insert([
                    'purchase_id'      => (int) $purchaseId,
                    'item_type'        => $row['item_type'],
                    'material_name'    => $row['material_name'],
                    'gold_purity_id'   => $row['gold_purity_id'],
                    'diamond_shape'    => $row['diamond_shape'],
                    'diamond_sieve'    => $row['diamond_sieve'],
                    'diamond_color'    => $row['diamond_color'],
                    'diamond_clarity'  => $row['diamond_clarity'],
                    'pcs'              => $row['pcs'],
                    'weight_gm'        => $row['weight_gm'],
                    'cts'              => $row['cts'],
                ], true);

                $this->inventoryTxnModel->insert([
                    'txn_date'            => $purchaseDate,
                    'transaction_type'    => 'purchase',
                    'location_id'         => $locationId,
                    'counter_location_id' => null,
                    'item_type'           => $row['item_type'],
                    'material_name'       => $row['material_name'],
                    'gold_purity_id'      => $row['gold_purity_id'],
                    'diamond_shape'       => $row['diamond_shape'],
                    'diamond_sieve'       => $row['diamond_sieve'],
                    'diamond_color'       => $row['diamond_color'],
                    'diamond_clarity'     => $row['diamond_clarity'],
                    'pcs'                 => $row['pcs'],
                    'weight_gm'           => $row['weight_gm'],
                    'cts'                 => $row['cts'],
                    'reference_type'      => 'purchase_item',
                    'reference_id'        => (int) $itemId,
                    'document_type'       => 'Material Receipt (GRN)',
                    'document_no'         => $invoiceNo,
                    'notes'               => $notes,
                    'created_by'          => (int) session('admin_id'),
                ]);

                $postingLines[] = $this->buildPostingLineFromPurchaseRow($row);
            }

            $warehouseInfo = $this->adminPostingService->resolveWarehouseBinByLocation($locationId);
            $posting = new PostingService($db);
            $warehouseAccountId = $posting->ensureAccount(
                'WAREHOUSE',
                'WH-' . $warehouseInfo['warehouse_id'],
                (string) $warehouseInfo['warehouse_name'] . ' Warehouse',
                'warehouses',
                (int) $warehouseInfo['warehouse_id']
            );
            $vendorAccountId = $posting->ensureAccount(
                'VENDOR',
                'VENDOR-' . $vendorId,
                'Vendor - ' . (string) $vendor['name'],
                'vendors',
                $vendorId
            );

            $posting->postVoucher([
                'voucher_type' => 'GRN',
                'voucher_date' => $purchaseDate,
                'to_warehouse_id' => (int) $warehouseInfo['warehouse_id'],
                'to_bin_id' => (int) $warehouseInfo['bin_id'],
                'party_id' => $vendorId,
                'debit_account_id' => $warehouseAccountId,
                'credit_account_id' => $vendorAccountId,
                'remarks' => 'Purchase ' . $purchaseNo . ' | Invoice ' . $invoiceNo,
                'created_by' => (int) session('admin_id'),
            ], $postingLines);

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/purchases'))->with('success', $type . ' purchase entry saved.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectItemsFromRequest(string $type): array
    {
        $materials = (array) $this->request->getPost('material_name');
        $goldPurities = (array) $this->request->getPost('gold_purity_id');
        $shapes = (array) $this->request->getPost('diamond_shape');
        $sizes = (array) $this->request->getPost('diamond_sieve');
        $colors = (array) $this->request->getPost('diamond_color');
        $qualities = (array) $this->request->getPost('diamond_clarity');
        $pcs = (array) $this->request->getPost('pcs');
        $weights = (array) $this->request->getPost('weight_gm');
        $cts = (array) $this->request->getPost('cts');

        $max = max(
            count($materials),
            count($goldPurities),
            count($shapes),
            count($sizes),
            count($colors),
            count($qualities),
            count($pcs),
            count($weights),
            count($cts)
        );

        $rows = [];
        for ($i = 0; $i < $max; $i++) {
            $material = trim((string) ($materials[$i] ?? ''));
            $goldPurityId = (! isset($goldPurities[$i]) || $goldPurities[$i] === '') ? null : (int) $goldPurities[$i];
            $shape = trim((string) ($shapes[$i] ?? ''));
            $size = trim((string) ($sizes[$i] ?? ''));
            $color = trim((string) ($colors[$i] ?? ''));
            $quality = trim((string) ($qualities[$i] ?? ''));
            $pcsVal = (int) ($pcs[$i] ?? 0);
            $weightVal = (float) ($weights[$i] ?? 0);
            $ctsVal = (float) ($cts[$i] ?? 0);

            if ($type === 'Gold') {
                if ($material === '' && $goldPurityId === null && $weightVal <= 0) {
                    continue;
                }
                if ($material === '' || $goldPurityId === null || $weightVal <= 0) {
                    continue;
                }

                $rows[] = [
                    'item_type'       => 'Gold',
                    'material_name'   => $material,
                    'gold_purity_id'  => $goldPurityId,
                    'diamond_shape'   => null,
                    'diamond_sieve'   => null,
                    'diamond_color'   => null,
                    'diamond_clarity' => null,
                    'pcs'             => 0,
                    'weight_gm'       => round($weightVal, 3),
                    'cts'             => 0,
                ];
                continue;
            }

            if ($type === 'Diamond') {
                if ($shape === '' && $ctsVal <= 0 && $pcsVal <= 0) {
                    continue;
                }
                if ($shape === '' || $ctsVal <= 0) {
                    continue;
                }

                $rows[] = [
                    'item_type'       => 'Diamond',
                    'material_name'   => $material !== '' ? $material : $shape,
                    'gold_purity_id'  => null,
                    'diamond_shape'   => $shape,
                    'diamond_sieve'   => $size === '' ? null : $size,
                    'diamond_color'   => $color === '' ? null : $color,
                    'diamond_clarity' => $quality === '' ? null : $quality,
                    'pcs'             => max(0, $pcsVal),
                    'weight_gm'       => 0,
                    'cts'             => round($ctsVal, 3),
                ];
                continue;
            }

            if ($material === '' && $ctsVal <= 0 && $pcsVal <= 0) {
                continue;
            }
            if ($material === '' || $ctsVal <= 0) {
                continue;
            }

            $rows[] = [
                'item_type'       => 'Stone',
                'material_name'   => $material,
                'gold_purity_id'  => null,
                'diamond_shape'   => $shape === '' ? null : $shape,
                'diamond_sieve'   => $size === '' ? null : $size,
                'diamond_color'   => $color === '' ? null : $color,
                'diamond_clarity' => $quality === '' ? null : $quality,
                'pcs'             => max(0, $pcsVal),
                'weight_gm'       => 0,
                'cts'             => round($ctsVal, 3),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function buildPostingLineFromPurchaseRow(array $row): array
    {
        $itemType = strtoupper((string) ($row['item_type'] ?? ''));

        if ($itemType === 'GOLD') {
            $weight = (float) ($row['weight_gm'] ?? 0);
            $goldPurityId = isset($row['gold_purity_id']) && $row['gold_purity_id'] !== null ? (int) $row['gold_purity_id'] : null;
            $fineGold = $weight;
            if ($goldPurityId !== null) {
                $purity = $this->goldPurityModel->find($goldPurityId);
                if ($purity) {
                    $fineGold = round($weight * ((float) ($purity['purity_percent'] ?? 100) / 100), 3);
                }
            }

            $lineMeta = $this->adminPostingService->buildGoldLineMeta($goldPurityId, $weight, $fineGold);

            return [
                'item_type' => 'GOLD',
                'item_key' => $lineMeta['item_key'],
                'material_name' => $lineMeta['material_name'],
                'gold_purity_id' => $goldPurityId,
                'qty_weight' => $weight,
                'qty_pcs' => 0,
                'qty_cts' => 0,
                'fine_gold' => $lineMeta['fine_gold'],
                'remarks' => (string) ($row['material_name'] ?? 'Gold Purchase'),
            ];
        }

        if ($itemType === 'DIAMOND') {
            $shape = trim((string) ($row['diamond_shape'] ?? ''));
            $size = trim((string) ($row['diamond_sieve'] ?? ''));
            $color = trim((string) ($row['diamond_color'] ?? ''));
            $clarity = trim((string) ($row['diamond_clarity'] ?? ''));

            return [
                'item_type' => 'DIAMOND',
                'item_key' => strtoupper(trim($shape . '|' . $size . '|' . $color . '|' . $clarity)),
                'material_name' => (string) ($row['material_name'] ?? 'Diamond'),
                'shape' => $shape !== '' ? $shape : null,
                'chalni_size' => $size !== '' ? $size : null,
                'color' => $color !== '' ? $color : null,
                'clarity' => $clarity !== '' ? $clarity : null,
                'qty_pcs' => (float) ($row['pcs'] ?? 0),
                'qty_cts' => (float) ($row['cts'] ?? 0),
                'qty_weight' => 0,
                'fine_gold' => 0,
                'remarks' => 'Diamond Purchase',
            ];
        }

        $stoneType = trim((string) ($row['material_name'] ?? 'Stone'));
        $size = trim((string) ($row['diamond_sieve'] ?? ''));
        $quality = trim((string) ($row['diamond_clarity'] ?? ''));

        return [
            'item_type' => 'STONE',
            'item_key' => $this->adminPostingService->buildStoneItemKey($stoneType, $size, $quality),
            'material_name' => $stoneType,
            'stone_type' => $stoneType,
            'qty_pcs' => (float) ($row['pcs'] ?? 0),
            'qty_cts' => (float) ($row['cts'] ?? 0),
            'qty_weight' => 0,
            'fine_gold' => 0,
            'remarks' => 'Stone Purchase',
        ];
    }

    /**
     * @return list<string>
     */
    private function distinctInventoryValues(string $column): array
    {
        $rows = $this->inventoryModel
            ->select($column)
            ->distinct()
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

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
