<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/** Repair missing account vouchers for already-issued August 2026 diamonds.
 *
 * The stock issues already exist. This service never invokes inventory stock
 * movement, changes the original issue lines or alters PL-HIST adjustments.
 */
final class HistoricalDiamondIssueVoucherRepair
{
    private const LOCK = 'aabhushan-aug26-diamond-account-repair';
    private const ISSUES = [
        ['033', '2026-08-01', 'UTTAM MAL', 64.090, 'Vvs Mix'],
        ['034', '2026-08-03', 'SHREE GOURANGO', 0.560, 'Vvs Mix'],
        ['035', '2026-08-03', 'RHEEA JEWELS', 3.790, 'Vvs Mix'],
        ['039', '2026-08-20', 'SHREE GOURANGO', 13.300, 'Lab Grown'],
        ['040', '2026-08-22', 'RHEEA JEWELS', 6.550, 'Vvs Mix'],
        ['041', '2026-08-23', 'JGD DIAMONDS', 0.870, 'Vvs Mix'],
    ];

    public function __construct(private BaseConnection $db) {}

    /** @return array{repaired:list<string>,already_recorded:list<string>} */
    public function run(): array
    {
        $lock = $this->db->query('SELECT GET_LOCK(?, 15) AS acquired', [self::LOCK])->getRowArray();
        if ((int) ($lock['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Could not lock historical diamond issue repair.');
        }
        try {
            return $this->repair();
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [self::LOCK]);
        }
    }

    private function repair(): array
    {
        $result = ['repaired' => [], 'already_recorded' => []];
        $this->db->transException(true)->transStart();
        try {
            $issues = [];
            $accountIds = [];
            $oldVoucherIds = [];
            foreach (self::ISSUES as [$number, $date, $name, $carats, $type]) {
                $ref = 'SMGAJ-J/26-27/' . $number;
                $rows = $this->db->query('SELECT h.*, k.name karigar_name FROM issue_headers h JOIN karigars k ON k.id=h.karigar_id WHERE h.voucher_no=? FOR UPDATE', [$ref])->getResultArray();
                if (count($rows) !== 1 || $rows[0]['issue_date'] !== $date || strtoupper((string) $rows[0]['karigar_name']) !== $name) {
                    throw new RuntimeException('Missing or conflicting historical diamond issue ' . $ref);
                }
                $issue = $rows[0];
                $lines = $this->db->query('SELECT l.*, i.diamond_type FROM issue_lines l JOIN items i ON i.id=l.item_id WHERE l.issue_id=?', [(int) $issue['id']])->getResultArray();
                if (count($lines) !== 1 || strcasecmp((string) $lines[0]['diamond_type'], $type) !== 0
                    || abs((float) $lines[0]['carat'] - $carats) >= 0.0005 || abs((float) $lines[0]['pcs']) >= 0.0005) {
                    throw new RuntimeException('Historical issue stock detail differs for ' . $ref);
                }
                $voucherId = (int) ($issue['account_voucher_id'] ?? 0);
                if ($voucherId <= 0 || isset($oldVoucherIds[$voucherId])) {
                    throw new RuntimeException('Issue has a missing or repeated account voucher reference: ' . $ref);
                }
                $oldVoucherIds[$voucherId] = true;
                $voucher = $this->db->table('vouchers')->where('id', $voucherId)->get()->getRowArray();
                $entries = $this->db->table('ledger_entries')->where('voucher_id', $voucherId)->get()->getResultArray();
                if (count($entries) !== 1) {
                    throw new RuntimeException('Issue must have exactly one preserved account ledger row: ' . $ref);
                }
                $ledger = $entries[0];
                if ($ledger['item_type'] !== 'DIAMOND' || $ledger['item_key'] !== 'DIAMOND-POOL'
                    || abs((float) $ledger['qty_cts'] - $carats) >= 0.0005
                    || abs((float) $ledger['qty_pcs']) >= 0.0005
                    || abs((float) $ledger['qty_weight']) >= 0.0005
                    || abs((float) $ledger['fine_gold_qty']) >= 0.0005) {
                    throw new RuntimeException('Issue ledger quantity or material differs for ' . $ref);
                }
                $debit = (int) $ledger['debit_account_id'];
                $credit = (int) $ledger['credit_account_id'];
                $account = $this->db->table('accounts')->where('id', $debit)->get()->getRowArray();
                $warehouse = $this->db->table('accounts')->where('id', $credit)->get()->getRowArray();
                if (! $account || strtoupper((string) $account['account_type']) !== 'KARIGAR'
                    || (string) $account['reference_table'] !== 'karigars' || (int) $account['reference_id'] !== (int) $issue['karigar_id']
                    || ! $warehouse || strtoupper((string) $warehouse['account_type']) !== 'WAREHOUSE') {
                    throw new RuntimeException('Issue ledger accounts differ for ' . $ref);
                }
                if ($voucher) {
                    $this->verifyVoucher($voucher, $issue, $ledger, $date, $carats, $ref);
                } else {
                    if ($this->db->table('voucher_lines')->where('voucher_id', $voucherId)->countAllResults() !== 0
                        || $this->db->table('voucher_reversals')->groupStart()->where('original_voucher_id', $voucherId)->orWhere('reversal_voucher_id', $voucherId)->groupEnd()->countAllResults() !== 0) {
                        throw new RuntimeException('Orphan issue has surviving voucher lines or reversal: ' . $ref);
                    }
                    $possible = $this->db->query('SELECT v.id FROM vouchers v JOIN voucher_lines l ON l.voucher_id=v.id WHERE v.voucher_type=? AND v.voucher_date=? AND v.party_id=? AND l.item_type=? AND ABS(l.qty_cts-?)<0.0005', ['DIAMOND_ISSUE', $date, (int) $issue['karigar_id'], 'DIAMOND', $carats])->getResultArray();
                    if ($possible !== []) {
                        throw new RuntimeException('Another existing voucher may already represent issue ' . $ref);
                    }
                    $accountIds[$debit] = true;
                    $accountIds[$credit] = true;
                }
                $issues[] = compact('issue', 'ledger', 'voucher', 'date', 'carats', 'ref');
            }
            // Lock balances before checking, including all existing-voucher ledger
            // rows (reversed vouchers and their reversals must both be counted).
            ksort($accountIds);
            foreach (array_keys($accountIds) as $id) { $this->assertBalance((int) $id); }
            $accounting = new KarigarMaterialAccountingService($this->db);
            foreach ($issues as $entry) {
                if ($entry['voucher']) {
                    $result['already_recorded'][] = $entry['ref'];
                    continue;
                }
                $old = $entry['ledger'];
                $newId = $accounting->postInventoryHeader('diamond', 'issue', (int) $entry['issue']['id']);
                $new = $this->db->table('ledger_entries')->where('voucher_id', $newId)->where('id !=', (int) $old['id'])->get()->getResultArray();
                $voucher = $this->db->table('vouchers')->where('id', $newId)->get()->getRowArray();
                if (! $voucher || count($new) !== 1 || (int) $new[0]['debit_account_id'] !== (int) $old['debit_account_id']
                    || (int) $new[0]['credit_account_id'] !== (int) $old['credit_account_id']
                    || $new[0]['item_type'] !== $old['item_type'] || $new[0]['item_key'] !== $old['item_key']
                    || abs((float) $new[0]['qty_cts'] - (float) $old['qty_cts']) >= 0.0005
                    || abs((float) $new[0]['qty_pcs'] - (float) $old['qty_pcs']) >= 0.0005) {
                    throw new RuntimeException('Recreated voucher does not match preserved ledger for ' . $entry['ref']);
                }
                $this->db->table('ledger_entries')->where('id', (int) $old['id'])->update(['voucher_id' => $newId]);
                $this->db->table('ledger_entries')->where('id', (int) $new[0]['id'])->delete();
                $this->db->table('vouchers')->where('id', $newId)->update([
                    'remarks' => (string) $voucher['remarks'] . ' | Repaired missing accounting voucher; existing issue stock and original ledger row retained.',
                ]);
                $result['repaired'][] = $entry['ref'];
            }
            foreach (array_keys($accountIds) as $id) { $this->assertBalance((int) $id); }
            $this->db->transComplete();
            if (! $this->db->transStatus()) { throw new RuntimeException('Diamond issue repair transaction failed.'); }
        } catch (Throwable $e) {
            while ($this->db->transRollback()) {}
            throw $e;
        }
        return $result;
    }

