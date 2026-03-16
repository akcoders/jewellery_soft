<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateShowroomSalesTables extends Migration
{
    public function up()
    {
        $this->createShowroomSalesTable();
        $this->createShowroomSaleItemsTable();
        $this->seedShowroomSalesPermissions();
    }

    public function down()
    {
        if ($this->db->tableExists('showroom_sale_items')) {
            $this->forge->dropTable('showroom_sale_items', true);
        }
        if ($this->db->tableExists('showroom_sales')) {
            $this->forge->dropTable('showroom_sales', true);
        }
    }

    private function createShowroomSalesTable(): void
    {
        if ($this->db->tableExists('showroom_sales')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'sale_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'sale_date' => ['type' => 'DATE'],
            'showroom_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'showroom_counter_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'salesperson_employee_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'customer_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'reservation_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'invoice_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'total_qty' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'taxable_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'gst_percent' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 3],
            'gst_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'received_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'payment_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Pending'],
            'sale_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Completed'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('sale_no');
        $this->forge->addKey('sale_date');
        $this->forge->addKey('showroom_id');
        $this->forge->addKey('showroom_counter_id');
        $this->forge->addKey('salesperson_employee_id');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('invoice_id');
        $this->forge->addKey('payment_status');
        $this->forge->createTable('showroom_sales', true);
    }

    private function createShowroomSaleItemsTable(): void
    {
        if ($this->db->tableExists('showroom_sale_items')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'showroom_sale_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'fg_item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'invoice_item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 200],
            'qty' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 1],
            'rate' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'gross_wt' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'net_gold_wt' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'diamond_cts' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'stone_wt' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'gst_percent' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 0],
            'gst_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('showroom_sale_id');
        $this->forge->addKey('fg_item_id');
        $this->forge->addKey('invoice_item_id');
        $this->forge->createTable('showroom_sale_items', true);
    }

    private function seedShowroomSalesPermissions(): void
    {
        if (! $this->db->tableExists('permissions')) {
            return;
        }

        $permissions = [
            ['code' => 'showroom.sales.read', 'name' => 'View Showroom Sales', 'module_group' => 'Showroom', 'action_key' => 'read', 'description' => 'Access showroom sales and retail billing screens', 'sort_order' => 505],
            ['code' => 'showroom.sales.manage', 'name' => 'Manage Showroom Sales', 'module_group' => 'Showroom', 'action_key' => 'manage', 'description' => 'Create retail sales and convert reservations into bills', 'sort_order' => 506],
        ];

        foreach ($permissions as $permission) {
            $existing = $this->db->table('permissions')->where('code', $permission['code'])->get()->getRowArray();
            $data = $permission + ['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')];
            if ($existing) {
                $this->db->table('permissions')->where('id', $existing['id'])->update($data);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $this->db->table('permissions')->insert($data);
            }
        }

        $superRole = $this->db->table('roles')->where('role_code', 'SUPER_ADMIN')->get()->getRowArray();
        if (! $superRole) {
            return;
        }

        $permissionRows = $this->db->table('permissions')->select('id')->whereIn('code', array_column($permissions, 'code'))->get()->getResultArray();
        foreach ($permissionRows as $permissionRow) {
            $permissionId = (int) ($permissionRow['id'] ?? 0);
            if ($permissionId <= 0) {
                continue;
            }
            $exists = $this->db->table('role_permissions')->where('role_id', (int) $superRole['id'])->where('permission_id', $permissionId)->countAllResults();
            if ($exists === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => (int) $superRole['id'],
                    'permission_id' => $permissionId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
