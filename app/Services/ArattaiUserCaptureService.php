<?php

namespace App\Services;

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
        $userId = preg_replace('/\D+/', '', trim((string) ($user['id'] ?? ''))) ?? '';
        if ($userId === '') {
            return null;
        }

        $row = [
            'id' => $userId,
            'name' => trim((string) ($user['name'] ?? '')),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'message_type' => trim((string) ($entity['type'] ?? '')),
        ];
        $this->store($row);
        return $row;
    }

    /** @return list<array<string,string>> */
    public function recent(): array
    {
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
