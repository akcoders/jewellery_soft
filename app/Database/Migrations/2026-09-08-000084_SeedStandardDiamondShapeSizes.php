<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeedStandardDiamondShapeSizes extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('diamond_size_masters')) {
            return;
        }

        $this->addDimensionColumns();

        $roundId = $this->shapeId('ROUND', 'Round');
        $princessId = $this->shapeId('PRINCESS', 'Princess');
        $marquiseId = $this->shapeId('MARQUISE', 'Marquise');

        $roundSizes = [
            ['-000000', 0.65], ['000000-00000', 0.70], ['00000-0000', 0.80],
            ['0000-000', 0.85], ['000-00', 0.90], ['00-0', 1.00], ['0-1', 1.05],
            ['1-1.5', 1.10], ['1.5-2', 1.15], ['2-2.5', 1.20], ['2.5-3', 1.25],
            ['3-3.5', 1.35], ['3.5-4', 1.40], ['4-4.5', 1.45], ['4.5-5', 1.50],
            ['5-5.5', 1.55], ['5.5-6', 1.60], ['6-6.5', 1.70], ['6.5-7', 1.80],
            ['7-7.5', 1.90], ['7.5-8', 2.00], ['8-8.5', 2.10], ['8.5-9', 2.20],
            ['9-9.5', 2.30], ['9.5-10', 2.40], ['10-10.5', 2.50], ['10.5-11', 2.60],
            ['11-11.5', 2.70], ['11.5-12', 2.80], ['12-12.5', 2.90], ['12.5-13', 3.00],
            ['13-13.5', 3.10], ['13.5-14', 3.20], ['14-14.5', 3.30], ['14.5-15', 3.40],
            ['15-15.5', 3.50], ['15.5-16', 3.60], ['16-16.5', 3.70], ['16.5-17', 3.80],
            ['17-17.5', 3.90], ['17.5-18', 4.00], ['18', 4.10], ['18.5', 4.20],
            ['19', 4.30], ['19.5', 4.40], ['20', 4.50],
        ];
        foreach ($roundSizes as $index => [$chalni, $diameter]) {
            $this->seedRoundSize($roundId, $index + 1, $chalni, $diameter);
        }

        // The combined 5.2/5.3 and 5.4/5.5 cells in the supplied chart are
        // expanded into individual calibrated Princess sizes for exact tracing.
        for ($tenths = 14; $tenths <= 58; $tenths++) {
            $side = $tenths / 10;
            $this->seedSize(
                $princessId,
                'STD-PRI-' . str_pad((string) $tenths, 3, '0', STR_PAD_LEFT),
                $this->decimalLabel($side) . ' mm',
                null,
                $side,
                $side,
                ($tenths - 13) * 10
            );
        }

        $marquiseSizes = [
            [2.50, 1.50], [2.95, 1.75], [3.00, 1.50], [3.50, 1.50],
            [3.00, 2.00], [3.50, 2.00], [4.00, 2.00], [4.05, 2.25],
            [4.50, 2.50], [5.00, 2.00], [5.50, 2.50], [5.00, 2.50],
            [6.00, 3.00], [7.00, 3.00],
        ];
        foreach ($marquiseSizes as $index => [$length, $width]) {
            $this->seedSize(
                $marquiseId,
                'STD-MAR-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                $this->decimalLabel($length) . ' × ' . $this->decimalLabel($width) . ' mm',
                null,
                $length,
                $width,
                ($index + 1) * 10
            );
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('diamond_size_masters')) {
            return;
        }

        $this->db->table('diamond_size_masters')
            ->groupStart()
                ->like('size_code', 'STD-RND-', 'after')
                ->orLike('size_code', 'STD-PRI-', 'after')
                ->orLike('size_code', 'STD-MAR-', 'after')
            ->groupEnd()
            ->delete();

        foreach (['chalni_label', 'length_mm', 'width_mm'] as $column) {
            if ($this->db->fieldExists($column, 'diamond_size_masters')) {
                $this->forge->dropColumn('diamond_size_masters', $column);
            }
        }
    }

    private function addDimensionColumns(): void
    {
        if (! $this->db->fieldExists('chalni_label', 'diamond_size_masters')) {
            $this->forge->addColumn('diamond_size_masters', [
                'chalni_label' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'size_label'],
            ]);
        }
        if (! $this->db->fieldExists('length_mm', 'diamond_size_masters')) {
            $this->forge->addColumn('diamond_size_masters', [
                'length_mm' => ['type' => 'DECIMAL', 'constraint' => '8,3', 'null' => true, 'after' => 'max_mm'],
            ]);
        }
        if (! $this->db->fieldExists('width_mm', 'diamond_size_masters')) {
            $this->forge->addColumn('diamond_size_masters', [
                'width_mm' => ['type' => 'DECIMAL', 'constraint' => '8,3', 'null' => true, 'after' => 'length_mm'],
            ]);
        }
    }

    private function shapeId(string $code, string $name): int
    {
        $row = $this->db->table('diamond_shape_masters')->select('id')->where('code', $code)->get()->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }

        $this->db->table('diamond_shape_masters')->insert([
            'code' => $code,
            'name' => $name,
            'sort_order' => 0,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function seedRoundSize(int $shapeId, int $position, string $chalni, float $diameter): void
    {
        $legacy = $this->db->table('diamond_size_masters')
            ->select('id')
            ->where('shape_id', $shapeId)
            ->where('size_label', 'Chalni ' . $chalni)
            ->get()
            ->getRowArray();
        $label = $this->decimalLabel($diameter) . ' mm · Chalni ' . $chalni;
        if ($legacy) {
            $this->db->table('diamond_size_masters')->where('id', (int) $legacy['id'])->update([
                'size_label' => $label,
                'chalni_label' => $chalni,
                'length_mm' => $diameter,
                'width_mm' => $diameter,
                'sort_order' => $position * 10,
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $this->seedSize(
            $shapeId,
            'STD-RND-' . str_pad((string) $position, 3, '0', STR_PAD_LEFT),
            $label,
            $chalni,
            $diameter,
            $diameter,
            $position * 10
        );
    }

    private function seedSize(
        int $shapeId,
        string $code,
        string $label,
        ?string $chalni,
        float $length,
        float $width,
        int $sortOrder
    ): void {
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            'INSERT INTO diamond_size_masters
                (shape_id, size_code, size_label, chalni_label, min_mm, max_mm, length_mm, width_mm, sort_order, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE
                size_label = VALUES(size_label), chalni_label = VALUES(chalni_label),
                length_mm = VALUES(length_mm), width_mm = VALUES(width_mm),
                sort_order = VALUES(sort_order), is_active = 1, updated_at = VALUES(updated_at)',
            [$shapeId, $code, $label, $chalni, $length, $width, $sortOrder, $now, $now]
        );
    }

    private function decimalLabel(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
