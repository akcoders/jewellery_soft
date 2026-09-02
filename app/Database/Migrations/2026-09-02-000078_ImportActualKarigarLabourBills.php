<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ImportActualKarigarLabourBills extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('labour_bills') || ! $this->db->tableExists('labour_bill_jobworks')) {
            return;
        }

        $this->db->transStart();
        $this->clearLegacyBillAndPaymentData();

        foreach ($this->billRows() as $bill) {
            $karigar = $this->db->table('karigars')->select('id')->where('name', $bill['karigar'])->get()->getRowArray();
            if (! $karigar) {
                continue;
            }
            $gstMasterId = $this->gstMasterId((string) $bill['gst_master']);
            $gstAmount = round((float) $bill['cgst'] + (float) $bill['sgst'] + (float) $bill['igst'], 2);
            $taxable = round((float) $bill['taxable'], 2);
            $components = [];
            foreach ([
                ['name' => 'CGST', 'rate' => (float) $bill['cgst_rate'], 'amount' => (float) $bill['cgst']],
                ['name' => 'SGST', 'rate' => (float) $bill['sgst_rate'], 'amount' => (float) $bill['sgst']],
                ['name' => 'IGST', 'rate' => (float) $bill['igst_rate'], 'amount' => (float) $bill['igst']],
            ] as $component) {
                if ($component['rate'] > 0 || $component['amount'] != 0.0) {
                    $components[] = [
                        'name' => $component['name'],
                        'percentage' => $component['rate'],
                        'amount' => round($component['amount'], 2),
                    ];
                }
            }

            $this->db->table('labour_bills')->insert([
                'bill_no' => $bill['bill_no'],
                'bill_date' => $bill['bill_date'],
                'order_id' => null,
                'receive_movement_id' => null,
                'karigar_id' => (int) $karigar['id'],
                'gst_master_id' => $gstMasterId,
                'tax_breakup_json' => json_encode($components, JSON_UNESCAPED_SLASHES),
                'gold_weight_gm' => $bill['net_weight'],
                'rate_per_gm' => (float) $bill['net_weight'] > 0 ? round($taxable / (float) $bill['net_weight'], 2) : 0,
                'labour_amount' => $taxable,
                'other_amount' => 0,
                'taxable_amount' => $taxable,
                'cgst_rate' => $bill['cgst_rate'],
                'cgst_amount' => $bill['cgst'],
                'sgst_rate' => $bill['sgst_rate'],
                'sgst_amount' => $bill['sgst'],
                'igst_rate' => $bill['igst_rate'],
                'igst_amount' => $bill['igst'],
                'gst_amount' => $gstAmount,
                'round_off_amount' => $bill['round_off'],
                'total_amount' => $bill['total'],
                'due_date' => null,
                'payment_status' => 'Pending',
                'attachment_path' => 'app/Database/Data/labour_bills/' . $bill['attachment'],
                'attachment_name' => basename((string) $bill['attachment']),
                'source_type' => 'Imported invoice',
                'notes' => 'Imported from verified labour invoice. Payment history intentionally reset for fresh manual entry.',
                'created_by' => null,
                'created_at' => $bill['bill_date'] . ' 12:00:00',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $billId = (int) $this->db->insertID();
            $this->linkJobworks($billId, (int) $karigar['id'], (array) $bill['jobworks']);
        }

        $this->db->transComplete();
    }

    public function down()
    {
        if (! $this->db->tableExists('labour_bills')) {
            return;
        }
        $ids = array_column(
            $this->db->table('labour_bills')->select('id')->where('source_type', 'Imported invoice')->get()->getResultArray(),
            'id'
        );
        if ($ids !== [] && $this->db->tableExists('labour_bill_jobworks')) {
            $this->db->table('labour_bill_jobworks')->whereIn('labour_bill_id', $ids)->delete();
        }
        if ($ids !== []) {
            $this->db->table('labour_bills')->whereIn('id', $ids)->delete();
        }
    }

    private function clearLegacyBillAndPaymentData(): void
    {
        if ($this->db->tableExists('labour_bill_payments')) {
            $this->db->query('DELETE FROM `labour_bill_payments`');
        }
        if ($this->db->tableExists('account_payments')) {
            $this->db->table('account_payments')->where('party_type', 'karigar')->delete();
        }
        if ($this->db->tableExists('karigar_payment_ledgers')) {
            $this->db->query('DELETE FROM `karigar_payment_ledgers`');
        }
        $this->db->query('DELETE FROM `labour_bill_jobworks`');
        $this->db->query('DELETE FROM `labour_bills`');
        if ($this->db->tableExists('production_ready_items')) {
            $this->db->table('production_ready_items')->update(['payment_status' => 'Pending', 'payment_date' => null]);
        }
    }

    /** @param list<array{0:string,1:int}> $jobworks */
    private function linkJobworks(int $billId, int $karigarId, array $jobworks): void
    {
        foreach ($jobworks as $selector) {
            [$sourceSheet, $sourceRow] = $selector;
            $ready = $this->db->table('production_ready_items')
                ->where('karigar_id', $karigarId)
                ->where('source_sheet', $sourceSheet)
                ->where('source_row', $sourceRow)
                ->get()->getRowArray();
            if (! $ready) {
                continue;
            }
            $this->db->table('labour_bill_jobworks')->insert([
                'labour_bill_id' => $billId,
                'jobwork_type' => 'ready_item',
                'jobwork_id' => (int) $ready['id'],
                'order_id' => (int) ($ready['order_id'] ?? 0) ?: null,
                'receive_movement_id' => null,
                'jobwork_date' => $ready['ready_date'] ?? null,
                'description' => trim((string) (($ready['reference_no'] ?? '') ?: ($ready['design_name'] ?? ''))) ?: null,
                'gross_weight_gm' => (float) ($ready['gross_weight_gm'] ?? 0),
                'net_weight_gm' => (float) ($ready['net_weight_gm'] ?? 0),
                'labour_amount' => (float) ($ready['labour_charges'] ?? 0),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function gstMasterId(string $name): ?int
    {
        if (! $this->db->tableExists('gst_masters')) {
            return null;
        }
        $row = $this->db->table('gst_masters')->select('id')->where('name', $name)->get()->getRowArray();
        return $row ? (int) $row['id'] : null;
    }

    /** @return list<array<string,mixed>> */
    private function billRows(): array
    {
        $local = static fn(array $row): array => $row + [
            'gst_master' => 'Local GST 5%', 'cgst_rate' => 2.5, 'sgst_rate' => 2.5, 'igst_rate' => 0,
            'igst' => 0, 'round_off' => 0, 'jobworks' => [],
        ];
        $interstate = static fn(array $row): array => $row + [
            'gst_master' => 'Interstate GST 5%', 'cgst_rate' => 0, 'sgst_rate' => 0, 'igst_rate' => 5,
            'cgst' => 0, 'sgst' => 0, 'round_off' => 0, 'jobworks' => [],
        ];

        return [
            $interstate(['karigar' => 'SHREE GOURANGO', 'bill_no' => 'SGG/01/2026-27', 'bill_date' => '2026-04-07', 'net_weight' => 14.684, 'taxable' => 3040, 'igst' => 152, 'total' => 3192, 'attachment' => 'gourango/01-2026-04-07.jpeg', 'jobworks' => [['SHREE GOURANGO', 4], ['SHREE GOURANGO', 7], ['SHREE GOURANGO', 11], ['SHREE GOURANGO', 15]]]),
            $interstate(['karigar' => 'SHREE GOURANGO', 'bill_no' => 'SGG/02/2026-27', 'bill_date' => '2026-04-13', 'net_weight' => 72.212, 'taxable' => 21664, 'igst' => 1083, 'total' => 22747, 'attachment' => 'gourango/02-2026-04-13.jpeg', 'jobworks' => [['SHREE GOURANGO', 24]]]),
            $interstate(['karigar' => 'SHREE GOURANGO', 'bill_no' => 'SGG/03/2026-27', 'bill_date' => '2026-04-22', 'net_weight' => 14.482, 'taxable' => 6224, 'igst' => 311, 'total' => 6535, 'attachment' => 'gourango/03-2026-04-22.jpeg', 'jobworks' => [['SHREE GOURANGO', 32], ['SHREE GOURANGO', 37], ['SHREE GOURANGO', 40], ['SHREE GOURANGO', 44]]]),
            $interstate(['karigar' => 'SHREE GOURANGO', 'bill_no' => 'SGG/04/2026-27', 'bill_date' => '2026-06-03', 'net_weight' => 23.104, 'taxable' => 7810, 'igst' => 391, 'total' => 8201, 'attachment' => 'gourango/04-2026-06-03.jpeg', 'jobworks' => [['SHREE GOURANGO', 53], ['SHREE GOURANGO', 57]]]),
            $interstate(['karigar' => 'SHREE GOURANGO', 'bill_no' => 'SGG/05/2026-27', 'bill_date' => '2026-07-06', 'net_weight' => 34.276, 'taxable' => 3904, 'igst' => 195, 'total' => 4099, 'attachment' => 'gourango/05-2026-07-06.jpeg', 'jobworks' => [['SHREE GOURANGO', 66], ['SHREE GOURANGO', 72], ['SHREE GOURANGO', 77]]]),
            $interstate(['karigar' => 'SHREE GOURANGO', 'bill_no' => 'SGG/06/2026-27', 'bill_date' => '2026-07-08', 'net_weight' => 32.560, 'taxable' => 34638, 'igst' => 1732, 'total' => 36370, 'attachment' => 'gourango/06-2026-07-08.jpeg', 'jobworks' => [['SHREE GOURANGO', 87]]]),
            $interstate(['karigar' => 'SHREE GOURANGO', 'bill_no' => 'SGG/07/2026-27', 'bill_date' => '2026-07-23', 'net_weight' => 54.344, 'taxable' => 15680, 'igst' => 784, 'total' => 16464, 'attachment' => 'gourango/07-2026-07-23.jpeg', 'jobworks' => [['SHREE GOURANGO', 96], ['SHREE GOURANGO', 101]]]),
            $interstate(['karigar' => 'SHREE GOURANGO', 'bill_no' => 'SGG/08/2026-27', 'bill_date' => '2026-08-17', 'net_weight' => 19.272, 'taxable' => 10308, 'igst' => 516, 'total' => 10824, 'attachment' => 'gourango/08-2026-08-17.jpeg', 'jobworks' => [['SHREE GOURANGO', 120], ['SHREE GOURANGO', 124], ['SHREE GOURANGO', 128], ['SHREE GOURANGO', 131]]]),
            $interstate(['karigar' => 'SHREE GOURANGO', 'bill_no' => 'SGG/09/2026-27', 'bill_date' => '2026-08-26', 'net_weight' => 24.020, 'taxable' => 10540, 'igst' => 527, 'total' => 11067, 'attachment' => 'gourango/09-2026-08-26.jpeg', 'jobworks' => [['SHREE GOURANGO', 140]]]),

            $local(['karigar' => 'JGD DIAMONDS', 'bill_no' => 'JGD/LS/002/26-27', 'bill_date' => '2026-04-17', 'net_weight' => 22.886, 'taxable' => 9154, 'cgst' => 229, 'sgst' => 229, 'total' => 9612, 'attachment' => 'jgd/JGD-LS-002-26-27.pdf', 'jobworks' => [['GR', 4], ['GR', 10]]]),
            $local(['karigar' => 'JGD DIAMONDS', 'bill_no' => 'JGD/LS/003/26-27', 'bill_date' => '2026-05-05', 'net_weight' => 27.392, 'taxable' => 10957, 'cgst' => 274, 'sgst' => 274, 'total' => 11505, 'attachment' => 'jgd/JGD-LS-003-26-27.pdf', 'jobworks' => [['GR', 19], ['GR', 25]]]),
            $local(['karigar' => 'JGD DIAMONDS', 'bill_no' => 'JGD/LS/004/26-27', 'bill_date' => '2026-05-20', 'net_weight' => 5.806, 'taxable' => 2322, 'cgst' => 58, 'sgst' => 58, 'total' => 2438, 'attachment' => 'jgd/JGD-LS-004-26-27.pdf', 'jobworks' => [['GR', 34]]]),
            $local(['karigar' => 'JGD DIAMONDS', 'bill_no' => 'JGD/LS/005/26-27', 'bill_date' => '2026-06-08', 'net_weight' => 28.330, 'taxable' => 8359, 'cgst' => 209, 'sgst' => 209, 'total' => 8777, 'attachment' => 'jgd/JGD-LS-005-26-27.pdf', 'jobworks' => [['GR', 48], ['GR', 52], ['GR', 56]]]),
            $local(['karigar' => 'JGD DIAMONDS', 'bill_no' => 'JGD/LS/006/26-27', 'bill_date' => '2026-07-09', 'net_weight' => 34.136, 'taxable' => 13654, 'cgst' => 341, 'sgst' => 341, 'total' => 14336, 'attachment' => 'jgd/JGD-LS-006-26-27.pdf', 'jobworks' => [['GR', 66]]]),
            $local(['karigar' => 'JGD DIAMONDS', 'bill_no' => 'JGD/LS/007/26-27', 'bill_date' => '2026-07-21', 'net_weight' => 15.142, 'taxable' => 10021, 'cgst' => 251, 'sgst' => 251, 'total' => 10523, 'attachment' => 'jgd/JGD-LS-007-26-27.pdf', 'jobworks' => [['GR', 76]]]),
            $local(['karigar' => 'JGD DIAMONDS', 'bill_no' => 'JGD/LS/009/26-27', 'bill_date' => '2026-08-31', 'net_weight' => 7.124, 'taxable' => 2850, 'cgst' => 71, 'sgst' => 71, 'total' => 2992, 'attachment' => 'jgd/JGD-LS-009-26-27.pdf']),

            $interstate(['karigar' => 'RHEEA JEWELS', 'bill_no' => 'JW001/26-27', 'bill_date' => '2026-04-15', 'net_weight' => 22.812, 'taxable' => 21671, 'igst' => 1084, 'total' => 22755, 'attachment' => 'rheea/JW001-26-27.pdf', 'jobworks' => [['RHEEA', 4]]]),
            $interstate(['karigar' => 'RHEEA JEWELS', 'bill_no' => 'JW002/26-27', 'bill_date' => '2026-04-18', 'net_weight' => 42.292, 'taxable' => 29393, 'igst' => 1470, 'total' => 30863, 'attachment' => 'rheea/JW002-26-27.jpeg', 'jobworks' => [['RHEEA', 16]]]),
            $interstate(['karigar' => 'RHEEA JEWELS', 'bill_no' => 'JW003/26-27', 'bill_date' => '2026-06-09', 'net_weight' => 12.334, 'taxable' => 11717, 'igst' => 586, 'total' => 12303, 'attachment' => 'rheea/JW003-26-27.pdf', 'jobworks' => [['RHEEA', 32]]]),
            $interstate(['karigar' => 'RHEEA JEWELS', 'bill_no' => 'JW004/26-27', 'bill_date' => '2026-06-16', 'net_weight' => 41.100, 'taxable' => 89265, 'igst' => 4463, 'total' => 93728, 'attachment' => 'rheea/JW004-26-27.pdf', 'jobworks' => [['RHEEA', 40], ['RHEEA', 44]]]),
            $interstate(['karigar' => 'RHEEA JEWELS', 'bill_no' => 'JW005/26-27', 'bill_date' => '2026-08-17', 'net_weight' => 42.252, 'taxable' => 29576, 'igst' => 1479, 'total' => 31055, 'attachment' => 'rheea/JW005-26-27.pdf', 'jobworks' => [['RHEEA', 54]]]),
            $interstate(['karigar' => 'RHEEA JEWELS', 'bill_no' => 'JW006/26-27', 'bill_date' => '2026-08-24', 'net_weight' => 38.030, 'taxable' => 29473, 'igst' => 1474, 'total' => 30947, 'attachment' => 'rheea/JW006-26-27.jpeg']),

            $interstate(['karigar' => 'SAFWAN JEWELLERY', 'bill_no' => 'SFW/02-06-2026', 'bill_date' => '2026-06-02', 'net_weight' => 26.970, 'taxable' => 9920, 'igst' => 496, 'total' => 10416, 'attachment' => 'safwan/2026-06-02.pdf', 'jobworks' => [['SAFWAN JEWELLERY', 4], ['SAFWAN JEWELLERY', 8], ['SAFWAN JEWELLERY', 11], ['SAFWAN JEWELLERY', 14]]]),
            $interstate(['karigar' => 'SAFWAN JEWELLERY', 'bill_no' => 'SFW/08-07-2026', 'bill_date' => '2026-07-08', 'net_weight' => 24.188, 'taxable' => 26500, 'igst' => 1325, 'total' => 27825, 'attachment' => 'safwan/2026-07-08.pdf', 'jobworks' => [['SAFWAN JEWELLERY', 21], ['SAFWAN JEWELLERY', 26]]]),
            $interstate(['karigar' => 'SAFWAN JEWELLERY', 'bill_no' => 'SFW/24-08-2026', 'bill_date' => '2026-08-24', 'net_weight' => 33.046, 'taxable' => 18160, 'igst' => 908, 'total' => 19068, 'attachment' => 'safwan/2026-08-24.pdf']),

            $local(['karigar' => 'UTTAM MAL', 'bill_no' => '1/2026-2027', 'bill_date' => '2026-04-06', 'net_weight' => 161.836, 'taxable' => 56642.60, 'cgst' => 1416.07, 'sgst' => 1416.07, 'round_off' => 0.26, 'total' => 59475, 'attachment' => 'uttam/01-2026-2027.pdf', 'jobworks' => [['UTTAM MAL', 4], ['UTTAM MAL', 10]]]),
            $local(['karigar' => 'UTTAM MAL', 'bill_no' => '2/2026-2027', 'bill_date' => '2026-04-15', 'net_weight' => 74.154, 'taxable' => 25953.90, 'cgst' => 648.85, 'sgst' => 648.85, 'round_off' => 0.40, 'total' => 27252, 'attachment' => 'uttam/02-2026-2027.pdf', 'jobworks' => [['UTTAM MAL', 18], ['UTTAM MAL', 23]]]),
            $local(['karigar' => 'UTTAM MAL', 'bill_no' => '3/2026-2027', 'bill_date' => '2026-04-22', 'net_weight' => 68.930, 'taxable' => 24126, 'cgst' => 603.15, 'sgst' => 603.15, 'round_off' => -0.30, 'total' => 25332, 'attachment' => 'uttam/03-2026-2027.pdf', 'jobworks' => [['UTTAM MAL', 32], ['UTTAM MAL', 36], ['UTTAM MAL', 40]]]),
            $local(['karigar' => 'UTTAM MAL', 'bill_no' => '4/2026-2027', 'bill_date' => '2026-05-09', 'net_weight' => 52.820, 'taxable' => 18487, 'cgst' => 462.18, 'sgst' => 462.18, 'round_off' => -0.36, 'total' => 19411, 'attachment' => 'uttam/04-2026-2027.pdf', 'jobworks' => [['UTTAM MAL', 49], ['UTTAM MAL', 52], ['UTTAM MAL', 59]]]),
            $local(['karigar' => 'UTTAM MAL', 'bill_no' => '5/2026-2027', 'bill_date' => '2026-05-16', 'net_weight' => 123.404, 'taxable' => 43191, 'cgst' => 1079.78, 'sgst' => 1079.78, 'round_off' => 0.44, 'total' => 45351, 'attachment' => 'uttam/05-2026-2027.pdf', 'jobworks' => [['UTTAM MAL', 67], ['UTTAM MAL', 73]]]),
            $local(['karigar' => 'UTTAM MAL', 'bill_no' => '6/2026-2027', 'bill_date' => '2026-05-29', 'net_weight' => 30.798, 'taxable' => 10779.30, 'cgst' => 269.48, 'sgst' => 269.48, 'round_off' => -0.26, 'total' => 11318, 'attachment' => 'uttam/06-2026-2027.pdf', 'jobworks' => [['UTTAM MAL', 94], ['UTTAM MAL', 98]]]),
            $local(['karigar' => 'UTTAM MAL', 'bill_no' => '7/2026-2027', 'bill_date' => '2026-06-10', 'net_weight' => 26.428, 'taxable' => 9249.80, 'cgst' => 231.25, 'sgst' => 231.25, 'round_off' => -0.30, 'total' => 9712, 'attachment' => 'uttam/07-2026-2027.pdf', 'jobworks' => [['UTTAM MAL', 109]]]),
            $local(['karigar' => 'UTTAM MAL', 'bill_no' => '8/2026-2027', 'bill_date' => '2026-06-24', 'net_weight' => 381.744, 'taxable' => 133610, 'cgst' => 3340.25, 'sgst' => 3340.25, 'round_off' => 0.50, 'total' => 140291, 'attachment' => 'uttam/08-2026-2027.pdf', 'jobworks' => [['UTTAM MAL', 118], ['UTTAM MAL', 123], ['UTTAM MAL', 128], ['UTTAM MAL', 133], ['UTTAM MAL', 138]]]),
            $local(['karigar' => 'UTTAM MAL', 'bill_no' => '9/2026-2027', 'bill_date' => '2026-07-03', 'net_weight' => 22.754, 'taxable' => 7964, 'cgst' => 199.10, 'sgst' => 199.10, 'round_off' => -0.20, 'total' => 8362, 'attachment' => 'uttam/09-2026-2027.pdf', 'jobworks' => [['UTTAM MAL', 148], ['UTTAM MAL', 152], ['UTTAM MAL', 156], ['UTTAM MAL', 159]]]),
            $local(['karigar' => 'UTTAM MAL', 'bill_no' => '10/2026-2027', 'bill_date' => '2026-07-13', 'net_weight' => 3.340, 'taxable' => 1109.21, 'cgst' => 27.73, 'sgst' => 27.73, 'round_off' => 0.33, 'total' => 1165, 'attachment' => 'uttam/10-2026-2027.pdf', 'jobworks' => [['UTTAM MAL', 168]]]),
            $local(['karigar' => 'UTTAM MAL', 'bill_no' => '11/2026-2027', 'bill_date' => '2026-08-22', 'net_weight' => 594.120, 'taxable' => 203169, 'cgst' => 5079.23, 'sgst' => 5079.23, 'round_off' => -0.46, 'total' => 213327, 'attachment' => 'uttam/11-2026-2027.pdf']),
        ];
    }
}
