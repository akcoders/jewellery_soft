<?php

namespace App\Services;

use App\Models\CompanySettingModel;
use RuntimeException;

class AttraiIntegrationService
{
    private CompanySettingModel $companySettingModel;
    private WhatsappSenderQueueService $queueService;
    private ArattaiUserMappingService $userMappingService;

    public function __construct(
        ?CompanySettingModel $companySettingModel = null,
        ?WhatsappSenderQueueService $queueService = null,
        ?ArattaiUserMappingService $userMappingService = null
    ) {
        $this->companySettingModel = $companySettingModel ?? new CompanySettingModel();
        $this->queueService = $queueService ?? new WhatsappSenderQueueService();
        $this->userMappingService = $userMappingService ?? new ArattaiUserMappingService();
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
        foreach ($this->approvalUserIds($settings) as $userId) {
            $perRecipient = $variables + ['to' => $userId, 'message' => $message];
            $requestPayload = $this->approvalRequestPayload($settings, $perRecipient, $actions);
            $queueIds[] = $this->queueService->enqueue([
                'event_key' => 'approval_requested',
                'provider_key' => 'attrai',
                'source_type' => 'mobile_approval_requests',
                'source_id' => $requestId,
                'order_id' => $this->orderIdFromApproval($request),
                'sender_number' => $settings['attrai_sender_id'] ?? null,
                'recipient_number' => $userId,
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
            'message' => $queueIds === [] ? 'No approval admin Arattai user ID is configured.' : 'Arattai approval message queued.',
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

        $db = db_connect();
        $row = $db->table('orders o')
            ->select('o.id, o.order_no, o.order_name, o.priority, o.due_date, o.status, o.order_notes, o.additional_details, o.followup_assigned_to, k.id AS karigar_id, k.name AS karigar_name, c.name AS customer_name, follower.name AS follower_login_name, e.full_name AS follower_name')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'inner')
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->join('admin_users follower', 'follower.id = o.followup_assigned_to', 'left')
            ->join('employees e', 'e.admin_user_id = o.followup_assigned_to AND e.is_active = 1', 'left')
            ->where('o.id', $orderId)
            ->get()->getRowArray();
        if (! is_array($row)) {
            return ['status' => 'skipped', 'message' => 'Assigned order not found.'];
        }
        $itemRows = $db->table('order_items oi')
            ->select('oi.item_description, oi.size_label, oi.qty, dm.name AS design_name, dv.ring_size, dv.bangle_size, dv.length_mm')
            ->join('design_masters dm', 'dm.id = oi.design_id', 'left')
            ->join('design_variants dv', 'dv.id = oi.variant_id', 'left')
            ->where('oi.order_id', $orderId)->orderBy('oi.id', 'ASC')->get()->getResultArray();
        $itemDetails = [];
        foreach ($itemRows as $item) {
            $sizes = array_values(array_filter([
                trim((string) ($item['size_label'] ?? '')),
                trim((string) ($item['ring_size'] ?? '')) !== '' ? 'Ring ' . trim((string) $item['ring_size']) : '',
                trim((string) ($item['bangle_size'] ?? '')) !== '' ? 'Bangle ' . trim((string) $item['bangle_size']) : '',
                (float) ($item['length_mm'] ?? 0) > 0 ? number_format((float) $item['length_mm'], 2) . ' mm' : '',
            ]));
            $label = trim((string) (($item['design_name'] ?? '') ?: ($item['item_description'] ?? 'Item')));
            $itemDetails[] = '• ' . $label
                . ($sizes !== [] ? ' | Size: ' . implode(', ', array_unique($sizes)) : '')
                . ' | Qty: ' . max(1, (int) ($item['qty'] ?? 1));
        }
        $karigarPhone = $this->userMappingService->karigarUserId((int) ($row['karigar_id'] ?? 0));
        $followerPhone = $this->userMappingService->followerUserId((int) ($row['followup_assigned_to'] ?? 0));
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
            'item_details' => implode("\n", $itemDetails),
            'description' => trim((string) (($row['additional_details'] ?? '') ?: ($row['order_notes'] ?? ''))),
        ];
        $template = trim((string) ($settings['attrai_template_karigar_assigned'] ?? ''));
        $message = $template !== ''
            ? $this->replacePlaceholders($template, $variables)
            : "*New Order Assigned*\n"
                . "Order: {$variables['order_no']}\n"
                . "Work: " . ($variables['order_name'] !== '' ? $variables['order_name'] : '-') . "\n"
                . ($variables['item_details'] !== '' ? $variables['item_details'] . "\n" : '')
                . ($variables['description'] !== '' ? "Description: {$variables['description']}\n" : '')
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
                'request_payload' => $this->displayRequestPayload(
                    (string) $recipient['phone'],
                    (string) $recipient['message'],
                    $mediaUrl
                ),
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
            throw new RuntimeException('Enter a valid Arattai User ID.');
        }
        $image = $this->companyLogoUrl($settings);
        $message = 'Arattai integration demo from ' . (trim((string) ($settings['company_name'] ?? '')) ?: 'Jewellery ERP')
            . ".\nTap the button below to test the incoming order-status flow.";
        $payload = $this->quickReplyPayload($phone, $message, [
            ['id' => 'preview.order_status', 'label' => 'Check Order Status'],
        ], $image);
        $queueId = $this->queueService->enqueue([
            'event_key' => 'attrai_demo',
            'provider_key' => 'attrai',
            'source_type' => 'company_settings',
            'sender_number' => $settings['attrai_sender_id'] ?? null,
            'recipient_number' => $phone,
            'recipient_name' => 'ATTRAI Demo',
            'message_type' => 'interactive',
            'message_text' => $message,
            'media_url' => $image !== '' ? $image : null,
            'request_payload' => $payload,
            'scheduled_at' => date('Y-m-d H:i:s'),
            'created_by' => null,
        ]);
        $dispatch = $this->queueService->dispatchById($queueId);
        if (($dispatch['status'] ?? '') !== 'sent') {
            $error = trim((string) ($dispatch['error_message'] ?? ''));
            throw new RuntimeException('Demo message could not be sent' . ($error !== '' ? ': ' . mb_substr($error, 0, 500) : '.'));
        }

        return ['status' => 'sent', 'queue_id' => $queueId, 'recipient_number' => $phone];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function verifyWebhookSignature(string $rawBody, string $providedSignature): void
    {
        $settings = $this->settings();
        $secret = trim((string) ($settings['attrai_webhook_secret'] ?? ''));
        if ($secret === '' || $providedSignature === '') {
            throw new RuntimeException('Arattai webhook signature or Shared Secret is missing.');
        }
        if (! str_starts_with(strtolower($providedSignature), 'sha256=')) {
            throw new RuntimeException('Invalid Arattai webhook signature format.');
        }
        $provided = trim(substr($providedSignature, 7));
        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
        if ($provided === '' || ! hash_equals($expected, $provided)) {
            throw new RuntimeException('Invalid Arattai webhook signature.');
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function handleWebhook(array $payload): array
    {
        $settings = $this->settings();
        if ((int) ($settings['attrai_enabled'] ?? 0) !== 1) {
            throw new RuntimeException('Arattai integration is disabled.');
        }

        $event = trim((string) ($payload['event'] ?? ''));
        if (in_array($event, ['chat.message.sent', 'chat.message.delivered', 'chat.message.failed', 'chat.message.deleted'], true)) {
            return $this->queueService->applyArattaiDeliveryEvent($event, $payload);
        }

        $actionId = $this->extractActionId($payload);
        if ($actionId === '') {
            $actionId = $this->resolveQuickReplyAction($payload);
        }
        if ($actionId === '') {
            if ($this->quickReplyDecision($payload, false) !== '') {
                $this->replyToWebhookUser(
                    $payload,
                    'Approval action could not be matched to its request. Please tap Approve or Disapprove on the latest approval card again.'
                );
                return ['status' => 'approval_context_missing', 'message' => 'Approval reply did not contain a usable request reference.'];
            }
            return $this->handleIncomingConversation($payload);
        }
        $actionUserId = $this->webhookUserId($payload);
        if ($actionUserId === '' || ! in_array($actionUserId, $this->approvalUserIds($settings), true)) {
            throw new RuntimeException('This Arattai user is not authorised to review ERP approvals.');
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
            $this->replyToWebhookUser($payload, 'Action already taken: This request is already ' . (string) ($request['status'] ?? 'reviewed') . '.');
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

        $approved = $decision !== 'reject';
        $decisionLabel = $decision === 'approve_no_point'
            ? 'approved successfully without points'
            : ($approved ? 'approved successfully' : 'disapproved successfully');
        $this->replyToWebhookUser(
            $payload,
            $this->approvalLabel((string) ($request['request_type'] ?? ''))
                . ' request #' . (int) $action['request_id'] . ' was ' . $decisionLabel . '. ERP data has been updated.'
        );
        if ($approved && (string) ($request['request_type'] ?? '') === 'issuement') {
            $requesterUserId = $this->userIdForAdmin((int) ($request['requested_by'] ?? 0));
            $voucherNo = trim((string) ($reviewed['result_reference'] ?? ''));
            if ($voucherNo !== '' && ! hash_equals($requesterUserId, $actionUserId)) {
                $this->sendConversationDocument(
                    'issuement_approved_pdf',
                    $actionUserId,
                    'Approved Issuement · ' . $voucherNo,
                    base_url('api/documents/issuements/' . rawurlencode($voucherNo)),
                    'mobile_approval_requests',
                    (int) $action['request_id'],
                    $this->orderIdFromApproval($reviewed)
                );
            }
        }

        return [
            'status' => 'processed',
            'decision' => $decision,
            'approval_request_id' => (int) $action['request_id'],
            'approval_status' => (string) ($reviewed['status'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $request */
    public function notifyApprovalOutcome(array $request, string $decision): array
    {
        $requestedBy = (int) ($request['requested_by'] ?? 0);
        $userId = $this->userIdForAdmin($requestedBy);
        if ($userId === '') {
            return ['status' => 'skipped', 'message' => 'Requester Arattai user ID is not configured.'];
        }
        $type = (string) ($request['request_type'] ?? '');
        $label = $this->approvalLabel($type);
        $isRejected = $decision === 'reject';
        $points = $type === 'followup'
            ? ($isRejected ? StaffPerformanceService::DISAPPROVED_FOLLOWUP_POINTS : ($decision === 'approve_no_point' ? 0.0 : StaffPerformanceService::PROPER_FOLLOWUP_POINTS))
            : 0.0;
        $message = $isRejected
            ? "*{$label} Disapproved*\nYour request was disapproved."
            : "*{$label} Approved Successfully*\nYour request has been approved.";
        if ($type === 'followup') {
            $message .= $points > 0
                ? "\nCongratulations! You received +" . $this->formatPoints($points) . ' points.'
                : ($points < 0
                    ? "\n" . $this->formatPoints($points) . ' points deducted.'
                    : "\nApproved as No Point: +0 points.");
        }
        $note = trim((string) ($request['review_note'] ?? ''));
        if ($note !== '') {
            $message .= "\nAdmin note: " . $note;
        }
        $result = $this->sendConversationMessage(
            'approval_outcome',
            $userId,
            $message,
            'mobile_approval_requests',
            (int) ($request['id'] ?? 0),
            $this->orderIdFromApproval($request),
            trim((string) ($request['attachment_url'] ?? ''))
        );
        if (! $isRejected && $type === 'issuement') {
            $voucherNo = trim((string) ($request['result_reference'] ?? ''));
            if ($voucherNo !== '') {
                try {
                    $this->sendConversationDocument(
                        'issuement_approved_pdf',
                        $userId,
                        'Approved Issuement · ' . $voucherNo,
                        base_url('api/documents/issuements/' . rawurlencode($voucherNo)),
                        'mobile_approval_requests',
                        (int) ($request['id'] ?? 0),
                        $this->orderIdFromApproval($request)
                    );
                } catch (Throwable $e) {
                    log_message('error', 'Arattai approved issuement PDF failed: {message}', ['message' => $e->getMessage()]);
                }
            }
        }
        return $result;
    }

    public function notifyPointChange(int $adminUserId, float $points, string $reason, string $reference = '', ?int $orderId = null): array
    {
        $userId = $this->userIdForAdmin($adminUserId);
        if ($userId === '' || abs($points) < 0.0001) {
            return ['status' => 'skipped', 'message' => 'Arattai user ID is missing or point change is zero.'];
        }
        $message = $points > 0
            ? '*Congratulations! Points Received*'
            : '*Performance Points Deducted*';
        $message .= "\n" . ($points > 0 ? '+' : '') . $this->formatPoints($points) . ' points';
        $message .= "\nReason: " . trim($reason);
        if (trim($reference) !== '') {
            $message .= "\nReference: " . trim($reference);
        }
        return $this->sendConversationMessage(
            'points_changed',
            $userId,
            $message,
            'admin_users',
            $adminUserId,
            $orderId
        );
    }

    /** @param array<string,mixed> $context */
    public function notifyIssuementToKarigar(string $voucherNo, int $karigarId, array $context = []): array
    {
        $db = db_connect();
        $userId = $this->userMappingService->karigarUserId($karigarId);
        if ($userId === '') {
            return ['status' => 'skipped', 'message' => 'Karigar Arattai user ID is not configured.'];
        }
        $parts = [];
        foreach (['gold' => ['gold_inventory_issue_headers', 'gold_inventory_issue_lines', 'weight_gm', 'gm'], 'diamond' => ['issue_headers', 'issue_lines', 'carat', 'cts'], 'stone' => ['stone_inventory_issue_headers', 'stone_inventory_issue_lines', 'qty', 'cts']] as $label => $definition) {
            [$headerTable, $lineTable, $quantityField, $unit] = $definition;
            if (! $db->tableExists($headerTable) || ! $db->tableExists($lineTable)) {
                continue;
            }
            $header = $db->table($headerTable)->select('id')->where('voucher_no', $voucherNo)->where('karigar_id', $karigarId)->get()->getRowArray();
            if (! is_array($header)) {
                continue;
            }
            $sum = $db->table($lineTable)->selectSum($quantityField, 'quantity')->where('issue_id', (int) $header['id'])->get()->getRowArray();
            $parts[] = ucfirst($label) . ': ' . number_format((float) ($sum['quantity'] ?? 0), 3) . ' ' . $unit;
        }
        $message = "*Material Issued to You*\nVoucher: {$voucherNo}";
        if ($parts !== []) {
            $message .= "\n" . implode("\n", $parts);
        }
        if (trim((string) ($context['purpose'] ?? '')) !== '') {
            $message .= "\nPurpose: " . trim((string) $context['purpose']);
        }
        if (trim((string) ($context['order_no'] ?? '')) !== '') {
            $message .= "\nOrder: " . trim((string) $context['order_no']);
        }
        $mediaUrl = trim((string) ($context['media_url'] ?? ''));
        if ($mediaUrl === '') {
            $mediaUrl = $this->issuementImageUrl($voucherNo, $karigarId);
        }
        return $this->sendConversationMessage(
            'issuement_to_karigar',
            $userId,
            $message,
            'issuement_voucher',
            (int) ($context['source_id'] ?? 0),
            isset($context['order_id']) ? (int) $context['order_id'] : null,
            $mediaUrl
        );
    }

    public function notifyKarigarPayment(int $karigarId, float $amount, string $paymentNo, string $paymentDate, string $reference = ''): array
    {
        $userId = $this->userMappingService->karigarUserId($karigarId);
        if ($userId === '') {
            return ['status' => 'skipped', 'message' => 'Karigar Arattai user ID is not configured.'];
        }
        $message = "*Payment Received*\nAmount: ₹" . number_format($amount, 2)
            . "\nPayment No: " . trim($paymentNo)
            . "\nDate: " . trim($paymentDate);
        if (trim($reference) !== '') {
            $message .= "\nReference: " . trim($reference);
        }
        return $this->sendConversationMessage(
            'karigar_payment', $userId, $message, 'karigars', $karigarId, null, $this->companyLogoUrl($this->settings())
        );
    }

    public function notifyOrderStatusToFollower(int $orderId, string $message = ''): array
    {
        $db = db_connect();
        $order = $db->table('orders')->select('id, order_no, order_name, status, due_date, followup_assigned_to')->where('id', $orderId)->get()->getRowArray();
        if (! is_array($order)) {
            return ['status' => 'skipped', 'message' => 'Order not found.'];
        }
        $userId = $this->userMappingService->followerUserId((int) ($order['followup_assigned_to'] ?? 0));
        if ($userId === '') {
            return ['status' => 'skipped', 'message' => 'Follower Arattai user ID is not configured.'];
        }
        $body = "*Order Update*\nOrder: " . ((string) ($order['order_no'] ?? '') ?: ('#' . $orderId))
            . "\nStatus: " . ((string) ($order['status'] ?? '') ?: '-')
            . "\nDue: " . ((string) ($order['due_date'] ?? '') ?: '-');
        if (trim($message) !== '') {
            $body .= "\nUpdate: " . trim($message);
        }
        return $this->sendConversationMessage('order_update_follower', $userId, $body, 'orders', $orderId, $orderId, $this->orderImageUrl($orderId, $this->settings()));
    }

    /** @return array{scanned:int,queued:int} */
    public function queueFollowupReminders(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
        $db = db_connect();
        $rows = $db->table('orders o')
            ->select('o.id, o.order_no, o.status, o.followup_due_at, o.followup_assigned_to')
            ->where('o.followup_due_at IS NOT NULL', null, false)
            ->whereNotIn('o.status', ['Ready', 'Complete', 'Completed', 'Packed', 'Delivered', 'Dispatched', 'Cancelled'])
            ->where('o.followup_due_at <=', $now->modify('+24 hours')->format('Y-m-d H:i:s'))
            ->where("NOT EXISTS (SELECT 1 FROM mobile_approval_requests mar INNER JOIN order_followups pending_followup ON pending_followup.id = mar.subject_id WHERE mar.request_type = 'followup' AND mar.status = 'pending' AND mar.subject_table = 'order_followups' AND pending_followup.order_id = o.id)", null, false)
            ->get()->getResultArray();
        $queued = 0;
        foreach ($rows as $row) {
            $userId = $this->userMappingService->followerUserId((int) ($row['followup_assigned_to'] ?? 0));
            $due = strtotime((string) ($row['followup_due_at'] ?? '')) ?: 0;
            if ($userId === '' || $due <= 0) {
                continue;
            }
            $overdue = $due < $now->getTimestamp();
            $delayDays = $overdue ? max(1, (int) ceil(($now->getTimestamp() - $due) / 86400)) : 0;
            $slot = $overdue ? ('D' . $delayDays) : date('YmdH', $due);
            $event = $overdue ? 'followup_overdue' : 'followup_upcoming';
            if ($db->table('whatsapp_sender_queue')->where('provider_key', 'attrai')->where('event_key', $event)
                ->where('source_type', 'orders')->where('source_id', (int) $row['id'])
                ->where('recipient_number', $userId)->like('message_no', $slot)->countAllResults() > 0) {
                continue;
            }
            $body = $overdue ? '*Follow-up Overdue*' : '*Upcoming Follow-up*';
            $body .= "\nOrder: " . (string) $row['order_no']
                . "\nDue: " . date('d M Y, h:i A', $due);
            if ($overdue) {
                $body .= "\nDelay: " . $delayDays . ' day(s)'
                    . "\nPerformance impact: -2 points for this delay tier";
            }
            $result = $this->sendConversationMessage(
                $event,
                $userId,
                $body,
                'orders',
                (int) $row['id'],
                (int) $row['id'],
                $this->orderImageUrl((int) $row['id'], $this->settings()),
                false,
                $slot
            );
            $queued += ($result['status'] ?? '') === 'queued' ? 1 : 0;
        }
        return ['scanned' => count($rows), 'queued' => $queued];
    }

    /** @return array{scanned:int,queued:int} */
    public function queueOverdueTaskPointAlerts(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
        $db = db_connect();
        if (! $db->tableExists('mobile_tasks')) {
            return ['scanned' => 0, 'queued' => 0];
        }
        $rows = $db->table('mobile_tasks t')
            ->select('t.id, t.admin_user_id, t.title, t.scheduled_at')
            ->where('t.counts_for_performance', 1)
            ->where('t.reference_type', null)
            ->where('t.is_done', 0)
            ->where('t.status !=', 'cancelled')
            ->where('t.scheduled_at <', $now->format('Y-m-d H:i:s'))
            ->get()->getResultArray();
        $queued = 0;
        foreach ($rows as $row) {
            $userId = $this->userMappingService->adminUserId((int) ($row['admin_user_id'] ?? 0));
            if ($userId === '') {
                continue;
            }
            $due = strtotime((string) ($row['scheduled_at'] ?? '')) ?: $now->getTimestamp();
            $delayDays = max(1, (int) ceil(($now->getTimestamp() - $due) / 86400));
            $slot = 'D' . $delayDays;
            if ($db->table('whatsapp_sender_queue')->where('provider_key', 'attrai')
                ->where('event_key', 'task_overdue_points')->where('source_type', 'mobile_tasks')
                ->where('source_id', (int) $row['id'])->where('recipient_number', $userId)
                ->like('message_no', $slot)->countAllResults() > 0) {
                continue;
            }
            $body = "*Performance Points Deducted*\n-2 points"
                . "\nReason: Task overdue"
                . "\nTask: " . ((string) ($row['title'] ?? '') ?: ('#' . (int) $row['id']))
                . "\nDue: " . date('d M Y, h:i A', $due)
                . "\nDelay: {$delayDays} day(s)";
            $result = $this->sendConversationMessage(
                'task_overdue_points', $userId, $body, 'mobile_tasks', (int) $row['id'], null, '', false, $slot
            );
            $queued += ($result['status'] ?? '') === 'queued' ? 1 : 0;
        }
        return ['scanned' => count($rows), 'queued' => $queued];
    }

    /** @return array{scanned:int,skipped:int,queued:int,sent:int,failed:int} */
    public function sendPendingApprovalBacklog(int $limit = 25, bool $force = false, int $dispatchLimit = 8): array
    {
        $limit = max(1, min(100, $limit));
        $dispatchLimit = max(0, min(8, $dispatchLimit));
        $requests = array_slice((new MobileApprovalService())->list(null, 'pending'), 0, $limit);
        $result = ['scanned' => count($requests), 'skipped' => 0, 'queued' => 0, 'sent' => 0, 'failed' => 0];
        $queueIds = [];
        $db = db_connect();

        foreach ($requests as $request) {
            $requestId = (int) ($request['id'] ?? 0);
            if ($requestId <= 0) {
                $result['skipped']++;
                continue;
            }
            if (! $force) {
                $alreadyQueued = $db->table('whatsapp_sender_queue')
                    ->where('provider_key', 'attrai')->where('event_key', 'approval_requested')
                    ->where('source_type', 'mobile_approval_requests')->where('source_id', $requestId)
                    ->countAllResults() > 0;
                if ($alreadyQueued) {
                    $result['skipped']++;
                    continue;
                }
            }

            $queued = $this->notifyApprovalRequested($request);
            $newIds = array_values(array_filter(array_map('intval', (array) ($queued['queue_ids'] ?? []))));
            $queueIds = array_merge($queueIds, $newIds);
            $result['queued'] += count($newIds);
        }

        foreach (array_slice($queueIds, 0, $dispatchLimit) as $queueId) {
            $dispatch = $this->queueService->dispatchById($queueId);
            if (($dispatch['status'] ?? '') === 'sent') {
                $result['sent']++;
            } else {
                $result['failed']++;
            }
        }
        return $result;
    }

    /** @return array{status:string,message:string,queue_ids:list<int>,dispatches:list<array<string,mixed>>} */
    public function sendTemplatePreview(string $userId, string $scenario = 'order_status'): array
    {
        $userId = preg_replace('/\D+/', '', trim($userId)) ?? '';
        if ($userId === '') {
            throw new RuntimeException('Enter a valid Arattai User ID.');
        }
        $settings = $this->settings();
        $logo = $this->companyLogoUrl($settings);
        $orderImage = $this->latestOrderImageUrl($settings) ?: $logo;
        $issuementImage = $this->latestIssuementApprovalImageUrl() ?: $logo;
        $samples = [
            'followup_approval' => ['approval_preview', "*Follow-up Approval Required*\nOrder: ORD-DEMO-101\nStage: In Production\nCurrent note: Polishing is in progress.\nPrevious note: Casting completed.\nNext follow-up: Tomorrow 11:00 AM", true, $orderImage],
            'issuement_approval' => ['issuement_preview', "*Issuement Approval Required*\nVoucher: ISS-DEMO-101\nKarigar: Demo Karigar\nGold: 12.500 gm\nDiamond: 1.250 cts", true, $issuementImage],
            'karigar_assignment' => ['karigar_assignment_preview', "*New Order Assigned*\nOrder: ORD-DEMO-101\nWork: Diamond Ring\n• Solitaire Ring | Size: Ring 14 | Qty: 1\nDescription: White gold, high polish\nDue date: 25 Oct 2026", false, $orderImage],
            'karigar_issuement' => ['karigar_issuement_preview', "*Material Issued to You*\nVoucher: ISS-DEMO-101\nGold: 12.500 gm\nDiamond: 1.250 cts", false, $issuementImage],
            'payment' => ['payment_preview', "*Payment Received*\nAmount: ₹5,000.00\nPayment No: PAY-DEMO-101", false, $logo],
            'points' => ['points_credit_preview', "*Congratulations! Points Received*\n+2 points\nReason: Follow-up approved", false, ''],
            'followup_due' => ['overdue_preview', "*Follow-up Overdue*\nOrder: ORD-DEMO-101\nDelay: 2 day(s)", false, $orderImage],
            'order_status' => ['order_status_preview', "*Order Status*\nOrder: ORD-DEMO-101\nStatus: In Production\nDue: 25 Oct 2026", false, $orderImage],
        ];
        if (! isset($samples[$scenario])) {
            throw new RuntimeException('Select a valid Arattai preview scenario.');
        }
        $queueIds = [];
        $dispatches = [];
        foreach ([$samples[$scenario]] as [$event, $body, $buttons, $image]) {
            $payload = $buttons
                ? $this->quickReplyPayload($userId, $body, [
                    ['id' => 'preview.approve', 'label' => 'Approve'],
                    ['id' => 'preview.reject', 'label' => 'Disapprove'],
                ], $image)
                : $this->displayRequestPayload($userId, $body, $image);
            $queueId = $this->enqueueConversation($event, $userId, $body, 'arattai_preview', null, null, $image, $payload);
            $queueIds[] = $queueId;
            $dispatches[] = $this->queueService->dispatchById($queueId);
        }
        $sent = count(array_filter($dispatches, static fn(array $row): bool => ($row['status'] ?? '') === 'sent'));
        $error = '';
        foreach ($dispatches as $dispatch) {
            if (($dispatch['status'] ?? '') !== 'sent' && trim((string) ($dispatch['error_message'] ?? '')) !== '') {
                $error = trim((string) $dispatch['error_message']);
                break;
            }
        }
        return [
            'status' => $sent === 1 ? 'sent' : 'failed',
            'message' => $sent === 1 ? 'Arattai preview message sent.' : ('Arattai preview failed.' . ($error !== '' ? ' Reason: ' . mb_substr($error, 0, 300) : '')),
            'queue_ids' => $queueIds,
            'dispatches' => $dispatches,
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function handleIncomingConversation(array $payload): array
    {
        if ((string) ($payload['event'] ?? '') !== 'chat.message.received') {
            return ['status' => 'ignored', 'message' => 'Webhook event does not require ERP processing.'];
        }
        $entity = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
        $user = is_array($entity['user'] ?? null) ? $entity['user'] : [];
        if ($user === [] && is_array($payload['user'] ?? null)) {
            $user = $payload['user'];
        }
        $userId = preg_replace('/\D+/', '', (string) ($user['id'] ?? '')) ?? '';
        if ($userId === '') {
            return ['status' => 'ignored', 'message' => 'Incoming message has no Arattai user ID.'];
        }
        $actorAdminUserId = $this->conversationActorAdminUserId($userId, $this->settings());
        if ($actorAdminUserId <= 0) {
            $this->sendConversationMessage(
                'order_status_denied',
                $userId,
                'This ERP assistant is available only to mapped internal Arattai users.',
                'arattai_chat'
            );
            return ['status' => 'denied', 'message' => 'Incoming user is not mapped to an active internal ERP user.'];
        }
        $entityContent = $entity['content'] ?? null;
        $buttonId = $this->firstNestedScalar($payload, ['button_id', 'buttonId', 'option_id', 'optionId']);
        if ($buttonId === 'preview.order_status') {
            $this->sendConversationMessage('order_status_prompt', $userId, 'Please send your Order ID or Order Number to check its current status.', 'arattai_chat');
            return ['status' => 'prompted', 'message' => 'Asked the user for an order ID.'];
        }
        if (str_starts_with($buttonId, 'preview.')) {
            $this->sendConversationMessage('preview_action_reply', $userId, 'This is a design preview only. No ERP data was changed.', 'arattai_preview');
            return ['status' => 'preview', 'message' => 'Preview action acknowledged.'];
        }
        $text = $this->incomingMessageText($payload, $entity, $entityContent);
        $ackKey = $this->firstNestedScalar($payload, ['ack_key', 'ackKey']);
        $replyTo = $this->firstNestedScalar($payload, $this->replyReferenceKeys());
        if (($ackKey !== '' || $replyTo !== '') && ($text !== '' || $buttonId !== '')) {
            $replyContext = $this->quickReplyQueueContext($ackKey, $replyTo);
            $contextRecipient = preg_replace('/\D+/', '', (string) ($replyContext['recipient_number'] ?? '')) ?? '';
            if ($contextRecipient !== '' && hash_equals($contextRecipient, $userId)) {
                if ($text === '' && $buttonId !== '') {
                    $text = $this->quickReplyLabel($replyContext, $buttonId);
                }
                if ((string) ($replyContext['event_key'] ?? '') === 'attrai_demo'
                    && ($buttonId === '1' || strcasecmp($text, 'Check Order Status') === 0)) {
                    $this->sendConversationMessage('order_status_prompt', $userId, 'Please send your Order ID or Order Number to check its current status.', 'arattai_chat');
                    return ['status' => 'prompted', 'message' => 'Asked the user for an order ID.'];
                }
                if ((string) ($replyContext['source_type'] ?? '') === 'arattai_preview') {
                    $this->sendConversationMessage('preview_action_reply', $userId, 'This is a design preview only. No ERP data was changed.', 'arattai_preview');
                    return ['status' => 'preview', 'message' => 'Preview action acknowledged.'];
                }
            }
        }

        $transaction = (new ArattaiTransactionConversationService())->handle(
            $payload,
            $userId,
            $text,
            $actorAdminUserId,
            function (string $event, string $message) use ($userId): void {
                $this->sendConversationMessage($event, $userId, $message, 'arattai_transaction_conversation');
            },
            function (string $event, string $message, array $labels) use ($userId): void {
                $options = [];
                foreach (array_slice($labels, 0, 10) as $index => $label) {
                    $options[] = ['id' => $event . '.' . ($index + 1), 'label' => mb_substr((string) $label, 0, 80)];
                }
                $interactive = $this->quickReplyPayload($userId, $message, $options);
                $this->sendConversationMessage(
                    $event,
                    $userId,
                    $message,
                    'arattai_transaction_conversation',
                    null,
                    null,
                    '',
                    true,
                    '',
                    $interactive
                );
            },
            function (string $event, string $caption, string $url) use ($userId): void {
                $this->sendConversationDocument($event, $userId, $caption, $url, 'arattai_transaction_conversation');
            }
        );
        if (($transaction['handled'] ?? false) === true) {
            unset($transaction['handled']);
            return $transaction;
        }

        $wizard = (new ArattaiOrderConversationService())->handle(
            $payload,
            $userId,
            $text,
            $actorAdminUserId,
            function (string $event, string $message) use ($userId): void {
                $this->sendConversationMessage($event, $userId, $message, 'arattai_order_conversation');
            },
            function (string $event, string $message, array $labels) use ($userId): void {
                $options = [];
                foreach (array_slice($labels, 0, 10) as $index => $label) {
                    $options[] = ['id' => $event . '.' . ($index + 1), 'label' => mb_substr((string) $label, 0, 80)];
                }
                $payload = $this->quickReplyPayload($userId, $message, $options);
                $this->sendConversationMessage(
                    $event,
                    $userId,
                    $message,
                    'arattai_order_conversation',
                    null,
                    null,
                    '',
                    true,
                    '',
                    $payload
                );
            }
        );
        if (($wizard['handled'] ?? false) === true) {
            unset($wizard['handled']);
            return $wizard;
        }
        if ($text === '') {
            $this->sendConversationMessage('order_status_prompt', $userId, 'Please send your Order ID or Order Number to check its current status.', 'arattai_chat');
            return ['status' => 'prompted', 'message' => 'Asked the user for an order ID.'];
        }

        $order = $this->findOrderFromMessage($text);
        if (! is_array($order)) {
            $this->sendConversationMessage(
                'order_status_prompt',
                $userId,
                "Welcome to Aabhushan ERP.\nPlease send a valid Order ID or Order Number (example: ORD-1001) to check status.",
                'arattai_chat'
            );
            return ['status' => 'prompted', 'message' => 'No order number was found; asked for one.'];
        }

        $orderId = (int) ($order['id'] ?? 0);
        $body = "*Order Status*\nOrder: " . (string) ($order['order_no'] ?? ('#' . $orderId))
            . "\nWork: " . ((string) ($order['order_name'] ?? '') ?: '-')
            . "\nStatus: " . ((string) ($order['status'] ?? '') ?: '-')
            . "\nPriority: " . ((string) ($order['priority'] ?? '') ?: '-')
            . "\nDue: " . ((string) ($order['due_date'] ?? '') ?: '-');
        $latest = db_connect()->table('order_followups')
            ->select('stage, description, followup_taken_on')
            ->where('order_id', $orderId)->where('approval_status', 'approved')
            ->orderBy('followup_taken_on', 'DESC')->orderBy('id', 'DESC')->get()->getRowArray();
        if (is_array($latest)) {
            $body .= "\nLatest update: " . trim((string) (($latest['description'] ?? '') ?: ($latest['stage'] ?? '')));
        }
        $this->sendConversationMessage(
            'order_status_reply',
            $userId,
            $body,
            'orders',
            $orderId,
            $orderId,
            $this->orderImageUrl($orderId, $this->settings())
        );
        return ['status' => 'replied', 'message' => 'Order status sent.', 'order_id' => $orderId];
    }

    /** @return array<string,mixed>|null */
    private function findOrderFromMessage(string $message): ?array
    {
        $db = db_connect();
        $message = trim($message);
        $candidates = [$message];
        if (preg_match_all('/[A-Za-z]{1,12}[A-Za-z0-9\/_-]*\d+[A-Za-z0-9\/_-]*/', $message, $matches)) {
            $candidates = array_merge($candidates, $matches[0]);
        }
        foreach (array_values(array_unique($candidates)) as $candidate) {
            $candidate = trim((string) $candidate, " \t\n\r\0\x0B#.,:;");
            if ($candidate === '') {
                continue;
            }
            $row = $db->table('orders')->select('id, order_no, order_name, status, priority, due_date')
                ->where('deleted_at', null)->where('order_no', $candidate)->get()->getRowArray();
            if (is_array($row)) {
                return $row;
            }
        }
        if (preg_match('/(?:order\s*(?:id|#)?\s*[:\-]?\s*|^#?)(\d{1,10})\b/i', $message, $match)) {
            $row = $db->table('orders')->select('id, order_no, order_name, status, priority, due_date')
                ->where('deleted_at', null)->where('id', (int) $match[1])->get()->getRowArray();
            return is_array($row) ? $row : null;
        }
        return null;
    }

    /** @param array<string,mixed> $settings */
    private function conversationActorAdminUserId(string $arattaiUserId, array $settings): int
    {
        $db = db_connect();
        if ($db->tableExists('arattai_user_mappings')) {
            $row = $db->table('arattai_user_mappings m')
                ->select('m.entity_type, m.entity_id')
                ->join('admin_users a', 'a.id = m.entity_id AND a.is_active = 1', 'inner')
                ->whereIn('m.entity_type', ['admin', 'follower'])
                ->where('m.arattai_user_id', $arattaiUserId)
                ->where('m.is_active', 1)
                ->orderBy('m.is_approval_admin', 'DESC')
                ->get()->getRowArray();
            if (is_array($row) && (int) ($row['entity_id'] ?? 0) > 0) {
                return (int) $row['entity_id'];
            }
        }
        if (in_array($arattaiUserId, $this->approvalUserIds($settings), true)) {
            $reviewerId = (int) ($settings['attrai_approval_reviewer_id'] ?? 0);
            return $this->isActiveReviewer($reviewerId) ? $reviewerId : 0;
        }
        return 0;
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $entity */
    private function incomingMessageText(array $payload, array $entity, mixed $entityContent): string
    {
        if (is_scalar($entityContent)) {
            return trim((string) $entityContent);
        }
        foreach ([
            $entityContent,
            $entity['message'] ?? null,
            $entity['text'] ?? null,
            $entity['body'] ?? null,
            $payload['message'] ?? null,
            $payload['text'] ?? null,
            $payload['body'] ?? null,
        ] as $candidate) {
            if (is_scalar($candidate)) {
                $value = trim((string) $candidate);
                if ($value !== '') {
                    return $value;
                }
            }
            if (is_array($candidate)) {
                $value = $this->firstNestedScalar($candidate, ['message', 'text', 'body', 'value', 'label', 'title']);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return '';
    }

    /** @param array<string,mixed> $payload */
    private function replyToWebhookUser(array $payload, string $message): void
    {
        $userId = $this->webhookUserId($payload);
        if ($userId !== '') {
            try {
                $this->sendConversationMessage('webhook_action_reply', $userId, $message, 'arattai_webhook');
            } catch (\Throwable $e) {
                log_message('error', 'Arattai webhook reply failed: {message}', ['message' => $e->getMessage()]);
            }
        }
    }

    /** @return array<string,mixed> */
    private function sendConversationMessage(
        string $event,
        string $userId,
        string $message,
        string $sourceType,
        ?int $sourceId = null,
        ?int $orderId = null,
        string $mediaUrl = '',
        bool $dispatch = true,
        string $messageSuffix = '',
        ?array $payload = null
    ): array {
        $settings = $this->settings();
        if (! $this->baseConfigured($settings)) {
            return ['status' => 'skipped', 'message' => 'Arattai integration is disabled or incomplete.'];
        }
        $userId = preg_replace('/\D+/', '', trim($userId)) ?? '';
        if ($userId === '') {
            return ['status' => 'skipped', 'message' => 'Arattai user ID is missing.'];
        }
        $queueId = $this->enqueueConversation(
            $event,
            $userId,
            $message,
            $sourceType,
            $sourceId,
            $orderId,
            $mediaUrl,
            $payload ?? $this->displayRequestPayload($userId, $message, $mediaUrl),
            $messageSuffix
        );
        if (! $dispatch) {
            return ['status' => 'queued', 'message' => 'Arattai message queued.', 'queue_id' => $queueId];
        }
        return $this->queueService->dispatchById($queueId) + ['message' => 'Arattai message processed.'];
    }

    /** @return array<string,mixed> */
    private function sendConversationDocument(
        string $event,
        string $userId,
        string $caption,
        string $url,
        string $sourceType,
        ?int $sourceId = null,
        ?int $orderId = null
    ): array {
        $url = trim($url);
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Arattai document URL is invalid.');
        }
        $payload = [
            'category' => 'display',
            'type' => 'document',
            'user_id' => preg_replace('/\D+/', '', $userId) ?? '',
            'content' => [
                'link' => mb_substr($url, 0, 1000),
                'caption' => mb_substr(trim($caption), 0, 200),
            ],
        ];
        return $this->sendConversationMessage(
            $event,
            $userId,
            $caption,
            $sourceType,
            $sourceId,
            $orderId,
            '',
            true,
            '',
            $payload
        );
    }

    /** @param array<string,mixed>|null $payload */
    private function enqueueConversation(
        string $event,
        string $userId,
        string $message,
        string $sourceType,
        ?int $sourceId = null,
        ?int $orderId = null,
        string $mediaUrl = '',
        ?array $payload = null,
        string $messageSuffix = ''
    ): int {
        $settings = $this->settings();
        $messageNo = 'ARA-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $event) ?: 'MSG', 0, 12))
            . '-' . date('YmdHis') . ($messageSuffix !== '' ? '-' . preg_replace('/[^A-Za-z0-9]/', '', $messageSuffix) : '')
            . '-' . random_int(100, 999);
        $payloadType = mb_strtolower(trim((string) ($payload['type'] ?? '')));
        $messageType = (string) ($payload['category'] ?? '') === 'input'
            ? 'interactive'
            : (in_array($payloadType, ['image', 'document', 'audio', 'video'], true)
                ? $payloadType
                : ($mediaUrl !== '' ? 'image' : 'text'));
        return $this->queueService->enqueue([
            'message_no' => $messageNo,
            'event_key' => $event,
            'provider_key' => 'attrai',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'order_id' => $orderId,
            'sender_number' => $settings['attrai_sender_id'] ?? null,
            'recipient_number' => $userId,
            'recipient_name' => 'Arattai User',
            'message_type' => $messageType,
            'message_text' => $message,
            'media_url' => $mediaUrl !== '' ? $mediaUrl : null,
            'request_payload' => json_encode(
                $payload ?? $this->displayRequestPayload($userId, $message, $mediaUrl),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
            'scheduled_at' => date('Y-m-d H:i:s'),
            'created_by' => null,
        ]);
    }

    /** @return array<string,mixed> */
    private function displayRequestPayload(string $userId, string $message, string $mediaUrl = ''): array
    {
        if (trim($mediaUrl) !== '') {
            return [
                'category' => 'display',
                'type' => 'image',
                'user_id' => $userId,
                'content' => [
                    'link' => mb_substr(trim($mediaUrl), 0, 1000),
                    'caption' => mb_substr(trim($message), 0, 200),
                ],
            ];
        }
        return [
            'category' => 'display',
            'type' => 'text',
            'user_id' => $userId,
            'content' => ['body' => trim($message)],
        ];
    }

    /** @param list<array{id:string,label:string}> $options @return array<string,mixed> */
    private function quickReplyPayload(string $userId, string $message, array $options, string $mediaUrl = ''): array
    {
        $content = [
            'body' => mb_substr(trim($message), 0, 2000),
            'footer' => ['text' => 'Aabhushan ERP'],
            'options' => array_map(static fn(int $index, array $option): array => [
                'id' => (string) ($index + 1),
                'label' => (string) $option['label'],
            ], array_keys($options), array_values($options)),
        ];
        if (trim($mediaUrl) !== '') {
            $content['header'] = ['type' => 'image', 'link' => trim($mediaUrl)];
        }
        return ['category' => 'input', 'type' => 'quick_reply', 'user_id' => $userId, 'content' => $content];
    }

    /** @param array<string,mixed> $queueRow */
    private function quickReplyLabel(array $queueRow, string $buttonId): string
    {
        $request = json_decode((string) ($queueRow['request_payload'] ?? ''), true);
        $options = is_array($request) && is_array($request['content']['options'] ?? null)
            ? $request['content']['options']
            : [];
        foreach ($options as $index => $option) {
            if (! is_array($option)) {
                continue;
            }
            $id = trim((string) ($option['id'] ?? ($index + 1)));
            if ($id === trim($buttonId)) {
                return trim((string) ($option['label'] ?? ''));
            }
        }
        return '';
    }

    private function userIdForAdmin(int $adminUserId): string
    {
        return $this->userMappingService->adminUserId($adminUserId);
    }

    private function formatPoints(float $points): string
    {
        return rtrim(rtrim(number_format($points, 2, '.', ''), '0'), '.');
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

    /** @param array<string,mixed> $payload */
    private function resolveQuickReplyAction(array $payload): string
    {
        $ackKey = $this->firstNestedScalar($payload, ['ack_key', 'ackKey']);
        $replyTo = $this->firstNestedScalar($payload, $this->replyReferenceKeys());
        $decision = $this->quickReplyDecision($payload);
        if ($decision === '') {
            return '';
        }
        $replyUserId = $this->webhookUserId($payload);
        $row = $this->quickReplyQueueContext($ackKey, $replyTo);
        if (! is_array($row)) {
            $requestId = $this->approvalRequestIdFromPayload($payload);
            if ($requestId > 0 && $replyUserId !== '') {
                $row = $this->approvalQueueContextForRequest($requestId, $replyUserId);
            }
        }
        if (! is_array($row) || (string) ($row['event_key'] ?? '') !== 'approval_requested') {
            return '';
        }
        $recipientUserId = preg_replace('/\D+/', '', (string) ($row['recipient_number'] ?? '')) ?? '';
        if ($replyUserId === '' || $recipientUserId === '' || ! hash_equals($recipientUserId, $replyUserId)) {
            return '';
        }
        $requestId = (int) ($row['source_id'] ?? 0);
        if ($decision !== '' && $requestId > 0) {
            return $this->signedAction($requestId, $decision, $this->settings());
        }
        return '';
    }

    /** @return array<string,mixed>|null */
    private function quickReplyQueueContext(string $ackKey, string $replyTo): ?array
    {
        $db = db_connect();
        if ($ackKey !== '') {
            $row = $db->table('whatsapp_sender_queue')
                ->select('event_key, source_type, source_id, recipient_number, request_payload')
                ->where('provider_key', 'attrai')->where('message_no', $ackKey)
                ->orderBy('id', 'DESC')->get()->getRowArray();
            if (is_array($row)) {
                return $row;
            }
        }
        if ($replyTo !== '') {
            $row = $db->table('whatsapp_sender_queue')
                ->select('event_key, source_type, source_id, recipient_number, request_payload')
                ->where('provider_key', 'attrai')->where('provider_message_id', $replyTo)
                ->orderBy('id', 'DESC')->get()->getRowArray();
            if (is_array($row)) {
                return $row;
            }
        }
        return null;
    }

    /** @return list<string> */
    private function replyReferenceKeys(): array
    {
        return [
            'reply_to', 'replyTo', 'replied_to', 'repliedTo',
            'reply_message_id', 'replyMessageId', 'reply_to_message_id', 'replyToMessageId',
            'replied_message_id', 'repliedMessageId',
            'quoted_message_id', 'quotedMessageId', 'quoted_message_uid', 'quotedMessageUid',
            'parent_message_id', 'parentMessageId', 'reference_message_id', 'referenceMessageId',
        ];
    }

    /** @param array<string,mixed> $payload */
    private function quickReplyDecision(array $payload, bool $allowButtonId = true): string
    {
        $entity = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
        $content = $entity['content'] ?? null;
        $contentArray = is_array($content) ? $content : [];
        $buttonId = $this->firstNestedScalar($payload, ['button_id', 'buttonId', 'option_id', 'optionId']);
        $selected = is_scalar($content)
            ? trim((string) $content)
            : trim((string) ($contentArray['label'] ?? $contentArray['title'] ?? ''));

        return match (strtolower($selected)) {
            'approve' => 'approve',
            'approve - no point' => 'approve_no_point',
            'disapprove' => 'reject',
            default => ! $allowButtonId ? '' : match ($buttonId) {
                '1' => 'approve',
                '2' => 'reject',
                '3' => 'approve_no_point',
                default => '',
            },
        };
    }

    /** @param array<string,mixed> $payload */
    private function approvalRequestIdFromPayload(array $payload): int
    {
        foreach ($payload as $value) {
            if (is_scalar($value)
                && preg_match('/\bRequest\s*:\s*#?\s*(\d+)\b/i', (string) $value, $matches) === 1) {
                return (int) $matches[1];
            }
            if (is_array($value)) {
                $requestId = $this->approvalRequestIdFromPayload($value);
                if ($requestId > 0) {
                    return $requestId;
                }
            }
        }
        return 0;
    }

    /** @return array<string,mixed>|null */
    private function approvalQueueContextForRequest(int $requestId, string $recipientUserId): ?array
    {
        if ($requestId <= 0 || $recipientUserId === '') {
            return null;
        }
        $row = db_connect()->table('whatsapp_sender_queue')
            ->select('event_key, source_type, source_id, recipient_number, request_payload')
            ->where('provider_key', 'attrai')
            ->where('event_key', 'approval_requested')
            ->where('source_id', $requestId)
            ->where('recipient_number', $recipientUserId)
            ->orderBy('id', 'DESC')->get()->getRowArray();
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $payload @param list<string> $keys */
    private function firstNestedScalar(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && is_scalar($payload[$key])) {
                $value = trim((string) $payload[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        foreach ($payload as $value) {
            if (is_array($value)) {
                $found = $this->firstNestedScalar($value, $keys);
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
        $content = [
            'body' => mb_substr((string) ($variables['message'] ?? ''), 0, 2000),
            'footer' => ['text' => 'Aabhushan ERP approval'],
            'options' => array_map(
                static fn (int $index, array $action): array => [
                    'id' => (string) ($index + 1),
                    'label' => (string) ($action['title'] ?? 'Review'),
                ],
                array_keys(json_decode((string) $variables['actions_json'], true) ?: []),
                array_values(json_decode((string) $variables['actions_json'], true) ?: [])
            ),
        ];
        $mediaUrl = trim((string) ($variables['media_url'] ?? ''));
        if ($mediaUrl !== '') {
            $content['header'] = ['type' => 'image', 'link' => $mediaUrl];
        }

        return json_encode([
            'category' => 'input',
            'type' => 'quick_reply',
            'user_id' => (string) ($variables['to'] ?? ''),
            'content' => $content,
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
            $previous = is_array($payload['previous_followup'] ?? null) ? $payload['previous_followup'] : [];
            if (trim((string) ($previous['stage'] ?? '')) !== '') $lines[] = 'Previous stage: ' . trim((string) $previous['stage']);
            if (trim((string) ($previous['description'] ?? '')) !== '') $lines[] = 'Previous note: ' . trim((string) $previous['description']);
            if (trim((string) ($previous['followup_taken_on'] ?? '')) !== '') $lines[] = 'Previous follow-up: ' . trim((string) $previous['followup_taken_on']);
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
        $url = $this->normalizeImageUrl($attachmentUrl);
        if ($url !== '') {
            return $url;
        }
        $attachmentPath = trim((string) ($request['attachment_path'] ?? ''));
        $url = $this->normalizeImageUrl($attachmentPath);
        if ($url !== '') {
            return $url;
        }
        $orderId = $this->orderIdFromApproval($request) ?? 0;
        return $orderId > 0 ? $this->orderImageUrl($orderId, $settings) : $this->companyLogoUrl($settings);
    }

    /** @param array<string,mixed> $settings */
    private function orderImageUrl(int $orderId, array $settings): string
    {
        $images = (new OrderThumbnailService())->map([$orderId], false);
        return $this->normalizeImageUrl((string) ($images[$orderId] ?? '')) ?: $this->companyLogoUrl($settings);
    }

    /** @param array<string,mixed> $settings */
    private function companyLogoUrl(array $settings): string
    {
        return $this->normalizeImageUrl((string) ($settings['logo_path'] ?? ''));
    }

    private function issuementImageUrl(string $voucherNo, int $karigarId): string
    {
        $db = db_connect();
        foreach (['gold_inventory_issue_headers', 'issue_headers', 'stone_inventory_issue_headers'] as $table) {
            if (! $db->tableExists($table) || ! $db->fieldExists('attachment_path', $table)) {
                continue;
            }
            $row = $db->table($table)->select('attachment_path')->where('voucher_no', $voucherNo)
                ->where('karigar_id', $karigarId)->get()->getRowArray();
            $url = $this->normalizeImageUrl((string) ($row['attachment_path'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }
        return $this->companyLogoUrl($this->settings());
    }

    /** @param array<string,mixed> $settings */
    private function latestOrderImageUrl(array $settings): string
    {
        $db = db_connect();
        if (! $db->tableExists('orders')) {
            return $this->companyLogoUrl($settings);
        }
        $rows = $db->table('orders')->select('id')->where('deleted_at', null)->orderBy('id', 'DESC')->limit(30)->get()->getResultArray();
        foreach ($rows as $row) {
            $url = $this->orderImageUrl((int) ($row['id'] ?? 0), $settings);
            if ($url !== '' && $url !== $this->companyLogoUrl($settings)) {
                return $url;
            }
        }
        return $this->companyLogoUrl($settings);
    }

    private function latestIssuementApprovalImageUrl(): string
    {
        $db = db_connect();
        if (! $db->tableExists('mobile_approval_requests')) {
            return '';
        }
        $rows = $db->table('mobile_approval_requests')->select('attachment_path')
            ->where('request_type', 'issuement')->where('attachment_path IS NOT NULL', null, false)
            ->orderBy('id', 'DESC')->limit(30)->get()->getResultArray();
        foreach ($rows as $row) {
            $url = $this->normalizeImageUrl((string) ($row['attachment_path'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }
        return '';
    }

    private function normalizeImageUrl(string $url): string
    {
        $url = trim($url);
        if (! $this->isImageUrl($url)) {
            return '';
        }
        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
        if ($scheme !== '') {
            return in_array($scheme, ['http', 'https'], true) ? $url : '';
        }
        return base_url(ltrim($url, '/'));
    }

    private function isImageUrl(string $url): bool
    {
        if ($url === '' || mb_strlen(trim($url)) > 1000) {
            return false;
        }
        $path = (string) (parse_url(trim($url), PHP_URL_PATH) ?? $url);
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true);
    }

    /** @param array<string,mixed> $payload */
    private function webhookUserId(array $payload): string
    {
        $entity = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
        $user = is_array($entity['user'] ?? null) ? $entity['user'] : [];
        if ($user === [] && is_array($payload['user'] ?? null)) {
            $user = $payload['user'];
        }
        return preg_replace('/\D+/', '', (string) ($user['id'] ?? '')) ?? '';
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
            && $this->approvalUserIds($settings) !== [];
    }

    /** @param array<string,mixed> $settings */
    private function baseConfigured(array $settings): bool
    {
        return (int) ($settings['attrai_enabled'] ?? 0) === 1
            && trim((string) ($settings['attrai_api_url'] ?? '')) !== '';
    }

    /** @param array<string,mixed> $settings @return list<string> */
    private function approvalUserIds(array $settings): array
    {
        return $this->userMappingService->approvalAdminUserIds($settings);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', trim($phone)) ?? '';
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
