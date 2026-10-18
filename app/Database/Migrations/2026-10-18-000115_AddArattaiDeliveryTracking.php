<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddArattaiDeliveryTracking extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('whatsapp_sender_queue')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('delivery_status', 'whatsapp_sender_queue')) {
            $fields['delivery_status'] = [
                'type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'provider_message_id',
            ];
        }
        if (! $this->db->fieldExists('delivery_updated_at', 'whatsapp_sender_queue')) {
            $fields['delivery_updated_at'] = [
                'type' => 'DATETIME', 'null' => true, 'after' => 'delivery_status',
            ];
        }
        if (! $this->db->fieldExists('provider_failure_reason', 'whatsapp_sender_queue')) {
            $fields['provider_failure_reason'] = [
                'type' => 'TEXT', 'null' => true, 'after' => 'delivery_updated_at',
            ];
        }
        if ($fields !== []) {
            $this->forge->addColumn('whatsapp_sender_queue', $fields);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('whatsapp_sender_queue')) {
            return;
        }
        $columns = array_values(array_filter(
            ['delivery_status', 'delivery_updated_at', 'provider_failure_reason'],
            fn (string $column): bool => $this->db->fieldExists($column, 'whatsapp_sender_queue')
        ));
        if ($columns !== []) {
            $this->forge->dropColumn('whatsapp_sender_queue', $columns);
        }
    }
}
