/**
 * ERP HTTP client. Every tool that reads or writes business data goes through
 * the ERP's own JSON API (/api/mobile/*), so the ERP's business rules, numbering,
 * ledgers and role permissions always apply. The MCP server acts *as the ERP
 * user* the API key is mapped to, using a short-lived ERP session token minted
 * by POST /api/mcp/session (shared secret) and cached encrypted in the state DB.
 */
import type { Principal } from "./auth.js";
import { loadConfig } from "./config.js";
import { decryptText, encryptText } from "./crypto.js";
import { execute, queryRows, stateDb, type Row } from "./db.js";
import { parseUtcSql, utcNowSql } from "./time.js";

const REQUEST_TIMEOUT_MS = 30_000;
const MAX_SESSION_MS = 11 * 60 * 60 * 1000;

export class ErpError extends Error {
  constructor(
    readonly status: number,
    message: string,
    readonly details?: unknown
  ) {
    super(message);
  }
}

interface ErpEnvelope {
  success?: boolean;
  message?: string;
  data?: unknown;
  errors?: unknown;
}

interface SessionRow extends Row {
  token_cipher: string;
  expires_at: string;
}

interface CachedSession {
  token: string;
  expiresAtMs: number;
}

const sessionCache = new Map<string, CachedSession>();

function cacheKey(principal: Principal): string {
  return principal.keyId === null ? `bootstrap:${principal.adminUserId}` : `key:${principal.keyId}`;
}

function requireErpBase(): string {
  const { erpBaseUrl } = loadConfig();
  if (erpBaseUrl === "") throw new Error("ERP_BASE_URL is not configured on the MCP server.");
  return erpBaseUrl;
}

function withTimeout(signal: AbortSignal | undefined): AbortSignal {
  const timeout = AbortSignal.timeout(REQUEST_TIMEOUT_MS);
  return signal ? AbortSignal.any([signal, timeout]) : timeout;
}

async function readEnvelope(response: Response): Promise<ErpEnvelope> {
  const text = await response.text();
  if (text.trim() === "") return {};
  try {
    return JSON.parse(text) as ErpEnvelope;
  } catch {
    return { success: false, message: `ERP returned a non-JSON response (HTTP ${response.status}): ${text.slice(0, 300)}` };
  }
}

async function mintSession(principal: Principal, signal?: AbortSignal): Promise<CachedSession> {
  const config = loadConfig();
  const base = requireErpBase();
  if (config.erpSharedSecret === "") throw new Error("MCP_ERP_SHARED_SECRET is not configured on the MCP server.");

  let response: Response;
  try {
    response = await fetch(`${base}/api/mcp/session`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json", "X-MCP-Secret": config.erpSharedSecret },
      body: JSON.stringify({ admin_user_id: principal.adminUserId, key_name: principal.keyName }),
      signal: withTimeout(signal)
    });
  } catch (error) {
    throw new ErpError(0, `Could not reach the ERP at ${base} to open a session: ${error instanceof Error ? error.message : String(error)}`);
  }
  const envelope = await readEnvelope(response);
  if (!response.ok || envelope.success === false) {
    throw new ErpError(
      response.status,
      `The ERP refused to open an MCP session (HTTP ${response.status}): ${envelope.message ?? "no message"}. Check mcp.sharedSecret in the ERP .env and that admin user #${principal.adminUserId} is active.`
    );
  }
  const data = (envelope.data ?? {}) as { token?: string; expires_at?: string };
  if (!data.token) throw new ErpError(response.status, "The ERP session response did not include a token.");
  const serverExpiry = parseUtcSql(data.expires_at);
  const expiresAtMs = Math.min(serverExpiry ? serverExpiry.getTime() : Number.POSITIVE_INFINITY, Date.now() + MAX_SESSION_MS);
  return { token: data.token, expiresAtMs };
}

async function loadStoredSession(principal: Principal): Promise<CachedSession | null> {
  const pool = stateDb();
  if (!pool || principal.keyId === null) return null;
  const { tokenSecret } = loadConfig();
  if (tokenSecret === "") return null;
  try {
    const rows = await queryRows<SessionRow>(pool, "SELECT token_cipher, expires_at FROM mcp_erp_sessions WHERE api_key_id = ? LIMIT 1", [principal.keyId]);
    const row = rows[0];
    if (!row) return null;
    const expires = parseUtcSql(row.expires_at);
    if (!expires) return null;
    return { token: decryptText(row.token_cipher, tokenSecret), expiresAtMs: expires.getTime() };
  } catch (error) {
    console.error("stored ERP session unusable:", error);
    return null;
  }
}

