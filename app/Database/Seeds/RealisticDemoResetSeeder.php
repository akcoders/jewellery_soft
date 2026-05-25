<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RealisticDemoResetSeeder extends Seeder
{
    /** @var array<string,int> */
    private array $warehouses = [];

    /** @var array<string,int> */
    private array $bins = [];

    /** @var array<string,int> */
    private array $purities = [];

    /** @var array<string,int> */
    private array $goldItems = [];

    /** @var array<string,int> */
    private array $diamondItems = [];

    /** @var array<string,int> */
    private array $stoneItems = [];

    /** @var list<int> */
    private array $vendors = [];

    /** @var list<int> */
    private array $karigars = [];

    /** @var list<int> */
    private array $customers = [];

    /** @var list<int> */
    private array $designs = [];

    public function run()
    {
        $this->resetDemoTables();
        $this->seedFoundation();
        $this->seedMasters();
        $this->seedPurchasesAndVendorPayments();
        $this->seedOrders();
    }

    private function resetDemoTables(): void
    {
        $tables = [
            'voucher_reversals',
            'voucher_lines',
            'vouchers',
            'ledger_entries',
            'account_balances',
            'accounts',
            'account_payments',
            'purchase_bill_payments',
            'vendor_payments',
            'labour_bill_payments',
            'labour_bills',
            'customer_receipts',
            'invoice_items',
            'invoices',
            'showroom_sale_items',
            'showroom_sales',
            'showroom_fg_movements',
            'showroom_reservations',
            'packing_list_items',
            'packing_lists',
            'delivery_challans',
            'qc_checks',
            'fg_items',
            'order_receive_details',
            'order_receive_summaries',
            'order_material_movements',
            'stone_issues',
            'diamond_issues',
            'stone_inventory_return_lines',
            'stone_inventory_return_headers',
            'stone_inventory_issue_lines',
            'stone_inventory_issue_headers',
            'gold_inventory_return_lines',
            'gold_inventory_return_headers',
            'gold_inventory_issue_lines',
            'gold_inventory_issue_headers',
            'return_lines',
            'return_headers',
            'issue_lines',
            'issue_headers',
            'job_card_timeline',
            'job_card_operations',
            'job_card_stages',
            'job_card_items',
            'job_cards',
            'order_followups',
            'order_status_history',
            'order_attachments',
            'order_items',
            'orders',
            'lead_followups',
            'lead_notes',
            'lead_images',
            'leads',
            'customer_addresses',
            'customers',
            'debit_notes',
            'credit_notes',
            'diamond_purchase_attachments',
            'stone_purchase_attachments',
            'purchase_lines',
            'purchase_headers',
            'stone_inventory_purchase_lines',
            'stone_inventory_purchase_headers',
            'gold_inventory_purchase_lines',
            'gold_inventory_purchase_headers',
            'purchase_items',
            'purchases',
            'gold_inventory_ledger_entries',
            'stone_ledger_entries',
            'diamond_ledger_entries',
            'gold_ledger_entries',
            'gold_inventory_stock',
            'stone_inventory_stock',
            'stock',
            'gold_inventory_items',
            'stone_inventory_items',
            'items',
            'diamond_bag_history',
            'diamond_bag_items',
            'diamond_bags',
            'inventory_transactions',
            'inventory_balances',
            'inventory_bins',
            'bins',
            'warehouses',
            'inventory_locations',
            'design_variants',
            'design_masters',
            'karigar_documents',
            'karigar_payment_ledgers',
            'karigars',
            'vendors',
            'lead_sources',
            'products',
            'product_categories',
            'gold_purities',
        ];

        $this->db->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->truncate();
            }
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS=1');
    }

    private function seedFoundation(): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ([
            ['STORE', 'Main Raw Material Store', 'STORE'],
            ['WIP_STORE', 'Karigar WIP Store', 'WIP'],
            ['FG_STORE', 'Finished Goods Vault', 'FG'],
            ['SHOWROOM', 'Main Retail Showroom', 'SHOWROOM'],
        ] as $row) {
            $id = $this->insertFiltered('warehouses', [
                'warehouse_code' => $row[0],
                'name' => $row[1],
                'warehouse_type' => $row[2],
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->warehouses[$row[0]] = $id;
            $this->bins[$row[0]] = $this->insertFiltered('bins', [
                'warehouse_id' => $id,
                'bin_code' => 'MAIN',
                'name' => 'Main Bin',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($this->db->tableExists('inventory_locations')) {
            foreach ($this->warehouses as $code => $warehouseId) {
                $this->insertFiltered('inventory_locations', [
                    'name' => $code,
                    'location_code' => $code,
                    'warehouse_id' => $warehouseId,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ([
            ['Walk-in', 'Walk-in showroom lead'],
            ['Instagram', 'Instagram catalogue enquiry'],
            ['Referral', 'Customer referral'],
            ['Website', 'Website form enquiry'],
        ] as $source) {
            $this->insertFiltered('lead_sources', [
                'name' => $source[0],
                'description' => $source[1],
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedMasters(): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ([
            ['22K', 91.666, 'YG'],
            ['18K', 75.000, 'YG'],
            ['18K-WG', 75.000, 'WG'],
        ] as $purity) {
            $id = $this->insertFiltered('gold_purities', [
                'purity_code' => $purity[0],
                'purity_percent' => $purity[1],
                'color_name' => $purity[2],
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->purities[$purity[0]] = $id;
            $this->goldItems[$purity[0]] = $this->insertFiltered('gold_inventory_items', [
                'gold_purity_id' => $id,
                'purity_code' => $purity[0],
                'purity_percent' => $purity[1],
                'color_name' => $purity[2],
                'form_type' => 'Bar',
                'remarks' => 'Opening demo stock',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['Natural', 'Round', '0.80', '1.20', 'EF', 'VS', 'Excellent'],
            ['Natural', 'Round', '1.20', '1.80', 'GH', 'VS-SI', 'Very Good'],
            ['Natural', 'Pear', '2.00', '3.00', 'GH', 'VS', 'Excellent'],
            ['Lab Grown', 'Round', '1.00', '1.50', 'EF', 'VVS', 'Excellent'],
        ] as $item) {
            $key = $item[1] . '-' . $item[2] . '-' . $item[4];
            $this->diamondItems[$key] = $this->insertFiltered('items', [
                'diamond_type' => $item[0],
                'shape' => $item[1],
                'chalni_from' => $item[2],
                'chalni_to' => $item[3],
                'color' => $item[4],
                'clarity' => $item[5],
                'cut' => $item[6],
                'remarks' => 'Demo diamond lot',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['Ruby 2mm Round', 'Ruby', 1150],
            ['Emerald 3x5 Oval', 'Emerald', 1800],
            ['Sapphire 2.5mm Round', 'Sapphire', 1350],
            ['CZ White 1.5mm', 'CZ', 75],
        ] as $stone) {
            $this->stoneItems[$stone[0]] = $this->insertFiltered('stone_inventory_items', [
                'product_name' => $stone[0],
                'stone_type' => $stone[1],
                'default_rate' => $stone[2],
                'remarks' => 'Demo stone stock',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['Kundan Gems Pvt Ltd', 'Mehul Shah', '9825011001', '24AABCK1111P1Z1', 'Surat Diamond Bourse, Surat'],
            ['Rajlaxmi Bullion', 'Nitin Soni', '9825011002', '24AABCR2222P1Z2', 'Zaveri Bazaar, Ahmedabad'],
            ['Prism Diamonds LLP', 'Aarav Mehta', '9825011003', '27AAEFP3333L1Z3', 'BKC Diamond Market, Mumbai'],
            ['Shree Color Stones', 'Rakesh Jain', '9825011004', '08AAQFS4444K1Z4', 'Johari Bazaar, Jaipur'],
            ['Om Casting Works', 'Dharmesh Patel', '9825011005', '24AAEFO5555M1Z5', 'Varachha, Surat'],
            ['Bright Findings Co', 'Anil Agarwal', '9825011006', '27AAEFB6666N1Z6', 'Andheri East, Mumbai'],
            ['Royal Packaging', 'Priya Doshi', '9825011007', '24AAEFR7777P1Z7', 'Ring Road, Surat'],
            ['Galaxy CAD Studio', 'Harsh Vora', '9825011008', '24AAEFG8888Q1Z8', 'Katargam, Surat'],
        ] as $vendor) {
            $this->vendors[] = $this->insertFiltered('vendors', [
                'name' => $vendor[0],
                'contact_person' => $vendor[1],
                'phone' => $vendor[2],
                'email' => strtolower(str_replace(' ', '.', $vendor[1])) . '@demo.local',
                'gstin' => $vendor[3],
                'address' => $vendor[4],
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['Maheshbhai Handmade', 'Handmade Polki, Rings', 720, 2.25],
            ['Iqbal Stone Setting', 'Diamond setting, Micro pave', 880, 1.50],
            ['Kiran Casting Unit', 'Casting and filing', 540, 1.00],
            ['Ravi Chain Works', 'Chains and bracelets', 460, 1.75],
            ['Vishal Polish House', 'Polish and rhodium', 350, 0.50],
            ['Amit Repair Bench', 'Repairs and resizing', 420, 1.25],
            ['Sanjay Meena Work', 'Meena and enamel', 690, 1.50],
            ['Bhavesh CAD CAM', 'CAD, wax and CAM', 600, 0.75],
            ['Naresh Jadau Works', 'Jadau necklaces', 950, 2.75],
            ['Paresh Finishers', 'Final finishing', 390, 0.50],
        ] as $i => $karigar) {
            $this->karigars[] = $this->insertFiltered('karigars', [
                'name' => $karigar[0],
                'phone' => '98765010' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'email' => 'karigar' . ($i + 1) . '@demo.local',
                'address' => 'Workshop lane ' . ($i + 1) . ', Surat',
                'city' => 'Surat',
                'state' => 'Gujarat',
                'joining_date' => date('Y-m-d', strtotime('-' . (300 + ($i * 20)) . ' days')),
                'department' => 'Production',
                'skills_text' => $karigar[1],
                'rate_per_gm' => $karigar[2],
                'wastage_percentage' => $karigar[3],
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['Rings', 'RNG'],
            ['Bangles', 'BNG'],
            ['Necklace Sets', 'NKS'],
            ['Earrings', 'ERG'],
            ['Pendants', 'PND'],
            ['Bracelets', 'BRC'],
        ] as $category) {
            $catId = $this->insertFiltered('product_categories', [
                'name' => $category[0],
                'description' => 'Demo ' . strtolower($category[0]),
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertFiltered('products', [
                'category_id' => $catId,
                'product_code' => $category[1] . '-STD',
                'product_name' => $category[0] . ' Standard',
                'item_type' => 'Gold',
                'unit_type' => 'pcs',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['DES-RNG-001', 'Floral Diamond Ring', 'Rings'],
            ['DES-RNG-002', 'Classic Solitaire Ring', 'Rings'],
            ['DES-BNG-001', 'Temple Work Bangle', 'Bangles'],
            ['DES-NKS-001', 'Ruby Necklace Set', 'Necklace Sets'],
            ['DES-ERG-001', 'Emerald Drop Earrings', 'Earrings'],
            ['DES-PND-001', 'Minimal Diamond Pendant', 'Pendants'],
        ] as $design) {
            $this->designs[] = $this->insertFiltered('design_masters', [
                'design_code' => $design[0],
                'name' => $design[1],
                'category' => $design[2],
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedPurchasesAndVendorPayments(): void
    {
        $now = date('Y-m-d H:i:s');
        $baseDate = strtotime('-75 days');
        $diamondIds = array_values($this->diamondItems);
        $stoneIds = array_values($this->stoneItems);
        $goldIds = array_values($this->goldItems);

        for ($i = 1; $i <= 14; $i++) {
            $vendorId = $this->vendors[$i % 4];
            $date = date('Y-m-d', strtotime('+' . ($i * 3) . ' days', $baseDate));
            $itemId = $diamondIds[$i % count($diamondIds)];
            $carat = 8 + ($i * 1.35);
            $rate = 42000 + (($i % 5) * 3500);
            $subtotal = round($carat * $rate, 2);
            $tax = round($subtotal * 0.03, 2);
            $total = $subtotal + $tax;
            $purchaseId = $this->insertFiltered('purchase_headers', [
                'purchase_date' => $date,
                'vendor_id' => $vendorId,
                'supplier_name' => $this->vendorName($vendorId),
                'invoice_no' => 'DIA/' . date('ym', strtotime($date)) . '/' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'due_date' => date('Y-m-d', strtotime($date . ' +21 days')),
                'tax_percentage' => 3,
                'invoice_total' => $total,
                'notes' => 'Assorted certified diamonds for customer orders',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertFiltered('purchase_lines', [
                'purchase_id' => $purchaseId,
                'item_id' => $itemId,
                'pcs' => 70 + ($i * 4),
                'carat' => $carat,
                'rate_per_carat' => $rate,
                'line_value' => $subtotal,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertFiltered('stock', [
                'item_id' => $itemId,
                'pcs_balance' => 500,
                'carat_balance' => 85.5,
                'avg_cost_per_carat' => $rate,
                'stock_value' => 85.5 * $rate,
                'updated_at' => $now,
            ]);
            $this->maybePayPurchase('diamond', $purchaseId, $vendorId, $date, $total, $i);
        }

        for ($i = 1; $i <= 10; $i++) {
            $vendorId = $this->vendors[3 + ($i % 3)];
            $date = date('Y-m-d', strtotime('+' . ($i * 4) . ' days', $baseDate));
            $itemId = $stoneIds[$i % count($stoneIds)];
            $qty = 120 + ($i * 12);
            $rate = 600 + (($i % 4) * 280);
            $subtotal = round($qty * $rate, 2);
            $tax = round($subtotal * 0.03, 2);
            $total = $subtotal + $tax;
            $purchaseId = $this->insertFiltered('stone_inventory_purchase_headers', [
                'purchase_date' => $date,
                'vendor_id' => $vendorId,
                'supplier_name' => $this->vendorName($vendorId),
                'invoice_no' => 'STN/' . date('ym', strtotime($date)) . '/' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'due_date' => date('Y-m-d', strtotime($date . ' +18 days')),
                'tax_percentage' => 3,
                'invoice_total' => $total,
                'notes' => 'Color stone and CZ assortment',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertFiltered('stone_inventory_purchase_lines', [
                'purchase_id' => $purchaseId,
                'item_id' => $itemId,
                'qty' => $qty,
                'rate' => $rate,
                'line_value' => $subtotal,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertFiltered('stone_inventory_stock', [
                'item_id' => $itemId,
                'qty_balance' => 700,
                'avg_rate' => $rate,
                'stock_value' => 700 * $rate,
                'updated_at' => $now,
            ]);
            $this->maybePayPurchase('stone', $purchaseId, $vendorId, $date, $total, $i);
        }

        for ($i = 1; $i <= 8; $i++) {
            $vendorId = $this->vendors[1];
            $date = date('Y-m-d', strtotime('+' . ($i * 5) . ' days', $baseDate));
            $itemId = $goldIds[$i % count($goldIds)];
            $weight = 95 + ($i * 12.5);
            $rate = 5850 + (($i % 3) * 120);
            $line = round($weight * $rate, 2);
            $purchaseId = $this->insertFiltered('gold_inventory_purchase_headers', [
                'purchase_date' => $date,
                'supplier_name' => $this->vendorName($vendorId),
                'invoice_no' => 'GLD/' . date('ym', strtotime($date)) . '/' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'location_id' => null,
                'notes' => 'Bullion purchase for manufacturing',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertFiltered('gold_inventory_purchase_lines', [
                'purchase_id' => $purchaseId,
                'item_id' => $itemId,
                'weight_gm' => $weight,
                'fine_weight_gm' => round($weight * 0.91666, 3),
                'rate_per_gm' => $rate,
                'line_value' => $line,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertFiltered('gold_inventory_stock', [
                'item_id' => $itemId,
                'weight_balance_gm' => 1150,
                'fine_balance_gm' => 1038,
                'avg_cost_per_gm' => $rate,
                'stock_value' => 1150 * $rate,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedOrders(): void
    {
        $now = date('Y-m-d H:i:s');
        $names = [
            'Aarohi Shah', 'Niyati Patel', 'Rhea Mehta', 'Kavya Desai', 'Avni Jain', 'Isha Trivedi',
            'Mira Kapoor', 'Dia Doshi', 'Prisha Vora', 'Tara Malhotra', 'Anaya Joshi', 'Sia Bhatt',
            'Reyansh Shah', 'Vivaan Patel', 'Arjun Mehta', 'Kabir Jain', 'Devansh Desai', 'Neel Vora',
            'Dhruv Trivedi', 'Ayaan Kapoor', 'Kiara Shah', 'Myra Patel', 'Anika Mehta', 'Sara Jain',
            'Ved Doshi', 'Yash Vora', 'Het Shah', 'Moksh Patel', 'Krisha Mehta', 'Jiya Desai',
            'Aditi Soni', 'Naira Jain', 'Rudra Shah', 'Vihaan Desai', 'Pari Patel', 'Esha Vora',
            'Meera Shah', 'Rashi Mehta', 'Tanvi Jain', 'Riya Patel', 'Om Shah', 'Aryan Mehta',
            'Manan Desai', 'Jay Patel', 'Vihan Jain', 'Naman Vora', 'Pihu Shah', 'Aanya Soni',
            'Shruti Desai', 'Harsh Mehta',
        ];
        $products = ['Diamond Ring', 'Engagement Ring', 'Temple Bangle', 'Ruby Necklace', 'Emerald Earrings', 'Diamond Pendant', 'Repair Resize', 'Bracelet'];
        $statuses = array_merge(array_fill(0, 18, 'Delivered'), array_fill(0, 10, 'Ready'), array_fill(0, 12, 'In Progress'), array_fill(0, 5, 'Confirmed'), array_fill(0, 5, 'Repair'));
        $diamondIds = array_values($this->diamondItems);
        $stoneIds = array_values($this->stoneItems);
        $goldIds = array_values($this->goldItems);

        for ($i = 1; $i <= 50; $i++) {
            $date = date('Y-m-d', strtotime('-' . (70 - $i) . ' days'));
            $name = $names[$i - 1];
            $leadId = $this->insertFiltered('leads', [
                'lead_no' => 'LD-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'name' => $name,
                'phone' => '9' . str_pad((string) (810000000 + $i), 9, '0', STR_PAD_LEFT),
                'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
                'source_id' => (($i % 4) + 1),
                'city' => $i % 3 === 0 ? 'Ahmedabad' : 'Surat',
                'requirement_text' => $products[$i % count($products)] . ' custom order',
                'stage' => $i % 5 === 0 ? 'Negotiation' : 'Converted',
                'status' => 'Open',
                'created_at' => $date . ' 10:00:00',
                'updated_at' => $now,
            ]);
            $customerId = $this->insertFiltered('customers', [
                'customer_code' => 'CUS-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'name' => $name,
                'phone' => '9' . str_pad((string) (810000000 + $i), 9, '0', STR_PAD_LEFT),
                'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
                'gstin' => $i % 7 === 0 ? '24ABCDE' . str_pad((string) $i, 4, '0', STR_PAD_LEFT) . 'F1Z5' : null,
                'is_active' => 1,
                'created_at' => $date . ' 10:30:00',
                'updated_at' => $now,
            ]);
            $this->customers[] = $customerId;
            $karigarId = $this->karigars[$i % count($this->karigars)];
            $status = $statuses[$i - 1];
            $isRepair = $status === 'Repair' || $i % 11 === 0;
            $orderId = $this->insertFiltered('orders', [
                'order_no' => 'ORD-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'order_type' => $isRepair ? 'Repair' : 'Sales',
                'customer_id' => $customerId,
                'lead_id' => $leadId,
                'assigned_karigar_id' => $karigarId,
                'assigned_at' => date('Y-m-d H:i:s', strtotime($date . ' +1 day')),
                'status' => $status,
                'priority' => $i % 6 === 0 ? 'High' : ($i % 4 === 0 ? 'Low' : 'Medium'),
                'due_date' => date('Y-m-d', strtotime($date . ' +18 days')),
                'order_notes' => 'Customer approved estimate and design for ' . $products[$i % count($products)],
                'repair_ornament_details' => $isRepair ? 'Old ring/bracelet received from customer' : null,
                'repair_work_details' => $isRepair ? 'Resize, polish and stone tightening' : null,
                'repair_receive_weight_gm' => $isRepair ? (6.5 + ($i % 5)) : null,
                'repair_received_at' => $isRepair ? $date : null,
                'expected_diamond_spec' => 'Round EF/VS melee as per CAD',
                'expected_stone_spec' => $i % 4 === 0 ? 'Ruby/Emerald accents' : null,
                'priority_level' => $i % 6 === 0 ? 3 : 1,
                'created_at' => $date . ' 11:00:00',
                'updated_at' => $now,
            ]);

            $goldRequired = round(4.8 + (($i % 9) * 1.15), 3);
            $diamondRequired = round(($i % 4 === 0 ? 0.18 : 0.35) + (($i % 5) * 0.08), 3);
            $itemId = $this->insertFiltered('order_items', [
                'order_id' => $orderId,
                'design_id' => $this->designs[$i % count($this->designs)],
                'gold_purity_id' => $this->purities[$i % 3 === 0 ? '18K' : '22K'],
                'item_description' => $products[$i % count($products)],
                'size_label' => $i % 3 === 0 ? 'Custom' : (string) (10 + ($i % 12)),
                'qty' => 1,
                'gold_required_gm' => $goldRequired,
                'diamond_required_cts' => $diamondRequired,
                'item_status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $jobCardId = $this->insertFiltered('job_cards', [
                'job_card_no' => 'JC-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'order_id' => $orderId,
                'order_item_id' => $itemId,
                'status' => in_array($status, ['Delivered', 'Ready'], true) ? 'Completed' : 'Assigned',
                'priority' => $i % 6 === 0 ? 'High' : 'Medium',
                'due_date' => date('Y-m-d', strtotime($date . ' +14 days')),
                'qc_status' => in_array($status, ['Delivered', 'Ready'], true) ? 'Pass' : 'Pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $issueDate = date('Y-m-d', strtotime($date . ' +2 days'));
            $receiveDate = date('Y-m-d', strtotime($date . ' +12 days'));
            $goldIssue = round($goldRequired + 0.35, 3);
            $goldReceive = round($goldRequired - 0.12, 3);
            $diamondIssue = $diamondRequired;
            $diamondReceive = round(max(0, $diamondRequired - 0.03), 3);
            $this->seedMaterialIssueReceive($orderId, $jobCardId, $karigarId, $issueDate, $receiveDate, $goldIssue, $goldReceive, $diamondIssue, $diamondReceive, $goldIds[$i % count($goldIds)], $diamondIds[$i % count($diamondIds)], $stoneIds[$i % count($stoneIds)], $status);

            if (in_array($status, ['Delivered', 'Ready', 'In Progress'], true)) {
                $labourRate = (float) (520 + (($i % 7) * 60));
                $labourAmount = round($goldReceive * $labourRate, 2);
                $otherAmount = $i % 4 === 0 ? 850 : 350;
                $billId = $this->insertFiltered('labour_bills', [
                    'bill_no' => 'LB-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'bill_date' => $receiveDate,
                    'order_id' => $orderId,
                    'karigar_id' => $karigarId,
                    'gold_weight_gm' => $goldReceive,
                    'rate_per_gm' => $labourRate,
                    'labour_amount' => $labourAmount,
                    'other_amount' => $otherAmount,
                    'total_amount' => $labourAmount + $otherAmount,
                    'due_date' => date('Y-m-d', strtotime($receiveDate . ' +10 days')),
                    'payment_status' => $i % 3 === 0 ? 'Paid' : ($i % 3 === 1 ? 'Partial' : 'Pending'),
                    'notes' => 'Labour for ' . $products[$i % count($products)],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                if ($i % 3 !== 2) {
                    $paid = $i % 3 === 0 ? $labourAmount + $otherAmount : round(($labourAmount + $otherAmount) * 0.55, 2);
                    $this->insertFiltered('labour_bill_payments', [
                        'labour_bill_id' => $billId,
                        'payment_date' => date('Y-m-d', strtotime($receiveDate . ' +3 days')),
                        'amount' => $paid,
                        'reference_no' => 'LAB-UPI-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                        'notes' => 'Demo labour settlement',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $this->insertFiltered('account_payments', [
                        'payment_no' => 'PAY-KAR-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                        'payment_date' => date('Y-m-d', strtotime($receiveDate . ' +3 days')),
                        'party_type' => 'karigar',
                        'karigar_id' => $karigarId,
                        'amount' => $paid,
                        'payment_mode' => $i % 2 === 0 ? 'UPI' : 'Bank Transfer',
                        'reference_no' => 'LAB-UPI-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                        'bill_type' => 'labour',
                        'labour_bill_id' => $billId,
                        'notes' => 'Auto seeded labour payment',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (in_array($status, ['Delivered', 'Ready'], true)) {
                $this->seedFinishedSale($i, $orderId, $jobCardId, $customerId, $receiveDate, $goldReceive, $diamondReceive, $status);
            }
        }
    }

    private function seedMaterialIssueReceive(int $orderId, int $jobCardId, int $karigarId, string $issueDate, string $receiveDate, float $goldIssue, float $goldReceive, float $diamondIssue, float $diamondReceive, int $goldItemId, int $diamondItemId, int $stoneItemId, string $status): void
    {
        $now = date('Y-m-d H:i:s');
        $issueMovementId = $this->insertFiltered('order_material_movements', [
            'order_id' => $orderId,
            'movement_type' => 'issue',
            'gold_gm' => $goldIssue,
            'diamond_cts' => $diamondIssue,
            'karigar_id' => $karigarId,
            'gross_weight_gm' => $goldIssue + ($diamondIssue / 5),
            'net_gold_weight_gm' => $goldIssue,
            'pure_gold_weight_gm' => round($goldIssue * 0.91666, 3),
            'notes' => 'Gold and diamond issued to karigar',
            'created_at' => $issueDate . ' 09:30:00',
            'updated_at' => $now,
        ]);

        $goldIssueId = $this->insertFiltered('gold_inventory_issue_headers', [
            'voucher_no' => 'GI-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT),
            'issue_date' => $issueDate,
            'order_id' => $orderId,
            'karigar_id' => $karigarId,
            'issue_to' => $this->karigarName($karigarId),
            'purpose' => 'Order Production',
            'notes' => 'Gold issue against order',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insertFiltered('gold_inventory_issue_lines', [
            'issue_id' => $goldIssueId,
            'item_id' => $goldItemId,
            'weight_gm' => $goldIssue,
            'fine_weight_gm' => round($goldIssue * 0.91666, 3),
            'rate_per_gm' => 5920,
            'line_value' => round($goldIssue * 5920, 2),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $diaIssueId = $this->insertFiltered('issue_headers', [
            'voucher_no' => 'DI-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT),
            'issue_date' => $issueDate,
            'order_id' => $orderId,
            'karigar_id' => $karigarId,
            'issue_to' => $this->karigarName($karigarId),
            'purpose' => 'Setting',
            'notes' => 'Diamond issue against order',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insertFiltered('issue_lines', [
            'issue_id' => $diaIssueId,
            'item_id' => $diamondItemId,
            'pcs' => 28,
            'carat' => $diamondIssue,
            'rate_per_carat' => 45500,
            'line_value' => round($diamondIssue * 45500, 2),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ((int) $orderId % 4 === 0) {
            $stoneIssueId = $this->insertFiltered('stone_inventory_issue_headers', [
                'voucher_no' => 'SI-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT),
                'issue_date' => $issueDate,
                'order_id' => $orderId,
                'karigar_id' => $karigarId,
                'issue_to' => $this->karigarName($karigarId),
                'purpose' => 'Color Stone Setting',
                'notes' => 'Color stone issue against order',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertFiltered('stone_inventory_issue_lines', [
                'issue_id' => $stoneIssueId,
                'item_id' => $stoneItemId,
                'qty' => 10,
                'rate' => 1150,
                'line_value' => 11500,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (in_array($status, ['Delivered', 'Ready', 'In Progress'], true)) {
            $receiveMovementId = $this->insertFiltered('order_material_movements', [
                'order_id' => $orderId,
                'movement_type' => 'receive',
                'gold_gm' => $goldReceive,
                'diamond_cts' => $diamondReceive,
                'karigar_id' => $karigarId,
                'gross_weight_gm' => $goldReceive + ($diamondReceive / 5) + 0.15,
                'net_gold_weight_gm' => $goldReceive,
                'pure_gold_weight_gm' => round($goldReceive * 0.91666, 3),
                'notes' => 'Finished/partly finished item received from karigar',
                'created_at' => $receiveDate . ' 17:00:00',
                'updated_at' => $now,
            ]);
            $this->insertFiltered('order_receive_summaries', [
                'movement_id' => $receiveMovementId,
                'order_id' => $orderId,
                'gross_weight_gm' => $goldReceive + ($diamondReceive / 5) + 0.15,
                'net_gold_weight_gm' => $goldReceive,
                'pure_gold_weight_gm' => round($goldReceive * 0.91666, 3),
                'diamond_weight_cts' => $diamondReceive,
                'diamond_weight_gm' => round($diamondReceive / 5, 3),
                'labour_rate_per_gm' => 650,
                'labour_amount' => round($goldReceive * 650, 2),
                'total_valuation' => round(($goldReceive * 5920) + ($diamondReceive * 45500) + ($goldReceive * 650), 2),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertFiltered('return_headers', [
                'voucher_no' => 'DR-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT),
                'return_date' => $receiveDate,
                'order_id' => $orderId,
                'issue_id' => $diaIssueId,
                'karigar_id' => $karigarId,
                'return_from' => $this->karigarName($karigarId),
                'purpose' => 'Unused diamond return',
                'notes' => 'Small balance diamonds returned',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertFiltered('gold_inventory_return_headers', [
                'voucher_no' => 'GR-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT),
                'return_date' => $receiveDate,
                'order_id' => $orderId,
                'karigar_id' => $karigarId,
                'return_from' => $this->karigarName($karigarId),
                'purpose' => 'Scrap return',
                'notes' => 'Gold scrap returned after production',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedFinishedSale(int $i, int $orderId, int $jobCardId, int $customerId, string $receiveDate, float $goldReceive, float $diamondReceive, string $status): void
    {
        $now = date('Y-m-d H:i:s');
        $tagNo = 'TAG-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $saleDate = date('Y-m-d', strtotime($receiveDate . ' +2 days'));
        $fgId = $this->insertFiltered('fg_items', [
            'tag_no' => $tagNo,
            'order_id' => $orderId,
            'job_card_id' => $jobCardId,
            'qty' => 1,
            'gross_wt' => round($goldReceive + ($diamondReceive / 5) + 0.2, 3),
            'net_gold_wt' => $goldReceive,
            'diamond_cts' => $diamondReceive,
            'stone_wt' => $i % 4 === 0 ? 0.45 : 0,
            'status' => $status === 'Delivered' ? 'SOLD' : 'AVAILABLE',
            'warehouse_id' => $this->warehouses['FG_STORE'] ?? null,
            'bin_id' => $this->bins['FG_STORE'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insertFiltered('qc_checks', [
            'fg_item_id' => $fgId,
            'tag_no' => $tagNo,
            'qc_status' => 'PASS',
            'remarks' => 'QC passed in demo flow',
            'created_at' => $now,
        ]);
        $packingId = $this->insertFiltered('packing_lists', [
            'packing_no' => 'PK-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'packing_date' => $saleDate,
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'warehouse_id' => $this->warehouses['FG_STORE'] ?? null,
            'status' => 'Packed',
            'seal_no' => 'SEAL' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insertFiltered('packing_list_items', [
            'packing_list_id' => $packingId,
            'fg_item_id' => $fgId,
            'tag_no' => $tagNo,
            'qty' => 1,
            'gross_wt' => round($goldReceive + ($diamondReceive / 5) + 0.2, 3),
            'net_gold_wt' => $goldReceive,
            'diamond_cts' => $diamondReceive,
            'stone_wt' => $i % 4 === 0 ? 0.45 : 0,
            'created_at' => $now,
        ]);

        $taxable = round(($goldReceive * 6800) + ($diamondReceive * 62000) + 4500 + (($i % 4 === 0) ? 15000 : 0), 2);
        $gst = round($taxable * 0.03, 2);
        $total = $taxable + $gst;
        $invoiceId = $this->insertFiltered('invoices', [
            'invoice_no' => 'INV-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'invoice_date' => $saleDate,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'packing_list_id' => $packingId,
            'taxable_amount' => $taxable,
            'gst_amount' => $gst,
            'total_amount' => $total,
            'status' => $i % 4 === 0 ? 'Partial' : 'Paid',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insertFiltered('invoice_items', [
            'invoice_id' => $invoiceId,
            'fg_item_id' => $fgId,
            'description' => 'Custom jewellery item ' . $tagNo,
            'qty' => 1,
            'rate' => $taxable,
            'amount' => $taxable,
            'gst_percent' => 3,
            'gst_amount' => $gst,
            'created_at' => $now,
        ]);
        $receiptAmount = $i % 4 === 0 ? round($total * 0.65, 2) : $total;
        $this->insertFiltered('customer_receipts', [
            'receipt_no' => 'RCPT-2026-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'receipt_date' => date('Y-m-d', strtotime($saleDate . ' +1 day')),
            'customer_id' => $customerId,
            'invoice_id' => $invoiceId,
            'amount' => $receiptAmount,
            'payment_mode' => $i % 2 === 0 ? 'UPI' : 'Bank Transfer',
            'reference_no' => 'CUST-TXN-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
            'notes' => 'Customer receipt for demo invoice',
            'created_at' => $now,
        ]);
    }

    private function maybePayPurchase(string $sourceType, int $purchaseId, int $vendorId, string $purchaseDate, float $total, int $i): void
    {
        $now = date('Y-m-d H:i:s');
        if ($i % 4 === 0) {
            return;
        }
        $amount = $i % 4 === 1 ? round($total * 0.5, 2) : $total;
        $paymentDate = date('Y-m-d', strtotime($purchaseDate . ' +7 days'));
        $ref = 'VEN-NEFT-' . strtoupper($sourceType) . '-' . str_pad((string) $purchaseId, 5, '0', STR_PAD_LEFT);
        $this->insertFiltered('purchase_bill_payments', [
            'source_type' => $sourceType,
            'source_id' => $purchaseId,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'reference_no' => $ref,
            'notes' => 'Vendor purchase settlement',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insertFiltered('account_payments', [
            'payment_no' => 'PAY-VEN-' . strtoupper($sourceType) . '-' . str_pad((string) $purchaseId, 4, '0', STR_PAD_LEFT),
            'payment_date' => $paymentDate,
            'party_type' => 'vendor',
            'vendor_id' => $vendorId,
            'amount' => $amount,
            'payment_mode' => $i % 2 === 0 ? 'Cheque' : 'Bank Transfer',
            'reference_no' => $ref,
            'bill_type' => 'purchase',
            'bill_source_type' => $sourceType,
            'bill_source_id' => $purchaseId,
            'notes' => 'Auto seeded vendor payment',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insertFiltered('vendor_payments', [
            'payment_no' => 'VP-' . strtoupper($sourceType) . '-' . str_pad((string) $purchaseId, 4, '0', STR_PAD_LEFT),
            'payment_date' => $paymentDate,
            'vendor_id' => $vendorId,
            'amount' => $amount,
            'payment_mode' => $i % 2 === 0 ? 'Cheque' : 'Bank Transfer',
            'reference_no' => $ref,
            'notes' => 'Vendor payment mirror entry',
            'created_at' => $now,
        ]);
    }

    private function vendorName(int $vendorId): string
    {
        $row = $this->db->table('vendors')->select('name')->where('id', $vendorId)->get()->getRowArray();
        return (string) ($row['name'] ?? 'Vendor');
    }

    private function karigarName(int $karigarId): string
    {
        $row = $this->db->table('karigars')->select('name')->where('id', $karigarId)->get()->getRowArray();
        return (string) ($row['name'] ?? 'Karigar');
    }

    /**
     * @param array<string,mixed> $data
     */
    private function insertFiltered(string $table, array $data): int
    {
        if (! $this->db->tableExists($table)) {
            return 0;
        }
        $filtered = [];
        foreach ($data as $field => $value) {
            if ($this->db->fieldExists($field, $table)) {
                $filtered[$field] = $value;
            }
        }
        if ($filtered === []) {
            return 0;
        }
        if (in_array($table, ['stock', 'gold_inventory_stock', 'stone_inventory_stock'], true) && isset($filtered['item_id'])) {
            $itemId = (int) $filtered['item_id'];
            $exists = $this->db->table($table)->where('item_id', $itemId)->countAllResults() > 0;
            if ($exists) {
                $this->db->table($table)->where('item_id', $itemId)->update($filtered);
                return $itemId;
            }
        }
        $this->db->table($table)->insert($filtered);
        return (int) $this->db->insertID();
    }
}
