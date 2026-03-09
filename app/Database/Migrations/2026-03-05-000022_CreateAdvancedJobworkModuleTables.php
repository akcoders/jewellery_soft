<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdvancedJobworkModuleTables extends Migration
{
    public function up()
    {
        $this->createRbac();
        $this->createPartyAndWarehouseCore();
        $this->createProductionCore();
        $this->createVoucherCore();
        $this->createLedgerCore();
        $this->createFgPackingInvoiceCore();
        $this->createAuditCore();
    }

    public function down()
    {
        foreach ([
            'voucher_reversals','audit_logs','approvals','vendor_payments','customer_receipts','invoice_items','invoices',
            'purchase_invoices','grn_items','grns','packing_list_items','packing_lists','qc_checks','fg_items',
            'ledger_entries','account_balances','accounts','voucher_lines','vouchers','diamond_bag_history',
            'job_card_stages','job_card_items','product_variants','bins','warehouses','parties',
            'user_roles','role_permissions','permissions','roles'
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function createRbac(): void
    {
        $this->createTableIfMissing('roles', [
            'id' => $this->pk(),
            'name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'description' => ['type' => 'TEXT', 'null' => true],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [['name','unique']]);

        $this->createTableIfMissing('permissions', [
            'id' => $this->pk(),
            'code' => ['type' => 'VARCHAR', 'constraint' => 100],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [['code','unique']]);

        $this->createTableIfMissing('role_permissions', [
            'id' => $this->pk(),
            'role_id' => $this->idRef(),
            'permission_id' => $this->idRef(),
            'created_at' => $this->dt(),
        ], [[['role_id','permission_id'],'unique']]);

        $this->createTableIfMissing('user_roles', [
            'id' => $this->pk(),
            'user_id' => $this->idRef(),
            'role_id' => $this->idRef(),
            'created_at' => $this->dt(),
        ], [[['user_id','role_id'],'unique']]);
    }

    private function createPartyAndWarehouseCore(): void
    {
        $this->createTableIfMissing('parties', [
            'id' => $this->pk(),
            'party_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'party_code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'gstin' => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
            'address' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [['party_code','unique'], ['party_type','index']]);

        $this->createTableIfMissing('warehouses', [
            'id' => $this->pk(),
            'warehouse_code' => ['type' => 'VARCHAR', 'constraint' => 30],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'warehouse_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'address' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [['warehouse_code','unique'], ['name','unique']]);

        $this->createTableIfMissing('bins', [
            'id' => $this->pk(),
            'warehouse_id' => $this->idRef(),
            'bin_code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [[['warehouse_id','bin_code'],'unique'], ['warehouse_id','index']]);

        $this->createTableIfMissing('product_variants', [
            'id' => $this->pk(),
            'product_id' => $this->idRef(),
            'variant_code' => ['type' => 'VARCHAR', 'constraint' => 60],
            'variant_size' => ['type' => 'VARCHAR', 'constraint' => 60],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [[['product_id','variant_code'],'unique']]);

        if ($this->db->tableExists('orders')) {
            $this->addCol('orders', 'order_type', ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Fresh']);
            $this->addCol('orders', 'expected_diamond_spec', ['type' => 'TEXT', 'null' => true]);
            $this->addCol('orders', 'expected_stone_spec', ['type' => 'TEXT', 'null' => true]);
            $this->addCol('orders', 'priority_level', ['type' => 'INT', 'constraint' => 11, 'default' => 0]);
        }
    }

    private function createProductionCore(): void
    {
        $this->createTableIfMissing('job_card_items', [
            'id' => $this->pk(),
            'job_card_id' => $this->idRef(),
            'order_item_id' => $this->idRef(true),
            'design_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'variant_size' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'qty' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'due_date' => ['type' => 'DATE', 'null' => true],
            'priority' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [['job_card_id','index']]);

        $this->createTableIfMissing('job_card_stages', [
            'id' => $this->pk(),
            'job_card_id' => $this->idRef(),
            'stage_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Pending'],
            'remarks' => ['type' => 'TEXT', 'null' => true],
            'created_by' => $this->idRef(true),
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [['job_card_id','index']]);

        if ($this->db->tableExists('diamond_bags')) {
            $this->addCol('diamond_bags', 'warehouse_id', $this->idRef(true));
            $this->addCol('diamond_bags', 'bin_id', $this->idRef(true));
            $this->addCol('diamond_bags', 'shape', ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true]);
            $this->addCol('diamond_bags', 'chalni_size', ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true]);
            $this->addCol('diamond_bags', 'chalni_min', ['type' => 'DECIMAL', 'constraint' => '8,3', 'null' => true]);
            $this->addCol('diamond_bags', 'chalni_max', ['type' => 'DECIMAL', 'constraint' => '8,3', 'null' => true]);
            $this->addCol('diamond_bags', 'color', ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true]);
            $this->addCol('diamond_bags', 'clarity', ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true]);
            $this->addCol('diamond_bags', 'diamond_cut', ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true]);
            $this->addCol('diamond_bags', 'fluorescence', ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true]);
            $this->addCol('diamond_bags', 'pcs_balance', ['type' => 'DECIMAL', 'constraint' => '14,3', 'default' => 0]);
            $this->addCol('diamond_bags', 'cts_balance', ['type' => 'DECIMAL', 'constraint' => '14,3', 'default' => 0]);
        }

        $this->createTableIfMissing('diamond_bag_history', [
            'id' => $this->pk(),
            'bag_id' => $this->idRef(),
            'action_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'ref_voucher_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'from_warehouse_id' => $this->idRef(true),
            'from_bin_id' => $this->idRef(true),
            'to_warehouse_id' => $this->idRef(true),
            'to_bin_id' => $this->idRef(true),
            'pcs' => ['type' => 'DECIMAL', 'constraint' => '14,3', 'default' => 0],
            'cts' => ['type' => 'DECIMAL', 'constraint' => '14,3', 'default' => 0],
            'remarks' => ['type' => 'TEXT', 'null' => true],
            'created_by' => $this->idRef(true),
            'created_at' => $this->dt(),
        ], [['bag_id','index'], ['ref_voucher_id','index']]);
    }

    private function createVoucherCore(): void
    {
        $this->createTableIfMissing('vouchers', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'voucher_no' => ['type' => 'VARCHAR', 'constraint' => 50],
            'voucher_type' => ['type' => 'VARCHAR', 'constraint' => 40],
            'voucher_date' => ['type' => 'DATE'],
            'voucher_datetime' => ['type' => 'DATETIME'],
            'from_warehouse_id' => $this->idRef(true), 'from_bin_id' => $this->idRef(true),
            'to_warehouse_id' => $this->idRef(true), 'to_bin_id' => $this->idRef(true),
            'order_id' => $this->idRef(true), 'job_card_id' => $this->idRef(true), 'party_id' => $this->idRef(true),
            'debit_account_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'credit_account_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Posted'],
            'is_reversal' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'reversal_of_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'remarks' => ['type' => 'TEXT', 'null' => true],
            'created_by' => $this->idRef(true),
            'created_ip' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [['voucher_no','unique'], ['voucher_type','index'], ['order_id','index']]);

        $this->createTableIfMissing('voucher_lines', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'voucher_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'line_no' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'item_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'item_key' => ['type' => 'VARCHAR', 'constraint' => 160],
            'material_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'bag_id' => $this->idRef(true),
            'tag_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'gold_purity_id' => $this->idRef(true),
            'shape' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'chalni_size' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'color' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'clarity' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'stone_type' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'qty_pcs' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'qty_cts' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'qty_weight' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'fine_gold' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'rate' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'remarks' => ['type' => 'TEXT', 'null' => true],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [[['voucher_id','line_no'],'unique'], ['voucher_id','index'], ['item_key','index']]);

        if ($this->db->tableExists('inventory_balances')) {
            $this->addCol('inventory_balances', 'item_key', ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true]);
            $this->addCol('inventory_balances', 'qty_pcs', ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0]);
            $this->addCol('inventory_balances', 'qty_cts', ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0]);
            $this->addCol('inventory_balances', 'qty_weight', ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0]);
            $this->addCol('inventory_balances', 'fine_gold_qty', ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0]);
            if ($this->indexNotExists('inventory_balances', 'uniq_inventory_balance_item')) {
                $this->db->query('CREATE UNIQUE INDEX uniq_inventory_balance_item ON inventory_balances (warehouse_id, bin_id, item_type, item_key)');
            }
        }
    }

    private function createLedgerCore(): void
    {
        $this->createTableIfMissing('accounts', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'account_code' => ['type' => 'VARCHAR', 'constraint' => 60],
            'account_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'account_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'reference_table' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'reference_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [['account_code','unique'], ['account_type','index']]);

        $this->createTableIfMissing('account_balances', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'account_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'item_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'item_key' => ['type' => 'VARCHAR', 'constraint' => 160],
            'qty_pcs' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'qty_cts' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'qty_weight' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'fine_gold_qty' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [[['account_id','item_type','item_key'],'unique']]);

        $this->createTableIfMissing('ledger_entries', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'voucher_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'line_no' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'debit_account_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'credit_account_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'item_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'item_key' => ['type' => 'VARCHAR', 'constraint' => 160],
            'qty_pcs' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'qty_cts' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'qty_weight' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'fine_gold_qty' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'order_id' => $this->idRef(true), 'job_card_id' => $this->idRef(true),
            'created_at' => $this->dt(),
        ], [['voucher_id','index'], ['item_key','index']]);
    }

    private function createFgPackingInvoiceCore(): void
    {
        $this->createTableIfMissing('fg_items', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'tag_no' => ['type' => 'VARCHAR', 'constraint' => 80],
            'order_id' => $this->idRef(true), 'job_card_id' => $this->idRef(true),
            'product_id' => $this->idRef(true), 'variant_id' => $this->idRef(true),
            'qty' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'gross_wt' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'net_gold_wt' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'diamond_cts' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'stone_wt' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Available'],
            'warehouse_id' => $this->idRef(true), 'bin_id' => $this->idRef(true),
            'reserved_order_id' => $this->idRef(true),
            'created_by' => $this->idRef(true),
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [['tag_no','unique'], ['status','index']]);

        $this->createTableIfMissing('qc_checks', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'fg_item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'tag_no' => ['type' => 'VARCHAR', 'constraint' => 80],
            'qc_status' => ['type' => 'VARCHAR', 'constraint' => 20],
            'reason_code' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'remarks' => ['type' => 'TEXT', 'null' => true],
            'created_by' => $this->idRef(true),
            'created_at' => $this->dt(),
        ], [['fg_item_id','index']]);

        $this->createTableIfMissing('packing_lists', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'packing_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'packing_date' => ['type' => 'DATE'],
            'order_id' => $this->idRef(true), 'customer_id' => $this->idRef(true),
            'warehouse_id' => $this->idRef(true),
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Packed'],
            'seal_no' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => $this->idRef(true),
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [['packing_no','unique']]);

        $this->createTableIfMissing('packing_list_items', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'packing_list_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'fg_item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'tag_no' => ['type' => 'VARCHAR', 'constraint' => 80],
            'qty' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'gross_wt' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'net_gold_wt' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'diamond_cts' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'stone_wt' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'created_at' => $this->dt(),
        ], [['packing_list_id','index']]);

        $this->createTableIfMissing('grns', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'grn_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'grn_date' => ['type' => 'DATE'],
            'vendor_id' => $this->idRef(), 'warehouse_id' => $this->idRef(true), 'bin_id' => $this->idRef(true),
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Posted'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => $this->idRef(true),
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [['grn_no','unique']]);

        $this->createTableIfMissing('grn_items', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'grn_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'item_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'item_key' => ['type' => 'VARCHAR', 'constraint' => 160],
            'material_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'qty_pcs' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'qty_cts' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'qty_weight' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'rate' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'created_at' => $this->dt(),
        ], [['grn_id','index']]);

        $this->createTableIfMissing('purchase_invoices', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'invoice_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'invoice_date' => ['type' => 'DATE'],
            'vendor_id' => $this->idRef(),
            'grn_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'taxable_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'gst_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'payment_due_date' => ['type' => 'DATE', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Pending'],
            'created_by' => $this->idRef(true),
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [['invoice_no','unique'], ['vendor_id','index']]);

        $this->createTableIfMissing('invoices', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'invoice_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'invoice_date' => ['type' => 'DATE'],
            'customer_id' => $this->idRef(), 'order_id' => $this->idRef(true),
            'packing_list_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'taxable_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'gst_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Unpaid'],
            'created_by' => $this->idRef(true),
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [['invoice_no','unique'], ['customer_id','index']]);

        $this->createTableIfMissing('invoice_items', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'invoice_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'fg_item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 200],
            'qty' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 1],
            'rate' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'gst_percent' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 0],
            'gst_amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'created_at' => $this->dt(),
        ], [['invoice_id','index']]);

        $this->createTableIfMissing('customer_receipts', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'receipt_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'receipt_date' => ['type' => 'DATE'],
            'customer_id' => $this->idRef(),
            'invoice_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'payment_mode' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'reference_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => $this->idRef(true),
            'created_at' => $this->dt(),
        ], [['receipt_no','unique'], ['customer_id','index']]);

        $this->createTableIfMissing('vendor_payments', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'payment_no' => ['type' => 'VARCHAR', 'constraint' => 60],
            'payment_date' => ['type' => 'DATE'],
            'vendor_id' => $this->idRef(),
            'purchase_invoice_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
            'payment_mode' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'reference_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => $this->idRef(true),
            'created_at' => $this->dt(),
        ], [['payment_no','unique'], ['vendor_id','index']]);
    }

    private function createAuditCore(): void
    {
        $this->createTableIfMissing('approvals', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 40],
            'entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'approval_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Pending'],
            'approved_by' => $this->idRef(true),
            'remarks' => ['type' => 'TEXT', 'null' => true],
            'created_at' => $this->dt(), 'updated_at' => $this->dt(),
        ], [['status','index']]);

        $this->createTableIfMissing('audit_logs', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 40],
            'entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 30],
            'before_json' => ['type' => 'LONGTEXT', 'null' => true],
            'after_json' => ['type' => 'LONGTEXT', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_by' => $this->idRef(true),
            'created_at' => $this->dt(),
        ], [['entity_type','index']]);

        $this->createTableIfMissing('voucher_reversals', [
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'original_voucher_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'reversal_voucher_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'reason' => ['type' => 'TEXT', 'null' => true],
            'created_by' => $this->idRef(true),
            'created_at' => $this->dt(),
        ], [[['original_voucher_id','reversal_voucher_id'],'unique']]);
    }

    private function createTableIfMissing(string $table, array $fields, array $keys = []): void
    {
        if ($this->db->tableExists($table)) {
            return;
        }

        $this->forge->addField($fields);
        $this->forge->addPrimaryKey('id');
        foreach ($keys as $def) {
            $column = $def[0];
            $type = $def[1];
            if ($type === 'unique') {
                $this->forge->addUniqueKey($column);
            } elseif ($type === 'index') {
                $this->forge->addKey($column);
            }
        }
        $this->forge->createTable($table, true);
    }

    private function addCol(string $table, string $column, array $def): void
    {
        if (! $this->db->tableExists($table) || $this->db->fieldExists($column, $table)) {
            return;
        }
        $this->forge->addColumn($table, [$column => $def]);
    }

    private function indexNotExists(string $table, string $indexName): bool
    {
        $result = $this->db->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'")->getResultArray();
        return $result === [];
    }

    private function pk(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
    }

    private function idRef(bool $nullable = false): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => $nullable];
    }

    private function dt(): array
    {
        return ['type' => 'DATETIME', 'null' => true];
    }
}
