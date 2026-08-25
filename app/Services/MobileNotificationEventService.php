<?php

namespace App\Services;

use App\Models\AdminUserModel;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

class MobileNotificationEventService
{
    private const WORKDAY_TIMEZONE = 'Asia/Kolkata';
    private const WORKDAY_START_HOUR = 11;
    private const WORKDAY_END_HOUR = 20;
    private const TERMINAL_ORDER_STATUSES = [
        'Ready',
        'Complete',
        'Completed',
        'Packed',
        'Delivered',
        'Dispatched',
        'Cancelled',
    ];

    private MobilePushService $pushService;
    private RbacService $rbacService;
    private AdminUserModel $adminUserModel;

    public function __construct(
        ?MobilePushService $pushService = null,
        ?RbacService $rbacService = null,
        ?AdminUserModel $adminUserModel = null
    ) {
        $this->pushService = $pushService ?? new MobilePushService();
        $this->rbacService = $rbacService ?? new RbacService();
        $this->adminUserModel = $adminUserModel ?? new AdminUserModel();
    }

    public function notifyOrderCreated(int $orderId, string $source = 'system'): array
    {
        if ($orderId <= 0) {
            return $this->emptySummary('Invalid order.');
        }

        $order = db_connect()->table('orders o')
            ->select('o.id, o.order_no, o.order_from, o.status, c.name AS customer_name')
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->where('o.id', $orderId)
            ->get()
            ->getRowArray();

        if (! is_array($order)) {
            return $this->emptySummary('Order not found.');
        }

        $orderNo = trim((string) ($order['order_no'] ?? '')) ?: ('#' . $orderId);
        $party = trim((string) (($order['customer_name'] ?? '') ?: ($order['order_from'] ?? '')));
        $message = 'Order ' . $orderNo . ' has been created';
        if ($party !== '') {
            $message .= ' for ' . $party;
        }
        $message .= '.';

        return $this->queueForPermission('orders.read', [
            'type' => 'order_created',
            'reference_table' => 'orders',
            'reference_id' => $orderId,
            'dedupe_key' => 'order-created:' . $orderId,
            'title' => 'New Order Created',
            'message' => $message,
            'payload' => [
                'type' => 'order_created',
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'status' => (string) ($order['status'] ?? ''),
                'source' => trim($source) ?: 'system',
            ],
        ]);
    }

    public function notifyFollowupAdded(int $orderId, int $followupId): array
    {
        if ($orderId <= 0 || $followupId <= 0) {
            return $this->emptySummary('Invalid followup.');
        }

        $db = db_connect();
        $row = $db->table('order_followups ofu')
            ->select('ofu.id, ofu.order_id, ofu.stage, ofu.description, ofu.next_followup_date, o.order_no, o.status')
            ->join('orders o', 'o.id = ofu.order_id', 'inner')
            ->where('ofu.id', $followupId)
            ->where('ofu.order_id', $orderId)
            ->get()
            ->getRowArray();

        if (! is_array($row)) {
            return $this->emptySummary('Followup not found.');
        }

        $this->cancelSupersededFollowupReminders($orderId, $followupId);

        $orderNo = trim((string) ($row['order_no'] ?? '')) ?: ('#' . $orderId);
        $stage = trim((string) ($row['stage'] ?? '')) ?: 'Updated';
        $immediate = $this->queueForPermission('orders.followup', [
            'type' => 'followup_added',
            'reference_table' => 'order_followups',
            'reference_id' => $followupId,
            'dedupe_key' => 'followup-added:' . $followupId,
            'title' => 'Order Follow-up Added',
            'message' => 'Order ' . $orderNo . ' follow-up updated to ' . $stage . '.',
            'payload' => [
                'type' => 'followup_added',
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'followup_id' => $followupId,
                'stage' => $stage,
            ],
        ]);

        $scheduled = $this->emptySummary('No next follow-up time was selected.');
        $timezone = new DateTimeZone(self::WORKDAY_TIMEZONE);
        $nextFollowup = trim((string) ($row['next_followup_date'] ?? ''));
        $nextAt = null;
        if ($nextFollowup !== '') {
            try {
                $nextAt = new DateTimeImmutable($nextFollowup, $timezone);
            } catch (\Throwable $e) {
                $nextAt = null;
            }
        }
        $orderStatus = strtolower(trim((string) ($row['status'] ?? '')));
        $terminalStatuses = array_map('strtolower', self::TERMINAL_ORDER_STATUSES);
        if ($nextAt !== null && $nextAt > new DateTimeImmutable('now', $timezone) && ! in_array($orderStatus, $terminalStatuses, true)) {
            $scheduled = $this->queueForPermission('orders.followup', [
                'type' => 'followup_due',
                'reference_table' => 'order_followups',
                'reference_id' => $followupId,
                'dedupe_key' => 'followup-due:' . $followupId,
                'title' => 'Follow-up Due',
                'message' => 'Order ' . $orderNo . ' follow-up is due now.',
                'scheduled_at' => $nextAt->format('Y-m-d H:i:s'),
                'payload' => [
                    'type' => 'followup_due',
                    'order_id' => $orderId,
                    'order_no' => $orderNo,
                    'followup_id' => $followupId,
                    'stage' => $stage,
                ],
            ]);
        }

        return [
            'queued' => (bool) ($immediate['queued'] ?? false) || (bool) ($scheduled['queued'] ?? false),
            'immediate' => $immediate,
            'scheduled' => $scheduled,
        ];
    }

