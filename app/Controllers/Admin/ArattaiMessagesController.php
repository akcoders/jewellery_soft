<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class ArattaiMessagesController extends BaseController
{
    public function index(): string
    {
        $db = db_connect();
        $status = strtolower(trim((string) $this->request->getGet('status')));
        $search = trim((string) $this->request->getGet('q'));
        $event = trim((string) $this->request->getGet('event'));
        $page = max(1, (int) $this->request->getGet('page'));
        $perPage = 50;

        $builder = $db->table('whatsapp_sender_queue q')->select('q.*')->where('q.provider_key', 'attrai');
        if (in_array($status, ['pending', 'processing', 'sent', 'failed', 'accepted', 'delivered'], true)) {
            if (in_array($status, ['accepted', 'delivered'], true) && $db->fieldExists('delivery_status', 'whatsapp_sender_queue')) {
                $builder->where('q.delivery_status', $status);
            } else {
                $builder->where('q.status', $status);
            }
        }
        if ($event !== '') {
            $builder->where('q.event_key', $event);
        }
        if ($search !== '') {
            $builder->groupStart()->like('q.message_no', $search)->orLike('q.recipient_number', $search)
                ->orLike('q.recipient_name', $search)->orLike('q.message_text', $search)
                ->orLike('q.error_message', $search)->groupEnd();
        }

        $total = (clone $builder)->countAllResults();
        $rows = $builder->orderBy('q.id', 'DESC')->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        foreach ($rows as &$row) {
            $row['event_label'] = $this->eventLabel((string) ($row['event_key'] ?? ''));
            $row['failure_reason'] = $this->failureReason($row);
        }
        unset($row);

        $summaryRows = $db->table('whatsapp_sender_queue')->select('status, COUNT(*) AS total', false)
            ->where('provider_key', 'attrai')->groupBy('status')->get()->getResultArray();
        $summary = ['all' => 0, 'pending' => 0, 'sent' => 0, 'failed' => 0, 'delivered' => 0];
        foreach ($summaryRows as $row) {
            $key = (string) ($row['status'] ?? '');
            $count = (int) ($row['total'] ?? 0);
            $summary['all'] += $count;
            if (array_key_exists($key, $summary)) {
                $summary[$key] = $count;
            }
        }
        if ($db->fieldExists('delivery_status', 'whatsapp_sender_queue')) {
            $summary['delivered'] = $db->table('whatsapp_sender_queue')->where('provider_key', 'attrai')
                ->where('delivery_status', 'delivered')->countAllResults();
        }
        $events = $db->table('whatsapp_sender_queue')->select('event_key')->where('provider_key', 'attrai')
            ->where('event_key IS NOT NULL', null, false)->distinct()->orderBy('event_key', 'ASC')->get()->getResultArray();

        return view('admin/arattai_messages/index', [
            'title' => 'Arattai Message Audit', 'rows' => $rows, 'summary' => $summary,
            'events' => array_column($events, 'event_key'),
            'filters' => ['status' => $status, 'event' => $event, 'q' => $search],
            'page' => $page, 'perPage' => $perPage, 'total' => $total,
        ]);
    }

    private function eventLabel(string $event): string
    {
        return match ($event) {
            'approval_requested' => 'Approval request sent to reviewer',
            'approval_outcome' => 'Approval result sent to requester',
            'karigar_assigned' => 'New order assigned to karigar',
            'follower_assigned' => 'Order assigned to follower',
            'issuement_to_karigar' => 'Material issuement sent to karigar',
            'karigar_payment' => 'Payment confirmation sent to karigar',
            'order_update_follower' => 'Order status update sent to follower',
            'order_status_reply' => 'Order status requested in Arattai chat',
            'order_status_prompt' => 'Order ID requested from Arattai user',
            'points_changed', 'task_overdue_points' => 'Performance points notification',
            'followup_overdue' => 'Overdue follow-up reminder',
            'followup_upcoming' => 'Upcoming follow-up reminder',
            'attrai_demo' => 'Manual integration test',
            default => $event !== '' ? ucwords(str_replace('_', ' ', $event)) : 'ERP notification',
        };
    }

    /** @param array<string,mixed> $row */
    private function failureReason(array $row): string
    {
        foreach (['provider_failure_reason', 'error_message'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $code = trim((string) ($decoded['code'] ?? ''));
                    $message = trim((string) ($decoded['message'] ?? ''));
                    return trim(($code !== '' ? $code . ': ' : '') . $message) ?: $value;
                }
                return $value;
            }
        }
        if ((string) ($row['status'] ?? '') === 'pending') {
            return (int) ($row['attempts'] ?? 0) > 0
                ? 'Previous attempt failed; queued for automatic retry.'
                : 'Waiting for the sender queue/cron to process it.';
        }
        if ((string) ($row['status'] ?? '') === 'sent') {
            return (string) ($row['delivery_status'] ?? '') === 'delivered'
                ? 'Arattai confirmed delivery to the device.'
                : 'Arattai accepted the API request; delivery confirmation may still be pending.';
        }
        return '';
    }
}
