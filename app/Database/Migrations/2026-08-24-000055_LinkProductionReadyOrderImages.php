<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class LinkProductionReadyOrderImages extends Migration
{
    /** @var list<array{sheet:string,row:int,filename:string}> */
    private const OFFSET_IMAGES = [
        ['sheet' => 'BEMAL', 'row' => 37, 'filename' => 'bemal-r36-6.jpeg'],
        ['sheet' => 'sattar', 'row' => 8, 'filename' => 'sattar-r7-2.jpeg'],
    ];

    public function up()
    {
        if (! $this->canLinkImages()) {
            return;
        }

        foreach (self::OFFSET_IMAGES as $target) {
            $items = $this->db->table('production_ready_items')
                ->select('id, batch_id')
                ->where('source_sheet', $target['sheet'])
                ->where('source_row', $target['row'])
                ->groupStart()
                    ->where('image_path IS NULL', null, false)
                    ->orWhere('image_path', '')
                ->groupEnd()
                ->get()
                ->getResultArray();

            foreach ($items as $item) {
                $sample = $this->db->table('production_ready_items')
                    ->select('image_path')
                    ->where('batch_id', (int) $item['batch_id'])
                    ->where('image_path IS NOT NULL', null, false)
                    ->where('image_path !=', '')
                    ->get(1)
                    ->getRowArray();
                if (! $sample) {
                    continue;
                }

                $relativePath = trim(str_replace('\\', '/', dirname((string) $sample['image_path'])), '/') . '/' . $target['filename'];
                if (! is_file(WRITEPATH . $relativePath)) {
                    continue;
                }
                $this->db->table('production_ready_items')->where('id', (int) $item['id'])->update(['image_path' => $relativePath]);
            }
        }

        $this->synchronizeFinishedJewelleryImages();
    }

    public function down()
    {
        if (! $this->canLinkImages()) {
            return;
        }

        foreach (self::OFFSET_IMAGES as $target) {
            $items = $this->db->table('production_ready_items')
                ->select('id')
                ->where('source_sheet', $target['sheet'])
                ->where('source_row', $target['row'])
                ->like('image_path', '/' . $target['filename'], 'before')
                ->get()
                ->getResultArray();
            foreach ($items as $item) {
                $this->db->table('production_ready_items')->where('id', (int) $item['id'])->update(['image_path' => null]);
                $this->db->table('fg_items')->where('production_ready_item_id', (int) $item['id'])->update(['source_image_path' => null]);
            }
        }
    }

    private function canLinkImages(): bool
    {
        return $this->db->tableExists('production_ready_items')
            && $this->db->tableExists('fg_items')
            && $this->db->fieldExists('image_path', 'production_ready_items')
            && $this->db->fieldExists('source_image_path', 'fg_items');
    }

    private function synchronizeFinishedJewelleryImages(): void
    {
        $items = $this->db->table('production_ready_items')
            ->select('id, image_path')
            ->where('image_path IS NOT NULL', null, false)
            ->where('image_path !=', '')
            ->get()
            ->getResultArray();
        foreach ($items as $item) {
            $this->db->table('fg_items')
                ->where('production_ready_item_id', (int) $item['id'])
                ->update(['source_image_path' => (string) $item['image_path']]);
        }
    }
}
