<?php

namespace App\Services;

use App\Models\DeliveryChallanModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

class DeliveryChallanService
{
    private const TYPES = ['ornament', 'loose_diamond', 'loose_gold'];
    private const GST_RATES = [0.0, 3.0, 5.0, 12.0, 18.0];

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /** @return array<string,mixed> */
    public function formData(): array
    {
        $setting = $this->setting();
        return [
            'next_challan_no' => $this->previewNumber($setting),
            'customers' => $this->customers(),
            'branches' => [
                ['key' => 'Mumbai', 'name' => 'Mumbai', 'address' => trim((string) ($setting['mumbai_branch_address'] ?? ''))],
                ['key' => 'Hyderabad', 'name' => 'Hyderabad', 'address' => trim((string) ($setting['hyderabad_branch_address'] ?? ''))],
            ],
            'gst_rates' => self::GST_RATES,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $rows = $this->db->table('delivery_challans dc')
            ->select('dc.*, COALESCE(dc.customer_name, c.name, order_customer.name) AS display_customer, o.order_no', false)
            ->join('customers c', 'c.id = dc.customer_id', 'left')
            ->join('orders o', 'o.id = dc.order_id', 'left')
            ->join('customers order_customer', 'order_customer.id = o.customer_id', 'left')
            ->orderBy('dc.id', 'DESC')->get()->getResultArray();
        foreach ($rows as &$row) {
            $summary = $this->decodeSummary((string) ($row['summary_json'] ?? ''));
            $row['items'] = $summary['items'] ?? [];
            $row['material_types'] = $this->materialLabels($row['items']);
            $row['is_standalone'] = ! empty($summary['standalone']);
        }
        unset($row);
        return $rows;
    }

    /** @return array<string,mixed> */
    public function create(array $payload, int $createdBy): array
    {
        $payload = $this->validatedPayload($payload);
        $date = (string) $payload['challan_date'];
        $customerId = (int) $payload['customer_id'];
        $customer = (array) $payload['_customer'];
        $setting = (array) $payload['_setting'];
        $dispatchFrom = (string) $payload['dispatch_from'];
        $fromAddress = (string) $payload['_dispatch_from_address'];
        $items = (array) $payload['items'];
        $taxPercent = (float) $payload['tax_percent'];
        $taxable = round(array_sum(array_column($items, 'value')), 2);
        $tax = round($taxable * $taxPercent / 100, 2);
        $address = (string) $payload['_customer_address'];
        $totals = $this->totals($items);

        try {
            $this->db->transException(true)->transStart();
            $settingRow = $this->db->query('SELECT * FROM company_settings ORDER BY id ASC LIMIT 1 FOR UPDATE')->getRowArray();
            if (! is_array($settingRow)) {
                $this->db->table('company_settings')->insert([
                    'delivery_challan_suffix' => 'DC',
                    'delivery_challan_last_number' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $settingRow = $this->db->query('SELECT * FROM company_settings ORDER BY id ASC LIMIT 1 FOR UPDATE')->getRowArray() ?? [];
            }
            $serial = $this->nextSerial($settingRow);
            $challanNo = $this->formatNumber($settingRow, $serial);
            $model = new DeliveryChallanModel($this->db);
            $id = (int) $model->insert([
                'challan_no' => $challanNo,
                'challan_date' => $date,
                'order_id' => null,
                'customer_id' => $customerId,
                'dispatch_from' => $dispatchFrom,
                'dispatch_from_address' => $fromAddress,
                'customer_name' => (string) ($customer['name'] ?? ''),
                'customer_address' => $address,
                'customer_gstin' => trim((string) ($customer['gstin'] ?? '')) ?: null,
                'total_pcs' => (int) $totals['pcs'],
                'gross_weight_gm' => $totals['gross'],
                'net_gold_weight_gm' => $totals['net'],
                'diamond_weight_cts' => $totals['diamond'],
                'color_stone_weight_cts' => $totals['stone'],
                'other_weight_gm' => $totals['other'],
                'taxable_value' => $taxable,
                'tax_percent' => $taxPercent,
                'tax_amount' => $tax,
                'total_amount' => round($taxable + $tax, 2),
                'summary_json' => json_encode(['standalone' => true, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                'created_by' => $createdBy > 0 ? $createdBy : null,
            ], true);
            if ($id <= 0) {
                throw new RuntimeException('Could not create delivery challan.');
            }
            $this->db->table('company_settings')->where('id', (int) ($settingRow['id'] ?? 0))->update([
                'delivery_challan_last_number' => $serial,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        return $this->find($id);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function validatedPayload(array $payload): array
    {
        $date = trim((string) ($payload['challan_date'] ?? ''));
        if ($date === '' || strtotime($date) === false) {
            throw new RuntimeException('Select a valid challan date.');
        }
        $date = date('Y-m-d', strtotime($date));
        $customerId = (int) ($payload['customer_id'] ?? 0);
        $customer = $this->db->table('customers')->where('id', $customerId)
            ->where('is_active', 1)->where('deleted_at', null)->get()->getRowArray();
        if (! is_array($customer)) {
            throw new RuntimeException('Select a valid customer.');
        }
        $setting = $this->setting();
        $dispatchFrom = ucfirst(strtolower(trim((string) ($payload['dispatch_from'] ?? ''))));
        if (! in_array($dispatchFrom, ['Mumbai', 'Hyderabad'], true)) {
            throw new RuntimeException('Select Mumbai or Hyderabad as dispatch location.');
        }
        $addressKey = strtolower($dispatchFrom) . '_branch_address';
        $fromAddress = trim((string) ($setting[$addressKey] ?? ''));
        if ($fromAddress === '') {
            throw new RuntimeException($dispatchFrom . ' branch address is not configured in Company Settings.');
        }
        $items = $this->normalizeItems($payload['items'] ?? []);
        $taxPercent = round((float) ($payload['tax_percent'] ?? 0), 2);
        if (! in_array($taxPercent, self::GST_RATES, true)) {
            throw new RuntimeException('Select a valid GST rate.');
        }
        $address = $this->customerAddress($customerId);
        return [
            'challan_date' => $date,
            'customer_id' => $customerId,
            'customer_name' => (string) ($customer['name'] ?? ''),
            'dispatch_from' => $dispatchFrom,
            'tax_percent' => $taxPercent,
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'items' => $items,
            '_customer' => $customer,
            '_setting' => $setting,
            '_dispatch_from_address' => $fromAddress,
            '_customer_address' => $address,
        ];
    }

    /** @return array<string,mixed> */
    public function find(int $id): array
    {
        $row = $this->db->table('delivery_challans dc')
            ->select('dc.*, COALESCE(dc.customer_name, c.name, order_customer.name) AS display_customer, o.order_no', false)
            ->join('customers c', 'c.id = dc.customer_id', 'left')
            ->join('orders o', 'o.id = dc.order_id', 'left')
            ->join('customers order_customer', 'order_customer.id = o.customer_id', 'left')
            ->where('dc.id', $id)->get()->getRowArray();
        if (! is_array($row)) {
            throw new RuntimeException('Delivery challan not found.');
        }
        $summary = $this->decodeSummary((string) ($row['summary_json'] ?? ''));
        $row['items'] = $summary['items'] ?? $this->legacyItems($row);
        $row['material_types'] = $this->materialLabels($row['items']);
        return $row;
    }

    /** @return array<string,mixed> */
    public function setting(): array
    {
        return $this->db->table('company_settings')->orderBy('id', 'ASC')->get()->getRowArray() ?? [];
    }

    /** @return list<array<string,mixed>> */
    private function customers(): array
    {
        $customers = $this->db->table('customers')->where('is_active', 1)
            ->where('deleted_at', null)->orderBy('name', 'ASC')->get()->getResultArray();
        foreach ($customers as &$customer) {
            $customer['address'] = $this->customerAddress((int) ($customer['id'] ?? 0));
        }
        unset($customer);
        return $customers;
    }

    private function customerAddress(int $customerId): string
    {
        $row = $this->db->table('customer_addresses')->where('customer_id', $customerId)
            ->orderBy('is_default', 'DESC')->orderBy('id', 'ASC')->get()->getRowArray();
        if (! is_array($row)) {
            return '';
        }
        return implode(', ', array_filter(array_map('trim', [
            (string) ($row['line1'] ?? ''), (string) ($row['line2'] ?? ''),
            (string) ($row['city'] ?? ''), (string) ($row['state'] ?? ''),
            (string) ($row['pincode'] ?? ''), (string) ($row['country'] ?? ''),
        ])));
    }

    /** @param mixed $raw @return list<array<string,mixed>> */
    private function normalizeItems(mixed $raw): array
    {
        if (! is_array($raw)) {
            throw new RuntimeException('Add at least one challan item.');
        }
        $items = [];
        foreach (array_values($raw) as $index => $input) {
            if (! is_array($input)) {
                continue;
            }
            $type = strtolower(trim((string) ($input['item_type'] ?? '')));
            if (! in_array($type, self::TYPES, true)) {
                throw new RuntimeException('Line ' . ($index + 1) . ': select a valid item type.');
            }
            $pcs = (float) ($input['pcs'] ?? 0);
            $value = round((float) ($input['value'] ?? 0), 2);
            if ($pcs <= 0 || floor($pcs) !== $pcs || $value <= 0) {
                throw new RuntimeException('Line ' . ($index + 1) . ': whole PCS and positive value are required.');
            }
            $item = [
                'item_type' => $type,
                'description' => trim((string) ($input['description'] ?? '')) ?: $this->typeLabel($type),
                'purity' => strtoupper(trim((string) ($input['purity'] ?? ''))),
                'pcs' => (int) $pcs,
                'gross_weight_gm' => round(max(0, (float) ($input['gross_weight_gm'] ?? 0)), 3),
                'net_weight_gm' => round(max(0, (float) ($input['net_weight_gm'] ?? 0)), 3),
                'diamond_weight_cts' => round(max(0, (float) ($input['diamond_weight_cts'] ?? 0)), 3),
                'stone_weight_cts' => round(max(0, (float) ($input['stone_weight_cts'] ?? 0)), 3),
                'other_weight_gm' => round(max(0, (float) ($input['other_weight_gm'] ?? 0)), 3),
                'value' => $value,
            ];
            if ($type === 'ornament') {
                if (! in_array($item['purity'], ['14 KT', '18 KT', '22 KT'], true)) {
                    throw new RuntimeException('Line ' . ($index + 1) . ': select 14 KT, 18 KT or 22 KT.');
                }
                if ($item['gross_weight_gm'] <= 0 || $item['net_weight_gm'] <= 0 || $item['net_weight_gm'] > $item['gross_weight_gm']) {
                    throw new RuntimeException('Line ' . ($index + 1) . ': enter valid gross and net ornament weights.');
                }
            } elseif ($type === 'loose_diamond') {
                if ($item['diamond_weight_cts'] <= 0) {
                    throw new RuntimeException('Line ' . ($index + 1) . ': diamond carat weight is required.');
                }
                $item['purity'] = '';
            } else {
                if ($item['net_weight_gm'] <= 0) {
                    throw new RuntimeException('Line ' . ($index + 1) . ': gold weight is required.');
                }
            }
            $items[] = $item;
        }
        if ($items === []) {
            throw new RuntimeException('Add at least one challan item.');
        }
        return $items;
    }

    /** @param list<array<string,mixed>> $items @return array<string,float|int> */
    private function totals(array $items): array
    {
        $totals = ['pcs' => 0, 'gross' => 0.0, 'net' => 0.0, 'diamond' => 0.0, 'stone' => 0.0, 'other' => 0.0];
        foreach ($items as $item) {
            $totals['pcs'] += (int) $item['pcs'];
            $totals['gross'] += (float) $item['gross_weight_gm'];
            $totals['net'] += (float) $item['net_weight_gm'];
            $totals['diamond'] += (float) $item['diamond_weight_cts'];
            $totals['stone'] += (float) $item['stone_weight_cts'];
            $totals['other'] += (float) $item['other_weight_gm'];
        }
        foreach (['gross', 'net', 'diamond', 'stone', 'other'] as $key) {
            $totals[$key] = round((float) $totals[$key], 3);
        }
        return $totals;
    }

    private function previewNumber(array $setting): string
    {
        return $this->formatNumber($setting, $this->nextSerial($setting));
    }

    private function nextSerial(array $setting): int
    {
        $prefix = $this->prefix($setting);
        $max = max(0, (int) ($setting['delivery_challan_last_number'] ?? 0));
        $rows = $this->db->table('delivery_challans')->select('challan_no')
            ->like('challan_no', $prefix, 'after')->get()->getResultArray();
        foreach ($rows as $row) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '[-\/]?(\d+)$/', (string) ($row['challan_no'] ?? ''), $match) === 1) {
                $max = max($max, (int) $match[1]);
            }
        }
        return $max + 1;
    }

    private function formatNumber(array $setting, int $serial): string
    {
        return $this->prefix($setting) . str_pad((string) $serial, 4, '0', STR_PAD_LEFT);
    }

    private function prefix(array $setting): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($setting['delivery_challan_suffix'] ?? 'DC'))) ?: 'DC';
    }

    /** @return array<string,mixed> */
    private function decodeSummary(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param list<array<string,mixed>> $items */
    private function materialLabels(array $items): string
    {
        $labels = [];
        foreach ($items as $item) {
            $labels[$this->typeLabel((string) ($item['item_type'] ?? 'ornament'))] = true;
        }
        return implode(' + ', array_keys($labels));
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'loose_diamond' => 'Loose Diamond',
            'loose_gold' => 'Loose Gold / Metal',
            default => 'Ornament',
        };
    }

    /** @return list<array<string,mixed>> */
    private function legacyItems(array $row): array
    {
        return [[
            'item_type' => 'ornament', 'description' => 'Studded Jewellery', 'purity' => '18 KT',
            'pcs' => max(1, (int) ($row['total_pcs'] ?? 1)),
            'gross_weight_gm' => (float) ($row['gross_weight_gm'] ?? 0),
            'net_weight_gm' => (float) ($row['net_gold_weight_gm'] ?? 0),
            'diamond_weight_cts' => (float) ($row['diamond_weight_cts'] ?? 0),
            'stone_weight_cts' => (float) ($row['color_stone_weight_cts'] ?? 0),
            'other_weight_gm' => (float) ($row['other_weight_gm'] ?? 0),
            'value' => (float) ($row['taxable_value'] ?? 0),
        ]];
    }
}
