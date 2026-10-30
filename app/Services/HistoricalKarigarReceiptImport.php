<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/** Imports only the supplied, reviewed August–September 2026 scanned bills. */
final class HistoricalKarigarReceiptImport
{
    private const DATA = APPPATH . 'Database/Data/karigar-receipts-20260809/';
    private const CUSTOMER = 'Mahalaxmi Gold and Jewellers Hyderabad';
    private const LOCK = 'aabhushan-karigar-scans-aug-sep-2026';
    // These identities were individually checked against the source packing
    // list, including material details. Never use local numeric database IDs.
    private const VERIFIED_MATCHES = [
        'SCAN_20261030_115405408.pdf:1' => 'PL26-SHREE-GOURANGO-G09-R120',
        'SCAN_20261030_115405408.pdf:2' => 'PL26-SHREE-GOURANGO-G09-R124',
        'SCAN_20261030_115405408.pdf:3' => 'PL26-SHREE-GOURANGO-G09-R128',
        'SCAN_20261030_115405408.pdf:4' => 'PL26-SHREE-GOURANGO-G09-R131',
        'SCAN_20261030_115100551.pdf:1' => 'PL26-SHREE-GOURANGO-G10-R140',
        'SCAN_20261030_114805621.pdf:1' => 'PL26-RHEEA-G06-R54',
        'SCAN_20261030_114902083.pdf:1' => 'PL26-UTTAM-MAL-G12-R176',
        'SCAN_20261030_114902083.pdf:2' => 'PL26-UTTAM-MAL-G12-R181',
        'SCAN_20261030_114902083.pdf:3' => 'PL26-UTTAM-MAL-G12-R186',
    ];
    // This July item matches the photo, design date, net weight, karat and
    // stones; the gross difference equals the diamond weight on the prior receipt.
    private const VERIFIED_PARTIAL = [
        'SCAN_20261030_115302174.pdf:1' => 'PL26-SAFWAN-JEWELLERY-G03-R34',
    ];

    public function __construct(private BaseConnection $db) {}

    /** @return array{created:list<string>,duplicates:list<string>,linked:list<string>,ambiguous:list<string>} */
    public function run(): array
    {
        $locked = $this->db->query('SELECT GET_LOCK(?, 15) AS acquired', [self::LOCK])->getRowArray();
        if ((int) ($locked['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Could not acquire historical receiving import lock.');
        }
        try {
            return $this->runLocked();
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [self::LOCK]);
        }
    }

