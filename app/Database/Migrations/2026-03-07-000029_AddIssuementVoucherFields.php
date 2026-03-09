<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIssuementVoucherFields extends Migration
{
    public function up()
    {
        $this->updateDiamondIssueHeaders();
        $this->updateGoldIssueHeaders();
    }

    public function down()
    {
        if ($this->db->tableExists('issue_headers')) {
            foreach (['idx_issue_headers_voucher_no', 'idx_issue_headers_karigar_id', 'idx_issue_headers_location_id'] as $idx) {
                try {
                    $this->db->query('DROP INDEX ' . $idx . ' ON issue_headers');
                } catch (\Throwable $e) {
                }
            }
            $drop = [];
            foreach (['voucher_no', 'karigar_id', 'location_id', 'attachment_name', 'attachment_path', 'created_by'] as $column) {
                if ($this->db->fieldExists($column, 'issue_headers')) {
                    $drop[] = $column;
                }
            }
            if ($drop !== []) {
                $this->forge->dropColumn('issue_headers', $drop);
            }
        }

        if ($this->db->tableExists('gold_inventory_issue_headers')) {
            try {
                $this->db->query('DROP INDEX idx_gold_issue_headers_voucher_no ON gold_inventory_issue_headers');
            } catch (\Throwable $e) {
            }
            $drop = [];
            foreach (['voucher_no', 'attachment_name', 'attachment_path'] as $column) {
                if ($this->db->fieldExists($column, 'gold_inventory_issue_headers')) {
                    $drop[] = $column;
                }
            }
            if ($drop !== []) {
                $this->forge->dropColumn('gold_inventory_issue_headers', $drop);
            }
        }
    }

    private function updateDiamondIssueHeaders(): void
    {
        if (! $this->db->tableExists('issue_headers')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('voucher_no', 'issue_headers')) {
            $fields['voucher_no'] = ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'issue_date'];
        }
        if (! $this->db->fieldExists('karigar_id', 'issue_headers')) {
            $fields['karigar_id'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'order_id'];
        }
        if (! $this->db->fieldExists('location_id', 'issue_headers')) {
            $fields['location_id'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'karigar_id'];
        }
        if (! $this->db->fieldExists('attachment_name', 'issue_headers')) {
            $fields['attachment_name'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'notes'];
        }
        if (! $this->db->fieldExists('attachment_path', 'issue_headers')) {
            $fields['attachment_path'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'attachment_name'];
        }
        if (! $this->db->fieldExists('created_by', 'issue_headers')) {
            $fields['created_by'] = ['type' => 'INT', 'constraint' => 11, 'null' => true, 'after' => 'attachment_path'];
        }
        if ($fields !== []) {
            $this->forge->addColumn('issue_headers', $fields);
        }

        $idxVoucher = $this->db->query("SHOW INDEX FROM issue_headers WHERE Key_name = 'idx_issue_headers_voucher_no'")->getResultArray();
        if ($idxVoucher === [] && $this->db->fieldExists('voucher_no', 'issue_headers')) {
            $this->db->query('CREATE INDEX idx_issue_headers_voucher_no ON issue_headers (voucher_no)');
        }
        $idxKarigar = $this->db->query("SHOW INDEX FROM issue_headers WHERE Key_name = 'idx_issue_headers_karigar_id'")->getResultArray();
        if ($idxKarigar === [] && $this->db->fieldExists('karigar_id', 'issue_headers')) {
            $this->db->query('CREATE INDEX idx_issue_headers_karigar_id ON issue_headers (karigar_id)');
        }
        $idxLocation = $this->db->query("SHOW INDEX FROM issue_headers WHERE Key_name = 'idx_issue_headers_location_id'")->getResultArray();
        if ($idxLocation === [] && $this->db->fieldExists('location_id', 'issue_headers')) {
            $this->db->query('CREATE INDEX idx_issue_headers_location_id ON issue_headers (location_id)');
        }
    }

    private function updateGoldIssueHeaders(): void
    {
        if (! $this->db->tableExists('gold_inventory_issue_headers')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('voucher_no', 'gold_inventory_issue_headers')) {
            $fields['voucher_no'] = ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'issue_date'];
        }
        if (! $this->db->fieldExists('attachment_name', 'gold_inventory_issue_headers')) {
            $fields['attachment_name'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'notes'];
        }
        if (! $this->db->fieldExists('attachment_path', 'gold_inventory_issue_headers')) {
            $fields['attachment_path'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'attachment_name'];
        }
        if ($fields !== []) {
            $this->forge->addColumn('gold_inventory_issue_headers', $fields);
        }

        $idx = $this->db->query("SHOW INDEX FROM gold_inventory_issue_headers WHERE Key_name = 'idx_gold_issue_headers_voucher_no'")->getResultArray();
        if ($idx === [] && $this->db->fieldExists('voucher_no', 'gold_inventory_issue_headers')) {
            $this->db->query('CREATE INDEX idx_gold_issue_headers_voucher_no ON gold_inventory_issue_headers (voucher_no)');
        }
    }
}

