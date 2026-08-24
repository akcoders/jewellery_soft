<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\ProductionDataImportService;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

class ProductionImportController extends BaseController
{
    public function index(): string
    {
        $db = db_connect();
        $ready = $db->tableExists('production_import_batches');
        $batch = null;
        $summary = [];
        $documents = [];

        if ($ready) {
            $batch = $db->table('production_import_batches')->orderBy('id', 'DESC')->get()->getRowArray();
            if ($batch) {
                $summary = json_decode((string) ($batch['summary_json'] ?? ''), true) ?: [];
                $documents = $db->table('production_purchase_documents')
                    ->where('batch_id', (int) $batch['id'])
                    ->orderBy('category', 'ASC')
                    ->orderBy('vendor_name', 'ASC')
                    ->orderBy('document_date', 'ASC')
                    ->get()->getResultArray();
            }
        }

        return view('admin/system/production_import', [
            'title' => 'Production Data Import',
            'importReady' => $ready,
            'latestBatch' => $batch,
            'summary' => $summary,
            'documents' => $documents,
        ]);
    }

    public function import()
    {
        $confirmation = strtoupper(trim((string) $this->request->getPost('replacement_confirmation')));
        if ($confirmation !== 'REPLACE') {
            return redirect()->back()->withInput()->with('error', 'Type REPLACE to confirm the production data replacement.');
        }

        $adminPassword = (string) $this->request->getPost('admin_password');
        if (strlen($adminPassword) < 12) {
            return redirect()->back()->withInput()->with('error', 'The new Shweta administrator password must contain at least 12 characters.');
        }

        $file = $this->request->getFile('production_zip');
        if (! $file || ! $file->isValid()) {
            $message = $file ? $file->getErrorString() : 'No ZIP file was selected.';
            return redirect()->back()->withInput()->with('error', 'Upload failed: ' . $message);
        }
        if (strtolower($file->getClientExtension()) !== 'zip') {
            return redirect()->back()->withInput()->with('error', 'Only a ZIP archive is accepted.');
        }
        if ($file->getSize() > 30 * 1024 * 1024) {
            return redirect()->back()->withInput()->with('error', 'The ZIP exceeds the 30 MB import limit.');
        }

        try {
            $result = (new ProductionDataImportService())->import(
                $file->getTempName(),
                $file->getClientName(),
                (int) (session('admin_id') ?? 0),
                $adminPassword
            );
            $summary = $result['summary'] ?? [];

            return redirect()->to(site_url('admin/system/production-import'))->with(
                'success',
                sprintf(
                    'Production data imported: %d orders, %d karigars, %d vendors, and %d purchase documents.',
                    (int) ($summary['orders'] ?? 0),
                    (int) ($summary['karigars'] ?? 0),
                    (int) ($summary['vendors'] ?? 0),
                    (int) ($summary['purchase_documents'] ?? 0)
                )
            );
        } catch (Throwable $e) {
            log_message('error', 'Production data import failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

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
}
