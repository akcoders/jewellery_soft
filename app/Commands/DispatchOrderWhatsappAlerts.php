<?php

namespace App\Commands;

use App\Services\OrderWhatsAppService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DispatchOrderWhatsappAlerts extends BaseCommand
{
    protected $group = 'Notifications';
    protected $name = 'orders:dispatch-whatsapp-alerts';
    protected $description = 'Dispatch daily delayed-order WhatsApp alerts.';

    public function run(array $params)
    {
        $forDate = trim((string) ($params[0] ?? date('Y-m-d')));
        $service = new OrderWhatsAppService();
        $result = $service->dispatchDailyDelayAlerts($forDate);

        CLI::write(
            sprintf(
                'Order WhatsApp alerts complete. scanned=%d sent=%d failed=%d skipped=%d',
                (int) ($result['scanned'] ?? 0),
                (int) ($result['sent'] ?? 0),
                (int) ($result['failed'] ?? 0),
                (int) ($result['skipped'] ?? 0)
            ),
            'green'
        );
    }
}
