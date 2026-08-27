<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class UserCustomerManagementTest extends CIUnitTestCase
{
    public function testAdminUsersCanBeCreatedViewedAndHavePasswordsResetSecurely(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/Access/UsersController.php');
        $index = (string) file_get_contents(APPPATH . 'Views/admin/access/users/index.php');
        $create = (string) file_get_contents(APPPATH . 'Views/admin/access/users/create.php');
        $details = (string) file_get_contents(APPPATH . 'Views/admin/access/users/form.php');

        $this->assertStringContainsString("access/users/create', 'Admin\\Access\\UsersController::create", $routes);
        $this->assertStringContainsString("access/users/(:num)/password', 'Admin\\Access\\UsersController::updatePassword", $routes);
        $this->assertStringContainsString("'password_hash' => password_hash", $controller);
        $this->assertStringContainsString("tableExists('admin_remember_tokens')", $controller);
        $this->assertStringContainsString("tableExists('mobile_api_tokens')", $controller);
        $this->assertStringContainsString('Select at least one active role', $controller);
        $this->assertStringContainsString('Create User', $index);
        $this->assertStringContainsString('name="role_ids[]"', $create);
        $this->assertStringContainsString('name="password_confirm"', $create);
        $this->assertStringContainsString('User Details & Access', $details);
        $this->assertStringContainsString('Existing passwords cannot be viewed', $details);
    }

    public function testCustomerDetailsExposePortalAccountsWithoutExposingPasswords(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/CustomerController.php');
        $index = (string) file_get_contents(APPPATH . 'Views/admin/customers/index.php');
        $details = (string) file_get_contents(APPPATH . 'Views/admin/customers/show.php');

        $this->assertStringContainsString("customers/(:num)', 'Admin\\CustomerController::show", $routes);
        $this->assertStringContainsString("customers/(:num)/users', 'Admin\\CustomerController::storePortalUser", $routes);
        $this->assertStringContainsString('updatePortalPassword/$1/$2', $routes);
        $this->assertStringContainsString("->where('customer_id', \$customerId)", $controller);
        $this->assertStringContainsString("'password_hash' => password_hash", $controller);
        $this->assertStringContainsString('Details', $index);
        $this->assertStringContainsString('Portal Users', $details);
        $this->assertStringContainsString('Add Portal User', $details);
        $this->assertStringContainsString('Create Portal User', $details);
        $this->assertStringContainsString('Passwords are never displayed', $details);
        $this->assertStringContainsString('Update Portal Password', $details);
        $this->assertStringNotContainsString("['password_hash']", $details);
    }
}
