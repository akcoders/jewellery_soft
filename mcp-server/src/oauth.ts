/**
 * OAuth 2.1 authorization server for MCP clients that cannot send a static
 * bearer key (ChatGPT connectors, claude.ai custom connectors).
 *
 * Flow: the client registers (/register, RFC 7591) and opens /authorize with
 * PKCE S256. The browser is sent to the ERP consent page (/admin/mcp-authorize)
 * with an HMAC-signed request; the ERP user logs in and allows access, the ERP
 * records a grant (an "oauth" row in its mcp_api_keys table) and sends the
 * browser back to /oauth/callback with a signed decision. The client then gets
 * a one-time code and exchanges it at /token for an opaque access token (1 h)
 * and a rotating refresh token (30 d), both stored hashed in the state DB.
 * Every token check also asks the ERP (cached 60 s) whether the grant is still
 * active, so revoking the connection in the ERP admin panel cuts access.
 */
import { randomBytes } from "node:crypto";
import { ALL_SCOPES, loadConfig, type Scope } from "./config.js";
import { randomToken, safeEqual, sha256Base64Url, sha256Hex, signPayload, verifySignedPayload } from "./crypto.js";
import { ensureOAuthSchema, execute, queryRows, stateDb, type Row } from "./db.js";
import { sqlEnabled } from "./sql.js";
import { ErpKeyRejected, erpKeysEnabled, forgetErpGrant, verifyErpGrant, type ErpKey } from "./erpKeys.js";
import { parseUtcSql, utcNowSql } from "./time.js";

const REQUEST_TTL_S = 600;
const CODE_TTL_S = 120;
const ACCESS_TTL_S = 3600;
const REFRESH_TTL_S = 30 * 86400;
const DEFAULT_SCOPES: Scope[] = ["read", "write"];
const ACCESS_PREFIX = "mcpat_";
const REFRESH_PREFIX = "mcprt_";
const CODE_PREFIX = "mcpac_";
const FORBIDDEN_REDIRECT_SCHEMES = ["javascript", "data", "file", "vbscript", "about", "blob", "ws", "wss", "ftp"];

interface ClientRow extends Row {
  client_id: string;
  client_secret_hash: string | null;
  client_name: string;
  redirect_uris: string;
  token_endpoint_auth_method: string;
}

interface RequestRow extends Row {
  id: string;
  client_id: string;
  redirect_uri: string;
  code_challenge: string;
  scope: string;
  state: string | null;
  expires_at: string;
  decided_at: string | null;
  code_expires_at: string | null;
  code_used_at: string | null;
  grant_id: number | null;
  admin_user_id: number | null;
  granted_scope: string | null;
}

interface TokenRow extends Row {
  token_hash: string;
  kind: string;
  client_id: string;
  grant_id: number;
  admin_user_id: number;
  scope: string;
  expires_at: string;
  revoked_at: string | null;
}

interface ErpDecision {
  v: number;
  rid: string;
  decision: "allow" | "deny";
  grant_id?: number;
  uid?: number;
  scopes?: string[];
  exp: number;
}

export interface OAuthAccess {
  grantId: number;
  adminUserId: number;
  scopes: Scope[];
  expiresAt: Date;
}

/** OAuth needs the public URL (issuer), the state DB and the ERP shared secret. */
export function oauthEnabled(): boolean {
  return loadConfig().publicUrl !== "" && stateDb() !== undefined && erpKeysEnabled();
}

export function isOAuthAccessToken(token: string): boolean {
  return token.startsWith(ACCESS_PREFIX);
}

/** Scopes a client may ask for; "sql" only while the SQL tools are enabled. */
export function availableScopes(): Scope[] {
  return sqlEnabled() ? [...ALL_SCOPES] : ALL_SCOPES.filter(scope => scope !== "sql");
}

export function issuerUrl(): string {
  return new URL(loadConfig().publicUrl).origin;
}

function nowSeconds(): number {
  return Math.floor(Date.now() / 1000);
}

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-store", Pragma: "no-cache" }
  });
}

function oauthError(error: string, description: string, status = 400): Response {
  return jsonResponse({ error, error_description: description }, status);
}