    private function assertBalance(int $accountId): void
    {
        $rows = $this->db->query('SELECT qty_cts FROM account_balances WHERE account_id=? AND item_type=? AND item_key=? FOR UPDATE', [$accountId, 'DIAMOND', 'DIAMOND-POOL'])->getResultArray();
        if (count($rows) !== 1) { throw new RuntimeException('Missing or duplicate diamond balance for account ' . $accountId); }
        $sum = $this->db->query('SELECT COALESCE(SUM(CASE WHEN e.debit_account_id=? THEN e.qty_cts ELSE -e.qty_cts END),0) total FROM ledger_entries e JOIN vouchers v ON v.id=e.voucher_id WHERE (e.debit_account_id=? OR e.credit_account_id=?) AND e.item_type=? AND e.item_key=?', [$accountId, $accountId, $accountId, 'DIAMOND', 'DIAMOND-POOL'])->getRowArray();
        if (abs((float) $rows[0]['qty_cts'] - (float) ($sum['total'] ?? 0)) >= 0.0005) {
            throw new RuntimeException('Diamond balance does not reconcile with existing-voucher ledger for account ' . $accountId);
        }
    }

    private function verifyVoucher(array $voucher, array $issue, array $ledger, string $date, float $carats, string $ref): void
    {
        $lines = $this->db->table('voucher_lines')->where('voucher_id', (int) $voucher['id'])->get()->getResultArray();
        if ($voucher['voucher_type'] !== 'DIAMOND_ISSUE' || $voucher['status'] !== 'Posted'
            || $voucher['voucher_date'] !== $date || (int) $voucher['party_id'] !== (int) $issue['karigar_id']
            || (int) $voucher['debit_account_id'] !== (int) $ledger['debit_account_id']
            || (int) $voucher['credit_account_id'] !== (int) $ledger['credit_account_id']
            || count($lines) !== 1 || $lines[0]['item_type'] !== 'DIAMOND'
            || $lines[0]['item_key'] !== 'DIAMOND-POOL' || abs((float) $lines[0]['qty_cts'] - $carats) >= 0.0005) {
            throw new RuntimeException('Existing issue account voucher differs for ' . $ref);
        }
    }
}
