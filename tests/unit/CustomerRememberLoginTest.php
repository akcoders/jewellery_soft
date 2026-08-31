<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class CustomerRememberLoginTest extends CIUnitTestCase
{
    public function testCustomerRememberTokensAreServerSideHashedAndScopedToPortalUsers(): void
    {
        $migration = (string) file_get_contents(APPPATH . 'Database/Migrations/2026-08-28-000072_CreateCustomerRememberTokens.php');
        $service = (string) file_get_contents(APPPATH . 'Services/CustomerRememberMeService.php');

        $this->assertStringContainsString("createTable('customer_remember_tokens'", $migration);
        $this->assertStringContainsString("addForeignKey('customer_user_id', 'customer_users'", $migration);
        $this->assertStringContainsString("COOKIE_NAME = 'aabhushan_customer_remember'", $service);
        $this->assertStringContainsString("hash('sha256', \$validator)", $service);
        $this->assertStringContainsString("['customer_admin', 'sales_person']", $service);
        $this->assertStringContainsString("join('customers c'", $service);
        $this->assertStringContainsString("'customer_active'", $service);
        $this->assertStringContainsString("->where('selector', \$oldSelector)", $service);
        $this->assertStringContainsString("true,\n            'Lax'", $service);
        $this->assertStringNotContainsString('password_hash', $service);
        $this->assertStringNotContainsString('localStorage', $service);
    }

    public function testCustomerAuthIssuesRestoresRotatesAndRevokesRememberedLogin(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Customer/AuthController.php');
        $filter = (string) file_get_contents(APPPATH . 'Filters/CustomerAuth.php');
        $customerAdmin = (string) file_get_contents(APPPATH . 'Controllers/Admin/CustomerController.php');
        $login = (string) file_get_contents(APPPATH . 'Views/customer/login.php');

        $this->assertStringContainsString("getPost('remember')", $controller);
        $this->assertStringContainsString('CustomerRememberMeService())->forget', $controller);
        $this->assertStringContainsString('->restore($this->request)', $controller);
        $this->assertStringContainsString('->completePending($request, $response)', $filter);
        $this->assertStringContainsString('->revokeUser($userId)', $customerAdmin);
        $this->assertStringContainsString('name="remember"', $login);
        $this->assertStringContainsString('Remember me on this device', $login);
        $this->assertStringContainsString('customer or salesperson login', $login);
        $this->assertStringNotContainsString('value="12345678"', $login);
    }
}
