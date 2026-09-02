<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

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
        foreach ($rows as &$row) {
            $row['components'] = $this->parseComponentString((string) ($row['component_string'] ?? ''));
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

        $taxableAmount = max(0, round($taxableAmount, 2));
        $components = [];
        $amounts = ['CGST' => 0.0, 'SGST' => 0.0, 'IGST' => 0.0];
        $rates = ['CGST' => 0.0, 'SGST' => 0.0, 'IGST' => 0.0];
        foreach ((array) ($master['components'] ?? []) as $component) {
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
            'gst_master_name' => (string) $master['name'],
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
