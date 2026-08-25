<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class LinkReceivedStoneInventoryConsumption extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('order_receive_details')
            && ! $this->db->fieldExists('stone_inventory_item_id', 'order_receive_details')) {
            $this->forge->addColumn('order_receive_details', [
                'stone_inventory_item_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'component_name',
                ],
            ]);
            $this->db->query(
                'ALTER TABLE `order_receive_details` ADD INDEX `idx_receive_detail_stone_item` (`stone_inventory_item_id`)'
            );
        }

        if ($this->db->tableExists('stone_inventory_issue_headers')
            && ! $this->db->fieldExists('receive_movement_id', 'stone_inventory_issue_headers')) {
            $this->forge->addColumn('stone_inventory_issue_headers', [
                'receive_movement_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'order_id',
                ],
            ]);
            $this->db->query(
                'ALTER TABLE `stone_inventory_issue_headers` ADD UNIQUE INDEX `uq_stone_issue_receive_movement` (`receive_movement_id`)'
            );
        }
    }

    public function down()
    {
        if ($this->db->tableExists('stone_inventory_issue_headers')
            && $this->db->fieldExists('receive_movement_id', 'stone_inventory_issue_headers')) {
            $this->forge->dropKey('stone_inventory_issue_headers', 'uq_stone_issue_receive_movement');
            $this->forge->dropColumn('stone_inventory_issue_headers', 'receive_movement_id');
        }

        if ($this->db->tableExists('order_receive_details')
            && $this->db->fieldExists('stone_inventory_item_id', 'order_receive_details')) {
            $this->forge->dropKey('order_receive_details', 'idx_receive_detail_stone_item');
            $this->forge->dropColumn('order_receive_details', 'stone_inventory_item_id');
        }
    }
}
