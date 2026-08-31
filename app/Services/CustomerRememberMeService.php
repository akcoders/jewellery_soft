<?php

namespace App\Services;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CustomerRememberMeService
{
    public const COOKIE_NAME = 'aabhushan_customer_remember';
    public const TTL_SECONDS = 2_592_000;

    private const PENDING_ROTATION_KEY = '_customer_remember_rotation';
    private const PENDING_FORGET_KEY = '_customer_remember_forget';

    public function issue(int $customerUserId, RequestInterface $request, ResponseInterface $response): void
    {
        $db = db_connect();
        if ($customerUserId <= 0 || ! $db->tableExists('customer_remember_tokens')) {
            return;
        }

        $this->deleteCookieToken($this->cookieValue($request));
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');

        $db->table('customer_remember_tokens')->where('expires_at <', $now)->delete();
        $db->table('customer_remember_tokens')->insert([
            'customer_user_id' => $customerUserId,
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_SECONDS),
            'last_used_at' => null,
            'created_at' => $now,
        ]);

        session()->remove([self::PENDING_ROTATION_KEY, self::PENDING_FORGET_KEY]);
        $this->setCookie($response, $request, $selector . ':' . $validator, self::TTL_SECONDS);
    }

    public function restore(RequestInterface $request): bool
    {
        if (session('customer_user_logged_in')) {
            return true;
        }

        $db = db_connect();
        if (! $db->tableExists('customer_remember_tokens')) {
            return false;
        }

        $cookieValue = $this->cookieValue($request);
        [$selector, $validator] = $this->tokenParts($cookieValue);
        if ($selector === '' || $validator === '') {
            if ($cookieValue !== '') {
                session()->set(self::PENDING_FORGET_KEY, true);
            }
            return false;
        }

        $token = $db->table('customer_remember_tokens rt')
            ->select('rt.id, rt.customer_user_id, rt.selector, rt.validator_hash, rt.expires_at, cu.customer_id, cu.name, cu.email, cu.role, cu.is_active, c.name AS customer_name, c.is_active AS customer_active')
            ->join('customer_users cu', 'cu.id = rt.customer_user_id', 'inner')
            ->join('customers c', 'c.id = cu.customer_id', 'inner')
            ->where('rt.selector', $selector)
            ->get()
            ->getRowArray();

        $validRole = in_array((string) ($token['role'] ?? ''), ['customer_admin', 'sales_person'], true);
        $valid = $token
            && (int) ($token['is_active'] ?? 0) === 1
            && (int) ($token['customer_active'] ?? 0) === 1
            && $validRole
            && strtotime((string) ($token['expires_at'] ?? '')) >= time()
            && hash_equals((string) ($token['validator_hash'] ?? ''), hash('sha256', $validator));

        if (! $valid) {
            $this->deleteCookieToken($cookieValue);
            session()->set(self::PENDING_FORGET_KEY, true);
            return false;
        }

        session()->regenerate(true);
        session()->set([
            'customer_user_logged_in' => true,
            'customer_user_id' => (int) $token['customer_user_id'],
            'customer_id' => (int) $token['customer_id'],
            'customer_user_name' => (string) $token['name'],
            'customer_name' => (string) $token['customer_name'],
            'customer_user_role' => (string) $token['role'],
            self::PENDING_ROTATION_KEY => [
                'id' => (int) $token['id'],
                'selector' => (string) $token['selector'],
                'expires_at' => (string) $token['expires_at'],
            ],
        ]);

        $db->table('customer_users')->where('id', (int) $token['customer_user_id'])->update([
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function completePending(RequestInterface $request, ResponseInterface $response): void
    {
        if (session(self::PENDING_FORGET_KEY)) {
            session()->remove(self::PENDING_FORGET_KEY);
            $this->deleteCookie($response);
        }

        $pending = session(self::PENDING_ROTATION_KEY);
        if (! is_array($pending)) {
            return;
        }
        session()->remove(self::PENDING_ROTATION_KEY);

        $tokenId = (int) ($pending['id'] ?? 0);
        $oldSelector = (string) ($pending['selector'] ?? '');
        $expiresAt = strtotime((string) ($pending['expires_at'] ?? '')) ?: 0;
        $remaining = $expiresAt - time();
        if ($tokenId <= 0 || $remaining <= 0 || ! preg_match('/^[a-f0-9]{24}$/', $oldSelector)) {
            $this->deleteCookie($response);
            return;
        }

        $db = db_connect();
        if (! $db->tableExists('customer_remember_tokens')) {
            $this->deleteCookie($response);
            return;
        }

        $newSelector = bin2hex(random_bytes(12));
        $newValidator = bin2hex(random_bytes(32));
        $db->table('customer_remember_tokens')
            ->where('id', $tokenId)
            ->where('selector', $oldSelector)
            ->update([
                'selector' => $newSelector,
                'validator_hash' => hash('sha256', $newValidator),
                'last_used_at' => date('Y-m-d H:i:s'),
            ]);

        if ($db->affectedRows() !== 1) {
            $this->deleteCookie($response);
            return;
        }

        $this->setCookie($response, $request, $newSelector . ':' . $newValidator, $remaining);
    }

    public function forget(RequestInterface $request, ResponseInterface $response): void
    {
        $this->deleteCookieToken($this->cookieValue($request));
        session()->remove([self::PENDING_ROTATION_KEY, self::PENDING_FORGET_KEY]);
        $this->deleteCookie($response);
    }

    public function revokeUser(int $customerUserId): void
    {
        $db = db_connect();
        if ($customerUserId > 0 && $db->tableExists('customer_remember_tokens')) {
            $db->table('customer_remember_tokens')->where('customer_user_id', $customerUserId)->delete();
        }
    }

    private function cookieValue(RequestInterface $request): string
    {
        if (! method_exists($request, 'getCookie')) {
            return '';
        }

        return trim((string) $request->getCookie(self::COOKIE_NAME));
    }

    /** @return array{0:string,1:string} */
    private function tokenParts(string $cookieValue): array
    {
        $parts = explode(':', $cookieValue, 2);
        $selector = (string) ($parts[0] ?? '');
        $validator = (string) ($parts[1] ?? '');
        if (! preg_match('/^[a-f0-9]{24}$/', $selector) || ! preg_match('/^[a-f0-9]{64}$/', $validator)) {
            return ['', ''];
        }

        return [$selector, $validator];
    }

    private function deleteCookieToken(string $cookieValue): void
    {
        $db = db_connect();
        if (! $db->tableExists('customer_remember_tokens')) {
            return;
        }

        [$selector] = $this->tokenParts($cookieValue);
        if ($selector !== '') {
            $db->table('customer_remember_tokens')->where('selector', $selector)->delete();
        }
    }

    private function setCookie(ResponseInterface $response, RequestInterface $request, string $value, int $maxAge): void
    {
        $response->setCookie(
            self::COOKIE_NAME,
            $value,
            max(1, $maxAge),
            '',
            '/',
            '',
            $this->isSecure($request),
            true,
            'Lax'
        );
    }

    private function deleteCookie(ResponseInterface $response): void
    {
        $response->deleteCookie(self::COOKIE_NAME, '', '/');
    }

    private function isSecure(RequestInterface $request): bool
    {
        if (ENVIRONMENT === 'production') {
            return true;
        }
        if (method_exists($request, 'isSecure') && $request->isSecure()) {
            return true;
        }

        return str_starts_with(strtolower((string) config('App')->baseURL), 'https://');
    }
}