function escapeHtml(value: string): string {
  return value.replace(/[&<>"']/g, ch => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[ch] ?? ch);
}

/** Shown in the browser when we cannot safely redirect back to the client. */
function htmlError(title: string, message: string, status = 400): Response {
  const body = `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>${escapeHtml(title)}</title></head>
<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem;color:#222"><h1 style="font-size:1.4rem">${escapeHtml(title)}</h1><p>${escapeHtml(message)}</p></body></html>`;
  return new Response(body, { status, headers: { "Content-Type": "text/html; charset=utf-8", "Cache-Control": "no-store" } });
}

function redirectTo(location: string): Response {
  return new Response(null, { status: 302, headers: { Location: location, "Cache-Control": "no-store" } });
}

function withParams(base: string, params: Record<string, string | undefined>): string {
  const url = new URL(base);
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined) url.searchParams.set(key, value);
  }
  return url.href;
}

function parseScopeList(value: string): Scope[] {
  const scopes = value
    .split(/[\s,]+/)
    .map(scope => scope.trim().toLowerCase())
    .filter((scope): scope is Scope => (ALL_SCOPES as readonly string[]).includes(scope));
  return [...new Set(scopes)];
}

function resourceAllowed(resource: string): boolean {
  const normalize = (value: string) => value.replace(/\/+$/, "");
  const target = normalize(resource);
  return target === normalize(loadConfig().publicUrl) || target === normalize(issuerUrl());
}

/** Returns why a redirect URI is unacceptable, or null when it is fine. */
function redirectUriProblem(uri: string): string | null {
  let url: URL;
  try {
    url = new URL(uri);
  } catch {
    return "is not a valid URL";
  }
  if (url.hash !== "") return "must not contain a fragment";
  const scheme = url.protocol.slice(0, -1).toLowerCase();
  if (scheme === "https") return null;
  if (scheme === "http") {
    return ["localhost", "127.0.0.1", "[::1]"].includes(url.hostname) ? null : "must use https (http is only allowed for localhost)";
  }
  if (FORBIDDEN_REDIRECT_SCHEMES.includes(scheme)) return `uses a forbidden scheme (${scheme})`;
  return null; // private-use scheme of a native app, e.g. cursor://
}

/** What the consent page shows as "will return to". */
function redirectLabel(uri: string): string {
  const url = new URL(uri);
  return url.protocol === "https:" || url.protocol === "http:" ? url.host : `${url.protocol}//${url.host}`;
}

function cleanClientName(value: string): string {
  return value.replace(/[\u0000-\u001f\u007f<>]/g, "").trim().slice(0, 100);
}

async function oauthPool() {
  const pool = stateDb();
  if (!pool) throw new Error("MCP_DATABASE_URL is required for OAuth.");
  await ensureOAuthSchema(pool);
  return pool;
}

async function findClient(pool: NonNullable<ReturnType<typeof stateDb>>, clientId: string): Promise<ClientRow | null> {
  if (clientId.length > 64) return null;
  const rows = await queryRows<ClientRow>(
    pool,
    "SELECT client_id, client_secret_hash, client_name, redirect_uris, token_endpoint_auth_method FROM mcp_oauth_clients WHERE client_id = ? LIMIT 1",
    [clientId]
  );
  return rows[0] ?? null;
}

function registeredRedirects(client: ClientRow): string[] {
  try {
    const parsed = JSON.parse(client.redirect_uris) as unknown;
    return Array.isArray(parsed) ? parsed.map(String) : [];
  } catch {
    return [];
  }
}

/** Reads an application/x-www-form-urlencoded (or JSON) body into strings. */
async function readForm(request: Request): Promise<Record<string, string>> {
  const text = (await request.text()).slice(0, 20_000);
  if ((request.headers.get("content-type") ?? "").includes("application/json")) {
    try {
      const parsed = JSON.parse(text) as Record<string, unknown>;
      return Object.fromEntries(Object.entries(parsed).filter(([, value]) => typeof value === "string" || typeof value === "number").map(([key, value]) => [key, String(value)]));
    } catch {
      return {};
    }
  }
  return Object.fromEntries(new URLSearchParams(text));
}

