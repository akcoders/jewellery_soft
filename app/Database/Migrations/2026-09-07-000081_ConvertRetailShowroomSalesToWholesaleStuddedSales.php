<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ConvertRetailShowroomSalesToWholesaleStuddedSales extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('showroom_sales') || ! $this->db->tableExists('showroom_sale_items')) {
            return;
        }

        if ($this->db->fieldExists('showroom_id', 'showroom_sales')) {
            $this->forge->modifyColumn('showroom_sales', [
                'showroom_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
            ]);
        }

        $this->addMissingColumns('showroom_sales', [
            'packing_list_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'invoice_id'],
            'gst_master_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'packing_list_id'],
            'tax_breakup_json' => ['type' => 'LONGTEXT', 'null' => true, 'after' => 'gst_master_id'],
            'hsn_sac' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => '711319', 'after' => 'tax_breakup_json'],
            'total_gold_weight' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0, 'after' => 'total_qty'],
            'total_diamond_weight' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0, 'after' => 'total_gold_weight'],
            'total_stone_weight' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0, 'after' => 'total_diamond_weight'],
            'gold_rate' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0, 'after' => 'total_stone_weight'],
            'gold_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0, 'after' => 'gold_rate'],
            'diamond_rate' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0, 'after' => 'gold_amount'],
            'diamond_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0, 'after' => 'diamond_rate'],
            'stone_rate' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0, 'after' => 'diamond_amount'],
            'stone_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0, 'after' => 'stone_rate'],
            'other_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0, 'after' => 'stone_amount'],
            'round_off_amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'gst_amount'],
        ]);

        $this->addMissingColumns('showroom_sale_items', [
            'order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'fg_item_id'],
            'image_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'description'],
            'gold_rate' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0, 'after' => 'stone_wt'],
            'gold_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0, 'after' => 'gold_rate'],
            'diamond_rate' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0, 'after' => 'gold_amount'],
            'diamond_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0, 'after' => 'diamond_rate'],
            'stone_rate' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0, 'after' => 'diamond_amount'],
            'stone_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0, 'after' => 'stone_rate'],
            'other_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0, 'after' => 'stone_amount'],
        ]);

        $this->updatePermissions();
    }

    public function down()
    {
        if ($this->db->tableExists('showroom_sale_items')) {
            foreach ([
                'order_id', 'image_path', 'gold_rate', 'gold_amount', 'diamond_rate', 'diamond_amount',
                'stone_rate', 'stone_amount', 'other_amount',
            ] as $column) {
                if ($this->db->fieldExists($column, 'showroom_sale_items')) {
                    $this->forge->dropColumn('showroom_sale_items', $column);
                }
            }
        }

        if ($this->db->tableExists('showroom_sales')) {
            foreach ([
                'packing_list_id', 'gst_master_id', 'tax_breakup_json', 'hsn_sac', 'total_gold_weight',
                'total_diamond_weight', 'total_stone_weight', 'gold_rate', 'gold_amount', 'diamond_rate',
                'diamond_amount', 'stone_rate', 'stone_amount', 'other_amount', 'round_off_amount',
            ] as $column) {
                if ($this->db->fieldExists($column, 'showroom_sales')) {
                    $this->forge->dropColumn('showroom_sales', $column);
                }
            }
        }
    }

    /** @param array<string,array<string,mixed>> $fields */
    private function addMissingColumns(string $table, array $fields): void
    {
        foreach ($fields as $name => $definition) {
            if (! $this->db->fieldExists($name, $table)) {
                $this->forge->addColumn($table, [$name => $definition]);
            }
        }
    }

    private function updatePermissions(): void
    {
        if (! $this->db->tableExists('permissions')) {
            return;
        }

        $this->db->table('permissions')->where('code', 'showroom.sales.read')->update([
            'name' => 'View Studded Jewellery Sales',
            'module_group' => 'Studded Jewellery',
            'description' => 'View wholesale studded jewellery sale bills and documents',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('permissions')->where('code', 'showroom.sales.manage')->update([
            'name' => 'Manage Studded Jewellery Sales',
            'module_group' => 'Studded Jewellery',
            'description' => 'Create wholesale sale bills from finished jewellery inventory',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('permissions')->where('code', 'showroom.stock.read')->update([
            'name' => 'View Jewellery Inventory',
            'module_group' => 'Studded Jewellery',
            'description' => 'View completed jewellery inventory and movement history',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('permissions')->where('code', 'showroom.stock.manage')->update([
            'name' => 'Manage Jewellery Inventory',
            'module_group' => 'Studded Jewellery',
            'description' => 'Manage completed jewellery inventory status',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('permissions')
            ->whereIn('code', ['showroom.masters.read', 'showroom.masters.manage', 'showroom.reservations.manage'])
            ->update([
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
