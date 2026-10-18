<?php

namespace App\Commands;

use App\Services\AttraiIntegrationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

class SendPendingArattaiApprovals extends BaseCommand
{
    protected $group = 'Notifications';
    protected $name = 'arattai:send-pending-approvals';
    protected $description = 'Send current pending ERP approvals with details, images and action buttons to configured Arattai admins.';
    protected $usage = 'arattai:send-pending-approvals [limit] [--force]';
    protected $arguments = ['limit' => 'Maximum pending approvals to scan (default 25, max 100).'];
    protected $options = ['--force' => 'Queue another message even when this pending approval was already queued or sent.'];

    public function run(array $params)
    {
        $limit = max(1, min(100, (int) ($params[0] ?? 25)));
        $force = (bool) CLI::getOption('force');
        try {
            $result = (new AttraiIntegrationService())->sendPendingApprovalBacklog($limit, $force, 8);
            CLI::write(sprintf(
                'Pending approvals: scanned=%d skipped_duplicate=%d queued=%d sent_now=%d failed_now=%d. Remaining queued messages will be sent by the notification cron.',
                $result['scanned'], $result['skipped'], $result['queued'], $result['sent'], $result['failed']
            ), $result['failed'] > 0 ? 'yellow' : 'green');
            return $result['failed'] > 0 ? EXIT_ERROR : EXIT_SUCCESS;
        } catch (Throwable $e) {
            CLI::error($e->getMessage());
            return EXIT_ERROR;
        }
    }
}