    private function runLocked(): array
    {
        $bills = [];
        foreach (['august.json', 'september.json'] as $file) {
            $path = self::DATA . $file;
            $raw = file_get_contents($path);
            if ($raw === false) {
                throw new RuntimeException('Missing historical bill manifest: ' . $file);
            }
            $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $bills = array_merge($bills, $manifest['bills'] ?? []);
        }
        if (count($bills) !== 12) {
            throw new RuntimeException('The historical bill manifest must contain exactly 12 bills.');
        }
        $customer = $this->unique('customers', ['name' => self::CUSTOMER, 'is_active' => 1], 'customer');
        $location = $this->unique('inventory_locations', ['code' => 'MAIN', 'is_active' => 1], 'receiving location');
        $result = ['created' => [], 'duplicates' => [], 'linked' => [], 'ambiguous' => []];
        $this->db->transException(true)->transStart();
        try {
            foreach ($bills as $bill) {
                $date = (string) ($bill['invoice_date'] ?? '');
                if ($date < '2026-08-01' || $date >= '2026-10-01' || ! preg_match('/^2026-(08|09)-\d{2}$/', $date)) {
                    throw new RuntimeException('Out-of-scope invoice date: ' . $date);
                }
                $source = basename((string) ($bill['file'] ?? $bill['source_pdf'] ?? ''));
                $invoice = trim((string) ($bill['invoice_no'] ?? ''));
                $karigar = $this->unique('karigars', ['name' => $bill['karigar_master_name'] ?? $bill['karigar'], 'is_active' => 1], 'karigar');
                if ($source === '' || $invoice === '' || empty($bill['items'])) {
                    throw new RuntimeException('Missing source invoice identity or item rows.');
                }
                foreach ($bill['items'] as $index => $rawItem) {
                    $line = $index + 1;
                    $identity = $source . ':' . $line;
                    $orderNo = 'SCAN-' . strtoupper(substr(hash('sha256', $identity), 0, 24));
                    $item = $this->normalize($rawItem);
                    $purity = $this->unique('gold_purities', ['purity_code' => $item['karat'] . 'K', 'is_active' => 1], 'gold purity');
                    $existing = $this->db->table('orders')->where('order_no', $orderNo)->get()->getRowArray();
                    if ($existing) {
                        $summary = $this->db->table('order_receive_summaries')->where('order_id', (int) $existing['id'])->get()->getRowArray();
                        if (! $summary || (int) $existing['assigned_karigar_id'] !== (int) $karigar['id'] || (int) $existing['customer_id'] !== (int) $customer['id']
                            || (int) $summary['gold_purity_id'] !== (int) $purity['id']
                            || abs((float) $summary['gross_weight_gm'] - $item['gross']) >= 0.0005
                            || abs((float) $summary['net_gold_weight_gm'] - $item['net']) >= 0.0005
                            || abs((float) $summary['pure_gold_weight_gm'] - $item['pure']) >= 0.0005
                            || abs((float) $summary['diamond_weight_cts'] - $item['diamond']) >= 0.0005
                            || abs((float) $summary['stone_weight_cts'] - $item['stone']) >= 0.0005
                            || (int) $summary['account_voucher_id'] <= 0
                            || $this->db->table('fg_items')->where('order_id', (int) $existing['id'])->countAllResults() !== 1) {
                            throw new RuntimeException('Incomplete or conflicting existing import for ' . $identity);
                        }
                        $result['duplicates'][] = $identity . ' (already imported)';
                        continue;
                    }
                    $candidate = $this->findCandidates((int) $karigar['id'], $item);
                    if ($candidate['exact'] !== []) {
                        $known = self::VERIFIED_MATCHES[$identity] ?? null;
                        if ($known === null || count($candidate['exact']) !== 1 || $candidate['exact'][0] !== $known || $candidate['partial'] !== []) {
                            throw new RuntimeException('Unreviewed receiving candidate for ' . $identity . ': ' . json_encode($candidate));
                        }
                        $this->verifyExistingCustomer($known, (int) $customer['id']);
                        if ($this->linkInScopeCustomer($known, (int) $customer['id'])) {
                            $result['linked'][] = $known;
                        }
                        $result['duplicates'][] = $identity . ' (existing order ' . implode(', ', $candidate['exact']) . ')';
                        continue;
                    }
                    if ($candidate['partial'] !== []) {
                        $known = self::VERIFIED_PARTIAL[$identity] ?? null;
                        if ($known === null || count($candidate['partial']) !== 1 || $candidate['partial'][0] !== $known) {
                            throw new RuntimeException('Unreviewed receiving candidate for ' . $identity . ': ' . json_encode($candidate));
                        }
                        $this->verifyExistingCustomer($known, (int) $customer['id']);
                        $this->verifySafwanSource($known);
                        $result['duplicates'][] = $identity . ' (verified historical order ' . $known . ')';
                        continue;
                    }
                    if (isset(self::VERIFIED_MATCHES[$identity]) || isset(self::VERIFIED_PARTIAL[$identity])) {
                        throw new RuntimeException('Expected verified historical order missing for ' . $identity . '; review this target before import.');
                    }
                    $billProvenance = $bill;
                    unset($billProvenance['items']);
                    $this->insertItem($orderNo, $identity, $invoice, $date, $item, $customer, $karigar, $location, $purity, $billProvenance);
                    $result['created'][] = $identity . ' => ' . $orderNo;
                }
            }
            $this->db->transComplete();
            if (! $this->db->transStatus()) {
                throw new RuntimeException('Historical receiving import transaction failed.');
            }
        } catch (Throwable $e) {
            // PostingService opens a nested transaction. Unwind every level so
            // an exception inside it also rolls back the outer import.
            while ($this->db->transRollback()) {
            }
            throw $e;
        }
        return $result;
    }

    private function unique(string $table, array $criteria, string $label): array
    {
        $rows = $this->db->table($table)->where($criteria)->get()->getResultArray();
        if (count($rows) !== 1) {
            throw new RuntimeException('Expected exactly one active ' . $label . ': ' . json_encode($criteria));
        }
        return $rows[0];
    }

    private function verifyExistingCustomer(string $orderNo, int $customerId): void
    {
        $order = $this->db->table('orders')->where('order_no', $orderNo)->get()->getRowArray();
        if (! $order || ((int) ($order['customer_id'] ?? 0) > 0 && (int) $order['customer_id'] !== $customerId)) {
            throw new RuntimeException('Existing historical order has a different customer: ' . $orderNo);
        }
    }

