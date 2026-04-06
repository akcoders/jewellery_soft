-- Demo ERP seed data for 10 end-to-end order flows.
-- Safe for staging/demo use only.
-- This script uses explicit demo IDs so it can be re-run.

START TRANSACTION;

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM delivery_challans WHERE id BETWEEN 95201 AND 95208;
DELETE FROM packing_lists WHERE id BETWEEN 95101 AND 95108;

DELETE FROM stone_inventory_return_lines WHERE id BETWEEN 94351 AND 94356;
DELETE FROM stone_inventory_return_headers WHERE id BETWEEN 94301 AND 94306;
DELETE FROM stone_inventory_issue_lines WHERE id BETWEEN 94251 AND 94257;
DELETE FROM stone_inventory_issue_headers WHERE id BETWEEN 94201 AND 94206;
DELETE FROM stone_inventory_purchase_lines WHERE id BETWEEN 94151 AND 94157;
DELETE FROM stone_inventory_purchase_headers WHERE id BETWEEN 94101 AND 94106;
DELETE FROM stone_inventory_stock WHERE item_id BETWEEN 90801 AND 90804;
DELETE FROM stone_inventory_items WHERE id BETWEEN 90801 AND 90804;

DELETE FROM gold_inventory_return_lines WHERE id BETWEEN 93351 AND 93360;
DELETE FROM gold_inventory_return_headers WHERE id BETWEEN 93301 AND 93310;
DELETE FROM gold_inventory_issue_lines WHERE id BETWEEN 93251 AND 93260;
DELETE FROM gold_inventory_issue_headers WHERE id BETWEEN 93201 AND 93210;
DELETE FROM gold_inventory_purchase_lines WHERE id BETWEEN 93151 AND 93160;
DELETE FROM gold_inventory_purchase_headers WHERE id BETWEEN 93101 AND 93110;
DELETE FROM gold_inventory_stock WHERE item_id BETWEEN 90701 AND 90703;
DELETE FROM gold_inventory_items WHERE id BETWEEN 90701 AND 90703;

DELETE FROM return_lines WHERE id BETWEEN 92351 AND 92355;
DELETE FROM return_headers WHERE id BETWEEN 92301 AND 92305;
DELETE FROM issue_lines WHERE id BETWEEN 92251 AND 92257;
DELETE FROM issue_headers WHERE id BETWEEN 92201 AND 92205;
DELETE FROM purchase_lines WHERE id BETWEEN 92151 AND 92157;
DELETE FROM purchase_headers WHERE id BETWEEN 92101 AND 92105;
DELETE FROM stock WHERE item_id BETWEEN 90601 AND 90603;
DELETE FROM items WHERE id BETWEEN 90601 AND 90603;

DELETE FROM order_items WHERE id BETWEEN 91101 AND 91110;
DELETE FROM orders WHERE id BETWEEN 91001 AND 91010;

