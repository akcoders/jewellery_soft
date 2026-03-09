<?php

namespace App\Controllers\Api;

class ReportsController extends ApiBaseController
{
    private function wantsQueryDebug(): bool
    {
        return (string) $this->request->getGet('show_query') === '1';
    }

    private function okWithOptionalQuery(array $rows, string $sql)
    {
        if (! $this->wantsQueryDebug()) {
            return $this->ok($rows);
        }

        return $this->ok([
            'query' => trim($sql),
            'rows' => $rows,
        ]);
    }

    public function stockOnHand()
    {
        $sql = "
            SELECT
                w.name AS warehouse_name,
                b.name AS bin_name,
                ib.item_type,
                ib.item_key,
                SUM(ib.qty_pcs) AS qty_pcs,
                SUM(ib.qty_cts) AS qty_cts,
                SUM(ib.qty_weight) AS qty_weight,
                SUM(ib.fine_gold_qty) AS fine_gold_qty
            FROM inventory_balances ib
            LEFT JOIN warehouses w ON w.id = ib.warehouse_id
            LEFT JOIN bins b ON b.id = ib.bin_id
            GROUP BY w.name, b.name, ib.item_type, ib.item_key
            ORDER BY w.name, b.name, ib.item_type, ib.item_key
        ";

        $rows = db_connect()->query($sql)->getResultArray();
        return $this->okWithOptionalQuery($rows, $sql);
    }

    public function karigarOutstanding()
    {
        $sql = "
            SELECT
                a.account_code,
                a.account_name,
                ab.item_type,
                ab.item_key,
                ab.qty_pcs,
                ab.qty_cts,
                ab.qty_weight,
                ab.fine_gold_qty
            FROM account_balances ab
            JOIN accounts a ON a.id = ab.account_id
            WHERE a.account_type = 'KARIGAR'
            ORDER BY a.account_name, ab.item_type, ab.item_key
        ";

        $rows = db_connect()->query($sql)->getResultArray();
        return $this->okWithOptionalQuery($rows, $sql);
    }

    public function orderConsumption()
    {
        $orderId = (int) ($this->request->getGet('order_id') ?? 0);
        $where = $orderId > 0 ? 'WHERE v.order_id = ' . $orderId : '';

        $sql = "
            SELECT
                v.order_id,
                vl.item_type,
                vl.item_key,
                SUM(CASE WHEN v.voucher_type IN ('GOLD_ISSUE','DIAMOND_BAG_ISSUE','STONE_ISSUE','ISSUE') THEN vl.qty_weight ELSE 0 END) AS issued_weight,
                SUM(CASE WHEN v.voucher_type IN ('GOLD_RETURN','DIAMOND_BAG_RETURN','STONE_RETURN','RETURN') THEN vl.qty_weight ELSE 0 END) AS returned_weight,
                SUM(CASE WHEN v.voucher_type IN ('DIAMOND_LOSS_BREAKAGE','LOSS_BREAKAGE') THEN vl.qty_weight ELSE 0 END) AS loss_weight,
                SUM(CASE WHEN v.voucher_type IN ('GOLD_ISSUE','DIAMOND_BAG_ISSUE','STONE_ISSUE','ISSUE') THEN vl.qty_weight ELSE 0 END)
                - SUM(CASE WHEN v.voucher_type IN ('GOLD_RETURN','DIAMOND_BAG_RETURN','STONE_RETURN','RETURN') THEN vl.qty_weight ELSE 0 END)
                - SUM(CASE WHEN v.voucher_type IN ('DIAMOND_LOSS_BREAKAGE','LOSS_BREAKAGE') THEN vl.qty_weight ELSE 0 END)
                AS consumed_weight
            FROM vouchers v
            JOIN voucher_lines vl ON vl.voucher_id = v.id
            $where
            GROUP BY v.order_id, vl.item_type, vl.item_key
            ORDER BY v.order_id, vl.item_type, vl.item_key
        ";

        $rows = db_connect()->query($sql)->getResultArray();
        return $this->okWithOptionalQuery($rows, $sql);
    }

    public function wastage()
    {
        $sql = "
            SELECT
                o.id AS order_id,
                o.order_no,
                SUM(CASE WHEN m.movement_type = 'issue' THEN m.gold_gm ELSE 0 END) AS issued_gold,
                SUM(CASE WHEN m.movement_type = 'receive' THEN m.gold_gm ELSE 0 END) AS received_gold,
                CASE
                    WHEN SUM(CASE WHEN m.movement_type = 'issue' THEN m.gold_gm ELSE 0 END) = 0 THEN 0
                    ELSE ROUND(
                        (SUM(CASE WHEN m.movement_type = 'issue' THEN m.gold_gm ELSE 0 END)
                        - SUM(CASE WHEN m.movement_type = 'receive' THEN m.gold_gm ELSE 0 END))
                        / SUM(CASE WHEN m.movement_type = 'issue' THEN m.gold_gm ELSE 0 END) * 100,
                    3)
                END AS wastage_percent,
                k.wastage_percentage AS allowed_wastage_percent
            FROM orders o
            LEFT JOIN order_material_movements m ON m.order_id = o.id
            LEFT JOIN karigars k ON k.id = o.assigned_karigar_id
            GROUP BY o.id, o.order_no, k.wastage_percentage
            ORDER BY o.id DESC
        ";

        $rows = db_connect()->query($sql)->getResultArray();
        foreach ($rows as &$row) {
            $row['excess_wastage'] = ((float) ($row['wastage_percent'] ?? 0)) > ((float) ($row['allowed_wastage_percent'] ?? 0));
        }

        return $this->okWithOptionalQuery($rows, $sql);
    }

