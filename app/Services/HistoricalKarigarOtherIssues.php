<?php

namespace App\Services;

use App\Services\DiamondInventory\StockService as DiamondStockService;
use App\Services\StoneInventory\StockService as StoneStockService;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/** Material issue components evidenced by the August/September 2026 scans. */
final class HistoricalKarigarOtherIssues
{
    private const LOCK = 'aabhushan-scan-other-issues-20260809';
    // Original issue vouchers do not contain stone types: these are inferred
    // from the individually matching, subsequently received packing sheets.
    private const STONES = [
        '033' => ['2026-08-01', 'UTTAM MAL', ['Green' => 77.620, 'Pink' => 35.310]],
        '037' => ['2026-08-11', 'SAFWAN JEWELLERY', ['Green' => 2.400, 'Red' => .120, 'Ruby' => 1.400, 'Beads' => .800]],
        '038' => ['2026-08-19', 'RHEEA JEWELS', ['Cz' => 12.250]],
        '041' => ['2026-08-23', 'JGD DIAMONDS', ['Green' => 1.160]],
        '042' => ['2026-09-01', 'SHREE GOURANGO', ['Blue' => 1.400]],
        '043' => ['2026-09-01', 'UTTAM MAL', ['Green' => .850, 'Pe' => 5.500]],
    ];
    private const STONE_PROVENANCE = [
        '033' => 'Original stone total 182.490 ct; existing SRV-674, SRV-675 and SRV-676 already account for 69.560 ct; this issue records remaining 112.930 ct.',
        '037' => 'Original stone total 5.650 ct; existing SRV-706 accounts for .930 ct; this issue records remaining 4.720 ct.',
    ];
    private const DIAMONDS = [
        '042' => ['2026-09-01', 'SHREE GOURANGO', 6.140, 0],
        '043' => ['2026-09-01', 'UTTAM MAL', .580, 0],
        '044' => ['2026-09-08', 'RHEEA JEWELS', 2.820, 4.720],
        '045' => ['2026-09-09', 'ANANDI JEWELS', 9.620, 0],
    ];
    // Each previously received item has an existing stock-consuming issue.
    // These are verified before excluding its stone from the photographed slip.
    private const PREVIOUS_STONE = [
        'PL26-UTTAM-MAL-G12-R176' => ['UTTAM MAL', ['Green' => 8.140, 'Pink' => 8.830]],
        'PL26-UTTAM-MAL-G12-R181' => ['UTTAM MAL', ['Green' => 4.530, 'Pink' => 3.000]],
        'PL26-UTTAM-MAL-G12-R186' => ['UTTAM MAL', ['Green' => 28.410, 'Pink' => 16.650]],
        'PL26-SHREE-GOURANGO-G09-R131' => ['SHREE GOURANGO', ['Red' => .330]],
        'PL26-SAFWAN-JEWELLERY-G03-R34' => ['SAFWAN JEWELLERY', ['Green' => .600, 'Red' => .030, 'Ruby' => .250, 'Beads' => .050]],
    ];

    public function __construct(private BaseConnection $db) {}

    /** @return array{stone:list<string>,diamond:list<string>,existing:list<string>,pending:list<string>} */
    public function run(): array
    {
        $lock = $this->db->query('SELECT GET_LOCK(?, 15) AS acquired', [self::LOCK])->getRowArray();
        if ((int) ($lock['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Could not acquire scanned material issue lock.');
        }
        try {
            return $this->runLocked();
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [self::LOCK]);
        }
    }

