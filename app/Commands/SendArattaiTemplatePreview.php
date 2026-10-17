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
    protected $description = 'Send all ERP Arattai conversational workflow previews to one Arattai user ID.';
    protected $usage = 'arattai:send-template-preview <arattai-user-id>';
    protected $arguments = ['arattai-user-id' => 'Arattai user ID captured from an incoming chat message.'];

    public function run(array $params)
    {
        $userId = trim((string) ($params[0] ?? ''));
        if ($userId === '') {
            CLI::error('Usage: php spark arattai:send-template-preview <arattai-user-id>');
            return EXIT_ERROR;
        }
        try {
            $result = (new AttraiIntegrationService())->sendTemplatePreview($userId);
            CLI::write((string) ($result['message'] ?? 'Preview completed.'), ($result['status'] ?? '') === 'sent' ? 'green' : 'yellow');
            return ($result['status'] ?? '') === 'sent' ? EXIT_SUCCESS : EXIT_ERROR;
        } catch (Throwable $e) {
            CLI::error($e->getMessage());
            return EXIT_ERROR;
        }
    }
}
