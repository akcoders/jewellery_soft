<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use InvalidArgumentException;
use Throwable;

/**
 * API keys for the MCP server (mcp-server/), issued per ERP user from
 * /admin/mcp-keys. Only the SHA-256 hash of a key is stored; the plain key is
 * shown once when it is generated. The MCP server checks keys through
 * POST /api/mcp/verify-key (shared secret), so it never needs this database.
 */
class McpApiKeyService
{
    public const TABLE = 'mcp_api_keys';

    public const SCOPES = ['read', 'write', 'sql'];

    /** Allowed expiry choices in days; 0 = never expires. */
    public const EXPIRY_DAYS = [30, 90, 180, 365, 0];

    /** key_prefix of rows created by the OAuth consent page (ChatGPT, claude.ai connectors). */
    public const OAUTH_PREFIX = 'oauth';

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function tableReady(): bool
    {
        try {
            return $this->db->tableExists(self::TABLE);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Creates the key table when it does not exist yet. Shared by the
     * migration and by the admin page so it self-heals on hosts where
     * Database Update has not been run.
     */
    public function ensureTable(): void
    {
        if ($this->tableReady()) {
            return;
        }

        $forge = Database::forge($this->db);
        $forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 40],
            'key_prefix' => ['type' => 'VARCHAR', 'constraint' => 16],
            'key_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'admin_user_id' => ['type' => 'INT', 'unsigned' => true],
            'scopes' => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'read'],
            'expires_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->addUniqueKey('key_hash');
        $forge->addKey('admin_user_id');
        $forge->createTable(self::TABLE, true);
    }

    /**
     * Normalises a scope list (array or comma separated) to known scopes.
     *
     * @param array<int,string>|string $scopes
     * @return list<string>
     */
    public static function normalizeScopes($scopes): array
    {
        $items = is_array($scopes) ? $scopes : preg_split('/[,\s]+/', (string) $scopes);
        $clean = [];
        foreach ((array) $items as $scope) {
            $scope = strtolower(trim((string) $scope));
            if (in_array($scope, self::SCOPES, true) && ! in_array($scope, $clean, true)) {
                $clean[] = $scope;
            }
        }

        return $clean;
    }

    /** Same format the MCP server's own CLI issues: "mcp_" + 64 hex chars. */
    public static function generatePlainKey(): string
    {
        return 'mcp_' . bin2hex(random_bytes(32));
    }

    public static function hashKey(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }

    /**
     * Keys with their ERP user, newest first.
     *
     * @return list<array<string,mixed>>
     */
    public function listKeys(): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        return $this->db->table(self::TABLE . ' k')
            ->select('k.*, u.name AS user_name, u.email AS user_email, u.is_active AS user_active, c.name AS created_by_name')
            ->join('admin_users u', 'u.id = k.admin_user_id', 'left')
            ->join('admin_users c', 'c.id = k.created_by', 'left')
            ->orderBy('k.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Validates the form and stores a new key.
     *
     * @param array<string,mixed> $input
     * @return array{plain_key:string,row:array<string,mixed>}
     */
    public function create(array $input, int $createdBy): array
    {
        $this->ensureTable();

        $adminUserId = (int) ($input['admin_user_id'] ?? 0);
        $user = $adminUserId > 0
            ? $this->db->table('admin_users')->where('id', $adminUserId)->where('is_active', 1)->get(1)->getRowArray()
            : null;
        if (! is_array($user)) {
            throw new InvalidArgumentException('Select an active ERP user for the key.');
        }

        $name = trim((string) preg_replace('/[^A-Za-z0-9 _.\-]/', '', (string) ($input['name'] ?? '')));
        if ($name === '' || mb_strlen($name) > 40) {
            throw new InvalidArgumentException('Key name is required (up to 40 letters, numbers, spaces, dot, dash or underscore).');
        }

        $duplicate = $this->db->table(self::TABLE)
            ->where('admin_user_id', $adminUserId)
            ->where('name', $name)
            ->where('revoked_at', null)
            ->countAllResults();
        if ($duplicate > 0) {
            throw new InvalidArgumentException('This user already has an active key named "' . $name . '". Use a different name or revoke the old key.');
        }

        $scopes = self::normalizeScopes($input['scopes'] ?? []);
        if ($scopes === []) {
            throw new InvalidArgumentException('Select at least one scope.');
        }

        $days = (int) ($input['expires_days'] ?? 365);
        if (! in_array($days, self::EXPIRY_DAYS, true)) {
            throw new InvalidArgumentException('Choose a valid expiry.');
        }

        $plainKey = self::generatePlainKey();
        $now = date('Y-m-d H:i:s');
        $row = [
            'name' => $name,
            'key_prefix' => substr($plainKey, 0, 12),
            'key_hash' => self::hashKey($plainKey),
            'admin_user_id' => $adminUserId,
            'scopes' => implode(',', $scopes),
            'expires_at' => $days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : null,
            'revoked_at' => null,
            'last_used_at' => null,
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'created_at' => $now,
        ];
        $this->db->table(self::TABLE)->insert($row);
        $row['id'] = (int) $this->db->insertID();

        return ['plain_key' => $plainKey, 'row' => $row];
    }

    /** Revokes a key; returns false when it does not exist or is already revoked. */
    public function revoke(int $id): bool
    {
        if (! $this->tableReady() || $id <= 0) {
            return false;
        }

        $this->db->table(self::TABLE)
            ->where('id', $id)
            ->where('revoked_at', null)
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);

        return $this->db->affectedRows() > 0;
    }

