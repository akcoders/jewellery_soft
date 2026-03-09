<?php

namespace App\Controllers\Api;

use App\Services\PostingService;
use RuntimeException;

class DemoController extends ApiBaseController
{
    public function run()
    {
        $db = db_connect();
        $db->transException(true)->transStart();

        try {
            $warehouses = $this->ensureWarehouses($db);
            $entities = $this->ensurePartiesAndMasters($db);

            $customerId = $entities['customer_id'];
            $vendorId = $entities['vendor_id'];
            $karigarId = $entities['karigar_id'];
            $goldPurityId = $entities['gold_purity_id'];

            $orderNo = 'DEMO-ORD-' . date('YmdHis');
            $orderId = (int) $db->table('orders')->insert([
                'order_no' => $orderNo,
                'customer_id' => $customerId,
                'due_date' => date('Y-m-d', strtotime('+7 days')),
                'priority' => 'High',
                'status' => 'Confirmed',
                'order_type' => 'Sales',
                'expected_diamond_spec' => json_encode(['shape' => 'Round', 'chalni_size' => '0.18-0.20', 'color' => 'EF', 'clarity' => 'VS']),
                'expected_stone_spec' => json_encode([]),
                'priority_level' => 10,
                'assigned_karigar_id' => $karigarId,
                'assigned_at' => date('Y-m-d H:i:s'),
                'order_notes' => 'Demo flow order',
                'created_by' => (int) (session('admin_id') ?: 0),
            ], true);

            $db->table('order_items')->insert([
                'order_id' => $orderId,
                'size_label' => '16',
                'qty' => 1,
                'gold_purity_id' => $goldPurityId,
                'gold_required_gm' => 10.000,
                'diamond_required_cts' => 0.600,
                'item_description' => 'Demo ring item',
            ]);

            $jobCardId = (int) $db->table('job_cards')->insert([
                'job_card_no' => 'DEMO-JC-' . date('YmdHis'),
                'order_id' => $orderId,
                'due_date' => date('Y-m-d', strtotime('+5 days')),
                'status' => 'Assigned',
                'priority' => 'High',
                'created_by' => (int) (session('admin_id') ?: 0),
            ], true);

            $bagNo = 'DEMO-BAG-' . date('His');
            $bagId = (int) $db->table('diamond_bags')->insert([
                'bag_no' => $bagNo,
                'shape' => 'Round',
                'chalni_size' => '0.18-0.20',
                'color' => 'EF',
                'clarity' => 'VS',
                'diamond_cut' => 'EX',
                'fluorescence' => 'None',
                'pcs_balance' => 60,
                'cts_balance' => 1.200,
                'warehouse_id' => $warehouses['STORE']['id'],
                'bin_id' => $warehouses['STORE']['bin_id'],
                'created_by' => (int) (session('admin_id') ?: 0),
            ], true);

            $db->table('grns')->insert([
                'grn_no' => 'DEMO-GRN-' . date('His'),
                'grn_date' => date('Y-m-d'),
                'vendor_id' => $vendorId,
                'warehouse_id' => $warehouses['STORE']['id'],
                'bin_id' => $warehouses['STORE']['bin_id'],
                'status' => 'Posted',
                'notes' => 'Demo GRN',
                'created_by' => (int) (session('admin_id') ?: 0),
            ]);

            $posting = new PostingService($db);
            $storeAcc = $posting->ensureAccount('WAREHOUSE', 'WH-' . $warehouses['STORE']['id'], 'STORE Warehouse', 'warehouses', $warehouses['STORE']['id']);
            $vendorAcc = $posting->ensureAccount('VENDOR', 'VENDOR-' . $vendorId, 'Vendor Demo', 'parties', $vendorId);
            $karigarAcc = $posting->ensureAccount('KARIGAR', 'KARIGAR-' . $karigarId, 'Karigar Demo', 'parties', $karigarId);
            $wipAcc = $posting->ensureAccount('WAREHOUSE', 'WH-' . $warehouses['WIP_STORE']['id'], 'WIP Warehouse', 'warehouses', $warehouses['WIP_STORE']['id']);
            $fgAcc = $posting->ensureAccount('WAREHOUSE', 'WH-' . $warehouses['FG_STORE']['id'], 'FG Warehouse', 'warehouses', $warehouses['FG_STORE']['id']);
            $qcAcc = $posting->ensureAccount('PROCESS', 'QC-HOLD', 'QC Hold');
            $packedAcc = $posting->ensureAccount('DISPATCH', 'PACKED', 'Packed Account');
            $dispatchedAcc = $posting->ensureAccount('DISPATCH', 'DISPATCHED', 'Dispatched Account');

            $grnVoucher = $posting->postVoucher([
                'voucher_type' => 'GRN',
                'voucher_date' => date('Y-m-d'),
                'to_warehouse_id' => $warehouses['STORE']['id'],
                'to_bin_id' => $warehouses['STORE']['bin_id'],
                'party_id' => $vendorId,
                'debit_account_id' => $storeAcc,
                'credit_account_id' => $vendorAcc,
                'remarks' => 'Demo purchase gold + diamond bag',
                'created_by' => (int) (session('admin_id') ?: 0),
            ], [
                [
                    'item_type' => 'GOLD',
                    'item_key' => 'GOLD-' . $goldPurityId . '-YG-BAR',
                    'material_name' => 'Gold 22K YG',
                    'gold_purity_id' => $goldPurityId,
                    'qty_weight' => 20.000,
                    'fine_gold' => 18.333,
                ],
                [
                    'item_type' => 'DIAMOND_BAG',
                    'item_key' => 'BAG-' . $bagId,
                    'material_name' => 'Diamond Bag ' . $bagNo,
                    'bag_id' => $bagId,
                    'shape' => 'Round',
                    'chalni_size' => '0.18-0.20',
                    'color' => 'EF',
                    'clarity' => 'VS',
                    'qty_pcs' => 60,
                    'qty_cts' => 1.200,
                ],
            ]);

            $issueVoucher = $posting->postVoucher([
                'voucher_type' => 'ISSUE',
                'voucher_date' => date('Y-m-d'),
                'from_warehouse_id' => $warehouses['STORE']['id'],
                'from_bin_id' => $warehouses['STORE']['bin_id'],
                'order_id' => $orderId,
                'job_card_id' => $jobCardId,
                'party_id' => $karigarId,
                'debit_account_id' => $karigarAcc,
                'credit_account_id' => $storeAcc,
                'remarks' => 'Issue to karigar',
                'created_by' => (int) (session('admin_id') ?: 0),
            ], [
                ['item_type' => 'GOLD', 'item_key' => 'GOLD-' . $goldPurityId . '-YG-BAR', 'qty_weight' => 10.000, 'fine_gold' => 9.166],
                ['item_type' => 'DIAMOND_BAG', 'item_key' => 'BAG-' . $bagId, 'bag_id' => $bagId, 'qty_pcs' => 30, 'qty_cts' => 0.600],
            ]);

            $returnVoucher = $posting->postVoucher([
                'voucher_type' => 'RETURN',
                'voucher_date' => date('Y-m-d'),
                'to_warehouse_id' => $warehouses['STORE']['id'],
                'to_bin_id' => $warehouses['STORE']['bin_id'],
                'order_id' => $orderId,
                'job_card_id' => $jobCardId,
                'party_id' => $karigarId,
                'debit_account_id' => $storeAcc,
                'credit_account_id' => $karigarAcc,
                'remarks' => 'Partial return from karigar',
                'created_by' => (int) (session('admin_id') ?: 0),
            ], [
                ['item_type' => 'GOLD', 'item_key' => 'GOLD-' . $goldPurityId . '-YG-BAR', 'qty_weight' => 1.000, 'fine_gold' => 0.916],
                ['item_type' => 'DIAMOND_BAG', 'item_key' => 'BAG-' . $bagId, 'bag_id' => $bagId, 'qty_pcs' => 5, 'qty_cts' => 0.100],
            ]);

            $scrapVoucher = $posting->postVoucher([
                'voucher_type' => 'SCRAP_RETURN',
                'voucher_date' => date('Y-m-d'),
                'to_warehouse_id' => $warehouses['STORE']['id'],
                'to_bin_id' => $warehouses['STORE']['bin_id'],
                'order_id' => $orderId,
                'job_card_id' => $jobCardId,
                'party_id' => $karigarId,
                'debit_account_id' => $storeAcc,
                'credit_account_id' => $karigarAcc,
                'remarks' => 'Scrap return from karigar',
                'created_by' => (int) (session('admin_id') ?: 0),
            ], [
                ['item_type' => 'GOLD', 'item_key' => 'GOLD-' . $goldPurityId . '-YG-SCRAP', 'qty_weight' => 0.200, 'fine_gold' => 0.120],
            ]);

            $tagNo = 'DEMO-TAG-' . date('His');
            $fgItemId = (int) $db->table('fg_items')->insert([
                'tag_no' => $tagNo,
                'order_id' => $orderId,
                'job_card_id' => $jobCardId,
                'qty' => 1,
                'gross_wt' => 9.200,
                'net_gold_wt' => 8.600,
                'diamond_cts' => 0.500,
                'stone_wt' => 0,
                'status' => 'QC_HOLD',
                'warehouse_id' => $warehouses['WIP_STORE']['id'],
                'bin_id' => $warehouses['WIP_STORE']['bin_id'],
                'created_by' => (int) (session('admin_id') ?: 0),
            ], true);

            $ornReceiveVoucher = $posting->postVoucher([
                'voucher_type' => 'ORNAMENT_RECEIVE',
                'voucher_date' => date('Y-m-d'),
                'to_warehouse_id' => $warehouses['WIP_STORE']['id'],
                'to_bin_id' => $warehouses['WIP_STORE']['bin_id'],
                'order_id' => $orderId,
                'job_card_id' => $jobCardId,
                'party_id' => $karigarId,
                'debit_account_id' => $qcAcc,
                'credit_account_id' => $karigarAcc,
                'remarks' => 'Ornament receive',
                'created_by' => (int) (session('admin_id') ?: 0),
            ], [
                ['item_type' => 'FG', 'item_key' => 'TAG-' . $tagNo, 'tag_no' => $tagNo, 'qty_pcs' => 1, 'qty_weight' => 9.200, 'qty_cts' => 0.500, 'fine_gold' => 8.600],
            ]);

            $db->table('qc_checks')->insert([
                'fg_item_id' => $fgItemId,
                'tag_no' => $tagNo,
                'qc_status' => 'PASS',
                'remarks' => 'QC pass in demo',
                'created_by' => (int) (session('admin_id') ?: 0),
            ]);

            $qcPassVoucher = $posting->postVoucher([
                'voucher_type' => 'QC_PASS_MOVE',
                'voucher_date' => date('Y-m-d'),
                'from_warehouse_id' => $warehouses['WIP_STORE']['id'],
                'from_bin_id' => $warehouses['WIP_STORE']['bin_id'],
                'to_warehouse_id' => $warehouses['FG_STORE']['id'],
                'to_bin_id' => $warehouses['FG_STORE']['bin_id'],
                'order_id' => $orderId,
                'job_card_id' => $jobCardId,
                'debit_account_id' => $fgAcc,
                'credit_account_id' => $qcAcc,
                'remarks' => 'QC pass move to FG',
                'created_by' => (int) (session('admin_id') ?: 0),
            ], [
                ['item_type' => 'FG', 'item_key' => 'TAG-' . $tagNo, 'tag_no' => $tagNo, 'qty_pcs' => 1, 'qty_weight' => 9.200, 'qty_cts' => 0.500, 'fine_gold' => 8.600],
            ]);

            $db->table('fg_items')->where('id', $fgItemId)->update([
                'status' => 'AVAILABLE',
                'warehouse_id' => $warehouses['FG_STORE']['id'],
                'bin_id' => $warehouses['FG_STORE']['bin_id'],
            ]);

            $packingNo = 'DEMO-PK-' . date('His');
            $packingId = (int) $db->table('packing_lists')->insert([
                'packing_no' => $packingNo,
                'packing_date' => date('Y-m-d'),
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'warehouse_id' => $warehouses['FG_STORE']['id'],
                'status' => 'Packed',
                'created_by' => (int) (session('admin_id') ?: 0),
            ], true);

            $db->table('packing_list_items')->insert([
                'packing_list_id' => $packingId,
                'fg_item_id' => $fgItemId,
                'tag_no' => $tagNo,
                'qty' => 1,
                'gross_wt' => 9.200,
                'net_gold_wt' => 8.600,
                'diamond_cts' => 0.500,
                'stone_wt' => 0,
            ]);

            $db->table('fg_items')->where('id', $fgItemId)->update(['status' => 'PACKED']);

            $packVoucher = $posting->postVoucher([
                'voucher_type' => 'PACKED',
                'voucher_date' => date('Y-m-d'),
                'debit_account_id' => $packedAcc,
                'credit_account_id' => $fgAcc,
                'order_id' => $orderId,
                'remarks' => 'Packed item',
                'created_by' => (int) (session('admin_id') ?: 0),
            ], [
                ['item_type' => 'FG', 'item_key' => 'TAG-' . $tagNo, 'qty_pcs' => 1, 'qty_weight' => 9.200, 'qty_cts' => 0.500, 'fine_gold' => 8.600],
            ]);

            $dispatchVoucher = $posting->postVoucher([
                'voucher_type' => 'DISPATCH',
                'voucher_date' => date('Y-m-d'),
                'debit_account_id' => $dispatchedAcc,
                'credit_account_id' => $packedAcc,
                'order_id' => $orderId,
                'remarks' => 'Dispatched item',
                'created_by' => (int) (session('admin_id') ?: 0),
            ], [
                ['item_type' => 'FG', 'item_key' => 'TAG-' . $tagNo, 'qty_pcs' => 1, 'qty_weight' => 9.200, 'qty_cts' => 0.500, 'fine_gold' => 8.600],
            ]);

            $db->table('packing_lists')->where('id', $packingId)->update(['status' => 'Dispatched']);
            $db->table('fg_items')->where('id', $fgItemId)->update(['status' => 'DISPATCHED']);

            $invoiceNo = 'DEMO-INV-' . date('His');
            $invoiceId = (int) $db->table('invoices')->insert([
                'invoice_no' => $invoiceNo,
                'invoice_date' => date('Y-m-d'),
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'packing_list_id' => $packingId,
                'taxable_amount' => 50000,
                'gst_amount' => 1500,
                'total_amount' => 51500,
                'status' => 'Unpaid',
                'created_by' => (int) (session('admin_id') ?: 0),
            ], true);

            $db->table('invoice_items')->insert([
                'invoice_id' => $invoiceId,
                'fg_item_id' => $fgItemId,
                'description' => 'Custom ornament ' . $tagNo,
                'qty' => 1,
                'rate' => 50000,
                'amount' => 50000,
                'gst_percent' => 3,
                'gst_amount' => 1500,
            ]);

            $receiptId = (int) $db->table('customer_receipts')->insert([
                'receipt_no' => 'DEMO-RCPT-' . date('His'),
                'receipt_date' => date('Y-m-d'),
                'customer_id' => $customerId,
                'invoice_id' => $invoiceId,
                'amount' => 30000,
                'payment_mode' => 'Bank',
                'reference_no' => 'UTR-DEMO-' . date('His'),
                'notes' => 'Partial payment',
                'created_by' => (int) (session('admin_id') ?: 0),
            ], true);

            $db->table('orders')->where('id', $orderId)->update(['status' => 'Dispatched']);

            $balanceSnapshot = $db->query(
                "SELECT item_type, item_key, SUM(qty_weight) qty_weight, SUM(qty_cts) qty_cts, SUM(qty_pcs) qty_pcs, SUM(fine_gold_qty) fine_gold_qty FROM inventory_balances GROUP BY item_type, item_key ORDER BY item_type, item_key"
            )->getResultArray();

            $karigarOutstanding = $db->query(
                "SELECT a.account_code, a.account_name, ab.item_type, ab.item_key, ab.qty_weight, ab.qty_cts, ab.qty_pcs, ab.fine_gold_qty FROM account_balances ab JOIN accounts a ON a.id = ab.account_id WHERE a.account_code = ?",
                ['KARIGAR-' . $karigarId]
            )->getResultArray();

            $voucherTrail = [
                'grn' => $grnVoucher,
                'issue' => $issueVoucher,
                'return' => $returnVoucher,
                'scrap' => $scrapVoucher,
                'ornament_receive' => $ornReceiveVoucher,
                'qc_pass' => $qcPassVoucher,
                'packed' => $packVoucher,
                'dispatch' => $dispatchVoucher,
            ];

            $db->transComplete();

            return $this->ok([
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'job_card_id' => $jobCardId,
                'bag_id' => $bagId,
                'fg_item_id' => $fgItemId,
                'packing_list_id' => $packingId,
                'invoice_id' => $invoiceId,
                'receipt_id' => $receiptId,
                'voucher_trail' => $voucherTrail,
                'inventory_balances' => $balanceSnapshot,
                'karigar_outstanding' => $karigarOutstanding,
            ], 'Demo flow executed successfully.');
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->fail('Demo flow failed.', 500, $e->getMessage());
        }
    }

