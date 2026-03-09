<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class BackfillReceiveSnapshots extends BaseCommand
{
    protected $group = 'Data';
    protected $name = 'backfill:receive-snapshots';
    protected $description = 'Backfill order receive summaries/details from old receive movements.';

    public function run(array $params)
    {
        $db = Database::connect();
        if (! $db->tableExists('order_material_movements')) {
            CLI::error('order_material_movements table not found.');
            return;
        }
        if (! $db->tableExists('order_receive_summaries') || ! $db->tableExists('order_receive_details')) {
            CLI::error('Run migrations first: order_receive_summaries/order_receive_details missing.');
            return;
        }

        $movements = $db->table('order_material_movements omm')
            ->select('omm.*, o.status as order_status')
            ->join('orders o', 'o.id = omm.order_id', 'left')
            ->where('omm.movement_type', 'receive')
            ->orderBy('omm.id', 'ASC')
            ->get()
            ->getResultArray();

        if ($movements === []) {
            CLI::write('No receive movements found.');
            return;
        }

        $done = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($movements as $movement) {
            $movementId = (int) ($movement['id'] ?? 0);
            $orderId = (int) ($movement['order_id'] ?? 0);
            if ($movementId <= 0 || $orderId <= 0) {
                $skipped++;
                continue;
            }

            $exists = $db->table('order_receive_summaries')->where('movement_id', $movementId)->countAllResults();
            if ($exists > 0) {
                $skipped++;
                continue;
            }

            try {
                $db->transStart();

                $parsed = $this->parseReceiveNotes((string) ($movement['notes'] ?? ''));

                $gross = round((float) ($movement['gross_weight_gm'] ?? 0), 3);
                $net = round((float) ($movement['net_gold_weight_gm'] ?? 0), 3);
                $pure = round((float) ($movement['pure_gold_weight_gm'] ?? 0), 3);

                $diamondCts = $parsed['diamond_cts'] > 0
                    ? $parsed['diamond_cts']
                    : round((float) ($movement['diamond_cts'] ?? 0), 3);
                $diamondGm = $parsed['diamond_gm'] > 0
                    ? $parsed['diamond_gm']
                    : round((float) ($movement['diamond_weight_gm'] ?? ($diamondCts * 0.2)), 3);
                $stoneCts = round($parsed['stone_cts'], 3);
                $stoneGm = round($parsed['stone_gm'], 3);

                $otherStored = (float) ($movement['other_weight_gm'] ?? 0);
                $otherGm = $parsed['other_gm'] > 0
                    ? $parsed['other_gm']
                    : round(max(0.0, $otherStored - $stoneGm), 3);
                if ($otherGm <= 0 && $otherStored > 0 && $stoneGm <= 0) {
                    $otherGm = round($otherStored, 3);
                }

                $diamondAmount = round($parsed['diamond_amount'], 2);
                $stoneAmount = round($parsed['stone_amount'], 2);
                $otherAmount = round($parsed['other_amount'], 2);

                $labour = $this->labourFromMovement($db, $movementId);
                $labourRate = $parsed['labour_rate'] > 0 ? $parsed['labour_rate'] : $labour['rate'];
                $labourAmount = $parsed['labour_amount'] > 0 ? $parsed['labour_amount'] : $labour['amount'];
                if ($otherAmount <= 0 && $labour['other'] > 0) {
                    $otherAmount = $labour['other'];
                }

                $goldRate = $parsed['gold_rate'] > 0 ? $parsed['gold_rate'] : $this->avgGoldIssueRateForOrder($db, $orderId);
                $goldAmount = $parsed['gold_amount'] > 0
                    ? $parsed['gold_amount']
                    : round(max(0.0, $net) * max(0.0, $goldRate), 2);

                $totalValuation = round($diamondAmount + $stoneAmount + $otherAmount + $goldAmount + $labourAmount, 2);

                $db->table('order_receive_summaries')->insert([
                    'movement_id' => $movementId,
                    'order_id' => $orderId,
                    'gross_weight_gm' => $gross,
                    'net_gold_weight_gm' => $net,
                    'pure_gold_weight_gm' => $pure,
                    'diamond_weight_cts' => $diamondCts,
                    'diamond_weight_gm' => $diamondGm,
                    'stone_weight_cts' => $stoneCts,
                    'stone_weight_gm' => $stoneGm,
                    'other_weight_gm' => $otherGm,
                    'diamond_amount' => $diamondAmount,
                    'stone_amount' => $stoneAmount,
                    'other_amount' => $otherAmount,
                    'gold_amount' => $goldAmount,
                    'labour_rate_per_gm' => $labourRate,
                    'labour_amount' => $labourAmount,
                    'total_valuation' => $totalValuation,
                    'created_by' => (int) ($movement['created_by'] ?? 0),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $rows = $this->buildDetailRows($diamondCts, $diamondAmount, $stoneCts, $stoneAmount, $otherGm, $otherAmount);
                foreach ($rows as $row) {
                    $db->table('order_receive_details')->insert([
                        'movement_id' => $movementId,
                        'order_id' => $orderId,
                        'component_type' => $row['component_type'],
                        'component_name' => $row['component_name'],
                        'pcs' => 0,
                        'weight_cts' => $row['weight_cts'],
                        'weight_gm' => $row['weight_gm'],
                        'rate' => $row['rate'],
                        'line_total' => $row['line_total'],
                        'created_by' => (int) ($movement['created_by'] ?? 0),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                $db->transComplete();
                if ($db->transStatus() === false) {
                    $errors++;
                    CLI::error("Failed movement #{$movementId}");
                    continue;
                }

                $done++;
            } catch (\Throwable $e) {
                $db->transRollback();
                $errors++;
                CLI::error("Error movement #{$movementId}: " . $e->getMessage());
            }
        }

        CLI::write("Backfill complete. inserted={$done}, skipped={$skipped}, errors={$errors}", 'green');
    }

    /**
     * @return array<string,float>
     */
    private function parseReceiveNotes(string $notes): array
    {
        $out = [
            'diamond_gm' => 0.0,
            'diamond_cts' => 0.0,
            'stone_gm' => 0.0,
            'stone_cts' => 0.0,
            'other_gm' => 0.0,
            'diamond_amount' => 0.0,
            'stone_amount' => 0.0,
            'gold_rate' => 0.0,
            'gold_amount' => 0.0,
            'labour_rate' => 0.0,
            'labour_amount' => 0.0,
            'other_amount' => 0.0,
        ];

        if ($notes === '') {
            return $out;
        }

        if (preg_match('/Diamond\s+([0-9.]+)\s*gm\s*\[([0-9.]+)\s*cts\]\s*\+\s*Stone\s+([0-9.]+)\s*gm\s*\[([0-9.]+)\s*cts\]\s*\+\s*Other\s+([0-9.]+)\s*gm/i', $notes, $m) === 1) {
            $out['diamond_gm'] = round((float) ($m[1] ?? 0), 3);
            $out['diamond_cts'] = round((float) ($m[2] ?? 0), 3);
            $out['stone_gm'] = round((float) ($m[3] ?? 0), 3);
            $out['stone_cts'] = round((float) ($m[4] ?? 0), 3);
            $out['other_gm'] = round((float) ($m[5] ?? 0), 3);
        }

        if (preg_match('/Sections:\s*Diamond\s+Amt\s+([0-9.]+)\s*\|\s*Stone\s+Amt\s+([0-9.]+)\s*\|\s*Gold\s+Rate\s+([0-9.]+)\s*\|\s*Gold\s+Amt\s+([0-9.]+)\s*\|\s*Labour\s+Rate\s+([0-9.]+)\s*\|\s*Labour\s+Total\s+([0-9.]+)\s*\|\s*Other\s+Bill\s+([0-9.]+)/i', $notes, $m) === 1) {
            $out['diamond_amount'] = round((float) ($m[1] ?? 0), 2);
            $out['stone_amount'] = round((float) ($m[2] ?? 0), 2);
            $out['gold_rate'] = round((float) ($m[3] ?? 0), 2);
            $out['gold_amount'] = round((float) ($m[4] ?? 0), 2);
            $out['labour_rate'] = round((float) ($m[5] ?? 0), 2);
            $out['labour_amount'] = round((float) ($m[6] ?? 0), 2);
            $out['other_amount'] = round((float) ($m[7] ?? 0), 2);
            return $out;
        }

        if (preg_match('/Sections:\s*Diamond\s+Amt\s+([0-9.]+)\s*\|\s*Stone\s+Amt\s+([0-9.]+)\s*\|\s*Labour\s+Rate\s+([0-9.]+)\s*\|\s*Labour\s+Total\s+([0-9.]+)\s*\|\s*Other\s+Bill\s+([0-9.]+)/i', $notes, $m) === 1) {
            $out['diamond_amount'] = round((float) ($m[1] ?? 0), 2);
            $out['stone_amount'] = round((float) ($m[2] ?? 0), 2);
            $out['labour_rate'] = round((float) ($m[3] ?? 0), 2);
            $out['labour_amount'] = round((float) ($m[4] ?? 0), 2);
            $out['other_amount'] = round((float) ($m[5] ?? 0), 2);
        }

        return $out;
    }

    /**
     * @return array{rate:float,amount:float,other:float}
     */
    private function labourFromMovement($db, int $movementId): array
    {
        if (! $db->tableExists('labour_bills')) {
            return ['rate' => 0.0, 'amount' => 0.0, 'other' => 0.0];
        }

        $row = $db->table('labour_bills')
            ->select('rate_per_gm, labour_amount, other_amount')
            ->where('receive_movement_id', $movementId)
            ->get()
            ->getRowArray();

        if (! is_array($row)) {
            return ['rate' => 0.0, 'amount' => 0.0, 'other' => 0.0];
        }

        return [
            'rate' => round((float) ($row['rate_per_gm'] ?? 0), 2),
            'amount' => round((float) ($row['labour_amount'] ?? 0), 2),
            'other' => round((float) ($row['other_amount'] ?? 0), 2),
        ];
    }

    private function avgGoldIssueRateForOrder($db, int $orderId): float
    {
        if ($orderId <= 0 || ! $db->tableExists('gold_inventory_issue_headers') || ! $db->tableExists('gold_inventory_issue_lines')) {
            return 0.0;
        }

        $row = $db->table('gold_inventory_issue_headers ih')
            ->select('COALESCE(SUM(il.line_value),0) as amount, COALESCE(SUM(il.weight_gm),0) as wt', false)
            ->join('gold_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
            ->where('ih.order_id', $orderId)
            ->get()
            ->getRowArray();

        $wt = (float) ($row['wt'] ?? 0);
        if ($wt <= 0) {
            return 0.0;
        }

        return round(((float) ($row['amount'] ?? 0)) / $wt, 2);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildDetailRows(
        float $diamondCts,
        float $diamondAmount,
        float $stoneCts,
        float $stoneAmount,
        float $otherGm,
        float $otherAmount
    ): array {
        $rows = [];

        if ($diamondCts > 0 || $diamondAmount > 0) {
            $rows[] = [
                'component_type' => 'diamond',
                'component_name' => 'Backfill Diamond',
                'weight_cts' => round($diamondCts, 3),
                'weight_gm' => round($diamondCts * 0.2, 3),
                'rate' => $diamondCts > 0 ? round($diamondAmount / $diamondCts, 2) : 0.0,
                'line_total' => round($diamondAmount, 2),
            ];
        }
        if ($stoneCts > 0 || $stoneAmount > 0) {
            $rows[] = [
                'component_type' => 'stone',
                'component_name' => 'Backfill Stone',
                'weight_cts' => round($stoneCts, 3),
                'weight_gm' => round($stoneCts * 0.2, 3),
                'rate' => $stoneCts > 0 ? round($stoneAmount / $stoneCts, 2) : 0.0,
                'line_total' => round($stoneAmount, 2),
            ];
        }
        if ($otherGm > 0 || $otherAmount > 0) {
            $rows[] = [
                'component_type' => 'other',
                'component_name' => 'Backfill Other',
                'weight_cts' => 0.0,
                'weight_gm' => round($otherGm, 3),
                'rate' => round($otherAmount, 2),
                'line_total' => round($otherAmount, 2),
            ];
        }

        return $rows;
    }
}

