<?php

namespace App\Controllers\Admin\GoldInventory;

use App\Controllers\BaseController;

class LedgerController extends BaseController
{
    public function index(): string
    {
        $filters = [
            'from' => trim((string) $this->request->getGet('from')),
            'to' => trim((string) $this->request->getGet('to')),
            'txn_type' => trim((string) $this->request->getGet('txn_type')),
            'item_id' => trim((string) $this->request->getGet('item_id')),
            'order_id' => trim((string) $this->request->getGet('order_id')),
            'karigar_id' => trim((string) $this->request->getGet('karigar_id')),
        ];

        $builder = db_connect()->table('gold_inventory_ledger_entries gle')
            ->select("gle.*, gi.purity_code, gi.color_name, gi.form_type, gp.purity_code as master_purity_code, o.order_no, k.name as karigar_name, iloc.name as location_name, COALESCE(gih.voucher_no, gph.invoice_no, grh.voucher_no, CONCAT('ADJ#', gah.id)) as reference_voucher_no", false)
            ->join('gold_inventory_items gi', 'gi.id = gle.item_id', 'left')
            ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
            ->join('orders o', 'o.id = gle.order_id', 'left')
            ->join('karigars k', 'k.id = gle.karigar_id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = gle.location_id', 'left')
            ->join('gold_inventory_issue_headers gih', "gih.id = gle.reference_id AND gle.reference_table = 'gold_inventory_issue_headers'", 'left', false)
            ->join('gold_inventory_purchase_headers gph', "gph.id = gle.reference_id AND gle.reference_table = 'gold_inventory_purchase_headers'", 'left', false)
            ->join('gold_inventory_return_headers grh', "grh.id = gle.reference_id AND gle.reference_table = 'gold_inventory_return_headers'", 'left', false)
            ->join('gold_inventory_adjustment_headers gah', "gah.id = gle.reference_id AND gle.reference_table = 'gold_inventory_adjustment_headers'", 'left', false)
            ->orderBy('gle.txn_date', 'DESC')
            ->orderBy('gle.id', 'DESC');

        if ($filters['from'] !== '') {
            $builder->where('gle.txn_date >=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $builder->where('gle.txn_date <=', $filters['to']);
        }
        if ($filters['txn_type'] !== '') {
            $builder->where('gle.txn_type', $filters['txn_type']);
        }
        if ($filters['item_id'] !== '') {
            $builder->where('gle.item_id', (int) $filters['item_id']);
        }
        if ($filters['order_id'] !== '') {
            $builder->where('gle.order_id', (int) $filters['order_id']);
        }
        if ($filters['karigar_id'] !== '') {
            $builder->where('gle.karigar_id', (int) $filters['karigar_id']);
        }

        $rows = $builder->get()->getResultArray();

        $summary = [
            'debit_weight' => 0.0,
            'credit_weight' => 0.0,
            'balance_weight' => 0.0,
            'debit_fine' => 0.0,
            'credit_fine' => 0.0,
            'balance_fine' => 0.0,
        ];
        foreach ($rows as $row) {
            $summary['debit_weight'] += (float) ($row['debit_weight_gm'] ?? 0);
            $summary['credit_weight'] += (float) ($row['credit_weight_gm'] ?? 0);
            $summary['debit_fine'] += (float) ($row['debit_fine_gm'] ?? 0);
            $summary['credit_fine'] += (float) ($row['credit_fine_gm'] ?? 0);
        }
        $summary['balance_weight'] = $summary['debit_weight'] - $summary['credit_weight'];
        $summary['balance_fine'] = $summary['debit_fine'] - $summary['credit_fine'];

        return view('admin/gold_inventory/ledger/index', [
            'title' => 'Gold Ledger',
            'rows' => $rows,
            'filters' => $filters,
            'summary' => $summary,
            'txnTypes' => $this->distinctTxnTypes(),
            'itemOptions' => $this->itemOptions(),
            'orderOptions' => $this->orderOptions(),
            'karigarOptions' => $this->karigarOptions(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function distinctTxnTypes(): array
    {
        $rows = db_connect()->table('gold_inventory_ledger_entries')
            ->select('txn_type')
            ->distinct()
            ->orderBy('txn_type', 'ASC')
            ->get()
            ->getResultArray();

        $types = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row['txn_type'] ?? ''));
            if ($value !== '') {
                $types[] = $value;
            }
        }

        return $types;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function itemOptions(): array
    {
        return db_connect()->table('gold_inventory_items gi')
            ->select('gi.id, gi.purity_code, gi.color_name, gi.form_type')
            ->orderBy('gi.id', 'DESC')
            ->limit(300)
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function orderOptions(): array
    {
        if (! db_connect()->tableExists('orders')) {
            return [];
        }

        return db_connect()->table('orders')
            ->select('id, order_no')
            ->orderBy('id', 'DESC')
            ->limit(300)
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function karigarOptions(): array
    {
        if (! db_connect()->tableExists('karigars')) {
            return [];
        }

        return db_connect()->table('karigars')
            ->select('id, name')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }
}