    public function bagHistory()
    {
        $bagId = (int) ($this->request->getGet('bag_id') ?? 0);
        $builder = db_connect()->table('diamond_bag_history h')
            ->select('h.*, d.bag_no, fv.voucher_no as ref_voucher_no, fw.name as from_warehouse, tw.name as to_warehouse')
            ->join('diamond_bags d', 'd.id = h.bag_id', 'left')
            ->join('vouchers fv', 'fv.id = h.ref_voucher_id', 'left')
            ->join('warehouses fw', 'fw.id = h.from_warehouse_id', 'left')
            ->join('warehouses tw', 'tw.id = h.to_warehouse_id', 'left')
            ->orderBy('h.id', 'DESC');

        if ($bagId > 0) {
            $builder->where('h.bag_id', $bagId);
        }

        $sql = $builder->getCompiledSelect(false);
        $rows = $builder->get()->getResultArray();

        return $this->okWithOptionalQuery($rows, $sql);
    }

    public function outstandingAging()
    {
        $sql = "
            SELECT
                'CUSTOMER' AS party_type,
                c.id AS party_id,
                c.name AS party_name,
                i.id AS doc_id,
                i.invoice_no AS doc_no,
                i.invoice_date AS doc_date,
                i.total_amount AS amount,
                IFNULL((SELECT SUM(r.amount) FROM customer_receipts r WHERE r.invoice_id = i.id), 0) AS paid,
                i.total_amount - IFNULL((SELECT SUM(r.amount) FROM customer_receipts r WHERE r.invoice_id = i.id), 0) AS outstanding,
                DATEDIFF(CURDATE(), i.invoice_date) AS age_days
            FROM invoices i
            JOIN customers c ON c.id = i.customer_id
            WHERE i.total_amount - IFNULL((SELECT SUM(r.amount) FROM customer_receipts r WHERE r.invoice_id = i.id), 0) > 0

            UNION ALL

            SELECT
                'VENDOR' AS party_type,
                v.id AS party_id,
                v.name AS party_name,
                pi.id AS doc_id,
                pi.invoice_no AS doc_no,
                pi.invoice_date AS doc_date,
                pi.total_amount AS amount,
                IFNULL((SELECT SUM(vp.amount) FROM vendor_payments vp WHERE vp.purchase_invoice_id = pi.id), 0) AS paid,
                pi.total_amount - IFNULL((SELECT SUM(vp.amount) FROM vendor_payments vp WHERE vp.purchase_invoice_id = pi.id), 0) AS outstanding,
                DATEDIFF(CURDATE(), pi.invoice_date) AS age_days
            FROM purchase_invoices pi
            JOIN vendors v ON v.id = pi.vendor_id
            WHERE pi.total_amount - IFNULL((SELECT SUM(vp.amount) FROM vendor_payments vp WHERE vp.purchase_invoice_id = pi.id), 0) > 0
            ORDER BY party_type, party_name, age_days DESC
        ";

        return $this->okWithOptionalQuery(db_connect()->query($sql)->getResultArray(), $sql);
    }

    public function sqlTemplates()
    {
        $templates = [
            'stock_on_hand' => 'SELECT warehouse_id, bin_id, item_type, item_key, SUM(qty_weight) FROM inventory_balances GROUP BY warehouse_id, bin_id, item_type, item_key',
            'karigar_outstanding' => "SELECT a.account_name, ab.* FROM account_balances ab JOIN accounts a ON a.id = ab.account_id WHERE a.account_type='KARIGAR'",
            'order_consumption' => 'SELECT v.order_id, vl.item_key, SUM(...) FROM vouchers v JOIN voucher_lines vl ON vl.voucher_id=v.id GROUP BY v.order_id, vl.item_key',
            'wastage' => 'SELECT order_id, issued, returned, ((issued-returned)/issued)*100 FROM order_material_movements',
            'bag_history' => 'SELECT * FROM diamond_bag_history WHERE bag_id=? ORDER BY id DESC',
            'ageing' => 'SELECT customer/vendor outstanding with DATEDIFF(CURDATE(), invoice_date)',
        ];

        $code = trim((string) $this->request->getGet('code'));
        if ($code !== '') {
            if (! array_key_exists($code, $templates)) {
                return $this->failValidationError('Unknown SQL template code: ' . $code);
            }

            return $this->ok([
                'code' => $code,
                'query' => $templates[$code],
            ]);
        }

        return $this->ok($templates);
    }
}