function parseBasicAuth(header: string | null): { id: string; secret: string } | null {
  if (!header || !/^basic /i.test(header)) return null;
  try {
    const decoded = Buffer.from(header.slice(6).trim(), "base64").toString("utf8");
    const colon = decoded.indexOf(":");
    if (colon < 0) return null;
    return { id: decodeURIComponent(decoded.slice(0, colon)), secret: decodeURIComponent(decoded.slice(colon + 1)) };
  } catch {
    return null;
  }
}

// ---------------------------------------------------------------------------
// Metadata
// ---------------------------------------------------------------------------

export function authorizationServerMetadata(): Response {
  const issuer = issuerUrl();
  return jsonResponse({
    issuer,
    authorization_endpoint: `${issuer}/authorize`,
    token_endpoint: `${issuer}/token`,
    registration_endpoint: `${issuer}/register`,
    revocation_endpoint: `${issuer}/revoke`,
    response_types_supported: ["code"],
    response_modes_supported: ["query"],
    grant_types_supported: ["authorization_code", "refresh_token"],
    code_challenge_methods_supported: ["S256"],
    token_endpoint_auth_methods_supported: ["none", "client_secret_post", "client_secret_basic"],
    revocation_endpoint_auth_methods_supported: ["none", "client_secret_post", "client_secret_basic"],
    scopes_supported: availableScopes(),
    authorization_response_iss_parameter_supported: true,
    service_documentation: `${issuer}/`
  });
}

// ---------------------------------------------------------------------------
// Dynamic client registration (RFC 7591)
// ---------------------------------------------------------------------------

export async function handleRegister(request: Request): Promise<Response> {
  if (request.method !== "POST") return oauthError("invalid_request", "Use POST.", 405);
  const pool = await oauthPool();

  let body: Record<string, unknown>;
  try {
    const text = await request.text();
    if (text.length > 10_000) return oauthError("invalid_client_metadata", "Registration request is too large.");
    const parsed = JSON.parse(text) as unknown;
    if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) throw new Error("not an object");
    body = parsed as Record<string, unknown>;
  } catch {
    return oauthError("invalid_client_metadata", "Body must be a JSON object.");
  }

  const uris = body.redirect_uris;
  if (!Array.isArray(uris) || uris.length === 0 || uris.length > 10 || !uris.every(uri => typeof uri === "string" && uri.length <= 500)) {
    return oauthError("invalid_redirect_uri", "redirect_uris must list 1 to 10 URLs.");
  }
  for (const uri of uris as string[]) {
    const problem = redirectUriProblem(uri);
    if (problem) return oauthError("invalid_redirect_uri", `${uri} ${problem}.`);
  }

  if (Array.isArray(body.grant_types) && !body.grant_types.map(String).includes("authorization_code")) {
    return oauthError("invalid_client_metadata", "grant_types must include authorization_code.");
  }
  if (body.response_types !== undefined && !(Array.isArray(body.response_types) && body.response_types.every(type => type === "code"))) {
    return oauthError("invalid_client_metadata", "Only the code response type is supported.");
  }
  const method = typeof body.token_endpoint_auth_method === "string" ? body.token_endpoint_auth_method : "none";
  if (!["none", "client_secret_post", "client_secret_basic"].includes(method)) {
    return oauthError("invalid_client_metadata", `token_endpoint_auth_method "${method}" is not supported.`);
  }

  const clientName = cleanClientName(typeof body.client_name === "string" ? body.client_name : "") || "MCP client";
  const clientId = `mcpc_${randomBytes(16).toString("hex")}`;
  const clientSecret = method === "none" ? null : randomToken("mcpcs_");
  const issuedAt = nowSeconds();

  await execute(
    pool,
    "INSERT INTO mcp_oauth_clients (client_id, client_secret_hash, client_name, redirect_uris, token_endpoint_auth_method, created_at) VALUES (?, ?, ?, ?, ?, ?)",
    [clientId, clientSecret ? sha256Hex(clientSecret) : null, clientName, JSON.stringify(uris), method, utcNowSql()]
  );

  return jsonResponse(
    {
      client_id: clientId,
      client_id_issued_at: issuedAt,
      ...(clientSecret ? { client_secret: clientSecret, client_secret_expires_at: 0 } : {}),
      client_name: clientName,
      redirect_uris: uris,
      grant_types: ["authorization_code", "refresh_token"],
      response_types: ["code"],
      token_endpoint_auth_method: method
    },
    201
  );
}

