/**
 * API keys issued from the ERP admin page (/admin/mcp-keys). The ERP stores
 * only key hashes; the MCP server sends the SHA-256 hash to
 * POST /api/mcp/verify-key (shared secret) and caches the answer briefly, so a
 * revoked key stops working within ERP_KEY_CACHE_MS.
 */
import { loadConfig } from "./config.js";
import { parseUtcSql } from "./time.js";

/** ERP key ids are offset so they never collide with state-DB key ids (sessions, audit log). */
export const ERP_KEY_ID_OFFSET = 1_000_000_000;

const ERP_KEY_CACHE_MS = 60_000;
const ERP_KEY_MISS_CACHE_MS = 15_000;
const VERIFY_TIMEOUT_MS = 10_000;

export interface ErpKey {
  id: number;
  name: string;
  adminUserId: number;
  scopes: string[];
  expiresAt: Date | null;
}

/** Thrown when the key is known to be unusable (unknown, revoked, expired, user inactive). */
export class ErpKeyRejected extends Error {}

type CacheEntry = { until: number; key: ErpKey } | { until: number; rejected: string };

const cache = new Map<string, CacheEntry>();
const CACHE_MAX_ENTRIES = 1000;

function remember(keyHash: string, entry: CacheEntry): void {
  if (cache.size >= CACHE_MAX_ENTRIES) cache.clear();
  cache.set(keyHash, entry);
}

export function erpKeysEnabled(): boolean {
  const config = loadConfig();
  return config.erpBaseUrl !== "" && config.erpSharedSecret !== "";
}

/**
 * Resolves a key hash through the ERP. Throws ErpKeyRejected for unusable keys
 * and a plain Error when the ERP cannot be reached or answers unexpectedly.
 */
export async function verifyErpKey(keyHash: string): Promise<ErpKey> {
  const now = Date.now();
  const cached = cache.get(keyHash);
  if (cached && cached.until > now) {
    if ("rejected" in cached) throw new ErpKeyRejected(cached.rejected);
    return cached.key;
  }

  const config = loadConfig();
  let response: Response;
  try {
    response = await fetch(`${config.erpBaseUrl}/api/mcp/verify-key`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json", "X-MCP-Secret": config.erpSharedSecret },
      body: JSON.stringify({ key_hash: keyHash }),
      signal: AbortSignal.timeout(VERIFY_TIMEOUT_MS)
    });
  } catch (error) {
    throw new Error(`ERP key check failed: ${error instanceof Error ? error.message : String(error)}`);
  }

  let envelope: { success?: boolean; message?: string; data?: Record<string, unknown> } = {};
  try {
    envelope = (await response.json()) as typeof envelope;
  } catch {
    envelope = {};
  }

  if (response.status === 404) {
    const reason = envelope.message ?? "Unknown API key.";
    remember(keyHash, { until: now + ERP_KEY_MISS_CACHE_MS, rejected: reason });
    throw new ErpKeyRejected(reason);
  }
  if (!response.ok || envelope.success === false || !envelope.data) {
    throw new Error(`ERP key check failed (HTTP ${response.status}): ${envelope.message ?? "no message"}`);
  }

  const data = envelope.data;
  const key: ErpKey = {
    id: Number(data.id),
    name: String(data.name ?? "erp-key"),
    adminUserId: Number(data.admin_user_id),
    scopes: Array.isArray(data.scopes) ? data.scopes.map(String) : [],
    expiresAt: parseUtcSql(data.expires_at)
  };
  if (!Number.isInteger(key.id) || key.id <= 0 || !Number.isInteger(key.adminUserId) || key.adminUserId <= 0) {
    throw new Error("ERP key check returned an invalid key record.");
  }
  remember(keyHash, { until: now + ERP_KEY_CACHE_MS, key });
  return key;
}
