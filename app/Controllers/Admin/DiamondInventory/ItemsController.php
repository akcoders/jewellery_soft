<?php

namespace App\Controllers\Admin\DiamondInventory;

use App\Controllers\BaseController;
use App\Models\ItemModel;
use App\Services\DiamondInventory\StockService;
use Throwable;

class ItemsController extends BaseController
{
    private ItemModel $itemModel;
    private StockService $stockService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->itemModel = new ItemModel();
        $this->stockService = new StockService();
    }

    public function index(): string
    {
        $q = trim((string) $this->request->getGet('q'));

        $builder = $this->itemModel->builder()
            ->select('items.*, stock.pcs_balance, stock.carat_balance, stock.avg_cost_per_carat, stock.stock_value')
            ->join('stock', 'stock.item_id = items.id', 'left')
            ->orderBy('items.id', 'DESC');

        if ($q !== '') {
            $builder->groupStart()
                ->like('items.diamond_type', $q)
                ->orLike('items.shape', $q)
                ->orLike('items.color', $q)
                ->orLike('items.clarity', $q)
                ->orLike('items.cut', $q)
                ->groupEnd();
        }

        return view('admin/diamond_inventory/items/index', [
            'title' => 'Diamond Items',
            'items' => $builder->get()->getResultArray(),
            'q' => $q,
        ]);
    }

    public function create(): string
    {
        return view('admin/diamond_inventory/items/create', [
            'title' => 'Create Diamond Item',
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

        return redirect()->to(site_url('admin/diamond-inventory/items/' . $itemId . '/edit'))
            ->with('success', 'Item saved. Stock row initialized.');
    }

    public function edit(int $id)
    {
        $item = $this->itemModel->find($id);
        if (! $item) {
            return redirect()->to(site_url('admin/diamond-inventory/items'))->with('error', 'Item not found.');
        }

        return view('admin/diamond_inventory/items/edit', [
            'title' => 'Edit Diamond Item',
            'item' => $item,
        ]);
    }

    public function update(int $id)
    {
        $item = $this->itemModel->find($id);
        if (! $item) {
            return redirect()->to(site_url('admin/diamond-inventory/items'))->with('error', 'Item not found.');
        }

        $payload = $this->itemPayloadFromRequest();
        if ($payload['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }

        try {
            $data = $payload['data'];
            $this->itemModel->update($id, [
                'diamond_type' => $data['diamond_type'],
                'shape' => $data['shape'],
                'chalni_from' => $data['chalni_from'],
                'chalni_to' => $data['chalni_to'],
                'color' => $data['color'],
                'clarity' => $data['clarity'],
                'cut' => $data['cut'],
                'remarks' => trim((string) $this->request->getPost('remarks')) ?: null,
            ]);
            db_connect()->query(
                'INSERT INTO stock (item_id, pcs_balance, carat_balance, avg_cost_per_carat, stock_value, updated_at)
                 VALUES (?, 0, 0, 0, 0, NOW())
                 ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)',
                [$id]
            );
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-inventory/items'))->with('success', 'Item updated.');
    }

    public function delete(int $id)
    {
        $item = $this->itemModel->find($id);
        if (! $item) {
            return redirect()->to(site_url('admin/diamond-inventory/items'))->with('error', 'Item not found.');
        }

        $stock = db_connect()->table('stock')->where('item_id', $id)->get()->getRowArray();
        if ($stock && ((float) $stock['pcs_balance'] > 0 || (float) $stock['carat_balance'] > 0)) {
            return redirect()->to(site_url('admin/diamond-inventory/items'))
                ->with('error', 'Cannot delete item with non-zero stock.');
        }

        $db = db_connect();
        $db->transStart();
        $db->table('stock')->where('item_id', $id)->delete();
        $this->itemModel->delete($id);
        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to(site_url('admin/diamond-inventory/items'))->with('error', 'Delete failed.');
        }

        return redirect()->to(site_url('admin/diamond-inventory/items'))->with('success', 'Item deleted.');
    }

    /**
     * @return array{data:array<string,mixed>,error:?string}
     */
    private function itemPayloadFromRequest(): array
    {
        $data = [
            'diamond_type' => trim((string) $this->request->getPost('diamond_type')),
            'shape' => trim((string) $this->request->getPost('shape')),
            'chalni_from' => trim((string) $this->request->getPost('chalni_from')),
            'chalni_to' => trim((string) $this->request->getPost('chalni_to')),
            'color' => trim((string) $this->request->getPost('color')),
            'clarity' => trim((string) $this->request->getPost('clarity')),
            'cut' => trim((string) $this->request->getPost('cut')),
            'remarks' => trim((string) $this->request->getPost('remarks')),
        ];

        if ($data['diamond_type'] === '') {
            return ['data' => $data, 'error' => 'Diamond type is required.'];
        }

        $from = $data['chalni_from'] === '' ? null : $data['chalni_from'];
        $to = $data['chalni_to'] === '' ? null : $data['chalni_to'];
        if (($from === null && $to !== null) || ($from !== null && $to === null)) {
            return ['data' => $data, 'error' => 'Both chalni from and chalni to are required when chalni is used.'];
        }
        if ($from !== null && ! ctype_digit($from)) {
            return ['data' => $data, 'error' => 'Chalni from must contain digits only.'];
        }
        if ($to !== null && ! ctype_digit($to)) {
            return ['data' => $data, 'error' => 'Chalni to must contain digits only.'];
        }
        if ($from !== null && $to !== null && ((int) ltrim($from, '0')) > ((int) ltrim($to, '0'))) {
            return ['data' => $data, 'error' => 'Chalni from must be less than or equal to chalni to.'];
        }

        $diamondType = ucwords(strtolower($data['diamond_type']));
        $shape = $data['shape'] === '' ? null : ucwords(strtolower($data['shape']));
        $color = $data['color'] === '' ? null : strtoupper($data['color']);
        $clarity = $data['clarity'] === '' ? null : strtoupper($data['clarity']);
        $cut = $data['cut'] === '' ? null : ucwords(strtolower($data['cut']));

        return [
            'data' => [
                'diamond_type' => $diamondType,
                'shape' => $shape,
                'chalni_from' => $from,
                'chalni_to' => $to,
                'color' => $color,
                'clarity' => $clarity,
                'cut' => $cut,
                'remarks' => $data['remarks'] === '' ? null : $data['remarks'],
            ],
            'error' => null,
        ];
    }
}
