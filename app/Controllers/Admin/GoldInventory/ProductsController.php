<?php

namespace App\Controllers\Admin\GoldInventory;

use App\Controllers\BaseController;
use App\Models\GoldInventoryItemModel;
use App\Models\GoldPurityModel;
use App\Services\GoldInventory\StockService;
use Throwable;

class ProductsController extends BaseController
{
    private GoldInventoryItemModel $itemModel;
    private GoldPurityModel $purityModel;
    private StockService $stockService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->itemModel = new GoldInventoryItemModel();
        $this->purityModel = new GoldPurityModel();
        $this->stockService = new StockService();
    }

    public function index(): string
    {
        $q = trim((string) $this->request->getGet('q'));

        $builder = db_connect()->table('gold_inventory_items gi')
            ->select('gi.*, gp.purity_code as master_purity_code, gp.purity_percent as master_purity_percent, gis.weight_balance_gm, gis.fine_balance_gm, gis.avg_cost_per_gm, gis.stock_value')
            ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
            ->join('gold_inventory_stock gis', 'gis.item_id = gi.id', 'left')
            ->orderBy('gi.id', 'DESC');

        if ($q !== '') {
            $builder->groupStart()
                ->like('gp.purity_code', $q)
                ->orLike('gi.color_name', $q)
                ->orLike('gi.form_type', $q)
                ->orLike('gi.purity_percent', $q)
                ->groupEnd();
        }

        return view('admin/gold_inventory/products/index', [
            'title' => 'Gold Product Master',
            'rows' => $builder->get()->getResultArray(),
            'q' => $q,
        ]);
    }

    public function create(): string
    {
        return view('admin/gold_inventory/products/create', [
            'title' => 'Create Gold Product',
            'row' => null,
            'purities' => $this->purityOptions(),
            'action' => site_url('admin/gold-inventory/products'),
        ]);
    }

    public function store()
    {
        $payload = $this->payloadFromRequest();
        if ($payload['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }

        try {
            $this->stockService->upsertItemFromSignature($payload['data']);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/products'))->with('success', 'Gold product created.');
    }

    public function edit(int $id)
    {
        $row = $this->itemModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/gold-inventory/products'))->with('error', 'Product not found.');
        }

        return view('admin/gold_inventory/products/edit', [
            'title' => 'Edit Gold Product',
            'row' => $row,
            'purities' => $this->purityOptions(),
            'action' => site_url('admin/gold-inventory/products/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        $row = $this->itemModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/gold-inventory/products'))->with('error', 'Product not found.');
        }

        $payload = $this->payloadFromRequest();
        if ($payload['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }

        $signature = $this->normalizeForMatch($payload['data']);
        $duplicate = db_connect()->query(
            'SELECT id FROM gold_inventory_items
             WHERE gold_purity_id <=> ?
               AND purity_code <=> ?
               AND color_name <=> ?
               AND form_type <=> ?
               AND id <> ?
             LIMIT 1',
            [
                $signature['gold_purity_id'],
                $signature['purity_code'],
                $signature['color_name'],
                $signature['form_type'],
                $id,
            ]
        )->getRowArray();

        if ($duplicate) {
            return redirect()->back()->withInput()->with('error', 'Product already exists with same purity/color/form.');
        }

        try {
            $this->itemModel->update($id, $signature);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/products'))->with('success', 'Gold product updated.');
    }

    public function delete(int $id)
    {
        $row = $this->itemModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/gold-inventory/products'))->with('error', 'Product not found.');
        }

        $stock = db_connect()->table('gold_inventory_stock')->where('item_id', $id)->get()->getRowArray();
        if ($stock && ((float) ($stock['weight_balance_gm'] ?? 0) > 0 || (float) ($stock['fine_balance_gm'] ?? 0) > 0)) {
            return redirect()->to(site_url('admin/gold-inventory/products'))->with('error', 'Cannot delete product with non-zero stock.');
        }

        $hasTxns =
            db_connect()->table('gold_inventory_purchase_lines')->where('item_id', $id)->countAllResults() > 0
            || db_connect()->table('gold_inventory_issue_lines')->where('item_id', $id)->countAllResults() > 0
            || db_connect()->table('gold_inventory_return_lines')->where('item_id', $id)->countAllResults() > 0
            || db_connect()->table('gold_inventory_adjustment_lines')->where('item_id', $id)->countAllResults() > 0;

        if ($hasTxns) {
            return redirect()->to(site_url('admin/gold-inventory/products'))->with('error', 'Cannot delete product with transactions.');
        }

        $db = db_connect();
        $db->transStart();
        $db->table('gold_inventory_stock')->where('item_id', $id)->delete();
        $this->itemModel->delete($id);
        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to(site_url('admin/gold-inventory/products'))->with('error', 'Delete failed.');
        }

        return redirect()->to(site_url('admin/gold-inventory/products'))->with('success', 'Gold product deleted.');
    }

    /**
     * @return array{data:array<string,mixed>,error:?string}
     */
    private function payloadFromRequest(): array
    {
        $purityId = (int) $this->request->getPost('gold_purity_id');
        $colorName = trim((string) $this->request->getPost('color_name'));
        $formType = trim((string) $this->request->getPost('form_type'));
        $remarks = trim((string) $this->request->getPost('remarks'));

        if ($purityId <= 0) {
            return ['data' => [], 'error' => 'Gold purity is required.'];
        }
        if ($formType === '') {
            return ['data' => [], 'error' => 'Form/Product type is required.'];
        }

        $purity = $this->purityModel->find($purityId);
        if (! $purity) {
            return ['data' => [], 'error' => 'Selected purity not found.'];
        }

        $data = [
            'gold_purity_id' => $purityId,
            'purity_code' => (string) ($purity['purity_code'] ?? ''),
            'purity_percent' => (float) ($purity['purity_percent'] ?? 0),
            'color_name' => $colorName !== '' ? $colorName : (string) ($purity['color_name'] ?? ''),
            'form_type' => $formType,
            'remarks' => $remarks === '' ? null : $remarks,
        ];

        return ['data' => $data, 'error' => null];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function normalizeForMatch(array $data): array
    {
        $purityCode = strtoupper(trim((string) ($data['purity_code'] ?? 'NA')));
        $purityPercent = (float) ($data['purity_percent'] ?? 0);
        $color = strtoupper(trim((string) ($data['color_name'] ?? 'NA')));
        $form = ucwords(strtolower(trim((string) ($data['form_type'] ?? 'Raw'))));

        return [
            'gold_purity_id' => (int) ($data['gold_purity_id'] ?? 0),
            'purity_code' => $purityCode === '' ? 'NA' : $purityCode,
            'purity_percent' => round(max(0.0, min(100.0, $purityPercent)), 3),
            'color_name' => $color === '' ? 'NA' : $color,
            'form_type' => $form === '' ? 'Raw' : $form,
            'remarks' => isset($data['remarks']) ? trim((string) $data['remarks']) ?: null : null,
        ];
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
}

