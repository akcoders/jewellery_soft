<?php

namespace App\Services;

use App\Services\GoldInventory\StockService;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/** Posts the four verified September gold issuements from the scanned vouchers. */
final class HistoricalKarigarGoldIssues
{
    private const MANIFEST = APPPATH . 'Database/Data/karigar-issuements-20260809/vouchers.json';
    private const LOCK = 'aabhushan-scan-gold-issues-202609';
    private const EXPECTED = [
        '042' => ['2026-09-01', 'SHREE GOURANGO', 59.825],
        '043' => ['2026-09-01', 'UTTAM MAL', 5.630],
        '044' => ['2026-09-08', 'RHEEA JEWELS', 42.128],
        '045' => ['2026-09-09', 'ANANDI JEWELS', 104.816],
    ];

    public function __construct(private BaseConnection $db) {}

    /** @return array{created:list<string>,already_recorded:list<string>} */
    public function run(): array
    {
        $lock = $this->db->query('SELECT GET_LOCK(?, 15) AS acquired', [self::LOCK])->getRowArray();
        if ((int) ($lock['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Could not lock September gold issue import.');
        }
        try {
            return $this->runLocked();
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [self::LOCK]);
        }
    }

    private function runLocked(): array
    {
        $raw = file_get_contents(self::MANIFEST);
        if ($raw === false) {
            throw new RuntimeException('September gold issue manifest is missing.');
        }
        $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $entries = [];
        foreach ($manifest['vouchers'] ?? [] as $entry) {
            $code = $this->voucherCode((string) ($entry['voucher_no'] ?? ''));
            if ($code === null || isset($entries[$code])) {
                throw new RuntimeException('Invalid or duplicated scanned issue voucher.');
            }
            $entries[$code] = $entry;
        }
        $location = $this->unique('inventory_locations', ['code' => 'MAIN', 'is_active' => 1]);
        $gold = $this->unique('gold_inventory_items', ['purity_code' => '24K', 'purity_percent' => 100, 'form_type' => 'Production Ledger']);
        $result = ['created' => [], 'already_recorded' => []];
        $this->db->transException(true)->transStart();
        try {
            $this->assertStockReconciles((int) $gold['id']);
            $stock = new StockService($this->db);
            $accounting = new KarigarMaterialAccountingService($this->db);
            foreach (self::EXPECTED as $code => [$date, $karigarName, $weight]) {
                $entry = $entries[$code] ?? null;
                if (! is_array($entry) || ($entry['date'] ?? '') !== $date
                    || ($entry['karigar_master_name'] ?? '') !== $karigarName
                    || abs((float) ($entry['pure_gold_g'] ?? 0) - $weight) >= 0.0005
                    || empty($entry['source_images'])) {
                    throw new RuntimeException('September issue manifest does not match the reviewed voucher ' . $code);
                }
                $karigar = $this->unique('karigars', ['name' => $karigarName, 'is_active' => 1]);
                $matched = $this->findExisting($code, $date, (int) $karigar['id'], $weight);
                if ($matched !== null) {
                    $this->verifyExisting($matched, $date, (int) $karigar['id'], (int) $gold['id'], $weight);
                    $result['already_recorded'][] = (string) $entry['voucher_no'];
                    continue;
                }
                $now = date('Y-m-d H:i:s');
                $notes = sprintf(
                    'Verified historical gold issue from %s; image %s. Date only is known. The scanned declared goods value INR %.2f includes all materials; no gold-only rate is stated.',
                    $entry['voucher_no'], implode(', ', $entry['source_images']), (float) ($entry['declared_goods_value_inr'] ?? 0)
                );
                $this->db->table('gold_inventory_issue_headers')->insert([
                    'issue_date' => $date,
                    'voucher_no' => (string) $entry['voucher_no'],
                    'karigar_id' => (int) $karigar['id'],
                    'location_id' => (int) $location['id'],
                    'issue_to' => (string) $karigar['name'],
                    'purpose' => 'Jobwork',
                    'notes' => $notes,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $issueId = (int) $this->db->insertID();
                $this->db->table('gold_inventory_issue_lines')->insert([
                    'issue_id' => $issueId,
                    'item_id' => (int) $gold['id'],
                    'weight_gm' => $weight,
                    'fine_weight_gm' => $weight,
                    // The scan has a combined value, not a gold-only unit price.
                    'rate_per_gm' => null,
                    'line_value' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $stock->applyIssue($issueId, [
                    'txn_date' => $date,
                    'karigar_id' => (int) $karigar['id'],
                    'location_id' => (int) $location['id'],
                    'notes' => $notes,
                ]);
                $accountId = $accounting->postInventoryHeader('gold', 'issue', $issueId);
                if ($accountId <= 0) {
                    throw new RuntimeException('Gold issue account voucher was not posted.');
                }
                $result['created'][] = (string) $entry['voucher_no'];
            }
            $this->recalculateGoldLedger((int) $gold['id']);
            $this->assertStockReconciles((int) $gold['id']);
            $this->db->transComplete();
            if (! $this->db->transStatus()) {
                throw new RuntimeException('September gold issue transaction failed.');
            }
        } catch (Throwable $e) {
            while ($this->db->transRollback()) {
            }
            throw $e;
        }
        return $result;
    }

    private function unique(string $table, array $criteria): array
    {
        $rows = $this->db->table($table)->where($criteria)->get()->getResultArray();
        if (count($rows) !== 1) {
            throw new RuntimeException('Expected one ' . $table . ' record: ' . json_encode($criteria));
        }
        return $rows[0];
    }

    /** Recognise the historical gold register's spelling as well as the scan. */
    private function voucherCode(string $voucher): ?string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $voucher));
        return preg_match('/^SMGAJ[IJ]?2627(\d{3})$/', $normalized, $m) ? $m[1] : null;
    }

    private function findExisting(string $code, string $date, int $karigar, float $weight): ?array
    {
        $rows = $this->db->query(
            'SELECT h.*, COALESCE(SUM(l.weight_gm),0) total_weight, COALESCE(SUM(l.fine_weight_gm),0) total_fine FROM gold_inventory_issue_headers h LEFT JOIN gold_inventory_issue_lines l ON l.issue_id=h.id GROUP BY h.id'
        )->getResultArray();
        $same = [];
        foreach ($rows as $row) {
            $sameVoucher = $this->voucherCode((string) ($row['voucher_no'] ?? '')) === $code;
            $possibleDuplicate = (string) $row['issue_date'] === $date
                && (int) $row['karigar_id'] === $karigar
                && abs((float) $row['total_fine'] - $weight) < 0.0005;
            if ($sameVoucher) {
                $same[] = $row;
            } elseif ($possibleDuplicate) {
                throw new RuntimeException('Possible gold issue duplicate with another voucher: ' . $row['voucher_no']);
            }
        }
        if (count($same) > 1) {
            throw new RuntimeException('Multiple gold issues match voucher ' . $code);
        }
        return $same[0] ?? null;
    }

    private function verifyExisting(array $row, string $date, int $karigar, int $gold, float $weight): void
    {
        if ((string) $row['issue_date'] !== $date || (int) $row['karigar_id'] !== $karigar
            || abs((float) $row['total_weight'] - $weight) >= 0.0005
            || abs((float) $row['total_fine'] - $weight) >= 0.0005) {
            throw new RuntimeException('Existing gold issue has conflicting date, karigar, or weight: ' . $row['voucher_no']);
        }
        $issueId = (int) $row['id'];
        $lines = $this->db->table('gold_inventory_issue_lines')->where('issue_id', $issueId)->get()->getResultArray();
        foreach ($lines as $line) {
            if ((int) $line['item_id'] !== $gold) {
                throw new RuntimeException('Existing issue has an unexpected gold stock item: ' . $row['voucher_no']);
            }
        }
        $ledger = $this->db->table('gold_inventory_ledger_entries')->where('reference_table', 'gold_inventory_issue_headers')
            ->where('reference_id', $issueId)->get()->getResultArray();
        if (count($ledger) !== 1 || (string) $ledger[0]['txn_date'] !== $date || (int) $ledger[0]['item_id'] !== $gold
            || abs((float) $ledger[0]['credit_weight_gm'] - $weight) >= 0.0005
            || abs((float) $ledger[0]['credit_fine_gm'] - $weight) >= 0.0005) {
            throw new RuntimeException('Existing issue stock ledger is incomplete: ' . $row['voucher_no']);
        }
        $voucher = $this->db->table('vouchers')->where('id', (int) ($row['account_voucher_id'] ?? 0))->get()->getRowArray();
        if (! $voucher || (string) $voucher['voucher_type'] !== 'GOLD_ISSUE'
            || (string) $voucher['voucher_date'] !== $date || (int) $voucher['party_id'] !== $karigar
            || (string) $voucher['status'] !== 'Posted') {
            throw new RuntimeException('Existing issue account voucher is missing or inconsistent: ' . $row['voucher_no']);
        }
        $value = $this->db->table('voucher_lines')->selectSum('fine_gold')->where('voucher_id', (int) $voucher['id'])->where('item_type', 'GOLD')->get()->getRowArray();
        if (abs((float) ($value['fine_gold'] ?? 0) - $weight) >= 0.0005) {
            throw new RuntimeException('Existing issue accounting weight is inconsistent: ' . $row['voucher_no']);
        }
    }

    private function assertStockReconciles(int $itemId): void
    {
        $stock = $this->unique('gold_inventory_stock', ['item_id' => $itemId]);
        $total = $this->db->query(
            'SELECT COALESCE(SUM(debit_weight_gm-credit_weight_gm),0) weight, COALESCE(SUM(debit_fine_gm-credit_fine_gm),0) fine FROM gold_inventory_ledger_entries WHERE item_id=?', [$itemId]
        )->getRowArray();
        if (abs((float) $stock['weight_balance_gm'] - (float) $total['weight']) >= 0.0005
            || abs((float) $stock['fine_balance_gm'] - (float) $total['fine']) >= 0.0005) {
            throw new RuntimeException('24K production stock does not reconcile with its gold ledger.');
        }
    }

    /** Backdated issues affect later running balances, not earlier transactions. */
    private function recalculateGoldLedger(int $itemId): void
    {
        $weight = $fine = 0.0;
        $rows = $this->db->table('gold_inventory_ledger_entries')->where('item_id', $itemId)
            ->orderBy('txn_date', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($rows as $row) {
            $weight = round($weight + (float) $row['debit_weight_gm'] - (float) $row['credit_weight_gm'], 3);
            $fine = round($fine + (float) $row['debit_fine_gm'] - (float) $row['credit_fine_gm'], 3);
            if ((string) $row['txn_date'] >= '2026-09-01') {
                $this->db->table('gold_inventory_ledger_entries')->where('id', (int) $row['id'])->update([
                    'balance_weight_gm' => $weight,
                    'balance_fine_gm' => $fine,
                ]);
            }
        }
    }
}
