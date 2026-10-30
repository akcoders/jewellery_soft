/**
 * Bearer API-key authentication for the /mcp endpoint.
 *
 * Keys are stored hashed (SHA-256) in the external state database. A single
 * bootstrap key from the environment is accepted for first-time setup and for
 * the smoke test. The verifier plugs into the SDK's requireBearerAuth gate, so
 * failures become spec-compliant 401/403 responses with a WWW-Authenticate
 * challenge.
 */
import { OAuthError, OAuthErrorCode, type AuthInfo, type OAuthTokenVerifier } from "@modelcontextprotocol/server";
import { ALL_SCOPES, isScope, loadConfig, type Scope } from "./config.js";
import { safeEqual, sha256Hex } from "./crypto.js";
import { execute, queryRows, stateDb, type Row } from "./db.js";
import { ERP_KEY_ID_OFFSET, ErpKeyRejected, erpKeysEnabled, verifyErpKey, type ErpKey } from "./erpKeys.js";
import { parseUtcSql, utcNowSql } from "./time.js";

export interface Principal {
  /** Row id in mcp_api_keys, or null for the bootstrap key. */
  keyId: number | null;
  keyName: string;
  adminUserId: number;
  scopes: Scope[];
}

interface ApiKeyRow extends Row {
  id: number;
  name: string;
  admin_user_id: number;
  scopes: string;
  expires_at: string | null;
  revoked_at: string | null;
}

export function parseScopes(value: string): Scope[] {
  const scopes = value
    .split(/[,\s]+/)
    .map(scope => scope.trim().toLowerCase())
    .filter(isScope);
  return [...new Set(scopes)];
}

function toAuthInfo(token: string, principal: Principal, expiresAtMs: number): AuthInfo {
  const { publicUrl } = loadConfig();
  return {
    token,
    clientId: principal.keyId === null ? "bootstrap" : `key-${principal.keyId}`,
    scopes: principal.scopes,
    expiresAt: Math.floor(expiresAtMs / 1000),
    ...(publicUrl !== "" ? { resource: new URL(publicUrl) } : {}),
    extra: { principal }
  };
}

async function verifyThroughErp(token: string, keyHash: string, now: number): Promise<AuthInfo> {
  let key: ErpKey;
  try {
    key = await verifyErpKey(keyHash);
  } catch (error) {
    if (error instanceof ErpKeyRejected) throw new OAuthError(OAuthErrorCode.InvalidToken, error.message);
    console.error("ERP key check failed:", error);
    throw new OAuthError(OAuthErrorCode.ServerError, "The ERP could not verify this key. Try again shortly.");
  }

  if (key.expiresAt && key.expiresAt.getTime() <= now) throw new OAuthError(OAuthErrorCode.InvalidToken, "This API key has expired.");
  const scopes = parseScopes(key.scopes.join(","));
  if (scopes.length === 0) {
    throw new OAuthError(OAuthErrorCode.InvalidToken, "This API key has no scopes. Re-issue it with at least the 'read' scope.");
  }

  const principal: Principal = { keyId: ERP_KEY_ID_OFFSET + key.id, keyName: key.name, adminUserId: key.adminUserId, scopes };
  const expiresAtMs = key.expiresAt ? Math.min(key.expiresAt.getTime(), now + 3_600_000) : now + 3_600_000;
  return toAuthInfo(token, principal, expiresAtMs);
}

export function createVerifier(): OAuthTokenVerifier {
  return {
    async verifyAccessToken(token: string): Promise<AuthInfo> {
      const config = loadConfig();
      const now = Date.now();

      if (config.bootstrapKey !== "" && safeEqual(token, config.bootstrapKey)) {
        if (config.bootstrapAdminUserId <= 0) {
          throw new OAuthError(OAuthErrorCode.InvalidToken, "MCP_BOOTSTRAP_ADMIN_USER_ID must be set with MCP_BOOTSTRAP_KEY.");
        }
        return toAuthInfo(
          token,
          { keyId: null, keyName: "bootstrap", adminUserId: config.bootstrapAdminUserId, scopes: [...ALL_SCOPES] },
          now + 3_600_000
        );
      }

      const keyHash = sha256Hex(token);
      const pool = stateDb();
      let row: ApiKeyRow | undefined;
      if (pool) {
        try {
          const rows = await queryRows<ApiKeyRow>(
            pool,
            "SELECT id, name, admin_user_id, scopes, expires_at, revoked_at FROM mcp_api_keys WHERE key_hash = ? LIMIT 1",
            [keyHash]
          );
          row = rows[0];
        } catch (error) {
          console.error("API key lookup failed:", error);
          throw new OAuthError(OAuthErrorCode.ServerError, "The key store is unavailable. Try again shortly.");
        }
      }

      if (!pool || !row) {
        // Not a state-DB key: try keys issued from the ERP admin page.
        if (erpKeysEnabled()) return verifyThroughErp(token, keyHash, now);
        throw new OAuthError(OAuthErrorCode.InvalidToken, pool ? "Unknown API key." : "Unknown API key (no key store is configured).");
      }
      if (row.revoked_at) throw new OAuthError(OAuthErrorCode.InvalidToken, "This API key has been revoked.");
      const expires = parseUtcSql(row.expires_at);
      if (expires && expires.getTime() <= now) throw new OAuthError(OAuthErrorCode.InvalidToken, "This API key has expired.");

      const principal: Principal = {
        keyId: Number(row.id),
        keyName: String(row.name),
        adminUserId: Number(row.admin_user_id),
        scopes: parseScopes(String(row.scopes ?? ""))
      };
      if (principal.scopes.length === 0) {
        throw new OAuthError(OAuthErrorCode.InvalidToken, "This API key has no scopes. Re-issue it with at least the 'read' scope.");
      }

      // Short awaited write: the request lifecycle is a few hundred milliseconds either way.
      execute(pool, "UPDATE mcp_api_keys SET last_used_at = ? WHERE id = ?", [utcNowSql(), principal.keyId]).catch(error => {
        console.error("last_used_at update failed:", error);
      });

      const expiresAtMs = expires ? Math.min(expires.getTime(), now + 3_600_000) : now + 3_600_000;
      return toAuthInfo(token, principal, expiresAtMs);
    }
  };
}

export function principalFromAuthInfo(authInfo: AuthInfo | undefined): Principal {
  const candidate = authInfo?.extra?.principal as Partial<Principal> | undefined;
  if (!candidate || typeof candidate.adminUserId !== "number" || !Array.isArray(candidate.scopes)) {
    throw new Error("Request is not authenticated. Send 'Authorization: Bearer <api key>'.");
  }
  return {
    keyId: typeof candidate.keyId === "number" ? candidate.keyId : null,
    keyName: typeof candidate.keyName === "string" ? candidate.keyName : "unknown",
    adminUserId: candidate.adminUserId,
    scopes: candidate.scopes.filter(isScope)
  };
}

/** Returns an explanation when the principal lacks the scope, otherwise null. */
export function missingScopeMessage(principal: Principal, scope: Scope): string | null {
  if (principal.scopes.includes(scope)) return null;
  return `This API key does not have the '${scope}' scope (it has: ${principal.scopes.join(", ") || "none"}). Ask the ERP administrator to issue a key with '${scope}' access.`;
}
