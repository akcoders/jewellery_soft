<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class McpSessionEndpointTest extends CIUnitTestCase
{
    public function testMcpSessionRouteIsRegisteredUnderApi(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertStringContainsString("\$routes->post('mcp/session', 'Api\\McpSessionController::create');", $routes);
    }

    public function testMcpSessionRequiresSharedSecretAndIssuesMobileToken(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Api/McpSessionController.php');

        $this->assertStringContainsString("env('mcp.sharedSecret', '')", $controller);
        $this->assertStringContainsString("getHeaderLine('X-MCP-Secret')", $controller);
        $this->assertStringContainsString('hash_equals($secret, $given)', $controller);
        $this->assertStringContainsString("->where('is_active', 1)", $controller);
        $this->assertStringContainsString("hash('sha256', \$plainToken)", $controller);
        $this->assertStringContainsString("'device_name' => \$deviceName", $controller);
        $this->assertStringContainsString("->set(['revoked_at' => \$now])", $controller);
        $this->assertStringContainsString('private const SESSION_HOURS = 12;', $controller);
    }

    public function testEnvTemplateDocumentsTheSharedSecret(): void
    {
        $env = (string) file_get_contents(ROOTPATH . 'env');

        $this->assertStringContainsString('# mcp.sharedSecret', $env);
    }
}
