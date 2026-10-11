<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CompanySettingModel;
use App\Services\BatchPackingListService;
use App\Services\PdfService;
use Throwable;

class PackingListController extends BaseController
{
    private BatchPackingListService $packingLists;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->packingLists = new BatchPackingListService();
    }

    public function index(): string
    {
        return view('admin/packing_lists/index', [
            'title' => 'Packing List Generator',
            'orders' => $this->packingLists->eligibleOrders(),
        ]);
    }

    public function pdf()
    {
        try {
            $report = $this->packingLists->report((array) $this->request->getPost('order_ids'));
            $company = (new CompanySettingModel())->first() ?? [];
            $pdf = (new PdfService())->render('pdf/batch_packing_list', [
                'company' => $company,
                'report' => $report,
            ], 'A4', 'landscape');
            $filename = strtolower((string) $report['packing_no']) . '.pdf';
            return $this->response->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->setBody($pdf);
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
