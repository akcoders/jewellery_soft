<?php

namespace App\Controllers\Api\Mobile;

use App\Services\DeliveryChallanService;
use App\Services\PdfService;
use Throwable;

class DeliveryChallansController extends MobileBaseController
{
    private DeliveryChallanService $challans;

    public function __construct()
    {
        $this->challans = new DeliveryChallanService();
    }

    public function index()
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }
        return $this->ok(['items' => $this->challans->all()]);
    }

    public function createForm()
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }
        return $this->ok($this->challans->formData());
    }

    public function store()
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }
        try {
            $challan = $this->challans->create($this->payload(), (int) ($this->mobileAdmin['id'] ?? 0));
            return $this->ok(['challan' => $challan], 'Delivery challan created.', 201);
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function pdf(int $id)
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }
        try {
            $challan = $this->challans->find($id);
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), 404);
        }
        $pdf = (new PdfService())->render('pdf/delivery_challan', [
            'company' => $this->challans->setting(),
            'challan' => $challan,
            'items' => $challan['items'] ?? [],
        ]);
        $filename = 'delivery_challan_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $challan['challan_no']) . '.pdf';
        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($pdf);
    }
}
