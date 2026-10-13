<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class LinkDiamondBagsToInventoryLocations extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('diamond_bags') || ! $this->db->tableExists('inventory_locations')) {
            return;
        }
        if (! $this->db->fieldExists('inventory_location_id', 'diamond_bags')) {
            $this->forge->addColumn('diamond_bags', [
                'inventory_location_id' => [
                    'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true,
                    'after' => 'bin_id',
                ],
            ]);
        }
        if ($this->db->tableExists('warehouses')) {
            $this->db->query(
                'UPDATE diamond_bags b '
                . 'INNER JOIN warehouses w ON w.id = b.warehouse_id '
                . 'INNER JOIN inventory_locations l ON LOWER(TRIM(l.name)) = LOWER(TRIM(w.name)) '
                . 'SET b.inventory_location_id = l.id '
                . 'WHERE b.inventory_location_id IS NULL'
            );
        }
        $indexes = $this->db->getIndexData('diamond_bags');
        if (! isset($indexes['idx_diamond_bag_inventory_location'])) {
            $this->db->query('ALTER TABLE `diamond_bags` ADD INDEX `idx_diamond_bag_inventory_location` (`inventory_location_id`)');
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('diamond_bags') || ! $this->db->fieldExists('inventory_location_id', 'diamond_bags')) {
            return;
        }
        $indexes = $this->db->getIndexData('diamond_bags');
        if (isset($indexes['idx_diamond_bag_inventory_location'])) {
            $this->forge->dropKey('diamond_bags', 'idx_diamond_bag_inventory_location');
        }
        $this->forge->dropColumn('diamond_bags', 'inventory_location_id');
    }
}
