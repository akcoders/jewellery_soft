<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

class TaxMasterService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /** @return list<array<string,mixed>> */
    public function options(bool $activeOnly = true): array
    {
        if (! $this->db->tableExists('gst_masters')) {
            return [];
        }
        $builder = $this->db->table('gst_masters gm')
            ->select("gm.*, GROUP_CONCAT(CONCAT(tt.name, ':', gmc.percentage) ORDER BY gmc.id SEPARATOR '|') AS component_string", false)
            ->join('gst_master_components gmc', 'gmc.gst_master_id = gm.id', 'left')
            ->join('tax_types tt', 'tt.id = gmc.tax_type_id', 'left')
            ->groupBy('gm.id')
            ->orderBy('gm.total_percentage', 'ASC')
            ->orderBy('gm.name', 'ASC');
        if ($activeOnly) {
            $builder->where('gm.is_active', 1);
        }
        $rows = $builder->get()->getResultArray();

        $componentsByMaster = [];
        $masterIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        if ($masterIds !== []) {
            $componentRows = $this->db->table('gst_master_components gmc')
                ->select('gmc.gst_master_id, gmc.tax_type_id, gmc.percentage, tt.name')
                ->join('tax_types tt', 'tt.id = gmc.tax_type_id', 'left')
                ->whereIn('gmc.gst_master_id', $masterIds)
                ->orderBy('gmc.id', 'ASC')
                ->get()
                ->getResultArray();
            foreach ($componentRows as $component) {
                $componentsByMaster[(int) $component['gst_master_id']][] = [
                    'tax_type_id' => (int) $component['tax_type_id'],
                    'name' => trim((string) ($component['name'] ?? '')),
                    'percentage' => (float) $component['percentage'],
                ];
            }
        }

        foreach ($rows as &$row) {
            $row['components'] = $componentsByMaster[(int) $row['id']]
                ?? $this->parseComponentString((string) ($row['component_string'] ?? ''));
        }
        unset($row);
        return $rows;
    }

    /** @return array<string,mixed> */
    public function calculate(int $masterId, float $taxableAmount, float $roundOff = 0.0): array
    {
        $master = null;
        foreach ($this->options(false) as $option) {
            if ((int) $option['id'] === $masterId && (int) ($option['is_active'] ?? 0) === 1) {
                $master = $option;
                break;
            }
        }
        if (! $master) {
            throw new RuntimeException('Please select a valid active GST master.');
        }

        return $this->calculateFromComponents(
            $masterId,
            (string) $master['name'],
            (array) ($master['components'] ?? []),
            $taxableAmount,
            $roundOff
        );
    }

    /**
     * Update a GST master and refresh the stored GST snapshots of every linked
     * purchase. Stock quantities and payment data are intentionally untouched.
     *
     * @param array<int,float> $componentPercentages Tax type ID => percentage
     * @return array{total:int,tables:array<string,int>}
     */
    public function updateMasterAndLinkedPurchases(int $masterId, string $name, array $componentPercentages): array
    {
        $master = $this->db->table('gst_masters')->where('id', $masterId)->get()->getRowArray();
        if (! $master) {
            throw new RuntimeException('GST master not found.');
        }

        $componentPercentages = array_filter(
            $componentPercentages,
            static fn (float $percentage, int $taxTypeId): bool => $taxTypeId > 0 && $percentage > 0,
            ARRAY_FILTER_USE_BOTH
        );
        $taxTypes = [];
        if ($componentPercentages !== []) {
            $rows = $this->db->table('tax_types')
                ->select('id, name')
                ->whereIn('id', array_keys($componentPercentages))
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $taxTypes[(int) $row['id']] = trim((string) $row['name']);
            }
            if (count($taxTypes) !== count($componentPercentages)) {
                throw new RuntimeException('One or more tax types are invalid.');
            }
        }

        $components = [];
        foreach ($componentPercentages as $taxTypeId => $percentage) {
            $components[] = [
                'tax_type_id' => (int) $taxTypeId,
                'name' => $taxTypes[(int) $taxTypeId],
                'percentage' => round((float) $percentage, 3),
            ];
        }

        $now = date('Y-m-d H:i:s');
        $counts = [];
        $this->db->transException(true)->transBegin();
        try {

        $masterUpdate = [
            'name' => trim($name),
            'total_percentage' => round(array_sum($componentPercentages), 3),
        ];
        if ($this->db->fieldExists('updated_at', 'gst_masters')) {
            $masterUpdate['updated_at'] = $now;
        }
        $this->db->table('gst_masters')->where('id', $masterId)->update($masterUpdate);

        $this->db->table('gst_master_components')->where('gst_master_id', $masterId)->delete();
        foreach ($components as $component) {
            $this->db->table('gst_master_components')->insert([
                'gst_master_id' => $masterId,
                'tax_type_id' => $component['tax_type_id'],
                'percentage' => $component['percentage'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            'purchase_headers' => 'invoice_total',
            'gold_inventory_purchase_headers' => 'invoice_total',
            'stone_inventory_purchase_headers' => 'invoice_total',
            'purchases' => 'invoice_amount',
        ] as $table => $totalField) {
            $counts[$table] = $this->refreshLinkedPurchaseTaxes(
                $table,
                $totalField,
                $masterId,
                trim($name),
                $components,
                $now
            );
        }

        if (! $this->db->transStatus()) {
            throw new RuntimeException('Unable to update the GST master and linked purchases.');
        }
        $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return [
            'total' => array_sum($counts),
            'tables' => $counts,
        ];
    }

    /**
     * @param list<array<string,mixed>> $components
     */
    private function refreshLinkedPurchaseTaxes(
        string $table,
        string $totalField,
        int $masterId,
        string $masterName,
        array $components,
        string $now
    ): int {
        if (! $this->db->tableExists($table)
            || ! $this->db->fieldExists('gst_master_id', $table)
            || ! $this->db->fieldExists('taxable_amount', $table)) {
            return 0;
        }

        $select = ['id', 'taxable_amount'];
        if ($this->db->fieldExists('round_off_amount', $table)) {
            $select[] = 'round_off_amount';
        }
        $purchases = $this->db->table($table)
            ->select(implode(', ', $select))
            ->where('gst_master_id', $masterId)
            ->get()
            ->getResultArray();

        $updated = 0;
        foreach ($purchases as $purchase) {
            $tax = $this->calculateFromComponents(
                $masterId,
                $masterName,
                $components,
                (float) ($purchase['taxable_amount'] ?? 0),
                (float) ($purchase['round_off_amount'] ?? 0)
            );
            $values = [
                'tax_breakup_json' => $tax['tax_breakup_json'],
                'taxable_amount' => $tax['taxable_amount'],
                'cgst_rate' => $tax['cgst_rate'],
                'cgst_amount' => $tax['cgst_amount'],
                'sgst_rate' => $tax['sgst_rate'],
                'sgst_amount' => $tax['sgst_amount'],
                'igst_rate' => $tax['igst_rate'],
                'igst_amount' => $tax['igst_amount'],
                'gst_amount' => $tax['gst_amount'],
                'round_off_amount' => $tax['round_off_amount'],
                'tax_percentage' => round(array_sum(array_column($tax['components'], 'percentage')), 3),
                $totalField => $tax['invoice_total'],
                'updated_at' => $now,
            ];
            $values = array_filter(
                $values,
                fn (mixed $_value, string $field): bool => $this->db->fieldExists($field, $table),
                ARRAY_FILTER_USE_BOTH
            );
            $this->db->table($table)->where('id', (int) $purchase['id'])->update($values);
            $updated++;
        }

        return $updated;
    }

    /**
     * @param list<array<string,mixed>> $masterComponents
     * @return array<string,mixed>
     */
    private function calculateFromComponents(
        int $masterId,
        string $masterName,
        array $masterComponents,
        float $taxableAmount,
        float $roundOff
    ): array {

        $taxableAmount = max(0, round($taxableAmount, 2));
        $components = [];
        $amounts = ['CGST' => 0.0, 'SGST' => 0.0, 'IGST' => 0.0];
        $rates = ['CGST' => 0.0, 'SGST' => 0.0, 'IGST' => 0.0];
        foreach ($masterComponents as $component) {
            $name = strtoupper(trim((string) ($component['name'] ?? '')));
            $percentage = round((float) ($component['percentage'] ?? 0), 3);
            $amount = round($taxableAmount * $percentage / 100, 2);
            $components[] = ['name' => $name, 'percentage' => $percentage, 'amount' => $amount];
            if (array_key_exists($name, $amounts)) {
                $rates[$name] += $percentage;
                $amounts[$name] += $amount;
            }
        }
        $taxAmount = round(array_sum(array_column($components, 'amount')), 2);
        $roundOff = round($roundOff, 2);

        return [
            'gst_master_id' => $masterId,
            'gst_master_name' => $masterName,
            'taxable_amount' => $taxableAmount,
            'components' => $components,
            'tax_breakup_json' => json_encode($components, JSON_UNESCAPED_SLASHES),
            'cgst_rate' => round($rates['CGST'], 3),
            'cgst_amount' => round($amounts['CGST'], 2),
            'sgst_rate' => round($rates['SGST'], 3),
            'sgst_amount' => round($amounts['SGST'], 2),
            'igst_rate' => round($rates['IGST'], 3),
            'igst_amount' => round($amounts['IGST'], 2),
            'gst_amount' => $taxAmount,
            'round_off_amount' => $roundOff,
            'invoice_total' => max(0, round($taxableAmount + $taxAmount + $roundOff, 2)),
        ];
    }

    /** @return list<array{name:string,percentage:float}> */
    private function parseComponentString(string $value): array
    {
        $components = [];
        foreach (array_filter(explode('|', $value)) as $part) {
            [$name, $percentage] = array_pad(explode(':', $part, 2), 2, '0');
            $components[] = ['name' => trim($name), 'percentage' => (float) $percentage];
        }
        return $components;
    }
}
