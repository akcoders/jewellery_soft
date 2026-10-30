<?php

namespace App\Database\Migrations;

use App\Services\HistoricalKarigarReceiptImport;
use App\Services\HistoricalKarigarGoldIssues;
use CodeIgniter\Database\Migration;
use RuntimeException;
use Throwable;

/** Historical data import; all writes are transactional and rechecked on each target. */
final class ImportVerifiedKarigarScans extends Migration
{
    public function up(): void
    {
        // Hold the issue lock until the outer transaction commits so another
        // importer cannot observe a partial issue/receipt operation.
        $lockName = 'aabhushan-scan-gold-issues-202609';
        $lock = $this->db->query('SELECT GET_LOCK(?, 15) AS acquired', [$lockName])->getRowArray();
        if ((int) ($lock['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Could not lock the historical scan import.');
        }
        try {
            $this->db->transException(true)->transStart();
            try {
                // The gold service obtains the same lock reentrantly. Its
                // nested transaction commits only with this outer transaction.
                $issues = (new HistoricalKarigarGoldIssues($this->db))->run();
                $result = (new HistoricalKarigarReceiptImport($this->db))->run();
                $this->db->transComplete();
                if (! $this->db->transStatus()) {
                    throw new RuntimeException('Historical issue and receiving import transaction failed.');
                }
            } catch (Throwable $e) {
                while ($this->db->transRollback()) {
                }
                throw $e;
            }
            log_message('info', 'Historical karigar gold issues: {result}', ['result' => json_encode($issues)]);
            log_message('info', 'Historical karigar scan import: {result}', ['result' => json_encode($result)]);
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    public function down(): void
    {
        // Accounting and received stock cannot safely be undone by a schema rollback.
    }
}
