<?php

namespace App\Services;

use App\Models\ArattaiWebhookEventModel;
use Throwable;

class ArattaiWebhookEventService
{
    /** @param array<string,mixed> $payload */
    public function start(string $eventId, array $payload): array
    {
        $db = db_connect();
        if (! $db->tableExists('arattai_webhook_events')) {
            return ['id' => 0, 'duplicate' => false];
        }
        $eventId = trim($eventId);
        if ($eventId === '') {
            $eventId = 'missing-' . hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES) ?: random_bytes(16));
        }
        $existing = $db->table('arattai_webhook_events')->where('event_id', $eventId)->get()->getRowArray();
        if (is_array($existing)) {
            return ['id' => (int) ($existing['id'] ?? 0), 'duplicate' => true];
        }
        $entity = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
        $user = is_array($entity['user'] ?? null) ? $entity['user'] : [];
        try {
            $id = (int) (new ArattaiWebhookEventModel())->insert([
                'event_id' => $eventId,
                'event_type' => trim((string) ($payload['event'] ?? '')) ?: null,
                'arattai_user_id' => preg_replace('/\D+/', '', (string) ($user['id'] ?? '')) ?: null,
                'business_number_id' => trim((string) ($payload['phonenumber_id'] ?? '')) ?: null,
                'processing_status' => 'received',
                'received_at' => date('Y-m-d H:i:s'),
            ], true);
            return ['id' => $id, 'duplicate' => false];
        } catch (Throwable) {
            $existing = $db->table('arattai_webhook_events')->where('event_id', $eventId)->get()->getRowArray();
            return ['id' => (int) ($existing['id'] ?? 0), 'duplicate' => is_array($existing)];
        }
    }

    public function finish(int $id, string $status, string $message = ''): void
    {
        if ($id <= 0 || ! db_connect()->tableExists('arattai_webhook_events')) {
            return;
        }
        (new ArattaiWebhookEventModel())->update($id, [
            'processing_status' => mb_substr(trim($status) ?: 'processed', 0, 30),
            'result_message' => mb_substr(trim($message), 0, 500) ?: null,
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
