<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrderAndJobCardTables extends Migration
{
    public function up()
    {
        $this->createOrders();
        $this->createOrderItems();
        $this->createOrderAttachments();
        $this->createOrderStatusHistory();
        $this->createJobCards();
        $this->createJobCardOperations();
        $this->createJobCardTimeline();
    }

    public function down()
    {
        $this->forge->dropTable('job_card_timeline', true);
        $this->forge->dropTable('job_card_operations', true);
        $this->forge->dropTable('job_cards', true);
        $this->forge->dropTable('order_status_history', true);
        $this->forge->dropTable('order_attachments', true);
        $this->forge->dropTable('order_items', true);
        $this->forge->dropTable('orders', true);
    }

    private function createOrders(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'order_no' => ['type' => 'VARCHAR', 'constraint' => 40],
            'order_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Sales'],
            'customer_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'lead_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'quotation_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Confirmed'],
            'priority' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Medium'],
            'due_date' => ['type' => 'DATE', 'null' => true],
            'order_notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('order_no');
        $this->forge->addKey('status');
        $this->forge->addKey('due_date');
        $this->forge->createTable('orders', true);
    }

    private function createOrderItems(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'design_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'variant_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'item_description' => ['type' => 'TEXT', 'null' => true],
            'size_label' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'qty' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'gold_required_gm' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'diamond_required_cts' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'item_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Confirmed'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('item_status');
        $this->forge->createTable('order_items', true);
    }

    private function createOrderAttachments(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'order_item_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'file_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'reference'],
            'file_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_path' => ['type' => 'VARCHAR', 'constraint' => 255],
            'uploaded_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('order_id');
        $this->forge->createTable('order_attachments', true);
    }

    private function createOrderStatusHistory(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'from_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'to_status' => ['type' => 'VARCHAR', 'constraint' => 30],
            'remarks' => ['type' => 'TEXT', 'null' => true],
            'changed_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('order_id');
        $this->forge->createTable('order_status_history', true);
    }

    private function createJobCards(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'job_card_no' => ['type' => 'VARCHAR', 'constraint' => 40],
            'order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'order_item_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Pending'],
            'priority' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Medium'],
            'due_date' => ['type' => 'DATE', 'null' => true],
            'qc_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Pending'],
            'rework_count' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('job_card_no');
        $this->forge->addKey('order_id');
        $this->forge->addKey('status');
        $this->forge->createTable('job_cards', true);
    }

    private function createJobCardOperations(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'job_card_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'operation_name' => ['type' => 'VARCHAR', 'constraint' => 50],
            'sequence_no' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Pending'],
            'started_at' => ['type' => 'DATETIME', 'null' => true],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'remarks' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('job_card_id');
        $this->forge->createTable('job_card_operations', true);
    }

    private function createJobCardTimeline(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'job_card_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'event_type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'event_note' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('job_card_id');
        $this->forge->createTable('job_card_timeline', true);
    }
}

