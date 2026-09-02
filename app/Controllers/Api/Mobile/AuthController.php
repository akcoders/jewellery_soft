<?php

namespace App\Controllers\Api\Mobile;

use App\Models\AdminUserModel;
use App\Models\MobileApiTokenModel;

class AuthController extends MobileBaseController
{
    public function login()
    {
        $payload = $this->payload();
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $deviceName = trim((string) ($payload['device_name'] ?? 'Android'));

        if ($email === '' || $password === '') {
            return $this->fail('email and password are required.', 422);
        }

        $admin = (new AdminUserModel())->where('email', $email)->first();
        if (! $admin || (int) ($admin['is_active'] ?? 0) !== 1 || ! password_verify($password, (string) ($admin['password_hash'] ?? ''))) {
            return $this->fail('Invalid email or password.', 401);
        }

        $plainToken = bin2hex(random_bytes(40));
        $tokenHash = hash('sha256', $plainToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $tokenModel = new MobileApiTokenModel();
        $tokenModel->insert([
            'admin_user_id' => (int) $admin['id'],
            'token_hash' => $tokenHash,
            'device_name' => $deviceName !== '' ? $deviceName : null,
            'last_used_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'revoked_at' => null,
        ]);
        $roleCodes = $this->roleCodes((int) $admin['id']);

        return $this->ok([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
            'user' => [
                'id' => (int) $admin['id'],
                'name' => (string) ($admin['name'] ?? ''),
                'email' => (string) ($admin['email'] ?? ''),
                'role_codes' => $roleCodes,
                'is_admin' => $this->isAdminRole($roleCodes),
            ],
        ], 'Login successful.');
    }

    public function me()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $roleCodes = $this->roleCodes((int) ($this->mobileAdmin['id'] ?? 0));
        return $this->ok([
            'id' => (int) ($this->mobileAdmin['id'] ?? 0),
            'name' => (string) ($this->mobileAdmin['name'] ?? ''),
            'email' => (string) ($this->mobileAdmin['email'] ?? ''),
            'role_codes' => $roleCodes,
            'is_admin' => $this->isAdminRole($roleCodes),
        ]);
    }

    public function logout()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        if ($this->mobileTokenRow) {
            (new MobileApiTokenModel())->update((int) $this->mobileTokenRow['id'], [
                'revoked_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->ok(null, 'Logged out.');
    }

    /** @return list<string> */
    private function roleCodes(int $adminUserId): array
    {
        if ($adminUserId <= 0) {
            return [];
        }

        $rows = db_connect()->table('user_roles ur')
            ->select('r.role_code')
            ->join('roles r', 'r.id = ur.role_id', 'inner')
            ->where('ur.user_id', $adminUserId)
            ->orderBy('r.role_code', 'ASC')
            ->get()->getResultArray();

        return array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => strtoupper(trim((string) ($row['role_code'] ?? ''))),
            $rows
        ))));
    }

    private function isAdminRole(array $roleCodes): bool
    {
        return array_intersect(['SUPER_ADMIN', 'ADMIN', 'OWNER'], $roleCodes) !== [];
    }
}
