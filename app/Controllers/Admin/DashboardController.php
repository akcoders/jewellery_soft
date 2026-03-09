<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CustomerModel;
use App\Models\LeadFollowupModel;
use App\Models\LeadModel;
use App\Models\OrderModel;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $leadModel       = new LeadModel();
        $customerModel   = new CustomerModel();
        $orderModel      = new OrderModel();
        $followupModel   = new LeadFollowupModel();
        $todayDateTime   = date('Y-m-d H:i:s');

        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd   = date('Y-m-d 23:59:59');

        $counts = [
            'openLeads'       => $leadModel->where('status', 'Open')->countAllResults(),
            'customers'       => $customerModel->where('is_active', 1)->countAllResults(),
            'activeOrders'    => $orderModel->whereIn('status', ['Confirmed', 'In Production', 'QC', 'Ready', 'Packed'])->countAllResults(),
            'dispatchedToday' => $orderModel->where('status', 'Dispatched')->where('updated_at >=', $todayStart)->where('updated_at <=', $todayEnd)->countAllResults(),
        ];

        $db = db_connect();
        $activeWorkStatuses = ['Confirmed', 'In Production', 'QC', 'Ready', 'Packed'];

        $fineGoldAll = 0.0;
        if ($db->tableExists('gold_inventory_stock') && $db->tableExists('gold_inventory_items')) {
            $fields = $db->getFieldNames('gold_inventory_stock');
            if (in_array('fine_balance_gm', $fields, true)) {
                $fineGoldRow = $db->table('gold_inventory_stock')
                    ->select('COALESCE(SUM(fine_balance_gm),0) as total_fine', false)
                    ->get()
                    ->getRowArray();
                $fineGoldAll = (float) ($fineGoldRow['total_fine'] ?? 0);
            } else {
                $fineGoldRow = $db->table('gold_inventory_stock gis')
                    ->select('COALESCE(SUM(gis.weight_balance_gm * (COALESCE(gi.purity_percent,100)/100)),0) as total_fine', false)
                    ->join('gold_inventory_items gi', 'gi.id = gis.item_id', 'left')
                    ->get()
                    ->getRowArray();
                $fineGoldAll = (float) ($fineGoldRow['total_fine'] ?? 0);
            }
        }

        $currentReq = 0.0;
        if ($db->tableExists('order_items') && $db->tableExists('orders')) {
            $requiredRows = $db->table('order_items oi')
                ->select('oi.order_id, COALESCE(SUM(oi.gold_required_gm),0) as total_required', false)
                ->join('orders o', 'o.id = oi.order_id', 'inner')
                ->whereIn('o.status', $activeWorkStatuses)
                ->groupBy('oi.order_id')
                ->get()
                ->getResultArray();

            $issuedByOrder = [];
            if ($db->tableExists('gold_inventory_issue_headers') && $db->tableExists('gold_inventory_issue_lines')) {
                $issueRows = $db->table('gold_inventory_issue_headers ih')
                    ->select('ih.order_id, COALESCE(SUM(il.weight_gm),0) as total_issued', false)
                    ->join('gold_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                    ->join('orders o', 'o.id = ih.order_id', 'inner')
                    ->whereIn('o.status', $activeWorkStatuses)
                    ->groupBy('ih.order_id')
                    ->get()
                    ->getResultArray();

                foreach ($issueRows as $row) {
                    $issuedByOrder[(int) ($row['order_id'] ?? 0)] = (float) ($row['total_issued'] ?? 0);
                }
            }

            if ($db->tableExists('order_material_movements')) {
                $issuedRows = $db->table('order_material_movements omm')
                    ->select('omm.order_id, COALESCE(SUM(omm.gold_gm),0) as total_issued', false)
                    ->join('orders o', 'o.id = omm.order_id', 'inner')
                    ->whereIn('o.status', $activeWorkStatuses)
                    ->where('omm.movement_type', 'issue')
                    ->groupBy('omm.order_id')
                    ->get()
                    ->getResultArray();

                foreach ($issuedRows as $row) {
                    $orderId = (int) ($row['order_id'] ?? 0);
                    if (! isset($issuedByOrder[$orderId])) {
                        $issuedByOrder[$orderId] = 0.0;
                    }
                    $issuedByOrder[$orderId] += (float) ($row['total_issued'] ?? 0);
                }
            }

            foreach ($requiredRows as $row) {
                $orderId = (int) ($row['order_id'] ?? 0);
                $required = (float) ($row['total_required'] ?? 0);
                $issued = (float) ($issuedByOrder[$orderId] ?? 0);
                $currentReq += max(0, $required - $issued);
            }
        }

        $avgPurePrice = 0.0;
        if ($db->tableExists('gold_inventory_purchase_lines') && $db->tableExists('gold_inventory_items')) {
            $avgPriceRow = $db->table('gold_inventory_purchase_lines pl')
                ->select('COALESCE(SUM(pl.line_value),0) as total_value, COALESCE(SUM(CASE WHEN pl.fine_weight_gm IS NOT NULL AND pl.fine_weight_gm > 0 THEN pl.fine_weight_gm ELSE (pl.weight_gm * (COALESCE(gi.purity_percent, gp.purity_percent, 100)/100)) END),0) as total_fine_weight', false)
                ->join('gold_inventory_items gi', 'gi.id = pl.item_id', 'left')
                ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
                ->get()
                ->getRowArray();
            $avgFineWeight = (float) ($avgPriceRow['total_fine_weight'] ?? 0);
            $avgValue = (float) ($avgPriceRow['total_value'] ?? 0);
            $avgPurePrice = $avgFineWeight > 0 ? ($avgValue / $avgFineWeight) : 0.0;
        }

        $minusKarigarCount = 0;
        $minusKarigarGold = 0.0;
        if ($db->tableExists('account_balances') && $db->tableExists('accounts')) {
            $minusRows = $db->table('account_balances ab')
                ->select('ab.account_id, ab.qty_weight')
                ->join('accounts a', 'a.id = ab.account_id', 'inner')
                ->where('a.account_type', 'KARIGAR')
                ->where('ab.item_type', 'GOLD')
                ->where('ab.qty_weight <', 0)
                ->get()
                ->getResultArray();

            $minusKarigarCount = count($minusRows);
            foreach ($minusRows as $row) {
                $minusKarigarGold += abs((float) ($row['qty_weight'] ?? 0));
            }
        } elseif ($db->tableExists('gold_inventory_issue_headers') && $db->tableExists('gold_inventory_issue_lines') && $db->tableExists('gold_inventory_return_headers') && $db->tableExists('gold_inventory_return_lines')) {
            $issueRows = $db->table('gold_inventory_issue_headers ih')
                ->select('ih.karigar_id, COALESCE(SUM(il.weight_gm),0) as issue_gm', false)
                ->join('gold_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->groupBy('ih.karigar_id')
                ->get()
                ->getResultArray();
            $returnRows = $db->table('gold_inventory_return_headers rh')
                ->select('rh.karigar_id, COALESCE(SUM(rl.weight_gm),0) as return_gm', false)
                ->join('gold_inventory_return_lines rl', 'rl.return_id = rh.id', 'inner')
                ->groupBy('rh.karigar_id')
                ->get()
                ->getResultArray();

            $balanceMap = [];
            foreach ($issueRows as $row) {
                $kid = (int) ($row['karigar_id'] ?? 0);
                if ($kid <= 0) {
                    continue;
                }
                $balanceMap[$kid] = (float) ($balanceMap[$kid] ?? 0) + (float) ($row['issue_gm'] ?? 0);
            }
            foreach ($returnRows as $row) {
                $kid = (int) ($row['karigar_id'] ?? 0);
                if ($kid <= 0) {
                    continue;
                }
                $balanceMap[$kid] = (float) ($balanceMap[$kid] ?? 0) - (float) ($row['return_gm'] ?? 0);
            }
            foreach ($balanceMap as $bal) {
                if ($bal < 0) {
                    $minusKarigarCount++;
                    $minusKarigarGold += abs((float) $bal);
                }
            }
        }

        $overdueFollowups = $followupModel
            ->select('lead_followups.*, leads.name as lead_name, leads.phone as lead_phone')
            ->join('leads', 'leads.id = lead_followups.lead_id', 'left')
            ->where('lead_followups.status', 'Pending')
            ->where('lead_followups.followup_at <', $todayDateTime)
            ->orderBy('lead_followups.followup_at', 'ASC')
            ->findAll(10);

        $recentOrders = $orderModel
            ->select('orders.*, customers.name as customer_name')
            ->join('customers', 'customers.id = orders.customer_id', 'left')
            ->orderBy('orders.id', 'DESC')
            ->findAll(8);

        return view('admin/dashboard/index', [
            'title'          => 'Admin Dashboard',
            'counts'         => $counts,
            'goldCards'      => [
                'fine_gold_total' => round($fineGoldAll, 3),
                'current_req_gold' => round($currentReq, 3),
                'avg_price_pure' => round($avgPurePrice, 2),
                'minus_karigar_count' => (int) $minusKarigarCount,
                'minus_karigar_gold' => round($minusKarigarGold, 3),
            ],
            'overdueFollowups' => $overdueFollowups,
            'recentOrders'   => $recentOrders,
        ]);
    }
}
