<?php

namespace App\Commands;

use App\Services\WhatsappSenderQueueService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DispatchWhatsappSenderQueue extends BaseCommand
{
    protected $group = 'Notifications';
    protected $name = 'whatsapp:dispatch-sender-queue';
    protected $description = 'Dispatch pending WhatsApp sender queue messages.';

    public function run(array $params)
    {
        $limit = (int) ($params[0] ?? 100);
        if ($limit <= 0) {
            $limit = 100;
        }

        $service = new WhatsappSenderQueueService();
        $result = $service->dispatchPending($limit);

        CLI::write(
            sprintf(
                'WhatsApp sender queue complete. scanned=%d sent=%d failed=%d skipped=%d',
                (int) ($result['scanned'] ?? 0),
                (int) ($result['sent'] ?? 0),
                (int) ($result['failed'] ?? 0),
                (int) ($result['skipped'] ?? 0)
            ),
            'green'
        );
    }
}