    /** Only attach reviewed existing orders dated August/September; never touch July. */
    private function linkInScopeCustomer(string $orderNo, int $customerId): bool
    {
        $order = $this->db->table('orders')->where('order_no', $orderNo)->get()->getRowArray();
        if (! $order || (int) ($order['customer_id'] ?? 0) > 0) { return false; }
        $receipts = $this->db->table('order_material_movements')->where('order_id', (int) $order['id'])->where('movement_type', 'receive')->get()->getResultArray();
        if (count($receipts) !== 1) { throw new RuntimeException('Cannot confirm date of existing receipt ' . $orderNo); }
        $date = substr((string) ($receipts[0]['created_at'] ?? ''), 0, 10);
        if ($date < '2026-08-01' || $date >= '2026-10-01') { return false; }
        $this->db->table('orders')->where('id', (int) $order['id'])->where('customer_id', null)->update(['customer_id' => $customerId, 'updated_at' => date('Y-m-d H:i:s')]);
        return $this->db->affectedRows() === 1;
    }

    private function verifySafwanSource(string $orderNo): void
    {
        $rows = $this->db->table('production_ready_items p')->select('p.*')
            ->join('orders o', 'o.id=p.order_id')->where('o.order_no', $orderNo)->get()->getResultArray();
        if (count($rows) !== 1) { throw new RuntimeException('Safwan source row is missing or duplicated.'); }
        $row = $rows[0];
        $components = json_decode((string) $row['stones_json'], true);
        if (! is_array($components) || (string) $row['purity_label'] !== '14K'
            || abs((float) $row['gross_weight_gm'] - 7.230) >= 0.0005
            || abs((float) $row['net_weight_gm'] - 6.560) >= 0.0005
            || abs((float) $row['pure_weight_gm'] - 4.920) >= 0.0005) {
            throw new RuntimeException('Safwan source row no longer matches the reviewed receipt.');
        }
        foreach ([['GREEN', 1, 0.60], ['RED', 1, 0.03], ['RUBY', 8, 0.25], ['BEADS', 1, 0.05]] as [$name, $pcs, $weight]) {
            $matches = array_filter($components, static fn($part): bool => strtoupper((string) ($part['name'] ?? '')) === $name && abs((float) ($part['pcs'] ?? 0) - $pcs) < 0.0005 && abs((float) ($part['weight_cts'] ?? 0) - $weight) < 0.0005);
            if (count($matches) !== 1) { throw new RuntimeException('Safwan source stones no longer match.'); }
        }
    }

    /** Preserve the printed chain as part of the same finished item. */
    private function normalize(array $item): array
    {
        $gross = (float) ($item['gross_g'] ?? $item['gross_weight_g'] ?? 0);
        $net = (float) ($item['net_g'] ?? $item['net_weight_g'] ?? 0) + (float) ($item['chain_net_g_as_printed'] ?? 0);
        $pure = (float) ($item['pure_g_as_printed'] ?? $item['pure_weight_g'] ?? 0) + (float) ($item['chain_pure_g_as_printed'] ?? 0);
        $gold = (float) ($item['gold_amount_sheet_inr'] ?? $item['gold_amount_inr'] ?? 0) + (float) ($item['additional_chain_gold_amount_sheet_inr'] ?? 0);
        $labour = (float) ($item['labour_sheet_inr'] ?? $item['labour_amount_inr'] ?? 0);
        $total = (float) ($item['total_value_sheet_inr'] ?? $item['total_value_inr'] ?? 0);
        $karat = (int) ($item['karat'] ?? 0);
        $components = [];
        $diamond = $diamondPcs = $stone = $otherGm = 0.0;
        $diamondValue = $stoneValue = $otherValue = 0.0;
        foreach (($item['components'] ?? $item['studs'] ?? []) as $row) {
            $name = trim((string) ($row['type_as_printed'] ?? $row['type'] ?? ''));
            $weight = (float) ($row['weight_ct'] ?? 0);
            $pcs = (float) ($row['pieces'] ?? 0);
            $rate = (float) ($row['rate_as_printed'] ?? $row['rate_inr_per_ct'] ?? 0);
            $amount = (float) ($row['amount_as_printed'] ?? $row['amount_inr'] ?? 0);
            if ($name === '' || $weight < 0 || $pcs < 0 || $rate < 0 || $amount < 0) {
                throw new RuntimeException('Invalid component on scanned invoice.');
            }
            $type = preg_match('/diamond|\bCVD\b/i', $name) ? 'diamond' : (in_array(strtoupper($name), ['OW', 'BB', 'SILVER'], true) ? 'other' : 'stone');
            if ($type === 'diamond') { $diamond += $weight; $diamondPcs += $pcs; $diamondValue += $amount; }
            elseif ($type === 'stone') { $stone += $weight; $stoneValue += $amount; }
            else { $otherGm += $weight * 0.2; $otherValue += $amount; }
            $components[] = compact('name', 'type', 'weight', 'pcs', 'rate', 'amount');
        }
        $weightDifference = round($gross - $net - 0.2 * ($diamond + $stone) - $otherGm, 3);
        if ($gross <= 0 || $net <= 0 || $pure <= 0 || ! in_array($karat, [14, 18, 22, 24], true) || abs($weightDifference) > 0.003) {
            throw new RuntimeException('Invalid or unreconciled weight in ' . json_encode($item));
        }
        return compact('gross', 'net', 'pure', 'gold', 'labour', 'total', 'karat', 'components', 'diamond', 'diamondPcs', 'stone', 'otherGm', 'diamondValue', 'stoneValue', 'otherValue') + ['description' => (string) ($item['description'] ?? 'Jewellery'), 'source' => $item];
    }

