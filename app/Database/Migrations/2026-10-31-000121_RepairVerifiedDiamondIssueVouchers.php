<?php

namespace App\Database\Migrations;

use App\Services\HistoricalDiamondIssueVoucherRepair;
use CodeIgniter\Database\Migration;

final class RepairVerifiedDiamondIssueVouchers extends Migration
{
    public function up(): void
    {
        $result = (new HistoricalDiamondIssueVoucherRepair($this->db))->run();
        log_message('info', 'Historical diamond issue voucher repair: {result}', ['result' => json_encode($result)]);
    }

    public function down(): void
    {
        // Posted material accounting is not safely reversed by schema rollback.
    }
}
