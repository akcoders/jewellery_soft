<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceProductionAccountingAndJewelleryInventory extends Migration
{
    public function up()
    {
        $this->forge->addColumn('production_ready_items', [
            'order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'karigar_id'],
            'fg_item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'order_id'],
            'image_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'stones_json'],
            'payment_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Pending', 'after' => 'status_note'],
            'payment_date' => ['type' => 'DATE', 'null' => true, 'after' => 'payment_status'],
        ]);
        $this->db->query('ALTER TABLE `production_ready_items` ADD INDEX `idx_production_ready_order` (`order_id`)');
        $this->db->query('ALTER TABLE `production_ready_items` ADD INDEX `idx_production_ready_fg` (`fg_item_id`)');

        $this->forge->addColumn('production_purchase_documents', [
            'invoice_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true, 'after' => 'invoice_no'],
            'paid_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true, 'after' => 'payment_status'],
            'payment_date' => ['type' => 'DATE', 'null' => true, 'after' => 'paid_amount'],
            'account_payment_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'payment_date'],
            'reconciliation_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'Source recorded', 'after' => 'account_payment_id'],
        ]);
        $this->db->query('ALTER TABLE `production_purchase_documents` ADD INDEX `idx_production_document_payment` (`account_payment_id`)');

        $this->forge->addColumn('fg_items', [
            'production_ready_item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'job_card_id'],
            'design_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true, 'after' => 'variant_id'],
            'purity_label' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'design_name'],
            'studded_details_json' => ['type' => 'LONGTEXT', 'null' => true, 'after' => 'stone_wt'],
            'source_image_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'studded_details_json'],
            'inventory_remarks' => ['type' => 'TEXT', 'null' => true, 'after' => 'showroom_stock_status'],
            'terminal_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'inventory_remarks'],
        ]);
        $this->db->query('ALTER TABLE `fg_items` ADD UNIQUE INDEX `uq_fg_production_ready_item` (`production_ready_item_id`)');
        $this->db->query('ALTER TABLE `fg_items` ADD INDEX `idx_fg_inventory_status` (`status`, `showroom_stock_status`)');
    }

    public function down()
    {
        foreach ([
            'production_ready_items' => ['order_id', 'fg_item_id', 'image_path', 'payment_status', 'payment_date'],
            'production_purchase_documents' => ['invoice_amount', 'paid_amount', 'payment_date', 'account_payment_id', 'reconciliation_status'],
            'fg_items' => ['production_ready_item_id', 'design_name', 'purity_label', 'studded_details_json', 'source_image_path', 'inventory_remarks', 'terminal_at'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                if ($this->db->fieldExists($column, $table)) {
                    $this->forge->dropColumn($table, $column);
                }
            }
        }
    }
}