    private function runLocked(): array
    {
        $location = $this->unique('inventory_locations', ['code' => 'MAIN', 'is_active' => 1]);
        $diamond = $this->unique('items', ['diamond_type' => 'Vvs Mix', 'shape' => 'Round', 'color' => 'EFGH', 'clarity' => 'VVS MIX']);
        $labDiamond = $this->unique('items', ['diamond_type' => 'Lab Grown', 'shape' => 'Round', 'clarity' => 'LAB GROWN']);
        $result = ['stone' => [], 'diamond' => [], 'existing' => [], 'pending' => []];
        $this->db->transException(true)->transStart();
        try {
            $this->verifyPreviousStone();
            $stoneIds = [];
            foreach (self::STONES as [, , $quantities]) {
                foreach (array_keys($quantities) as $label) {
                    $stoneIds[] = (int) $this->unique('stone_inventory_items', ['product_name' => $label])['id'];
                }
            }
            $stoneIds = array_values(array_unique($stoneIds));
            sort($stoneIds);
            foreach ($stoneIds as $id) { $this->assertStoneStock($id); }
            $diamondIds = [(int) $diamond['id'], (int) $labDiamond['id']];
            sort($diamondIds);
            foreach ($diamondIds as $id) { $this->assertDiamondStock($id); }
            $checks = $this->accountChecks($location);
            foreach ($checks as [$accountId, $type]) { $this->assertAccountBalance($accountId, $type); }
            $accounting = new KarigarMaterialAccountingService($this->db);
            $stoneStock = new StoneStockService($this->db);
            $diamondStock = new DiamondStockService($this->db);
            foreach (self::STONES as $code => [$date, $name, $quantities]) {
                $party = $this->unique('karigars', ['name' => $name, 'is_active' => 1]);
                $items = [];
                foreach ($quantities as $label => $weight) {
                    $items[] = ['item' => $this->unique('stone_inventory_items', ['product_name' => $label]), 'weight' => $weight];
                }
                $reference = 'SMGAJ-J/26-27/' . $code;
                $existing = $this->matchingHeader('stone_inventory_issue_headers', $reference, $date, (int) $party['id'], (int) $location['id'], 'stone');
                if ($existing) {
                    $this->verifyStoredStone($existing, $items);
                    $result['existing'][] = $reference . ' stone';
                    continue;
                }
                $now = date('Y-m-d H:i:s');
                $this->db->table('stone_inventory_issue_headers')->insert([
                    'voucher_no' => $reference, 'issue_date' => $date,
                    'karigar_id' => (int) $party['id'], 'location_id' => (int) $location['id'],
                    'issue_to' => $name, 'purpose' => 'Historical jobwork issue',
                    'notes' => 'Issue voucher provides aggregate stone quantity only. Stone types allocated from matching packing sheets; existing related SRV issues excluded. Line rates are existing inventory average book costs at import, not scanned voucher prices; unknown cost is zero. Source ' . $reference . '. ' . (self::STONE_PROVENANCE[$code] ?? ''),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $issue = (int) $this->db->insertID();
                foreach ($items as $line) {
                    $stock = $this->db->query('SELECT avg_rate FROM stone_inventory_stock WHERE item_id = ? FOR UPDATE', [(int) $line['item']['id']])->getRowArray();
                    if (! $stock) {
                        throw new RuntimeException('Stone stock row is missing for ' . $line['item']['product_name']);
                    }
                    $bookRate = round(max(0, (float) $stock['avg_rate']), 2);
                    $this->db->table('stone_inventory_issue_lines')->insert([
                        'issue_id' => $issue, 'item_id' => (int) $line['item']['id'], 'pcs' => 0,
                        'qty' => $line['weight'], 'rate' => $bookRate,
                        'line_value' => round((float) $line['weight'] * $bookRate, 2),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                $stoneStock->applyReceiptBackflushIssue($issue);
                $accounting->postInventoryHeader('stone', 'issue', $issue);
                $result['stone'][] = $reference;
            }
            foreach (self::DIAMONDS as $code => [$date, $name, $weight, $labWeight]) {
                $party = $this->unique('karigars', ['name' => $name, 'is_active' => 1]);
                $reference = 'SMGAJ-J/26-27/' . $code;
                $existing = $this->matchingHeader('issue_headers', $reference, $date, (int) $party['id'], (int) $location['id'], 'diamond');
                if ($existing) {
                    $this->verifyStoredDiamond($existing, (int) $diamond['id'], $weight, (int) $labDiamond['id'], $labWeight);
                    $result['existing'][] = $reference . ' diamond';
                    continue;
                }
                $now = date('Y-m-d H:i:s');
                $this->db->table('issue_headers')->insert([
                    'voucher_no' => $reference, 'issue_date' => $date, 'karigar_id' => (int) $party['id'],
                    'location_id' => (int) $location['id'], 'issue_to' => $name, 'purpose' => 'Historical jobwork issue',
                    'notes' => 'Diamond issue from scanned voucher ' . $reference . '; VVS mix and lab-grown stock inferred from matching packing sheets. Issue pieces and unit cost are not stated.',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $issue = (int) $this->db->insertID();
                $this->db->table('issue_lines')->insert([
                    'issue_id' => $issue, 'item_id' => (int) $diamond['id'], 'pcs' => 0,
                    'carat' => $weight, 'rate_per_carat' => null, 'line_value' => null,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                if ($labWeight > 0) {
                    $this->db->table('issue_lines')->insert([
                        'issue_id' => $issue, 'item_id' => (int) $labDiamond['id'], 'pcs' => 0,
                        'carat' => $labWeight, 'rate_per_carat' => null, 'line_value' => null,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                $diamondStock->applyIssue($issue, $labWeight > 0);
                (new DiamondBagTraceService($this->db))->applyIssue($issue);
                $accounting->postInventoryHeader('diamond', 'issue', $issue);
                $result['diamond'][] = $reference;
            }
            foreach ($stoneIds as $id) { $this->assertStoneStock($id); }
            foreach ($diamondIds as $id) { $this->assertDiamondStock($id); }
            foreach ($checks as [$accountId, $type]) { $this->assertAccountBalance($accountId, $type); }
            $result['existing'][] = 'SMGAJ-J/26-27/034 stone .330 ct covered by SRV-698';
            $this->db->transComplete();
            if (! $this->db->transStatus()) {
                throw new RuntimeException('Historical other-material issue transaction failed.');
            }
        } catch (Throwable $e) {
            while ($this->db->transRollback()) {}
            throw $e;
        }
        return $result;
    }

    private function unique(string $table, array $criteria): array
    {
        $rows = $this->db->table($table)->where($criteria)->get()->getResultArray();
        if (count($rows) !== 1) {
            throw new RuntimeException('Expected exactly one ' . $table . ' record: ' . json_encode($criteria));
        }
        return $rows[0];
    }

    private function verifyPreviousStone(): void
    {
        foreach (self::PREVIOUS_STONE as $orderNo => [$karigarName, $expected]) {
            $party = $this->unique('karigars', ['name' => $karigarName, 'is_active' => 1]);
            $order = $this->unique('orders', ['order_no' => $orderNo]);
            $rows = $this->db->table('stone_inventory_issue_headers h')
                ->select('h.*')->join('order_material_movements m', 'm.id = h.receive_movement_id')
                ->where('m.order_id', (int) $order['id'])->where('h.karigar_id', (int) $party['id'])
                ->get()->getResultArray();
            if (count($rows) !== 1) {
                throw new RuntimeException('Missing or ambiguous prior stone issue for ' . $orderNo);
            }
            $items = [];
            foreach ($expected as $name => $weight) {
                $items[] = ['item' => $this->unique('stone_inventory_items', ['product_name' => $name]), 'weight' => $weight];
            }
            $this->verifyStoredStone($rows[0], $items);
            if ($orderNo === 'PL26-SHREE-GOURANGO-G09-R131') {
                $this->correctVerifiedStoneDate($rows[0]);
            }
        }
    }

    /** The scanned issue predates the receipt-based backflush of the same .330 ct. */
    private function correctVerifiedStoneDate(array $header): void
    {
        if ((string) $header['voucher_no'] !== 'SRV-698') {
            throw new RuntimeException('Unexpected existing Gourango stone issue reference.');
        }
        $date = (string) $header['issue_date'];
        if ($date === '2026-08-03') { return; }
        if ($date !== '2026-08-17') { throw new RuntimeException('Unexpected existing Gourango stone issue date.'); }
        $voucherId = (int) $header['account_voucher_id'];
        $voucher = $this->db->table('vouchers')->where('id', $voucherId)->get()->getRowArray();
        $originalDatetime = (string) ($voucher['voucher_datetime'] ?? '');
        $note = 'Issue date corrected from 2026-08-17 to 2026-08-03 using scanned SMGAJ-J/26-27/034; original voucher datetime ' . $originalDatetime . '. Time 12:00 is a placeholder.';
        $this->db->table('stone_inventory_issue_headers')->where('id', (int) $header['id'])->where('issue_date', $date)->update([
            'issue_date' => '2026-08-03', 'notes' => trim((string) ($header['notes'] ?? '') . ' | ' . $note),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if ($this->db->affectedRows() !== 1) { throw new RuntimeException('Gourango stone issue changed concurrently.'); }
        $this->db->table('vouchers')->where('id', $voucherId)->where('voucher_date', $date)->update([
            'voucher_date' => '2026-08-03', 'voucher_datetime' => '2026-08-03 12:00:00',
            'remarks' => trim((string) ($voucher['remarks'] ?? '') . ' | ' . $note),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if ($this->db->affectedRows() !== 1) { throw new RuntimeException('Gourango stone voucher changed concurrently.'); }
    }

    /** Ignore different-numbering October vouchers; detect same-day possible matches. */
    private function matchingHeader(string $table, string $reference, string $date, int $party, int $location, string $type): ?array
    {
        $rows = $this->db->table($table)->where('voucher_no', $reference)->get()->getResultArray();
        if (count($rows) > 1) {
            throw new RuntimeException('Multiple existing ' . $type . ' issues for ' . $reference);
        }
        $sameDay = $this->db->table($table)->where('issue_date', $date)->where('karigar_id', $party)->get()->getResultArray();
        foreach ($sameDay as $candidate) {
            if (! $rows || (int) $candidate['id'] !== (int) $rows[0]['id']) {
                throw new RuntimeException('Unreviewed same-day ' . $type . ' issue for ' . $reference . ': ' . ($candidate['voucher_no'] ?? $candidate['id']));
            }
        }
        if ($rows) {
            if ((string) $rows[0]['issue_date'] !== $date || (int) $rows[0]['karigar_id'] !== $party || (int) $rows[0]['location_id'] !== $location) {
                throw new RuntimeException('Conflicting ' . $type . ' issue ' . $reference);
            }
            return $rows[0];
        }
        return null;
    }

    private function assertStoneStock(int $itemId): void
    {
        $stock = $this->db->query('SELECT qty_balance FROM stone_inventory_stock WHERE item_id = ? FOR UPDATE', [$itemId])->getRowArray();
        $rows = $this->db->query("SELECT
            COALESCE((SELECT SUM(qty) FROM stone_inventory_purchase_lines WHERE item_id=?),0)
            - COALESCE((SELECT SUM(qty) FROM stone_inventory_issue_lines WHERE item_id=?),0)
            + COALESCE((SELECT SUM(qty) FROM stone_inventory_return_lines WHERE item_id=?),0)
            + COALESCE((SELECT SUM(CASE WHEN h.adjustment_type='add' THEN l.qty WHEN h.adjustment_type='subtract' THEN -l.qty ELSE 0 END) FROM stone_inventory_adjustment_lines l JOIN stone_inventory_adjustment_headers h ON h.id=l.adjustment_id WHERE l.item_id=?),0) AS expected", [$itemId, $itemId, $itemId, $itemId])->getRowArray();
        if (! $stock || abs((float) $stock['qty_balance'] - (float) $rows['expected']) >= .0005) {
            throw new RuntimeException('Stone stock does not reconcile with physical movements for item ' . $itemId);
        }
    }

    private function assertDiamondStock(int $itemId): void
    {
        $stock = $this->db->query('SELECT carat_balance FROM stock WHERE item_id = ? FOR UPDATE', [$itemId])->getRowArray();
        $rows = $this->db->query("SELECT
            COALESCE((SELECT SUM(carat) FROM diamond_inventory_opening_balances WHERE item_id=?),0)
            + COALESCE((SELECT SUM(carat) FROM purchase_lines WHERE item_id=?),0)
            - COALESCE((SELECT SUM(carat) FROM issue_lines WHERE item_id=?),0)
            + COALESCE((SELECT SUM(carat) FROM return_lines WHERE item_id=?),0)
            + COALESCE((SELECT SUM(CASE WHEN h.adjustment_type='add' THEN l.carat WHEN h.adjustment_type='subtract' THEN -l.carat ELSE 0 END) FROM diamond_inventory_adjustment_lines l JOIN diamond_inventory_adjustment_headers h ON h.id=l.adjustment_id WHERE l.item_id=?),0) AS expected", [$itemId, $itemId, $itemId, $itemId, $itemId])->getRowArray();
        if (! $stock || abs((float) $stock['carat_balance'] - (float) $rows['expected']) >= .0005) {
            throw new RuntimeException('Diamond stock does not reconcile with physical movements for item ' . $itemId);
        }
    }

    /** @return list<array{int,string}> */
    private function accountChecks(array $location): array
    {
        $warehouses = $this->db->table('warehouses')->where('warehouse_code', 'STORE')->get()->getResultArray();
        if ($warehouses === []) {
            $warehouses = $this->db->table('warehouses')->where('name', (string) $location['name'])->get()->getResultArray();
        }
        if (count($warehouses) !== 1) { throw new RuntimeException('Cannot uniquely resolve MAIN warehouse.'); }
        $warehouse = $this->unique('accounts', ['reference_table' => 'warehouses', 'reference_id' => (int) $warehouses[0]['id']]);
        $checks = [(int) $warehouse['id'] . ':STONE' => [(int) $warehouse['id'], 'STONE'], (int) $warehouse['id'] . ':DIAMOND' => [(int) $warehouse['id'], 'DIAMOND']];
        foreach (['STONE' => self::STONES, 'DIAMOND' => self::DIAMONDS] as $type => $issues) {
            foreach ($issues as $entry) {
                $party = $this->unique('karigars', ['name' => $entry[1], 'is_active' => 1]);
                $account = $this->unique('accounts', ['reference_table' => 'karigars', 'reference_id' => (int) $party['id']]);
                $checks[(int) $account['id'] . ':' . $type] = [(int) $account['id'], $type];
            }
        }
        ksort($checks, SORT_NATURAL);
        return array_values($checks);
    }

    private function assertAccountBalance(int $accountId, string $type): void
    {
        $key = $type . '-POOL';
        $balance = $this->db->query('SELECT qty_cts FROM account_balances WHERE account_id=? AND item_type=? AND item_key=? FOR UPDATE', [$accountId, $type, $key])->getRowArray();
        $ledger = $this->db->query('SELECT COALESCE(SUM(CASE WHEN l.debit_account_id=? THEN l.qty_cts ELSE -l.qty_cts END),0) qty FROM ledger_entries l JOIN vouchers v ON v.id=l.voucher_id WHERE l.item_type=? AND l.item_key=? AND (l.debit_account_id=? OR l.credit_account_id=?)', [$accountId, $type, $key, $accountId, $accountId])->getRowArray();
        if (abs((float) ($balance['qty_cts'] ?? 0) - (float) ($ledger['qty'] ?? 0)) >= .0005) {
            throw new RuntimeException('Account balance does not reconcile with ' . $type . ' voucher ledger for account ' . $accountId);
        }
    }

    private function verifyStoredStone(array $header, array $expected): void
    {
        $lines = $this->db->table('stone_inventory_issue_lines')->where('issue_id', (int) $header['id'])->get()->getResultArray();
        $actual = [];
        foreach ($lines as $line) {
            $id = (int) $line['item_id'];
            $actual[$id] = ($actual[$id] ?? 0) + (float) $line['qty'];
        }
        if (count($actual) !== count($expected)) { throw new RuntimeException('Existing stone issue components do not match: ' . $header['voucher_no']); }
        $sum = 0.0;
        foreach ($expected as $line) {
            $id = (int) $line['item']['id']; $weight = (float) $line['weight'];
            if (! isset($actual[$id]) || abs($actual[$id] - $weight) >= .0005) { throw new RuntimeException('Existing stone issue weight differs: ' . $header['voucher_no']); }
            $sum += $weight;
        }
        $this->verifyVoucher($header, 'STONE_ISSUE', 'STONE', $sum);
    }

    private function verifyStoredDiamond(array $header, int $itemId, float $weight, int $labItemId, float $labWeight): void
    {
        $lines = $this->db->table('issue_lines')->where('issue_id', (int) $header['id'])->get()->getResultArray();
        $actual = [];
        foreach ($lines as $line) {
            $id = (int) $line['item_id'];
            $actual[$id] = ($actual[$id] ?? 0) + (float) $line['carat'];
        }
        if (count($actual) !== ($labWeight > 0 ? 2 : 1)
            || ! isset($actual[$itemId]) || abs($actual[$itemId] - $weight) >= .0005
            || ($labWeight > 0 && (! isset($actual[$labItemId]) || abs($actual[$labItemId] - $labWeight) >= .0005))) {
            throw new RuntimeException('Existing diamond issue components differ: ' . $header['voucher_no']);
        }
        $this->verifyVoucher($header, 'DIAMOND_ISSUE', 'DIAMOND', $weight + $labWeight);
    }

    private function verifyVoucher(array $header, string $voucherType, string $itemType, float $weight): void
    {
        $location = $this->unique('inventory_locations', ['code' => 'MAIN', 'is_active' => 1]);
        if ((int) $header['location_id'] !== (int) $location['id']) {
            throw new RuntimeException('Existing issue has a different location: ' . $header['voucher_no']);
        }
        $voucher = $this->db->table('vouchers')->where('id', (int) ($header['account_voucher_id'] ?? 0))->get()->getRowArray();
        if (! $voucher || (string) $voucher['status'] !== 'Posted' || (string) $voucher['voucher_type'] !== $voucherType
            || (int) $voucher['party_id'] !== (int) $header['karigar_id'] || (string) $voucher['voucher_date'] !== (string) $header['issue_date']) {
            throw new RuntimeException('Existing issue has no matching posted voucher: ' . $header['voucher_no']);
        }
        $debit = $this->db->table('accounts')->where('id', (int) $voucher['debit_account_id'])->get()->getRowArray();
        if (! $debit || (string) $debit['reference_table'] !== 'karigars' || (int) $debit['reference_id'] !== (int) $header['karigar_id']) {
            throw new RuntimeException('Existing issue has the wrong debit account: ' . $header['voucher_no']);
        }
        $warehouses = $this->db->table('warehouses')->where('warehouse_code', 'STORE')->get()->getResultArray();
        if ($warehouses === []) {
            $warehouses = $this->db->table('warehouses')->where('name', (string) $location['name'])->get()->getResultArray();
        }
        $credit = $this->db->table('accounts')->where('id', (int) $voucher['credit_account_id'])->get()->getRowArray();
        if (count($warehouses) !== 1 || ! $credit || (string) $credit['reference_table'] !== 'warehouses'
            || (int) $credit['reference_id'] !== (int) $warehouses[0]['id']) {
            throw new RuntimeException('Existing issue has the wrong warehouse account: ' . $header['voucher_no']);
        }
        $sum = $this->db->table('voucher_lines')->selectSum('qty_cts')->where('voucher_id', (int) $voucher['id'])->where('item_type', $itemType)->get()->getRowArray();
        if (abs((float) ($sum['qty_cts'] ?? 0) - $weight) >= .0005) {
            throw new RuntimeException('Existing issue voucher quantity differs: ' . $header['voucher_no']);
        }
        $ledger = $this->db->table('ledger_entries')->where('voucher_id', (int) $voucher['id'])->where('item_type', $itemType)->get()->getResultArray();
        if (count($ledger) !== 1 || abs((float) $ledger[0]['qty_cts'] - $weight) >= .0005
            || (int) $ledger[0]['debit_account_id'] !== (int) $voucher['debit_account_id']
            || (int) $ledger[0]['credit_account_id'] !== (int) $voucher['credit_account_id']) {
            throw new RuntimeException('Existing issue has a missing or conflicting ledger: ' . $header['voucher_no']);
        }
    }
}
