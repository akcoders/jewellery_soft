<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DetailedOrderFormWorkflowTest extends CIUnitTestCase
{
    public function testDetailedOrderFieldsAreMigratedAndPersisted(): void
    {
        $migration = $this->source('Database/Migrations/2026-09-09-000088_AddDetailedOrderFormFields.php');
        $model = $this->source('Models/OrderModel.php');

        foreach ($this->detailedFields() as $field) {
            $this->assertStringContainsString("'{$field}'", $migration, $field);
            $this->assertStringContainsString("'{$field}'", $model, $field);
        }

        $this->assertStringContainsString("DATE(o.created_at)", $migration);
        $this->assertStringContainsString("NULLIF(c.phone", $migration);
        $this->assertStringContainsString("oi.diamond_required_cts > 0", $migration);
    }

    public function testAdminAndCustomerOrderCreationCaptureTheSamePaperFormDetails(): void
    {
        $adminController = $this->source('Controllers/Admin/OrderController.php');
        $customerController = $this->source('Controllers/Customer/OrdersController.php');

        foreach ($this->detailedFields() as $field) {
            $this->assertStringContainsString("'{$field}' =>", $adminController, 'admin ' . $field);
            $this->assertStringContainsString("'{$field}' =>", $customerController, 'customer ' . $field);
        }

        $this->assertStringContainsString('Advance amount cannot exceed the approximate price.', $adminController);
        $this->assertStringContainsString('Advance amount cannot exceed the approximate price.', $customerController);
        $this->assertStringContainsString('Enter the fixed gold rate per gram.', $adminController);
        $this->assertStringContainsString('Enter the fixed gold rate per gram.', $customerController);
    }

    public function testBothFormsMatchTheReferenceFormAndSupportMultipleImages(): void
    {
        foreach ([
            'Views/admin/orders/create.php',
            'Views/customer/orders/create.php',
        ] as $path) {
            $form = $this->source($path);

            foreach ([
                'order_received_date',
                'contact_number',
                'material_category',
                'certificate_requirement',
                'gold_rate_block_status',
                'gold_rate_per_gm',
                'approximate_price',
                'advance_amount',
                'due_date',
                'additional_details',
            ] as $field) {
                $this->assertStringContainsString('name="' . $field . '"', $form, $path . ' ' . $field);
            }

            foreach (['Gold', 'Diamond', 'Jadau', 'Silver'] as $category) {
                $this->assertStringContainsString("'{$category}'", $form, $path . ' ' . $category);
            }

            $this->assertStringContainsString('multiple', $form, $path . ' multiple reference images');
            $this->assertStringNotContainsString('name="branch_state"', $form, $path . ' branch/state field');
            foreach (['Hyderabad', 'Secbad', 'Vijayawada', 'Bengaluru'] as $ignoredBranch) {
                $this->assertStringNotContainsString($ignoredBranch, $form, $path . ' ' . $ignoredBranch);
            }
        }

        $this->assertStringContainsString('name="order_files[]"', $this->source('Views/admin/orders/create.php'));
        $this->assertStringContainsString('name="order_images[]"', $this->source('Views/customer/orders/create.php'));
    }

    public function testAdminEditAndDetailRemainCompatibleWithDetailedOrders(): void
    {
        $edit = $this->source('Views/admin/orders/edit.php');
        $show = $this->source('Views/admin/orders/show.php');

        foreach ($this->detailedFields() as $field) {
            $this->assertStringContainsString($field, $edit, 'edit ' . $field);
            $this->assertStringContainsString($field, $show, 'show ' . $field);
        }

        $this->assertStringContainsString('Gold Target', $show);
        $this->assertStringContainsString('Diamond Target', $show);
        $this->assertStringContainsString('Size / Length', $show);
    }

    /** @return list<string> */
    private function detailedFields(): array
    {
        return [
            'order_received_date',
            'contact_number',
            'material_category',
            'certificate_requirement',
            'additional_details',
            'gold_rate_block_status',
            'gold_rate_per_gm',
            'approximate_price',
            'advance_amount',
        ];
    }

    private function source(string $relativePath): string
    {
        return (string) file_get_contents(APPPATH . $relativePath);
    }
}
