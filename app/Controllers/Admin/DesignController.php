<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\DesignMasterModel;
use App\Models\KarigarModel;

class DesignController extends BaseController
{
    private DesignMasterModel $designModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->designModel = new DesignMasterModel();
    }

    public function index(): string
    {
        $designs = $this->designModel
            ->select('design_masters.*, karigars.name AS source_karigar_name')
            ->join('karigars', 'karigars.id = design_masters.source_karigar_id', 'left')
            ->orderBy('design_masters.id', 'DESC')
            ->findAll();

        return view('admin/designs/index', [
            'title'   => 'Design Master',
            'designs' => $designs,
        ]);
    }

    public function create(): string
    {
        return view('admin/designs/create', [
            'title'    => 'Add Design',
            'karigars' => (new KarigarModel())->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
        ]);
    }

    public function store()
    {
        $rules = [
            'design_code' => 'required|max_length[40]|is_unique[design_masters.design_code]',
            'name'        => 'required|max_length[150]',
            'category'    => 'permit_empty|max_length[100]',
            'subcategory' => 'permit_empty|max_length[100]',
            'source_karigar_id' => 'permit_empty|integer',
            'purity_label' => 'permit_empty|max_length[30]',
            'gross_weight_gm' => 'permit_empty|decimal',
            'net_gold_weight_gm' => 'permit_empty|decimal',
            'pure_gold_weight_gm' => 'permit_empty|decimal',
            'diamond_weight_cts' => 'permit_empty|decimal',
            'stone_weight_cts' => 'permit_empty|decimal',
            'design_image' => 'permit_empty|is_image[design_image]|max_size[design_image,6144]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        foreach (['gross_weight_gm', 'net_gold_weight_gm', 'pure_gold_weight_gm', 'diamond_weight_cts', 'stone_weight_cts'] as $weightField) {
            $value = $this->decimalOrNull($weightField);
            if ($value !== null && $value < 0) {
                return redirect()->back()->withInput()->with('error', 'Design weights cannot be negative.');
            }
        }
        $karigarId = (int) $this->request->getPost('source_karigar_id');
        if ($karigarId > 0 && (new KarigarModel())->where('id', $karigarId)->where('is_active', 1)->countAllResults() !== 1) {
            return redirect()->back()->withInput()->with('error', 'Selected karigar is not available.');
        }

        $imagePath = null;
        $image = $this->request->getFile('design_image');
        if ($image && $image->isValid() && ! $image->hasMoved()) {
            $uploadDir = FCPATH . 'uploads/designs';
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $newName = $image->getRandomName();
            $image->move($uploadDir, $newName);
            $imagePath = 'uploads/designs/' . $newName;
        }

        $this->designModel->insert([
            'design_code' => strtoupper(trim((string) $this->request->getPost('design_code'))),
            'name'        => trim((string) $this->request->getPost('name')),
            'category'    => trim((string) $this->request->getPost('category')),
            'subcategory' => trim((string) $this->request->getPost('subcategory')) ?: null,
            'image_path'  => $imagePath,
            'source_karigar_id' => $karigarId ?: null,
            'purity_label' => trim((string) $this->request->getPost('purity_label')) ?: null,
            'gross_weight_gm' => $this->decimalOrNull('gross_weight_gm'),
            'net_gold_weight_gm' => $this->decimalOrNull('net_gold_weight_gm'),
            'pure_gold_weight_gm' => $this->decimalOrNull('pure_gold_weight_gm'),
            'diamond_weight_cts' => $this->decimalOrNull('diamond_weight_cts'),
            'stone_weight_cts' => $this->decimalOrNull('stone_weight_cts'),
            'source_type' => 'MANUAL',
            'is_active'   => 1,
        ]);

        return redirect()->to(site_url('admin/designs'))->with('success', 'Design created successfully.');
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }

    private function decimalOrNull(string $field): ?float
    {
        $value = trim((string) $this->request->getPost($field));
        return $value === '' ? null : (float) $value;
    }
}
