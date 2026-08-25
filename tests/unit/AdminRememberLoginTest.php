<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class AdminRememberLoginTest extends CIUnitTestCase
{
    public function testRememberLoginUsesHashedServerSideTokensAndSecureCookieFlags(): void
    {
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-08-25-000068_CreateAdminRememberTokens.php'
        );
        $service = (string) file_get_contents(APPPATH . 'Services/AdminRememberMeService.php');
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/AuthController.php');

        $this->assertStringContainsString("createTable('admin_remember_tokens'", $migration);
        $this->assertStringContainsString("'validator_hash'", $migration);
        $this->assertStringContainsString("'expires_at'", $migration);
        $this->assertStringContainsString("hash('sha256', \$validator)", $service);
        $this->assertStringContainsString('bin2hex(random_bytes(32))', $service);
        $this->assertStringContainsString('2_592_000', $service);
        $this->assertStringContainsString("true,\n            'Lax'", $service);
        $this->assertStringContainsString("getPost('remember')", $controller);
        $this->assertStringContainsString('AdminRememberMeService())->forget', $controller);
    }

    public function testAdminFiltersCanRestoreAValidRememberedLogin(): void
    {
        foreach (['AdminAuth.php', 'AdminGuest.php', 'AdminPermission.php'] as $file) {
            $source = (string) file_get_contents(APPPATH . 'Filters/' . $file);
            $this->assertStringContainsString('AdminRememberMeService', $source, $file);
            $this->assertStringContainsString('->restore($request)', $source, $file);
        }
    }

    public function testLoginFormProvidesRememberCheckboxAndPasswordManagerHints(): void
    {
        $view = (string) file_get_contents(APPPATH . 'Views/admin/auth/login.php');

        $this->assertStringContainsString('name="remember"', $view);
        $this->assertStringContainsString('Remember me on this device', $view);
        $this->assertStringContainsString('autocomplete="username"', $view);
        $this->assertStringContainsString('autocomplete="current-password"', $view);
        $this->assertStringContainsString('signed in for 30 days', $view);
    }

    public function testDevendraMigrationSeedsOnlyPasswordHashAndOrderOperationsRole(): void
    {
        $migration = (string) file_get_contents(
            APPPATH . 'Database/Migrations/2026-08-25-000069_SeedDevendraMishraUser.php'
        );

        $this->assertStringContainsString("'devendra.mishra@aabhushan.in'", $migration);
        $this->assertStringContainsString("'Devendra Mishra'", $migration);
        $this->assertStringContainsString("'ORDER_OPERATIONS'", $migration);
        $this->assertStringContainsString("'orders.followup'", $migration);
        $this->assertStringContainsString("'issuements.create'", $migration);
        $this->assertStringContainsString("private const PASSWORD_HASH = '\$2y\$12\$", $migration);
        $this->assertStringNotContainsString('Devendra@26#M7', $migration);
    }
}