// ---------------------------------------------------------------------------
// Authorization endpoint → ERP consent page
// ---------------------------------------------------------------------------

export async function handleAuthorize(request: Request): Promise<Response> {
  const query = new URL(request.url).searchParams;
  const pool = await oauthPool();

  const client = await findClient(pool, query.get("client_id") ?? "");
  if (!client) {
    return htmlError("Unknown application", "This application is not registered with the Aabhushan ERP MCP server. Remove the connector in your AI app and add it again.");
  }
  const registered = registeredRedirects(client);
  let redirectUri = query.get("redirect_uri") ?? "";
  if (redirectUri === "" && registered.length === 1) redirectUri = registered[0] ?? "";
  if (!registered.includes(redirectUri)) {
    return htmlError("Invalid redirect address", "The redirect address does not match the one this application registered.");
  }

  const state = query.get("state") ?? undefined;
  const fail = (error: string, description: string) =>
    redirectTo(withParams(redirectUri, { error, error_description: description, state, iss: issuerUrl() }));

  if (query.get("response_type") !== "code") return fail("unsupported_response_type", "Only response_type=code is supported.");
  const challenge = query.get("code_challenge") ?? "";
  if (!/^[A-Za-z0-9_-]{43,128}$/.test(challenge) || query.get("code_challenge_method") !== "S256") {
    return fail("invalid_request", "PKCE with code_challenge_method=S256 is required.");
  }
  const resource = query.get("resource") ?? "";
  if (resource !== "" && !resourceAllowed(resource)) return fail("invalid_target", "This server does not serve the requested resource.");
  if (state !== undefined && state.length > 2000) return fail("invalid_request", "state is too long.");

  // Unknown scopes (e.g. "openid") are ignored; no usable scope means the default set.
  const requested = parseScopeList(query.get("scope") ?? "").filter(scope => availableScopes().includes(scope));
  const scopes = requested.length > 0 ? requested : DEFAULT_SCOPES;

  const requestId = randomBytes(24).toString("hex");
  await execute(
    pool,
    "INSERT INTO mcp_oauth_requests (id, client_id, redirect_uri, code_challenge, scope, state, resource, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
    [requestId, client.client_id, redirectUri, challenge, scopes.join(" "), state ?? null, resource || null, utcNowSql(new Date(Date.now() + REQUEST_TTL_S * 1000)), utcNowSql()]
  );

  const { erpBaseUrl, erpSharedSecret } = loadConfig();
  const signed = signPayload(
    {
      v: 1,
      rid: requestId,
      client: client.client_name,
      redirect: redirectLabel(redirectUri),
      scopes,
      cb: `${issuerUrl()}/oauth/callback`,
      exp: nowSeconds() + REQUEST_TTL_S
    },
    erpSharedSecret
  );
  return redirectTo(withParams(`${erpBaseUrl}/admin/mcp-authorize`, { d: signed.data, s: signed.sig }));
}

// ---------------------------------------------------------------------------
// ERP consent decision → authorization code
// ---------------------------------------------------------------------------

