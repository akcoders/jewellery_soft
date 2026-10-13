<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOrderFamilyFollowupOptions extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('orders') && ! $this->db->fieldExists('parent_order_id', 'orders')) {
            $this->forge->addColumn('orders', [
                'parent_order_id' => [
                    'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true,
                    'after' => 'order_no',
                ],
            ]);
            $this->db->query('ALTER TABLE `orders` ADD INDEX `idx_orders_parent_order` (`parent_order_id`)');
        }

        if ($this->db->tableExists('order_followups')) {
            $fields = [];
            if (! $this->db->fieldExists('followup_scope', 'order_followups')) {
                $fields['followup_scope'] = [
                    'type' => 'VARCHAR', 'constraint' => 20, 'default' => 'order', 'after' => 'order_id',
                ];
            }
            if (! $this->db->fieldExists('points_enabled', 'order_followups')) {
                $fields['points_enabled'] = [
                    'type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 1,
                    'after' => 'approval_status',
                ];
            }
            if ($fields !== []) {
                $this->forge->addColumn('order_followups', $fields);
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('order_followups')) {
            foreach (['points_enabled', 'followup_scope'] as $column) {
                if ($this->db->fieldExists($column, 'order_followups')) $this->forge->dropColumn('order_followups', $column);
            }
        }
        if ($this->db->tableExists('orders') && $this->db->fieldExists('parent_order_id', 'orders')) {
            $indexes = $this->db->getIndexData('orders');
            if (isset($indexes['idx_orders_parent_order'])) $this->forge->dropKey('orders', 'idx_orders_parent_order');
            $this->forge->dropColumn('orders', 'parent_order_id');
        }
    }
}
