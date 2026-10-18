<?php

namespace App\Services;

class ArattaiUserMappingService
{
    public function userId(string $entityType, int $entityId): string
    {
        if ($entityId <= 0) {
            return '';
        }
        $db = db_connect();
        if (! $db->tableExists('arattai_user_mappings')) {
            return '';
        }
        $row = $db->table('arattai_user_mappings')->select('arattai_user_id')
            ->where('entity_type', strtolower(trim($entityType)))->where('entity_id', $entityId)
            ->where('is_active', 1)->get()->getRowArray();
        return $this->normalize((string) ($row['arattai_user_id'] ?? ''));
    }

    public function adminUserId(int $adminUserId): string
    {
        $mapped = $this->userId('admin', $adminUserId);
        if ($mapped === '') {
            $mapped = $this->userId('follower', $adminUserId);
        }
        return $mapped !== '' ? $mapped : $this->legacyEmployeeUserId($adminUserId);
    }

    public function followerUserId(int $adminUserId): string
    {
        $mapped = $this->userId('follower', $adminUserId);
        if ($mapped === '') {
            $mapped = $this->userId('admin', $adminUserId);
        }
        return $mapped !== '' ? $mapped : $this->legacyEmployeeUserId($adminUserId);
    }

    public function karigarUserId(int $karigarId): string
    {
        $mapped = $this->userId('karigar', $karigarId);
        if ($mapped !== '') {
            return $mapped;
        }
        $row = db_connect()->table('karigars')->select('attrai_number')->where('id', $karigarId)->get()->getRowArray();
        return $this->normalize((string) ($row['attrai_number'] ?? ''));
    }

    public function customerUserId(int $customerId): string
    {
        return $this->userId('customer', $customerId);
    }

    /** @param array<string,mixed> $settings @return list<string> */
    public function approvalAdminUserIds(array $settings = []): array
    {
        $db = db_connect();
        $ids = [];
        if ($db->tableExists('arattai_user_mappings')) {
            $rows = $db->table('arattai_user_mappings')->select('arattai_user_id')
                ->where('entity_type', 'admin')->where('is_approval_admin', 1)->where('is_active', 1)
                ->orderBy('id', 'ASC')->get()->getResultArray();
            foreach ($rows as $row) {
                $id = $this->normalize((string) ($row['arattai_user_id'] ?? ''));
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }
        if ($ids !== []) {
            return array_values(array_unique($ids));
        }
        foreach (preg_split('/[\s,;]+/', trim((string) ($settings['attrai_approval_numbers'] ?? ''))) ?: [] as $part) {
            $id = $this->normalize((string) $part);
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function legacyEmployeeUserId(int $adminUserId): string
    {
        if ($adminUserId <= 0) {
            return '';
        }
        $row = db_connect()->table('employees')->select('attrai_number')
            ->where('admin_user_id', $adminUserId)->where('is_active', 1)->get()->getRowArray();
        return $this->normalize((string) ($row['attrai_number'] ?? ''));
    }

    private function normalize(string $userId): string
    {
        return preg_replace('/\D+/', '', trim($userId)) ?? '';
    }
}
