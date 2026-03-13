<?php

namespace App\Commands;

use App\Services\MobilePushService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DispatchMobilePushNotifications extends BaseCommand
{
    protected $group = 'Notifications';
    protected $name = 'mobile:dispatch-push-notifications';
    protected $description = 'Dispatch pending mobile push notifications to OneSignal.';

    public function run(array $params)
    {
        $limit = (int) ($params[0] ?? 200);
        if ($limit <= 0) {
            $limit = 200;
        }

        $service = new MobilePushService();
        $result = $service->dispatchPendingNotifications($limit);

        CLI::write(
            sprintf(
                'Mobile push dispatch complete. scanned=%d sent=%d queued=%d failed=%d skipped=%d',
                (int) ($result['scanned'] ?? 0),
                (int) ($result['sent'] ?? 0),
                (int) ($result['queued'] ?? 0),
                (int) ($result['failed'] ?? 0),
                (int) ($result['skipped'] ?? 0)
            ),
            'green'
        );
    }
}
