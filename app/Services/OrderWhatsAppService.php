<?php

namespace App\Services;

use App\Models\CompanySettingModel;
use App\Models\WhatsappMessageLogModel;
use CodeIgniter\HTTP\CURLRequest;
use Throwable;

class OrderWhatsAppService
{
    private CompanySettingModel $companySettingModel;
    private WhatsappMessageLogModel $logModel;
    private WhatsappSenderQueueService $senderQueueService;

    public function __construct()
    {
        $this->companySettingModel = new CompanySettingModel();
        $this->logModel = new WhatsappMessageLogModel();
        $this->senderQueueService = new WhatsappSenderQueueService();
    }

    /**
     * @return array<string,mixed>
     */
    public function notifyOrderCreated(int $orderId): array
    {
        return $this->sendCustomerOrderEvent($orderId, 'order_created', 'whatsapp_template_order_created', 'whatsapp_notify_order_created');
    }

    /**
     * @return array<string,mixed>
     */
    public function notifyOrderStatusChanged(int $orderId, string $fromStatus, string $toStatus, ?string $remarks = null): array
    {
        return $this->sendCustomerOrderEvent(
            $orderId,
            'order_status_changed',
            'whatsapp_template_order_status_changed',
            'whatsapp_notify_order_status_changed',
            [
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'status' => $toStatus,
                'remarks' => trim($remarks ?? ''),
            ],
            'order_status_changed|' . $orderId . '|' . strtoupper(trim($fromStatus)) . '|' . strtoupper(trim($toStatus))
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function notifyOrderReady(int $orderId, string $statusLabel): array
    {
        return $this->sendCustomerOrderEvent(
            $orderId,
            'order_ready',
            'whatsapp_template_order_ready',
            'whatsapp_notify_order_ready',
            [
                'ready_status' => $statusLabel,
                'status' => $statusLabel,
            ],
            'order_ready|' . $orderId . '|' . strtoupper(trim($statusLabel))
        );
    }

    /**
     * @param array<string,float> $monitor
     * @return array<string,mixed>
     */
    public function notifyOrderOverBudget(int $orderId, array $monitor, string $context): array
    {
        $contextKey = strtolower(trim($context)) === 'receive' ? 'receive' : 'issue';
        $extra = [
            'budget_context' => strtoupper($contextKey),
            'over_issue_gold' => $this->formatNumber((float) ($monitor['over_issue_gold'] ?? 0), 3),
            'over_issue_diamond' => $this->formatNumber((float) ($monitor['over_issue_diamond'] ?? 0), 3),
            'over_receive_gold' => $this->formatNumber((float) ($monitor['over_receive_gold'] ?? 0), 3),
            'over_receive_diamond' => $this->formatNumber((float) ($monitor['over_receive_diamond'] ?? 0), 3),
        ];

        return $this->sendInternalOrderEvent(
            $orderId,
            'order_over_budget',
            'whatsapp_template_order_over_budget',
            'whatsapp_notify_order_over_budget',
            $extra,
            'order_over_budget|' . $orderId . '|' . $contextKey . '|' . $extra['over_issue_gold'] . '|' . $extra['over_issue_diamond'] . '|' . $extra['over_receive_gold'] . '|' . $extra['over_receive_diamond']
        );
    }

    /**
     * @return array<string,int>
     */
    public function dispatchDailyDelayAlerts(?string $forDate = null): array
    {
        $settings = $this->settings();
        if (! $this->isToggleEnabled($settings, 'whatsapp_notify_order_delay_daily') || ! $this->isConfigured($settings)) {
            return ['scanned' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $targetDate = $this->normalizeDate($forDate ?: date('Y-m-d'));
        $rows = db_connect()->table('orders o')
            ->select('o.id')
            ->whereNotIn('o.status', ['Cancelled', 'Completed'])
            ->where('o.due_date <', $targetDate)
            ->orderBy('o.id', 'ASC')
            ->get()
            ->getResultArray();

        $result = ['scanned' => count($rows), 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($rows as $row) {
            $orderId = (int) ($row['id'] ?? 0);
            if ($orderId <= 0) {
                $result['skipped']++;
                continue;
            }

            $dispatch = $this->sendInternalOrderEvent(
                $orderId,
                'order_delay_daily',
                'whatsapp_template_order_delay_daily',
                'whatsapp_notify_order_delay_daily',
                ['delay_check_date' => $targetDate],
                'order_delay_daily|' . $orderId . '|' . $targetDate
            );

            if (($dispatch['status'] ?? '') === 'sent') {
                $result['sent']++;
            } elseif (($dispatch['status'] ?? '') === 'skipped') {
                $result['skipped']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function sendCustomerOrderEvent(
        int $orderId,
        string $eventKey,
        string $templateField,
        string $toggleField,
        array $extra = [],
        ?string $eventHash = null
    ): array {
        $context = $this->orderContext($orderId);
        if ($context === null) {
            return ['status' => 'skipped', 'message' => 'Order not found.'];
        }

        $phone = $this->preferredCustomerPhone($context);
        if ($phone === null) {
            return ['status' => 'skipped', 'message' => 'Customer/lead phone not available.'];
        }

        return $this->dispatchMessage($eventKey, $templateField, $toggleField, $context, [$phone], $extra, $eventHash);
    }

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function sendInternalOrderEvent(
        int $orderId,
        string $eventKey,
        string $templateField,
        string $toggleField,
        array $extra = [],
        ?string $eventHash = null
    ): array {
        $context = $this->orderContext($orderId);
        if ($context === null) {
            return ['status' => 'skipped', 'message' => 'Order not found.'];
        }

        $phones = $this->alertPhones($this->settings());
        if ($phones === []) {
            return ['status' => 'skipped', 'message' => 'Alert numbers not configured.'];
        }

        return $this->dispatchMessage($eventKey, $templateField, $toggleField, $context, $phones, $extra, $eventHash);
    }

    /**
     * @param list<string> $phones
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function dispatchMessage(
        string $eventKey,
        string $templateField,
        string $toggleField,
        array $context,
        array $phones,
        array $extra = [],
        ?string $eventHash = null
    ): array {
        $settings = $this->settings();
        if (! $this->isToggleEnabled($settings, $toggleField)) {
            return ['status' => 'skipped', 'message' => 'Event disabled in settings.'];
        }
        if ($eventHash !== null && $this->alreadySent($eventHash)) {
            return ['status' => 'skipped', 'message' => 'Already sent.'];
        }

        $messageTemplate = trim((string) ($settings[$templateField] ?? ''));
        $result = ['status' => 'skipped', 'message' => 'No recipients.'];

        foreach ($phones as $phone) {
            $variables = $this->buildVariables($context, $extra, $eventKey, $phone, $settings);
            $message = $messageTemplate !== ''
                ? $this->replacePlaceholders($messageTemplate, $variables)
                : $this->defaultMessage($eventKey, $variables);

            $requestPayload = $this->buildRequestPayload($settings, $variables, $message, $phone);
            $logId = (int) $this->logModel->insert([
                'event_key' => $eventKey,
                'event_hash' => $eventHash,
                'order_id' => (int) ($context['order_id'] ?? 0) ?: null,
                'customer_id' => (int) ($context['customer_id'] ?? 0) ?: null,
                'recipient_phone' => $phone,
                'message_text' => $message,
                'request_payload' => $requestPayload['log_payload'],
                'status' => 'pending',
                'sent_on' => date('Y-m-d'),
            ], true);

            $queueId = $this->senderQueueService->enqueue([
                'event_key' => $eventKey,
                'source_type' => 'whatsapp_message_logs',
                'source_id' => $logId,
                'order_id' => (int) ($context['order_id'] ?? 0) ?: null,
                'customer_id' => (int) ($context['customer_id'] ?? 0) ?: null,
                'sender_number' => trim((string) ($settings['whatsapp_sender_id'] ?? '')) ?: null,
                'recipient_number' => $phone,
                'recipient_name' => (string) ($context['customer_name'] ?? ''),
                'message_type' => 'text',
                'message_text' => $message,
                'request_payload' => $requestPayload['log_payload'],
                'scheduled_at' => date('Y-m-d H:i:s'),
            ]);
            $this->logModel->update($logId, [
                'status' => 'queued',
                'response_payload' => json_encode(['whatsapp_sender_queue_id' => $queueId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'error_message' => null,
            ]);

            $result = [
                'status' => 'queued',
                'message' => 'Message queued for WhatsApp sender cron.',
                'log_id' => $logId,
                'queue_id' => $queueId,
            ];
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,string> $variables
     * @return array{request_options:array<string,mixed>,log_payload:string}
     */
    private function buildRequestPayload(array $settings, array $variables, string $message, string $phone): array
    {
        $bodyTemplate = trim((string) ($settings['whatsapp_body_template'] ?? ''));
        $resolved = $bodyTemplate !== ''
            ? $this->replacePlaceholders($bodyTemplate, $variables)
            : json_encode([
                'to' => $phone,
                'message' => $message,
                'sender' => trim((string) ($settings['whatsapp_sender_id'] ?? '')) ?: null,
                'event' => $variables['event_key'],
                'order_no' => $variables['order_no'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $authType = strtolower(trim((string) ($settings['whatsapp_auth_type'] ?? 'none')));
        $authHeader = trim((string) ($settings['whatsapp_auth_header'] ?? 'Authorization'));
        $authToken = trim((string) ($settings['whatsapp_auth_token'] ?? ''));
        if ($authToken !== '') {
            if ($authType === 'bearer') {
                $headers[$authHeader] = 'Bearer ' . $authToken;
            } elseif ($authType === 'custom') {
                $headers[$authHeader] = $authToken;
            } elseif ($authType === 'basic') {
                $headers[$authHeader] = 'Basic ' . $authToken;
            }
        }

        $extraHeaders = json_decode((string) ($settings['whatsapp_extra_headers_json'] ?? ''), true);
        if (is_array($extraHeaders)) {
            foreach ($extraHeaders as $key => $value) {
                if (is_string($key) && $key !== '') {
                    $headers[$key] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
        }

        return [
            'request_options' => [
                'headers' => $headers,
                'body' => $resolved,
            ],
            'log_payload' => $resolved,
        ];
    }

    /**
     * @param array<string,mixed> $settings
     * @param array{request_options:array<string,mixed>,log_payload:string} $payload
     * @return array{status:string,response_payload:?string,error_message:?string}
     */
    private function sendHttpRequest(array $settings, array $payload): array
    {
        $apiUrl = trim((string) ($settings['whatsapp_api_url'] ?? ''));
        $method = strtoupper(trim((string) ($settings['whatsapp_http_method'] ?? 'POST')));
        if ($method === '') {
            $method = 'POST';
        }

        try {
            $http = service('curlrequest', [
                'timeout' => max(5, min(120, (int) ($settings['whatsapp_timeout_sec'] ?? 20))),
                'http_errors' => false,
            ]);
            if (! $http instanceof CURLRequest) {
                return ['status' => 'failed', 'response_payload' => null, 'error_message' => 'HTTP client not available.'];
            }

            $response = $http->request($method, $apiUrl, $payload['request_options']);
            $body = (string) $response->getBody();
            $statusCode = (int) $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'status' => 'sent',
                    'response_payload' => $body !== '' ? $body : null,
                    'error_message' => null,
                ];
            }

            return [
                'status' => 'failed',
                'response_payload' => $body !== '' ? $body : null,
                'error_message' => $body !== '' ? $body : ('HTTP ' . $statusCode),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'failed',
                'response_payload' => null,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $extra
     * @param array<string,mixed> $settings
     * @return array<string,string>
     */
    private function buildVariables(array $context, array $extra, string $eventKey, string $phone, array $settings): array
    {
        $vars = [];
        foreach (array_merge($context, $extra) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $vars[(string) $key] = trim((string) ($value ?? ''));
            }
        }

        $vars['event_key'] = $eventKey;
        $vars['to'] = $phone;
        $vars['sender_id'] = trim((string) ($settings['whatsapp_sender_id'] ?? ''));
        $vars['company_name'] = trim((string) ($settings['company_name'] ?? ($vars['company_name'] ?? '')));
        $vars['message'] = $vars['message'] ?? '';

        foreach ($vars as $key => $value) {
            $vars[$key . '_json'] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""';
        }

        return $vars;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function orderContext(int $orderId): ?array
    {
        $row = db_connect()->table('orders o')
            ->select('o.id as order_id, o.order_no, o.order_type, o.status, o.priority, o.due_date, o.order_notes, o.whatsapp_notification_number, o.expected_diamond_spec, o.expected_stone_spec, o.assigned_karigar_id, o.created_at, c.id as customer_id, c.name as customer_name, c.phone as customer_phone, c.email as customer_email, l.id as lead_id, l.name as lead_name, l.phone as lead_phone, k.name as karigar_name', false)
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->join('leads l', 'l.id = o.lead_id', 'left')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left')
            ->where('o.id', $orderId)
            ->get()
            ->getRowArray();

        if (! is_array($row)) {
            return null;
        }

        $budget = db_connect()->table('order_items')
            ->select('COALESCE(SUM(gold_required_gm),0) as gold_required_gm, COALESCE(SUM(diamond_required_cts),0) as diamond_required_cts', false)
            ->where('order_id', $orderId)
            ->get()
            ->getRowArray() ?? [];

        $goldBudget = (float) ($budget['gold_required_gm'] ?? 0);
        $diamondBudget = (float) ($budget['diamond_required_cts'] ?? 0);

        $issueGold = 0.0;
        $receiveGold = 0.0;
        $issueDiamond = 0.0;
        $receiveDiamond = 0.0;

        $db = db_connect();
        if ($db->tableExists('gold_inventory_issue_headers') && $db->tableExists('gold_inventory_issue_lines')) {
            $issueGold = (float) (($db->table('gold_inventory_issue_headers ih')
                ->select('COALESCE(SUM(il.weight_gm),0) as total_gold', false)
                ->join('gold_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->where('ih.order_id', $orderId)
                ->get()
                ->getRowArray()['total_gold'] ?? 0));
        }
        if ($db->tableExists('gold_inventory_return_headers') && $db->tableExists('gold_inventory_return_lines')) {
            $receiveGold = (float) (($db->table('gold_inventory_return_headers rh')
                ->select('COALESCE(SUM(rl.weight_gm),0) as total_gold', false)
                ->join('gold_inventory_return_lines rl', 'rl.return_id = rh.id', 'inner')
                ->where('rh.order_id', $orderId)
                ->get()
                ->getRowArray()['total_gold'] ?? 0));
        }
        if ($db->tableExists('issue_headers') && $db->tableExists('issue_lines')) {
            $issueDiamond = (float) (($db->table('issue_headers ih')
                ->select('COALESCE(SUM(il.carat),0) as total_carat', false)
                ->join('issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->where('ih.order_id', $orderId)
                ->get()
                ->getRowArray()['total_carat'] ?? 0));
        }
        if ($db->tableExists('return_headers') && $db->tableExists('return_lines')) {
            $receiveDiamond = (float) (($db->table('return_headers rh')
                ->select('COALESCE(SUM(rl.carat),0) as total_carat', false)
                ->join('return_lines rl', 'rl.return_id = rh.id', 'inner')
                ->where('rh.order_id', $orderId)
                ->get()
                ->getRowArray()['total_carat'] ?? 0));
        }

        $row['customer_display_name'] = trim((string) ($row['customer_name'] ?: $row['lead_name'] ?: 'Customer'));
        $row['customer_phone_display'] = trim((string) ($row['whatsapp_notification_number'] ?: $row['customer_phone'] ?: $row['lead_phone'] ?: ''));
        $row['due_date_display'] = $this->formatDate((string) ($row['due_date'] ?? ''));
        $row['created_at_display'] = $this->formatDateTime((string) ($row['created_at'] ?? ''));
        $row['gold_budget'] = $this->formatNumber($goldBudget, 3);
        $row['diamond_budget'] = $this->formatNumber($diamondBudget, 3);
        $row['gold_issued'] = $this->formatNumber($issueGold, 3);
        $row['diamond_issued'] = $this->formatNumber($issueDiamond, 3);
        $row['gold_received'] = $this->formatNumber($receiveGold, 3);
        $row['diamond_received'] = $this->formatNumber($receiveDiamond, 3);
        $row['gold_over_budget'] = $this->formatNumber(max(0, $issueGold - $goldBudget, $receiveGold - $goldBudget), 3);
        $row['diamond_over_budget'] = $this->formatNumber(max(0, $issueDiamond - $diamondBudget, $receiveDiamond - $diamondBudget), 3);

        $daysDelayed = 0;
        $dueDate = trim((string) ($row['due_date'] ?? ''));
        if ($dueDate !== '') {
            $dueTs = strtotime($dueDate);
            if ($dueTs !== false) {
                $daysDelayed = max(0, (int) floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $dueTs))) / 86400));
            }
        }
        $row['delay_days'] = (string) $daysDelayed;
        return $row;
    }

    private function preferredCustomerPhone(array $context): ?string
    {
        $phone = trim((string) ($context['whatsapp_notification_number'] ?? ''));
        if ($phone === '') {
            $phone = trim((string) ($context['customer_phone'] ?? ''));
        }
        if ($phone === '') {
            $phone = trim((string) ($context['lead_phone'] ?? ''));
        }

        return $this->normalizePhone($phone);
    }

    /**
     * @param array<string,mixed> $settings
     * @return list<string>
     */
    private function alertPhones(array $settings): array
    {
        $raw = trim((string) ($settings['whatsapp_alert_numbers'] ?? ''));
        if ($raw === '') {
            return [];
        }

        $phones = preg_split('/[\s,;]+/', $raw) ?: [];
        $out = [];
        foreach ($phones as $phone) {
            $normalized = $this->normalizePhone($phone);
            if ($normalized !== null) {
                $out[] = $normalized;
            }
        }

        return array_values(array_unique($out));
    }

    private function normalizePhone(string $phone): ?string
    {
        $trimmed = trim($phone);
        if ($trimmed === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9+]/', '', $trimmed);
        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string,mixed>
     */
    private function settings(): array
    {
        $row = $this->companySettingModel->orderBy('id', 'ASC')->first();
        return is_array($row) ? $row : [];
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function isConfigured(array $settings): bool
    {
        return ! empty($settings['whatsapp_enabled']) && trim((string) ($settings['whatsapp_api_url'] ?? '')) !== '';
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function isToggleEnabled(array $settings, string $field): bool
    {
        return (int) ($settings[$field] ?? 1) === 1;
    }

    private function alreadySent(string $eventHash): bool
    {
        return $this->logModel->where('event_hash', $eventHash)->countAllResults() > 0;
    }

    /**
     * @param array<string,string> $variables
     */
    private function defaultMessage(string $eventKey, array $variables): string
    {
        if ($eventKey === 'order_created') {
            return "Dear {$variables['customer_display_name']}, your order {$variables['order_no']} has been created.\nStatus: {$variables['status']}\nDue Date: {$variables['due_date_display']}\nGold Budget: {$variables['gold_budget']} gm\nDiamond Budget: {$variables['diamond_budget']} cts";
        }
        if ($eventKey === 'order_status_changed') {
            return "Order {$variables['order_no']} status updated.\nFrom: {$variables['from_status']}\nTo: {$variables['to_status']}\nDue Date: {$variables['due_date_display']}\nRemarks: " . ($variables['remarks'] !== '' ? $variables['remarks'] : '-');
        }
        if ($eventKey === 'order_ready') {
            return "Good news. Order {$variables['order_no']} is ready.\nCurrent Status: {$variables['status']}\nCustomer: {$variables['customer_display_name']}\nDue Date: {$variables['due_date_display']}";
        }
        if ($eventKey === 'order_over_budget') {
            return "Order {$variables['order_no']} is over budget.\nContext: {$variables['budget_context']}\nGold Over: {$variables['gold_over_budget']} gm\nDiamond Over: {$variables['diamond_over_budget']} cts\nStatus: {$variables['status']}\nCustomer: {$variables['customer_display_name']}";
        }

        return "Delayed order alert.\nOrder: {$variables['order_no']}\nCustomer: {$variables['customer_display_name']}\nStatus: {$variables['status']}\nDue Date: {$variables['due_date_display']}\nDelay: {$variables['delay_days']} day(s)";
    }

    /**
     * @param array<string,string> $variables
     */
    private function replacePlaceholders(string $template, array $variables): string
    {
        $replace = [];
        foreach ($variables as $key => $value) {
            $replace['{{' . $key . '}}'] = $value;
        }

        return strtr($template, $replace);
    }

    private function normalizeDate(string $date): string
    {
        $ts = strtotime($date);
        return $ts === false ? date('Y-m-d') : date('Y-m-d', $ts);
    }

    private function formatDate(string $date): string
    {
        $ts = strtotime($date);
        return $ts === false ? '-' : date('d-m-Y', $ts);
    }

    private function formatDateTime(string $dateTime): string
    {
        $ts = strtotime($dateTime);
        return $ts === false ? '-' : date('d-m-Y H:i', $ts);
    }

    private function formatNumber(float $value, int $precision): string
    {
        return number_format($value, $precision, '.', '');
    }
}
