<?php

namespace App\Controllers\Admin\GoldInventory;

use App\Controllers\BaseController;
use App\Models\GoldPurityModel;
use Throwable;

class PurityController extends BaseController
{
    private GoldPurityModel $purityModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->purityModel = new GoldPurityModel();
    }

    public function index(): string
    {
        $q = trim((string) $this->request->getGet('q'));

        $builder = $this->purityModel->builder()
            ->select('gold_purities.*, COUNT(gi.id) as product_count', false)
            ->join('gold_inventory_items gi', 'gi.gold_purity_id = gold_purities.id', 'left')
            ->groupBy('gold_purities.id')
            ->orderBy('gold_purities.purity_percent', 'DESC')
            ->orderBy('gold_purities.id', 'DESC');

        if ($q !== '') {
            $builder->groupStart()
                ->like('gold_purities.purity_code', $q)
                ->orLike('gold_purities.color_name', $q)
                ->orLike('gold_purities.purity_percent', $q)
                ->groupEnd();
        }

        return view('admin/gold_inventory/purities/index', [
            'title' => 'Gold Purity Master',
            'rows' => $builder->get()->getResultArray(),
            'q' => $q,
        ]);
    }

    public function create(): string
    {
        return view('admin/gold_inventory/purities/create', [
            'title' => 'Create Gold Purity',
            'row' => null,
            'action' => site_url('admin/gold-inventory/purities'),
        ]);
    }

    public function store()
    {
        $payload = $this->payloadFromRequest();
        if ($payload['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }

        $exists = $this->purityModel
            ->where('purity_code', $payload['data']['purity_code'])
            ->countAllResults();
        if ($exists > 0) {
            return redirect()->back()->withInput()->with('error', 'Purity code already exists.');
        }

        try {
            $this->purityModel->insert($payload['data']);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/purities'))->with('success', 'Gold purity created.');
    }

    public function edit(int $id)
    {
        $row = $this->purityModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/gold-inventory/purities'))->with('error', 'Purity not found.');
        }

        return view('admin/gold_inventory/purities/edit', [
            'title' => 'Edit Gold Purity',
            'row' => $row,
            'action' => site_url('admin/gold-inventory/purities/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        $row = $this->purityModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/gold-inventory/purities'))->with('error', 'Purity not found.');
        }

        $payload = $this->payloadFromRequest();
        if ($payload['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }

        $exists = $this->purityModel
            ->where('purity_code', $payload['data']['purity_code'])
            ->where('id !=', $id)
            ->countAllResults();
        if ($exists > 0) {
            return redirect()->back()->withInput()->with('error', 'Purity code already exists.');
        }

        try {
            $this->purityModel->update($id, $payload['data']);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/purities'))->with('success', 'Gold purity updated.');
    }

    public function delete(int $id)
    {
        $row = $this->purityModel->find($id);
        if (! $row) {
            return redirect()->to(site_url('admin/gold-inventory/purities'))->with('error', 'Purity not found.');
        }

        $inUse = db_connect()->table('gold_inventory_items')->where('gold_purity_id', $id)->countAllResults();
        if ($inUse > 0) {
            return redirect()->to(site_url('admin/gold-inventory/purities'))
                ->with('error', 'Cannot delete purity. It is already used in product master.');
        }

        $this->purityModel->delete($id);
        return redirect()->to(site_url('admin/gold-inventory/purities'))->with('success', 'Gold purity deleted.');
    }

    /**
     * @return array{data:array<string,mixed>,error:?string}
     */
    private function payloadFromRequest(): array
    {
        $purityCode = strtoupper(trim((string) $this->request->getPost('purity_code')));
        $purityPercent = (float) $this->request->getPost('purity_percent');
        $colorName = strtoupper(trim((string) $this->request->getPost('color_name')));
        $isActive = (int) $this->request->getPost('is_active') === 1 ? 1 : 0;

        if ($purityCode === '') {
            return ['data' => [], 'error' => 'Purity code is required.'];
        }
        if ($purityPercent <= 0 || $purityPercent > 100) {
            return ['data' => [], 'error' => 'Purity percent must be between 0.001 and 100.'];
        }

        return [
            'data' => [
                'purity_code' => $purityCode,
                'purity_percent' => round($purityPercent, 3),
                'color_name' => $colorName === '' ? null : $colorName,
                'is_active' => $isActive,
            ],
            'error' => null,
        ];
    }
}

