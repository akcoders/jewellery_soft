<?php

namespace App\Services;

use App\Models\AdminUserTourPreferenceModel;

class AdminApplicationTourService
{
    public const TOUR_KEY = 'admin-application-overview';
    public const TOUR_VERSION = '2026.08';

    /**
     * @return array{available:bool,shouldAutoStart:bool,dontShowAgain:bool,state:string,currentStepKey:?string,version:string}
     */
    public function status(int $adminUserId): array
    {
        $default = [
            'available' => false,
            'shouldAutoStart' => false,
            'dontShowAgain' => false,
            'state' => 'new',
            'currentStepKey' => null,
            'version' => self::TOUR_VERSION,
        ];

        if ($adminUserId <= 0 || ! db_connect()->tableExists('admin_user_tour_preferences')) {
            return $default;
        }

        $row = (new AdminUserTourPreferenceModel())
            ->where('admin_user_id', $adminUserId)
            ->where('tour_key', self::TOUR_KEY)
            ->first();

        if (! $row) {
            return array_merge($default, [
                'available' => true,
                'shouldAutoStart' => true,
            ]);
        }

        $dontShowAgain = (int) ($row['dont_show_again'] ?? 0) === 1;
        $completedCurrentVersion = (string) ($row['state'] ?? '') === 'completed'
            && (string) ($row['tour_version'] ?? '') === self::TOUR_VERSION;

        return [
            'available' => true,
            'shouldAutoStart' => ! $dontShowAgain && ! $completedCurrentVersion,
            'dontShowAgain' => $dontShowAgain,
            'state' => (string) ($row['state'] ?? 'new'),
            'currentStepKey' => ($row['current_step_key'] ?? null) !== null
                ? (string) $row['current_step_key']
                : null,
            'version' => self::TOUR_VERSION,
        ];
    }

    public function record(int $adminUserId, string $action, ?string $stepKey = null): bool
    {
        if ($adminUserId <= 0 || ! db_connect()->tableExists('admin_user_tour_preferences')) {
            return false;
        }

        $model = new AdminUserTourPreferenceModel();
        $existing = $model
            ->where('admin_user_id', $adminUserId)
            ->where('tour_key', self::TOUR_KEY)
            ->first();
        $now = date('Y-m-d H:i:s');
        $data = [
            'admin_user_id' => $adminUserId,
            'tour_key' => self::TOUR_KEY,
            'tour_version' => self::TOUR_VERSION,
        ];

        if ($action === 'started' || $action === 'progress') {
            $data['state'] = 'started';
            $data['current_step_key'] = $stepKey;
            if ($action === 'started' && empty($existing['started_at'])) {
                $data['started_at'] = $now;
            }
        } elseif ($action === 'completed') {
            $data['state'] = 'completed';
            $data['current_step_key'] = null;
            $data['completed_at'] = $now;
        } elseif ($action === 'dismissed') {
            $data['state'] = 'dismissed';
            $data['current_step_key'] = null;
            $data['dont_show_again'] = 1;
            $data['dismissed_at'] = $now;
        } else {
            return false;
        }

        if ($existing) {
            return $model->update((int) $existing['id'], $data);
        }

        return $model->insert($data) !== false;
    }
}