    /**
     * Looks up a key by its SHA-256 hash for the MCP server. Returns the key
     * details when it is usable, otherwise a reason string.
     *
     * @return array<string,mixed>|string
     */
    public function verifyHash(string $keyHash)
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $keyHash) || ! $this->tableReady()) {
            return 'Unknown API key.';
        }

        return $this->usableKey($this->db->table(self::TABLE . ' k')
            ->select('k.*, u.is_active AS user_active')
            ->join('admin_users u', 'u.id = k.admin_user_id', 'left')
            ->where('k.key_hash', $keyHash)
            ->get(1)
            ->getRowArray());
    }

    /**
     * Looks up an OAuth connection (grant) by id for the MCP server. Only
     * rows created by the consent page qualify, so admin keys cannot be
     * used by id.
     *
     * @return array<string,mixed>|string
     */
    public function verifyGrant(int $id)
    {
        if ($id <= 0 || ! $this->tableReady()) {
            return 'Unknown connection.';
        }

        return $this->usableKey($this->db->table(self::TABLE . ' k')
            ->select('k.*, u.is_active AS user_active')
            ->join('admin_users u', 'u.id = k.admin_user_id', 'left')
            ->where('k.id', $id)
            ->where('k.key_prefix', self::OAUTH_PREFIX)
            ->get(1)
            ->getRowArray());
    }

    /**
     * Records the user's consent for an OAuth client. The row's key hash is
     * random and never revealed, so it can only be used through OAuth tokens.
     *
     * @param list<string> $scopes
     */
    public function createOAuthGrant(int $adminUserId, string $clientName, array $scopes): int
    {
        $this->ensureTable();

        $scopes = self::normalizeScopes($scopes);
        if ($adminUserId <= 0 || $scopes === []) {
            throw new InvalidArgumentException('A user and at least one scope are required.');
        }

        $label = trim((string) preg_replace('/[^A-Za-z0-9 _.\-]/', '', $clientName)) ?: 'MCP client';
        $name = mb_substr('OAuth ' . $label, 0, 33) . ' ' . bin2hex(random_bytes(3));

        $this->db->table(self::TABLE)->insert([
            'name' => $name,
            'key_prefix' => self::OAUTH_PREFIX,
            'key_hash' => self::hashKey(bin2hex(random_bytes(32))),
            'admin_user_id' => $adminUserId,
            'scopes' => implode(',', $scopes),
            'expires_at' => null,
            'revoked_at' => null,
            'last_used_at' => null,
            'created_by' => $adminUserId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * HMAC-signed JSON for the browser hand-off with the MCP server. Matches
     * signPayload/verifySignedPayload in mcp-server/src/crypto.ts:
     * data = base64url(JSON), sig = base64url(HMAC-SHA256(data)).
     *
     * @param array<string,mixed> $payload
     * @return array{data:string,sig:string}
     */
    public static function signPayload(array $payload, string $secret): array
    {
        $data = self::base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return ['data' => $data, 'sig' => self::base64UrlEncode(hash_hmac('sha256', $data, $secret, true))];
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function verifySignedPayload(string $data, string $sig, string $secret): ?array
    {
        if ($secret === '' || $data === '' || $sig === '') {
            return null;
        }
        $expected = self::base64UrlEncode(hash_hmac('sha256', $data, $secret, true));
        if (! hash_equals($expected, $sig)) {
            return null;
        }
        $decoded = json_decode((string) base64_decode(strtr($data, '-_', '+/'), true), true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>|string
     */
    private function usableKey(?array $row)
    {
        if (! is_array($row)) {
            return 'Unknown API key.';
        }
        if (! empty($row['revoked_at'])) {
            return 'This API key has been revoked.';
        }
        if (! empty($row['expires_at']) && strtotime((string) $row['expires_at']) <= time()) {
            return 'This API key has expired.';
        }
        if ((int) ($row['user_active'] ?? 0) !== 1) {
            return 'The ERP user for this API key is inactive.';
        }

        // Cheap write throttle: the MCP server caches verifications for a minute anyway.
        $lastUsed = empty($row['last_used_at']) ? 0 : (int) strtotime((string) $row['last_used_at']);
        if ($lastUsed < time() - 300) {
            $this->db->table(self::TABLE)->where('id', (int) $row['id'])->update(['last_used_at' => date('Y-m-d H:i:s')]);
        }

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'admin_user_id' => (int) $row['admin_user_id'],
            'scopes' => self::normalizeScopes((string) $row['scopes']),
            'expires_at' => empty($row['expires_at']) ? null : gmdate('Y-m-d H:i:s', (int) strtotime((string) $row['expires_at'])),
        ];
    }
}
