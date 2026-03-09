<?php

namespace App\Controllers\Admin\StoneInventory;

use App\Controllers\BaseController;
use App\Models\StoneInventoryItemModel;
use App\Services\StoneInventory\StockService;
use Throwable;

class ItemsController extends BaseController
{
    private StoneInventoryItemModel $itemModel;
    private StockService $stockService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->itemModel = new StoneInventoryItemModel();
        $this->stockService = new StockService();
    }

    public function index(): string
    {
        $q = trim((string) $this->request->getGet('q'));

        $builder = $this->itemModel->builder()
            ->select('stone_inventory_items.*, stone_inventory_stock.qty_balance, stone_inventory_stock.avg_rate, stone_inventory_stock.stock_value')
            ->join('stone_inventory_stock', 'stone_inventory_stock.item_id = stone_inventory_items.id', 'left')
            ->orderBy('stone_inventory_items.id', 'DESC');

        if ($q !== '') {
            $builder->groupStart()
                ->like('stone_inventory_items.product_name', $q)
                ->orLike('stone_inventory_items.stone_type', $q)
                ->orLike('stone_inventory_items.remarks', $q)
                ->groupEnd();
        }

        return view('admin/stone_inventory/items/index', [
            'title' => 'Stone Items',
            'items' => $builder->get()->getResultArray(),
            'q' => $q,
        ]);
    }

    public function create(): string
    {
        return view('admin/stone_inventory/items/create', [
            'title' => 'Create Stone Item',
            'item' => null,
        ]);
    }

    public function store()
    {
        $payload = $this->itemPayloadFromRequest();
        if ($payload['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }

        try {
            $itemId = $this->stockService->upsertItemFromSignature($payload['data']);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/stone-inventory/items/' . $itemId . '/edit'))
            ->with('success', 'Item saved. Stock row initialized.');
    }

    public function edit(int $id)
    {
        $item = $this->itemModel->find($id);
        if (! $item) {
            return redirect()->to(site_url('admin/stone-inventory/items'))->with('error', 'Item not found.');
        }

        return view('admin/stone_inventory/items/edit', [
            'title' => 'Edit Stone Item',
            'item' => $item,
        ]);
    }

    public function update(int $id)
    {
        $item = $this->itemModel->find($id);
        if (! $item) {
            return redirect()->to(site_url('admin/stone-inventory/items'))->with('error', 'Item not found.');
        }

        $payload = $this->itemPayloadFromRequest();
        if ($payload['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }

        try {
            $data = $payload['data'];
            $this->itemModel->update($id, [
                'product_name' => $data['product_name'],
                'stone_type' => $data['stone_type'],
                'default_rate' => $data['default_rate'],
                'remarks' => trim((string) $this->request->getPost('remarks')) ?: null,
            ]);

            db_connect()->query(
                'INSERT INTO stone_inventory_stock (item_id, qty_balance, avg_rate, stock_value, updated_at)
                 VALUES (?, 0, 0, 0, NOW())
                 ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)',
                [$id]
            );
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/stone-inventory/items'))->with('success', 'Item updated.');
    }

    public function delete(int $id)
    {
        $item = $this->itemModel->find($id);
        if (! $item) {
            return redirect()->to(site_url('admin/stone-inventory/items'))->with('error', 'Item not found.');
        }

        $stock = db_connect()->table('stone_inventory_stock')->where('item_id', $id)->get()->getRowArray();
        if ($stock && (float) ($stock['qty_balance'] ?? 0) > 0) {
            return redirect()->to(site_url('admin/stone-inventory/items'))
                ->with('error', 'Cannot delete item with non-zero stock.');
        }

        $db = db_connect();
        $db->transStart();
        $db->table('stone_inventory_stock')->where('item_id', $id)->delete();
        $this->itemModel->delete($id);
        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to(site_url('admin/stone-inventory/items'))->with('error', 'Delete failed.');
        }

        return redirect()->to(site_url('admin/stone-inventory/items'))->with('success', 'Item deleted.');
    }

    /**
     * @return array{data:array<string,mixed>,error:?string}
     */
    private function itemPayloadFromRequest(): array
    {
        $data = [
            'product_name' => trim((string) $this->request->getPost('product_name')),
            'stone_type' => trim((string) $this->request->getPost('stone_type')),
            'default_rate' => round((float) ($this->request->getPost('default_rate') ?? 0), 2),
            'remarks' => trim((string) $this->request->getPost('remarks')),
        ];

        if ($data['product_name'] === '') {
            return ['data' => $data, 'error' => 'Product name is required.'];
        }
        if ($data['default_rate'] < 0) {
            return ['data' => $data, 'error' => 'Default rate cannot be negative.'];
        }

        return [
            'data' => [
                'product_name' => ucwords(strtolower($data['product_name'])),
                'stone_type' => $data['stone_type'] === '' ? null : ucwords(strtolower($data['stone_type'])),
                'default_rate' => $data['default_rate'],
                'remarks' => $data['remarks'] === '' ? null : $data['remarks'],
            ],
            'error' => null,
        ];
    }
}