    public function queueHourlyDelayedFollowups(?DateTimeImmutable $now = null): array
    {
        $timezone = new DateTimeZone(self::WORKDAY_TIMEZONE);
        $now = ($now ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone);

        if (! self::isWorkingHour($now)) {
            return [
                'working_hour' => false,
                'orders_scanned' => 0,
                'notifications_queued' => 0,
                'message' => 'Outside the 11:00-20:00 notification window.',
            ];
        }

        $db = db_connect();
        $hourStart = $now->setTime((int) $now->format('G'), 0, 0);
        $latestSubquery = $db->table('order_followups')
            ->select('MAX(id) AS id')
            ->groupBy('order_id')
            ->getCompiledSelect();

        $rows = $db->table('order_followups ofu')
            ->select('ofu.id, ofu.order_id, ofu.stage, ofu.next_followup_date, o.order_no, o.status')
            ->join('(' . $latestSubquery . ') latest', 'latest.id = ofu.id', 'inner', false)
            ->join('orders o', 'o.id = ofu.order_id', 'inner')
            ->where('ofu.next_followup_date IS NOT NULL', null, false)
            ->where('ofu.next_followup_date <', $hourStart->format('Y-m-d H:i:s'))
            ->whereNotIn('o.status', self::TERMINAL_ORDER_STATUSES)
            ->orderBy('ofu.next_followup_date', 'ASC')
            ->get()
            ->getResultArray();

        $queued = 0;
        $slot = $now->format('YmdH');
        foreach ($rows as $row) {
            $followupId = (int) ($row['id'] ?? 0);
            $orderId = (int) ($row['order_id'] ?? 0);
            if ($followupId <= 0 || $orderId <= 0) {
                continue;
            }

            $orderNo = trim((string) ($row['order_no'] ?? '')) ?: ('#' . $orderId);
            $dueAt = new DateTimeImmutable((string) $row['next_followup_date'], $timezone);
            if (! self::isDelayedSlotEligible($dueAt, $now)) {
                continue;
            }
            $dedupeKey = 'followup-delay:' . $followupId . ':' . $slot;
            $this->pushService->cancelUnsentByReferenceTypes(
                'order_followups',
                $followupId,
                ['followup_delay'],
                $dedupeKey
            );
            $summary = $this->queueForPermission('orders.followup', [
                'type' => 'followup_delay',
                'reference_table' => 'order_followups',
                'reference_id' => $followupId,
                'dedupe_key' => $dedupeKey,
                'title' => 'Delayed Follow-up',
                'message' => 'Order ' . $orderNo . ' follow-up is overdue since ' . $dueAt->format('d M, h:i A') . '.',
                'payload' => [
                    'type' => 'followup_delay',
                    'order_id' => $orderId,
                    'order_no' => $orderNo,
                    'followup_id' => $followupId,
                    'due_at' => (string) ($row['next_followup_date'] ?? ''),
                    'delay_slot' => $slot,
                ],
            ]);
            $queued += (int) ($summary['queued_count'] ?? 0);
        }

        return [
            'working_hour' => true,
            'orders_scanned' => count($rows),
            'notifications_queued' => $queued,
            'slot' => $slot,
        ];
    }

    public static function isWorkingHour(DateTimeInterface $time): bool
    {
        $hour = (int) $time->format('G');
        return $hour >= self::WORKDAY_START_HOUR && $hour <= self::WORKDAY_END_HOUR;
    }

    public static function isDelayedSlotEligible(DateTimeInterface $dueAt, DateTimeInterface $now): bool
    {
        if (! self::isWorkingHour($now)) {
            return false;
        }

        $timezone = new DateTimeZone(self::WORKDAY_TIMEZONE);
        $current = DateTimeImmutable::createFromInterface($now)->setTimezone($timezone);
        $due = DateTimeImmutable::createFromInterface($dueAt)->setTimezone($timezone);
        $hourStart = $current->setTime((int) $current->format('G'), 0, 0);

        return $due < $hourStart;
    }

    private function queueForPermission(string $permission, array $notification): array
    {
        $notification['defer_dispatch'] = true;
        $admins = $this->adminUserModel->where('is_active', 1)->orderBy('id', 'ASC')->findAll();
        $results = [];
        $queuedCount = 0;
        $failedCount = 0;
        $duplicateCount = 0;
        $baseDedupeKey = trim((string) ($notification['dedupe_key'] ?? ''));

        foreach ($admins as $admin) {
            $adminId = (int) ($admin['id'] ?? 0);
            if ($adminId <= 0 || ! $this->rbacService->userCan($adminId, $permission)) {
                continue;
            }

            $personalized = $notification;
            if ($baseDedupeKey !== '') {
                $personalized['dedupe_key'] = $baseDedupeKey . ':admin:' . $adminId;
            }

            $result = $this->pushService->queueForAdminRow($admin, $personalized);
            $results[$adminId] = $result;
            if (($result['queued'] ?? false) && ($result['created'] ?? false)) {
                $queuedCount++;
            } elseif ($result['duplicate'] ?? false) {
                $duplicateCount++;
            } else {
                $failedCount++;
            }
        }

        return [
            'queued' => $queuedCount > 0,
            'recipient_count' => count($results),
            'queued_count' => $queuedCount,
            'failed_count' => $failedCount,
            'duplicate_count' => $duplicateCount,
            'results' => $results,
        ];
    }

    private function cancelSupersededFollowupReminders(int $orderId, int $currentFollowupId): void
    {
        $rows = db_connect()->table('order_followups')
            ->select('id')
            ->where('order_id', $orderId)
            ->where('id !=', $currentFollowupId)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $this->pushService->cancelByReference('order_followups', $id);
            }
        }
    }

    private function emptySummary(string $message): array
    {
        return [
            'queued' => false,
            'recipient_count' => 0,
            'queued_count' => 0,
            'failed_count' => 0,
            'duplicate_count' => 0,
            'results' => [],
            'message' => $message,
        ];
    }
}
