<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\OrderReadyStageService;
use Throwable;

class OrderReadyStageController extends BaseController
{
    public function __construct()
    {
        helper(['form', 'url']);
    }

    public function index(): string
    {
        $query = mb_substr(trim((string) $this->request->getGet('q')), 0, 150);
        return view('admin/orders/ready_stage', [
            'title' => 'Move Order to Ready',
            'orders' => (new OrderReadyStageService())->orderOptions($query),
            'query' => $query,
            'selectedOrderId' => (int) ($this->request->getGet('order_id') ?: old('order_id')),
        ]);
    }

    public function update()
    {
        $orderId = (int) $this->request->getPost('order_id');
        $query = mb_substr(trim((string) $this->request->getPost('q')), 0, 150);
        $returnUrl = site_url('admin/orders/ready-stage') . '?' . http_build_query(['order_id' => $orderId, 'q' => $query]);
        try {
            $result = (new OrderReadyStageService())->markReady($orderId, (int) session('admin_id'));
            return redirect()->to($returnUrl)
                ->with('success', $result['order_no'] . ' is Ready. Receive the finished jewellery to complete this order.');
        } catch (Throwable $e) {
            return redirect()->to($returnUrl)->withInput()->with('error', $e->getMessage());
        }
    }
}
