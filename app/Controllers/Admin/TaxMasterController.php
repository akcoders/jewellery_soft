<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\GstMasterModel;
use App\Models\TaxTypeModel;
use App\Services\TaxMasterService;

class TaxMasterController extends BaseController
{
    public function index(): string
    {
        return view('admin/tax_masters/index', [
            'title' => 'Tax & GST Masters',
            'taxTypes' => db_connect()->table('tax_types')->orderBy('name')->get()->getResultArray(),
            'gstMasters' => (new TaxMasterService())->options(false),
        ]);
    }

    public function storeTaxType()
    {
        $name = strtoupper(trim((string) $this->request->getPost('name')));
        if ($name === '' || mb_strlen($name) > 80) {
            return redirect()->back()->withInput()->with('error', 'Enter a valid tax type name.');
        }
        if (db_connect()->table('tax_types')->where('name', $name)->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', 'This tax type already exists.');
        }
        (new TaxTypeModel())->insert(['name' => $name, 'is_active' => 1]);
        return redirect()->back()->with('success', 'Tax type created.');
    }

    public function storeGstMaster()
    {
        $name = trim((string) $this->request->getPost('name'));
        $typeIds = (array) $this->request->getPost('tax_type_id');
        $percentages = (array) $this->request->getPost('percentage');
        if ($name === '' || mb_strlen($name) > 120) {
            return redirect()->back()->withInput()->with('error', 'Enter a valid GST master name.');
        }
        if (db_connect()->table('gst_masters')->where('name', $name)->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', 'This GST master already exists.');
        }

        $components = [];
        foreach ($typeIds as $index => $typeIdRaw) {
            $typeId = (int) $typeIdRaw;
            $percentage = round((float) ($percentages[$index] ?? 0), 3);
            if ($typeId <= 0 || $percentage <= 0) {
                continue;
            }
            if (isset($components[$typeId])) {
                return redirect()->back()->withInput()->with('error', 'The same tax type cannot be added twice.');
            }
            $components[$typeId] = $percentage;
        }
        if ($components === [] && ! $this->request->getPost('allow_zero_tax')) {
            return redirect()->back()->withInput()->with('error', 'Add at least one tax component or mark this as a zero-tax master.');
        }

        $db = db_connect();
        $validTypes = $db->table('tax_types')->select('id')->where('is_active', 1)->whereIn('id', array_keys($components) ?: [0])->get()->getResultArray();
        if (count($validTypes) !== count($components)) {
            return redirect()->back()->withInput()->with('error', 'One or more tax types are invalid or inactive.');
        }

        $db->transStart();
        $masterId = (int) (new GstMasterModel())->insert([
            'name' => $name,
            'total_percentage' => round(array_sum($components), 3),
            'is_active' => 1,
        ], true);
        foreach ($components as $typeId => $percentage) {
            $db->table('gst_master_components')->insert([
                'gst_master_id' => $masterId,
                'tax_type_id' => $typeId,
                'percentage' => $percentage,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $db->transComplete();

        return $db->transStatus()
            ? redirect()->back()->with('success', 'GST master created.')
            : redirect()->back()->withInput()->with('error', 'Unable to create GST master.');
    }

    public function toggleTaxType(int $id)
    {
        $model = new TaxTypeModel();
        $row = $model->find($id);
        if (! $row) {
            return redirect()->back()->with('error', 'Tax type not found.');
        }
        $model->update($id, ['is_active' => (int) ($row['is_active'] ?? 0) === 1 ? 0 : 1]);
        return redirect()->back()->with('success', 'Tax type status updated.');
    }

    public function toggleGstMaster(int $id)
    {
        $model = new GstMasterModel();
        $row = $model->find($id);
        if (! $row) {
            return redirect()->back()->with('error', 'GST master not found.');
        }
        $model->update($id, ['is_active' => (int) ($row['is_active'] ?? 0) === 1 ? 0 : 1]);
        return redirect()->back()->with('success', 'GST master status updated.');
    }
}
