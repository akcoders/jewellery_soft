<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWhatsappNotificationFieldsToOrders extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('orders')) {
            return;
        }

        $fields = $this->db->getFieldNames('orders');
        $add = [];

        if (! in_array('whatsapp_notification_number', $fields, true)) {
            $add['whatsapp_notification_number'] = [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => true,
                'after' => 'order_notes',
            ];
        }

        if (! in_array('whatsapp_notify_order_created', $fields, true)) {
            $add['whatsapp_notify_order_created'] = [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
                'after' => 'whatsapp_notification_number',
            ];
        }

        if ($add !== []) {
            $this->forge->addColumn('orders', $add);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('orders')) {
            return;
        }

        $fields = $this->db->getFieldNames('orders');
        foreach (['whatsapp_notify_order_created', 'whatsapp_notification_number'] as $field) {
            if (in_array($field, $fields, true)) {
                $this->forge->dropColumn('orders', $field);
            }
        }
    }
}
