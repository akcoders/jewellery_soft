<?php

namespace App\Services;

use App\Models\ArattaiUserModel;
use RuntimeException;

class ArattaiUserCaptureService
{
    private string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? WRITEPATH . 'arattai-users.json';
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,string>|null
     */
    public function capture(array $payload): ?array
    {
        if ((string) ($payload['event'] ?? '') !== 'chat.message.received') {
            return null;
        }

        $entity = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
        $user = is_array($entity['user'] ?? null) ? $entity['user'] : [];
        if ($user === [] && is_array($payload['user'] ?? null)) {
            $user = $payload['user'];
        }
        $userId = preg_replace('/\D+/', '', trim((string) ($user['id'] ?? ''))) ?? '';
        if ($userId === '') {
            return null;
        }

        $row = [
            'id' => $userId,
            'name' => trim((string) ($user['name'] ?? '')),
            'phone' => trim((string) ($user['phone'] ?? $user['mobile'] ?? $user['phonenumber'] ?? '')),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'message_type' => trim((string) ($entity['type'] ?? '')),
        ];
        if (db_connect()->tableExists('arattai_users')) {
            $model = new ArattaiUserModel();
            $data = [
                'arattai_user_id' => $row['id'],
                'name' => $row['name'] !== '' ? $row['name'] : null,
                'phone' => $row['phone'] !== '' ? $row['phone'] : null,
                'last_message_type' => $row['message_type'] !== '' ? $row['message_type'] : null,
                'last_seen_at' => $row['last_seen_at'],
            ];
            if ($model->find($row['id'])) {
                $model->update($row['id'], $data);
            } else {
                $model->insert($data);
            }
        }
        $this->store($row);
        return $row;
    }

    /** @return list<array<string,string>> */
    public function recent(): array
    {
        if (db_connect()->tableExists('arattai_users')) {
            return array_map(static fn(array $row): array => [
                'id' => (string) ($row['arattai_user_id'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'message_type' => (string) ($row['last_message_type'] ?? ''),
                'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
            ], (new ArattaiUserModel())->orderBy('last_seen_at', 'DESC')->findAll(100));
        }
        if (! is_file($this->storagePath)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($this->storagePath), true);
        if (! is_array($decoded)) {
            return [];
        }
        $rows = array_values(array_filter($decoded, 'is_array'));
        usort($rows, static fn (array $a, array $b): int => strcmp(
            (string) ($b['last_seen_at'] ?? ''),
            (string) ($a['last_seen_at'] ?? '')
        ));
        return array_slice($rows, 0, 100);
    }

    /** @param array<string,string> $row */
    private function store(array $row): void
    {
        $handle = fopen($this->storagePath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Arattai user capture file could not be opened.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Arattai user capture file could not be locked.');
            }
            rewind($handle);
            $decoded = json_decode((string) stream_get_contents($handle), true);
            $users = is_array($decoded) ? $decoded : [];
            $users[$row['id']] = $row;
            uasort($users, static fn (array $a, array $b): int => strcmp(
                (string) ($b['last_seen_at'] ?? ''),
                (string) ($a['last_seen_at'] ?? '')
            ));
            $users = array_slice($users, 0, 100, true);
            $json = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            rewind($handle);
            if (! ftruncate($handle, 0) || fwrite($handle, $json) === false) {
                throw new RuntimeException('Arattai user capture file could not be written.');
            }
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }
}
