<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class DiamondLedgerService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @param array{from:string,to:string,karigar_id:int,order_no:string,product:string,txn_type:string} $filters
     * @return array<string,mixed>
     */
    public function build(array $filters): array
    {
        $productOptions = $this->products();
        $products = trim((string) ($filters['product'] ?? '')) === ''
            ? $productOptions
            : array_values(array_filter($productOptions, static fn(array $row): bool => (string) $row['label'] === (string) $filters['product']));
        $productLabels = array_column($products, 'label');
        $rows = $this->movementRows($filters);
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        $opening = [];
        foreach ($productLabels as $label) {
            $opening[$label] = ['cts' => 0.0, 'pcs' => 0.0];
        }

        if ($from !== '') {
            foreach ($rows as $row) {
                if ((string) ($row['txn_date'] ?? '') >= $from) {
                    continue;
                }
                $product = (string) ($row['product'] ?? 'Unclassified');
                if (! isset($opening[$product])) {
                    continue;
                }
                $sign = (string) ($row['direction'] ?? '') === 'received' ? 1 : -1;
                $opening[$product]['cts'] += $sign * (float) ($row['carat'] ?? 0);
                $opening[$product]['pcs'] += $sign * (float) ($row['pcs'] ?? 0);
            }
        }

        $periodRows = array_values(array_filter($rows, static function (array $row) use ($from, $to, $filters): bool {
            $date = (string) ($row['txn_date'] ?? '');
            if ($from !== '' && $date < $from) return false;
            if ($to !== '' && $date > $to) return false;
            $txnType = trim((string) ($filters['txn_type'] ?? ''));
            return $txnType === '' || strcasecmp((string) ($row['txn_type'] ?? ''), $txnType) === 0;
        }));

        $matrix = [];
        $totals = [];
        foreach ($productLabels as $label) {
            $totals[$label] = [
                'received_cts' => 0.0,
                'issued_cts' => 0.0,
                'received_pcs' => 0.0,
                'issued_pcs' => 0.0,
            ];
        }

        foreach ($periodRows as $row) {
            $key = implode('|', [
                (string) ($row['txn_date'] ?? ''),
                (string) ($row['txn_type'] ?? ''),
                (string) ($row['reference_no'] ?? ''),
                (string) ($row['description'] ?? ''),
                (string) ($row['order_no'] ?? ''),
            ]);
            if (! isset($matrix[$key])) {
                $matrix[$key] = [
                    'txn_date' => (string) ($row['txn_date'] ?? ''),
                    'description' => (string) ($row['description'] ?? '-'),
                    'reference_no' => (string) ($row['reference_no'] ?? '-'),
                    'txn_type' => (string) ($row['txn_type'] ?? 'Movement'),
                    'order_no' => (string) ($row['order_no'] ?? ''),
                    'notes' => (string) ($row['notes'] ?? ''),
                    'products' => [],
                ];
            }
            $product = (string) ($row['product'] ?? 'Unclassified');
            if (! isset($totals[$product])) {
                continue;
            }
            if (! isset($matrix[$key]['products'][$product])) {
                $matrix[$key]['products'][$product] = [
                    'received_cts' => 0.0,
                    'issued_cts' => 0.0,
                    'received_pcs' => 0.0,
                    'issued_pcs' => 0.0,
                ];
            }
            $direction = (string) ($row['direction'] ?? '') === 'received' ? 'received' : 'issued';
            $matrix[$key]['products'][$product][$direction . '_cts'] += (float) ($row['carat'] ?? 0);
            $matrix[$key]['products'][$product][$direction . '_pcs'] += (float) ($row['pcs'] ?? 0);
            $totals[$product][$direction . '_cts'] += (float) ($row['carat'] ?? 0);
            $totals[$product][$direction . '_pcs'] += (float) ($row['pcs'] ?? 0);
        }

        $matrixRows = array_values($matrix);
        usort($matrixRows, static function (array $a, array $b): int {
            $date = strcmp((string) ($a['txn_date'] ?? ''), (string) ($b['txn_date'] ?? ''));
            return $date !== 0 ? $date : strcmp((string) ($a['reference_no'] ?? ''), (string) ($b['reference_no'] ?? ''));
        });

        $summary = ['opening_cts' => 0.0, 'received_cts' => 0.0, 'issued_cts' => 0.0, 'closing_cts' => 0.0];
        foreach ($productLabels as $label) {
            foreach ($opening[$label] as $field => $value) {
                $opening[$label][$field] = round($value, 3);
            }
            foreach ($totals[$label] as $field => $value) {
                $totals[$label][$field] = round($value, 3);
            }
            $totals[$label]['closing_cts'] = round(
                $opening[$label]['cts'] + $totals[$label]['received_cts'] - $totals[$label]['issued_cts'],
                3
            );
            $totals[$label]['closing_pcs'] = round(
                $opening[$label]['pcs'] + $totals[$label]['received_pcs'] - $totals[$label]['issued_pcs'],
                3
            );
            $summary['opening_cts'] += $opening[$label]['cts'];
            $summary['received_cts'] += $totals[$label]['received_cts'];
            $summary['issued_cts'] += $totals[$label]['issued_cts'];
            $summary['closing_cts'] += $totals[$label]['closing_cts'];
        }
        foreach ($summary as $field => $value) {
            $summary[$field] = round($value, 3);
        }
        $summary['transactions'] = count($matrixRows);
        $summary['products'] = count($products);

        return [
            'products' => $products,
            'product_options' => $productOptions,
            'rows' => $matrixRows,
            'opening' => $opening,
            'totals' => $totals,
            'summary' => $summary,
            'transaction_types' => ['Opening', 'Purchase', 'Issue', 'Return', 'Adjustment In', 'Adjustment Out'],
        ];
    }

    /** @return list<array{label:string}> */
    private function products(): array
    {
        if (! $this->db->tableExists('items')) {
            return [];
        }
        $productExpression = "COALESCE(NULLIF(TRIM(clarity), ''), NULLIF(TRIM(diamond_type), ''))";
        $builder = $this->db->table('items')
            ->select($productExpression . ' AS label, MIN(id) AS sort_id', false)
            ->where($productExpression . ' IS NOT NULL', null, false)
            ->groupBy($productExpression)
            ->orderBy('sort_id', 'ASC');
        return array_map(
            static fn(array $row): array => ['label' => (string) $row['label']],
            $builder->get()->getResultArray()
        );
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function movementRows(array $filters): array
    {
        $rows = [];
        $karigarId = (int) ($filters['karigar_id'] ?? 0);
        $orderNo = trim((string) ($filters['order_no'] ?? ''));
        $product = trim((string) ($filters['product'] ?? ''));
        $karigarName = '';
        if ($karigarId > 0 && $this->db->tableExists('karigars')) {
            $karigarName = (string) (($this->db->table('karigars')->select('name')->where('id', $karigarId)->get()->getRowArray()['name'] ?? ''));
        }

        if ($karigarId <= 0 && $orderNo === '') {
            if ($this->db->tableExists('diamond_inventory_opening_balances')) {
                $builder = $this->db->table('diamond_inventory_opening_balances ob')
                    ->select("ob.opening_date AS txn_date, 'Opening' AS txn_type, COALESCE(NULLIF(ob.reference_no,''), CONCAT('OPEN#',ob.id)) AS reference_no, 'Opening Balance' AS description, '' AS order_no, ob.notes, COALESCE(NULLIF(TRIM(i.clarity),''), NULLIF(TRIM(i.diamond_type),'')) AS product, ob.pcs, ob.carat, 'received' AS direction", false)
                    ->join('items i', 'i.id = ob.item_id', 'left');
                $this->applyProductFilter($builder, $product);
                $rows = array_merge($rows, $builder->get()->getResultArray());
            }
            if ($this->db->tableExists('purchase_headers') && $this->db->tableExists('purchase_lines')) {
                $builder = $this->db->table('purchase_headers ph')
                    ->select("ph.purchase_date AS txn_date, 'Purchase' AS txn_type, COALESCE(NULLIF(ph.invoice_no,''), CONCAT('PUR#',ph.id)) AS reference_no, COALESCE(NULLIF(ph.supplier_name,''), 'Supplier') AS description, '' AS order_no, ph.notes, COALESCE(NULLIF(TRIM(i.clarity),''), NULLIF(TRIM(i.diamond_type),'')) AS product, pl.pcs, pl.carat, 'received' AS direction", false)
                    ->join('purchase_lines pl', 'pl.purchase_id = ph.id', 'inner')
                    ->join('items i', 'i.id = pl.item_id', 'left');
                $this->applyProductFilter($builder, $product);
                $rows = array_merge($rows, $builder->get()->getResultArray());
            }
            if ($this->db->tableExists('diamond_inventory_adjustment_headers') && $this->db->tableExists('diamond_inventory_adjustment_lines')) {
                $builder = $this->db->table('diamond_inventory_adjustment_headers ah')
                    ->select("ah.adjustment_date AS txn_date, CASE WHEN LOWER(ah.adjustment_type)='add' THEN 'Adjustment In' ELSE 'Adjustment Out' END AS txn_type, CONCAT('ADJ#',ah.id) AS reference_no, 'Stock Adjustment' AS description, '' AS order_no, ah.notes, COALESCE(NULLIF(TRIM(i.clarity),''), NULLIF(TRIM(i.diamond_type),'')) AS product, al.pcs, al.carat, CASE WHEN LOWER(ah.adjustment_type)='add' THEN 'received' ELSE 'issued' END AS direction", false)
                    ->join('diamond_inventory_adjustment_lines al', 'al.adjustment_id = ah.id', 'inner')
                    ->join('items i', 'i.id = al.item_id', 'left');
                $this->applyProductFilter($builder, $product);
                $rows = array_merge($rows, $builder->get()->getResultArray());
            }
        }

        if ($this->db->tableExists('issue_headers') && $this->db->tableExists('issue_lines')) {
            $builder = $this->db->table('issue_headers ih')
                ->select("ih.issue_date AS txn_date, 'Issue' AS txn_type, COALESCE(NULLIF(ih.voucher_no,''), CONCAT('ISS#',ih.id)) AS reference_no, COALESCE(NULLIF(ih.issue_to,''), k.name, 'Karigar') AS description, COALESCE(o.order_no,'') AS order_no, ih.notes, COALESCE(NULLIF(TRIM(i.clarity),''), NULLIF(TRIM(i.diamond_type),'')) AS product, il.pcs, il.carat, 'issued' AS direction", false)
                ->join('issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->join('items i', 'i.id = il.item_id', 'left')
                ->join('orders o', 'o.id = ih.order_id', 'left')
                ->join('karigars k', 'k.id = ih.karigar_id', 'left');
            $this->applyPartyFilters($builder, 'ih', 'issue_to', $karigarId, $karigarName, $orderNo, $product);
            $rows = array_merge($rows, $builder->get()->getResultArray());
        }

        if ($this->db->tableExists('return_headers') && $this->db->tableExists('return_lines')) {
            $builder = $this->db->table('return_headers rh')
                ->select("rh.return_date AS txn_date, 'Return' AS txn_type, COALESCE(NULLIF(rh.voucher_no,''), CONCAT('RET#',rh.id)) AS reference_no, COALESCE(NULLIF(rh.return_from,''), k.name, 'Karigar') AS description, COALESCE(o.order_no,'') AS order_no, rh.notes, COALESCE(NULLIF(TRIM(i.clarity),''), NULLIF(TRIM(i.diamond_type),'')) AS product, rl.pcs, rl.carat, 'received' AS direction", false)
                ->join('return_lines rl', 'rl.return_id = rh.id', 'inner')
                ->join('items i', 'i.id = rl.item_id', 'left')
                ->join('orders o', 'o.id = rh.order_id', 'left')
                ->join('karigars k', 'k.id = rh.karigar_id', 'left');
            $this->applyPartyFilters($builder, 'rh', 'return_from', $karigarId, $karigarName, $orderNo, $product);
            $rows = array_merge($rows, $builder->get()->getResultArray());
        }

        return array_values(array_filter($rows, static fn(array $row): bool => trim((string) ($row['product'] ?? '')) !== ''));
    }

    private function applyPartyFilters($builder, string $alias, string $partyField, int $karigarId, string $karigarName, string $orderNo, string $product): void
    {
        if ($karigarId > 0) {
            $builder->groupStart()->where($alias . '.karigar_id', $karigarId);
            if ($karigarName !== '') $builder->orLike($alias . '.' . $partyField, $karigarName);
            $builder->groupEnd();
        }
        if ($orderNo !== '') $builder->like('o.order_no', $orderNo);
        $this->applyProductFilter($builder, $product);
    }

    private function applyProductFilter($builder, string $product): void
    {
        if ($product !== '') {
            $builder->where("COALESCE(NULLIF(TRIM(i.clarity), ''), NULLIF(TRIM(i.diamond_type), ''))", $product);
        }
    }
}