async function storeSession(principal: Principal, session: CachedSession): Promise<void> {
  const pool = stateDb();
  if (!pool || principal.keyId === null) return;
  const { tokenSecret } = loadConfig();
  if (tokenSecret === "") return;
  try {
    await execute(
      pool,
      `INSERT INTO mcp_erp_sessions (api_key_id, token_cipher, expires_at, updated_at) VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE token_cipher = VALUES(token_cipher), expires_at = VALUES(expires_at), updated_at = VALUES(updated_at)`,
      [principal.keyId, encryptText(session.token, tokenSecret), utcNowSql(new Date(session.expiresAtMs)), utcNowSql()]
    );
  } catch (error) {
    console.error("storing ERP session failed:", error);
  }
}

function isFresh(session: CachedSession | null | undefined): session is CachedSession {
  return !!session && session.expiresAtMs - Date.now() > 60_000;
}

export async function getErpToken(principal: Principal, signal?: AbortSignal): Promise<string> {
  const key = cacheKey(principal);
  const cached = sessionCache.get(key);
  if (isFresh(cached)) return cached.token;

  const stored = await loadStoredSession(principal);
  if (isFresh(stored)) {
    sessionCache.set(key, stored);
    return stored.token;
  }

  const minted = await mintSession(principal, signal);
  sessionCache.set(key, minted);
  await storeSession(principal, minted);
  return minted.token;
}

export function invalidateErpToken(principal: Principal): void {
  sessionCache.delete(cacheKey(principal));
}

export type QueryParams = Record<string, string | number | boolean | null | undefined>;

export function normalizeMobilePath(path: string): string {
  let clean = path.trim().replace(/^\/+/, "");
  clean = clean.replace(/^(index\.php\/)?/, "");
  if (clean.startsWith("mobile/")) clean = `api/${clean}`;
  if (!clean.startsWith("api/")) clean = `api/${clean}`;
  if (!/^api\/mobile\/[A-Za-z0-9_\-\/.]*$/.test(clean) || clean.includes("..")) {
    throw new Error(`Only ERP mobile API paths are allowed (api/mobile/...). Got "${path}". Use erp_api_catalog to list valid paths.`);
  }
  return clean;
}

export interface ErpRequestOptions {
  query?: QueryParams;
  body?: unknown;
  signal?: AbortSignal;
}

/**
 * Calls the ERP mobile API as the principal's ERP user and returns the `data`
 * part of the ERP envelope. Throws ErpError with the ERP's own message.
 */
export async function erpRequest(principal: Principal, method: "GET" | "POST", path: string, options: ErpRequestOptions = {}): Promise<unknown> {
  const base = requireErpBase();
  const cleanPath = normalizeMobilePath(path);
  const url = new URL(`${base}/${cleanPath}`);
  for (const [key, value] of Object.entries(options.query ?? {})) {
    if (value === undefined || value === null || value === "") continue;
    url.searchParams.set(key, String(value));
  }

  const attempt = async (token: string): Promise<Response> => {
    try {
      return await fetch(url, {
        method,
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/json",
          ...(method === "POST" ? { "Content-Type": "application/json" } : {})
        },
        body: method === "POST" ? JSON.stringify(options.body ?? {}) : undefined,
        signal: withTimeout(options.signal)
      });
    } catch (error) {
      if (error instanceof DOMException && error.name === "TimeoutError") {
        throw new ErpError(0, `The ERP did not answer ${method} ${cleanPath} within 30 seconds. Try again or narrow the request.`);
      }
      throw new ErpError(0, `Could not reach the ERP for ${method} ${cleanPath}: ${error instanceof Error ? error.message : String(error)}`);
    }
  };

  let response = await attempt(await getErpToken(principal, options.signal));
  if (response.status === 401) {
    invalidateErpToken(principal);
    response = await attempt(await getErpToken(principal, options.signal));
  }

  const envelope = await readEnvelope(response);
  if (!response.ok || envelope.success === false) {
    const message = envelope.message ?? `HTTP ${response.status}`;
    const hint =
      response.status === 403
        ? " The mapped ERP user lacks permission for this action."
        : response.status === 404
          ? " Check the id; list tools return valid ids."
          : response.status === 422
            ? " Fix the listed fields and retry."
            : "";
    throw new ErpError(response.status, `ERP rejected ${method} ${cleanPath}: ${message}.${hint}`, envelope.errors);
  }
  return envelope.data === undefined ? envelope : envelope.data;
}