    /** Weight matches are candidates; full matching weight and purity is a strong duplicate. */
    private function findCandidates(int $karigarId, array $item): array
    {
        $rows = $this->db->query(
            'SELECT DISTINCT o.order_no, COALESCE(r.gross_weight_gm,m.gross_weight_gm) gross, COALESCE(r.net_gold_weight_gm,m.net_gold_weight_gm,m.gold_gm) net, COALESCE(r.pure_gold_weight_gm,m.pure_gold_weight_gm) pure, COALESCE(g.purity_code,p.purity_label) purity FROM order_material_movements m JOIN orders o ON o.id=m.order_id LEFT JOIN order_receive_summaries r ON r.movement_id=m.id LEFT JOIN gold_purities g ON g.id=COALESCE(r.gold_purity_id,m.gold_purity_id) LEFT JOIN production_ready_items p ON p.order_id=o.id WHERE m.movement_type=? AND COALESCE(m.karigar_id,o.assigned_karigar_id)=? AND (ABS(COALESCE(r.gross_weight_gm,m.gross_weight_gm)-?) < 0.0005 OR ABS(COALESCE(r.net_gold_weight_gm,m.net_gold_weight_gm,m.gold_gm)-?) < 0.0005 OR ABS(m.gross_weight_gm-?) < 0.0005 OR ABS(m.net_gold_weight_gm-?) < 0.0005 OR ABS(m.gold_gm-?) < 0.0005) UNION SELECT DISTINCT o.order_no, r.gross_weight_gm gross, r.net_gold_weight_gm net, r.pure_gold_weight_gm pure, COALESCE(g.purity_code,p.purity_label) purity FROM order_receive_summaries r LEFT JOIN order_material_movements m ON m.id=r.movement_id JOIN orders o ON o.id=r.order_id LEFT JOIN gold_purities g ON g.id=r.gold_purity_id LEFT JOIN production_ready_items p ON p.order_id=o.id WHERE m.id IS NULL AND o.assigned_karigar_id=? AND (ABS(r.gross_weight_gm-?) < 0.0005 OR ABS(r.net_gold_weight_gm-?) < 0.0005)',
            ['receive', $karigarId, $item['gross'], $item['net'], $item['gross'], $item['net'], $item['net'], $karigarId, $item['gross'], $item['net']]
        )->getResultArray();
        $exact = $partial = [];
        foreach ($rows as $row) {
            $all = abs((float) $row['gross'] - $item['gross']) < 0.0005 && abs((float) $row['net'] - $item['net']) < 0.0005 && abs((float) $row['pure'] - $item['pure']) < 0.0005 && preg_match('/\b' . $item['karat'] . '\s*K(?:T)?\b/i', (string) $row['purity']);
            if ($all) { $exact[] = $row['order_no']; } else { $partial[] = $row['order_no']; }
        }
        return ['exact' => array_values(array_unique($exact)), 'partial' => array_values(array_unique($partial))];
    }

