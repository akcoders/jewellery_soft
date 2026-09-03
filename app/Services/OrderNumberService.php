<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class OrderNumberService
{
    private const SUFFIX_LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const SUFFIX_DIGITS = '23456789';

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function generate(
        int $customerId,
        string $orderCategory,
        int $salesPersonUserId = 0,
        string $customerFallback = ''
    ): string {
        $customer = $customerId > 0
            ? $this->db->table('customers')->select('customer_code, name')->where('id', $customerId)->get()->getRowArray()
            : null;

        $customerSource = trim((string) ($customer['customer_code'] ?? ''));
        if ($customerSource === '') {
            $customerSource = trim((string) ($customer['name'] ?? $customerFallback));
        }
        $customerCode = $this->segmentCode($customerSource, 'CUS', 12);

        $salesPerson = $salesPersonUserId > 0
            ? $this->db->table('customer_users')
                ->select('name')
                ->where('id', $salesPersonUserId)
                ->where('role', 'sales_person')
                ->get()
                ->getRowArray()
            : null;
        $salesCode = $this->nameCode((string) ($salesPerson['name'] ?? ''), 'NS');
        $categoryCode = $this->categoryCode($orderCategory);

        for ($attempt = 0; $attempt < 128; $attempt++) {
            $orderNo = implode('-', [$customerCode, $categoryCode, $salesCode, $this->randomSuffix()]);
            if ($this->db->table('orders')->where('order_no', $orderNo)->countAllResults() === 0) {
                return $orderNo;
            }
        }

        throw new RuntimeException('Could not generate a unique order number.');
    }

    public function categoryCode(string $category): string
    {
        $normalized = strtoupper(trim($category));
        $mapped = [
            'RING' => 'RNG',
            'EARRING' => 'EAR',
            'JHUMKI' => 'JMK',
            'BANGLE' => 'BGL',
            'BRACELET' => 'BRC',
            'NECKLACE' => 'NCK',
            'PENDANT' => 'PND',
            'HAARAM' => 'HRM',
            'MANGALSUTRA' => 'MGS',
            'CHAIN' => 'CHN',
            'NOSE PIN' => 'NSP',
            'ANKLET' => 'ANK',
            'KADA' => 'KDA',
            'BROOCH' => 'BCH',
            'OTHER' => 'OTH',
            'MANUFACTURING' => 'MFG',
            'SALES' => 'SAL',
            'REPAIR' => 'REP',
            'SERVICE' => 'SER',
        ][$normalized] ?? '';

        return $mapped !== '' ? $mapped : $this->segmentCode($normalized, 'ORD', 4);
    }

    public function nameCode(string $name, string $fallback = 'NS'): string
    {
        $words = preg_split('/[^A-Z0-9]+/', strtoupper(trim($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) > 1) {
            $initials = '';
            foreach ($words as $word) {
                $initials .= substr($word, 0, 1);
                if (strlen($initials) === 4) {
                    break;
                }
            }

            return $initials !== '' ? $initials : $fallback;
        }

        return $this->segmentCode((string) ($words[0] ?? ''), $fallback, 4);
    }

    private function segmentCode(string $value, string $fallback, int $maxLength): string
    {
        $code = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($value))) ?: '';
        if ($code === '') {
            $code = $fallback;
        }

        return substr($code, 0, $maxLength);
    }

    private function randomSuffix(): string
    {
        $characters = [
            self::SUFFIX_LETTERS[random_int(0, strlen(self::SUFFIX_LETTERS) - 1)],
            self::SUFFIX_LETTERS[random_int(0, strlen(self::SUFFIX_LETTERS) - 1)],
            self::SUFFIX_DIGITS[random_int(0, strlen(self::SUFFIX_DIGITS) - 1)],
            self::SUFFIX_DIGITS[random_int(0, strlen(self::SUFFIX_DIGITS) - 1)],
        ];

        for ($index = count($characters) - 1; $index > 0; $index--) {
            $swapWith = random_int(0, $index);
            [$characters[$index], $characters[$swapWith]] = [$characters[$swapWith], $characters[$index]];
        }

        return implode('', $characters);
    }
}
