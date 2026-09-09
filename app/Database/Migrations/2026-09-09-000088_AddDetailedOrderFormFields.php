<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDetailedOrderFormFields extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('orders')) {
            return;
        }

        $columns = [
            'order_received_date' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'order_from',
            ],
            'contact_number' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => true,
                'after' => 'order_received_date',
            ],
            'material_category' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'Gold',
                'after' => 'contact_number',
            ],
            'certificate_requirement' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
                'after' => 'material_category',
            ],
            'additional_details' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'certificate_requirement',
            ],
            'gold_rate_block_status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'Not Fixed',
                'after' => 'additional_details',
            ],
            'gold_rate_per_gm' => [
                'type' => 'DECIMAL',
                'constraint' => '18,2',
                'null' => true,
                'after' => 'gold_rate_block_status',
            ],
            'approximate_price' => [
                'type' => 'DECIMAL',
                'constraint' => '18,2',
                'null' => true,
                'after' => 'gold_rate_per_gm',
            ],
            'advance_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '18,2',
                'default' => 0,
                'after' => 'approximate_price',
            ],
        ];

        foreach ($columns as $name => $definition) {
            if (! $this->db->fieldExists($name, 'orders')) {
                $this->forge->addColumn('orders', [$name => $definition]);
            }
        }

        $this->db->query(
            'UPDATE `orders` o
             LEFT JOIN `customers` c ON c.id = o.customer_id
             SET o.order_received_date = COALESCE(o.order_received_date, DATE(o.created_at)),
                 o.contact_number = COALESCE(NULLIF(o.contact_number, \'\'), NULLIF(c.phone, \'\')),
                 o.material_category = CASE
                    WHEN EXISTS (
                        SELECT 1 FROM `order_items` oi
                        WHERE oi.order_id = o.id AND oi.diamond_required_cts > 0
                    ) THEN \'Diamond\'
                    ELSE COALESCE(NULLIF(o.material_category, \'\'), \'Gold\')
                 END'
        );
    }

    public function down()
    {
        if (! $this->db->tableExists('orders')) {
            return;
        }

        foreach ([
            'advance_amount',
            'approximate_price',
            'gold_rate_per_gm',
            'gold_rate_block_status',
            'additional_details',
            'certificate_requirement',
            'material_category',
            'contact_number',
            'order_received_date',
        ] as $column) {
            if ($this->db->fieldExists($column, 'orders')) {
                $this->forge->dropColumn('orders', $column);
            }
        }
    }
}