    /**
     * @return array<string,array<string,int>>
     */
    private function ensureWarehouses($db): array
    {
        $codes = ['VAULT', 'STORE', 'WIP_STORE', 'FG_STORE', 'SHOWROOM', 'BRANCH_STORE'];
        $result = [];
        foreach ($codes as $code) {
            $row = $db->table('warehouses')->where('warehouse_code', $code)->get()->getRowArray();
            if (! $row) {
                $id = (int) $db->table('warehouses')->insert([
                    'warehouse_code' => $code,
                    'name' => ucwords(strtolower(str_replace('_', ' ', $code))),
                    'warehouse_type' => $code,
                    'is_active' => 1,
                ], true);
                $row = ['id' => $id, 'warehouse_code' => $code];
            }

            $bin = $db->table('bins')->where('warehouse_id', (int) $row['id'])->where('bin_code', 'MAIN')->get()->getRowArray();
            if (! $bin) {
                $binId = (int) $db->table('bins')->insert([
                    'warehouse_id' => (int) $row['id'],
                    'bin_code' => 'MAIN',
                    'name' => 'Main Bin',
                    'is_active' => 1,
                ], true);
            } else {
                $binId = (int) $bin['id'];
            }

            $result[$code] = ['id' => (int) $row['id'], 'bin_id' => $binId];
        }

        return $result;
    }

