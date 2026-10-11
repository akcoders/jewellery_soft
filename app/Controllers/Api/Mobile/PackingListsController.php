<?php

namespace App\Controllers\Api\Mobile;

use App\Models\CompanySettingModel;
use App\Services\BatchPackingListService;
use App\Services\PdfService;
use App\Services\RbacService;
use Throwable;

class PackingListsController extends MobileBaseController
{
    public function index()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }
        $userId = (int) ($this->mobileAdmin['id'] ?? 0);
        $canManageAll = (new RbacService())->userCan($userId, 'orders.assign');
        try {
            return $this->ok((new BatchPackingListService())->eligibleOrders($userId, $canManageAll));
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function pdf()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }
        $userId = (int) ($this->mobileAdmin['id'] ?? 0);
        $canManageAll = (new RbacService())->userCan($userId, 'orders.assign');
        try {
            $payload = $this->payload();
            $report = (new BatchPackingListService())->report((array) ($payload['order_ids'] ?? []), $userId, $canManageAll);
            $pdf = (new PdfService())->render('pdf/batch_packing_list', [
                'company' => (new CompanySettingModel())->first() ?? [],
                'report' => $report,
            ], 'A4', 'landscape');
            $filename = strtolower((string) $report['packing_no']) . '.pdf';
            return $this->response->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->setBody($pdf);
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }
}
