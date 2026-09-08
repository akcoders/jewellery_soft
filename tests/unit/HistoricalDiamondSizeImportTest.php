<?php

namespace Tests\Unit;

use App\Database\Migrations\MapHistoricalDesignDiamondSizes;
use App\Services\HistoricalDiamondSizeImportService;
use App\Services\HistoricalDiamondSizeWorkbook;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class HistoricalDiamondSizeImportTest extends CIUnitTestCase
{
    private array $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifest = json_decode(file_get_contents(APPPATH . 'Database/Data/historical-diamond-size-map.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->db = Database::connect(['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => ''], false);
        $this->db->query('CREATE TABLE orders (id INTEGER PRIMARY KEY, order_no TEXT)');
        $this->db->query('CREATE TABLE production_ready_items (id INTEGER PRIMARY KEY, order_id INTEGER, source_sheet TEXT, source_row INTEGER)');
        $this->db->query('CREATE TABLE order_receive_details (id INTEGER PRIMARY KEY, order_id INTEGER, component_type TEXT, component_name TEXT, pcs DECIMAL(16,3), weight_cts DECIMAL(16,3))');
        $this->db->query('CREATE TABLE account_balances (id INTEGER PRIMARY KEY, balance DECIMAL(16,3))');
        $this->db->table('account_balances')->insert(['balance' => 123.45]);
        $names = ['LAB_GROWN' => 'CVD', 'NATURAL_VVS_VS' => 'VVS/EF', 'NATURAL_SI' => 'SI/IJ'];
        foreach ($this->manifest['ready_orders'] as $index => $ready) {
            $id = $index + 100;
            $this->db->table('orders')->insert(['id' => $id, 'order_no' => $ready['order_no']]);
            $this->db->table('production_ready_items')->insert(['order_id' => $id, 'source_sheet' => $ready['sheet'], 'source_row' => $ready['row']]);
            foreach ($ready['signature'] as $family => $totals) {
                $this->db->table('order_receive_details')->insert(['order_id' => $id, 'component_type' => 'diamond', 'component_name' => $names[$family] ?? $family, 'pcs' => $totals['pcs'], 'weight_cts' => $totals['weight_cts']]);
            }
        }
        require_once APPPATH . 'Database/Migrations/2026-09-08-000085_MapHistoricalDesignDiamondSizes.php';
    }

    protected function tearDown(): void
    {
        $this->db->close();
        parent::tearDown();
    }

    public function testManifestReconcilesAndDoesNotGuessAmbiguousPairingOrQuality(): void
    {
        $reader = new HistoricalDiamondSizeWorkbook();
        $blocks = array_column($this->manifest['blocks'], null, 'key');
        $this->assertCount(73, $blocks);
        $this->assertSame(['matched' => 35, 'unmatched' => 35, 'review' => 3], array_count_values(array_column($blocks, 'status')));
        foreach ($blocks as $block) {
            $this->assertEqualsWithDelta($block['pcs'], array_sum(array_column($block['lines'], 'pcs')), .0005);
            $this->assertEqualsWithDelta($block['weight_cts'], array_sum(array_column($block['lines'], 'weight_cts')), .0005);
            $this->assertEquals($block['signature'], $reader->signature($block['lines'], true));
        }
        $this->assertSame('review', $blocks['RANJAN:14']['status']);
        $this->assertSame('review', $blocks['RANJAN:20']['status']);
        $this->assertSame('review', $blocks['GR:63']['status']);
        $this->assertStringContainsString('color grades differ', $blocks['GR:63']['reason']);
        $this->assertSame('partial', $blocks['SATTA:17']['coverage']);
        $this->assertSame('PL26-SAFWAN-JEWELLERY-G03-R34', $blocks['SATTA:17']['target']['order_no']);
        $this->assertSame('', $blocks['SATTA:7']['lines'][6]['size_label']);
        $this->assertSame(-25.0, (float) $blocks['SATTA:7']['lines'][6]['pcs']);
        $this->assertSame('000000-00000', $blocks['GR:7']['lines'][0]['size_label']);
        $this->assertSame('7.7.5', $blocks['RHEEA:36']['lines'][0]['size_label']);
        $this->assertSame('5-5.5-6', $blocks['GR:30']['lines'][1]['size_label']);
    }

    public function testMigrationImportsOnlyMetadataAndRerunDoesNotDuplicate(): void
    {
        $before = $this->db->table('order_receive_details')->get()->getResultArray();
        $migration = new MapHistoricalDesignDiamondSizes(Database::forge($this->db));
        $migration->up();
        $expected = array_sum(array_map(static fn ($block) => $block['status'] === 'matched' ? count($block['lines']) : 0, $this->manifest['blocks']));
        $this->assertSame($expected, $this->db->table('order_diamond_size_details')->countAllResults());
        $this->assertSame(35, $this->db->table('order_diamond_size_details')->select('order_id')->distinct()->countAllResults());
        $this->assertSame(73, $this->db->table('historical_diamond_size_blocks')->countAllResults());
        $this->assertSame(3, $this->db->table('historical_diamond_size_blocks')->where('status', 'review')->countAllResults());
        $again = (new HistoricalDiamondSizeImportService($this->db))->import($this->manifest);
        $this->assertSame(0, $again['inserted_lines']);
        $this->assertSame($expected, $again['existing_lines']);
        $this->assertSame($before, $this->db->table('order_receive_details')->get()->getResultArray());
        $this->assertEqualsWithDelta(123.45, $this->db->table('account_balances')->get()->getRowArray()['balance'], .0001);
    }

    public function testMissingOrChangedReceivingIsSkippedAndReadOnlyPreviewDoesNotWrite(): void
    {
        $migration = new MapHistoricalDesignDiamondSizes(Database::forge($this->db));
        $migration->up();
        $order = $this->db->table('orders')->where('order_no', 'PL26-RHEEA-G01-R4')->get()->getRowArray();
        $this->db->table('order_receive_details')->where('order_id', $order['id'])->update(['pcs' => 999]);
        $missing = $this->db->table('orders')->where('order_no', 'PL26-RHEEA-G02-R16')->get()->getRowArray();
        $this->db->table('orders')->where('id', $missing['id'])->delete();
        $before = $this->db->table('historical_diamond_size_blocks')->get()->getResultArray();
        $summary = (new HistoricalDiamondSizeImportService($this->db))->import($this->manifest, false);
        $this->assertSame(2, $summary['skipped']);
        $this->assertSame(33, $summary['mapped_orders']);
        $this->assertSame(0, $summary['inserted_lines']);
        $this->assertSame($before, $this->db->table('historical_diamond_size_blocks')->get()->getResultArray());
    }

    public function testConflictingExistingReferenceRollsBackTheEntireImport(): void
    {
        $migration = new MapHistoricalDesignDiamondSizes(Database::forge($this->db));
        $migration->up();
        $this->db->table('order_diamond_size_details')->where('source_sheet', 'UM')->where('source_row', 7)->update(['order_id' => 999999]);
        $before = $this->db->table('historical_diamond_size_blocks')->get()->getResultArray();
        try {
            (new HistoricalDiamondSizeImportService($this->db))->import($this->manifest);
            $this->fail('Conflicting source identity must not be reassigned.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Conflicting size reference', $error->getMessage());
        }
        $this->assertSame($before, $this->db->table('historical_diamond_size_blocks')->get()->getResultArray());
    }
}
