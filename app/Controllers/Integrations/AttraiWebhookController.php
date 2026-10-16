<?php

namespace App\Controllers\Integrations;

use App\Controllers\BaseController;
use App\Services\AttraiIntegrationService;
use App\Services\ArattaiUserCaptureService;
use RuntimeException;
use Throwable;

class AttraiWebhookController extends BaseController
{
    public function verify()
    {
        $challenge = trim((string) $this->request->getGet('challenge'));
        if ($challenge === '') {
            return $this->response->setStatusCode(400)->setJSON(['message' => 'challenge is required']);
        }
        return $this->response->setJSON(['challenge' => $challenge]);
    }

    public function receive()
    {
        $rawBody = (string) $this->request->getBody();
        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            $payload = [];
        }

        try {
            $service = new AttraiIntegrationService();
            $service->verifyWebhookSignature(
                $rawBody,
                trim($this->request->getHeaderLine('X-Hub-Signature-256'))
            );
            try {
                (new ArattaiUserCaptureService())->capture($payload);
            } catch (Throwable $e) {
                log_message('warning', 'Arattai user ID capture failed: {message}', ['message' => $e->getMessage()]);
            }
            $result = $service->handleWebhook($payload);
            return $this->response->setJSON(['ok' => true, 'data' => $result]);
        } catch (RuntimeException $e) {
            $message = strtolower($e->getMessage());
            $status = str_contains($message, 'signature') || str_contains($message, 'secret') ? 401 : 422;
            return $this->response->setStatusCode($status)->setJSON([
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'ATTRAI webhook failed: {message}', ['message' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON([
                'ok' => false,
                'message' => 'ATTRAI webhook could not be processed.',
            ]);
        }
    }

}
