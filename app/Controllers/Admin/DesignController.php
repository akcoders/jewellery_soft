<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\DesignMasterModel;

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
        $designs = $this->designModel->orderBy('id', 'DESC')->findAll();

        return view('admin/designs/index', [
            'title'   => 'Design Master',
            'designs' => $designs,
        ]);
    }

    public function create(): string
    {
        return view('admin/designs/create', [
            'title' => 'Add Design',
        ]);
    }

    public function store()
    {
        $rules = [
            'design_code' => 'required|max_length[40]|is_unique[design_masters.design_code]',
            'name'        => 'required|max_length[150]',
            'category'    => 'permit_empty|max_length[100]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
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
            'design_code' => trim((string) $this->request->getPost('design_code')),
            'name'        => trim((string) $this->request->getPost('name')),
            'category'    => trim((string) $this->request->getPost('category')),
            'image_path'  => $imagePath,
            'is_active'   => 1,
        ]);

        return redirect()->to(site_url('admin/designs'))->with('success', 'Design created successfully.');
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}

