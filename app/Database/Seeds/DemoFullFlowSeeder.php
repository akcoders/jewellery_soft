<?php

namespace App\Database\Seeds;

use App\Services\PostingService;
use CodeIgniter\Database\Seeder;

class DemoFullFlowSeeder extends Seeder
{
    public function run()
    {
        $this->call(AdvancedJobworkSeeder::class);

        $db = $this->db;
        $customer = $db->table('customers')->where('name', 'Seed Demo Customer')->get()->getRowArray();
        $customerId = $customer ? (int) $customer['id'] : (int) $db->table('customers')->insert([
            'name' => 'Seed Demo Customer',
            'phone' => '9000001111',
            'is_active' => 1,
        ], true);

        $vendor = $db->table('vendors')->where('name', 'Seed Demo Vendor')->get()->getRowArray();
        $vendorId = $vendor ? (int) $vendor['id'] : (int) $db->table('vendors')->insert([
            'name' => 'Seed Demo Vendor',
            'phone' => '9000002222',
            'is_active' => 1,
        ], true);

        $karigar = $db->table('karigars')->where('name', 'Seed Demo Karigar')->get()->getRowArray();
        $karigarId = $karigar ? (int) $karigar['id'] : (int) $db->table('karigars')->insert([
            'name' => 'Seed Demo Karigar',
            'phone' => '9000003333',
            'is_active' => 1,
            'wastage_percentage' => 2.5,
        ], true);

        $store = $db->table('warehouses')->where('warehouse_code', 'STORE')->get()->getRowArray();
        $wip = $db->table('warehouses')->where('warehouse_code', 'WIP_STORE')->get()->getRowArray();
        $fg = $db->table('warehouses')->where('warehouse_code', 'FG_STORE')->get()->getRowArray();
        $storeBin = $db->table('bins')->where('warehouse_id', (int) $store['id'])->where('bin_code', 'MAIN')->get()->getRowArray();

        $purity = $db->table('gold_purities')->where('purity_code', '22K')->get()->getRowArray();
        $purityId = (int) ($purity['id'] ?? 1);

        $orderId = (int) $db->table('orders')->insert([
            'order_no' => 'SEED-ORD-' . date('YmdHis'),
            'customer_id' => $customerId,
            'due_date' => date('Y-m-d', strtotime('+7 days')),
            'priority' => 'High',
            'status' => 'Confirmed',
            'assigned_karigar_id' => $karigarId,
            'assigned_at' => date('Y-m-d H:i:s'),
            'order_type' => 'Sales',
        ], true);

        $jobCardId = (int) $db->table('job_cards')->insert([
            'job_card_no' => 'SEED-JC-' . date('YmdHis'),
            'order_id' => $orderId,
            'due_date' => date('Y-m-d', strtotime('+5 days')),
            'status' => 'Assigned',
            'priority' => 'High',
        ], true);

        $bagId = (int) $db->table('diamond_bags')->insert([
            'bag_no' => 'SEED-BAG-' . date('His'),
            'shape' => 'Round',
            'chalni_size' => '0.18-0.20',
            'color' => 'EF',
            'clarity' => 'VS',
            'pcs_balance' => 40,
            'cts_balance' => 0.800,
            'warehouse_id' => (int) $store['id'],
            'bin_id' => (int) $storeBin['id'],
        ], true);

        $posting = new PostingService($db);
        $storeAcc = $posting->ensureAccount('WAREHOUSE', 'WH-' . (int) $store['id'], 'STORE Warehouse', 'warehouses', (int) $store['id']);
        $vendorAcc = $posting->ensureAccount('VENDOR', 'VENDOR-' . $vendorId, 'Seed Vendor', 'parties', $vendorId);
        $karigarAcc = $posting->ensureAccount('KARIGAR', 'KARIGAR-' . $karigarId, 'Seed Karigar', 'parties', $karigarId);
        $qcAcc = $posting->ensureAccount('PROCESS', 'QC-HOLD', 'QC Hold');
        $fgAcc = $posting->ensureAccount('WAREHOUSE', 'WH-' . (int) $fg['id'], 'FG Warehouse', 'warehouses', (int) $fg['id']);

        $posting->postVoucher([
            'voucher_type' => 'GRN',
            'voucher_date' => date('Y-m-d'),
            'to_warehouse_id' => (int) $store['id'],
            'to_bin_id' => (int) $storeBin['id'],
            'debit_account_id' => $storeAcc,
            'credit_account_id' => $vendorAcc,
            'order_id' => $orderId,
            'job_card_id' => $jobCardId,
        ], [
            ['item_type' => 'GOLD', 'item_key' => 'GOLD-' . $purityId . '-YG-BAR', 'qty_weight' => 15, 'fine_gold' => 13.750],
            ['item_type' => 'DIAMOND_BAG', 'item_key' => 'BAG-' . $bagId, 'bag_id' => $bagId, 'qty_pcs' => 40, 'qty_cts' => 0.800],
        ]);

        $posting->postVoucher([
            'voucher_type' => 'ISSUE',
            'voucher_date' => date('Y-m-d'),
            'from_warehouse_id' => (int) $store['id'],
            'from_bin_id' => (int) $storeBin['id'],
            'debit_account_id' => $karigarAcc,
            'credit_account_id' => $storeAcc,
            'order_id' => $orderId,
            'job_card_id' => $jobCardId,
        ], [
            ['item_type' => 'GOLD', 'item_key' => 'GOLD-' . $purityId . '-YG-BAR', 'qty_weight' => 8, 'fine_gold' => 7.333],
            ['item_type' => 'DIAMOND_BAG', 'item_key' => 'BAG-' . $bagId, 'bag_id' => $bagId, 'qty_pcs' => 20, 'qty_cts' => 0.400],
        ]);

        $posting->postVoucher([
            'voucher_type' => 'RETURN',
            'voucher_date' => date('Y-m-d'),
            'to_warehouse_id' => (int) $store['id'],
            'to_bin_id' => (int) $storeBin['id'],
            'debit_account_id' => $storeAcc,
            'credit_account_id' => $karigarAcc,
            'order_id' => $orderId,
            'job_card_id' => $jobCardId,
        ], [
            ['item_type' => 'GOLD', 'item_key' => 'GOLD-' . $purityId . '-YG-BAR', 'qty_weight' => 0.8, 'fine_gold' => 0.733],
            ['item_type' => 'DIAMOND_BAG', 'item_key' => 'BAG-' . $bagId, 'bag_id' => $bagId, 'qty_pcs' => 4, 'qty_cts' => 0.08],
        ]);

        $tagNo = 'SEED-TAG-' . date('His');
        $fgId = (int) $db->table('fg_items')->insert([
            'tag_no' => $tagNo,
            'order_id' => $orderId,
            'job_card_id' => $jobCardId,
            'qty' => 1,
            'gross_wt' => 7.1,
            'net_gold_wt' => 6.7,
            'diamond_cts' => 0.32,
            'status' => 'AVAILABLE',
            'warehouse_id' => (int) $fg['id'],
        ], true);

        $db->table('qc_checks')->insert([
            'fg_item_id' => $fgId,
            'tag_no' => $tagNo,
            'qc_status' => 'PASS',
        ]);

        $packingId = (int) $db->table('packing_lists')->insert([
            'packing_no' => 'SEED-PK-' . date('His'),
            'packing_date' => date('Y-m-d'),
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'warehouse_id' => (int) $fg['id'],
            'status' => 'Packed',
        ], true);

        $db->table('packing_list_items')->insert([
            'packing_list_id' => $packingId,
            'fg_item_id' => $fgId,
            'tag_no' => $tagNo,
            'qty' => 1,
            'gross_wt' => 7.1,
            'net_gold_wt' => 6.7,
            'diamond_cts' => 0.32,
            'stone_wt' => 0,
        ]);

        $invoiceId = (int) $db->table('invoices')->insert([
            'invoice_no' => 'SEED-INV-' . date('His'),
            'invoice_date' => date('Y-m-d'),
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'packing_list_id' => $packingId,
            'taxable_amount' => 35000,
            'gst_amount' => 1050,
            'total_amount' => 36050,
            'status' => 'Unpaid',
        ], true);

        $db->table('invoice_items')->insert([
            'invoice_id' => $invoiceId,
            'fg_item_id' => $fgId,
            'description' => 'Seed demo ornament',
            'qty' => 1,
            'rate' => 35000,
            'amount' => 35000,
            'gst_percent' => 3,
            'gst_amount' => 1050,
        ]);

        $db->table('customer_receipts')->insert([
            'receipt_no' => 'SEED-RCPT-' . date('His'),
            'receipt_date' => date('Y-m-d'),
            'customer_id' => $customerId,
            'invoice_id' => $invoiceId,
            'amount' => 20000,
            'payment_mode' => 'Bank',
        ]);
    }
}
