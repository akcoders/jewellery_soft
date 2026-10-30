<?php

namespace Tests\Unit;

use App\Services\McpApiKeyService;
use CodeIgniter\Test\CIUnitTestCase;

final class McpApiKeyTest extends CIUnitTestCase
{
    public function testScopesAreNormalisedToKnownValues(): void
    {
        $this->assertSame(['write', 'read'], McpApiKeyService::normalizeScopes(['Write', 'read', 'bogus', 'read']));
        $this->assertSame(['read', 'sql'], McpApiKeyService::normalizeScopes('read, sql'));
        $this->assertSame([], McpApiKeyService::normalizeScopes(''));
    }

    public function testGeneratedKeysMatchTheMcpServerFormatAndHash(): void
    {
        $key = McpApiKeyService::generatePlainKey();

        $this->assertMatchesRegularExpression('/^mcp_[a-f0-9]{64}$/', $key);
        $this->assertNotSame($key, McpApiKeyService::generatePlainKey());
        // Same SHA-256 the Node MCP server computes for a bearer key.
        $this->assertSame('bb9c9abad60424739d208a28953a4fef9e624189af10a5f4818d258534d352ea', McpApiKeyService::hashKey('mcp_test'));
    }

    public function testAdminRoutesAreGuardedAndVerifyRouteIsRegistered(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertStringContainsString("\$routes->get('mcp-keys', 'Admin\\McpApiKeyController::index', ['filter' => 'permission:company-settings.manage']);", $routes);
        $this->assertStringContainsString("\$routes->post('mcp-keys', 'Admin\\McpApiKeyController::store', ['filter' => ['permission:company-settings.manage', 'csrf']]);", $routes);
        $this->assertStringContainsString("\$routes->post('mcp-keys/(:num)/revoke', 'Admin\\McpApiKeyController::revoke/\$1', ['filter' => ['permission:company-settings.manage', 'csrf']]);", $routes);
        $this->assertStringContainsString("\$routes->post('mcp/verify-key', 'Api\\McpSessionController::verifyKey');", $routes);
    }

    public function testVerifyKeyRequiresSharedSecretAndStoresOnlyHashes(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Api/McpSessionController.php');
        $service = (string) file_get_contents(APPPATH . 'Services/McpApiKeyService.php');

        $this->assertStringContainsString("if (! \$this->hasValidSecret('key verification'))", $controller);
        $this->assertStringContainsString("if (! \$this->hasValidSecret('session'))", $controller);
        $this->assertStringContainsString("'key_hash' => self::hashKey(\$plainKey)", $service);
        $this->assertStringNotContainsString("'plain_key' => \$plainKey,\n            'key_prefix'", $service);
        $this->assertStringContainsString("return 'This API key has been revoked.';", $service);
        $this->assertStringContainsString("return 'The ERP user for this API key is inactive.';", $service);
    }

    public function testSidebarLinksToTheKeyPage(): void
    {
        $layout = (string) file_get_contents(APPPATH . 'Views/admin/layouts/main.php');

        $this->assertStringContainsString("site_url('admin/mcp-keys')", $layout);
    }
}
