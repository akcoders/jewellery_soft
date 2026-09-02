<?php

namespace App\Controllers\Api\Mobile;

use App\Models\MobileTaskModel;
use App\Services\MobilePushService;
use App\Services\StaffPerformanceService;
use Throwable;

class TasksController extends MobileBaseController
{
    private MobileTaskModel $taskModel;
    private MobilePushService $pushService;
    private StaffPerformanceService $performanceService;

    public function __construct()
    {
        $this->taskModel = new MobileTaskModel();
        $this->pushService = new MobilePushService();
        $this->performanceService = new StaffPerformanceService();
    }

    public function index()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }
        if (! $this->performanceService->isStaffUser((int) ($this->mobileAdmin['id'] ?? 0))) {
            return $this->fail('Assigned tasks are available only for non-admin staff.', 403);
        }

        $query = trim((string) $this->request->getGet('q'));
        $builder = db_connect()->table('mobile_tasks t')
            ->select('t.*, creator.name as assigned_by_name')
            ->join('admin_users creator', 'creator.id = t.created_by', 'left')
            ->where('t.admin_user_id', (int) ($this->mobileAdmin['id'] ?? 0))
            ->where('t.counts_for_performance', 1)
            ->where('t.status !=', 'cancelled')
            ->groupStart()
                ->where('t.is_done', 0)
                ->orWhere('t.completed_at >=', date('Y-m-d H:i:s', strtotime('-30 days')))
            ->groupEnd();
        if ($query !== '') {
            $builder->groupStart()->like('t.title', $query)->orLike('t.note', $query)->groupEnd();
        }
        $rows = $builder
            ->orderBy('t.is_done', 'ASC')
            ->orderBy('t.scheduled_at', 'ASC')
            ->get()->getResultArray();

        $now = date('Y-m-d H:i:s');
        foreach ($rows as &$row) {
            $row['is_overdue'] = (int) ($row['is_done'] ?? 0) === 0 && (string) ($row['scheduled_at'] ?? '') < $now;
            $row['proof_url'] = ! empty($row['proof_path']) ? base_url(ltrim((string) $row['proof_path'], '/')) : null;
        }
        unset($row);

        return $this->ok($rows);
    }

    public function complete(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }
        if (! $this->performanceService->isStaffUser((int) ($this->mobileAdmin['id'] ?? 0))) {
            return $this->fail('Assigned tasks are available only for non-admin staff.', 403);
        }

        $task = $this->taskModel
            ->where('id', $id)
            ->where('admin_user_id', (int) ($this->mobileAdmin['id'] ?? 0))
            ->where('counts_for_performance', 1)
            ->first();
        if (! is_array($task)) {
            return $this->fail('Assigned task not found.', 404);
        }
        if ((int) ($task['is_done'] ?? 0) === 1 || in_array((string) ($task['status'] ?? ''), ['cancelled', 'completed_on_time', 'completed_late'], true)) {
            return $this->fail('Task is already closed.', 422);
        }

        $payload = $this->payload();
        $proofBase64 = trim((string) ($payload['proof_base64'] ?? ''));
        $proofNote = trim((string) ($payload['proof_note'] ?? ''));
        if ($proofBase64 === '') {
            return $this->fail('Completion proof image is required.', 422);
        }

        $saved = $this->saveProof($proofBase64);
        if (! ($saved['ok'] ?? false)) {
            return $this->fail((string) ($saved['message'] ?? 'Invalid proof image.'), 422);
        }

        $completedAt = date('Y-m-d H:i:s');
        $onTime = $completedAt <= (string) ($task['scheduled_at'] ?? '');
        try {
            db_connect()->transException(true)->transStart();
            $this->taskModel->update($id, [
                'is_done' => 1,
                'status' => $onTime ? 'completed_on_time' : 'completed_late',
                'completed_at' => $completedAt,
                'completed_by' => (int) ($this->mobileAdmin['id'] ?? 0),
                'proof_name' => $saved['name'],
                'proof_path' => $saved['path'],
                'proof_note' => $proofNote !== '' ? $proofNote : null,
                'score_delta' => $onTime ? StaffPerformanceService::TASK_ON_TIME_POINTS : StaffPerformanceService::TASK_LATE_POINTS,
            ]);
            $this->pushService->cancelByReference('mobile_tasks', $id);
            db_connect()->transComplete();
        } catch (Throwable $e) {
            db_connect()->transRollback();
            $absolutePath = FCPATH . ltrim((string) $saved['path'], '/');
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
            return $this->fail('Could not complete task: ' . $e->getMessage(), 500);
        }

        return $this->ok([
            'task' => $this->taskModel->find($id),
            'points' => $onTime ? StaffPerformanceService::TASK_ON_TIME_POINTS : StaffPerformanceService::TASK_LATE_POINTS,
        ], $onTime ? 'Task completed on time. +2 points.' : 'Task completed after its due time. -2 points.');
    }

    private function saveProof(string $encoded): array
    {
        $mime = '';
        if (preg_match('/^data:(image\/(?:jpeg|png|webp));base64,(.+)$/s', $encoded, $matches)) {
            $mime = strtolower((string) $matches[1]);
            $encoded = (string) $matches[2];
        }
        $binary = base64_decode(preg_replace('/\s+/', '', $encoded) ?? '', true);
        if ($binary === false || strlen($binary) < 32 || strlen($binary) > 8 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'Proof must be a JPG, PNG or WebP image up to 8 MB.'];
        }
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $mime !== '' ? $mime : (string) $detected;
        if (! isset($extensions[$mime]) || ! isset($extensions[(string) $detected])) {
            return ['ok' => false, 'message' => 'Only JPG, PNG and WebP proof images are allowed.'];
        }

        $directory = FCPATH . 'uploads/tasks/proofs';
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return ['ok' => false, 'message' => 'Proof upload directory is not writable.'];
        }
        $name = 'task-proof-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[(string) $detected];
        if (file_put_contents($directory . DIRECTORY_SEPARATOR . $name, $binary, LOCK_EX) === false) {
            return ['ok' => false, 'message' => 'Could not store proof image.'];
        }
        return ['ok' => true, 'name' => $name, 'path' => 'uploads/tasks/proofs/' . $name];
    }
}
