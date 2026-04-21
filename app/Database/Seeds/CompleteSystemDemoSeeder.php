<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CompleteSystemDemoSeeder extends Seeder
{
    public function run()
    {
        $this->call(AdminUserSeeder::class);
        $this->call(InitialMasterSeeder::class);
        $this->call(AdvancedJobworkSeeder::class);
        $this->call(DemoKarigarSeeder::class);
        $this->call(DemoDiamondBagSeeder::class);
        $this->call(DemoDesignInventorySeeder::class);
        $this->call(DiamondInventorySeeder::class);
        $this->call(DemoFullFlowSeeder::class);

        $db = $this->db;
        $now = date('Y-m-d H:i:s');

        $this->ensureCompanySettings($now);
        $salesEmployeeId = $this->ensureSalesExecutive($now);
        [$showroomId, $counterId] = $this->ensureShowroomFoundation($salesEmployeeId, $now);
        $this->ensureShowroomSale($showroomId, $counterId, $salesEmployeeId, $now);
        $this->ensureNotes($now);
    }

    private function ensureCompanySettings(string $now): void
    {
        if (! $this->db->tableExists('company_settings')) {
            return;
        }

        $row = $this->db->table('company_settings')->orderBy('id', 'ASC')->get()->getRowArray();
        $data = $this->filterExistingFields('company_settings', [
            'company_name' => 'Jewellery Soft Demo',
            'email' => 'info@jewellerysoft.demo',
            'phone' => '9000009999',
            'address_line' => 'Demo Manufacturing Unit, India',
            'city' => 'Demo City',
            'state' => 'Demo State',
            'pincode' => '395007',
            'gstin' => '24ABCDE1234F1Z5',
            'updated_at' => $now,
        ]);

        if ($row) {
            $this->db->table('company_settings')->where('id', (int) $row['id'])->update($data);
            return;
        }

        $data['created_at'] = $now;
        $this->db->table('company_settings')->insert($data);
    }

    private function ensureSalesExecutive(string $now): int
    {
        if (! $this->db->tableExists('admin_users') || ! $this->db->tableExists('employees')) {
            return 0;
        }

        $user = $this->db->table('admin_users')->where('email', 'salesexec@demo.com')->get()->getRowArray();
        if (! $user) {
            $this->db->table('admin_users')->insert([
                'name' => 'Demo Sales Executive',
                'email' => 'salesexec@demo.com',
                'password_hash' => password_hash('Sales@123', PASSWORD_DEFAULT),
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $user = ['id' => (int) $this->db->insertID()];
        }

        $employee = $this->db->table('employees')->where('admin_user_id', (int) $user['id'])->get()->getRowArray();
        if ($employee) {
            return (int) $employee['id'];
        }

        $departmentId = $this->ensureDepartment($now);
        $designationId = $this->ensureDesignation($departmentId, $now);

        $this->db->table('employees')->insert([
            'employee_code' => 'EMP-SALES-DEMO',
            'admin_user_id' => (int) $user['id'],
            'department_id' => $departmentId > 0 ? $departmentId : null,
            'designation_id' => $designationId > 0 ? $designationId : null,
            'full_name' => 'Demo Sales Executive',
            'mobile' => '9000000010',
            'email' => 'salesexec@demo.com',
            'work_location' => 'Retail Showroom',
            'joining_date' => date('Y-m-d'),
            'notes' => 'Complete system demo sales user',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    private function ensureDepartment(string $now): int
    {
        if (! $this->db->tableExists('departments')) {
            return 0;
        }

        $row = $this->db->table('departments')->where('department_code', 'SALES')->get()->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }

        $this->db->table('departments')->insert([
            'department_code' => 'SALES',
            'name' => 'Sales',
            'sort_order' => 20,
            'is_active' => 1,
            'notes' => 'Retail showroom and sales staff',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    private function ensureDesignation(int $departmentId, string $now): int
    {
        if (! $this->db->tableExists('designations')) {
            return 0;
        }

        $row = $this->db->table('designations')->where('designation_code', 'SALES_EXEC')->get()->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }

        $this->db->table('designations')->insert([
            'department_id' => $departmentId > 0 ? $departmentId : null,
            'designation_code' => 'SALES_EXEC',
            'name' => 'Sales Executive',
            'level_no' => 2,
            'can_manage_team' => 0,
            'is_active' => 1,
            'description' => 'Retail showroom sales executive',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @return array{0:int,1:int}
     */
    private function ensureShowroomFoundation(int $salesEmployeeId, string $now): array
    {
        if (! $this->db->tableExists('showrooms') || ! $this->db->tableExists('showroom_counters')) {
            return [0, 0];
        }

        $showroom = $this->db->table('showrooms')->where('showroom_code', 'DEMO-SR')->get()->getRowArray();
        if (! $showroom) {
            $this->db->table('showrooms')->insert($this->filterExistingFields('showrooms', [
                'showroom_code' => 'DEMO-SR',
                'name' => 'Demo Retail Showroom',
                'showroom_type' => 'Retail Showroom',
                'manager_employee_id' => $salesEmployeeId > 0 ? $salesEmployeeId : null,
                'phone' => '9000004444',
                'email' => 'showroom@jewellerysoft.demo',
                'gstin' => '24ABCDE1234F1Z5',
                'address_line' => 'Demo Retail Counter, India',
                'city_name' => 'Demo City',
                'state_name' => 'Demo State',
                'is_active' => 1,
                'notes' => 'Complete system demo showroom',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
            $showroom = ['id' => (int) $this->db->insertID()];
        }

        $counter = $this->db->table('showroom_counters')
            ->where('showroom_id', (int) $showroom['id'])
            ->where('counter_code', 'CNT-1')
            ->get()
            ->getRowArray();

        if (! $counter) {
            $this->db->table('showroom_counters')->insert([
                'showroom_id' => (int) $showroom['id'],
                'counter_code' => 'CNT-1',
                'counter_name' => 'Main Counter',
                'counter_type' => 'Sales Counter',
                'incharge_employee_id' => $salesEmployeeId > 0 ? $salesEmployeeId : null,
                'is_active' => 1,
                'notes' => 'Complete system demo counter',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $counter = ['id' => (int) $this->db->insertID()];
        }

        if ($salesEmployeeId > 0 && $this->db->tableExists('showroom_staff_assignments')) {
            $exists = $this->db->table('showroom_staff_assignments')
                ->where('showroom_id', (int) $showroom['id'])
                ->where('employee_id', $salesEmployeeId)
                ->countAllResults();
            if ($exists === 0) {
                $this->db->table('showroom_staff_assignments')->insert([
                    'showroom_id' => (int) $showroom['id'],
                    'employee_id' => $salesEmployeeId,
                    'role_label' => 'Sales Executive',
                    'is_primary' => 1,
                    'effective_from' => date('Y-m-d'),
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        return [(int) $showroom['id'], (int) $counter['id']];
    }

    private function ensureShowroomSale(int $showroomId, int $counterId, int $salesEmployeeId, string $now): void
    {
        if ($showroomId <= 0 || ! $this->db->tableExists('showroom_sales') || ! $this->db->tableExists('showroom_sale_items')) {
            return;
        }

        $invoice = $this->db->table('invoices')->orderBy('id', 'DESC')->get()->getRowArray();
        $customer = $this->db->table('customers')->where('name', 'Seed Demo Customer')->get()->getRowArray();
        $fgItem = $this->db->table('fg_items')->orderBy('id', 'DESC')->get()->getRowArray();
        $invoiceItem = $invoice ? $this->db->table('invoice_items')->where('invoice_id', (int) $invoice['id'])->orderBy('id', 'ASC')->get()->getRowArray() : null;

        if (! $invoice || ! $customer || ! $fgItem) {
            return;
        }

        $sale = $this->db->table('showroom_sales')->where('invoice_id', (int) $invoice['id'])->get()->getRowArray();
        if (! $sale) {
            $this->db->table('showroom_sales')->insert([
                'sale_no' => 'DEMO-SALE-' . date('Ymd'),
                'sale_date' => (string) ($invoice['invoice_date'] ?? date('Y-m-d')),
                'showroom_id' => $showroomId,
                'showroom_counter_id' => $counterId > 0 ? $counterId : null,
                'salesperson_employee_id' => $salesEmployeeId > 0 ? $salesEmployeeId : null,
                'customer_id' => (int) $customer['id'],
                'invoice_id' => (int) $invoice['id'],
                'total_qty' => 1,
                'taxable_amount' => (float) ($invoice['taxable_amount'] ?? 0),
                'gst_percent' => 3,
                'gst_amount' => (float) ($invoice['gst_amount'] ?? 0),
                'total_amount' => (float) ($invoice['total_amount'] ?? 0),
                'received_amount' => (float) ($this->db->table('customer_receipts')->select('COALESCE(SUM(amount),0) as total_amount', false)->where('invoice_id', (int) $invoice['id'])->get()->getRowArray()['total_amount'] ?? 0),
                'payment_status' => 'Partial',
                'sale_status' => 'Completed',
                'notes' => 'Complete system demo showroom sale',
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sale = ['id' => (int) $this->db->insertID()];
        }

        $exists = $this->db->table('showroom_sale_items')
            ->where('showroom_sale_id', (int) $sale['id'])
            ->where('fg_item_id', (int) $fgItem['id'])
            ->countAllResults();

        if ($exists === 0) {
            $this->db->table('showroom_sale_items')->insert([
                'showroom_sale_id' => (int) $sale['id'],
                'fg_item_id' => (int) $fgItem['id'],
                'invoice_item_id' => $invoiceItem ? (int) $invoiceItem['id'] : null,
                'description' => (string) ($invoiceItem['description'] ?? 'Demo Ornament Sale'),
                'qty' => (float) ($invoiceItem['qty'] ?? 1),
                'rate' => (float) ($invoiceItem['rate'] ?? 0),
                'amount' => (float) ($invoiceItem['amount'] ?? 0),
                'gross_wt' => (float) ($fgItem['gross_wt'] ?? 0),
                'net_gold_wt' => (float) ($fgItem['net_gold_wt'] ?? 0),
                'diamond_cts' => (float) ($fgItem['diamond_cts'] ?? 0),
                'stone_wt' => (float) ($fgItem['stone_wt'] ?? 0),
                'gst_percent' => (float) ($invoiceItem['gst_percent'] ?? 3),
                'gst_amount' => (float) ($invoiceItem['gst_amount'] ?? 0),
                'created_at' => $now,
            ]);
        }
    }

    private function ensureNotes(string $now): void
    {
        $customer = $this->db->tableExists('customers')
            ? $this->db->table('customers')->where('name', 'Seed Demo Customer')->get()->getRowArray()
            : null;
        $vendor = $this->db->tableExists('vendors')
            ? $this->db->table('vendors')->where('name', 'Seed Demo Vendor')->get()->getRowArray()
            : null;
        $order = $this->db->tableExists('orders')
            ? $this->db->table('orders')->orderBy('id', 'DESC')->get()->getRowArray()
            : null;
        $invoice = $this->db->tableExists('invoices')
            ? $this->db->table('invoices')->orderBy('id', 'DESC')->get()->getRowArray()
            : null;

        if ($this->db->tableExists('debit_notes') && $customer) {
            $exists = $this->db->table('debit_notes')->where('reference_no', 'DEMO-DEBIT-001')->countAllResults();
            if ($exists === 0) {
                $this->db->table('debit_notes')->insert([
                    'note_no' => 'DN-DEMO-001',
                    'note_date' => date('Y-m-d'),
                    'party_type' => 'customer',
                    'customer_id' => (int) $customer['id'],
                    'order_id' => $order ? (int) $order['id'] : null,
                    'invoice_id' => $invoice ? (int) $invoice['id'] : null,
                    'reference_no' => 'DEMO-DEBIT-001',
                    'reason' => 'Extra customization charge',
                    'taxable_amount' => 1500,
                    'gst_percent' => 3,
                    'gst_amount' => 45,
                    'total_amount' => 1545,
                    'status' => 'Posted',
                    'notes' => 'Demo customer debit note',
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if ($this->db->tableExists('credit_notes') && $vendor) {
            $exists = $this->db->table('credit_notes')->where('reference_no', 'DEMO-CREDIT-001')->countAllResults();
            if ($exists === 0) {
                $this->db->table('credit_notes')->insert([
                    'note_no' => 'CN-DEMO-001',
                    'note_date' => date('Y-m-d'),
                    'party_type' => 'vendor',
                    'vendor_id' => (int) $vendor['id'],
                    'order_id' => $order ? (int) $order['id'] : null,
                    'invoice_id' => $invoice ? (int) $invoice['id'] : null,
                    'reference_no' => 'DEMO-CREDIT-001',
                    'reason' => 'Material return adjustment',
                    'taxable_amount' => 2200,
                    'gst_percent' => 3,
                    'gst_amount' => 66,
                    'total_amount' => 2266,
                    'status' => 'Posted',
                    'notes' => 'Demo vendor credit note',
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function filterExistingFields(string $table, array $data): array
    {
        $filtered = [];
        foreach ($data as $field => $value) {
            if ($this->db->fieldExists($field, $table)) {
                $filtered[$field] = $value;
            }
        }

        return $filtered;
    }
}
