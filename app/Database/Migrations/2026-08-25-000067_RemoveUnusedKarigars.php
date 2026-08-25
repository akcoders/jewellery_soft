<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;
use Throwable;

class RemoveUnusedKarigars extends Migration
{
    private const NAMES = ['om', 'ranjan', 'sattar'];

    public function up()
    {
        if (! $this->db->tableExists('karigars')) {
            return;
        }

        $karigars = $this->db->query(
            'SELECT id, name FROM karigars WHERE LOWER(TRIM(name)) IN (?, ?, ?) ORDER BY id',
            self::NAMES
        )->getResultArray();
        if ($karigars === []) {
            return;
        }

        $this->db->transException(true)->transStart();
        try {
            foreach ($karigars as $karigar) {
                $karigarId = (int) ($karigar['id'] ?? 0);
                $name = trim((string) ($karigar['name'] ?? ''));
                if ($karigarId <= 0) {
                    continue;
                }

                $this->assertNoKarigarReferences($karigarId, $name);
                $accountIds = $this->karigarAccountIds($karigarId);
                $this->assertNoAccountTransactions($accountIds, $name);
                if ($accountIds !== []) {
                    $this->db->table('accounts')->whereIn('id', $accountIds)->delete();
                }
                $this->db->table('karigars')->where('id', $karigarId)->delete();
            }

            $remaining = (int) $this->db->query(
                'SELECT COUNT(*) AS row_count FROM karigars WHERE LOWER(TRIM(name)) IN (?, ?, ?)',
                self::NAMES
            )->getRowArray()['row_count'];
            if ($remaining !== 0) {
                throw new RuntimeException('Unused karigar cleanup did not remove every requested record.');
            }

            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Deleted demo labour masters are intentionally not restored on rollback.
     */
    public function down()
    {
    }

    private function assertNoKarigarReferences(int $karigarId, string $name): void
    {
        $references = [
            'account_payments' => 'karigar_id',
            'design_masters' => 'source_karigar_id',
            'diamond_ledger_entries' => 'karigar_id',
            'gold_inventory_issue_headers' => 'karigar_id',
            'gold_inventory_ledger_entries' => 'karigar_id',
            'gold_inventory_return_headers' => 'karigar_id',
            'gold_ledger_entries' => 'karigar_id',
            'issue_headers' => 'karigar_id',
            'karigar_documents' => 'karigar_id',
            'karigar_payment_ledgers' => 'karigar_id',
            'labour_bills' => 'karigar_id',
            'orders' => 'assigned_karigar_id',
            'order_material_movements' => 'karigar_id',
            'production_diamond_issue_lines' => 'karigar_id',
            'production_gold_movements' => 'karigar_id',
            'production_ready_items' => 'karigar_id',
            'return_headers' => 'karigar_id',
            'stone_inventory_issue_headers' => 'karigar_id',
            'stone_inventory_return_headers' => 'karigar_id',
            'stone_issues' => 'karigar_id',
            'stone_ledger_entries' => 'karigar_id',
            'vouchers' => 'party_id',
        ];

        foreach ($references as $table => $column) {
            if (! $this->db->tableExists($table) || ! $this->db->fieldExists($column, $table)) {
                continue;
            }
            $count = (int) $this->db->table($table)->where($column, $karigarId)->countAllResults();
            if ($count > 0) {
                throw new RuntimeException(sprintf(
                    'Karigar %s cannot be removed because %s contains %d linked record(s).',
                    $name,
                    $table,
                    $count
                ));
            }
        }
    }

    /** @return list<int> */
    private function karigarAccountIds(int $karigarId): array
    {
        if (! $this->db->tableExists('accounts')) {
            return [];
        }

        $rows = $this->db->table('accounts')
            ->select('id')
            ->where('reference_id', $karigarId)
            ->groupStart()
                ->where('account_type', 'KARIGAR')
                ->orWhere('reference_table', 'karigars')
            ->groupEnd()
            ->get()
            ->getResultArray();

        return array_values(array_map(static fn(array $row): int => (int) $row['id'], $rows));
    }

    /** @param list<int> $accountIds */
    private function assertNoAccountTransactions(array $accountIds, string $name): void
    {
        if ($accountIds === []) {
            return;
        }

        $references = [
            ['account_balances', 'account_id'],
            ['ledger_entries', 'debit_account_id'],
            ['ledger_entries', 'credit_account_id'],
            ['vouchers', 'debit_account_id'],
            ['vouchers', 'credit_account_id'],
        ];
        foreach ($references as [$table, $column]) {
            if (! $this->db->tableExists($table) || ! $this->db->fieldExists($column, $table)) {
                continue;
            }
            $count = (int) $this->db->table($table)->whereIn($column, $accountIds)->countAllResults();
            if ($count > 0) {
                throw new RuntimeException(sprintf(
                    'Karigar %s cannot be removed because its account has %d linked record(s) in %s.',
                    $name,
                    $count,
                    $table
                ));
            }
        }
    }
}
