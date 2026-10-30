<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminUserModel;
use App\Services\McpApiKeyService;
use InvalidArgumentException;
use Throwable;

/**
 * /admin/mcp-keys – issue and revoke per-user API keys for the MCP server.
 */
class McpApiKeyController extends BaseController
{
    public function index(): string
    {
        $service = new McpApiKeyService();
        $service->ensureTable();

        $users = (new AdminUserModel())
            ->select('id, name, email')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();

        return view('admin/mcp_keys/index', [
            'title' => 'MCP API Keys',
            'keys' => $service->listKeys(),
            'users' => $users,
            'scopes' => McpApiKeyService::SCOPES,
            'expiryDays' => McpApiKeyService::EXPIRY_DAYS,
            'mcpUrl' => trim((string) env('mcp.publicUrl', '')),
        ]);
    }

    public function store()
    {
        try {
            $created = (new McpApiKeyService())->create((array) $this->request->getPost(), (int) session('admin_id'));

            return redirect()->to(site_url('admin/mcp-keys'))
                ->with('mcp_new_key', $created['plain_key'])
                ->with('success', 'API key "' . $created['row']['name'] . '" created. Copy it now; it will not be shown again.');
        } catch (InvalidArgumentException $e) {
            return redirect()->to(site_url('admin/mcp-keys'))->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'MCP API key create failed: {message}', ['message' => $e->getMessage()]);

            return redirect()->to(site_url('admin/mcp-keys'))->withInput()->with('error', 'Could not create the API key. Please try again.');
        }
    }

    public function revoke(int $id)
    {
        $revoked = (new McpApiKeyService())->revoke($id);

        return redirect()->to(site_url('admin/mcp-keys'))
            ->with($revoked ? 'success' : 'error', $revoked
                ? 'API key revoked. The MCP server stops accepting it within a minute.'
                : 'Key not found or already revoked.');
    }
}