export async function handleCallback(request: Request): Promise<Response> {
  const query = new URL(request.url).searchParams;
  const decision = verifySignedPayload<ErpDecision>(query.get("d") ?? "", query.get("s") ?? "", loadConfig().erpSharedSecret);
  if (!decision || decision.v !== 1 || typeof decision.rid !== "string" || typeof decision.exp !== "number") {
    return htmlError("Could not verify the ERP response", "Start the connection again from your AI app.");
  }
  if (decision.exp < nowSeconds()) return htmlError("Approval expired", "The approval took too long. Start the connection again from your AI app.");

  const pool = await oauthPool();
  const rows = await queryRows<RequestRow>(pool, "SELECT * FROM mcp_oauth_requests WHERE id = ? LIMIT 1", [decision.rid]);
  const pending = rows[0];
  if (!pending) return htmlError("Unknown request", "Start the connection again from your AI app.");
  if (pending.decided_at) return htmlError("Already used", "This approval was already used. Start the connection again from your AI app.");
  const expires = parseUtcSql(pending.expires_at);
  if (!expires || expires.getTime() < Date.now()) return htmlError("Request expired", "Start the connection again from your AI app.");

  const state = pending.state ?? undefined;
  const iss = issuerUrl();

  if (decision.decision !== "allow") {
    await execute(pool, "UPDATE mcp_oauth_requests SET decided_at = ? WHERE id = ? AND decided_at IS NULL", [utcNowSql(), pending.id]);
    return redirectTo(withParams(pending.redirect_uri, { error: "access_denied", error_description: "Access was declined in the ERP.", state, iss }));
  }

  const grantId = Number(decision.grant_id);
  const adminUserId = Number(decision.uid);
  const requested = parseScopeList(pending.scope);
  const granted = parseScopeList((decision.scopes ?? []).join(" ")).filter(scope => requested.includes(scope));
  if (!Number.isInteger(grantId) || grantId <= 0 || !Number.isInteger(adminUserId) || adminUserId <= 0 || granted.length === 0) {
    return htmlError("Invalid approval", "The ERP approval was incomplete. Start the connection again from your AI app.");
  }

  const code = randomToken(CODE_PREFIX);
  const result = await execute(
    pool,
    `UPDATE mcp_oauth_requests SET decided_at = ?, code_hash = ?, code_expires_at = ?, grant_id = ?, admin_user_id = ?, granted_scope = ?
     WHERE id = ? AND decided_at IS NULL`,
    [utcNowSql(), sha256Hex(code), utcNowSql(new Date(Date.now() + CODE_TTL_S * 1000)), grantId, adminUserId, granted.join(" "), pending.id]
  );
  if (result.affectedRows !== 1) return htmlError("Already used", "This approval was already used. Start the connection again from your AI app.");

  forgetErpGrant(grantId);
  return redirectTo(withParams(pending.redirect_uri, { code, state, iss }));
}

// ---------------------------------------------------------------------------
// Token endpoint
// ---------------------------------------------------------------------------

type Pool = NonNullable<ReturnType<typeof stateDb>>;

export async function handleToken(request: Request): Promise<Response> {
  if (request.method !== "POST") return oauthError("invalid_request", "Use POST.", 405);
  const pool = await oauthPool();
  const form = await readForm(request);

  const basic = parseBasicAuth(request.headers.get("authorization"));
  const clientId = basic?.id ?? form.client_id ?? "";
  const clientSecret = basic?.secret ?? form.client_secret ?? "";
  const client = clientId ? await findClient(pool, clientId) : null;
  if (!client) return oauthError("invalid_client", "Unknown client.", 401);
  if (client.client_secret_hash && (clientSecret === "" || !safeEqual(sha256Hex(clientSecret), client.client_secret_hash))) {
    return oauthError("invalid_client", "Client authentication failed.", 401);
  }
  if (form.resource && !resourceAllowed(form.resource)) return oauthError("invalid_target", "This server does not serve the requested resource.");

  cleanupExpired(pool);
  if (form.grant_type === "authorization_code") return exchangeCode(pool, client, form);
  if (form.grant_type === "refresh_token") return refreshTokens(pool, client, form);
  return oauthError("unsupported_grant_type", "Use authorization_code or refresh_token.");
}

