<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\ProductionPurchaseWorkbookService;
use CodeIgniter\Exceptions\PageNotFoundException;

class ProductionImportController extends BaseController
{
    public function document(int $id)
    {
        $db = db_connect();
        if (! $db->tableExists('production_purchase_documents')) {
            throw PageNotFoundException::forPageNotFound();
        }
        $document = $db->table('production_purchase_documents')->where('id', $id)->get()->getRowArray();
        if (! $document) {
            throw PageNotFoundException::forPageNotFound();
        }

        $relativePath = ltrim(str_replace(['\\', '..'], ['/', ''], (string) $document['stored_path']), '/');
        $fullPath = realpath(WRITEPATH . $relativePath);
        $allowedRoot = realpath(WRITEPATH . 'uploads/production-imports');
        if (! $fullPath || ! $allowedRoot || ! str_starts_with($fullPath, $allowedRoot . DIRECTORY_SEPARATOR) || ! is_file($fullPath)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($fullPath, null)->setFileName((string) $document['original_name']);
    }

    public function purchaseRegister()
    {
        $path = (new ProductionPurchaseWorkbookService())->workbookPath();
        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($path, null)->setFileName('verified-production-purchase-register.xlsx');
    }
}
