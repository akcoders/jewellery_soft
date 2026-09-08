<?php

namespace App\Controllers\Admin\DiamondInventory;

use App\Controllers\BaseController;
use App\Models\DiamondShapeMasterModel;
use App\Models\DiamondSizeMasterModel;
use Throwable;

class ShapeSizeController extends BaseController
{
    private DiamondShapeMasterModel $shapeModel;
    private DiamondSizeMasterModel $sizeModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->shapeModel = new DiamondShapeMasterModel();
        $this->sizeModel = new DiamondSizeMasterModel();
    }

    public function index(): string
    {
        $shapes = $this->shapeModel->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll();
        $sizes = db_connect()->table('diamond_size_masters sz')
            ->select('sz.*, sm.name AS shape_name, sm.code AS shape_code')
            ->join('diamond_shape_masters sm', 'sm.id = sz.shape_id', 'inner')
            ->orderBy('sm.sort_order', 'ASC')->orderBy('sz.sort_order', 'ASC')->orderBy('sz.size_label', 'ASC')
            ->get()->getResultArray();

        return view('admin/diamond_inventory/shape_sizes/index', [
            'title' => 'Diamond Shape & Size Master',
            'shapes' => $shapes,
            'sizes' => $sizes,
        ]);
    }

    public function storeShape()
    {
        $name = trim((string) $this->request->getPost('name'));
        $code = strtoupper(trim((string) $this->request->getPost('code')));
        $code = preg_replace('/[^A-Z0-9]+/', '_', $code) ?: '';
        if ($name === '' || $code === '') {
            return redirect()->back()->withInput()->with('error', 'Shape name and code are required.');
        }

        try {
            $this->shapeModel->insert([
                'name' => ucwords(strtolower($name)),
                'code' => $code,
                'sort_order' => max(0, (int) $this->request->getPost('sort_order')),
                'is_active' => 1,
            ]);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Shape could not be saved. Check for a duplicate name/code.');
        }

        return redirect()->to(site_url('admin/diamond-inventory/shape-sizes'))->with('success', 'Diamond shape added.');
    }

    public function storeSize()
    {
        $shapeId = (int) $this->request->getPost('shape_id');
        $code = strtoupper(trim((string) $this->request->getPost('size_code')));
        $label = trim((string) $this->request->getPost('size_label'));
        $chalni = trim((string) $this->request->getPost('chalni_label'));
        $minRaw = trim((string) $this->request->getPost('min_mm'));
        $maxRaw = trim((string) $this->request->getPost('max_mm'));
        $lengthRaw = trim((string) $this->request->getPost('length_mm'));
        $widthRaw = trim((string) $this->request->getPost('width_mm'));

        if (! $this->shapeModel->find($shapeId) || $code === '' || $label === '') {
            return redirect()->back()->withInput()->with('error', 'Shape, size code and size label are required.');
        }
        $min = $minRaw === '' ? null : (float) $minRaw;
        $max = $maxRaw === '' ? null : (float) $maxRaw;
        if (($min === null) !== ($max === null)) {
            return redirect()->back()->withInput()->with('error', 'Enter both minimum and maximum millimetres, or leave both blank.');
        }
        if ($min !== null && ($min <= 0 || $max < $min)) {
            return redirect()->back()->withInput()->with('error', 'Size millimetre range is invalid.');
        }
        $length = $lengthRaw === '' ? null : (float) $lengthRaw;
        $width = $widthRaw === '' ? null : (float) $widthRaw;
        if ($width !== null && $length === null) {
            return redirect()->back()->withInput()->with('error', 'Enter length/diameter before width.');
        }
        if (($length !== null && $length <= 0) || ($width !== null && $width <= 0)) {
            return redirect()->back()->withInput()->with('error', 'Diamond dimensions must be greater than zero.');
        }

        try {
            $this->sizeModel->insert([
                'shape_id' => $shapeId,
                'size_code' => preg_replace('/\s+/', '-', $code),
                'size_label' => $label,
                'chalni_label' => $chalni === '' ? null : $chalni,
                'min_mm' => $min,
                'max_mm' => $max,
                'length_mm' => $length,
                'width_mm' => $width ?? $length,
                'sort_order' => max(0, (int) $this->request->getPost('sort_order')),
                'is_active' => 1,
            ]);
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Size could not be saved. The code may already exist for this shape.');
        }

        return redirect()->to(site_url('admin/diamond-inventory/shape-sizes'))->with('success', 'Diamond size added.');
    }

    public function toggleShape(int $id)
    {
        $shape = $this->shapeModel->find($id);
        if ($shape) {
            $this->shapeModel->update($id, ['is_active' => (int) ($shape['is_active'] ?? 0) === 1 ? 0 : 1]);
        }
        return redirect()->to(site_url('admin/diamond-inventory/shape-sizes'));
    }

    public function toggleSize(int $id)
    {
        $size = $this->sizeModel->find($id);
        if ($size) {
            $this->sizeModel->update($id, ['is_active' => (int) ($size['is_active'] ?? 0) === 1 ? 0 : 1]);
        }
        return redirect()->to(site_url('admin/diamond-inventory/shape-sizes'));
    }
}
