<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCustomerOrderPortal extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('customer_users')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'customer_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => 150],
                'mobile' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'email' => ['type' => 'VARCHAR', 'constraint' => 191],
                'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
                'role' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'sales_person'],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'last_login_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('email');
            $this->forge->addKey(['customer_id', 'role', 'is_active']);
            $this->forge->createTable('customer_users');
        }

        if (! $this->db->fieldExists('sales_person_user_id', 'orders')) {
            $this->forge->addColumn('orders', [
                'sales_person_user_id' => [
                    'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true,
                    'after' => 'customer_id',
                ],
            ]);
            $this->db->query('ALTER TABLE `orders` ADD INDEX `idx_orders_sales_person` (`sales_person_user_id`)');
        }

        if (! $this->db->fieldExists('order_design_type', 'orders')) {
            $this->forge->addColumn('orders', [
                'order_design_type' => [
                    'type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Fresh',
                    'after' => 'order_type',
                ],
            ]);
            $this->db->query('ALTER TABLE `orders` ADD INDEX `idx_orders_design_type` (`order_design_type`)');
        }
    }

    public function down()
    {
        // Customer credentials and order ownership are live records and are retained.
    }
}
