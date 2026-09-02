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
    public const FOLLOWUP_ON_TIME_POINTS = 1.0;
    public const FOLLOWUP_LATE_POINTS = -1.0;

    private const TIMEZONE = 'Asia/Kolkata';
    private const ADMIN_ROLES = ['SUPER_ADMIN', 'ADMIN', 'OWNER'];

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
        } else {
            (new OrderFollowupScheduleModel())->insert([
                'order_id' => $orderId,
                'assigned_to' => $assignedTo,
                'due_at' => $normalizedDueAt,
                'status' => 'pending',
                'created_by' => $createdBy,
            ]);
        }

        $db->table('orders')->where('id', $orderId)->update([
            'followup_assigned_to' => $assignedTo,
            'followup_due_at' => $normalizedDueAt,
            'updated_at' => $now,
        ]);
    }

    public function completeOrderFollowup(int $orderId, int $followupId, int $completedBy, ?string $nextDueAt): void
    {
        $db = db_connect();
        if (! $db->tableExists('order_followup_schedules')) {
            return;
        }

        $now = $this->now();
        $schedule = $db->table('order_followup_schedules')
            ->where('order_id', $orderId)
            ->where('status', 'pending')
            ->orderBy('due_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()->getRowArray();
        if ($schedule) {
            $onTime = $now <= (string) $schedule['due_at'];
            $completedByAssignee = $completedBy === (int) $schedule['assigned_to'];
            $status = $completedByAssignee
                ? ($onTime ? 'completed_on_time' : 'completed_late')
                : 'completed_by_other';
            $scoreDelta = $completedByAssignee && $onTime
                ? self::FOLLOWUP_ON_TIME_POINTS
                : self::FOLLOWUP_LATE_POINTS;
            $db->table('order_followup_schedules')->where('id', (int) $schedule['id'])->update([
                'status' => $status,
                'completed_followup_id' => $followupId,
                'completed_by' => $completedBy,
                'completed_at' => $now,
                'score_delta' => $scoreDelta,
                'updated_at' => $now,
            ]);
        }

        $order = $db->table('orders')->select('followup_assigned_to, status')->where('id', $orderId)->get()->getRowArray();
        $assignedTo = (int) ($order['followup_assigned_to'] ?? 0);
        $terminal = in_array((string) ($order['status'] ?? ''), ['Ready', 'Packed', 'Dispatched', 'Completed', 'Cancelled'], true);
        $normalizedNextDue = $terminal ? null : $this->normalizeDateTime($nextDueAt);
        if ($assignedTo > 0 && $normalizedNextDue !== null) {
            (new OrderFollowupScheduleModel())->insert([
                'order_id' => $orderId,
                'assigned_to' => $assignedTo,
                'due_at' => $normalizedNextDue,
                'status' => 'pending',
                'created_by' => $completedBy,
            ]);
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

        $rows = [];
        $eventsByUser = [];
        foreach ($staff as $person) {
            $id = (int) $person['id'];
            $personTasks = array_values(array_filter($tasks, static fn(array $row): bool => (int) $row['admin_user_id'] === $id));
            $personFollowups = array_values(array_filter($followups, static fn(array $row): bool => (int) $row['assigned_to'] === $id));
            $metrics = $this->metrics($personTasks, $personFollowups);
            $rows[] = $person + $metrics;
            $eventsByUser[$id] = $this->events($personTasks, $personFollowups);
        }

        usort($rows, static fn(array $a, array $b): int => [$b['score'], $b['on_time_rate']] <=> [$a['score'], $a['on_time_rate']]);
        $completed = array_sum(array_column($rows, 'completed_actions'));
        $onTime = array_sum(array_column($rows, 'on_time_actions'));
        $overdue = array_sum(array_column($rows, 'overdue_actions'));
        $dueActions = $completed + $overdue;
        $scoreTotal = array_sum(array_column($rows, 'score'));
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
                'staff_count' => count($rows),
                'average_score' => count($rows) > 0 ? round($scoreTotal / count($rows), 1) : 0,
                'completed_actions' => $completed,
                'on_time_rate' => $dueActions > 0 ? round(($onTime / $dueActions) * 100, 1) : 0,
                'overdue_actions' => $overdue,
                'unassigned_open_orders' => $unassignedOpenOrders,
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
            'on_time_rate' => 0,
            'task_on_time' => 0,
            'task_late' => 0,
            'task_overdue' => 0,
            'followup_on_time' => 0,
            'followup_late' => 0,
            'followup_overdue' => 0,
        ];
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
            'followup_on_time' => self::FOLLOWUP_ON_TIME_POINTS,
            'followup_late_or_overdue' => self::FOLLOWUP_LATE_POINTS,
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
            ->where('t.scheduled_at >=', $start)
            ->where('t.scheduled_at <', $end)
            ->where('t.status !=', 'cancelled')
            ->orderBy('t.scheduled_at', 'DESC')
            ->get()->getResultArray();
    }

    private function followupRows(array $staffIds, string $start, string $end): array
    {
        $db = db_connect();
        if ($staffIds === [] || ! $db->tableExists('order_followup_schedules')) {
            return [];
        }
        return $db->table('order_followup_schedules s')
            ->select('s.*, o.order_no, au.name as completed_by_name')
            ->join('orders o', 'o.id = s.order_id', 'left')
            ->join('admin_users au', 'au.id = s.completed_by', 'left')
            ->whereIn('s.assigned_to', $staffIds)
            ->where('s.due_at >=', $start)
            ->where('s.due_at <', $end)
            ->where('s.status !=', 'cancelled')
            ->orderBy('s.due_at', 'DESC')
            ->get()->getResultArray();
    }

    private function metrics(array $tasks, array $followups): array
    {
        $now = $this->now();
        $taskOnTime = $taskLate = $taskOverdue = 0;
        $followupOnTime = $followupLate = $followupOverdue = 0;
        $earned = $lost = 0.0;

        foreach ($tasks as $task) {
            $status = strtolower((string) ($task['status'] ?? 'pending'));
            $done = (int) ($task['is_done'] ?? 0) === 1 || str_starts_with($status, 'completed');
            if ($done) {
                $onTime = $status === 'completed_on_time' || (float) ($task['score_delta'] ?? 0) > 0;
                $delta = $onTime ? self::TASK_ON_TIME_POINTS : self::TASK_LATE_POINTS;
                $onTime ? $taskOnTime++ : $taskLate++;
            } elseif ((string) ($task['scheduled_at'] ?? '') < $now) {
                $delta = self::TASK_LATE_POINTS;
                $taskOverdue++;
            } else {
                $delta = 0.0;
            }
            $delta >= 0 ? $earned += $delta : $lost += abs($delta);
        }

        foreach ($followups as $followup) {
            $status = strtolower((string) ($followup['status'] ?? 'pending'));
            if (str_starts_with($status, 'completed')) {
                $onTime = $status === 'completed_on_time' || (float) ($followup['score_delta'] ?? 0) > 0;
                $delta = $onTime ? self::FOLLOWUP_ON_TIME_POINTS : self::FOLLOWUP_LATE_POINTS;
                $onTime ? $followupOnTime++ : $followupLate++;
            } elseif ((string) ($followup['due_at'] ?? '') < $now) {
                $delta = self::FOLLOWUP_LATE_POINTS;
                $followupOverdue++;
            } else {
                $delta = 0.0;
            }
            $delta >= 0 ? $earned += $delta : $lost += abs($delta);
        }

        $completed = $taskOnTime + $taskLate + $followupOnTime + $followupLate;
        $onTime = $taskOnTime + $followupOnTime;
        $overdue = $taskOverdue + $followupOverdue;
        $dueActions = $completed + $overdue;
        return [
            'score' => max(0, round(self::BASE_SCORE + $earned - $lost, 1)),
            'points_earned' => round($earned, 1),
            'points_lost' => round($lost, 1),
            'task_on_time' => $taskOnTime,
            'task_late' => $taskLate,
            'task_overdue' => $taskOverdue,
            'followup_on_time' => $followupOnTime,
            'followup_late' => $followupLate,
            'followup_overdue' => $followupOverdue,
            'completed_actions' => $completed,
            'on_time_actions' => $onTime,
            'overdue_actions' => $overdue,
            'on_time_rate' => $dueActions > 0 ? round(($onTime / $dueActions) * 100, 1) : 0,
        ];
    }

    private function events(array $tasks, array $followups): array
    {
        $events = [];
        $now = $this->now();
        foreach ($tasks as $task) {
            $status = strtolower((string) ($task['status'] ?? 'pending'));
            $done = (int) ($task['is_done'] ?? 0) === 1 || str_starts_with($status, 'completed');
            $overdue = ! $done && (string) ($task['scheduled_at'] ?? '') < $now;
            $delta = $done
                ? ($status === 'completed_on_time' || (float) ($task['score_delta'] ?? 0) > 0 ? self::TASK_ON_TIME_POINTS : self::TASK_LATE_POINTS)
                : ($overdue ? self::TASK_LATE_POINTS : 0.0);
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
            $overdue = $status === 'pending' && (string) ($followup['due_at'] ?? '') < $now;
            $delta = str_starts_with($status, 'completed')
                ? ($status === 'completed_on_time' ? self::FOLLOWUP_ON_TIME_POINTS : self::FOLLOWUP_LATE_POINTS)
                : ($overdue ? self::FOLLOWUP_LATE_POINTS : 0.0);
            $events[] = [
                'type' => 'Follow-up',
                'title' => 'Order ' . (string) (($followup['order_no'] ?? '') ?: ('#' . ($followup['order_id'] ?? ''))),
                'reference' => (string) ($followup['order_no'] ?? ''),
                'due_at' => (string) ($followup['due_at'] ?? ''),
                'completed_at' => (string) ($followup['completed_at'] ?? ''),
                'status' => $overdue ? 'overdue' : $status,
                'score_delta' => $delta,
                'order_id' => (int) ($followup['order_id'] ?? 0),
                'proof_url' => null,
            ];
        }
        usort($events, static fn(array $a, array $b): int => strcmp((string) $b['due_at'], (string) $a['due_at']));
        return $events;
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
