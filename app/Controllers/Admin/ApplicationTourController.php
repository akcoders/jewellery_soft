<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\AdminApplicationTourService;
use CodeIgniter\HTTP\ResponseInterface;

class ApplicationTourController extends BaseController
{
    public function state(): ResponseInterface
    {
        $status = (new AdminApplicationTourService())->status((int) session('admin_id'));

        return $this->response->setHeader('Cache-Control', 'private, no-store, max-age=0')->setJSON([
            'success' => true,
            'tour' => $status,
            'csrfToken' => csrf_token(),
            'csrfHash' => csrf_hash(),
        ]);
    }

    public function update(): ResponseInterface
    {
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $action = strtolower(trim((string) ($payload['action'] ?? '')));
        $stepKey = trim((string) ($payload['stepKey'] ?? ''));
        if (! in_array($action, ['started', 'progress', 'completed', 'dismissed'], true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Invalid tour action.',
                'csrfToken' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }
        if ($stepKey !== '' && ! preg_match('/^[a-z0-9._-]{1,120}$/', $stepKey)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Invalid tour step.',
                'csrfToken' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        $service = new AdminApplicationTourService();
        if (! $service->record((int) session('admin_id'), $action, $stepKey !== '' ? $stepKey : null)) {
            return $this->response->setStatusCode(503)->setJSON([
                'success' => false,
                'message' => 'Tour preference could not be saved. Run the pending database update first.',
                'csrfToken' => csrf_token(),
                'csrfHash' => csrf_hash(),
            ]);
        }

        return $this->response->setHeader('Cache-Control', 'private, no-store, max-age=0')->setJSON([
            'success' => true,
            'tour' => $service->status((int) session('admin_id')),
            'csrfToken' => csrf_token(),
            'csrfHash' => csrf_hash(),
        ]);
    }
}
