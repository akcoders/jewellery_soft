<?php

namespace App\Database\Migrations;

use App\Services\ReadyOrderWorkbookImportService;
use CodeIgniter\Database\Migration;

class ImportCompletedPackingListOrders extends Migration
{
    public function up()
    {
        $this->addDesignProductionFields();

        (new ReadyOrderWorkbookImportService($this->db))->import(
            ROOTPATH . 'anuj/PL-2026-2027 order ready.xlsx'
        );
    }

    /**
     * The imported rows are live accounting and inventory history. This migration is
     * intentionally irreversible so a rollback cannot silently restore karigar balances.
     */
    public function down()
    {
    }

    private function addDesignProductionFields(): void
    {
        if (! $this->db->tableExists('design_masters')) {
            return;
        }

        $columns = [
            'subcategory' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'category'],
            'source_order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'image_path'],
            'source_order_item_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'source_order_id'],
            'source_karigar_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'source_order_item_id'],
            'purity_label' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'source_karigar_id'],
            'gross_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0, 'after' => 'purity_label'],
            'net_gold_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0, 'after' => 'gross_weight_gm'],
            'pure_gold_weight_gm' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0, 'after' => 'net_gold_weight_gm'],
            'diamond_weight_cts' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0, 'after' => 'pure_gold_weight_gm'],
            'stone_weight_cts' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0, 'after' => 'diamond_weight_cts'],
            'studded_details_json' => ['type' => 'LONGTEXT', 'null' => true, 'after' => 'stone_weight_cts'],
            'source_image_sha256' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true, 'after' => 'studded_details_json'],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'source_image_sha256'],
        ];

        foreach ($columns as $name => $definition) {
            if (! $this->db->fieldExists($name, 'design_masters')) {
                $this->forge->addColumn('design_masters', [$name => $definition]);
            }
        }

        $indexes = array_column($this->db->getIndexData('design_masters'), 'fields', 'name');
        if (! isset($indexes['idx_design_subcategory'])) {
            $this->db->query('ALTER TABLE `design_masters` ADD INDEX `idx_design_subcategory` (`subcategory`)');
        }
        if (! isset($indexes['idx_design_source_order'])) {
            $this->db->query('ALTER TABLE `design_masters` ADD INDEX `idx_design_source_order` (`source_order_id`)');
        }
        if (! isset($indexes['idx_design_image_hash'])) {
            $this->db->query('ALTER TABLE `design_masters` ADD INDEX `idx_design_image_hash` (`source_image_sha256`)');
        }
    }
}
