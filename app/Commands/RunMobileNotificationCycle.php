<?php

namespace App\Commands;

use App\Services\MobileNotificationEventService;
use App\Services\MobilePushService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

class RunMobileNotificationCycle extends BaseCommand
{
    protected $group = 'Notifications';
    protected $name = 'mobile:run-notification-cycle';
    protected $description = 'Queue due/overdue mobile alerts and dispatch due OneSignal push notifications.';

    public function run(array $params)
    {
        $limit = max(1, (int) ($params[0] ?? 200));
        $lockPath = WRITEPATH . 'mobile-push-cycle.lock';
        $lock = fopen($lockPath, 'c');

        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            CLI::write('Another mobile notification cycle is already running.', 'yellow');
            return $lock === false ? EXIT_ERROR : EXIT_SUCCESS;
        }

        try {
            $errors = [];
            $hourly = [
                'working_hour' => false,
                'orders_scanned' => 0,
                'notifications_queued' => 0,
            ];
            $dispatch = [
                'scanned' => 0,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
            ];

            try {
                $events = new MobileNotificationEventService();
                $hourly = $events->queueHourlyDelayedFollowups();
            } catch (Throwable $e) {
                $errors[] = 'Delayed followup generation failed: ' . $e->getMessage();
            }

            try {
                $push = new MobilePushService();
                $dispatch = $push->dispatchPendingNotifications($limit);
            } catch (Throwable $e) {
                $errors[] = 'Push dispatch failed: ' . $e->getMessage();
            }

            $summary = sprintf(
                'Mobile notification cycle complete. working_hour=%s overdue_orders=%d overdue_pushes=%d scanned=%d sent=%d failed=%d skipped=%d',
                ($hourly['working_hour'] ?? false) ? 'yes' : 'no',
                (int) ($hourly['orders_scanned'] ?? 0),
                (int) ($hourly['notifications_queued'] ?? 0),
                (int) ($dispatch['scanned'] ?? 0),
                (int) ($dispatch['sent'] ?? 0),
                (int) ($dispatch['failed'] ?? 0),
                (int) ($dispatch['skipped'] ?? 0)
            );
            CLI::write($summary, $errors === [] ? 'green' : 'yellow');
            foreach ($errors as $error) {
                CLI::error($error);
            }

            file_put_contents(
                WRITEPATH . 'mobile-push-cycle-heartbeat.json',
                json_encode([
                    'success' => $errors === [],
                    'completed_at' => date('c'),
                    'errors' => $errors,
                    'hourly' => $hourly,
                    'dispatch' => $dispatch,
                ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            );

            return $errors === [] ? EXIT_SUCCESS : EXIT_ERROR;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
