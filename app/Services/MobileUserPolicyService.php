<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class MobileUserPolicyService
{
    private const APPROVAL_FIELDS = [
        'followup' => 'followup_requires_approval',
        'issuement' => 'issuement_requires_approval',
        'delivery_challan' => 'delivery_challan_requires_approval',
    ];

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function requiresApproval(int $userId, string $workflow): bool
    {
        $field = self::APPROVAL_FIELDS[$workflow] ?? null;
        if ($userId <= 0 || $field === null || ! $this->db->fieldExists($field, 'admin_users')) {
            return true;
        }
        $row = $this->db->table('admin_users')->select($field)->where('id', $userId)->get()->getRowArray();
        return ! is_array($row) || (int) ($row[$field] ?? 1) === 1;
    }

    public function followupGalleryEnabled(int $userId): bool
    {
        $field = 'followup_gallery_enabled';
        if ($userId <= 0 || ! $this->db->fieldExists($field, 'admin_users')) {
            return false;
        }
        $row = $this->db->table('admin_users')->select($field)->where('id', $userId)->get()->getRowArray();
        return is_array($row) && (int) ($row[$field] ?? 0) === 1;
    }

    /** @return list<array<string,mixed>> */
    public function staffSettings(): array
    {
        $staff = (new StaffPerformanceService())->staffOptions();
        if ($staff === []) {
            return [];
        }
        $ids = array_values(array_map(static fn(array $row): int => (int) $row['id'], $staff));
        $rows = $this->db->table('admin_users')->whereIn('id', $ids)->get()->getResultArray();
        $settings = [];
        foreach ($rows as $row) {
            $settings[(int) $row['id']] = $row;
        }
        foreach ($staff as &$person) {
            $row = $settings[(int) $person['id']] ?? [];
            foreach (self::APPROVAL_FIELDS as $field) {
                $person[$field] = (int) ($row[$field] ?? 1);
            }
            $person['followup_gallery_enabled'] = (int) ($row['followup_gallery_enabled'] ?? 0);
        }
        unset($person);
        return $staff;
    }

    /** @param array<string,mixed> $payload */
    public function update(int $userId, array $payload): array
    {
        if (! (new StaffPerformanceService())->isStaffUser($userId)) {
            throw new RuntimeException('Select an active non-admin employee.');
        }
        $data = [];
        foreach ([...array_values(self::APPROVAL_FIELDS), 'followup_gallery_enabled'] as $field) {
            $data[$field] = filter_var($payload[$field] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->table('admin_users')->where('id', $userId)->update($data);
        foreach ($this->staffSettings() as $row) {
            if ((int) ($row['id'] ?? 0) === $userId) {
                return $row;
            }
        }
        throw new RuntimeException('Employee settings could not be loaded.');
    }
}
