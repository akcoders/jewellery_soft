<?php

namespace App\Commands;

use App\Services\HistoricalDiamondSizeImportService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ImportHistoricalDiamondSizes extends BaseCommand
{
    protected $group = 'Production';
    protected $name = 'designs:import-historical-diamond-sizes';
    protected $description = 'Validate historical diamond size references against receiving; use --apply to insert metadata.';
    protected $options = ['--apply' => 'Save reference metadata after migration 085. Default is read-only validation.'];

    public function run(array $params)
    {
        $manifest = json_decode(file_get_contents(APPPATH . 'Database/Data/historical-diamond-size-map.json'), true, 512, JSON_THROW_ON_ERROR);
        $summary = (new HistoricalDiamondSizeImportService())->import($manifest, (bool) CLI::getOption('apply'));
        foreach ($summary['blocks'] as $block) {
            CLI::write($block['source'] . ': ' . $block['status'] . ' — ' . $block['reason']);
        }
        unset($summary['blocks']);
        CLI::write(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