    private function insertItem(string $orderNo, string $identity, string $invoice, string $date, array $item, array $customer, array $karigar, array $location, array $purity, array $bill): void
    {
        $time = $date . ' 12:00:00';
        $now = date('Y-m-d H:i:s');
        $notes = 'Historical scan ' . $identity . ', invoice ' . $invoice . '. Invoice date used as receiving date; actual order/receipt time unknown. Printed pure weight used; no additional wastage inferred. Quantity is one printed item/group, not a confirmed piece count. Valuation is from packing sheet; unstated item labour recorded as zero. Four missing Uttam bill 11 gold amounts set to zero per instruction. Source item: ' . json_encode($item['source'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ' Bill: ' . json_encode($bill, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->db->table('orders')->insert(['order_no' => $orderNo, 'order_name' => $item['description'], 'order_type' => 'Manufacturing', 'order_from' => 'Historical karigar bill scan', 'order_received_date' => $date, 'customer_id' => (int) $customer['id'], 'assigned_karigar_id' => (int) $karigar['id'], 'status' => 'Completed', 'receiving_status' => 'received', 'received_at' => $time, 'priority' => 'Medium', 'order_notes' => $notes, 'created_at' => $now, 'updated_at' => $now]);
        $orderId = (int) $this->db->insertID();
        $this->db->table('order_items')->insert(['order_id' => $orderId, 'gold_purity_id' => (int) $purity['id'], 'item_description' => $item['description'], 'qty' => 1, 'gold_required_gm' => $item['net'], 'diamond_required_cts' => $item['diamond'], 'item_status' => 'Completed', 'created_at' => $now, 'updated_at' => $now]);
        $itemId = (int) $this->db->insertID();
        $this->db->table('job_cards')->insert(['job_card_no' => 'JC-' . $orderNo, 'order_id' => $orderId, 'order_item_id' => $itemId, 'status' => 'Completed', 'priority' => 'Medium', 'qc_status' => 'Pending', 'created_at' => $now, 'updated_at' => $now]);
        $this->db->table('order_material_movements')->insert(['order_id' => $orderId, 'movement_type' => 'receive', 'gold_gm' => $item['net'], 'diamond_cts' => $item['diamond'], 'gold_purity_id' => (int) $purity['id'], 'karigar_id' => (int) $karigar['id'], 'location_id' => (int) $location['id'], 'gross_weight_gm' => $item['gross'], 'other_weight_gm' => round($item['stone'] * 0.2 + $item['otherGm'], 3), 'diamond_weight_gm' => round($item['diamond'] * 0.2, 3), 'net_gold_weight_gm' => $item['net'], 'pure_gold_weight_gm' => $item['pure'], 'notes' => $notes, 'created_at' => $time, 'updated_at' => $now]);
        $movementId = (int) $this->db->insertID();
        $voucher = (new KarigarMaterialAccountingService($this->db))->postFinishedJewelleryReceipt($orderId, (int) $karigar['id'], (int) $location['id'], $item['pure'], $item['diamondPcs'], $item['diamond'], 'Historical receiving ' . $identity . ' invoice ' . $invoice, 0, $date, $item['stone'], true);
        $this->db->table('order_receive_summaries')->insert(['movement_id' => $movementId, 'account_voucher_id' => $voucher, 'stone_account_voucher_id' => $item['stone'] > 0 ? $voucher : null, 'order_id' => $orderId, 'gold_purity_id' => (int) $purity['id'], 'purity_percent' => (float) $purity['purity_percent'], 'effective_purity_percent' => round($item['pure'] / $item['net'] * 100, 3), 'gross_weight_gm' => $item['gross'], 'net_gold_weight_gm' => $item['net'], 'pure_gold_weight_gm' => $item['pure'], 'diamond_weight_cts' => $item['diamond'], 'diamond_weight_gm' => round($item['diamond'] * 0.2, 3), 'stone_weight_cts' => $item['stone'], 'stone_weight_gm' => round($item['stone'] * 0.2, 3), 'other_weight_gm' => round($item['otherGm'], 3), 'diamond_amount' => $item['diamondValue'], 'stone_amount' => $item['stoneValue'], 'other_amount' => $item['otherValue'], 'gold_amount' => $item['gold'], 'labour_rate_per_gm' => round($item['labour'] / $item['net'], 2), 'labour_amount' => $item['labour'], 'total_valuation' => $item['total'], 'created_at' => $time, 'updated_at' => $now]);
        foreach ($item['components'] as $component) {
            $this->db->table('order_receive_details')->insert(['movement_id' => $movementId, 'order_id' => $orderId, 'component_type' => $component['type'], 'component_name' => $component['name'], 'pcs' => $component['pcs'], 'weight_cts' => $component['weight'], 'weight_gm' => round($component['weight'] * 0.2, 3), 'rate' => $component['rate'], 'line_total' => $component['amount'], 'created_at' => $time, 'updated_at' => $now]);
        }
        $this->db->table('order_status_history')->insert(['order_id' => $orderId, 'from_status' => 'Ready', 'to_status' => 'Completed', 'remarks' => 'Imported historical karigar receiving from ' . $identity . '; invoice date used as receipt date.', 'created_at' => $time, 'updated_at' => $now]);
        $fg = (new FinishedJewelleryService($this->db))->createForCompletedOrder($orderId);
        if (! $fg) {
            throw new RuntimeException('Finished jewellery creation failed for ' . $identity);
        }
    }
}
