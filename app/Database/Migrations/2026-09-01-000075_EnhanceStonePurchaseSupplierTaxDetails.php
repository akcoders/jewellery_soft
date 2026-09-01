<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceStonePurchaseSupplierTaxDetails extends Migration
{
    public function up()
    {
        $table = 'stone_inventory_purchase_headers';
        if (! $this->db->tableExists($table)) {
            return;
        }

        $columns = [];
        if (! $this->db->fieldExists('supplier_address', $table)) {
            $columns['supplier_address'] = ['type' => 'TEXT', 'null' => true, 'after' => 'supplier_name'];
        }
        if (! $this->db->fieldExists('supplier_gstin', $table)) {
            $columns['supplier_gstin'] = ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true, 'after' => 'supplier_address'];
        }
        if (! $this->db->fieldExists('supplier_phone', $table)) {
            $columns['supplier_phone'] = ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'supplier_gstin'];
        }
        if (! $this->db->fieldExists('supplier_email', $table)) {
            $columns['supplier_email'] = ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'supplier_phone'];
        }
        if (! $this->db->fieldExists('taxable_amount', $table)) {
            $columns['taxable_amount'] = ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'tax_percentage'];
        }
        if (! $this->db->fieldExists('gst_amount', $table)) {
            $columns['gst_amount'] = ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'taxable_amount'];
        }

        if ($columns !== []) {
            $this->forge->addColumn($table, $columns);
        }

        $this->db->query(
            "UPDATE stone_inventory_purchase_headers ph
             LEFT JOIN vendors v ON v.id = ph.vendor_id
             LEFT JOIN (
                 SELECT purchase_id, COALESCE(SUM(line_value), 0) AS subtotal
                 FROM stone_inventory_purchase_lines
                 GROUP BY purchase_id
             ) totals ON totals.purchase_id = ph.id
             SET ph.supplier_address = COALESCE(ph.supplier_address, v.address),
                 ph.supplier_gstin = COALESCE(ph.supplier_gstin, v.gstin),
                 ph.supplier_phone = COALESCE(ph.supplier_phone, v.phone),
                 ph.supplier_email = COALESCE(ph.supplier_email, v.email),
                 ph.taxable_amount = COALESCE(totals.subtotal, 0),
                 ph.gst_amount = ROUND(COALESCE(totals.subtotal, 0) * COALESCE(ph.tax_percentage, 0) / 100, 2)"
        );
    }

    public function down()
    {
        $table = 'stone_inventory_purchase_headers';
        if (! $this->db->tableExists($table)) {
            return;
        }

        foreach (['gst_amount', 'taxable_amount', 'supplier_email', 'supplier_phone', 'supplier_gstin', 'supplier_address'] as $column) {
            if ($this->db->fieldExists($column, $table)) {
                $this->forge->dropColumn($table, $column);
            }
        }
    }
}
