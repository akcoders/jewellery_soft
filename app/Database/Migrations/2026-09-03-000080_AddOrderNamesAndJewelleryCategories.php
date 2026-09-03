<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOrderNamesAndJewelleryCategories extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('order_categories')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => 100],
                'code' => ['type' => 'VARCHAR', 'constraint' => 12],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('name');
            $this->forge->addUniqueKey('code');
            $this->forge->createTable('order_categories', true);
        }

        $this->seedCategories();

        if ($this->db->tableExists('orders')) {
            if (! $this->db->fieldExists('order_name', 'orders')) {
                $this->forge->addColumn('orders', [
                    'order_name' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true, 'after' => 'order_no'],
                ]);
            }
            if (! $this->db->fieldExists('order_category_id', 'orders')) {
                $this->forge->addColumn('orders', [
                    'order_category_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'order_name'],
                ]);
                $this->db->query('ALTER TABLE `orders` ADD INDEX `idx_orders_order_category_id` (`order_category_id`)');
            }

            $other = $this->db->table('order_categories')->where('code', 'OTH')->get()->getRowArray();
            $otherId = (int) ($other['id'] ?? 0);
            if ($otherId > 0) {
                $this->db->table('orders')->where('order_category_id', null)->update(['order_category_id' => $otherId]);
            }

            $this->db->query(
                "UPDATE `orders` o
                 LEFT JOIN (
                    SELECT oi.order_id, MIN(oi.id) first_item_id
                    FROM `order_items` oi
                    GROUP BY oi.order_id
                 ) first_item ON first_item.order_id = o.id
                 LEFT JOIN `order_items` oi ON oi.id = first_item.first_item_id
                 SET o.order_name = COALESCE(NULLIF(TRIM(oi.item_description), ''), o.order_no)
                 WHERE o.order_name IS NULL OR TRIM(o.order_name) = ''"
            );

            if ($otherId > 0) {
                $matchers = [
                    'JMK' => ['Jhumki'],
                    'EAR' => ['Earring', 'Ear Ring'],
                    'BGL' => ['Bangle', 'Chudi'],
                    'BRC' => ['Bracelet'],
                    'NCK' => ['Necklace'],
                    'PND' => ['Pendant'],
                    'HRM' => ['Haaram', 'Haram'],
                    'MGS' => ['Mangalsutra'],
                    'CHN' => ['Chain'],
                    'NSP' => ['Nose Pin', 'Nath'],
                    'ANK' => ['Anklet', 'Payal'],
                    'KDA' => ['Kada'],
                    'BCH' => ['Brooch'],
                    'RNG' => ['Ring'],
                ];
                foreach ($matchers as $code => $keywords) {
                    $category = $this->db->table('order_categories')->where('code', $code)->get()->getRowArray();
                    if (! $category) {
                        continue;
                    }
                    $builder = $this->db->table('orders')->where('order_category_id', $otherId)->groupStart();
                    foreach ($keywords as $index => $keyword) {
                        $index === 0 ? $builder->like('order_name', $keyword) : $builder->orLike('order_name', $keyword);
                    }
                    $builder->groupEnd()->update(['order_category_id' => (int) $category['id']]);
                }
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('orders')) {
            if ($this->db->fieldExists('order_category_id', 'orders')) {
                $this->forge->dropColumn('orders', 'order_category_id');
            }
            if ($this->db->fieldExists('order_name', 'orders')) {
                $this->forge->dropColumn('orders', 'order_name');
            }
        }

        $this->forge->dropTable('order_categories', true);
    }

    private function seedCategories(): void
    {
        $now = date('Y-m-d H:i:s');
        $categories = [
            ['name' => 'Ring', 'code' => 'RNG'],
            ['name' => 'Earring', 'code' => 'EAR'],
            ['name' => 'Jhumki', 'code' => 'JMK'],
            ['name' => 'Bangle', 'code' => 'BGL'],
            ['name' => 'Bracelet', 'code' => 'BRC'],
            ['name' => 'Necklace', 'code' => 'NCK'],
            ['name' => 'Pendant', 'code' => 'PND'],
            ['name' => 'Haaram', 'code' => 'HRM'],
            ['name' => 'Mangalsutra', 'code' => 'MGS'],
            ['name' => 'Chain', 'code' => 'CHN'],
            ['name' => 'Nose Pin', 'code' => 'NSP'],
            ['name' => 'Anklet', 'code' => 'ANK'],
            ['name' => 'Kada', 'code' => 'KDA'],
            ['name' => 'Brooch', 'code' => 'BCH'],
            ['name' => 'Other', 'code' => 'OTH'],
        ];

        foreach ($categories as $category) {
            if ($this->db->table('order_categories')->where('name', $category['name'])->countAllResults() > 0) {
                continue;
            }
            $this->db->table('order_categories')->insert($category + [
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
