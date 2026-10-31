<?php

namespace App\Database\Migrations;

use App\Services\HistoricalKarigarReceiptImport;
use App\Services\HistoricalKarigarGoldIssues;
use App\Services\HistoricalDiamondIssueVoucherRepair;
use App\Services\HistoricalKarigarOtherIssues;
use CodeIgniter\Database\Migration;
use RuntimeException;
use Throwable;

/** Historical data import; all writes are transactional and rechecked on each target. */
final class ImportVerifiedKarigarScans extends Migration
{
    public function up(): void
    {
        // Keep all issue locks until receipt posting commits. The services
        // acquire these same connection-local locks reentrantly.
        $locks = [
            'aabhushan-scan-gold-issues-202609',
            'aabhushan-aug26-diamond-account-repair',
            'aabhushan-scan-other-issues-20260809',
            'aabhushan-karigar-scans-aug-sep-2026',
        ];
        $acquired = [];
        try {
            foreach ($locks as $lockName) {
                $lock = $this->db->query('SELECT GET_LOCK(?, 15) AS acquired', [$lockName])->getRowArray();
                if ((int) ($lock['acquired'] ?? 0) !== 1) {
                    throw new RuntimeException('Could not lock the historical scan import.');
                }
                $acquired[] = $lockName;
            }
            $this->db->transException(true)->transStart();
            try {
                // All nested issue and receipt work commits together.
                $issues = (new HistoricalKarigarGoldIssues($this->db))->run();
                $diamondRepair = (new HistoricalDiamondIssueVoucherRepair($this->db))->run();
                $otherIssues = (new HistoricalKarigarOtherIssues($this->db))->run();
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
            log_message('info', 'Historical diamond accounting repair: {result}', ['result' => json_encode($diamondRepair)]);
            log_message('info', 'Historical other material issues: {result}', ['result' => json_encode($otherIssues)]);
            log_message('info', 'Historical karigar scan import: {result}', ['result' => json_encode($result)]);
        } finally {
            foreach (array_reverse($acquired) as $lockName) {
                $this->db->query('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        }
    }

    public function down(): void
    {
        // Accounting and received stock cannot safely be undone by a schema rollback.
    }
}
