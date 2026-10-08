<?php

namespace App\Controllers\Api\Mobile;

use App\Services\DeliveryChallanService;
use App\Services\MobileApprovalService;
use App\Services\MobileUserPolicyService;
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
            $userId = (int) ($this->mobileAdmin['id'] ?? 0);
            $payload = $this->challans->validatedPayload($this->payload());
            unset($payload['_customer'], $payload['_setting'], $payload['_dispatch_from_address'], $payload['_customer_address']);
            if ((new MobileUserPolicyService())->requiresApproval($userId, 'delivery_challan')) {
                $request = (new MobileApprovalService())->submit(
                    'delivery_challan',
                    $payload,
                    $userId,
                    'Delivery challan for ' . (string) ($payload['customer_name'] ?? 'customer')
                );
                return $this->ok([
                    'approval_required' => true,
                    'approval_request' => $request,
                    'submission_message' => 'Delivery challan sent for admin approval.',
                ], 'Delivery challan sent for admin approval.', 201);
            }
            $challan = $this->challans->create($payload, $userId);
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
