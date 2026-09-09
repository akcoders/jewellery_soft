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

    public function notifyDiamondRequirementRaised(int $requirementId): array
    {
        $row = $this->diamondRequirement($requirementId);
        if ($row === null) {
            return $this->emptySummary('Diamond requirement not found.');
        }

        return $this->queueForPermission('diamond.inventory.manage', [
            'type' => 'diamond_requirement_raised',
            'reference_table' => 'diamond_requirements',
            'reference_id' => $requirementId,
            'dedupe_key' => 'diamond-requirement-raised:' . $requirementId,
            'title' => 'Diamond Requirement Approval',
            'message' => (string) $row['requirement_no'] . ' for order ' . (string) $row['order_no'] . ' is waiting for approval.',
            'payload' => [
                'type' => 'diamond_requirement_raised',
                'screen' => 'diamond_requirements',
                'requirement_id' => $requirementId,
                'order_id' => (int) $row['order_id'],
                'order_no' => (string) $row['order_no'],
            ],
        ]);
    }

    public function notifyDiamondRequirementAssigned(int $requirementId): array
    {
        $row = $this->diamondRequirement($requirementId);
        $assignedTo = (int) ($row['assigned_to'] ?? 0);
        if ($row === null || $assignedTo <= 0) {
            return $this->emptySummary('Diamond requirement assignee not found.');
        }

        return $this->pushService->queueForAdmin($assignedTo, [
            'type' => 'diamond_bag_assignment',
            'reference_table' => 'diamond_requirements',
            'reference_id' => $requirementId,
            'dedupe_key' => 'diamond-bag-assigned:' . $requirementId . ':admin:' . $assignedTo,
            'title' => 'Diamond Bag Assigned',
            'message' => 'Prepare ' . (string) $row['requirement_no'] . ' for order ' . (string) $row['order_no'] . '.',
            'defer_dispatch' => true,
            'payload' => [
                'type' => 'diamond_bag_assignment',
                'screen' => 'diamond_requirements',
                'requirement_id' => $requirementId,
                'order_id' => (int) $row['order_id'],
                'order_no' => (string) $row['order_no'],
            ],
        ]);
    }

    public function notifyDiamondBagReady(int $requirementId): array
    {
        $row = $this->diamondRequirement($requirementId);
        if ($row === null) {
            return $this->emptySummary('Diamond requirement not found.');
        }
        $summary = $this->queueForPermission('diamond.inventory.manage', [
            'type' => 'diamond_bag_ready',
            'reference_table' => 'diamond_requirements',
            'reference_id' => $requirementId,
            'dedupe_key' => 'diamond-bag-ready:' . $requirementId,
            'title' => 'Diamond Bag Ready',
            'message' => (string) ($row['bag_no'] ?: 'Bag') . ' is ready for order ' . (string) $row['order_no'] . '.',
            'payload' => [
                'type' => 'diamond_bag_ready',
                'screen' => 'diamond_requirements',
                'requirement_id' => $requirementId,
                'order_id' => (int) $row['order_id'],
                'order_no' => (string) $row['order_no'],
                'bag_id' => (int) ($row['bag_id'] ?? 0),
            ],
        ]);

        $requester = (int) ($row['requested_by'] ?? 0);
        if ($requester > 0 && ! $this->rbacService->userCan($requester, 'diamond.inventory.manage')) {
            $this->pushService->queueForAdmin($requester, [
                'type' => 'diamond_bag_ready',
                'reference_table' => 'diamond_requirements',
                'reference_id' => $requirementId,
                'dedupe_key' => 'diamond-bag-ready:' . $requirementId . ':requester:' . $requester,
                'title' => 'Diamond Bag Ready',
                'message' => (string) ($row['bag_no'] ?: 'Bag') . ' is ready for order ' . (string) $row['order_no'] . '.',
                'defer_dispatch' => true,
                'payload' => [
                    'type' => 'diamond_bag_ready',
                    'screen' => 'diamond_requirements',
                    'requirement_id' => $requirementId,
                    'order_id' => (int) $row['order_id'],
                    'bag_id' => (int) ($row['bag_id'] ?? 0),
                ],
            ]);
        }

        return $summary;
    }

    /**
     * Queue an admin notification after an inventory purchase, issue, or return
     * has been committed. This method intentionally contains its own failure
     * boundary: a temporary notification/database lookup problem must never
     * make a successfully posted stock transaction look unsuccessful.
     */
    public function notifyInventoryTransactionCreated(
        string $transactionType,
        string $materialType,
        string $referenceTable,
        int $referenceId,
        string $source = 'system',
        array $context = []
    ): array {
        try {
            $transactionType = strtolower(trim($transactionType));
            $eventMap = [
                'purchase' => [
                    'permission' => 'accounts.read',
                    'type' => 'purchase_created',
                    'screen' => 'purchases',
                    'title' => 'New Purchase Created',
                    'label' => 'purchase',
                ],
                'issue' => [
                    'permission' => 'issuements.read',
                    'type' => 'issuement_created',
                    'screen' => 'issuements',
                    'title' => 'New Issuement Created',
                    'label' => 'issuement',
                ],
                'return' => [
                    'permission' => 'issuements.read',
                    'type' => 'return_created',
                    'screen' => 'returns',
                    'title' => 'New Return Created',
                    'label' => 'return',
                ],
            ];

            if (! isset($eventMap[$transactionType]) || $referenceId <= 0) {
                return $this->emptySummary('Invalid inventory transaction.');
            }

            $materialType = trim($materialType) ?: 'Material';
            $referenceTable = trim($referenceTable);
            if ($referenceTable === '' || preg_match('/^[a-z0-9_]+$/i', $referenceTable) !== 1) {
                return $this->emptySummary('Invalid inventory reference.');
            }

            $row = [];
            $db = db_connect();
            if ($db->tableExists($referenceTable)) {
                $found = $db->table($referenceTable)->where('id', $referenceId)->get()->getRowArray();
                if (is_array($found)) {
                    $row = $found;
                }
            }
            $details = array_replace($row, $context);

            $referenceNo = $this->firstText($details, ['voucher_no', 'invoice_no', 'purchase_no', 'reference_no']);
            $partyName = $this->firstText($details, ['issue_to', 'return_from', 'supplier_name', 'party_name']);
            if ($partyName === '' && (int) ($details['vendor_id'] ?? 0) > 0 && $db->tableExists('vendors')) {
                $vendor = $db->table('vendors')->select('name')->where('id', (int) $details['vendor_id'])->get()->getRowArray();
                $partyName = trim((string) ($vendor['name'] ?? ''));
            }
            if ($partyName === '' && (int) ($details['karigar_id'] ?? 0) > 0 && $db->tableExists('karigars')) {
                $karigar = $db->table('karigars')->select('name')->where('id', (int) $details['karigar_id'])->get()->getRowArray();
                $partyName = trim((string) ($karigar['name'] ?? ''));
            }

            $event = $eventMap[$transactionType];
            $message = $materialType . ' ' . $event['label'];
            if ($referenceNo !== '') {
                $message .= ' ' . $referenceNo;
            }
            $message .= ' has been created';
            if ($partyName !== '') {
                $message .= $transactionType === 'purchase' ? ' from ' . $partyName : ' for ' . $partyName;
            }
            $message .= '.';

            return $this->queueForPermission($event['permission'], [
                'type' => $event['type'],
                'reference_table' => $referenceTable,
                'reference_id' => $referenceId,
                'dedupe_key' => $event['type'] . ':' . $referenceTable . ':' . $referenceId,
                'title' => $event['title'],
                'message' => $message,
                'payload' => [
                    'type' => $event['type'],
                    'screen' => $event['screen'],
                    'transaction_type' => $transactionType,
                    'material_type' => $materialType,
                    'transaction_id' => $referenceId,
                    'reference_table' => $referenceTable,
                    'reference_no' => $referenceNo,
                    'party_name' => $partyName,
                    'source' => trim($source) ?: 'system',
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Inventory transaction notification failed: {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->emptySummary('Unable to queue inventory transaction notification.');
        }
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

    /** @return array<string,mixed>|null */
    private function diamondRequirement(int $requirementId): ?array
    {
        $db = db_connect();
        if ($requirementId <= 0 || ! $db->tableExists('diamond_requirements')) {
            return null;
        }
        $row = $db->table('diamond_requirements dr')
            ->select('dr.*, o.order_no, b.bag_no')
            ->join('orders o', 'o.id = dr.order_id', 'inner')
            ->join('diamond_bags b', 'b.id = dr.bag_id', 'left')
            ->where('dr.id', $requirementId)->get()->getRowArray();
        return is_array($row) ? $row : null;
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

    private function firstText(array $values, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($values[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
