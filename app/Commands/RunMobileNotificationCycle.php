<?php

namespace App\Commands;

use App\Services\MobileNotificationEventService;
use App\Services\MobilePushService;
use App\Services\AttraiIntegrationService;
use App\Services\WhatsappSenderQueueService;
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
            $arattai = [
                'followups' => ['scanned' => 0, 'queued' => 0],
                'tasks' => ['scanned' => 0, 'queued' => 0],
                'dispatch' => ['scanned' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0],
            ];

            try {
                $events = new MobileNotificationEventService();
                $hourly = $events->queueHourlyDelayedFollowups();
            } catch (Throwable $e) {
                $errors[] = 'Delayed followup generation failed: ' . $e->getMessage();
            }

            try {
                $arattaiService = new AttraiIntegrationService();
                $arattai['followups'] = $arattaiService->queueFollowupReminders();
                $arattai['tasks'] = $arattaiService->queueOverdueTaskPointAlerts();
                $arattai['dispatch'] = (new WhatsappSenderQueueService())->dispatchPending($limit);
            } catch (Throwable $e) {
                $errors[] = 'Arattai notification cycle failed: ' . $e->getMessage();
            }

            try {
                $push = new MobilePushService();
                $dispatch = $push->dispatchPendingNotifications($limit);
            } catch (Throwable $e) {
                $errors[] = 'Push dispatch failed: ' . $e->getMessage();
            }

            $summary = sprintf(
                'Mobile notification cycle complete. working_hour=%s overdue_orders=%d overdue_pushes=%d push_scanned=%d push_sent=%d arattai_queued=%d arattai_sent=%d arattai_failed=%d',
                ($hourly['working_hour'] ?? false) ? 'yes' : 'no',
                (int) ($hourly['orders_scanned'] ?? 0),
                (int) ($hourly['notifications_queued'] ?? 0),
                (int) ($dispatch['scanned'] ?? 0),
                (int) ($dispatch['sent'] ?? 0),
                (int) (($arattai['followups']['queued'] ?? 0) + ($arattai['tasks']['queued'] ?? 0)),
                (int) ($arattai['dispatch']['sent'] ?? 0),
                (int) ($arattai['dispatch']['failed'] ?? 0)
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
                    'arattai' => $arattai,
                ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            );

            return $errors === [] ? EXIT_SUCCESS : EXIT_ERROR;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