async function exchangeCode(pool: Pool, client: ClientRow, form: Record<string, string>): Promise<Response> {
  const code = form.code ?? "";
  const verifier = form.code_verifier ?? "";
  if (!code.startsWith(CODE_PREFIX) || verifier === "") return oauthError("invalid_request", "code and code_verifier are required.");

  const codeHash = sha256Hex(code);
  const rows = await queryRows<RequestRow>(pool, "SELECT * FROM mcp_oauth_requests WHERE code_hash = ? LIMIT 1", [codeHash]);
  const row = rows[0];
  if (!row || row.client_id !== client.client_id || row.grant_id === null || row.admin_user_id === null) {
    return oauthError("invalid_grant", "Unknown authorization code.");
  }
  const grantId = Number(row.grant_id);
  if (row.code_used_at) {
    // A replayed code may be stolen: revoke what it produced.
    await revokeFamily(pool, grantId, client.client_id);
    return oauthError("invalid_grant", "This authorization code was already used.");
  }
  const codeExpires = parseUtcSql(row.code_expires_at);
  if (!codeExpires || codeExpires.getTime() < Date.now()) return oauthError("invalid_grant", "The authorization code has expired.");
  if ((form.redirect_uri ?? row.redirect_uri) !== row.redirect_uri) return oauthError("invalid_grant", "redirect_uri does not match the authorization request.");
  if (!/^[A-Za-z0-9._~-]{43,128}$/.test(verifier) || !safeEqual(sha256Base64Url(verifier), row.code_challenge)) {
    return oauthError("invalid_grant", "PKCE verification failed.");
  }

  const used = await execute(pool, "UPDATE mcp_oauth_requests SET code_used_at = ? WHERE code_hash = ? AND code_used_at IS NULL", [utcNowSql(), codeHash]);
  if (used.affectedRows !== 1) return oauthError("invalid_grant", "This authorization code was already used.");

  const grant = await checkGrant(grantId, Number(row.admin_user_id));
  if (grant instanceof Response) return grant;
  const scopes = parseScopeList(row.granted_scope ?? "").filter(scope => grant.scopes.includes(scope));
  if (scopes.length === 0) return oauthError("invalid_scope", "The ERP connection no longer allows any scope.");
  return issueTokens(pool, client.client_id, grantId, grant.adminUserId, scopes);
}

async function refreshTokens(pool: Pool, client: ClientRow, form: Record<string, string>): Promise<Response> {
  const token = form.refresh_token ?? "";
  if (!token.startsWith(REFRESH_PREFIX)) return oauthError("invalid_grant", "Unknown refresh token.");

  const tokenHash = sha256Hex(token);
  const rows = await queryRows<TokenRow>(pool, "SELECT * FROM mcp_oauth_tokens WHERE token_hash = ? AND kind = 'refresh' LIMIT 1", [tokenHash]);
  const row = rows[0];
  if (!row || row.client_id !== client.client_id) return oauthError("invalid_grant", "Unknown refresh token.");
  const grantId = Number(row.grant_id);
  if (row.revoked_at) {
    // Refresh tokens rotate; reuse of an old one means it may be stolen.
    await revokeFamily(pool, grantId, client.client_id);
    return oauthError("invalid_grant", "This refresh token is no longer valid. Connect again.");
  }
  const expires = parseUtcSql(row.expires_at);
  if (!expires || expires.getTime() < Date.now()) return oauthError("invalid_grant", "The refresh token has expired. Connect again.");

  const rotated = await execute(pool, "UPDATE mcp_oauth_tokens SET revoked_at = ? WHERE token_hash = ? AND revoked_at IS NULL", [utcNowSql(), tokenHash]);
  if (rotated.affectedRows !== 1) return oauthError("invalid_grant", "This refresh token is no longer valid. Connect again.");

  const grant = await checkGrant(grantId, Number(row.admin_user_id));
  if (grant instanceof Response) return grant;
  const allowed = parseScopeList(row.scope).filter(scope => grant.scopes.includes(scope));
  const narrowed = form.scope ? parseScopeList(form.scope).filter(scope => allowed.includes(scope)) : allowed;
  if (narrowed.length === 0) return oauthError("invalid_scope", "No permitted scope left for this connection.");
  return issueTokens(pool, client.client_id, grantId, grant.adminUserId, narrowed);
}

/** Token endpoint check: always asks the ERP (no cache), so a revoked connection cannot mint new tokens. */
async function checkGrant(grantId: number, adminUserId: number): Promise<ErpKey | Response> {
  try {
    const grant = await verifyErpGrant(grantId, { fresh: true });
    if (grant.adminUserId !== adminUserId) return oauthError("invalid_grant", "The ERP connection belongs to a different user.");
    return grant;
  } catch (error) {
    if (error instanceof ErpKeyRejected) return oauthError("invalid_grant", `${error.message} Connect again.`);
    console.error("ERP grant check failed:", error);
    return oauthError("temporarily_unavailable", "The ERP could not be reached. Try again shortly.", 503);
  }
}

