<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPurchaseInvoiceFields extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('purchases')) {
            return;
        }

        $columns = [
            'purchase_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'purchase_no',
            ],
            'invoice_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
                'after'      => 'purchase_date',
            ],
            'invoice_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'default'    => 0.00,
                'after'      => 'invoice_no',
            ],
            'payment_due_date' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'invoice_amount',
            ],
            'payment_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'Pending',
                'after'      => 'payment_due_date',
            ],
        ];

        foreach ($columns as $name => $def) {
            if (! $this->db->fieldExists($name, 'purchases')) {
                $this->forge->addColumn('purchases', [$name => $def]);
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('purchases')) {
            return;
        }

        $columns = [
            'payment_status',
            'payment_due_date',
            'invoice_amount',
            'invoice_no',
            'purchase_type',
        ];

        foreach ($columns as $column) {
            if ($this->db->fieldExists($column, 'purchases')) {
                $this->forge->dropColumn('purchases', $column);
            }
        }
    }
}
