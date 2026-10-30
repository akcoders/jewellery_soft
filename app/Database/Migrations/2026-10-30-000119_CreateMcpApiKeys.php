<?php

namespace App\Database\Migrations;

use App\Services\McpApiKeyService;
use CodeIgniter\Database\Migration;

class CreateMcpApiKeys extends Migration
{
    public function up()
    {
        (new McpApiKeyService($this->db))->ensureTable();
    }

    public function down()
    {
        $this->forge->dropTable(McpApiKeyService::TABLE, true);
    }
}
