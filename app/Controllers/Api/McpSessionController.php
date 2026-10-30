<?php

namespace App\Controllers\Api;

use App\Models\AdminUserModel;
use App\Models\MobileApiTokenModel;

/**
 * Server-to-server endpoint used by the MCP server (mcp-server/) to obtain a
 * short-lived mobile API token for the ERP user an MCP API key is mapped to.
 * Protected by the shared secret `mcp.sharedSecret` from .env; nothing else in
 * the ERP changes, and the MCP server then uses the regular /api/mobile routes.
 */
class McpSessionController extends ApiBaseController
{
    private const SESSION_HOURS = 12;

    public function create()
    {
        $secret = trim((string) env('mcp.sharedSecret', ''));
        $given = trim((string) $this->request->getHeaderLine('X-MCP-Secret'));
        if ($secret === '' || $given === '' || ! hash_equals($secret, $given)) {
            log_message('warning', 'MCP session request rejected from {ip}', ['ip' => $this->request->getIPAddress()]);

            return $this->fail('Unauthorized.', 401);
        }

        $payload = $this->payload();
        $adminUserId = (int) ($payload['admin_user_id'] ?? 0);
        $keyName = preg_replace('/[^A-Za-z0-9 _.\-]/', '', (string) ($payload['key_name'] ?? 'default')) ?? 'default';
        $keyName = trim(mb_substr($keyName, 0, 40)) ?: 'default';

        if ($adminUserId <= 0) {
            return $this->fail('admin_user_id is required.', 422);
        }

        $admin = (new AdminUserModel())->where('id', $adminUserId)->where('is_active', 1)->first();
        if (! is_array($admin)) {
            return $this->fail('Admin user not found or inactive.', 404);
        }

        $deviceName = 'MCP:' . $keyName;
        $now = date('Y-m-d H:i:s');
        $tokenModel = new MobileApiTokenModel();

        // One live token per (user, MCP key): revoke the previous session first.
        $tokenModel->where('admin_user_id', $adminUserId)
            ->where('device_name', $deviceName)
            ->where('revoked_at', null)
            ->set(['revoked_at' => $now])
            ->update();

        $plainToken = bin2hex(random_bytes(40));
        $expiresAt = date('Y-m-d H:i:s', time() + self::SESSION_HOURS * 3600);
        $tokenModel->insert([
            'admin_user_id' => $adminUserId,
            'token_hash' => hash('sha256', $plainToken),
            'device_name' => $deviceName,
            'last_used_at' => $now,
            'expires_at' => $expiresAt,
            'revoked_at' => null,
        ]);

        return $this->ok([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
            'user' => [
                'id' => (int) $admin['id'],
                'name' => (string) ($admin['name'] ?? ''),
                'email' => (string) ($admin['email'] ?? ''),
            ],
        ], 'MCP session created.');
    }
}