DELETE FROM vendors WHERE id BETWEEN 90301 AND 90306;
DELETE FROM inventory_locations WHERE id BETWEEN 90401 AND 90404;
DELETE FROM karigars WHERE id BETWEEN 90201 AND 90205;
DELETE FROM customers WHERE id BETWEEN 90101 AND 90110;
DELETE FROM gold_purities WHERE id BETWEEN 90501 AND 90503;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO gold_purities (id, purity_code, purity_percent, color_name, is_active, created_at, updated_at) VALUES
(90501, '22KT', 91.600, 'Yellow', 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90502, '18KT', 75.000, 'Rose', 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90503, '14KT', 58.500, 'White', 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00');

INSERT INTO customers (id, customer_code, name, phone, email, gstin, terms_text, pricing_rule_id, is_active, created_at, updated_at, deleted_at) VALUES
(90101, 'DEMO-CUST-01', 'Nisha Mehta', '9000000001', 'nisha@example.com', NULL, NULL, NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00', NULL),
(90102, 'DEMO-CUST-02', 'Rahul Soni', '9000000002', 'rahul@example.com', NULL, NULL, NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00', NULL),
(90103, 'DEMO-CUST-03', 'Pooja Shah', '9000000003', 'pooja@example.com', NULL, NULL, NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00', NULL),
(90104, 'DEMO-CUST-04', 'Ankit Jain', '9000000004', 'ankit@example.com', NULL, NULL, NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00', NULL),
(90105, 'DEMO-CUST-05', 'Ritu Kothari', '9000000005', 'ritu@example.com', NULL, NULL, NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00', NULL),
(90106, 'DEMO-CUST-06', 'Sonal Patel', '9000000006', 'sonal@example.com', NULL, NULL, NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00', NULL),
(90107, 'DEMO-CUST-07', 'Vivek Doshi', '9000000007', 'vivek@example.com', NULL, NULL, NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00', NULL),
(90108, 'DEMO-CUST-08', 'Asha Agarwal', '9000000008', 'asha@example.com', NULL, NULL, NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00', NULL),
(90109, 'DEMO-CUST-09', 'Neha Bansal', '9000000009', 'neha@example.com', NULL, NULL, NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00', NULL),
(90110, 'DEMO-CUST-10', 'Karan Malhotra', '9000000010', 'karan@example.com', NULL, NULL, NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00', NULL);

INSERT INTO karigars (id, name, phone, department, skills_text, rate_per_gm, is_active, created_at, updated_at) VALUES
(90201, 'Rakesh Sutar', '9100000001', 'Gold', 'Chain,Bangle,Necklace', 450.00, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90202, 'Imran Setter', '9100000002', 'Setting', 'Ring,Pendant,Diamond Setting', 520.00, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90203, 'Mahesh Polish', '9100000003', 'Finishing', 'Polish,Assembly,Dispatch Prep', 380.00, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90204, 'Deepak Ringkar', '9100000004', 'Rings', 'Ring,Bracelet,Custom Size', 500.00, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90205, 'Kunal Repairkar', '9100000005', 'Repair', 'Repair,Resize,Solder', 425.00, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00');

INSERT INTO inventory_locations (id, name, location_type, is_active, created_at, updated_at) VALUES
(90401, 'Main Store', 'Store', 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90402, 'Gold Vault', 'Warehouse', 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90403, 'Diamond Room', 'Warehouse', 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90404, 'Stone Rack', 'Warehouse', 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00');

INSERT INTO vendors (id, name, contact_person, phone, email, address, gstin, is_active, created_at, updated_at) VALUES
(90301, 'Gold Bullion House', 'Amit Jain', '9200000001', 'goldbullion@example.com', 'Mumbai', NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90302, 'Prism Diamonds', 'Rohit Shah', '9200000002', 'prism@example.com', 'Surat', NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90303, 'Navkar Stones', 'Suresh Mehta', '9200000003', 'navkar@example.com', 'Jaipur', NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90304, 'Raj Bullion', 'Nitin Soni', '9200000004', 'rajbullion@example.com', 'Ahmedabad', NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90305, 'Sparkle Diamond Co', 'Pankaj Doshi', '9200000005', 'sparkle@example.com', 'Surat', NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90306, 'ColorGem Traders', 'Harsh Vora', '9200000006', 'colorgem@example.com', 'Jaipur', NULL, 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00');

INSERT INTO items (id, diamond_type, shape, chalni_from, chalni_to, color, clarity, cut, remarks, created_at, updated_at) VALUES
(90601, 'VVS Melee', 'Round', '-2', '+2', 'EF', 'VVS', 'Excellent', 'Demo melee diamonds', '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90602, 'SI Melee', 'Round', '+2', '+4', 'GH', 'SI', 'Very Good', 'Demo SI melee', '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90603, 'Solitaire', 'Round', '0.30', '0.35', 'EF', 'VS1', 'Excellent', 'Demo solitaire', '2026-03-11 09:00:00', '2026-03-11 09:00:00');

INSERT INTO gold_inventory_items (id, gold_purity_id, purity_code, purity_percent, color_name, form_type, remarks, created_at, updated_at) VALUES
(90701, 90501, '22KT', 91.600, 'Yellow', 'Casting Grain', 'Demo 22KT stock', '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90702, 90502, '18KT', 75.000, 'Rose', 'Wire', 'Demo 18KT stock', '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90703, 90503, '14KT', 58.500, 'White', 'Sheet', 'Demo 14KT stock', '2026-03-11 09:00:00', '2026-03-11 09:00:00');

INSERT INTO stone_inventory_items (id, product_name, stone_type, default_rate, remarks, created_at, updated_at) VALUES
(90801, 'Ruby Round 1.8mm', 'Ruby', 850.00, 'Demo ruby stone', '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90802, 'Emerald Octagon 3x5', 'Emerald', 1450.00, 'Demo emerald stone', '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90803, 'Black Bead 2mm', 'Bead', 12.00, 'Demo black beads', '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(90804, 'CZ Round 1.5mm', 'CZ', 35.00, 'Demo CZ stone', '2026-03-11 09:00:00', '2026-03-11 09:00:00');

INSERT INTO orders (id, order_no, order_type, customer_id, lead_id, quotation_id, assigned_karigar_id, assigned_at, status, priority, due_date, order_notes, repair_ornament_details, repair_work_details, repair_receive_weight_gm, repair_received_at, cancel_reason, cancelled_at, cancelled_by, created_by, created_at, updated_at, deleted_at) VALUES
(91001, 'DEMO-ORD-26001', 'Sales', 90101, NULL, NULL, 90204, '2026-03-11 10:00:00', 'Completed', 'High', '2026-03-18', '18KT solitaire ring', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-11 10:00:00', '2026-03-17 18:00:00', NULL),
(91002, 'DEMO-ORD-26002', 'Sales', 90102, NULL, NULL, 90201, '2026-03-11 10:05:00', 'Ready', 'Urgent', '2026-03-19', 'Bridal necklace with ruby drops', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-11 10:05:00', '2026-03-16 17:00:00', NULL),
(91003, 'DEMO-ORD-26003', 'Sales', 90103, NULL, NULL, 90201, '2026-03-11 10:10:00', 'Completed', 'Medium', '2026-03-22', '22KT gents chain', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-11 10:10:00', '2026-03-17 18:00:00', NULL),
(91004, 'DEMO-ORD-26004', 'Sales', 90104, NULL, NULL, 90202, '2026-03-11 10:15:00', 'QC', 'High', '2026-03-20', '18KT emerald pendant', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-11 10:15:00', '2026-03-15 17:00:00', NULL),
(91005, 'DEMO-ORD-26005', 'Sales', 90105, NULL, NULL, 90202, '2026-03-11 10:20:00', 'Packed', 'Urgent', '2026-03-17', '14KT white gold stud earrings', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-11 10:20:00', '2026-03-14 16:00:00', NULL),
(91006, 'DEMO-ORD-26006', 'Repair', 90106, NULL, NULL, 90205, '2026-03-11 10:25:00', 'Completed', 'Medium', '2026-03-21', 'Resize old ring and reset missing CZ', 'Old customer ring', 'Resize and CZ replacement', 6.900, '2026-03-11 11:00:00', NULL, NULL, NULL, NULL, '2026-03-11 10:25:00', '2026-03-14 18:00:00', NULL),
(91007, 'DEMO-ORD-26007', 'Sales', 90107, NULL, NULL, 90201, '2026-03-11 10:30:00', 'In Production', 'High', '2026-03-24', '22KT bangle pair with ruby accents', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-11 10:30:00', '2026-03-15 14:00:00', NULL),
(91008, 'DEMO-ORD-26008', 'Sales', 90108, NULL, NULL, 90203, '2026-03-11 10:35:00', 'Dispatched', 'Medium', '2026-03-23', 'Mangalsutra pendant with black beads and diamond', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-11 10:35:00', '2026-03-16 18:30:00', NULL),
(91009, 'DEMO-ORD-26009', 'Sales', 90109, NULL, NULL, 90204, '2026-03-11 10:40:00', 'Ready', 'Urgent', '2026-03-16', 'Kids bracelet 14KT', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-11 10:40:00', '2026-03-13 17:00:00', NULL),
(91010, 'DEMO-ORD-26010', 'Sales', 90110, NULL, NULL, 90202, '2026-03-11 10:45:00', 'Completed', 'High', '2026-03-25', 'Cocktail ring with emerald and diamonds', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-11 10:45:00', '2026-03-17 19:00:00', NULL);

INSERT INTO order_items (id, order_id, design_id, variant_id, gold_purity_id, item_description, size_label, qty, gold_required_gm, diamond_required_cts, item_status, created_at, updated_at) VALUES
(91101, 91001, NULL, NULL, 90502, 'Solitaire ring size 12', '12', 1, 8.000, 0.360, 'Completed', '2026-03-11 10:00:00', '2026-03-17 18:00:00'),
(91102, 91002, NULL, NULL, 90501, '22KT bridal necklace', NULL, 1, 54.000, 1.250, 'Ready', '2026-03-11 10:05:00', '2026-03-16 17:00:00'),
(91103, 91003, NULL, NULL, 90501, 'Gents chain 24 inch', NULL, 1, 32.000, 0.000, 'Completed', '2026-03-11 10:10:00', '2026-03-17 18:00:00'),
(91104, 91004, NULL, NULL, 90502, 'Emerald halo pendant', NULL, 1, 10.500, 0.000, 'QC', '2026-03-11 10:15:00', '2026-03-15 17:00:00'),
(91105, 91005, NULL, NULL, 90503, 'Stud earrings pair', NULL, 1, 5.800, 0.220, 'Packed', '2026-03-11 10:20:00', '2026-03-14 16:00:00'),
(91106, 91006, NULL, NULL, 90502, 'Repair ring resize plus one CZ replace', '14', 1, 0.900, 0.000, 'Completed', '2026-03-11 10:25:00', '2026-03-14 18:00:00'),
(91107, 91007, NULL, NULL, 90501, 'Bangle pair', NULL, 2, 46.000, 0.000, 'In Production', '2026-03-11 10:30:00', '2026-03-15 14:00:00'),
(91108, 91008, NULL, NULL, 90502, '18KT mangalsutra pendant', NULL, 1, 14.000, 0.180, 'Dispatched', '2026-03-11 10:35:00', '2026-03-16 18:30:00'),
(91109, 91009, NULL, NULL, 90503, 'Kids bracelet 6 inch', '6', 1, 4.500, 0.000, 'Ready', '2026-03-11 10:40:00', '2026-03-13 17:00:00'),
(91110, 91010, NULL, NULL, 90502, 'Cocktail ring size 14', '14', 1, 12.800, 0.620, 'Completed', '2026-03-11 10:45:00', '2026-03-17 19:00:00');

INSERT INTO purchase_headers (id, purchase_date, vendor_id, supplier_name, invoice_no, due_date, tax_percentage, invoice_total, notes, created_at, updated_at) VALUES
(92101, '2026-03-11', 90302, 'Prism Diamonds', 'PD-001', '2026-03-18', 3.000, 34080.00, 'Diamonds for DEMO-ORD-26001', '2026-03-11 12:00:00', '2026-03-11 12:00:00'),
(92102, '2026-03-11', 90305, 'Sparkle Diamond Co', 'SD-001', '2026-03-19', 3.000, 46350.00, 'Diamonds for DEMO-ORD-26002', '2026-03-11 12:05:00', '2026-03-11 12:05:00'),
(92103, '2026-03-11', 90305, 'Sparkle Diamond Co', 'SD-002', '2026-03-17', 3.000, 9517.20, 'Diamonds for DEMO-ORD-26005', '2026-03-11 12:10:00', '2026-03-11 12:10:00'),
(92104, '2026-03-12', 90302, 'Prism Diamonds', 'PD-002', '2026-03-18', 3.000, 7879.50, 'Diamonds for DEMO-ORD-26008', '2026-03-12 12:00:00', '2026-03-12 12:00:00'),
(92105, '2026-03-12', 90305, 'Sparkle Diamond Co', 'SD-003', '2026-03-20', 3.000, 47408.75, 'Diamonds for DEMO-ORD-26010', '2026-03-12 12:05:00', '2026-03-12 12:05:00');

INSERT INTO purchase_lines (id, purchase_id, item_id, pcs, carat, rate_per_carat, line_value, created_at, updated_at) VALUES
(92151, 92101, 90603, 1.000, 0.320, 98000.00, 31360.00, '2026-03-11 12:00:00', '2026-03-11 12:00:00'),
(92152, 92101, 90601, 24.000, 0.040, 42000.00, 1680.00, '2026-03-11 12:00:00', '2026-03-11 12:00:00'),
(92153, 92102, 90602, 180.000, 1.250, 36000.00, 45000.00, '2026-03-11 12:05:00', '2026-03-11 12:05:00'),
(92154, 92103, 90601, 40.000, 0.220, 42000.00, 9240.00, '2026-03-11 12:10:00', '2026-03-11 12:10:00'),
(92155, 92104, 90601, 30.000, 0.180, 42500.00, 7650.00, '2026-03-12 12:00:00', '2026-03-12 12:00:00'),
(92156, 92105, 90601, 52.000, 0.270, 42500.00, 11475.00, '2026-03-12 12:05:00', '2026-03-12 12:05:00'),
(92157, 92105, 90603, 1.000, 0.350, 99000.00, 34650.00, '2026-03-12 12:05:00', '2026-03-12 12:05:00');

INSERT INTO issue_headers (id, voucher_no, issue_date, order_id, karigar_id, location_id, issue_to, purpose, notes, attachment_name, attachment_path, created_by, created_at, updated_at) VALUES
(92201, 'DIV-26001', '2026-03-12', 91001, 90204, 90403, 'Deepak Ringkar', 'Stone setting', 'Diamond issue for DEMO-ORD-26001', NULL, NULL, NULL, '2026-03-12 13:00:00', '2026-03-12 13:00:00'),
(92202, 'DIV-26002', '2026-03-12', 91002, 90201, 90403, 'Rakesh Sutar', 'Diamond setting', 'Diamond issue for DEMO-ORD-26002', NULL, NULL, NULL, '2026-03-12 13:05:00', '2026-03-12 13:05:00'),
(92203, 'DIV-26005', '2026-03-12', 91005, 90202, 90403, 'Imran Setter', 'Melee setting', 'Diamond issue for DEMO-ORD-26005', NULL, NULL, NULL, '2026-03-12 13:10:00', '2026-03-12 13:10:00'),
(92204, 'DIV-26008', '2026-03-13', 91008, 90203, 90403, 'Mahesh Polish', 'Diamond setting', 'Diamond issue for DEMO-ORD-26008', NULL, NULL, NULL, '2026-03-13 13:00:00', '2026-03-13 13:00:00'),
(92205, 'DIV-26010', '2026-03-13', 91010, 90202, 90403, 'Imran Setter', 'Diamond setting', 'Diamond issue for DEMO-ORD-26010', NULL, NULL, NULL, '2026-03-13 13:05:00', '2026-03-13 13:05:00');

INSERT INTO issue_lines (id, issue_id, item_id, pcs, carat, rate_per_carat, line_value, created_at, updated_at) VALUES
(92251, 92201, 90603, 1.000, 0.320, 98000.00, 31360.00, '2026-03-12 13:00:00', '2026-03-12 13:00:00'),
(92252, 92201, 90601, 24.000, 0.040, 42000.00, 1680.00, '2026-03-12 13:00:00', '2026-03-12 13:00:00'),
(92253, 92202, 90602, 180.000, 1.250, 36000.00, 45000.00, '2026-03-12 13:05:00', '2026-03-12 13:05:00'),
(92254, 92203, 90601, 40.000, 0.220, 42000.00, 9240.00, '2026-03-12 13:10:00', '2026-03-12 13:10:00'),
(92255, 92204, 90601, 30.000, 0.180, 42500.00, 7650.00, '2026-03-13 13:00:00', '2026-03-13 13:00:00'),
(92256, 92205, 90601, 52.000, 0.270, 42500.00, 11475.00, '2026-03-13 13:05:00', '2026-03-13 13:05:00'),
(92257, 92205, 90603, 1.000, 0.350, 99000.00, 34650.00, '2026-03-13 13:05:00', '2026-03-13 13:05:00');

INSERT INTO return_headers (id, voucher_no, return_date, order_id, issue_id, karigar_id, return_from, purpose, notes, attachment_name, attachment_path, created_by, created_at, updated_at) VALUES
(92301, 'DRV-26002', '2026-03-16', 91002, 92202, 90201, 'Rakesh Sutar', 'Balance melee return', 'Diamond return for DEMO-ORD-26002', NULL, NULL, NULL, '2026-03-16 14:00:00', '2026-03-16 14:00:00'),
(92302, 'DRV-26005', '2026-03-14', 91005, 92203, 90202, 'Imran Setter', 'Balance melee return', 'Diamond return for DEMO-ORD-26005', NULL, NULL, NULL, '2026-03-14 14:00:00', '2026-03-14 14:00:00'),
(92303, 'DRV-26010', '2026-03-17', 91010, 92205, 90202, 'Imran Setter', 'Balance melee return', 'Diamond return for DEMO-ORD-26010', NULL, NULL, NULL, '2026-03-17 14:00:00', '2026-03-17 14:00:00');

INSERT INTO return_lines (id, return_id, item_id, pcs, carat, rate_per_carat, line_value, created_at, updated_at) VALUES
(92351, 92301, 90602, 12.000, 0.080, 36000.00, 2880.00, '2026-03-16 14:00:00', '2026-03-16 14:00:00'),
(92352, 92302, 90601, 2.000, 0.010, 42000.00, 420.00, '2026-03-14 14:00:00', '2026-03-14 14:00:00'),
(92353, 92303, 90601, 4.000, 0.015, 42500.00, 637.50, '2026-03-17 14:00:00', '2026-03-17 14:00:00');

INSERT INTO gold_inventory_purchase_headers (id, purchase_date, supplier_name, invoice_no, location_id, notes, created_by, created_at, updated_at) VALUES
(93101, '2026-03-11', 'Raj Bullion', 'RB-001', 90402, 'Gold purchase for DEMO-ORD-26001', NULL, '2026-03-11 11:00:00', '2026-03-11 11:00:00'),
(93102, '2026-03-11', 'Gold Bullion House', 'GBH-001', 90402, 'Gold purchase for DEMO-ORD-26002', NULL, '2026-03-11 11:05:00', '2026-03-11 11:05:00'),
(93103, '2026-03-11', 'Gold Bullion House', 'GBH-002', 90402, 'Gold purchase for DEMO-ORD-26003', NULL, '2026-03-11 11:10:00', '2026-03-11 11:10:00'),
(93104, '2026-03-12', 'Raj Bullion', 'RB-002', 90402, 'Gold purchase for DEMO-ORD-26004', NULL, '2026-03-12 11:00:00', '2026-03-12 11:00:00'),
(93105, '2026-03-11', 'Gold Bullion House', 'GBH-003', 90402, 'Gold purchase for DEMO-ORD-26005', NULL, '2026-03-11 11:15:00', '2026-03-11 11:15:00'),
(93106, '2026-03-12', 'Raj Bullion', 'RB-003', 90402, 'Gold purchase for DEMO-ORD-26006', NULL, '2026-03-12 11:05:00', '2026-03-12 11:05:00'),
(93107, '2026-03-12', 'Gold Bullion House', 'GBH-004', 90402, 'Gold purchase for DEMO-ORD-26007', NULL, '2026-03-12 11:10:00', '2026-03-12 11:10:00'),
(93108, '2026-03-12', 'Raj Bullion', 'RB-004', 90402, 'Gold purchase for DEMO-ORD-26008', NULL, '2026-03-12 11:15:00', '2026-03-12 11:15:00'),
(93109, '2026-03-11', 'Gold Bullion House', 'GBH-005', 90402, 'Gold purchase for DEMO-ORD-26009', NULL, '2026-03-11 11:20:00', '2026-03-11 11:20:00'),
(93110, '2026-03-12', 'Raj Bullion', 'RB-005', 90402, 'Gold purchase for DEMO-ORD-26010', NULL, '2026-03-12 11:20:00', '2026-03-12 11:20:00');

INSERT INTO gold_inventory_purchase_lines (id, purchase_id, item_id, weight_gm, fine_weight_gm, rate_per_gm, line_value, created_at, updated_at) VALUES
(93151, 93101, 90702, 12.500, 9.375, 5820.00, 72750.00, '2026-03-11 11:00:00', '2026-03-11 11:00:00'),
(93152, 93102, 90701, 75.000, 68.700, 6250.00, 468750.00, '2026-03-11 11:05:00', '2026-03-11 11:05:00'),
(93153, 93103, 90701, 40.000, 36.640, 6240.00, 249600.00, '2026-03-11 11:10:00', '2026-03-11 11:10:00'),
(93154, 93104, 90702, 16.000, 12.000, 5830.00, 93280.00, '2026-03-12 11:00:00', '2026-03-12 11:00:00'),
(93155, 93105, 90703, 9.000, 5.265, 4980.00, 44820.00, '2026-03-11 11:15:00', '2026-03-11 11:15:00'),
(93156, 93106, 90702, 2.000, 1.500, 5825.00, 11650.00, '2026-03-12 11:05:00', '2026-03-12 11:05:00'),
(93157, 93107, 90701, 60.000, 54.960, 6260.00, 375600.00, '2026-03-12 11:10:00', '2026-03-12 11:10:00'),
(93158, 93108, 90702, 20.000, 15.000, 5840.00, 116800.00, '2026-03-12 11:15:00', '2026-03-12 11:15:00'),
(93159, 93109, 90703, 7.500, 4.387, 4990.00, 37425.00, '2026-03-11 11:20:00', '2026-03-11 11:20:00'),
(93160, 93110, 90702, 18.000, 13.500, 5850.00, 105300.00, '2026-03-12 11:20:00', '2026-03-12 11:20:00');

INSERT INTO gold_inventory_issue_headers (id, voucher_no, issue_date, order_id, karigar_id, location_id, issue_to, purpose, notes, attachment_name, attachment_path, created_by, created_at, updated_at) VALUES
(93201, 'GIV-26001', '2026-03-12', 91001, 90204, 90402, 'Deepak Ringkar', 'Ring making', 'Gold issue for DEMO-ORD-26001', NULL, NULL, NULL, '2026-03-12 15:00:00', '2026-03-12 15:00:00'),
(93202, 'GIV-26002', '2026-03-12', 91002, 90201, 90402, 'Rakesh Sutar', 'Necklace manufacturing', 'Gold issue for DEMO-ORD-26002', NULL, NULL, NULL, '2026-03-12 15:05:00', '2026-03-12 15:05:00'),
(93203, 'GIV-26003', '2026-03-13', 91003, 90201, 90402, 'Rakesh Sutar', 'Chain making', 'Gold issue for DEMO-ORD-26003', NULL, NULL, NULL, '2026-03-13 15:00:00', '2026-03-13 15:00:00'),
(93204, 'GIV-26004', '2026-03-13', 91004, 90202, 90402, 'Imran Setter', 'Pendant making', 'Gold issue for DEMO-ORD-26004', NULL, NULL, NULL, '2026-03-13 15:05:00', '2026-03-13 15:05:00'),
(93205, 'GIV-26005', '2026-03-12', 91005, 90202, 90402, 'Imran Setter', 'Earring making', 'Gold issue for DEMO-ORD-26005', NULL, NULL, NULL, '2026-03-12 15:10:00', '2026-03-12 15:10:00'),
(93206, 'GIV-26006', '2026-03-13', 91006, 90205, 90402, 'Kunal Repairkar', 'Repair solder work', 'Gold issue for DEMO-ORD-26006', NULL, NULL, NULL, '2026-03-13 15:10:00', '2026-03-13 15:10:00'),
(93207, 'GIV-26007', '2026-03-13', 91007, 90201, 90402, 'Rakesh Sutar', 'Bangle making', 'Gold issue for DEMO-ORD-26007', NULL, NULL, NULL, '2026-03-13 15:15:00', '2026-03-13 15:15:00'),
(93208, 'GIV-26008', '2026-03-13', 91008, 90203, 90402, 'Mahesh Polish', 'Pendant making', 'Gold issue for DEMO-ORD-26008', NULL, NULL, NULL, '2026-03-13 15:20:00', '2026-03-13 15:20:00'),
(93209, 'GIV-26009', '2026-03-12', 91009, 90204, 90402, 'Deepak Ringkar', 'Bracelet making', 'Gold issue for DEMO-ORD-26009', NULL, NULL, NULL, '2026-03-12 15:20:00', '2026-03-12 15:20:00'),
(93210, 'GIV-26010', '2026-03-13', 91010, 90202, 90402, 'Imran Setter', 'Cocktail ring making', 'Gold issue for DEMO-ORD-26010', NULL, NULL, NULL, '2026-03-13 15:25:00', '2026-03-13 15:25:00');

INSERT INTO gold_inventory_issue_lines (id, issue_id, item_id, weight_gm, fine_weight_gm, rate_per_gm, line_value, created_at, updated_at) VALUES
(93251, 93201, 90702, 8.200, 6.150, 5820.00, 47724.00, '2026-03-12 15:00:00', '2026-03-12 15:00:00'),
(93252, 93202, 90701, 56.000, 51.296, 6250.00, 350000.00, '2026-03-12 15:05:00', '2026-03-12 15:05:00'),
(93253, 93203, 90701, 33.500, 30.686, 6240.00, 209040.00, '2026-03-13 15:00:00', '2026-03-13 15:00:00'),
(93254, 93204, 90702, 11.200, 8.400, 5830.00, 65296.00, '2026-03-13 15:05:00', '2026-03-13 15:05:00'),
(93255, 93205, 90703, 6.200, 3.627, 4980.00, 30876.00, '2026-03-12 15:10:00', '2026-03-12 15:10:00'),
(93256, 93206, 90702, 0.950, 0.712, 5825.00, 5533.75, '2026-03-13 15:10:00', '2026-03-13 15:10:00'),
(93257, 93207, 90701, 47.500, 43.510, 6260.00, 297350.00, '2026-03-13 15:15:00', '2026-03-13 15:15:00'),
(93258, 93208, 90702, 14.800, 11.100, 5840.00, 86432.00, '2026-03-13 15:20:00', '2026-03-13 15:20:00'),
(93259, 93209, 90703, 4.900, 2.867, 4990.00, 24451.00, '2026-03-12 15:20:00', '2026-03-12 15:20:00'),
(93260, 93210, 90702, 13.500, 10.125, 5850.00, 78975.00, '2026-03-13 15:25:00', '2026-03-13 15:25:00');

INSERT INTO gold_inventory_return_headers (id, voucher_no, return_date, order_id, issue_id, karigar_id, location_id, return_from, purpose, notes, attachment_name, attachment_path, created_by, created_at, updated_at) VALUES
(93301, 'GRV-26001', '2026-03-15', 91001, 93201, 90204, 90402, 'Deepak Ringkar', 'Unused gold return', 'Gold return for DEMO-ORD-26001', NULL, NULL, NULL, '2026-03-15 16:00:00', '2026-03-15 16:00:00'),
(93302, 'GRV-26002', '2026-03-16', 91002, 93202, 90201, 90402, 'Rakesh Sutar', 'Unused gold return', 'Gold return for DEMO-ORD-26002', NULL, NULL, NULL, '2026-03-16 16:00:00', '2026-03-16 16:00:00'),
(93303, 'GRV-26003', '2026-03-17', 91003, 93203, 90201, 90402, 'Rakesh Sutar', 'Unused gold return', 'Gold return for DEMO-ORD-26003', NULL, NULL, NULL, '2026-03-17 16:00:00', '2026-03-17 16:00:00'),
(93304, 'GRV-26004', '2026-03-15', 91004, 93204, 90202, 90402, 'Imran Setter', 'Unused gold return', 'Gold return for DEMO-ORD-26004', NULL, NULL, NULL, '2026-03-15 16:05:00', '2026-03-15 16:05:00'),
(93305, 'GRV-26005', '2026-03-14', 91005, 93205, 90202, 90402, 'Imran Setter', 'Unused gold return', 'Gold return for DEMO-ORD-26005', NULL, NULL, NULL, '2026-03-14 16:05:00', '2026-03-14 16:05:00'),
(93306, 'GRV-26006', '2026-03-14', 91006, 93206, 90205, 90402, 'Kunal Repairkar', 'Unused solder return', 'Gold return for DEMO-ORD-26006', NULL, NULL, NULL, '2026-03-14 16:10:00', '2026-03-14 16:10:00'),
(93307, 'GRV-26007', '2026-03-15', 91007, 93207, 90201, 90402, 'Rakesh Sutar', 'Stage one return', 'Gold return for DEMO-ORD-26007', NULL, NULL, NULL, '2026-03-15 16:10:00', '2026-03-15 16:10:00'),
(93308, 'GRV-26008', '2026-03-16', 91008, 93208, 90203, 90402, 'Mahesh Polish', 'Unused gold return', 'Gold return for DEMO-ORD-26008', NULL, NULL, NULL, '2026-03-16 16:10:00', '2026-03-16 16:10:00'),
(93309, 'GRV-26009', '2026-03-13', 91009, 93209, 90204, 90402, 'Deepak Ringkar', 'Unused gold return', 'Gold return for DEMO-ORD-26009', NULL, NULL, NULL, '2026-03-13 16:15:00', '2026-03-13 16:15:00'),
(93310, 'GRV-26010', '2026-03-17', 91010, 93210, 90202, 90402, 'Imran Setter', 'Unused gold return', 'Gold return for DEMO-ORD-26010', NULL, NULL, NULL, '2026-03-17 16:15:00', '2026-03-17 16:15:00');

INSERT INTO gold_inventory_return_lines (id, return_id, item_id, weight_gm, fine_weight_gm, rate_per_gm, line_value, created_at, updated_at) VALUES
(93351, 93301, 90702, 0.650, 0.488, 5820.00, 3783.00, '2026-03-15 16:00:00', '2026-03-15 16:00:00'),
(93352, 93302, 90701, 1.400, 1.282, 6250.00, 8750.00, '2026-03-16 16:00:00', '2026-03-16 16:00:00'),
(93353, 93303, 90701, 0.950, 0.870, 6240.00, 5928.00, '2026-03-17 16:00:00', '2026-03-17 16:00:00'),
(93354, 93304, 90702, 0.420, 0.315, 5830.00, 2448.60, '2026-03-15 16:05:00', '2026-03-15 16:05:00'),
(93355, 93305, 90703, 0.180, 0.105, 4980.00, 896.40, '2026-03-14 16:05:00', '2026-03-14 16:05:00'),
(93356, 93306, 90702, 0.120, 0.090, 5825.00, 699.00, '2026-03-14 16:10:00', '2026-03-14 16:10:00'),
(93357, 93307, 90701, 0.800, 0.733, 6260.00, 5008.00, '2026-03-15 16:10:00', '2026-03-15 16:10:00'),
(93358, 93308, 90702, 0.530, 0.398, 5840.00, 3095.20, '2026-03-16 16:10:00', '2026-03-16 16:10:00'),
(93359, 93309, 90703, 0.140, 0.082, 4990.00, 698.60, '2026-03-13 16:15:00', '2026-03-13 16:15:00'),
(93360, 93310, 90702, 0.460, 0.345, 5850.00, 2691.00, '2026-03-17 16:15:00', '2026-03-17 16:15:00');

INSERT INTO stone_inventory_purchase_headers (id, purchase_date, vendor_id, supplier_name, invoice_no, due_date, tax_percentage, invoice_total, notes, created_at, updated_at) VALUES
(94101, '2026-03-11', 90303, 'Navkar Stones', 'NS-001', '2026-03-19', 3.000, 36771.00, 'Stone purchase for DEMO-ORD-26002', '2026-03-11 12:30:00', '2026-03-11 12:30:00'),
(94102, '2026-03-12', 90306, 'ColorGem Traders', 'CG-001', '2026-03-20', 3.000, 4480.50, 'Stone purchase for DEMO-ORD-26004', '2026-03-12 12:30:00', '2026-03-12 12:30:00'),
(94103, '2026-03-12', 90306, 'ColorGem Traders', 'CG-002', '2026-03-21', 3.000, 360.50, 'Stone purchase for DEMO-ORD-26006', '2026-03-12 12:35:00', '2026-03-12 12:35:00'),
(94104, '2026-03-12', 90303, 'Navkar Stones', 'NS-002', '2026-03-22', 3.000, 17510.00, 'Stone purchase for DEMO-ORD-26007', '2026-03-12 12:40:00', '2026-03-12 12:40:00'),
(94105, '2026-03-12', 90306, 'ColorGem Traders', 'CG-003', '2026-03-20', 3.000, 1483.20, 'Stone purchase for DEMO-ORD-26008', '2026-03-12 12:45:00', '2026-03-12 12:45:00'),
(94106, '2026-03-12', 90306, 'ColorGem Traders', 'CG-004', '2026-03-23', 3.000, 1493.50, 'Stone purchase for DEMO-ORD-26010', '2026-03-12 12:50:00', '2026-03-12 12:50:00');

INSERT INTO stone_inventory_purchase_lines (id, purchase_id, item_id, qty, rate, line_value, created_at, updated_at) VALUES
(94151, 94101, 90801, 42.000, 850.00, 35700.00, '2026-03-11 12:30:00', '2026-03-11 12:30:00'),
(94152, 94102, 90802, 3.000, 1450.00, 4350.00, '2026-03-12 12:30:00', '2026-03-12 12:30:00'),
(94153, 94103, 90804, 10.000, 35.00, 350.00, '2026-03-12 12:35:00', '2026-03-12 12:35:00'),
(94154, 94104, 90801, 20.000, 850.00, 17000.00, '2026-03-12 12:40:00', '2026-03-12 12:40:00'),
(94155, 94105, 90803, 120.000, 12.00, 1440.00, '2026-03-12 12:45:00', '2026-03-12 12:45:00'),
(94156, 94106, 90802, 1.000, 1450.00, 1450.00, '2026-03-12 12:50:00', '2026-03-12 12:50:00');

INSERT INTO stone_inventory_issue_headers (id, voucher_no, issue_date, order_id, karigar_id, location_id, issue_to, purpose, notes, attachment_name, attachment_path, created_by, created_at, updated_at) VALUES
(94201, 'SIV-26002', '2026-03-12', 91002, 90201, 90404, 'Rakesh Sutar', 'Ruby hanging setting', 'Stone issue for DEMO-ORD-26002', NULL, NULL, NULL, '2026-03-12 16:00:00', '2026-03-12 16:00:00'),
(94202, 'SIV-26004', '2026-03-13', 91004, 90202, 90404, 'Imran Setter', 'Emerald setting', 'Stone issue for DEMO-ORD-26004', NULL, NULL, NULL, '2026-03-13 16:00:00', '2026-03-13 16:00:00'),
(94203, 'SIV-26006', '2026-03-13', 91006, 90205, 90404, 'Kunal Repairkar', 'CZ replacement', 'Stone issue for DEMO-ORD-26006', NULL, NULL, NULL, '2026-03-13 16:05:00', '2026-03-13 16:05:00'),
(94204, 'SIV-26007', '2026-03-13', 91007, 90201, 90404, 'Rakesh Sutar', 'Ruby setting', 'Stone issue for DEMO-ORD-26007', NULL, NULL, NULL, '2026-03-13 16:10:00', '2026-03-13 16:10:00'),
(94205, 'SIV-26008', '2026-03-13', 91008, 90203, 90404, 'Mahesh Polish', 'Bead linking', 'Stone issue for DEMO-ORD-26008', NULL, NULL, NULL, '2026-03-13 16:15:00', '2026-03-13 16:15:00'),
(94206, 'SIV-26010', '2026-03-13', 91010, 90202, 90404, 'Imran Setter', 'Center emerald setting', 'Stone issue for DEMO-ORD-26010', NULL, NULL, NULL, '2026-03-13 16:20:00', '2026-03-13 16:20:00');

INSERT INTO stone_inventory_issue_lines (id, issue_id, item_id, pcs, qty, rate, line_value, created_at, updated_at) VALUES
(94251, 94201, 90801, 42.000, 42.000, 850.00, 35700.00, '2026-03-12 16:00:00', '2026-03-12 16:00:00'),
(94252, 94202, 90802, 3.000, 3.000, 1450.00, 4350.00, '2026-03-13 16:00:00', '2026-03-13 16:00:00'),
(94253, 94203, 90804, 2.000, 2.000, 35.00, 70.00, '2026-03-13 16:05:00', '2026-03-13 16:05:00'),
(94254, 94204, 90801, 20.000, 20.000, 850.00, 17000.00, '2026-03-13 16:10:00', '2026-03-13 16:10:00'),
(94255, 94205, 90803, 120.000, 120.000, 12.00, 1440.00, '2026-03-13 16:15:00', '2026-03-13 16:15:00'),
(94256, 94206, 90802, 1.000, 1.000, 1450.00, 1450.00, '2026-03-13 16:20:00', '2026-03-13 16:20:00');

INSERT INTO stone_inventory_return_headers (id, voucher_no, return_date, order_id, issue_id, karigar_id, location_id, return_from, purpose, notes, attachment_name, attachment_path, created_by, created_at, updated_at) VALUES
(94301, 'SRV-26002', '2026-03-16', 91002, 94201, 90201, 90404, 'Rakesh Sutar', 'Unused ruby return', 'Stone return for DEMO-ORD-26002', NULL, NULL, NULL, '2026-03-16 17:00:00', '2026-03-16 17:00:00'),
(94302, 'SRV-26006', '2026-03-14', 91006, 94203, 90205, 90404, 'Kunal Repairkar', 'Unused CZ return', 'Stone return for DEMO-ORD-26006', NULL, NULL, NULL, '2026-03-14 17:00:00', '2026-03-14 17:00:00'),
(94303, 'SRV-26007', '2026-03-15', 91007, 94204, 90201, 90404, 'Rakesh Sutar', 'Balance ruby return', 'Stone return for DEMO-ORD-26007', NULL, NULL, NULL, '2026-03-15 17:00:00', '2026-03-15 17:00:00'),
(94304, 'SRV-26008', '2026-03-16', 91008, 94205, 90203, 90404, 'Mahesh Polish', 'Unused bead return', 'Stone return for DEMO-ORD-26008', NULL, NULL, NULL, '2026-03-16 17:00:00', '2026-03-16 17:00:00');

INSERT INTO stone_inventory_return_lines (id, return_id, item_id, qty, rate, line_value, created_at, updated_at) VALUES
(94351, 94301, 90801, 4.000, 850.00, 3400.00, '2026-03-16 17:00:00', '2026-03-16 17:00:00'),
(94352, 94302, 90804, 1.000, 35.00, 35.00, '2026-03-14 17:00:00', '2026-03-14 17:00:00'),
(94353, 94303, 90801, 2.000, 850.00, 1700.00, '2026-03-15 17:00:00', '2026-03-15 17:00:00'),
(94354, 94304, 90803, 8.000, 12.00, 96.00, '2026-03-16 17:00:00', '2026-03-16 17:00:00');

INSERT INTO stock (item_id, pcs_balance, carat_balance, avg_cost_per_carat, stock_value, updated_at) VALUES
(90601, 6.000, 0.025, 42316.90, 1057.92, '2026-03-17 18:00:00'),
(90602, 12.000, 0.080, 36000.00, 2880.00, '2026-03-17 18:00:00'),
(90603, 0.000, 0.000, 98522.39, 0.00, '2026-03-17 18:00:00');

INSERT INTO gold_inventory_stock (item_id, weight_balance_gm, fine_balance_gm, avg_cost_per_gm, stock_value, updated_at) VALUES
(90701, 41.150, 37.693, 6251.14, 257234.40, '2026-03-17 18:00:00'),
(90702, 22.030, 16.524, 5836.20, 128570.49, '2026-03-17 18:00:00'),
(90703, 5.720, 3.345, 4984.55, 28511.63, '2026-03-17 18:00:00');

INSERT INTO stone_inventory_stock (item_id, qty_balance, avg_rate, stock_value, updated_at) VALUES
(90801, 6.000, 850.00, 5100.00, '2026-03-17 18:00:00'),
(90802, 0.000, 1450.00, 0.00, '2026-03-17 18:00:00'),
(90803, 8.000, 12.00, 96.00, '2026-03-17 18:00:00'),
(90804, 9.000, 35.00, 315.00, '2026-03-17 18:00:00');

INSERT INTO packing_lists (id, packing_no, packing_date, order_id, customer_id, warehouse_id, status, seal_no, notes, created_by, created_at, updated_at) VALUES
(95101, 'PK-26001', '2026-03-17', 91001, 90101, 90401, 'Packed', 'SEAL-26001', 'Packing for DEMO-ORD-26001', NULL, '2026-03-17 11:00:00', '2026-03-17 11:00:00'),
(95102, 'PK-26002', '2026-03-16', 91002, 90102, 90401, 'Packed', 'SEAL-26002', 'Packing for DEMO-ORD-26002', NULL, '2026-03-16 11:00:00', '2026-03-16 11:00:00'),
(95103, 'PK-26003', '2026-03-17', 91003, 90103, 90401, 'Packed', 'SEAL-26003', 'Packing for DEMO-ORD-26003', NULL, '2026-03-17 11:05:00', '2026-03-17 11:05:00'),
(95104, 'PK-26005', '2026-03-14', 91005, 90105, 90401, 'Packed', 'SEAL-26005', 'Packing for DEMO-ORD-26005', NULL, '2026-03-14 11:00:00', '2026-03-14 11:00:00'),
(95105, 'PK-26006', '2026-03-14', 91006, 90106, 90401, 'Packed', 'SEAL-26006', 'Packing for DEMO-ORD-26006', NULL, '2026-03-14 11:05:00', '2026-03-14 11:05:00'),
(95106, 'PK-26008', '2026-03-16', 91008, 90108, 90401, 'Packed', 'SEAL-26008', 'Packing for DEMO-ORD-26008', NULL, '2026-03-16 11:05:00', '2026-03-16 11:05:00'),
(95107, 'PK-26009', '2026-03-13', 91009, 90109, 90401, 'Packed', 'SEAL-26009', 'Packing for DEMO-ORD-26009', NULL, '2026-03-13 11:00:00', '2026-03-13 11:00:00'),
(95108, 'PK-26010', '2026-03-17', 91010, 90110, 90401, 'Packed', 'SEAL-26010', 'Packing for DEMO-ORD-26010', NULL, '2026-03-17 11:10:00', '2026-03-17 11:10:00');

INSERT INTO delivery_challans (id, challan_no, challan_date, order_id, packing_list_id, receive_movement_id, gross_weight_gm, net_gold_weight_gm, diamond_weight_cts, color_stone_weight_cts, other_weight_gm, taxable_value, tax_percent, tax_amount, total_amount, summary_json, created_by, created_at, updated_at) VALUES
(95201, 'DC-26001', '2026-03-17', 91001, 95101, NULL, 8.700, 7.550, 0.360, 0.000, 0.000, 118500.00, 3.00, 3555.00, 122055.00, NULL, NULL, '2026-03-17 12:00:00', '2026-03-17 12:00:00'),
(95202, 'DC-26002', '2026-03-16', 91002, 95102, NULL, 55.200, 52.400, 1.170, 38.000, 0.000, 575000.00, 3.00, 17250.00, 592250.00, NULL, NULL, '2026-03-16 12:00:00', '2026-03-16 12:00:00'),
(95203, 'DC-26003', '2026-03-17', 91003, 95103, NULL, 32.300, 31.150, 0.000, 0.000, 0.000, 287500.00, 3.00, 8625.00, 296125.00, NULL, NULL, '2026-03-17 12:05:00', '2026-03-17 12:05:00'),
(95204, 'DC-26005', '2026-03-14', 91005, 95104, NULL, 5.950, 5.620, 0.210, 0.000, 0.000, 84500.00, 3.00, 2535.00, 87035.00, NULL, NULL, '2026-03-14 12:00:00', '2026-03-14 12:00:00'),
(95205, 'DC-26006', '2026-03-14', 91006, 95105, NULL, 6.980, 6.750, 0.000, 1.000, 0.000, 17500.00, 3.00, 525.00, 18025.00, NULL, NULL, '2026-03-14 12:05:00', '2026-03-14 12:05:00'),
(95206, 'DC-26008', '2026-03-16', 91008, 95106, NULL, 14.950, 14.270, 0.180, 112.000, 0.000, 162000.00, 3.00, 4860.00, 166860.00, NULL, NULL, '2026-03-16 12:05:00', '2026-03-16 12:05:00'),
(95207, 'DC-26009', '2026-03-13', 91009, 95107, NULL, 4.760, 4.420, 0.000, 0.000, 0.000, 39500.00, 3.00, 1185.00, 40685.00, NULL, NULL, '2026-03-13 12:00:00', '2026-03-13 12:00:00'),
(95208, 'DC-26010', '2026-03-17', 91010, 95108, NULL, 13.240, 12.680, 0.605, 1.000, 0.000, 214000.00, 3.00, 6420.00, 220420.00, NULL, NULL, '2026-03-17 12:10:00', '2026-03-17 12:10:00');

COMMIT;
