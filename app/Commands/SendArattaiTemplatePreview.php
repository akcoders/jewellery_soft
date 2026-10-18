<?php

namespace App\Commands;

use App\Services\AttraiIntegrationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

class SendArattaiTemplatePreview extends BaseCommand
{
    protected $group = 'Notifications';
    protected $name = 'arattai:send-template-preview';
    protected $description = 'Send one ERP Arattai conversational workflow preview to one Arattai user ID.';
    protected $usage = 'arattai:send-template-preview <arattai-user-id> [scenario]';
    protected $arguments = [
        'arattai-user-id' => 'Arattai user ID captured from an incoming chat message.',
        'scenario' => 'order_status, followup_approval, issuement_approval, karigar_assignment, karigar_issuement, payment, followup_due or points.',
    ];

    public function run(array $params)
    {
        $userId = trim((string) ($params[0] ?? ''));
        if ($userId === '') {
            CLI::error('Usage: php spark arattai:send-template-preview <arattai-user-id> [scenario]');
            return EXIT_ERROR;
        }
        try {
            $result = (new AttraiIntegrationService())->sendTemplatePreview($userId, trim((string) ($params[1] ?? 'order_status')));
            CLI::write((string) ($result['message'] ?? 'Preview completed.'), ($result['status'] ?? '') === 'sent' ? 'green' : 'yellow');
            return ($result['status'] ?? '') === 'sent' ? EXIT_SUCCESS : EXIT_ERROR;
        } catch (Throwable $e) {
            CLI::error($e->getMessage());
            return EXIT_ERROR;
        }
    }
}
