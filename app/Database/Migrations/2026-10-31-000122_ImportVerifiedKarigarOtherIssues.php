<?php

namespace App\Database\Migrations;

use App\Services\HistoricalKarigarOtherIssues;
use CodeIgniter\Database\Migration;

final class ImportVerifiedKarigarOtherIssues extends Migration
{
    public function up(): void
    {
        $result = (new HistoricalKarigarOtherIssues($this->db))->run();
        log_message('info', 'Historical karigar stone and diamond issues: {result}', ['result' => json_encode($result)]);
    }

    public function down(): void
    {
        // Issued stock and accounting vouchers require a reviewed reversal.
    }
}
