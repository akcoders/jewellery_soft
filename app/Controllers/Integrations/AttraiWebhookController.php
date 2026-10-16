<?php

namespace App\Controllers\Integrations;

use App\Controllers\BaseController;
use App\Services\AttraiIntegrationService;
use RuntimeException;
use Throwable;

class AttraiWebhookController extends BaseController
{
    public function receive()
    {
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }
        if (! is_array($payload)) {
            $payload = [];
        }

        try {
            $result = (new AttraiIntegrationService())->handleWebhook(
                $payload,
                $this->webhookSecret()
            );
            return $this->response->setJSON(['ok' => true, 'data' => $result]);
        } catch (RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'secret') ? 401 : 422;
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

    private function webhookSecret(): string
    {
        foreach (['X-Attrai-Webhook-Secret', 'X-Webhook-Secret'] as $header) {
            $value = trim($this->request->getHeaderLine($header));
            if ($value !== '') {
                return $value;
            }
        }
        $authorization = trim($this->request->getHeaderLine('Authorization'));
        if (stripos($authorization, 'Bearer ') === 0) {
            return trim(substr($authorization, 7));
        }
        return trim((string) $this->request->getGet('token'));
    }
}
