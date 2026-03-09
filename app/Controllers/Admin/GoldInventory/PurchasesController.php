<?php

namespace App\Controllers\Admin\GoldInventory;

use App\Controllers\BaseController;
use App\Models\GoldInventoryItemModel;
use App\Models\GoldInventoryPurchaseHeaderModel;
use App\Models\GoldInventoryPurchaseLineModel;
use App\Models\GoldPurityModel;
use App\Models\InventoryLocationModel;
use App\Services\GoldInventory\StockService;
use Throwable;

class PurchasesController extends BaseController
{
    private GoldInventoryPurchaseHeaderModel $headerModel;
    private GoldInventoryPurchaseLineModel $lineModel;
    private GoldInventoryItemModel $itemModel;
    private GoldPurityModel $purityModel;
    private InventoryLocationModel $locationModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->headerModel = new GoldInventoryPurchaseHeaderModel();
        $this->lineModel = new GoldInventoryPurchaseLineModel();
        $this->itemModel = new GoldInventoryItemModel();
        $this->purityModel = new GoldPurityModel();
        $this->locationModel = new InventoryLocationModel();
    }

    public function index(): string
    {
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));

        $builder = db_connect()->table('gold_inventory_purchase_headers ph')
            ->select('ph.*, il.name as location_name, COUNT(pl.id) as line_count, COALESCE(SUM(pl.weight_gm), 0) as total_weight, COALESCE(SUM(pl.line_value), 0) as total_value', false)
            ->join('gold_inventory_purchase_lines pl', 'pl.purchase_id = ph.id', 'left')
            ->join('inventory_locations il', 'il.id = ph.location_id', 'left')
            ->groupBy('ph.id')
            ->orderBy('ph.id', 'DESC');

        if ($from !== '') {
            $builder->where('ph.purchase_date >=', $from);
        }
        if ($to !== '') {
            $builder->where('ph.purchase_date <=', $to);
        }

        return view('admin/gold_inventory/purchases/index', [
            'title' => 'Gold Purchases',
            'purchases' => $builder->get()->getResultArray(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(): string
    {
        return view('admin/gold_inventory/purchases/create', [
            'title' => 'Create Gold Purchase',
            'items' => $this->itemOptions(),
            'purities' => $this->purityOptions(),
            'locations' => $this->locationOptions(),
            'purchase' => null,
            'lines' => [],
            'action' => site_url('admin/gold-inventory/purchases'),
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

            $purchaseDate = (string) $this->request->getPost('purchase_date');
            $locationId = (int) $this->request->getPost('location_id');
            $purchaseId = (int) $this->headerModel->insert([
                'purchase_date' => $purchaseDate,
                'supplier_name' => trim((string) $this->request->getPost('supplier_name')) ?: null,
                'invoice_no' => trim((string) $this->request->getPost('invoice_no')) ?: null,
                'location_id' => $locationId,
                'notes' => trim((string) $this->request->getPost('notes')) ?: null,
                'created_by' => (int) session('admin_id'),
            ], true);

            foreach ($parsed['lines'] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                if ($itemId <= 0) {
                    $itemId = $service->upsertItemFromSignature((array) ($line['signature'] ?? []));
                }

                $this->lineModel->insert([
                    'purchase_id' => $purchaseId,
                    'item_id' => $itemId,
                    'weight_gm' => $line['weight_gm'],
                    'fine_weight_gm' => $service->calculateFineWeightForItem($itemId, (float) $line['weight_gm']),
                    'rate_per_gm' => $line['rate_per_gm'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyPurchase($purchaseId, [
                'txn_date' => $purchaseDate,
                'location_id' => $locationId,
                'created_by' => (int) session('admin_id'),
                'notes' => 'Gold purchase posting',
            ]);

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/purchases'))
            ->with('success', 'Purchase saved and gold stock updated.');
    }

    public function view(int $id)
    {
        $purchase = db_connect()->table('gold_inventory_purchase_headers ph')
            ->select('ph.*, il.name as location_name')
            ->join('inventory_locations il', 'il.id = ph.location_id', 'left')
            ->where('ph.id', $id)
            ->get()
            ->getRowArray();

        if (! $purchase) {
            return redirect()->to(site_url('admin/gold-inventory/purchases'))->with('error', 'Purchase not found.');
        }

        return view('admin/gold_inventory/purchases/view', [
            'title' => 'View Gold Purchase',
            'purchase' => $purchase,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('gold_inventory_purchase_lines', 'purchase_id', $id),
        ]);
    }

    public function edit(int $id)
    {
        $purchase = $this->headerModel->find($id);
        if (! $purchase) {
            return redirect()->to(site_url('admin/gold-inventory/purchases'))->with('error', 'Purchase not found.');
        }

        return view('admin/gold_inventory/purchases/edit', [
            'title' => 'Edit Gold Purchase',
            'items' => $this->itemOptions(),
            'purities' => $this->purityOptions(),
            'locations' => $this->locationOptions(),
            'purchase' => $purchase,
            'lines' => $this->lineRows($id),
            'action' => site_url('admin/gold-inventory/purchases/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        $purchase = $this->headerModel->find($id);
        if (! $purchase) {
            return redirect()->to(site_url('admin/gold-inventory/purchases'))->with('error', 'Purchase not found.');
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
            $service->reversePurchase($id, [
                'txn_date' => (string) ($purchase['purchase_date'] ?? ''),
                'location_id' => (int) ($purchase['location_id'] ?? 0),
                'created_by' => (int) session('admin_id'),
                'notes' => 'Purchase reversal for edit',
            ]);

            $purchaseDate = (string) $this->request->getPost('purchase_date');
            $locationId = (int) $this->request->getPost('location_id');
            $this->headerModel->update($id, [
                'purchase_date' => $purchaseDate,
                'supplier_name' => trim((string) $this->request->getPost('supplier_name')) ?: null,
                'invoice_no' => trim((string) $this->request->getPost('invoice_no')) ?: null,
                'location_id' => $locationId,
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
                    'weight_gm' => $line['weight_gm'],
                    'fine_weight_gm' => $service->calculateFineWeightForItem($itemId, (float) $line['weight_gm']),
                    'rate_per_gm' => $line['rate_per_gm'],
                    'line_value' => $line['line_value'],
                ]);
            }

            $service->applyPurchase($id, [
                'txn_date' => $purchaseDate,
                'location_id' => $locationId,
                'created_by' => (int) session('admin_id'),
                'notes' => 'Gold purchase posting',
            ]);

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/purchases/view/' . $id))
            ->with('success', 'Purchase updated and stock recalculated.');
    }

    public function delete(int $id)
    {
        $purchase = $this->headerModel->find($id);
        if (! $purchase) {
            return redirect()->to(site_url('admin/gold-inventory/purchases'))->with('error', 'Purchase not found.');
        }

        $db = db_connect();
        $service = new StockService($db);

        try {
            $db->transException(true)->transStart();
            $service->reversePurchase($id, [
                'txn_date' => (string) ($purchase['purchase_date'] ?? ''),
                'location_id' => (int) ($purchase['location_id'] ?? 0),
                'created_by' => (int) session('admin_id'),
                'notes' => 'Purchase deleted reversal',
            ]);
            $this->lineModel->where('purchase_id', $id)->delete();
            $this->headerModel->delete($id);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->to(site_url('admin/gold-inventory/purchases'))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/purchases'))
            ->with('success', 'Purchase deleted and stock reversed.');
    }

    /**
     * @return array{lines:list<array<string,mixed>>,error:?string}
     */
    private function collectLinesFromRequest(bool $rateRequired): array
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

            if ($rateRequired && $rateRaw === '') {
                return ['lines' => [], 'error' => 'Rate per gram is required for purchase lines.'];
            }

            $rate = $rateRaw === '' ? 0.0 : (float) $rateRaw;
            if ($rate < 0) {
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

            $lines[] = [
                'item_id' => $itemId,
                'weight_gm' => round($weight, 3),
                'rate_per_gm' => round($rate, 2),
                'line_value' => round($weight * $rate, 2),
                'signature' => $signature,
            ];
        }

        return ['lines' => $lines, 'error' => null];
    }

    private function validateHeader(): ?string
    {
        if (! $this->validate([
            'purchase_date' => 'required|valid_date',
            'supplier_name' => 'permit_empty|max_length[120]',
            'invoice_no' => 'permit_empty|max_length[80]',
            'location_id' => 'required|integer',
            'notes' => 'permit_empty',
        ])) {
            $errors = $this->validator ? $this->validator->getErrors() : [];
            return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
        }

        $locationId = (int) $this->request->getPost('location_id');
        if (! $this->locationModel->where('is_active', 1)->find($locationId)) {
            return 'Selected location was not found.';
        }

        return null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function itemOptions(): array
    {
        return db_connect()->table('gold_inventory_items gi')
            ->select('gi.*, gp.purity_code as master_purity_code, gp.color_name as master_color_name')
            ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
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
    private function lineRows(int $purchaseId): array
    {
        return db_connect()->table('gold_inventory_purchase_lines pl')
            ->select('pl.*, gi.gold_purity_id, gi.purity_code, gi.purity_percent, gi.color_name, gi.form_type, gp.purity_code as master_purity_code')
            ->join('gold_inventory_items gi', 'gi.id = pl.item_id', 'left')
            ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
            ->where('pl.purchase_id', $purchaseId)
            ->orderBy('pl.id', 'ASC')
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
}
