<?php

namespace App\Controllers\Admin\DiamondInventory;

use App\Controllers\BaseController;
use App\Services\DiamondChalniStockService;
use Throwable;

class StockController extends BaseController
{
    public function index(): string
    {
        $service = new DiamondChalniStockService(db_connect());
        $products = $service->products();
        $selectedItemId = (int) $this->request->getGet('item_id');
        if ($selectedItemId <= 0) {
            foreach ($products as $product) {
                if ((float) ($product['traced_cts'] ?? 0) > 0) {
                    $selectedItemId = (int) $product['id'];
                    break;
                }
            }
            $selectedItemId = $selectedItemId > 0 ? $selectedItemId : (int) ($products[0]['id'] ?? 0);
        }

        $snapshot = $selectedItemId > 0
            ? $service->snapshot($selectedItemId)
            : ['product' => null, 'buckets' => [], 'stats' => []];

        return view('admin/diamond_inventory/stock/index', [
            'title' => 'Diamond Chalni Stock',
            'products' => $products,
            'selectedItemId' => $selectedItemId,
            'product' => $snapshot['product'],
            'buckets' => $snapshot['buckets'],
            'stats' => $snapshot['stats'],
            'shapes' => $service->shapes(),
            'sizes' => $service->sizes(),
            'migrationRequired' => ! $service->ready(),
        ]);
    }

    public function saveChalniStock()
    {
        $itemId = (int) $this->request->getPost('item_id');
        $service = new DiamondChalniStockService(db_connect());
        try {
            $service->save([
                'id' => (int) $this->request->getPost('id'),
                'item_id' => $itemId,
                'shape_id' => (int) $this->request->getPost('shape_id'),
                'size_id' => (int) $this->request->getPost('size_id'),
                'category_label' => (string) $this->request->getPost('category_label'),
                'pcs_balance' => (string) $this->request->getPost('pcs_balance'),
                'carat_balance' => (string) $this->request->getPost('carat_balance'),
                'notes' => (string) $this->request->getPost('notes'),
            ], (int) (session('admin_id') ?? 0));
        } catch (Throwable $e) {
            return redirect()->to(site_url('admin/diamond-inventory/stock?item_id=' . $itemId))
                ->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-inventory/stock?item_id=' . $itemId))
            ->with('success', 'Chalni-wise stock saved. Product ledger total was not double-counted.');
    }
}
