<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class CustomerOrderPortalWorkflowTest extends CIUnitTestCase
{
    public function testCustomerCreationProvisionsSecurePortalAdministrator(): void
    {
        $migration = (string) file_get_contents(APPPATH . 'Database/Migrations/2026-08-25-000064_CreateCustomerOrderPortal.php');
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Admin/CustomerController.php');
        $form = (string) file_get_contents(APPPATH . 'Views/admin/customers/create.php');

        $this->assertStringContainsString("createTable('customer_users')", $migration);
        $this->assertStringContainsString("'password_hash'", $migration);
        $this->assertStringContainsString("'sales_person_user_id'", $migration);
        $this->assertStringContainsString("'order_design_type'", $migration);
        $this->assertStringContainsString("password_hash((string) \$this->request->getPost('password')", $controller);
        $this->assertStringContainsString("'role' => 'customer_admin'", $controller);
        $this->assertStringContainsString('name="email"', $form);
        $this->assertStringContainsString('name="password"', $form);
        $this->assertStringContainsString('name="password_confirm"', $form);
    }

    public function testCustomerPortalNeverQueriesOrRendersKarigarAssignment(): void
    {
        $controller = (string) file_get_contents(APPPATH . 'Controllers/Customer/OrdersController.php');
        $index = (string) file_get_contents(APPPATH . 'Views/customer/orders/index.php');
        $create = (string) file_get_contents(APPPATH . 'Views/customer/orders/create.php');

        $this->assertStringContainsString("->where('orders.sales_person_user_id', (int) session('customer_user_id'))", $controller);
        $this->assertStringNotContainsString('assigned_karigar_id', $index);
        $this->assertStringNotContainsString('karigar_name', $index);
        $this->assertStringNotContainsString('assigned_karigar_id', $create);
        $this->assertStringContainsString('Internal karigar assignment always remains private.', $index);
        $this->assertStringContainsString('Karigar assignment and internal production details are never displayed', $create);
    }

    public function testSalespersonAndRepeatDesignControlsAreSearchableAndValidated(): void
    {
        $portalController = (string) file_get_contents(APPPATH . 'Controllers/Customer/OrdersController.php');
        $portalForm = (string) file_get_contents(APPPATH . 'Views/customer/orders/create.php');
        $adminForm = (string) file_get_contents(APPPATH . 'Views/admin/orders/create.php');
        $adminEdit = (string) file_get_contents(APPPATH . 'Views/admin/orders/edit.php');

        $this->assertStringContainsString("'order_design_type' => 'required|in_list[Fresh,Repeat]'", $portalController);
        $this->assertStringContainsString("'password_confirm' => 'required|matches[password]'", $portalController);
        $this->assertStringContainsString('id="sales-person" class="form-select js-searchable-select"', $portalForm);
        $this->assertStringContainsString('id="design-select" class="form-select js-searchable-select"', $portalForm);
        $this->assertStringContainsString('Unique Design Code', $portalForm);
        $this->assertStringContainsString('data-mobile=', $adminForm);
        $this->assertStringContainsString('js-design-select', $adminForm);
        $this->assertStringContainsString('data-dt-skip="true"', $adminForm);
        $this->assertStringContainsString('id="order-sales-person" class="form-control js-searchable-select"', $adminEdit);
    }
}