    /**
     * @return array<string,int>
     */
    private function ensurePartiesAndMasters($db): array
    {
        $customer = $db->table('customers')->where('name', 'Demo Customer')->get()->getRowArray();
        $customerId = $customer ? (int) $customer['id'] : (int) $db->table('customers')->insert([
            'customer_code' => 'DEMOCUST-' . date('His'),
            'name' => 'Demo Customer',
            'phone' => '9990001111',
            'email' => 'demo.customer@example.com',
            'gstin' => '27ABCDE1234F1Z5',
            'is_active' => 1,
        ], true);

        $vendor = $db->table('vendors')->where('name', 'Demo Vendor')->get()->getRowArray();
        $vendorId = $vendor ? (int) $vendor['id'] : (int) $db->table('vendors')->insert([
            'name' => 'Demo Vendor',
            'contact_person' => 'Vendor Rep',
            'phone' => '9990002222',
            'email' => 'demo.vendor@example.com',
            'gstin' => '27ABCDE1234F1Z6',
            'is_active' => 1,
        ], true);

        $karigar = $db->table('karigars')->where('name', 'Demo Karigar')->get()->getRowArray();
        $karigarId = $karigar ? (int) $karigar['id'] : (int) $db->table('karigars')->insert([
            'name' => 'Demo Karigar',
            'phone' => '9990003333',
            'city' => 'Surat',
            'department' => 'Setting',
            'wastage_percentage' => 2.500,
            'is_active' => 1,
        ], true);

        foreach ([
            ['CUSTOMER', 'CUST-' . $customerId, 'Demo Customer', $customerId],
            ['VENDOR', 'VENDOR-' . $vendorId, 'Demo Vendor', $vendorId],
            ['KARIGAR', 'KARIGAR-' . $karigarId, 'Demo Karigar', $karigarId],
        ] as $p) {
            $exists = $db->table('parties')->where('party_code', $p[1])->get()->getRowArray();
            if (! $exists) {
                $db->table('parties')->insert([
                    'party_type' => $p[0],
                    'party_code' => $p[1],
                    'name' => $p[2],
                    'is_active' => 1,
                ]);
            }
        }

        $purity = $db->table('gold_purities')->where('purity_code', '22K')->get()->getRowArray();
        $goldPurityId = $purity ? (int) $purity['id'] : (int) $db->table('gold_purities')->insert([
            'purity_code' => '22K',
            'purity_percent' => 91.666,
            'color_name' => 'YG',
            'is_active' => 1,
        ], true);

        return [
            'customer_id' => $customerId,
            'vendor_id' => $vendorId,
            'karigar_id' => $karigarId,
            'gold_purity_id' => $goldPurityId,
        ];
    }
}
