<?php

namespace App\Services;

use App\Models\CompanySettingModel;
use RuntimeException;

class AttraiIntegrationService
{
    private CompanySettingModel $companySettingModel;
    private WhatsappSenderQueueService $queueService;

    public function __construct(
        ?CompanySettingModel $companySettingModel = null,
        ?WhatsappSenderQueueService $queueService = null
    ) {
        $this->companySettingModel = $companySettingModel ?? new CompanySettingModel();
        $this->queueService = $queueService ?? new WhatsappSenderQueueService();
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public function notifyApprovalRequested(array $request): array
    {
        $settings = $this->settings();
        if (! $this->approvalNotificationsConfigured($settings)) {
            return ['status' => 'skipped', 'message' => 'ATTRAI approval notification is disabled or incomplete.'];
        }

        $requestId = (int) ($request['id'] ?? 0);
        $requestType = trim((string) ($request['request_type'] ?? ''));
        if ($requestId <= 0 || (string) ($request['status'] ?? '') !== 'pending') {
            return ['status' => 'skipped', 'message' => 'Approval request is not pending.'];
        }
        if ($requestType === 'followup' && $this->isChildOrderFollowup($request)) {
            return ['status' => 'skipped', 'message' => 'Child-order follow-up approval is represented by its parent order.'];
        }

        $actions = [
            'approve' => $this->signedAction($requestId, 'approve', $settings),
            'reject' => $this->signedAction($requestId, 'reject', $settings),
        ];
        if ($requestType === 'followup') {
            $actions['approve_no_point'] = $this->signedAction($requestId, 'approve_no_point', $settings);
        }

        $label = $this->approvalLabel($requestType);
        $summary = trim((string) ($request['summary'] ?? '')) ?: ($label . ' is waiting for approval.');
        $variables = [
            'approval_request_id' => (string) $requestId,
            'request_type' => $requestType,
            'request_label' => $label,
            'summary' => $summary,
            'requested_by_name' => trim((string) ($request['requested_by_name'] ?? '')),
            'approve_action_id' => $actions['approve'],
            'reject_action_id' => $actions['reject'],
            'approve_no_point_action_id' => $actions['approve_no_point'] ?? '',
            'webhook_url' => site_url('integrations/attrai/webhook'),
            'company_name' => trim((string) ($settings['company_name'] ?? '')),
        ] + $this->approvalDetailVariables($request);
        $mediaUrl = $this->approvalImageUrl($request, $settings);
        $variables['media_url'] = $mediaUrl;
        $variables['message_type'] = 'interactive';
        $messageTemplate = trim((string) ($settings['attrai_template_approval_requested'] ?? ''));
        $message = $messageTemplate !== ''
            ? $this->replacePlaceholders($messageTemplate, $variables)
            : $this->defaultApprovalMessage($variables, $requestType === 'followup');

        $queueIds = [];
        foreach ($this->alertPhones($settings) as $phone) {
            $perRecipient = $variables + ['to' => $phone, 'message' => $message];
            $requestPayload = $this->approvalRequestPayload($settings, $perRecipient, $actions);
            $queueIds[] = $this->queueService->enqueue([
                'event_key' => 'approval_requested',
                'provider_key' => 'attrai',
                'source_type' => 'mobile_approval_requests',
                'source_id' => $requestId,
                'order_id' => $this->orderIdFromApproval($request),
                'sender_number' => $settings['attrai_sender_id'] ?? null,
                'recipient_number' => $phone,
                'recipient_name' => 'Approval Admin',
                'message_type' => 'interactive',
                'message_text' => $message,
                'media_url' => $mediaUrl !== '' ? $mediaUrl : null,
                'request_payload' => $requestPayload,
                'scheduled_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return [
            'status' => $queueIds === [] ? 'skipped' : 'queued',
            'message' => $queueIds === [] ? 'No approval alert number is configured.' : 'ATTRAI approval message queued.',
            'queue_ids' => $queueIds,
        ];
    }

    /** @return array<string,mixed> */
    public function notifyKarigarAssigned(int $orderId, bool $includeKarigar = true, bool $includeFollower = true): array
    {
        $settings = $this->settings();
        if (! $this->baseConfigured($settings) || (int) ($settings['attrai_notify_karigar_assigned'] ?? 1) !== 1) {
            return ['status' => 'skipped', 'message' => 'ATTRAI karigar assignment notification is disabled or incomplete.'];
        }

        $row = db_connect()->table('orders o')
            ->select('o.id, o.order_no, o.order_name, o.priority, o.due_date, o.status, k.id AS karigar_id, k.name AS karigar_name, k.phone AS karigar_phone, k.attrai_number AS karigar_attrai_number, c.name AS customer_name, follower.name AS follower_login_name, e.full_name AS follower_name, e.mobile AS follower_mobile, e.attrai_number AS follower_attrai_number')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'inner')
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->join('admin_users follower', 'follower.id = o.followup_assigned_to', 'left')
            ->join('employees e', 'e.admin_user_id = o.followup_assigned_to AND e.is_active = 1', 'left')
            ->where('o.id', $orderId)
            ->get()->getRowArray();
        if (! is_array($row)) {
            return ['status' => 'skipped', 'message' => 'Assigned order not found.'];
        }
        $karigarPhone = $this->normalizePhone((string) (($row['karigar_attrai_number'] ?? '') ?: ($row['karigar_phone'] ?? '')));
        $followerPhone = $this->normalizePhone((string) (($row['follower_attrai_number'] ?? '') ?: ($row['follower_mobile'] ?? '')));
        $followerName = trim((string) (($row['follower_name'] ?? '') ?: ($row['follower_login_name'] ?? 'Follower')));
        $mediaUrl = $this->orderImageUrl($orderId, $settings);

        $variables = [
            'order_id' => (string) $orderId,
            'order_no' => trim((string) ($row['order_no'] ?? '')) ?: ('#' . $orderId),
            'order_name' => trim((string) ($row['order_name'] ?? '')),
            'customer_name' => trim((string) ($row['customer_name'] ?? '')),
            'karigar_name' => trim((string) ($row['karigar_name'] ?? 'Karigar')),
            'follower_name' => $followerName,
            'priority' => trim((string) ($row['priority'] ?? '')),
            'status' => trim((string) ($row['status'] ?? '')),
            'due_date' => trim((string) ($row['due_date'] ?? '')),
            'company_name' => trim((string) ($settings['company_name'] ?? '')),
            'media_url' => $mediaUrl,
            'message_type' => $mediaUrl !== '' ? 'image' : 'text',
        ];
        $template = trim((string) ($settings['attrai_template_karigar_assigned'] ?? ''));
        $message = $template !== ''
            ? $this->replacePlaceholders($template, $variables)
            : "Hello {$variables['karigar_name']}, order {$variables['order_no']} has been assigned to you.\n"
                . "Customer: " . ($variables['customer_name'] !== '' ? $variables['customer_name'] : '-') . "\n"
                . "Priority: " . ($variables['priority'] !== '' ? $variables['priority'] : '-') . "\n"
                . "Due date: " . ($variables['due_date'] !== '' ? $variables['due_date'] : '-');

        $followerTemplate = trim((string) ($settings['attrai_template_follower_assigned'] ?? ''));
        $followerMessage = $followerTemplate !== ''
            ? $this->replacePlaceholders($followerTemplate, $variables)
            : "Hello {$variables['follower_name']}, you are following order {$variables['order_no']}.\n"
                . "Karigar: " . ($variables['karigar_name'] !== '' ? $variables['karigar_name'] : '-') . "\n"
                . "Customer: " . ($variables['customer_name'] !== '' ? $variables['customer_name'] : '-') . "\n"
                . "Due date: " . ($variables['due_date'] !== '' ? $variables['due_date'] : '-');

        $recipients = [];
        if ($includeKarigar && $karigarPhone !== '') {
            $recipients[] = ['event' => 'karigar_assigned', 'phone' => $karigarPhone, 'name' => $variables['karigar_name'], 'message' => $message];
        }
        if ($includeFollower && $followerPhone !== '') {
            $recipients[] = ['event' => 'follower_assigned', 'phone' => $followerPhone, 'name' => $followerName, 'message' => $followerMessage];
        }
        if ($recipients === []) {
            return ['status' => 'skipped', 'message' => 'Karigar and follower ATTRAI numbers are not available.'];
        }

        $queueIds = [];
        $dispatches = [];
        foreach ($recipients as $recipient) {
            $queueId = $this->queueService->enqueue([
                'event_key' => $recipient['event'],
                'provider_key' => 'attrai',
                'source_type' => 'orders',
                'source_id' => $orderId,
                'order_id' => $orderId,
                'sender_number' => $settings['attrai_sender_id'] ?? null,
                'recipient_number' => $recipient['phone'],
                'recipient_name' => $recipient['name'],
                'message_type' => $mediaUrl !== '' ? 'image' : 'text',
                'message_text' => $recipient['message'],
                'media_url' => $mediaUrl !== '' ? $mediaUrl : null,
                'scheduled_at' => date('Y-m-d H:i:s'),
            ]);
            $queueIds[] = $queueId;
            $dispatches[] = $this->queueService->dispatchById($queueId);
        }

        $sent = count(array_filter($dispatches, static fn(array $dispatch): bool => ($dispatch['status'] ?? '') === 'sent'));
        return [
            'status' => $sent === count($dispatches) ? 'sent' : 'queued',
            'message' => 'Karigar/follower assignment messages processed.',
            'queue_ids' => $queueIds,
            'dispatches' => $dispatches,
        ];
    }

    /** @return array<string,mixed> */
    public function notifyFollowerAssigned(int $orderId): array
    {
        return $this->notifyKarigarAssigned($orderId, false, true);
    }

    /** @return array<string,mixed> */
    public function sendTestMessage(string $phone): array
    {
        $settings = $this->settings();
        if (! $this->baseConfigured($settings)) {
            throw new RuntimeException('Enable ATTRAI, then save its API URL and credentials first.');
        }
        $phone = $this->normalizePhone($phone);
        if ($phone === '') {
            throw new RuntimeException('Enter a valid demo mobile number.');
        }
        $queueId = $this->queueService->enqueue([
            'event_key' => 'attrai_demo',
            'provider_key' => 'attrai',
            'source_type' => 'company_settings',
            'sender_number' => $settings['attrai_sender_id'] ?? null,
            'recipient_number' => $phone,
            'recipient_name' => 'ATTRAI Demo',
            'message_type' => 'text',
            'message_text' => 'ATTRAI integration demo from ' . (trim((string) ($settings['company_name'] ?? '')) ?: 'Jewellery ERP') . '. Order updates and approval notifications are connected.',
            'scheduled_at' => date('Y-m-d H:i:s'),
            'created_by' => (int) session('admin_id') ?: null,
        ]);
        $dispatch = $this->queueService->dispatchById($queueId);
        if (($dispatch['status'] ?? '') !== 'sent') {
            throw new RuntimeException('Demo message could not be sent. Check the queue error and ATTRAI API settings.');
        }

        return ['status' => 'sent', 'queue_id' => $queueId, 'recipient_number' => $phone];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function handleWebhook(array $payload, string $providedSecret): array
    {
        $settings = $this->settings();
        if ((int) ($settings['attrai_enabled'] ?? 0) !== 1) {
            throw new RuntimeException('ATTRAI integration is disabled.');
        }
        $secret = trim((string) ($settings['attrai_webhook_secret'] ?? ''));
        if ($secret === '' || $providedSecret === '' || ! hash_equals($secret, $providedSecret)) {
            throw new RuntimeException('Invalid ATTRAI webhook secret.');
        }

        $actionId = $this->extractActionId($payload);
        if ($actionId === '') {
            throw new RuntimeException('ATTRAI action id was not found in the webhook payload.');
        }
        $action = $this->verifySignedAction($actionId, $settings);
        $reviewerId = (int) ($settings['attrai_approval_reviewer_id'] ?? 0);
        if (! $this->isActiveReviewer($reviewerId)) {
            throw new RuntimeException('Configure an active admin reviewer for ATTRAI approvals.');
        }

        $service = new MobileApprovalService();
        $request = $service->find((int) $action['request_id']);
        if (! is_array($request)) {
            throw new RuntimeException('Approval request not found.');
        }
        if ((string) ($request['status'] ?? '') !== 'pending') {
            return [
                'status' => 'already_reviewed',
                'approval_request_id' => (int) $action['request_id'],
                'approval_status' => (string) ($request['status'] ?? ''),
            ];
        }

        $note = 'Reviewed from ATTRAI app';
        $decision = (string) $action['decision'];
        $reviewed = $decision === 'reject'
            ? $service->reject((int) $action['request_id'], $reviewerId, $note)
            : $service->approve(
                (int) $action['request_id'],
                $reviewerId,
                $note,
                $decision !== 'approve_no_point'
            );

        return [
            'status' => 'processed',
            'decision' => $decision,
            'approval_request_id' => (int) $action['request_id'],
            'approval_status' => (string) ($reviewed['status'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $settings */
    private function signedAction(int $requestId, string $decision, array $settings): string
    {
        $secret = trim((string) ($settings['attrai_webhook_secret'] ?? ''));
        if ($secret === '') {
            throw new RuntimeException('ATTRAI webhook secret is required before approval buttons can be sent.');
        }
        $body = $this->base64UrlEncode(json_encode([
            'request_id' => $requestId,
            'decision' => $decision,
            'expires_at' => time() + 604800,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $body, $secret, true));
        return 'attrai.' . $body . '.' . $signature;
    }

    /** @param array<string,mixed> $settings @return array{request_id:int,decision:string} */
    private function verifySignedAction(string $actionId, array $settings): array
    {
        $parts = explode('.', trim($actionId));
        if (count($parts) !== 3 || $parts[0] !== 'attrai') {
            throw new RuntimeException('Invalid ATTRAI approval action.');
        }
        $secret = trim((string) ($settings['attrai_webhook_secret'] ?? ''));
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $parts[1], $secret, true));
        if (! hash_equals($expected, $parts[2])) {
            throw new RuntimeException('ATTRAI approval action signature is invalid.');
        }
        $decoded = json_decode($this->base64UrlDecode($parts[1]), true, 512, JSON_THROW_ON_ERROR);
        $requestId = (int) ($decoded['request_id'] ?? 0);
        $decision = (string) ($decoded['decision'] ?? '');
        if ($requestId <= 0 || ! in_array($decision, ['approve', 'approve_no_point', 'reject'], true)) {
            throw new RuntimeException('ATTRAI approval action is invalid.');
        }
        if ((int) ($decoded['expires_at'] ?? 0) < time()) {
            throw new RuntimeException('ATTRAI approval action has expired.');
        }
        return ['request_id' => $requestId, 'decision' => $decision];
    }

    /** @param array<string,mixed> $payload */
    private function extractActionId(array $payload): string
    {
        foreach (['action_id', 'actionId', 'button_id', 'buttonId', 'callback_data', 'callbackData', 'payload', 'id'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key])) {
                $value = trim((string) $payload[$key]);
                if (str_starts_with($value, 'attrai.')) {
                    return $value;
                }
            }
        }
        foreach ($payload as $value) {
            if (is_array($value)) {
                $found = $this->extractActionId($value);
                if ($found !== '') {
                    return $found;
                }
            }
        }
        return '';
    }

    /** @param array<string,mixed> $settings */
    private function approvalRequestPayload(array $settings, array $variables, array $actions): string
    {
        $variables['actions_json'] = json_encode(array_map(
            static fn(string $decision, string $id): array => [
                'id' => $id,
                'title' => match ($decision) {
                    'approve' => 'Approve',
                    'approve_no_point' => 'Approve - No Point',
                    default => 'Disapprove',
                },
            ],
            array_keys($actions),
            array_values($actions)
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';

        foreach ($variables as $key => $value) {
            $variables[$key . '_json'] = json_encode((string) $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""';
        }
        $template = trim((string) ($settings['attrai_approval_body_template'] ?? ''));
        if ($template !== '') {
            return $this->replacePlaceholders($template, $variables);
        }

        return json_encode([
            'to' => (string) ($variables['to'] ?? ''),
            'message' => (string) ($variables['message'] ?? ''),
            'sender' => trim((string) ($settings['attrai_sender_id'] ?? '')) ?: null,
            'event' => 'approval_requested',
            'reference' => 'approval-' . (string) ($variables['approval_request_id'] ?? ''),
            'actions' => json_decode((string) $variables['actions_json'], true),
            'webhook_url' => (string) ($variables['webhook_url'] ?? ''),
            'message_type' => (string) ($variables['message_type'] ?? 'interactive'),
            'media_url' => trim((string) ($variables['media_url'] ?? '')) ?: null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @param array<string,string> $variables */
    private function defaultApprovalMessage(array $variables, bool $allowNoPoint): string
    {
        $message = "*Approval Required: {$variables['request_label']}*\n"
            . "Request: #{$variables['approval_request_id']}\n"
            . $variables['summary'];
        if (trim((string) ($variables['details'] ?? '')) !== '') {
            $message .= "\n" . $variables['details'];
        }
        if (trim((string) ($variables['requested_by_name'] ?? '')) !== '') {
            $message .= "\nSubmitted by: " . $variables['requested_by_name'];
        }
        $message .= "\n\nSelect *Approve* or *Disapprove* in ATTRAI.";
        if ($allowNoPoint) {
            $message .= '\nFor follow-up, *Approve - No Point* is also available.';
        }
        return $message;
    }

    /** @param array<string,mixed> $request @return array<string,string> */
    private function approvalDetailVariables(array $request): array
    {
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
        $type = (string) ($request['request_type'] ?? '');
        $variables = [
            'order_no' => trim((string) ($payload['order_no'] ?? '')),
            'stage' => trim((string) ($payload['stage'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'next_followup_date' => trim((string) ($payload['next_followup_date'] ?? '')),
            'karigar_name' => trim((string) ($payload['karigar_name'] ?? '')),
            'location_name' => trim((string) ($payload['location_name'] ?? '')),
            'purpose' => trim((string) ($payload['purpose'] ?? '')),
            'party_name' => trim((string) ($payload['name'] ?? '')),
            'phone' => trim((string) ($payload['phone'] ?? '')),
            'department' => trim((string) ($payload['department'] ?? '')),
            'material_summary' => $this->materialSummary($payload),
            'details' => '',
        ];

        $lines = [];
        if ($type === 'followup') {
            if ($variables['order_no'] !== '') $lines[] = 'Order: ' . $variables['order_no'];
            if ($variables['stage'] !== '') $lines[] = 'Stage: ' . $variables['stage'];
            if ($variables['description'] !== '') $lines[] = 'Update: ' . $variables['description'];
            if ($variables['next_followup_date'] !== '') $lines[] = 'Next follow-up: ' . $variables['next_followup_date'];
        } elseif ($type === 'issuement') {
            if ($variables['karigar_name'] !== '') $lines[] = 'Karigar: ' . $variables['karigar_name'];
            if ($variables['location_name'] !== '') $lines[] = 'Location: ' . $variables['location_name'];
            if ($variables['purpose'] !== '') $lines[] = 'Purpose: ' . $variables['purpose'];
            if ($variables['material_summary'] !== '') $lines[] = 'Material: ' . $variables['material_summary'];
        } elseif (in_array($type, ['customer_create', 'karigar_create'], true)) {
            if ($variables['party_name'] !== '') $lines[] = 'Name: ' . $variables['party_name'];
            if ($variables['phone'] !== '') $lines[] = 'Phone: ' . $variables['phone'];
            if ($variables['department'] !== '') $lines[] = 'Department: ' . $variables['department'];
        }
        $variables['details'] = implode("\n", $lines);
        return $variables;
    }

    /** @param array<string,mixed> $payload */
    private function materialSummary(array $payload): string
    {
        $parts = [];
        foreach (['gold' => 'Gold', 'diamond' => 'Diamond', 'stone' => 'Stone'] as $key => $label) {
            $lines = is_array($payload[$key . '_lines'] ?? null) ? $payload[$key . '_lines'] : [];
            if ($lines !== []) {
                $parts[] = $label . ' ' . count($lines) . ' line(s)';
            }
        }
        return implode(', ', $parts);
    }

    private function approvalLabel(string $type): string
    {
        return match ($type) {
            'followup' => 'Follow-up',
            'issuement' => 'Issuement',
            'customer_create' => 'Customer creation',
            'karigar_create' => 'Karigar creation',
            'delivery_challan' => 'Delivery challan',
            default => 'ERP request',
        };
    }

    /** @param array<string,mixed> $request */
    private function orderIdFromApproval(array $request): ?int
    {
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
        $id = (int) ($payload['order_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    /** @param array<string,mixed> $request @param array<string,mixed> $settings */
    private function approvalImageUrl(array $request, array $settings): string
    {
        $attachmentUrl = trim((string) ($request['attachment_url'] ?? ''));
        if ($attachmentUrl !== '') {
            return $attachmentUrl;
        }
        $attachmentPath = trim((string) ($request['attachment_path'] ?? ''));
        if ($attachmentPath !== '') {
            return base_url(ltrim($attachmentPath, '/'));
        }
        $orderId = $this->orderIdFromApproval($request) ?? 0;
        return $orderId > 0 ? $this->orderImageUrl($orderId, $settings) : $this->companyLogoUrl($settings);
    }

    /** @param array<string,mixed> $settings */
    private function orderImageUrl(int $orderId, array $settings): string
    {
        $images = (new OrderThumbnailService())->map([$orderId], false);
        return trim((string) ($images[$orderId] ?? '')) ?: $this->companyLogoUrl($settings);
    }

    /** @param array<string,mixed> $settings */
    private function companyLogoUrl(array $settings): string
    {
        $logo = trim((string) ($settings['logo_path'] ?? ''));
        return $logo !== '' ? base_url(ltrim($logo, '/')) : '';
    }

    /** @param array<string,mixed> $request */
    private function isChildOrderFollowup(array $request): bool
    {
        $orderId = $this->orderIdFromApproval($request) ?? 0;
        $db = db_connect();
        if ($orderId <= 0 || ! $db->fieldExists('parent_order_id', 'orders')) {
            return false;
        }
        $row = $db->table('orders')->select('parent_order_id')->where('id', $orderId)->get()->getRowArray();
        return (int) ($row['parent_order_id'] ?? 0) > 0;
    }

    private function isActiveReviewer(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $db = db_connect();
        if ($db->table('admin_users')->where('id', $userId)->where('is_active', 1)->countAllResults() !== 1) {
            return false;
        }
        if (! $db->tableExists('user_roles') || ! $db->tableExists('roles')) {
            return true;
        }
        return $db->table('user_roles ur')
            ->join('roles r', 'r.id = ur.role_id', 'inner')
            ->where('ur.user_id', $userId)
            ->whereIn('r.role_code', ['SUPER_ADMIN', 'ADMIN', 'OWNER'])
            ->countAllResults() > 0;
    }

    /** @param array<string,mixed> $settings */
    private function approvalNotificationsConfigured(array $settings): bool
    {
        return $this->baseConfigured($settings)
            && (int) ($settings['attrai_notify_approval_requested'] ?? 1) === 1
            && trim((string) ($settings['attrai_webhook_secret'] ?? '')) !== ''
            && (int) ($settings['attrai_approval_reviewer_id'] ?? 0) > 0
            && $this->alertPhones($settings) !== [];
    }

    /** @param array<string,mixed> $settings */
    private function baseConfigured(array $settings): bool
    {
        return (int) ($settings['attrai_enabled'] ?? 0) === 1
            && trim((string) ($settings['attrai_api_url'] ?? '')) !== '';
    }

    /** @param array<string,mixed> $settings @return list<string> */
    private function alertPhones(array $settings): array
    {
        $parts = preg_split('/[\s,;]+/', trim((string) ($settings['attrai_approval_numbers'] ?? ''))) ?: [];
        $phones = [];
        foreach ($parts as $part) {
            $phone = $this->normalizePhone($part);
            if ($phone !== '') {
                $phones[] = $phone;
            }
        }
        return array_values(array_unique($phones));
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', trim($phone)) ?? '';
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }
        return $phone;
    }

    /** @param array<string,string> $variables */
    private function replacePlaceholders(string $template, array $variables): string
    {
        return preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', static function (array $match) use ($variables): string {
            return array_key_exists($match[1], $variables) ? (string) $variables[$match[1]] : '';
        }, $template) ?? $template;
    }

    /** @return array<string,mixed> */
    private function settings(): array
    {
        return $this->companySettingModel->orderBy('id', 'ASC')->first() ?? [];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('ATTRAI approval action encoding is invalid.');
        }
        return $decoded;
    }
}
