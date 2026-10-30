<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminUserModel;
use App\Services\AdminRememberMeService;
use App\Services\McpApiKeyService;
use Throwable;

/**
 * /admin/mcp-authorize – OAuth consent page for MCP clients (ChatGPT, claude.ai
 * connectors). The MCP server sends the browser here with an HMAC-signed
 * request; the logged-in ERP user allows or declines, and the browser goes back
 * to the MCP server with a signed decision. Any active ERP user can connect
 * their own account; the AI client then has exactly that user's permissions.
 */
class McpAuthorizeController extends BaseController
{
    /** Session key that lets the login page return here afterwards. */
    public const RETURN_SESSION_KEY = 'mcp_authorize_return';

    private const DECISION_TTL_SECONDS = 120;

    public function index()
    {
        $request = $this->signedRequest();
        if ($request === null) {
            return $this->invalid();
        }

        if (! $this->isLoggedIn()) {
            session()->set(self::RETURN_SESSION_KEY, site_url('admin/mcp-authorize') . '?' . http_build_query([
                'd' => (string) $this->request->getGet('d'),
                's' => (string) $this->request->getGet('s'),
            ]));

            return redirect()->to(site_url('admin/login'))
                ->with('error', 'Log in to connect ' . $request['client'] . ' to Aabhushan ERP.');
        }

        $user = (new AdminUserModel())->find((int) session('admin_id'));

        return view('admin/mcp_keys/authorize', [
            'title' => 'Connect ' . $request['client'],
            'clientName' => $request['client'],
            'redirectLabel' => $request['redirect'],
            'scopes' => $request['scopes'],
            'userName' => is_array($user) ? (string) ($user['name'] ?? '') : (string) session('admin_name'),
            'userEmail' => is_array($user) ? (string) ($user['email'] ?? '') : (string) session('admin_email'),
            'd' => (string) $this->request->getGet('d'),
            's' => (string) $this->request->getGet('s'),
        ]);
    }

    public function decide()
    {
        $data = (string) $this->request->getPost('d');
        $sig = (string) $this->request->getPost('s');
        $request = $this->signedRequest($data, $sig);
        if ($request === null) {
            return $this->invalid();
        }
        if (! $this->isLoggedIn()) {
            return redirect()->to(site_url('admin/login'))->with('error', 'Please login first.');
        }

        $secret = $this->sharedSecret();
        $decision = ['v' => 1, 'rid' => $request['rid'], 'decision' => 'deny', 'exp' => time() + self::DECISION_TTL_SECONDS];

        if ((string) $this->request->getPost('decision') === 'allow') {
            $scopes = array_values(array_intersect(
                McpApiKeyService::normalizeScopes((array) $this->request->getPost('scopes')),
                $request['scopes']
            ));
            if ($scopes === []) {
                return redirect()->to(site_url('admin/mcp-authorize') . '?' . http_build_query(['d' => $data, 's' => $sig]))
                    ->with('error', 'Select at least one permission, or choose Deny.');
            }

            $adminId = (int) session('admin_id');
            try {
                $grantId = (new McpApiKeyService())->createOAuthGrant($adminId, $request['client'], $scopes);
            } catch (Throwable $e) {
                log_message('error', 'MCP OAuth grant failed: {message}', ['message' => $e->getMessage()]);

                return $this->invalid('Could not save the connection. Please try again.');
            }

            $decision = [
                'v' => 1,
                'rid' => $request['rid'],
                'decision' => 'allow',
                'grant_id' => $grantId,
                'uid' => $adminId,
                'scopes' => $scopes,
                'exp' => time() + self::DECISION_TTL_SECONDS,
            ];
        }

        $signed = McpApiKeyService::signPayload($decision, $secret);
        $separator = str_contains($request['cb'], '?') ? '&' : '?';

        return redirect()->to($request['cb'] . $separator . http_build_query(['d' => $signed['data'], 's' => $signed['sig']]));
    }

    /**
     * Verifies the MCP server's signed request (from the query string by default).
     *
     * @return array{rid:string,client:string,redirect:string,scopes:list<string>,cb:string}|null
     */
    private function signedRequest(?string $data = null, ?string $sig = null): ?array
    {
        $payload = McpApiKeyService::verifySignedPayload(
            $data ?? (string) $this->request->getGet('d'),
            $sig ?? (string) $this->request->getGet('s'),
            $this->sharedSecret()
        );
        if ($payload === null || (int) ($payload['v'] ?? 0) !== 1 || (int) ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        $rid = (string) ($payload['rid'] ?? '');
        $cb = (string) ($payload['cb'] ?? '');
        $scopes = McpApiKeyService::normalizeScopes((array) ($payload['scopes'] ?? []));
        if ($rid === '' || (! str_starts_with($cb, 'https://') && ! str_starts_with($cb, 'http://')) || $scopes === []) {
            return null;
        }

        return [
            'rid' => $rid,
            'client' => mb_substr(trim((string) ($payload['client'] ?? '')), 0, 100) ?: 'MCP client',
            'redirect' => mb_substr((string) ($payload['redirect'] ?? ''), 0, 200),
            'scopes' => $scopes,
            'cb' => $cb,
        ];
    }

    private function isLoggedIn(): bool
    {
        return (bool) session('admin_logged_in') || (new AdminRememberMeService())->restore($this->request);
    }

    private function sharedSecret(): string
    {
        return trim((string) env('mcp.sharedSecret', ''));
    }

    private function invalid(string $message = 'This connection link is invalid or has expired. Start again from your AI app.')
    {
        return $this->response->setStatusCode(400)->setBody(view('admin/mcp_keys/authorize_error', ['message' => $message]));
    }
}
