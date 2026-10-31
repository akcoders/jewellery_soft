<?php

namespace App\Database\Migrations;

use App\Services\HistoricalKarigarGoldIssues;
use CodeIgniter\Database\Migration;

/** Applies the same issues on databases that already ran receiving migration 118. */
final class ImportVerifiedKarigarGoldIssues extends Migration
{
    public function up(): void
    {
        $result = (new HistoricalKarigarGoldIssues($this->db))->run();
        log_message('info', 'Historical karigar gold issues: {result}', ['result' => json_encode($result)]);
    }

    public function down(): void
    {
        // Posted issues and ledgers must not be silently deleted on rollback.
    }
}
