<?php

namespace App\Services;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminRememberMeService
{
    public const COOKIE_NAME = 'aabhushan_admin_remember';
    public const TTL_SECONDS = 2_592_000;

    public function issue(int $adminUserId, RequestInterface $request, ResponseInterface $response): void
    {
        if ($adminUserId <= 0 || ! db_connect()->tableExists('admin_remember_tokens')) {
            return;
        }

        $this->deleteCookieToken($this->cookieValue($request));
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');

        db_connect()->table('admin_remember_tokens')
            ->where('expires_at <', $now)
            ->delete();
        db_connect()->table('admin_remember_tokens')->insert([
            'admin_user_id' => $adminUserId,
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_SECONDS),
            'last_used_at' => null,
            'created_at' => $now,
        ]);

        $response->setCookie(
            self::COOKIE_NAME,
            $selector . ':' . $validator,
            self::TTL_SECONDS,
            '',
            '/',
            '',
            $this->isSecure($request),
            true,
            'Lax'
        );
    }

    public function restore(RequestInterface $request): bool
    {
        if (session('admin_logged_in')) {
            return true;
        }
        if (! db_connect()->tableExists('admin_remember_tokens')) {
            return false;
        }

        [$selector, $validator] = $this->tokenParts($this->cookieValue($request));
        if ($selector === '' || $validator === '') {
            return false;
        }

        $token = db_connect()->table('admin_remember_tokens rt')
            ->select('rt.id, rt.admin_user_id, rt.validator_hash, rt.expires_at, au.name, au.email, au.is_active')
            ->join('admin_users au', 'au.id = rt.admin_user_id', 'inner')
            ->where('rt.selector', $selector)
            ->get()
            ->getRowArray();

        $valid = $token
            && (int) ($token['is_active'] ?? 0) === 1
            && strtotime((string) ($token['expires_at'] ?? '')) >= time()
            && hash_equals((string) ($token['validator_hash'] ?? ''), hash('sha256', $validator));
        if (! $valid) {
            $this->deleteCookieToken($selector . ':' . $validator);
            return false;
        }

        session()->regenerate();
        session()->set([
            'admin_logged_in' => true,
            'admin_id' => (int) $token['admin_user_id'],
            'admin_name' => (string) $token['name'],
            'admin_email' => (string) $token['email'],
        ]);
        db_connect()->table('admin_remember_tokens')->where('id', (int) $token['id'])->update([
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function forget(RequestInterface $request, ResponseInterface $response): void
    {
        $this->deleteCookieToken($this->cookieValue($request));
        $response->deleteCookie(self::COOKIE_NAME, '', '/');
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
        if (! db_connect()->tableExists('admin_remember_tokens')) {
            return;
        }

        [$selector] = $this->tokenParts($cookieValue);
        if ($selector !== '') {
            db_connect()->table('admin_remember_tokens')->where('selector', $selector)->delete();
        }
    }

    private function isSecure(RequestInterface $request): bool
    {
        return method_exists($request, 'isSecure') && $request->isSecure();
    }
}
