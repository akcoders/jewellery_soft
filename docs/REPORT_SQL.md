# Report SQL Templates

## 1) Stock on hand by warehouse/bin (all materials)
```sql
SELECT w.name AS warehouse, b.name AS bin, ib.item_type, ib.item_key,
       SUM(ib.qty_pcs) AS qty_pcs,
       SUM(ib.qty_cts) AS qty_cts,
       SUM(ib.qty_weight) AS qty_weight,
       SUM(ib.fine_gold_qty) AS fine_gold_qty
FROM inventory_balances ib
LEFT JOIN warehouses w ON w.id = ib.warehouse_id
LEFT JOIN bins b ON b.id = ib.bin_id
GROUP BY w.name, b.name, ib.item_type, ib.item_key
ORDER BY w.name, b.name, ib.item_type, ib.item_key;
```

## 2) Karigar outstanding (gold/diamond/stone/labour)
```sql
SELECT a.account_name, ab.item_type, ab.item_key,
       ab.qty_weight AS gold_gm,
       ab.fine_gold_qty AS gold_fine,
       ab.qty_pcs, ab.qty_cts
FROM account_balances ab
JOIN accounts a ON a.id = ab.account_id
WHERE a.account_type = 'KARIGAR'
ORDER BY a.account_name, ab.item_type, ab.item_key;
```

## 3) Order-wise issued vs returned vs consumed
```sql
SELECT v.order_id, vl.item_type, vl.item_key,
       SUM(CASE WHEN v.voucher_type IN ('ISSUE','GOLD_ISSUE','DIAMOND_BAG_ISSUE','STONE_ISSUE') THEN vl.qty_weight ELSE 0 END) AS issued_weight,
       SUM(CASE WHEN v.voucher_type IN ('RETURN','GOLD_RETURN','DIAMOND_BAG_RETURN','STONE_RETURN') THEN vl.qty_weight ELSE 0 END) AS returned_weight,
       SUM(CASE WHEN v.voucher_type IN ('ISSUE','GOLD_ISSUE','DIAMOND_BAG_ISSUE','STONE_ISSUE') THEN vl.qty_weight ELSE 0 END)
     - SUM(CASE WHEN v.voucher_type IN ('RETURN','GOLD_RETURN','DIAMOND_BAG_RETURN','STONE_RETURN') THEN vl.qty_weight ELSE 0 END) AS consumed_weight
FROM vouchers v
JOIN voucher_lines vl ON vl.voucher_id = v.id
GROUP BY v.order_id, vl.item_type, vl.item_key
ORDER BY v.order_id;
```

## 4) Wastage allowed vs actual
```sql
SELECT o.order_no,
       SUM(CASE WHEN m.movement_type='issue' THEN m.gold_gm ELSE 0 END) AS issue_gold,
       SUM(CASE WHEN m.movement_type='receive' THEN m.gold_gm ELSE 0 END) AS receive_gold,
       ROUND((SUM(CASE WHEN m.movement_type='issue' THEN m.gold_gm ELSE 0 END)
            -SUM(CASE WHEN m.movement_type='receive' THEN m.gold_gm ELSE 0 END))
            / NULLIF(SUM(CASE WHEN m.movement_type='issue' THEN m.gold_gm ELSE 0 END),0) * 100,3) AS actual_wastage_pct,
       k.wastage_percent AS allowed_wastage_pct
FROM orders o
LEFT JOIN order_material_movements m ON m.order_id=o.id
LEFT JOIN karigars k ON k.id=o.assigned_karigar_id
GROUP BY o.id, o.order_no, k.wastage_percent;
```

## 5) Diamond bag movement history
```sql
SELECT h.created_at, d.bag_no, h.action_type, h.pcs, h.cts,
       fw.name AS from_wh, tw.name AS to_wh, v.voucher_no
FROM diamond_bag_history h
JOIN diamond_bags d ON d.id = h.bag_id
LEFT JOIN warehouses fw ON fw.id = h.from_warehouse_id
LEFT JOIN warehouses tw ON tw.id = h.to_warehouse_id
LEFT JOIN vouchers v ON v.id = h.ref_voucher_id
ORDER BY h.id DESC;
```

## 6) Customer/vendor outstanding ageing
```sql
SELECT 'CUSTOMER' party_type, c.name party_name, i.invoice_no doc_no, i.invoice_date doc_date,
       i.total_amount amount,
       IFNULL((SELECT SUM(r.amount) FROM customer_receipts r WHERE r.invoice_id=i.id),0) paid,
       i.total_amount-IFNULL((SELECT SUM(r.amount) FROM customer_receipts r WHERE r.invoice_id=i.id),0) outstanding,
       DATEDIFF(CURDATE(), i.invoice_date) age_days
FROM invoices i
JOIN customers c ON c.id=i.customer_id
UNION ALL
SELECT 'VENDOR', v.name, pi.invoice_no, pi.invoice_date,
       pi.total_amount,
       IFNULL((SELECT SUM(vp.amount) FROM vendor_payments vp WHERE vp.purchase_invoice_id=pi.id),0),
       pi.total_amount-IFNULL((SELECT SUM(vp.amount) FROM vendor_payments vp WHERE vp.purchase_invoice_id=pi.id),0),
       DATEDIFF(CURDATE(), pi.invoice_date)
FROM purchase_invoices pi
JOIN vendors v ON v.id=pi.vendor_id;
```
