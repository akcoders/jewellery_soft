<?php

namespace App\Controllers\Api\Mobile;

use App\Controllers\Api\ApiBaseController;
use App\Models\AdminUserModel;
use App\Models\MobileApiTokenModel;
use CodeIgniter\HTTP\ResponseInterface;

class MobileBaseController extends ApiBaseController
{
    protected ?array $mobileAdmin = null;
    protected ?array $mobileTokenRow = null;

    protected function requireMobileAuth(): ?ResponseInterface
    {
        $bearer = $this->bearerToken();
        if ($bearer === '') {
            return $this->fail('Unauthorized. Bearer token is required.', 401);
        }

        $tokenHash = hash('sha256', $bearer);
        $tokenModel = new MobileApiTokenModel();
        $row = $tokenModel->where('token_hash', $tokenHash)->first();

        if (! $row || ! empty($row['revoked_at'])) {
            return $this->fail('Unauthorized. Invalid token.', 401);
        }

        $expiresAt = (string) ($row['expires_at'] ?? '');
        if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
            return $this->fail('Session expired. Please login again.', 401);
        }

        $admin = (new AdminUserModel())
            ->where('id', (int) ($row['admin_user_id'] ?? 0))
            ->where('is_active', 1)
            ->first();

        if (! $admin) {
            return $this->fail('Unauthorized. User not available.', 401);
        }

        $this->mobileTokenRow = $row;
        $this->mobileAdmin = $admin;

        $tokenModel->update((int) $row['id'], [
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);

        return null;
    }

    protected function bearerToken(): string
    {
        $header = (string) $this->request->getHeaderLine('Authorization');
        if ($header === '') {
            return '';
        }

        if (! preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return '';
        }

        return trim((string) ($matches[1] ?? ''));
    }
}

