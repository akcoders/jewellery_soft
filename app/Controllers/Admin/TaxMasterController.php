<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\GstMasterModel;
use App\Models\TaxTypeModel;
use App\Services\TaxMasterService;
use Throwable;

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
        $input = $this->gstMasterInput(true);
        if ($input['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $input['error']);
        }

        $name = $input['name'];
        $components = $input['components'];
        $db = db_connect();
        if ($db->table('gst_masters')->where('name', $name)->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', 'This GST master already exists.');
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

    public function updateGstMaster(int $id)
    {
        $db = db_connect();
        if (! $db->table('gst_masters')->where('id', $id)->get()->getRowArray()) {
            return redirect()->back()->with('error', 'GST master not found.');
        }

        $input = $this->gstMasterInput(false);
        if ($input['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $input['error']);
        }
        if ($db->table('gst_masters')
            ->where('name', $input['name'])
            ->where('id !=', $id)
            ->countAllResults() > 0) {
            return redirect()->back()->withInput()->with('error', 'This GST master already exists.');
        }

        try {
            $result = (new TaxMasterService($db))->updateMasterAndLinkedPurchases(
                $id,
                $input['name'],
                $input['components']
            );
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->back()->with(
            'success',
            'GST master updated. ' . (int) $result['total'] . ' linked purchase record(s) recalculated.'
        );
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

    /**
     * @return array{name:string,components:array<int,float>,error:?string}
     */
    private function gstMasterInput(bool $activeTaxTypesOnly): array
    {
        $name = trim((string) $this->request->getPost('name'));
        if ($name === '' || mb_strlen($name) > 120) {
            return ['name' => $name, 'components' => [], 'error' => 'Enter a valid GST master name.'];
        }

        $typeIds = (array) $this->request->getPost('tax_type_id');
        $percentages = (array) $this->request->getPost('percentage');
        $components = [];
        $rowCount = max(count($typeIds), count($percentages));
        for ($index = 0; $index < $rowCount; $index++) {
            $typeId = (int) ($typeIds[$index] ?? 0);
            $percentageRaw = trim((string) ($percentages[$index] ?? ''));
            if ($typeId <= 0 && $percentageRaw === '') {
                continue;
            }
            if ($typeId <= 0 || $percentageRaw === '' || ! is_numeric($percentageRaw)) {
                return ['name' => $name, 'components' => [], 'error' => 'Select a tax type and enter its percentage for every component.'];
            }
            $percentage = round((float) $percentageRaw, 3);
            if ($percentage <= 0 || $percentage > 100) {
                return ['name' => $name, 'components' => [], 'error' => 'Each tax percentage must be greater than 0 and not more than 100.'];
            }
            if (isset($components[$typeId])) {
                return ['name' => $name, 'components' => [], 'error' => 'The same tax type cannot be added twice.'];
            }
            $components[$typeId] = $percentage;
        }

        if ($components === [] && ! $this->request->getPost('allow_zero_tax')) {
            return ['name' => $name, 'components' => [], 'error' => 'Add at least one tax component or mark this as a zero-tax master.'];
        }

        if ($components !== []) {
            $builder = db_connect()->table('tax_types')->select('id')->whereIn('id', array_keys($components));
            if ($activeTaxTypesOnly) {
                $builder->where('is_active', 1);
            }
            if (count($builder->get()->getResultArray()) !== count($components)) {
                $message = $activeTaxTypesOnly
                    ? 'One or more tax types are invalid or inactive.'
                    : 'One or more tax types are invalid.';
                return ['name' => $name, 'components' => [], 'error' => $message];
            }
        }

        return ['name' => $name, 'components' => $components, 'error' => null];
    }
}
