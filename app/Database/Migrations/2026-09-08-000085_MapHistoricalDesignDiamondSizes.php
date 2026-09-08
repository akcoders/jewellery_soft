<?php

namespace App\Database\Migrations;

use App\Services\HistoricalDiamondSizeImportService;
use CodeIgniter\Database\Migration;

class MapHistoricalDesignDiamondSizes extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('order_diamond_size_details')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'order_id' => ['type' => 'INT', 'unsigned' => true],
                'order_receive_detail_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'source_sha256' => ['type' => 'CHAR', 'constraint' => 64],
                'source_file' => ['type' => 'VARCHAR', 'constraint' => 180],
                'source_sheet' => ['type' => 'VARCHAR', 'constraint' => 80],
                'source_row' => ['type' => 'INT', 'unsigned' => true],
                'source_block' => ['type' => 'VARCHAR', 'constraint' => 100],
                'quality' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'shade' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'shape_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'size_label' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'chalni_label' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'pcs' => ['type' => 'DECIMAL', 'constraint' => '16,3'],
                'weight_cts' => ['type' => 'DECIMAL', 'constraint' => '16,3'],
                'source_kind' => ['type' => 'VARCHAR', 'constraint' => 40],
                'match_status' => ['type' => 'VARCHAR', 'constraint' => 20],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'source_data_json' => ['type' => 'LONGTEXT'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['source_sha256', 'source_sheet', 'source_row'], 'uq_historical_diamond_source_row');
            $this->forge->addKey('order_id');
            $this->forge->createTable('order_diamond_size_details');
        }
        if (! $this->db->tableExists('historical_diamond_size_blocks')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'source_sha256' => ['type' => 'CHAR', 'constraint' => 64],
                'source_file' => ['type' => 'VARCHAR', 'constraint' => 180],
                'source_block' => ['type' => 'VARCHAR', 'constraint' => 100],
                'order_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20],
                'reason' => ['type' => 'TEXT'],
                'source_data_json' => ['type' => 'LONGTEXT'],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['source_sha256', 'source_block'], 'uq_historical_diamond_block');
            $this->forge->createTable('historical_diamond_size_blocks');
        }
        $manifest = json_decode(file_get_contents(APPPATH . 'Database/Data/historical-diamond-size-map.json'), true, 512, JSON_THROW_ON_ERROR);
        (new HistoricalDiamondSizeImportService($this->db))->import($manifest);
    }

    public function down()
    {
        // Only this migration's reference tables; financial records are never touched.
        $this->forge->dropTable('order_diamond_size_details', true);
        $this->forge->dropTable('historical_diamond_size_blocks', true);
    }
}
