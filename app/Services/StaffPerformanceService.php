<?php

namespace App\Services;

use App\Models\OrderFollowupScheduleModel;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class StaffPerformanceService
{
    public const BASE_SCORE = 100.0;
    public const TASK_ON_TIME_POINTS = 2.0;
    public const TASK_LATE_POINTS = -2.0;
    public const FOLLOWUP_ON_TIME_POINTS = 0.0;
    public const FOLLOWUP_LATE_POINTS = -2.0;
    public const RATING_POINT_STEP = 5.0;
    public const ORDER_ENTRY_POINTS = 0.25;
    public const PROPER_FOLLOWUP_POINTS = 2.0;
    public const DISAPPROVED_FOLLOWUP_POINTS = -2.0;
    public const ISSUEMENT_POINTS = 0.25;
    public const RETURN_POINTS = 0.25;
    public const RECEIVING_POINTS = 0.25;
    public const PURCHASE_POINTS = 0.25;
    /** Monthly score every staff member should reach. */
    public const MONTHLY_TARGET_POINTS = 200.0;
    /** Reward points earned for every score point above the monthly target. */
    public const REWARD_POINTS_PER_EXTRA_POINT = 1.0;

    private const TIMEZONE = 'Asia/Kolkata';
    private const ADMIN_ROLES = ['SUPER_ADMIN', 'ADMIN', 'OWNER'];
    /** These linked tasks are already scored by their follow-up/issuement activity. */
    private const DEDICATED_WORKFLOW_TASK_TYPES = ['order_followup', 'order_gold_request'];

    public static function taskUsesDedicatedWorkflowScore(?string $referenceType): bool
    {
        return in_array(trim((string) $referenceType), self::DEDICATED_WORKFLOW_TASK_TYPES, true);
    }

    /** One penalty unit starts as soon as the due time is crossed, then grows every 24 hours. */
    public static function lateDayUnits(?string $dueAt, ?string $evaluatedAt): int
    {
        $due = strtotime(trim((string) $dueAt));
        $evaluated = strtotime(trim((string) $evaluatedAt));
        if ($due === false || $evaluated === false || $evaluated <= $due) {
            return 0;
        }

        return (int) ceil(($evaluated - $due) / 86400);
    }

    public static function taskPoints(?string $dueAt, ?string $completedAt): float
    {
        if (strtotime(trim((string) $dueAt)) === false || strtotime(trim((string) $completedAt)) === false) {
            return 0.0;
        }
        $lateDays = self::lateDayUnits($dueAt, $completedAt);
        return $lateDays > 0 ? $lateDays * self::TASK_LATE_POINTS : self::TASK_ON_TIME_POINTS;
    }

    public function staffOptions(): array
    {
        $db = db_connect();
        if (! $db->tableExists('admin_users')) {
            return [];
        }

        $users = $db->table('admin_users')
            ->select('id, name, email, is_active')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()->getResultArray();
        if ($users === [] || ! $db->tableExists('user_roles') || ! $db->tableExists('roles')) {
            return $users;
        }

        $userIds = array_map(static fn(array $row): int => (int) $row['id'], $users);
        $roleRows = $db->table('user_roles ur')
            ->select('ur.user_id, r.role_code, r.name as role_name')
            ->join('roles r', 'r.id = ur.role_id', 'inner')
            ->whereIn('ur.user_id', $userIds)
            ->get()->getResultArray();
        $rolesByUser = [];
        foreach ($roleRows as $role) {
            $code = strtoupper(trim((string) (($role['role_code'] ?? '') ?: ($role['role_name'] ?? ''))));
            $rolesByUser[(int) $role['user_id']][] = $code;
        }

        $staff = [];
        foreach ($users as $user) {
            $roles = array_values(array_unique($rolesByUser[(int) $user['id']] ?? []));
            if (array_intersect(self::ADMIN_ROLES, $roles) !== []) {
                continue;
            }
            $user['roles'] = $roles;
            $user['role_label'] = $roles === []
                ? 'Staff'
                : ucwords(strtolower(str_replace('_', ' ', implode(', ', $roles))));
            $staff[] = $user;
        }

        return $staff;
    }

    /** Owners and administrators (not scored themselves) who may review the team. */
    public function isAdminUser(int $userId): bool
    {
        $db = db_connect();
        if ($userId <= 0 || ! $db->tableExists('user_roles') || ! $db->tableExists('roles')) {
            return false;
        }

        return $db->table('user_roles ur')
            ->join('roles r', 'r.id = ur.role_id', 'inner')
            ->join('admin_users au', 'au.id = ur.user_id', 'inner')
            ->where('ur.user_id', $userId)
            ->where('au.is_active', 1)
            ->whereIn('r.role_code', self::ADMIN_ROLES)
            ->countAllResults() > 0;
    }

    public function isStaffUser(int $userId): bool
    {
        foreach ($this->staffOptions() as $staff) {
            if ((int) ($staff['id'] ?? 0) === $userId) {
                return true;
            }
        }
        return false;
    }

    public function syncOrderAssignment(int $orderId, ?int $assignedTo, ?string $dueAt, ?int $createdBy): void
    {
        $db = db_connect();
        if (! $db->tableExists('order_followup_schedules')) {
            return;
        }
        if ($assignedTo !== null && ! $this->isStaffUser($assignedTo)) {
            throw new InvalidArgumentException('Please select an active non-admin staff follower.');
        }

        $normalizedDueAt = $this->normalizeDateTime($dueAt);
        $now = $this->now();
        $pending = $db->table('order_followup_schedules')
            ->where('order_id', $orderId)
            ->where('status', 'pending')
            ->orderBy('id', 'DESC')
            ->get()->getRowArray();

        if ($assignedTo === null || $normalizedDueAt === null) {
            foreach ($db->table('order_followup_schedules')->select('id')->where('order_id', $orderId)->where('status', 'pending')->get()->getResultArray() as $row) {
                (new WorkflowTaskService())->cancel('order_followup', (int) $row['id']);
            }
            $db->table('order_followup_schedules')->where('order_id', $orderId)->where('status', 'pending')->update([
                'status' => 'cancelled',
                'updated_at' => $now,
            ]);
            $db->table('orders')->where('id', $orderId)->update([
                'followup_assigned_to' => $assignedTo,
                'followup_due_at' => $normalizedDueAt,
                'updated_at' => $now,
            ]);
            return;
        }

        if ($pending) {
            foreach ($db->table('order_followup_schedules')->select('id')->where('order_id', $orderId)
                ->where('status', 'pending')->where('id !=', (int) $pending['id'])->get()->getResultArray() as $row) {
                (new WorkflowTaskService())->cancel('order_followup', (int) $row['id']);
            }
            $db->table('order_followup_schedules')->where('id', (int) $pending['id'])->update([
                'assigned_to' => $assignedTo,
                'due_at' => $normalizedDueAt,
                'updated_at' => $now,
            ]);
            $db->table('order_followup_schedules')
                ->where('order_id', $orderId)
                ->where('status', 'pending')
                ->where('id !=', (int) $pending['id'])
                ->update(['status' => 'cancelled', 'updated_at' => $now]);
            $scheduleId = (int) $pending['id'];
        } else {
            $scheduleId = (int) (new OrderFollowupScheduleModel())->insert([
                'order_id' => $orderId,
                'assigned_to' => $assignedTo,
                'due_at' => $normalizedDueAt,
                'status' => 'pending',
                'created_by' => $createdBy,
            ], true);
        }

        $orderNo = (string) ($db->table('orders')->select('order_no')->where('id', $orderId)->get()->getRowArray()['order_no'] ?? ('#' . $orderId));
        (new WorkflowTaskService())->assign('order_followup', $scheduleId, $assignedTo,
            'Follow up order ' . $orderNo, $normalizedDueAt, $createdBy);

        $db->table('orders')->where('id', $orderId)->update([
            'followup_assigned_to' => $assignedTo,
            'followup_due_at' => $normalizedDueAt,
            'updated_at' => $now,
        ]);
    }

    public function completeOrderFollowup(
        int $orderId,
        int $followupId,
        int $completedBy,
        ?string $nextDueAt,
        ?string $completedAt = null
    ): void
    {
        $db = db_connect();
        if (! $db->tableExists('order_followup_schedules')) {
            return;
        }

        $eventAt = $this->normalizeDateTime($completedAt) ?? $this->now();
        $now = $this->now();
        $schedule = $db->table('order_followup_schedules')
            ->where('order_id', $orderId)
            ->where('status', 'pending')
            ->orderBy('due_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()->getRowArray();
        if ($schedule) {
            $onTime = $eventAt <= (string) $schedule['due_at'];
            $completedByAssignee = $completedBy === (int) $schedule['assigned_to'];
            $status = $completedByAssignee
                ? ($onTime ? 'completed_on_time' : 'completed_late')
                : 'completed_by_other';
            $db->table('order_followup_schedules')->where('id', (int) $schedule['id'])->update([
                'status' => $status,
                'completed_followup_id' => $followupId,
                'completed_by' => $completedBy,
                'completed_at' => $eventAt,
                'score_delta' => 0,
                'updated_at' => $now,
            ]);
            (new WorkflowTaskService())->complete('order_followup', (int) $schedule['id'], $completedBy, $eventAt);
        }

        $order = $db->table('orders')->select('followup_assigned_to, status')->where('id', $orderId)->get()->getRowArray();
        $assignedTo = (int) ($order['followup_assigned_to'] ?? 0);
        $terminal = in_array((string) ($order['status'] ?? ''), ['Ready', 'Packed', 'Dispatched', 'Completed', 'Cancelled'], true);
        $normalizedNextDue = $terminal ? null : $this->normalizeDateTime($nextDueAt);
        if ($assignedTo > 0 && $normalizedNextDue !== null) {
            $newScheduleId = (int) (new OrderFollowupScheduleModel())->insert([
                'order_id' => $orderId,
                'assigned_to' => $assignedTo,
                'due_at' => $normalizedNextDue,
                'status' => 'pending',
                'created_by' => $completedBy,
            ], true);
            $orderNo = (string) ($db->table('orders')->select('order_no')->where('id', $orderId)->get()->getRowArray()['order_no'] ?? ('#' . $orderId));
            (new WorkflowTaskService())->assign('order_followup', $newScheduleId, $assignedTo,
                'Follow up order ' . $orderNo, $normalizedNextDue, $completedBy);
        }
        $db->table('orders')->where('id', $orderId)->update([
            'followup_due_at' => $normalizedNextDue,
            'updated_at' => $now,
        ]);
    }

    public function closeOrderFollowupSchedules(int $orderId): void
    {
        $db = db_connect();
        if (! $db->tableExists('order_followup_schedules')) {
            return;
        }

        $now = $this->now();
        foreach ($db->table('order_followup_schedules')->select('id')->where('order_id', $orderId)->where('status', 'pending')->get()->getResultArray() as $row) {
            (new WorkflowTaskService())->cancel('order_followup', (int) $row['id']);
        }
        $db->table('order_followup_schedules')
            ->where('order_id', $orderId)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'score_delta' => 0, 'updated_at' => $now]);
        $db->table('orders')->where('id', $orderId)->update([
            'followup_due_at' => null,
            'updated_at' => $now,
        ]);
    }

    public function dashboardData(int $year, int $month, ?int $userId = null): array
    {
        $staff = $this->staffOptions();
        if ($userId !== null) {
            $staff = array_values(array_filter($staff, static fn(array $row): bool => (int) $row['id'] === $userId));
        }
        $staffIds = array_map(static fn(array $row): int => (int) $row['id'], $staff);
        [$start, $end] = $this->monthRange($year, $month);
        $tasks = $this->taskRows($staffIds, $start, $end);
        $followups = $this->followupRows($staffIds, $start, $end);
        $ratings = $this->ratingRows($staffIds, $start, $end);
        $activities = $this->activityRows($staffIds, $start, $end);

        $rows = [];
        $eventsByUser = [];
        foreach ($staff as $person) {
            $id = (int) $person['id'];
            $personTasks = array_values(array_filter($tasks, static fn(array $row): bool => (int) $row['admin_user_id'] === $id));
            $personFollowups = array_values(array_filter($followups, static fn(array $row): bool => (int) $row['assigned_to'] === $id));
            $personRatings = array_values(array_filter($ratings, static fn(array $row): bool => (int) $row['followup_assigned_to'] === $id));
            $personActivities = array_values(array_filter($activities, static fn(array $row): bool => (int) $row['user_id'] === $id));
            $metrics = $this->metrics($personTasks, $personFollowups, $personRatings, $personActivities, $start, $end);
            $rows[] = $person + $metrics;
            $eventsByUser[$id] = $this->events($personTasks, $personFollowups, $personRatings, $personActivities, $start, $end);
        }

        usort($rows, static fn(array $a, array $b): int => [
            (int) ($b['has_scored_activity'] ?? false),
            $b['score'],
            $b['on_time_rate'],
        ] <=> [
            (int) ($a['has_scored_activity'] ?? false),
            $a['score'],
            $a['on_time_rate'],
        ]);
        $completed = array_sum(array_column($rows, 'completed_actions'));
        $onTime = array_sum(array_column($rows, 'on_time_actions'));
        $overdue = array_sum(array_column($rows, 'overdue_actions'));
        $dueActions = array_sum(array_column($rows, 'due_action_count'));
        $activeRows = array_values(array_filter($rows, static fn(array $row): bool => (bool) ($row['has_scored_activity'] ?? false)));
        $scoreTotal = array_sum(array_column($activeRows, 'score'));
        $followupSubmissions = array_sum(array_column($rows, 'followup_submission_count'));
        $meaningfulUpdates = array_sum(array_column($rows, 'meaningful_updates'));
        $approvedFollowups = array_sum(array_column($rows, 'approved_followups'));
        $noPointFollowups = array_sum(array_column($rows, 'no_point_followups'));
        $disapprovedFollowups = array_sum(array_column($rows, 'disapproved_followups'));
        $duplicateFollowups = 0;
        $missingImageFollowups = 0;
        $qualityReviewedFollowups = $approvedFollowups + $noPointFollowups + $disapprovedFollowups;
        $actionRequired = count(array_filter($rows, static fn(array $row): bool => (bool) ($row['needs_attention'] ?? false)));
        $topPerformer = $activeRows[0] ?? null;
        $earnedBreakdown = $lostBreakdown = [];
        foreach ($rows as $row) {
            foreach ((array) ($row['point_earned_breakdown'] ?? []) as $reason => $points) {
                $earnedBreakdown[$reason] = round(($earnedBreakdown[$reason] ?? 0) + (float) $points, 2);
            }
            foreach ((array) ($row['point_loss_breakdown'] ?? []) as $reason => $points) {
                $lostBreakdown[$reason] = round(($lostBreakdown[$reason] ?? 0) + (float) $points, 2);
            }
        }
        arsort($earnedBreakdown);
        arsort($lostBreakdown);
        $unassignedOpenOrders = 0;
        $db = db_connect();
        if ($db->tableExists('orders')) {
            $unassignedOpenOrders = $db->table('orders')
                ->whereNotIn('status', ['Ready', 'Packed', 'Dispatched', 'Completed', 'Cancelled'])
                ->groupStart()
                    ->where('followup_assigned_to', null)
                    ->orWhere('followup_due_at', null)
                ->groupEnd()
                ->countAllResults();
        }

        return [
            'rows' => $rows,
            'events_by_user' => $eventsByUser,
            'tasks' => $tasks,
            'totals' => [
                'staff_count' => count($activeRows),
                'total_staff_count' => count($rows),
                'average_score' => count($activeRows) > 0 ? round($scoreTotal / count($activeRows), 1) : 0,
                'completed_actions' => $completed,
                'on_time_rate' => $dueActions > 0 ? round(($onTime / $dueActions) * 100, 1) : 0,
                'overdue_actions' => $overdue,
                'unassigned_open_orders' => $unassignedOpenOrders,
                'points_earned' => round(array_sum(array_column($rows, 'points_earned')), 1),
                'points_lost' => round(array_sum(array_column($rows, 'points_lost')), 1),
                'followup_submissions' => $followupSubmissions,
                'meaningful_updates' => $meaningfulUpdates,
                'approved_followups' => $approvedFollowups,
                'no_point_followups' => $noPointFollowups,
                'disapproved_followups' => $disapprovedFollowups,
                'duplicate_followups' => $duplicateFollowups,
                'missing_image_followups' => $missingImageFollowups,
                'evidence_compliance_rate' => $qualityReviewedFollowups > 0 ? round(max(0, min(100, (($qualityReviewedFollowups - $missingImageFollowups) / $qualityReviewedFollowups) * 100)), 1) : 0,
                'unique_update_rate' => $qualityReviewedFollowups > 0 ? round(max(0, min(100, ($approvedFollowups / $qualityReviewedFollowups) * 100)), 1) : 0,
                'action_required_count' => $actionRequired,
                'top_performer_name' => is_array($topPerformer) ? (string) ($topPerformer['name'] ?? '') : '',
                'top_performer_score' => is_array($topPerformer) ? (float) ($topPerformer['score'] ?? 0) : 0,
                'point_earned_breakdown' => $earnedBreakdown,
                'point_loss_breakdown' => $lostBreakdown,
                'monthly_target' => self::MONTHLY_TARGET_POINTS,
                'target_achievers' => count(array_filter($rows, static fn(array $row): bool => (bool) ($row['target_achieved'] ?? false))),
                'reward_points_total' => round(array_sum(array_column($rows, 'reward_points')), 1),
                'returns' => array_sum(array_column($rows, 'returns')),
                'receivings' => array_sum(array_column($rows, 'receivings')),
                'average_target_progress' => count($activeRows) > 0 ? round(array_sum(array_column($activeRows, 'target_progress')) / count($activeRows), 1) : 0,
            ],
        ];
    }

    public function ownPerformance(int $userId, ?int $year = null, ?int $month = null): array
    {
        $year ??= (int) date('Y');
        $month ??= (int) date('n');
        $data = $this->dashboardData($year, $month, $userId);
        $row = $data['rows'][0] ?? [
            'id' => $userId,
            'name' => 'Staff',
            'score' => self::BASE_SCORE,
            'points_earned' => 0,
            'points_lost' => 0,
            'completed_actions' => 0,
            'on_time_actions' => 0,
            'overdue_actions' => 0,
            'due_action_count' => 0,
            'on_time_rate' => 0,
            'task_on_time' => 0,
            'task_late' => 0,
            'task_overdue' => 0,
            'followup_on_time' => 0,
            'followup_late' => 0,
            'followup_overdue' => 0,
            'followup_delay_days' => 0,
            'task_delay_days' => 0,
            'followup_approval_credit_days' => 0,
            'followup_approval_credit_points' => 0,
            'rating_count' => 0,
            'rating_average' => 0,
            'order_entries' => 0,
            'proper_followups' => 0,
            'approved_followups' => 0,
            'no_point_followups' => 0,
            'disapproved_followups' => 0,
            'followup_submission_count' => 0,
            'meaningful_updates' => 0,
            'duplicate_followups' => 0,
            'missing_image_followups' => 0,
            'evidence_compliance_rate' => 0,
            'unique_update_rate' => 0,
            'issuements' => 0,
            'returns' => 0,
            'receivings' => 0,
            'purchases' => 0,
            'performance_band' => 'No activity',
            'analysis_summary' => 'No scored activity was recorded for this period.',
            'needs_attention' => false,
            'has_scored_activity' => false,
            'point_earned_breakdown' => [],
            'point_loss_breakdown' => [],
        ] + $this->targetProgress(self::BASE_SCORE);
        return [
            'year' => $year,
            'month' => $month,
            'score_rules' => $this->scoreRules(),
            'summary' => $row,
            'events' => $data['events_by_user'][$userId] ?? [],
        ];
    }

    public function scoreRules(): array
    {
        return [
            'base_score' => self::BASE_SCORE,
            'task_on_time' => self::TASK_ON_TIME_POINTS,
            'task_late_or_overdue' => self::TASK_LATE_POINTS,
            'task_delay_per_day' => self::TASK_LATE_POINTS,
            'followup_on_time' => 0,
            'followup_late_or_overdue' => self::FOLLOWUP_LATE_POINTS,
            'followup_delay_per_day' => self::FOLLOWUP_LATE_POINTS,
            'rating_point_step' => self::RATING_POINT_STEP,
            'order_entry' => self::ORDER_ENTRY_POINTS,
            'proper_followup' => self::PROPER_FOLLOWUP_POINTS,
            'approved_followup' => self::PROPER_FOLLOWUP_POINTS,
            'disapproved_followup' => self::DISAPPROVED_FOLLOWUP_POINTS,
            'issuement' => self::ISSUEMENT_POINTS,
            'return' => self::RETURN_POINTS,
            'receiving' => self::RECEIVING_POINTS,
            'purchase' => self::PURCHASE_POINTS,
            'monthly_target' => self::MONTHLY_TARGET_POINTS,
            'reward_per_extra_point' => self::REWARD_POINTS_PER_EXTRA_POINT,
        ];
    }

    private function taskRows(array $staffIds, string $start, string $end): array
    {
        $db = db_connect();
        if ($staffIds === [] || ! $db->tableExists('mobile_tasks')) {
            return [];
        }
        return $db->table('mobile_tasks t')
            ->select('t.*, assignee.name as assignee_name, creator.name as created_by_name')
            ->join('admin_users assignee', 'assignee.id = t.admin_user_id', 'left')
            ->join('admin_users creator', 'creator.id = t.created_by', 'left')
            ->whereIn('t.admin_user_id', $staffIds)
            ->where('t.counts_for_performance', 1)
            ->where('t.scheduled_at <', $end)
            ->groupStart()
                ->groupStart()->where('t.is_done', 1)->where('t.completed_at >=', $start)->groupEnd()
                ->orWhere('t.is_done', 0)
            ->groupEnd()
            ->where('t.status !=', 'cancelled')
            // Follow-up and gold-issuement mirror tasks already have their own score.
            // Other assigned work, linked or standalone, uses the normal task rule once.
            ->groupStart()
                ->where('t.reference_type', null)
                ->orWhereNotIn('t.reference_type', self::DEDICATED_WORKFLOW_TASK_TYPES)
            ->groupEnd()
            ->orderBy('t.scheduled_at', 'DESC')
            ->get()->getResultArray();
    }

    private function followupRows(array $staffIds, string $start, string $end): array
    {
        $db = db_connect();
        if ($staffIds === [] || ! $db->tableExists('order_followup_schedules')) {
            return [];
        }

        $builder = $db->table('order_followup_schedules s')
            ->select('s.*, o.order_no, au.name as completed_by_name, completed_followup.approval_status as completed_followup_approval_status, completed_followup.points_enabled as completed_followup_points_enabled')
            ->join('orders o', 'o.id = s.order_id', 'left')
            ->join('admin_users au', 'au.id = s.completed_by', 'left')
            ->join('order_followups completed_followup', 'completed_followup.id = s.completed_followup_id', 'left')
            ->whereIn('s.assigned_to', $staffIds)
            ->where('s.due_at <', $end)
            ->where('s.status !=', 'cancelled')
            ->groupStart()
                ->where('s.status', 'pending')
                ->orWhere('s.completed_at >=', $start)
                ->orWhere('s.due_at >=', $start);
        return $builder->groupEnd()
            ->orderBy('s.due_at', 'DESC')
            ->get()->getResultArray();
    }

    private function ratingRows(array $staffIds, string $start, string $end): array
    {
        $db = db_connect();
        if ($staffIds === [] || ! $db->tableExists('orders') || ! $db->fieldExists('completion_rating', 'orders')) {
            return [];
        }
        return $db->table('orders o')
            ->select('o.id, o.order_no, o.order_name, o.followup_assigned_to, o.completion_rating, o.rating_comment, o.rated_at, rater.name AS rated_by_name')
            ->join('admin_users rater', 'rater.id = o.rated_by', 'left')
            ->whereIn('o.followup_assigned_to', $staffIds)
            ->where('o.completion_rating IS NOT NULL', null, false)
            ->where('o.rated_at >=', $start)
            ->where('o.rated_at <', $end)
            ->orderBy('o.rated_at', 'DESC')
            ->get()->getResultArray();
    }

    private function activityRows(array $staffIds, string $start, string $end): array
    {
        $db = db_connect();
        if ($staffIds === []) {
            return [];
        }
        $rows = [];
        $disapprovedFollowupIds = [];
        if ($db->tableExists('orders') && $db->fieldExists('created_by', 'orders')) {
            foreach ($db->table('orders')->select('id, order_no, order_name, created_by, created_at')
                ->whereIn('created_by', $staffIds)->where('created_at >=', $start)->where('created_at <', $end)->get()->getResultArray() as $row) {
                $rows[] = $this->activity((int) $row['created_by'], 'Order Entry', (string) (($row['order_no'] ?? '') ?: ('Order #' . $row['id'])), (string) ($row['order_name'] ?? ''), (string) $row['created_at'], self::ORDER_ENTRY_POINTS, (int) $row['id']);
            }
        }
        if ($db->tableExists('order_followups')) {
            $quality = new FollowupQualityService($db);
            foreach ($db->table('order_followups f')->select('f.id, f.order_id, f.stage, f.description, f.followup_taken_by, f.followup_taken_on, f.image_path, f.approval_status, f.points_enabled, f.review_note, f.reviewed_at, f.duplicate_followup_id, f.duplicate_reason, f.duplicate_override, o.order_no')
                ->join('orders o', 'o.id = f.order_id', 'left')->whereIn('f.followup_taken_by', $staffIds)
                ->groupStart()
                    ->groupStart()->where('f.reviewed_at >=', $start)->where('f.reviewed_at <', $end)->groupEnd()
                    ->orGroupStart()->where('f.reviewed_at', null)->where('f.followup_taken_on >=', $start)->where('f.followup_taken_on <', $end)->groupEnd()
                ->groupEnd()->get()->getResultArray() as $row) {
                $userId = (int) $row['followup_taken_by'];
                $title = (string) (($row['order_no'] ?? '') ?: ('Order #' . $row['order_id']));
                $at = (string) (($row['reviewed_at'] ?? '') ?: $row['followup_taken_on']);
                $orderId = (int) $row['order_id'];
                $proofUrl = $quality->hasValidImage($row)
                    ? base_url(ltrim((string) $row['image_path'], '/'))
                    : null;
                foreach ($quality->scoreEvents($row) as $scoreEvent) {
                    if ($scoreEvent['type'] === 'Disapproved follow-up') {
                        $disapprovedFollowupIds[(int) $row['id']] = true;
                    }
                    $reference = $scoreEvent['type'] === 'Approved follow-up'
                        ? (string) (($row['stage'] ?? '') ?: ($row['description'] ?? ''))
                        : $scoreEvent['reason'];
                    $rows[] = $this->activity($userId, $scoreEvent['type'], $title, $reference, $at, $scoreEvent['score_delta'], $orderId, [
                        'followup_id' => (int) $row['id'],
                        'matched_followup_id' => (int) ($row['duplicate_followup_id'] ?? 0),
                        'proof_url' => $proofUrl,
                    ]);
                }
            }
        }
        if ($db->tableExists('mobile_approval_requests')) {
            $rejectedFollowupRequests = $db->table('mobile_approval_requests')
                ->select('id, subject_table, subject_id, requested_by, payload_json, review_note, reviewed_at')
                ->where('request_type', 'followup')
                ->where('status', 'rejected')
                ->whereIn('requested_by', $staffIds)
                ->where('reviewed_at >=', $start)
                ->where('reviewed_at <', $end)
                ->orderBy('id', 'ASC')
                ->get()->getResultArray();
            foreach ($rejectedFollowupRequests as $request) {
                $payload = json_decode((string) ($request['payload_json'] ?? '{}'), true);
                $payload = is_array($payload) ? $payload : [];
                $followupId = (string) ($request['subject_table'] ?? '') === 'order_followups'
                    ? (int) ($request['subject_id'] ?? 0)
                    : (int) ($payload['followup_id'] ?? 0);
                if ($followupId > 0 && isset($disapprovedFollowupIds[$followupId])) {
                    continue;
                }
                if ($followupId > 0) {
                    $disapprovedFollowupIds[$followupId] = true;
                }
                $orderId = (int) ($payload['order_id'] ?? 0);
                $reason = 'Follow-up disapproved by administrator.';
                if (trim((string) ($request['review_note'] ?? '')) !== '') {
                    $reason .= ' ' . trim((string) $request['review_note']);
                }
                $rows[] = $this->activity(
                    (int) $request['requested_by'],
                    'Disapproved follow-up',
                    $orderId > 0 ? 'Order #' . $orderId : 'Rejected follow-up request #' . $request['id'],
                    $reason,
                    (string) $request['reviewed_at'],
                    self::DISAPPROVED_FOLLOWUP_POINTS,
                    $orderId,
                    ['followup_id' => $followupId]
                );
            }
        }

        $issuements = [];
        foreach (['gold_inventory_issue_headers', 'issue_headers', 'stone_inventory_issue_headers'] as $table) {
            if (! $db->tableExists($table) || ! $db->fieldExists('created_by', $table)) {
                continue;
            }
            $issueBuilder = $db->table($table)->select('id, voucher_no, issue_date, created_by, created_at')->whereIn('created_by', $staffIds)
                ->where('issue_date >=', substr($start, 0, 10))->where('issue_date <', substr($end, 0, 10));
            if ($db->fieldExists('receive_movement_id', $table)) {
                // Stone auto-issued while receiving an order is system generated, not issuement work.
                $issueBuilder->where('receive_movement_id', null);
            }
            foreach ($issueBuilder->get()->getResultArray() as $row) {
                $voucher = trim((string) ($row['voucher_no'] ?? ''));
                $key = (int) $row['created_by'] . '|' . ($voucher !== '' ? $voucher : ($table . ':' . $row['id']));
                $issuements[$key] = $this->activity((int) $row['created_by'], 'Issuement', $voucher !== '' ? $voucher : ('Issue #' . $row['id']), 'Material issued', (string) (($row['created_at'] ?? '') ?: $row['issue_date']), self::ISSUEMENT_POINTS);
            }
        }
        array_push($rows, ...array_values($issuements));

        $returns = [];
        foreach (['gold_inventory_return_headers', 'return_headers', 'stone_inventory_return_headers'] as $table) {
            if (! $db->tableExists($table) || ! $db->fieldExists('created_by', $table)) {
                continue;
            }
            foreach ($db->table($table)->select('id, voucher_no, return_date, created_by, created_at')
                ->whereIn('created_by', $staffIds)
                ->where('return_date >=', substr($start, 0, 10))->where('return_date <', substr($end, 0, 10))
                ->get()->getResultArray() as $row) {
                $voucher = trim((string) ($row['voucher_no'] ?? ''));
                $key = (int) $row['created_by'] . '|' . ($voucher !== '' ? $voucher : ($table . ':' . $row['id']));
                $returns[$key] = $this->activity(
                    (int) $row['created_by'],
                    'Return',
                    $voucher !== '' ? $voucher : ('Return #' . $row['id']),
                    'Material returned',
                    (string) (($row['created_at'] ?? '') ?: $row['return_date']),
                    self::RETURN_POINTS
                );
            }
        }
        array_push($rows, ...array_values($returns));

        if ($db->tableExists('order_receive_summaries') && $db->fieldExists('created_by', 'order_receive_summaries')) {
            foreach ($db->table('order_receive_summaries rs')
                ->select('rs.id, rs.order_id, rs.created_by, rs.created_at, o.order_no')
                ->join('orders o', 'o.id = rs.order_id', 'left')
                ->whereIn('rs.created_by', $staffIds)
                ->where('rs.created_at >=', $start)->where('rs.created_at <', $end)
                ->get()->getResultArray() as $row) {
                $orderNo = trim((string) ($row['order_no'] ?? ''));
                $rows[] = $this->activity(
                    (int) $row['created_by'],
                    'Receiving',
                    $orderNo !== '' ? $orderNo : ('Order #' . $row['order_id']),
                    'Finished jewellery received',
                    (string) $row['created_at'],
                    self::RECEIVING_POINTS,
                    (int) $row['order_id']
                );
            }
        }

        foreach (['gold_inventory_purchase_headers', 'purchase_headers', 'stone_inventory_purchase_headers'] as $table) {
            if (! $db->tableExists($table) || ! $db->fieldExists('created_by', $table)) {
                continue;
            }
            foreach ($db->table($table)->select('id, invoice_no, supplier_name, purchase_date, created_by, created_at')->whereIn('created_by', $staffIds)
                ->where('purchase_date >=', substr($start, 0, 10))->where('purchase_date <', substr($end, 0, 10))->get()->getResultArray() as $row) {
                $rows[] = $this->activity((int) $row['created_by'], 'Purchase', (string) (($row['invoice_no'] ?? '') ?: ('Purchase #' . $row['id'])), (string) ($row['supplier_name'] ?? ''), (string) (($row['created_at'] ?? '') ?: $row['purchase_date']), self::PURCHASE_POINTS);
            }
        }
        return $rows;
    }

    private function activity(int $userId, string $type, string $title, string $reference, string $at, float $points, int $orderId = 0, array $details = []): array
    {
        return ['user_id' => $userId, 'type' => $type, 'title' => $title, 'reference' => $reference, 'occurred_at' => $at, 'score_delta' => $points, 'status' => 'completed', 'order_id' => $orderId] + $details;
    }

    private function metrics(
        array $tasks,
        array $followups,
        array $ratings,
        array $activities,
        string $periodStart,
        string $periodEnd
    ): array
    {
        $now = $this->now();
        $taskOnTime = $taskLate = $taskOverdue = 0;
        $followupOnTime = $followupLate = $followupOverdue = 0;
        $taskDelayDays = 0;
        $followupDelayDays = 0;
        $earned = $lost = 0.0;
        $earnedBreakdown = $lostBreakdown = [];

        foreach ($tasks as $task) {
            $status = strtolower((string) ($task['status'] ?? 'pending'));
            $done = (int) ($task['is_done'] ?? 0) === 1 || str_starts_with($status, 'completed');
            $scheduledAt = (string) ($task['scheduled_at'] ?? '');
            $completedAt = (string) ($task['completed_at'] ?? '');
            $completedByOther = $done
                && (int) ($task['completed_by'] ?? 0) > 0
                && (int) ($task['admin_user_id'] ?? 0) > 0
                && (int) $task['completed_by'] !== (int) $task['admin_user_id'];
            if ($completedByOther) {
                $delayDays = 0;
                $delta = 0.0;
            } elseif ($done) {
                $delayDays = $this->delayUnitsInPeriod($scheduledAt, $completedAt, $periodStart, $periodEnd);
                $isOnTime = self::lateDayUnits($scheduledAt, $completedAt) === 0;
                $completedInPeriod = $completedAt >= $periodStart && $completedAt < $periodEnd;
                $delta = $isOnTime
                    ? ($completedInPeriod ? self::TASK_ON_TIME_POINTS : 0.0)
                    : $delayDays * self::TASK_LATE_POINTS;
                $isOnTime ? $taskOnTime++ : $taskLate++;
            } elseif ($scheduledAt < $now) {
                $delayDays = $this->delayUnitsInPeriod($scheduledAt, $now, $periodStart, $periodEnd);
                $delta = $delayDays * self::TASK_LATE_POINTS;
                $taskOverdue++;
            } else {
                $delayDays = 0;
                $delta = 0.0;
            }
            $taskDelayDays += $delayDays;
            $delta >= 0 ? $earned += $delta : $lost += abs($delta);
            if ($delta > 0) $earnedBreakdown['Completed tasks'] = ($earnedBreakdown['Completed tasks'] ?? 0) + $delta;
            if ($delta < 0) $lostBreakdown['Late tasks'] = ($lostBreakdown['Late tasks'] ?? 0) + abs($delta);
        }

        foreach ($followups as $followup) {
            $status = strtolower((string) ($followup['status'] ?? 'pending'));
            $effectiveAt = (string) (($followup['performance_until_at'] ?? '') ?: $now);
            $delay = $this->followupDelayBreakdown($followup, $periodStart, $periodEnd, $now);
            if (str_starts_with($status, 'completed')) {
                $onTime = self::lateDayUnits(
                    (string) ($followup['due_at'] ?? ''),
                    (string) ($followup['completed_at'] ?? '')
                ) === 0;
                $delta = $delay['penalty_days'] * self::FOLLOWUP_LATE_POINTS;
                $onTime ? $followupOnTime++ : $followupLate++;
            } elseif ($effectiveAt > (string) ($followup['due_at'] ?? '')) {
                $delta = $delay['penalty_days'] * self::FOLLOWUP_LATE_POINTS;
                if (! empty($followup['performance_until_at'])) {
                    $followupLate++;
                } else {
                    $followupOverdue++;
                }
            } else {
                $delta = 0.0;
            }
            $followupDelayDays += $delay['penalty_days'];
            $delta >= 0 ? $earned += $delta : $lost += abs($delta);
            if ($delta < 0) $lostBreakdown['Follow-up delay'] = ($lostBreakdown['Follow-up delay'] ?? 0) + abs($delta);
        }

        $ratingTotal = 0;
        foreach ($ratings as $rating) {
            $value = max(1, min(5, (int) ($rating['completion_rating'] ?? 0)));
            $ratingTotal += $value;
            $delta = ($value - 3) * self::RATING_POINT_STEP;
            $delta >= 0 ? $earned += $delta : $lost += abs($delta);
            if ($delta > 0) $earnedBreakdown['Completion ratings'] = ($earnedBreakdown['Completion ratings'] ?? 0) + $delta;
            if ($delta < 0) $lostBreakdown['Low completion ratings'] = ($lostBreakdown['Low completion ratings'] ?? 0) + abs($delta);
        }
        $ratingAverage = count($ratings) > 0 ? $ratingTotal / count($ratings) : 0.0;

        foreach ($activities as $activity) {
            $delta = (float) ($activity['score_delta'] ?? 0);
            $delta >= 0 ? $earned += $delta : $lost += abs($delta);
            $type = (string) ($activity['type'] ?? 'Other activity');
            if ($delta > 0) $earnedBreakdown[$type] = ($earnedBreakdown[$type] ?? 0) + $delta;
            if ($delta < 0) $lostBreakdown[$type] = ($lostBreakdown[$type] ?? 0) + abs($delta);
        }

        $activityCounts = array_count_values(array_column($activities, 'type'));
        $approvedFollowups = count(array_filter($activities, static fn(array $row): bool =>
            (string) ($row['type'] ?? '') === 'Approved follow-up'
            && (float) ($row['score_delta'] ?? 0) > 0
        ));
        $noPointFollowups = count(array_filter($activities, static fn(array $row): bool =>
            (string) ($row['type'] ?? '') === 'Approved follow-up'
            && (float) ($row['score_delta'] ?? 0) === 0.0
        ));
        $disapprovedFollowups = (int) ($activityCounts['Disapproved follow-up'] ?? 0);
        $duplicateFollowups = $missingImageFollowups = $meaningfulUpdates = 0;
        $followupSubmissions = $approvedFollowups + $noPointFollowups + $disapprovedFollowups;
        $evidenceComplianceRate = $uniqueUpdateRate = 0.0;
        $completedActivityTypes = ['Order Entry', 'Approved follow-up', 'Disapproved follow-up', 'Issuement', 'Return', 'Receiving', 'Purchase'];
        $completed = $taskOnTime + $taskLate + count(array_filter($activities, static fn(array $row): bool => in_array((string) ($row['type'] ?? ''), $completedActivityTypes, true)));
        $onTime = $taskOnTime + $followupOnTime;
        $overdue = $taskOverdue + $followupOverdue;
        $dueActions = $taskOnTime + $taskLate + $taskOverdue + $followupOnTime + $followupLate + $followupOverdue;
        $score = max(0, round(self::BASE_SCORE + $earned - $lost, 1));
        $target = $this->targetProgress($score);
        $hasActivity = $dueActions > 0 || count($activities) > 0 || count($ratings) > 0;
        $needsAttention = $disapprovedFollowups > 0 || $overdue > 0 || $score < 90 || (count($ratings) > 0 && $ratingAverage < 3);
        [$performanceBand, $analysisSummary] = $this->performanceAssessment(
            $score,
            $hasActivity,
            $disapprovedFollowups,
            $overdue
        );
        return [
            'score' => $score,
            'points_earned' => round($earned, 1),
            'points_lost' => round($lost, 1),
            'task_on_time' => $taskOnTime,
            'task_late' => $taskLate,
            'task_overdue' => $taskOverdue,
            'task_delay_days' => $taskDelayDays,
            'followup_on_time' => $followupOnTime,
            'followup_late' => $followupLate,
            'followup_overdue' => $followupOverdue,
            'followup_delay_days' => $followupDelayDays,
            'followup_approval_credit_days' => 0,
            'followup_approval_credit_points' => 0.0,
            'rating_count' => count($ratings),
            'rating_average' => round($ratingAverage, 1),
            'order_entries' => (int) ($activityCounts['Order Entry'] ?? 0),
            'proper_followups' => $approvedFollowups,
            'approved_followups' => $approvedFollowups,
            'no_point_followups' => $noPointFollowups,
            'disapproved_followups' => $disapprovedFollowups,
            'followup_submission_count' => $followupSubmissions,
            'meaningful_updates' => $meaningfulUpdates,
            'duplicate_followups' => $duplicateFollowups,
            'missing_image_followups' => $missingImageFollowups,
            'evidence_compliance_rate' => $evidenceComplianceRate,
            'unique_update_rate' => $uniqueUpdateRate,
            'issuements' => (int) ($activityCounts['Issuement'] ?? 0),
            'returns' => (int) ($activityCounts['Return'] ?? 0),
            'receivings' => (int) ($activityCounts['Receiving'] ?? 0),
            'purchases' => (int) ($activityCounts['Purchase'] ?? 0),
            'completed_actions' => $completed,
            'on_time_actions' => $onTime,
            'overdue_actions' => $overdue,
            'due_action_count' => $dueActions,
            'on_time_rate' => $dueActions > 0 ? round(($onTime / $dueActions) * 100, 1) : 0,
            'performance_band' => $performanceBand,
            'analysis_summary' => $analysisSummary,
            'needs_attention' => $needsAttention,
            'monthly_target' => $target['monthly_target'],
            'target_progress' => $target['target_progress'],
            'points_to_target' => $target['points_to_target'],
            'target_achieved' => $target['target_achieved'],
            'reward_points' => $target['reward_points'],
            'has_scored_activity' => $hasActivity,
            'point_earned_breakdown' => array_map(static fn(float $points): float => round($points, 2), $earnedBreakdown),
            'point_loss_breakdown' => array_map(static fn(float $points): float => round($points, 2), $lostBreakdown),
        ];
    }

    /**
     * Monthly target of MONTHLY_TARGET_POINTS; every point above it earns
     * REWARD_POINTS_PER_EXTRA_POINT reward points.
     *
     * @return array{monthly_target:float,target_progress:float,points_to_target:float,target_achieved:bool,reward_points:float}
     */
    public function targetProgress(float $score): array
    {
        $target = self::MONTHLY_TARGET_POINTS;

        return [
            'monthly_target' => $target,
            'target_progress' => $target > 0 ? round(min(100, max(0, $score / $target * 100)), 1) : 0.0,
            'points_to_target' => round(max(0, $target - $score), 1),
            'target_achieved' => $score >= $target,
            'reward_points' => round(max(0, $score - $target) * self::REWARD_POINTS_PER_EXTRA_POINT, 1),
        ];
    }

    /** @return array{0:string,1:string} */
    private function performanceAssessment(float $score, bool $hasActivity, int $disapproved, int $overdue): array
    {
        if (! $hasActivity) return ['No activity', 'No scored activity was recorded for this period.'];
        if ($disapproved > 0) return ['Approval rejected', $disapproved . ' follow-up' . ($disapproved === 1 ? ' was' : 's were') . ' disapproved and reduced the score.'];
        if ($overdue > 0) return ['Schedule risk', $overdue . ' assigned action' . ($overdue === 1 ? ' is' : 's are') . ' overdue.'];
        if ($score >= 105) return ['Excellent', 'High contribution with no active scoring exception.'];
        if ($score >= 100) return ['Strong', 'Performance is at or above baseline with no active scoring exception.'];
        if ($score >= 90) return ['Needs attention', 'Performance is below baseline; review deductions in the point audit.'];
        return ['At risk', 'Material deductions require manager review and a corrective action plan.'];
    }

    private function events(
        array $tasks,
        array $followups,
        array $ratings,
        array $activities,
        string $periodStart,
        string $periodEnd
    ): array
    {
        $events = [];
        $now = $this->now();
        foreach ($tasks as $task) {
            $status = strtolower((string) ($task['status'] ?? 'pending'));
            $done = (int) ($task['is_done'] ?? 0) === 1 || str_starts_with($status, 'completed');
            $overdue = ! $done && (string) ($task['scheduled_at'] ?? '') < $now;
            $completedAt = (string) ($task['completed_at'] ?? '');
            $completedByOther = $done
                && (int) ($task['completed_by'] ?? 0) > 0
                && (int) ($task['admin_user_id'] ?? 0) > 0
                && (int) $task['completed_by'] !== (int) $task['admin_user_id'];
            $taskEnd = $done ? $completedAt : $now;
            $lateDays = ! $completedByOther && ($done || $overdue)
                ? $this->delayUnitsInPeriod((string) ($task['scheduled_at'] ?? ''), $taskEnd, $periodStart, $periodEnd)
                : 0;
            $onTimeCompletedInPeriod = ! $completedByOther
                && $done
                && self::lateDayUnits((string) ($task['scheduled_at'] ?? ''), $completedAt) === 0
                && $completedAt >= $periodStart
                && $completedAt < $periodEnd;
            $delta = $onTimeCompletedInPeriod
                ? self::TASK_ON_TIME_POINTS
                : $lateDays * self::TASK_LATE_POINTS;
            $events[] = [
                'type' => 'Task',
                'title' => (string) ($task['title'] ?? 'Task'),
                'reference' => 'TASK-' . (int) ($task['id'] ?? 0),
                'due_at' => (string) ($task['scheduled_at'] ?? ''),
                'completed_at' => (string) ($task['completed_at'] ?? ''),
                'status' => $overdue ? 'overdue' : $status,
                'score_delta' => $delta,
                'proof_url' => ! empty($task['proof_path']) ? base_url(ltrim((string) $task['proof_path'], '/')) : null,
            ];
        }
        foreach ($followups as $followup) {
            $status = strtolower((string) ($followup['status'] ?? 'pending'));
            $completedApprovalStatus = strtolower((string) ($followup['completed_followup_approval_status'] ?? 'approved'));
            $evaluatedAt = (string) ($followup['performance_until_at'] ?? $now);
            $overdue = $status === 'pending' && (string) ($followup['due_at'] ?? '') < $evaluatedAt;
            $delay = $this->followupDelayBreakdown($followup, $periodStart, $periodEnd, $now);
            $awaitingOrRejected = str_starts_with($status, 'completed')
                && in_array($completedApprovalStatus, ['pending', 'rejected'], true);
            $delta = $delay['penalty_days'] * self::FOLLOWUP_LATE_POINTS;
            $events[] = [
                'type' => 'Follow-up',
                'title' => 'Order ' . (string) (($followup['order_no'] ?? '') ?: ('#' . ($followup['order_id'] ?? ''))),
                'reference' => (string) ($followup['order_no'] ?? ''),
                'due_at' => (string) ($followup['due_at'] ?? ''),
                'completed_at' => (string) (($followup['completed_at'] ?? '') ?: ($followup['performance_until_at'] ?? '')),
                'status' => $awaitingOrRejected ? 'approval_' . $completedApprovalStatus : ($overdue ? 'overdue' : $status),
                'score_delta' => $delta,
                'order_id' => (int) ($followup['order_id'] ?? 0),
                'proof_url' => null,
            ];
        }
        foreach ($ratings as $rating) {
            $value = max(1, min(5, (int) ($rating['completion_rating'] ?? 0)));
            $events[] = [
                'type' => 'Rating',
                'title' => 'Order ' . (string) (($rating['order_no'] ?? '') ?: ('#' . ($rating['id'] ?? ''))),
                'reference' => $value . '/5 completion rating',
                'due_at' => (string) ($rating['rated_at'] ?? ''),
                'completed_at' => (string) ($rating['rated_at'] ?? ''),
                'status' => 'rated',
                'score_delta' => ($value - 3) * self::RATING_POINT_STEP,
                'order_id' => (int) ($rating['id'] ?? 0),
                'proof_url' => null,
                'rating' => $value,
                'comment' => (string) ($rating['rating_comment'] ?? ''),
            ];
        }
        foreach ($activities as $activity) {
            $events[] = [
                'type' => (string) $activity['type'],
                'title' => (string) $activity['title'],
                'reference' => (string) $activity['reference'],
                'due_at' => (string) $activity['occurred_at'],
                'completed_at' => (string) $activity['occurred_at'],
                'status' => 'completed',
                'score_delta' => (float) $activity['score_delta'],
                'order_id' => (int) ($activity['order_id'] ?? 0),
                'proof_url' => $activity['proof_url'] ?? null,
                'followup_id' => (int) ($activity['followup_id'] ?? 0),
                'matched_followup_id' => (int) ($activity['matched_followup_id'] ?? 0),
            ];
        }
        usort($events, static fn(array $a, array $b): int => strcmp((string) $b['due_at'], (string) $a['due_at']));
        return $events;
    }

    /** @param array<string,mixed> $followup */
    private function followupDelayBreakdown(
        array $followup,
        string $periodStart,
        string $periodEnd,
        string $now
    ): array
    {
        $status = strtolower((string) ($followup['status'] ?? 'pending'));
        if ($status === 'completed_by_other') {
            return ['penalty_days' => 0, 'approval_credit_days' => 0];
        }
        $endAt = str_starts_with($status, 'completed')
            ? (string) ($followup['completed_at'] ?? '')
            : (string) (($followup['performance_until_at'] ?? '') ?: $now);
        $dueAt = (string) ($followup['due_at'] ?? '');
        $createdAt = (string) ($followup['created_at'] ?? '');
        if ($createdAt !== '' && strtotime($createdAt) !== false && strtotime($dueAt) !== false
            && strtotime($createdAt) > strtotime($dueAt)) {
            $dueAt = $createdAt;
        }

        return [
            'penalty_days' => $this->delayUnitsInPeriod($dueAt, $endAt, $periodStart, $periodEnd),
            'approval_credit_days' => 0,
        ];
    }

    private function delayUnitsInPeriod(string $dueAt, string $evaluatedAt, string $periodStart, string $periodEnd): int
    {
        $due = strtotime($dueAt);
        $evaluated = strtotime($evaluatedAt);
        $start = strtotime($periodStart);
        $end = strtotime($periodEnd);
        if ($due === false || $evaluated === false || $start === false || $end === false || $evaluated <= $due) {
            return 0;
        }

        $limit = min($evaluated, $end);
        if ($limit <= $due || $limit <= $start) {
            return 0;
        }

        $unitsBeforePeriod = $start <= $due ? 0 : (int) ceil(($start - $due) / 86400);
        $unitsThroughLimit = (int) ceil(($limit - $due) / 86400);
        return max(0, $unitsThroughLimit - $unitsBeforePeriod);
    }

    private function monthRange(int $year, int $month): array
    {
        $tz = new DateTimeZone(self::TIMEZONE);
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
        return [$start->format('Y-m-d H:i:s'), $start->modify('+1 month')->format('Y-m-d H:i:s')];
    }

    private function normalizeDateTime(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($raw, new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s');
    }
}
