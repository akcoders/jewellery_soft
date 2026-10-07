<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\DeliveryChallanService;
use App\Services\PdfService;
use Throwable;

class DeliveryChallanController extends BaseController
{
    private DeliveryChallanService $challans;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->challans = new DeliveryChallanService();
    }

    public function index(): string
    {
        return view('admin/delivery_challans/index', [
            'title' => 'Delivery Challans',
            'challans' => $this->challans->all(),
        ]);
    }

    public function create(): string
    {
        return view('admin/delivery_challans/create', array_merge(
            ['title' => 'Create Delivery Challan'],
            $this->challans->formData()
        ));
    }

    public function store()
    {
        try {
            $challan = $this->challans->create([
                'challan_date' => $this->request->getPost('challan_date'),
                'dispatch_from' => $this->request->getPost('dispatch_from'),
                'customer_id' => $this->request->getPost('customer_id'),
                'tax_percent' => $this->request->getPost('tax_percent'),
                'notes' => $this->request->getPost('notes'),
                'items' => $this->postedItems(),
            ], (int) session('admin_id'));
            return redirect()->to(site_url('admin/delivery-challans'))
                ->with('success', 'Delivery challan ' . (string) ($challan['challan_no'] ?? '') . ' created.');
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function pdf(int $id)
    {
        try {
            $challan = $this->challans->find($id);
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        $pdf = (new PdfService())->render('pdf/delivery_challan', [
            'company' => $this->challans->setting(),
            'challan' => $challan,
            'items' => $challan['items'] ?? [],
        ]);
        $download = (string) $this->request->getGet('download') === '1';
        $filename = 'delivery_challan_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $challan['challan_no']) . '.pdf';
        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"')
            ->setBody($pdf);
    }

    /** @return list<array<string,mixed>> */
    private function postedItems(): array
    {
        $fields = ['item_type', 'description', 'purity', 'pcs', 'gross_weight_gm', 'net_weight_gm', 'diamond_weight_cts', 'stone_weight_cts', 'other_weight_gm', 'value'];
        $values = [];
        $count = 0;
        foreach ($fields as $field) {
            $values[$field] = (array) $this->request->getPost($field);
            $count = max($count, count($values[$field]));
        }
        $items = [];
        for ($index = 0; $index < $count; $index++) {
            $item = [];
            foreach ($fields as $field) {
                $item[$field] = $values[$field][$index] ?? '';
            }
            $items[] = $item;
        }
        return $items;
    }
}
