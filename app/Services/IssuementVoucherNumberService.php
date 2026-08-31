<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class IssuementVoucherNumberService
{
    private const TABLES = [
        'gold_inventory_issue_headers',
        'issue_headers',
        'stone_inventory_issue_headers',
    ];

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function next(): string
    {
        $setting = $this->setting();
        $prefix = $this->prefix((string) ($setting['issuement_suffix'] ?? 'ISS'));
        $nextSerial = max(1, (int) ($setting['issuement_start_count'] ?? 1));
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';

        foreach (self::TABLES as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            $rows = $this->db->table($table)
                ->select('voucher_no')
                ->like('voucher_no', $prefix, 'after')
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                if (preg_match($pattern, (string) ($row['voucher_no'] ?? ''), $matches) === 1) {
                    $nextSerial = max($nextSerial, ((int) $matches[1]) + 1);
                }
            }
        }

        do {
            $voucherNo = $prefix . str_pad((string) $nextSerial, 3, '0', STR_PAD_LEFT);
            $nextSerial++;
        } while ($this->exists($voucherNo));

        return $voucherNo;
    }

    public function resolveForCreate(?string $requested): string
    {
        $requested = trim((string) $requested);
        $voucherNo = $this->normalize($requested);
        if ($voucherNo === '') {
            if ($requested !== '') {
                throw new RuntimeException('Enter a valid voucher number using letters, numbers, hyphen, slash, or underscore.');
            }
            return $this->next();
        }

        $this->assertAvailable($voucherNo);
        return $voucherNo;
    }

    public function resolveForUpdate(?string $requested, ?string $current): string
    {
        $requested = trim((string) $requested);
        $voucherNo = $this->normalize($requested);
        if ($voucherNo === '') {
            throw new RuntimeException('Voucher number is required.');
        }

        if ($voucherNo === $this->normalize((string) $current)) {
            return $voucherNo;
        }

        $this->assertAvailable($voucherNo);
        return $voucherNo;
    }

    public function normalize(string $voucherNo): string
    {
        $voucherNo = strtoupper(trim($voucherNo));
        $voucherNo = preg_replace('/\s+/', '-', $voucherNo) ?? '';
        $voucherNo = preg_replace('/[^A-Z0-9\/_-]/', '', $voucherNo) ?? '';

        if (strlen($voucherNo) > 80) {
            throw new RuntimeException('Voucher number cannot exceed 80 characters.');
        }

        return $voucherNo;
    }

    public function assertAvailable(string $voucherNo): void
    {
        if ($this->exists($voucherNo)) {
            throw new RuntimeException('Voucher number already exists: ' . $voucherNo);
        }
    }

    private function exists(string $voucherNo): bool
    {
        foreach (self::TABLES as $table) {
            if ($this->db->tableExists($table)
                && $this->db->table($table)->where('voucher_no', $voucherNo)->countAllResults() > 0) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string,mixed> */
    private function setting(): array
    {
        if (! $this->db->tableExists('company_settings')) {
            return [];
        }

        $row = $this->db->table('company_settings')->orderBy('id', 'ASC')->get(1)->getRowArray();
        return is_array($row) ? $row : [];
    }

    private function prefix(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?? '';
        return $prefix !== '' ? $prefix : 'ISS';
    }
}
