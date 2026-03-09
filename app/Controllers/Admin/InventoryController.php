<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\GoldPurityModel;
use App\Models\InventoryBalanceModel;
use App\Models\InventoryBinModel;
use App\Models\InventoryItemModel;
use App\Models\InventoryLocationModel;
use App\Models\InventoryTransactionModel;
use App\Models\ProductCategoryModel;
use App\Models\ProductModel;

class InventoryController extends BaseController
{
    private InventoryItemModel $inventoryModel;
    private InventoryLocationModel $locationModel;
    private InventoryBinModel $binModel;
    private InventoryBalanceModel $balanceModel;
    private InventoryTransactionModel $txnModel;
    private GoldPurityModel $goldPurityModel;
    private ProductCategoryModel $productCategoryModel;
    private ProductModel $productModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->inventoryModel = new InventoryItemModel();
        $this->locationModel = new InventoryLocationModel();
        $this->binModel = new InventoryBinModel();
        $this->balanceModel = new InventoryBalanceModel();
        $this->txnModel = new InventoryTransactionModel();
        $this->goldPurityModel = new GoldPurityModel();
        $this->productCategoryModel = new ProductCategoryModel();
        $this->productModel = new ProductModel();
    }

    public function index(): string
    {
        return $this->stock();
    }

    public function stock(): string
    {
        $db = db_connect();
        $balances = [];

        if ($db->tableExists('inventory_balances')) {
            $balances = $this->balanceModel
                ->select('inventory_balances.*, inventory_locations.name as warehouse_name, inventory_bins.name as bin_name, gold_purities.purity_code')
                ->join('inventory_locations', 'inventory_locations.id = inventory_balances.warehouse_id', 'left')
                ->join('inventory_bins', 'inventory_bins.id = inventory_balances.bin_id', 'left')
                ->join('gold_purities', 'gold_purities.id = inventory_balances.gold_purity_id', 'left')
                ->where('(ABS(inventory_balances.weight_gm_balance) > 0.0001 OR ABS(inventory_balances.cts_balance) > 0.0001 OR ABS(inventory_balances.pcs_balance) > 0.0001)', null, false)
                ->orderBy('inventory_balances.updated_at', 'DESC')
                ->findAll();
        }

        if ($balances !== []) {
            $summary = [
                'gold_weight'    => 0.0,
                'diamond_cts'    => 0.0,
                'finished_count' => 0,
            ];

            foreach ($balances as $row) {
                if ((string) ($row['item_type'] ?? '') === 'Gold') {
                    $summary['gold_weight'] += (float) ($row['weight_gm_balance'] ?? 0);
                }
                if ((string) ($row['item_type'] ?? '') === 'Diamond') {
                    $summary['diamond_cts'] += (float) ($row['cts_balance'] ?? 0);
                }
                if ((string) ($row['item_type'] ?? '') === 'Finished Goods') {
                    $summary['finished_count']++;
                }
            }

            return view('admin/inventory/stock', [
                'title' => 'Inventory Stock',
                'summary' => $summary,
                'balances' => $balances,
                'items' => [],
            ]);
        }

        $items = $this->inventoryModel
            ->select('inventory_items.*, inventory_locations.name as location_name, gold_purities.purity_code')
            ->join('inventory_locations', 'inventory_locations.id = inventory_items.location_id', 'left')
            ->join('gold_purities', 'gold_purities.id = inventory_items.gold_purity_id', 'left')
            ->orderBy('inventory_items.id', 'DESC')
            ->findAll();

        $summary = [
            'gold_weight'    => 0.0,
            'diamond_cts'    => 0.0,
            'finished_count' => 0,
        ];

        foreach ($items as $row) {
            if ($row['item_type'] === 'Gold') {
                $summary['gold_weight'] += (float) $row['weight_gm'];
            }
            if ($row['item_type'] === 'Diamond') {
                $summary['diamond_cts'] += (float) $row['cts'];
            }
            if ($row['item_type'] === 'Finished Goods') {
                $summary['finished_count']++;
            }
        }

        return view('admin/inventory/stock', [
            'title'   => 'Inventory Stock',
            'items'   => $items,
            'balances' => [],
            'summary' => $summary,
        ]);
    }

    public function warehouses(): string
    {
        $locations = $this->locationModel->orderBy('name', 'ASC')->findAll();
        $bins = [];
        if (db_connect()->tableExists('inventory_bins')) {
            $bins = $this->binModel
                ->select('inventory_bins.*, inventory_locations.name as warehouse_name')
                ->join('inventory_locations', 'inventory_locations.id = inventory_bins.location_id', 'left')
                ->orderBy('inventory_locations.name', 'ASC')
                ->orderBy('inventory_bins.bin_code', 'ASC')
                ->findAll();
        }

        return view('admin/inventory/warehouses/index', [
            'title' => 'Warehouses',
            'locations' => $locations,
            'bins' => $bins,
        ]);
    }

    public function createWarehouse(): string
    {
        return view('admin/inventory/warehouses/create', [
            'title' => 'Create Warehouse',
            'types' => ['Vault', 'Store', 'WIP', 'Showroom', 'Branch'],
        ]);
    }

    public function addLocation()
    {
        $rules = [
            'code' => 'permit_empty|max_length[30]',
            'name' => 'required|max_length[100]',
            'location_type' => 'required|max_length[30]',
            'address' => 'permit_empty',
            'default_bins' => 'permit_empty',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', $this->firstValidationError());
        }

        $code = strtoupper(trim((string) $this->request->getPost('code')));
        $name = trim((string) $this->request->getPost('name'));
        if ($code !== '' && $this->locationModel->where('code', $code)->first()) {
            return redirect()->back()->withInput()->with('error', 'Warehouse code already exists.');
        }
        if ($this->locationModel->where('name', $name)->first()) {
            return redirect()->back()->with('error', 'Location already exists.');
        }

        $locationId = $this->locationModel->insert([
            'code' => $code === '' ? null : $code,
            'name' => $name,
            'location_type' => trim((string) $this->request->getPost('location_type')),
            'address' => $this->nullableString($this->request->getPost('address')),
            'is_active' => 1,
        ], true);

        if ($locationId && db_connect()->tableExists('inventory_bins')) {
            $binCodes = $this->parseBinCodes((string) $this->request->getPost('default_bins'));
            if ($binCodes === []) {
                $binCodes = ['MAIN'];
            }
            foreach ($binCodes as $binCode) {
                if (! $this->binModel->where('location_id', (int) $locationId)->where('bin_code', $binCode)->first()) {
                    $this->binModel->insert([
                        'location_id' => (int) $locationId,
                        'bin_code' => $binCode,
                        'name' => $binCode === 'MAIN' ? 'Main Bin' : $binCode . ' Bin',
                        'is_active' => 1,
                    ]);
                }
            }
        }

        return redirect()->to(site_url('admin/inventory/warehouses'))->with('success', 'Warehouse added.');
    }

    public function addBin()
    {
        if (! db_connect()->tableExists('inventory_bins')) {
            return redirect()->back()->with('error', 'Inventory bin table is not available. Run migration first.');
        }

        $rules = [
            'location_id' => 'required|integer',
            'bin_code' => 'required|max_length[40]',
            'name' => 'required|max_length[120]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $locationId = (int) $this->request->getPost('location_id');
        if (! $this->locationModel->find($locationId)) {
            return redirect()->back()->withInput()->with('error', 'Selected warehouse is invalid.');
        }

        $binCode = strtoupper(trim((string) $this->request->getPost('bin_code')));
        if ($this->binModel->where('location_id', $locationId)->where('bin_code', $binCode)->first()) {
            return redirect()->back()->withInput()->with('error', 'Bin code already exists in this warehouse.');
        }

        $this->binModel->insert([
            'location_id' => $locationId,
            'bin_code' => $binCode,
            'name' => trim((string) $this->request->getPost('name')),
            'is_active' => 1,
        ]);

        return redirect()->to(site_url('admin/inventory/warehouses'))->with('success', 'Warehouse bin created.');
    }

    public function adjustments(): string
    {
        $db = db_connect();
        $builder = $this->txnModel
            ->whereIn('inventory_transactions.transaction_type', ['adjustment_plus', 'adjustment_minus']);

        if ($db->fieldExists('to_warehouse_id', 'inventory_transactions')) {
            $builder
                ->select('inventory_transactions.*, inventory_locations.name as location_name')
                ->join('inventory_locations', 'inventory_locations.id = IFNULL(inventory_transactions.to_warehouse_id, IFNULL(inventory_transactions.from_warehouse_id, inventory_transactions.location_id))', 'left')
                ->orderBy('inventory_transactions.txn_datetime', 'DESC');

            if ($db->tableExists('inventory_bins')) {
                $builder
                    ->select('inventory_bins.name as bin_name')
                    ->join('inventory_bins', 'inventory_bins.id = IFNULL(inventory_transactions.to_bin_id, inventory_transactions.from_bin_id)', 'left');
            }
        } else {
            $builder
                ->select('inventory_transactions.*, inventory_locations.name as location_name')
                ->join('inventory_locations', 'inventory_locations.id = inventory_transactions.location_id', 'left');
        }

        $rows = $builder
            ->orderBy('inventory_transactions.id', 'DESC')
            ->findAll(500);

        return view('admin/inventory/adjustments/index', [
            'title' => 'Inventory Adjustments',
            'adjustments' => $rows,
        ]);
    }

    public function createAdjustment(): string
    {
        $locations = $this->locationModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
        $bins = [];
        if (db_connect()->tableExists('inventory_bins')) {
            $bins = $this->binModel
                ->where('is_active', 1)
                ->orderBy('location_id', 'ASC')
                ->orderBy('bin_code', 'ASC')
                ->findAll();
        }

        return view('admin/inventory/adjustments/create', [
            'title' => 'Create Inventory Adjustment',
            'locations' => $locations,
            'bins' => $bins,
            'goldPurities' => $this->goldPurityModel->where('is_active', 1)->orderBy('purity_percent', 'DESC')->findAll(),
            'materialOptions' => $this->materialOptions(),
        ]);
    }

    public function adjust()
    {
        $rules = [
            'txn_date' => 'required|valid_date',
            'location_id' => 'required|integer',
            'bin_id' => 'permit_empty|integer',
            'item_type' => 'required|max_length[20]',
            'material_name' => 'required|max_length[150]',
            'adjust_mode' => 'required|in_list[plus,minus]',
            'pcs' => 'permit_empty|integer|greater_than_equal_to[0]',
            'weight_gm' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'cts' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'gold_purity_id' => 'permit_empty|integer',
            'diamond_shape' => 'permit_empty|max_length[60]',
            'diamond_sieve' => 'permit_empty|max_length[60]',
            'diamond_sieve_min' => 'permit_empty|decimal',
            'diamond_sieve_max' => 'permit_empty|decimal',
            'diamond_color' => 'permit_empty|max_length[60]',
            'diamond_clarity' => 'permit_empty|max_length[60]',
            'diamond_cut' => 'permit_empty|max_length[60]',
            'diamond_quality' => 'permit_empty|max_length[60]',
            'diamond_fluorescence' => 'permit_empty|max_length[60]',
            'diamond_lab' => 'permit_empty|max_length[60]',
            'certificate_no' => 'permit_empty|max_length[120]',
            'packet_no' => 'permit_empty|max_length[80]',
            'lot_no' => 'permit_empty|max_length[80]',
            'stone_type' => 'permit_empty|max_length[80]',
            'stone_size' => 'permit_empty|max_length[60]',
            'stone_color_shade' => 'permit_empty|max_length[60]',
            'stone_quality_grade' => 'permit_empty|max_length[60]',
            'document_no' => 'permit_empty|max_length[60]',
            'notes' => 'permit_empty',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $locationId = (int) $this->request->getPost('location_id');
        $binId = $this->nullableInt($this->request->getPost('bin_id'));
        if ($binId !== null && ! $this->isBinInWarehouse($binId, $locationId)) {
            return redirect()->back()->withInput()->with('error', 'Selected bin does not belong to selected warehouse.');
        }

        $type = $this->request->getPost('adjust_mode') === 'plus' ? 'adjustment_plus' : 'adjustment_minus';
        $fromWarehouseId = $type === 'adjustment_minus' ? $locationId : null;
        $toWarehouseId = $type === 'adjustment_plus' ? $locationId : null;
        $fromBinId = $type === 'adjustment_minus' ? $binId : null;
        $toBinId = $type === 'adjustment_plus' ? $binId : null;

        $this->txnModel->insert([
            'txn_date' => (string) $this->request->getPost('txn_date'),
            'transaction_type' => $type,
            'location_id' => $locationId,
            'counter_location_id' => null,
            'from_warehouse_id' => $fromWarehouseId,
            'from_bin_id' => $fromBinId,
            'to_warehouse_id' => $toWarehouseId,
            'to_bin_id' => $toBinId,
            'item_type' => trim((string) $this->request->getPost('item_type')),
            'material_name' => trim((string) $this->request->getPost('material_name')),
            'gold_purity_id' => $this->nullableInt($this->request->getPost('gold_purity_id')),
            'diamond_shape' => $this->nullableString($this->request->getPost('diamond_shape')),
            'diamond_sieve' => $this->nullableString($this->request->getPost('diamond_sieve')),
            'diamond_sieve_min' => $this->nullableDecimal($this->request->getPost('diamond_sieve_min')),
            'diamond_sieve_max' => $this->nullableDecimal($this->request->getPost('diamond_sieve_max')),
            'diamond_color' => $this->nullableString($this->request->getPost('diamond_color')),
            'diamond_clarity' => $this->nullableString($this->request->getPost('diamond_clarity')),
            'diamond_cut' => $this->nullableString($this->request->getPost('diamond_cut')),
            'diamond_quality' => $this->nullableString($this->request->getPost('diamond_quality')),
            'diamond_fluorescence' => $this->nullableString($this->request->getPost('diamond_fluorescence')),
            'diamond_lab' => $this->nullableString($this->request->getPost('diamond_lab')),
            'certificate_no' => $this->nullableString($this->request->getPost('certificate_no')),
            'packet_no' => $this->nullableString($this->request->getPost('packet_no')),
            'lot_no' => $this->nullableString($this->request->getPost('lot_no')),
            'stone_type' => $this->nullableString($this->request->getPost('stone_type')),
            'stone_size' => $this->nullableString($this->request->getPost('stone_size')),
            'stone_color_shade' => $this->nullableString($this->request->getPost('stone_color_shade')),
            'stone_quality_grade' => $this->nullableString($this->request->getPost('stone_quality_grade')),
            'pcs' => (int) ($this->request->getPost('pcs') ?: 0),
            'weight_gm' => (float) ($this->request->getPost('weight_gm') ?: 0),
            'cts' => (float) ($this->request->getPost('cts') ?: 0),
            'reference_type' => 'inventory_adjustment',
            'reference_id' => null,
            'document_type' => 'Stock Adjustment',
            'document_no' => $this->nullableString($this->request->getPost('document_no')),
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => (int) session('admin_id'),
        ]);

        return redirect()->to(site_url('admin/inventory/adjustments'))->with('success', 'Inventory adjustment saved.');
    }

    public function transfer()
    {
        $rules = [
            'txn_date' => 'required|valid_date',
            'from_location_id' => 'required|integer',
            'from_bin_id' => 'permit_empty|integer',
            'to_location_id' => 'required|integer',
            'to_bin_id' => 'permit_empty|integer',
            'item_type' => 'required|max_length[20]',
            'material_name' => 'required|max_length[150]',
            'pcs' => 'permit_empty|integer|greater_than_equal_to[0]',
            'weight_gm' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'cts' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'gold_purity_id' => 'permit_empty|integer',
            'diamond_shape' => 'permit_empty|max_length[60]',
            'diamond_sieve' => 'permit_empty|max_length[60]',
            'diamond_sieve_min' => 'permit_empty|decimal',
            'diamond_sieve_max' => 'permit_empty|decimal',
            'diamond_color' => 'permit_empty|max_length[60]',
            'diamond_clarity' => 'permit_empty|max_length[60]',
            'diamond_cut' => 'permit_empty|max_length[60]',
            'diamond_quality' => 'permit_empty|max_length[60]',
            'diamond_fluorescence' => 'permit_empty|max_length[60]',
            'diamond_lab' => 'permit_empty|max_length[60]',
            'certificate_no' => 'permit_empty|max_length[120]',
            'packet_no' => 'permit_empty|max_length[80]',
            'lot_no' => 'permit_empty|max_length[80]',
            'stone_type' => 'permit_empty|max_length[80]',
            'stone_size' => 'permit_empty|max_length[60]',
            'stone_color_shade' => 'permit_empty|max_length[60]',
            'stone_quality_grade' => 'permit_empty|max_length[60]',
            'document_no' => 'permit_empty|max_length[60]',
            'notes' => 'permit_empty',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $fromId = (int) $this->request->getPost('from_location_id');
        $toId = (int) $this->request->getPost('to_location_id');
        if ($fromId === $toId) {
            return redirect()->back()->withInput()->with('error', 'From and To location must be different.');
        }

        $fromBinId = $this->nullableInt($this->request->getPost('from_bin_id'));
        $toBinId = $this->nullableInt($this->request->getPost('to_bin_id'));
        if ($fromBinId !== null && ! $this->isBinInWarehouse($fromBinId, $fromId)) {
            return redirect()->back()->withInput()->with('error', 'From bin does not belong to selected From warehouse.');
        }
        if ($toBinId !== null && ! $this->isBinInWarehouse($toBinId, $toId)) {
            return redirect()->back()->withInput()->with('error', 'To bin does not belong to selected To warehouse.');
        }

        $voucherGroup = 'TRF-' . date('YmdHis') . '-' . substr((string) mt_rand(100000, 999999), -4);
        $common = [
            'txn_date' => (string) $this->request->getPost('txn_date'),
            'voucher_group' => $voucherGroup,
            'item_type' => trim((string) $this->request->getPost('item_type')),
            'material_name' => trim((string) $this->request->getPost('material_name')),
            'gold_purity_id' => $this->nullableInt($this->request->getPost('gold_purity_id')),
            'diamond_shape' => $this->nullableString($this->request->getPost('diamond_shape')),
            'diamond_sieve' => $this->nullableString($this->request->getPost('diamond_sieve')),
            'diamond_sieve_min' => $this->nullableDecimal($this->request->getPost('diamond_sieve_min')),
            'diamond_sieve_max' => $this->nullableDecimal($this->request->getPost('diamond_sieve_max')),
            'diamond_color' => $this->nullableString($this->request->getPost('diamond_color')),
            'diamond_clarity' => $this->nullableString($this->request->getPost('diamond_clarity')),
            'diamond_cut' => $this->nullableString($this->request->getPost('diamond_cut')),
            'diamond_quality' => $this->nullableString($this->request->getPost('diamond_quality')),
            'diamond_fluorescence' => $this->nullableString($this->request->getPost('diamond_fluorescence')),
            'diamond_lab' => $this->nullableString($this->request->getPost('diamond_lab')),
            'certificate_no' => $this->nullableString($this->request->getPost('certificate_no')),
            'packet_no' => $this->nullableString($this->request->getPost('packet_no')),
            'lot_no' => $this->nullableString($this->request->getPost('lot_no')),
            'stone_type' => $this->nullableString($this->request->getPost('stone_type')),
            'stone_size' => $this->nullableString($this->request->getPost('stone_size')),
            'stone_color_shade' => $this->nullableString($this->request->getPost('stone_color_shade')),
            'stone_quality_grade' => $this->nullableString($this->request->getPost('stone_quality_grade')),
            'pcs' => (int) ($this->request->getPost('pcs') ?: 0),
            'weight_gm' => (float) ($this->request->getPost('weight_gm') ?: 0),
            'cts' => (float) ($this->request->getPost('cts') ?: 0),
            'reference_type' => 'inventory_transfer',
            'reference_id' => null,
            'document_type' => 'Stock Transfer',
            'document_no' => $this->nullableString($this->request->getPost('document_no')),
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => (int) session('admin_id'),
        ];

        $this->txnModel->insert($common + [
            'transaction_type' => 'transfer_out',
            'location_id' => $fromId,
            'counter_location_id' => $toId,
            'from_warehouse_id' => $fromId,
            'from_bin_id' => $fromBinId,
            'to_warehouse_id' => $toId,
            'to_bin_id' => $toBinId,
        ]);
        $this->txnModel->insert($common + [
            'transaction_type' => 'transfer_in',
            'location_id' => $toId,
            'counter_location_id' => $fromId,
            'from_warehouse_id' => $fromId,
            'from_bin_id' => $fromBinId,
            'to_warehouse_id' => $toId,
            'to_bin_id' => $toBinId,
        ]);

        return redirect()->to(site_url('admin/inventory/transactions'))->with('success', 'Inventory transfer saved.');
    }

    public function transactions(): string
    {
        $db = db_connect();
        $builder = $this->txnModel
            ->select('inventory_transactions.*, inventory_locations.name as location_name, counter_locations.name as counter_location_name')
            ->join('inventory_locations', 'inventory_locations.id = inventory_transactions.location_id', 'left')
            ->join('inventory_locations as counter_locations', 'counter_locations.id = inventory_transactions.counter_location_id', 'left');

        if ($db->fieldExists('from_warehouse_id', 'inventory_transactions')) {
            $builder
                ->select('from_wh.name as from_warehouse_name, to_wh.name as to_warehouse_name')
                ->join('inventory_locations as from_wh', 'from_wh.id = inventory_transactions.from_warehouse_id', 'left')
                ->join('inventory_locations as to_wh', 'to_wh.id = inventory_transactions.to_warehouse_id', 'left')
                ->orderBy('inventory_transactions.txn_datetime', 'DESC');
        }

        if ($db->tableExists('inventory_bins') && $db->fieldExists('from_bin_id', 'inventory_transactions')) {
            $builder
                ->select('from_bin.name as from_bin_name, to_bin.name as to_bin_name')
                ->join('inventory_bins as from_bin', 'from_bin.id = inventory_transactions.from_bin_id', 'left')
                ->join('inventory_bins as to_bin', 'to_bin.id = inventory_transactions.to_bin_id', 'left');
        }

        $rows = $builder
            ->orderBy('inventory_transactions.id', 'DESC')
            ->findAll(600);

        return view('admin/inventory/transactions/index', [
            'title' => 'Inventory Transactions',
            'transactions' => $rows,
        ]);
    }

    public function categories(): string
    {
        $categories = $this->productCategoryModel
            ->select('product_categories.*, COUNT(products.id) as product_count')
            ->join('products', 'products.category_id = product_categories.id', 'left')
            ->groupBy('product_categories.id')
            ->orderBy('product_categories.name', 'ASC')
            ->findAll();

        return view('admin/inventory/categories/index', [
            'title' => 'Product Categories',
            'categories' => $categories,
        ]);
    }

    public function createCategory(): string
    {
        return view('admin/inventory/categories/create', [
            'title' => 'Create Product Category',
        ]);
    }

    public function storeCategory()
    {
        $rules = [
            'name' => 'required|max_length[120]',
            'description' => 'permit_empty',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($this->productCategoryModel->where('name', $name)->first()) {
            return redirect()->back()->withInput()->with('error', 'Category name already exists.');
        }

        $this->productCategoryModel->insert([
            'name' => $name,
            'description' => $this->nullableString($this->request->getPost('description')),
            'is_active' => (int) ($this->request->getPost('is_active') ?? 1),
        ]);

        return redirect()->to(site_url('admin/inventory/categories'))->with('success', 'Product category created.');
    }

    public function editCategory(int $id): string
    {
        $category = $this->productCategoryModel->find($id);
        if (! $category) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Category not found.');
        }

        return view('admin/inventory/categories/edit', [
            'title' => 'Edit Product Category',
            'category' => $category,
        ]);
    }

    public function updateCategory(int $id)
    {
        $category = $this->productCategoryModel->find($id);
        if (! $category) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Category not found.');
        }

        $rules = [
            'name' => 'required|max_length[120]',
            'description' => 'permit_empty',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $name = trim((string) $this->request->getPost('name'));
        $duplicate = $this->productCategoryModel
            ->where('name', $name)
            ->where('id !=', $id)
            ->first();
        if ($duplicate) {
            return redirect()->back()->withInput()->with('error', 'Category name already exists.');
        }

        $this->productCategoryModel->update($id, [
            'name' => $name,
            'description' => $this->nullableString($this->request->getPost('description')),
            'is_active' => (int) ($this->request->getPost('is_active') ?? 1),
        ]);

        return redirect()->to(site_url('admin/inventory/categories'))->with('success', 'Product category updated.');
    }

    public function deleteCategory(int $id)
    {
        $category = $this->productCategoryModel->find($id);
        if (! $category) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Category not found.');
        }

        $productCount = $this->productModel->where('category_id', $id)->countAllResults();
        if ($productCount > 0) {
            return redirect()->back()->with('error', 'Cannot delete category. Products exist under this category.');
        }

        $this->productCategoryModel->delete($id);

        return redirect()->to(site_url('admin/inventory/categories'))->with('success', 'Product category deleted.');
    }

    public function products(): string
    {
        $products = $this->productModel
            ->select('products.*, product_categories.name as category_name')
            ->join('product_categories', 'product_categories.id = products.category_id', 'left')
            ->orderBy('products.id', 'DESC')
            ->findAll();

        return view('admin/inventory/products/index', [
            'title' => 'Products',
            'products' => $products,
        ]);
    }

    public function createProduct(): string
    {
        return view('admin/inventory/products/create', [
            'title' => 'Create Product',
            'categories' => $this->productCategoryModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'itemTypes' => $this->productItemTypes(),
            'unitTypes' => $this->productUnitTypes(),
        ]);
    }

    public function storeProduct()
    {
        $rules = [
            'category_id' => 'permit_empty|integer',
            'product_code' => 'required|max_length[50]',
            'product_name' => 'required|max_length[150]',
            'item_type' => 'required|in_list[Gold,Diamond,Stone,Finished Goods]',
            'unit_type' => 'required|in_list[gm,cts,pcs]',
            'description' => 'permit_empty',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $code = strtoupper(trim((string) $this->request->getPost('product_code')));
        if ($this->productModel->where('product_code', $code)->first()) {
            return redirect()->back()->withInput()->with('error', 'Product code already exists.');
        }

        $this->productModel->insert([
            'category_id' => $this->nullableInt($this->request->getPost('category_id')),
            'product_code' => $code,
            'product_name' => trim((string) $this->request->getPost('product_name')),
            'item_type' => trim((string) $this->request->getPost('item_type')),
            'unit_type' => trim((string) $this->request->getPost('unit_type')),
            'description' => $this->nullableString($this->request->getPost('description')),
            'is_active' => (int) ($this->request->getPost('is_active') ?? 1),
        ]);

        return redirect()->to(site_url('admin/inventory/products'))->with('success', 'Product created.');
    }

    public function editProduct(int $id): string
    {
        $product = $this->productModel->find($id);
        if (! $product) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Product not found.');
        }

        return view('admin/inventory/products/edit', [
            'title' => 'Edit Product',
            'product' => $product,
            'categories' => $this->productCategoryModel->where('is_active', 1)->orWhere('id', (int) ($product['category_id'] ?? 0))->orderBy('name', 'ASC')->findAll(),
            'itemTypes' => $this->productItemTypes(),
            'unitTypes' => $this->productUnitTypes(),
        ]);
    }

    public function updateProduct(int $id)
    {
        $product = $this->productModel->find($id);
        if (! $product) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Product not found.');
        }

        $rules = [
            'category_id' => 'permit_empty|integer',
            'product_code' => 'required|max_length[50]',
            'product_name' => 'required|max_length[150]',
            'item_type' => 'required|in_list[Gold,Diamond,Stone,Finished Goods]',
            'unit_type' => 'required|in_list[gm,cts,pcs]',
            'description' => 'permit_empty',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $code = strtoupper(trim((string) $this->request->getPost('product_code')));
        $duplicate = $this->productModel
            ->where('product_code', $code)
            ->where('id !=', $id)
            ->first();
        if ($duplicate) {
            return redirect()->back()->withInput()->with('error', 'Product code already exists.');
        }

        $this->productModel->update($id, [
            'category_id' => $this->nullableInt($this->request->getPost('category_id')),
            'product_code' => $code,
            'product_name' => trim((string) $this->request->getPost('product_name')),
            'item_type' => trim((string) $this->request->getPost('item_type')),
            'unit_type' => trim((string) $this->request->getPost('unit_type')),
            'description' => $this->nullableString($this->request->getPost('description')),
            'is_active' => (int) ($this->request->getPost('is_active') ?? 1),
        ]);

        return redirect()->to(site_url('admin/inventory/products'))->with('success', 'Product updated.');
    }

    public function deleteProduct(int $id)
    {
        $product = $this->productModel->find($id);
        if (! $product) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Product not found.');
        }

        $productName = trim((string) $product['product_name']);
        $db = db_connect();
        $usedInInventory = $db->table('inventory_items')->where('material_name', $productName)->countAllResults();
        $usedInTransactions = $db->table('inventory_transactions')->where('material_name', $productName)->countAllResults();
        $usedInPurchases = $db->table('purchase_items')->where('material_name', $productName)->countAllResults();
        if ($usedInInventory > 0 || $usedInTransactions > 0 || $usedInPurchases > 0) {
            return redirect()->back()->with('error', 'Cannot delete product. It is already used in inventory/purchase records.');
        }

        $this->productModel->delete($id);

        return redirect()->to(site_url('admin/inventory/products'))->with('success', 'Product deleted.');
    }

    /**
     * @return list<string>
     */
    private function materialOptions(): array
    {
        $inventoryValues = $this->distinctInventoryValues('material_name');
        $products = $this->productModel
            ->select('product_name')
            ->where('is_active', 1)
            ->orderBy('product_name', 'ASC')
            ->findAll();

        $merged = $inventoryValues;
        foreach ($products as $product) {
            $value = trim((string) ($product['product_name'] ?? ''));
            if ($value !== '') {
                $merged[] = $value;
            }
        }

        $merged = array_values(array_unique($merged));
        sort($merged);

        return $merged;
    }

    /**
     * @return list<string>
     */
    private function productItemTypes(): array
    {
        return ['Gold', 'Diamond', 'Stone', 'Finished Goods'];
    }

    /**
     * @return list<string>
     */
    private function productUnitTypes(): array
    {
        return ['gm', 'cts', 'pcs'];
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

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    private function nullableDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }

    private function nullableString($value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    /**
     * @return list<string>
     */
    private function parseBinCodes(string $raw): array
    {
        $parts = preg_split('/[\\r\\n,]+/', $raw) ?: [];
        $codes = [];
        foreach ($parts as $part) {
            $code = strtoupper(trim($part));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        $codes = array_values(array_unique($codes));
        sort($codes);

        return $codes;
    }

    private function isBinInWarehouse(int $binId, int $warehouseId): bool
    {
        if ($binId <= 0 || $warehouseId <= 0 || ! db_connect()->tableExists('inventory_bins')) {
            return false;
        }

        return $this->binModel
            ->where('id', $binId)
            ->where('location_id', $warehouseId)
            ->first() !== null;
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