async function issueTokens(pool: Pool, clientId: string, grantId: number, adminUserId: number, scopes: Scope[]): Promise<Response> {
  const accessToken = randomToken(ACCESS_PREFIX);
  const refreshToken = randomToken(REFRESH_PREFIX);
  const now = Date.now();
  const scope = scopes.join(" ");
  await execute(
    pool,
    `INSERT INTO mcp_oauth_tokens (token_hash, kind, client_id, grant_id, admin_user_id, scope, expires_at, created_at)
     VALUES (?, 'access', ?, ?, ?, ?, ?, ?), (?, 'refresh', ?, ?, ?, ?, ?, ?)`,
    [
      sha256Hex(accessToken), clientId, grantId, adminUserId, scope, utcNowSql(new Date(now + ACCESS_TTL_S * 1000)), utcNowSql(),
      sha256Hex(refreshToken), clientId, grantId, adminUserId, scope, utcNowSql(new Date(now + REFRESH_TTL_S * 1000)), utcNowSql()
    ]
  );
  return jsonResponse({ access_token: accessToken, token_type: "Bearer", expires_in: ACCESS_TTL_S, refresh_token: refreshToken, scope });
}

async function revokeFamily(pool: Pool, grantId: number, clientId: string): Promise<void> {
  await execute(pool, "UPDATE mcp_oauth_tokens SET revoked_at = ? WHERE grant_id = ? AND client_id = ? AND revoked_at IS NULL", [utcNowSql(), grantId, clientId]);
}

/** Occasionally drops long-expired rows; never blocks or fails the request. */
function cleanupExpired(pool: Pool): void {
  if (Math.random() > 0.02) return;
  const dayAgo = utcNowSql(new Date(Date.now() - 86_400_000));
  const weekAgo = utcNowSql(new Date(Date.now() - 7 * 86_400_000));
  Promise.all([
    execute(pool, "DELETE FROM mcp_oauth_requests WHERE expires_at < ? AND (code_expires_at IS NULL OR code_expires_at < ?)", [dayAgo, dayAgo]),
    execute(pool, "DELETE FROM mcp_oauth_tokens WHERE expires_at < ?", [weekAgo])
  ]).catch(error => console.error("OAuth cleanup failed:", error));
}

// ---------------------------------------------------------------------------
// Revocation (RFC 7009)
// ---------------------------------------------------------------------------

export async function handleRevoke(request: Request): Promise<Response> {
  if (request.method !== "POST") return oauthError("invalid_request", "Use POST.", 405);
  const pool = await oauthPool();
  const form = await readForm(request);
  const token = form.token ?? "";
  if (token === "") return oauthError("invalid_request", "token is required.");

  const rows = await queryRows<TokenRow>(pool, "SELECT * FROM mcp_oauth_tokens WHERE token_hash = ? LIMIT 1", [sha256Hex(token)]);
  const row = rows[0];
  if (row) {
    if (row.kind === "refresh") await revokeFamily(pool, Number(row.grant_id), row.client_id);
    else await execute(pool, "UPDATE mcp_oauth_tokens SET revoked_at = ? WHERE token_hash = ? AND revoked_at IS NULL", [utcNowSql(), row.token_hash]);
  }
  // Per RFC 7009 the response is the same whether or not the token existed.
  return new Response(null, { status: 200, headers: { "Cache-Control": "no-store" } });
}

// ---------------------------------------------------------------------------
// Access token lookup for /mcp
// ---------------------------------------------------------------------------

/** Returns the stored access token, or a reason why it cannot be used. */
export async function lookupAccessToken(token: string): Promise<OAuthAccess | string> {
  const pool = stateDb();
  if (!pool) return "OAuth is not configured on this server.";
  await ensureOAuthSchema(pool);
  const rows = await queryRows<TokenRow>(pool, "SELECT * FROM mcp_oauth_tokens WHERE token_hash = ? AND kind = 'access' LIMIT 1", [sha256Hex(token)]);
  const row = rows[0];
  if (!row) return "Unknown access token.";
  if (row.revoked_at) return "This access token has been revoked.";
  const expiresAt = parseUtcSql(row.expires_at);
  if (!expiresAt || expiresAt.getTime() <= Date.now()) return "This access token has expired.";
  return { grantId: Number(row.grant_id), adminUserId: Number(row.admin_user_id), scopes: parseScopeList(row.scope), expiresAt };
}
