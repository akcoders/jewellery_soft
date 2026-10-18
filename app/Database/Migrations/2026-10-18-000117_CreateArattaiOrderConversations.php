<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateArattaiOrderConversations extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('orders')) {
            if (! $this->db->fieldExists('end_customer_name', 'orders')) {
                $this->forge->addColumn('orders', [
                    'end_customer_name' => [
                        'type' => 'VARCHAR',
                        'constraint' => 180,
                        'null' => true,
                        'after' => 'customer_id',
                    ],
                ]);
            }
            if (! $this->db->fieldExists('sales_executive_name', 'orders')) {
                $this->forge->addColumn('orders', [
                    'sales_executive_name' => [
                        'type' => 'VARCHAR',
                        'constraint' => 180,
                        'null' => true,
                        'after' => 'sales_person_user_id',
                    ],
                ]);
            }
        }

        if ($this->db->tableExists('arattai_order_conversations')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'arattai_user_id' => ['type' => 'VARCHAR', 'constraint' => 80],
            'actor_admin_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'current_step' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'menu'],
            'draft_data' => ['type' => 'LONGTEXT', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'order_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'expires_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('arattai_user_id', 'uq_arattai_order_conversation_user');
        $this->forge->addKey(['status', 'expires_at'], false, false, 'idx_arattai_order_conversation_status');
        $this->forge->addKey('order_id', false, false, 'idx_arattai_order_conversation_order');
        $this->forge->createTable('arattai_order_conversations');
    }

    public function down(): void
    {
        $this->forge->dropTable('arattai_order_conversations', true);
        if (! $this->db->tableExists('orders')) {
            return;
        }
        foreach (['sales_executive_name', 'end_customer_name'] as $column) {
            if ($this->db->fieldExists($column, 'orders')) {
                $this->forge->dropColumn('orders', $column);
            }
        }
    }
}
