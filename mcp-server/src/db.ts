/**
 * External state database (MySQL): API keys, cached ERP sessions, audit log.
 * One small pool per warm instance; every request is short-lived.
 */
import mysql, { type Pool, type PoolOptions, type RowDataPacket, type ResultSetHeader } from "mysql2/promise";

export type SqlParam = string | number | boolean | null | Date | Buffer;
import { loadConfig } from "./config.js";

export type Row = RowDataPacket;

export function poolOptionsFromUrl(url: string, extra: Partial<PoolOptions> = {}): PoolOptions {
  const parsed = new URL(url);
  if (!/^mysql2?:$/.test(parsed.protocol)) {
    throw new Error(`Unsupported database URL scheme "${parsed.protocol}". Use mysql://user:pass@host:3306/db`);
  }
  const sslParam = (parsed.searchParams.get("ssl") ?? "").toLowerCase();
  let ssl: PoolOptions["ssl"];
  if (sslParam === "true" || sslParam === "1" || sslParam === "required") ssl = { minVersion: "TLSv1.2", rejectUnauthorized: true };
  if (sslParam === "skip-verify") ssl = { minVersion: "TLSv1.2", rejectUnauthorized: false };
  return {
    host: parsed.hostname,
    port: parsed.port ? Number(parsed.port) : 3306,
    user: decodeURIComponent(parsed.username),
    password: decodeURIComponent(parsed.password),
    database: parsed.pathname.replace(/^\//, ""),
    ssl,
    waitForConnections: true,
    connectionLimit: 2,
    maxIdle: 2,
    idleTimeout: 30_000,
    connectTimeout: 10_000,
    enableKeepAlive: false,
    timezone: "Z",
    dateStrings: true,
    supportBigNumbers: true,
    bigNumberStrings: true,
    ...extra
  };
}

let statePool: Pool | undefined;
let statePoolUrl = "";

/** Returns the state pool, or undefined when MCP_DATABASE_URL is not configured. */
export function stateDb(): Pool | undefined {
  const { stateDbUrl } = loadConfig();
  if (stateDbUrl === "") return undefined;
  if (!statePool || statePoolUrl !== stateDbUrl) {
    statePool = mysql.createPool(poolOptionsFromUrl(stateDbUrl));
    statePoolUrl = stateDbUrl;
  }
  return statePool;
}

export function requireStateDb(): Pool {
  const pool = stateDb();
  if (!pool) throw new Error("MCP_DATABASE_URL is not configured. Set it to a MySQL URL (mysql://user:pass@host:3306/db).");
  return pool;
}

export const STATE_SCHEMA_SQL: readonly string[] = [
  `CREATE TABLE IF NOT EXISTS mcp_api_keys (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    key_prefix VARCHAR(16) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    admin_user_id INT UNSIGNED NOT NULL,
    scopes VARCHAR(255) NOT NULL DEFAULT 'read',
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mcp_api_keys_hash (key_hash),
    KEY idx_mcp_api_keys_user (admin_user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`,
  `CREATE TABLE IF NOT EXISTS mcp_erp_sessions (
    api_key_id INT UNSIGNED NOT NULL,
    token_cipher TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (api_key_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`,
  `CREATE TABLE IF NOT EXISTS mcp_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    api_key_id INT UNSIGNED NULL,
    admin_user_id INT UNSIGNED NULL,
    tool_name VARCHAR(120) NOT NULL,
    ok TINYINT(1) NOT NULL DEFAULT 1,
    duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
    summary VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_mcp_audit_created (created_at),
    KEY idx_mcp_audit_key (api_key_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`
];

/** OAuth 2.1 state: registered clients, pending authorizations (+ their codes) and issued tokens. */
export const OAUTH_SCHEMA_SQL: readonly string[] = [
  `CREATE TABLE IF NOT EXISTS mcp_oauth_clients (
    client_id VARCHAR(64) NOT NULL,
    client_secret_hash CHAR(64) NULL,
    client_name VARCHAR(100) NOT NULL,
    redirect_uris TEXT NOT NULL,
    token_endpoint_auth_method VARCHAR(30) NOT NULL DEFAULT 'none',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (client_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`,
  `CREATE TABLE IF NOT EXISTS mcp_oauth_requests (
    id CHAR(48) NOT NULL,
    client_id VARCHAR(64) NOT NULL,
    redirect_uri VARCHAR(500) NOT NULL,
    code_challenge VARCHAR(128) NOT NULL,
    scope VARCHAR(100) NOT NULL,
    state TEXT NULL,
    resource VARCHAR(300) NULL,
    expires_at DATETIME NOT NULL,
    decided_at DATETIME NULL,
    code_hash CHAR(64) NULL,
    code_expires_at DATETIME NULL,
    code_used_at DATETIME NULL,
    grant_id INT UNSIGNED NULL,
    admin_user_id INT UNSIGNED NULL,
    granted_scope VARCHAR(100) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mcp_oauth_requests_code (code_hash),
    KEY idx_mcp_oauth_requests_expires (expires_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`,
  `CREATE TABLE IF NOT EXISTS mcp_oauth_tokens (
    token_hash CHAR(64) NOT NULL,
    kind VARCHAR(10) NOT NULL,
    client_id VARCHAR(64) NOT NULL,
    grant_id INT UNSIGNED NOT NULL,
    admin_user_id INT UNSIGNED NOT NULL,
    scope VARCHAR(100) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (token_hash),
    KEY idx_mcp_oauth_tokens_grant (grant_id, client_id),
    KEY idx_mcp_oauth_tokens_expires (expires_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`
];

export async function ensureStateSchema(pool: Pool): Promise<void> {
  for (const statement of [...STATE_SCHEMA_SQL, ...OAUTH_SCHEMA_SQL]) await pool.query(statement);
}

let oauthSchemaReady: Promise<void> | undefined;

/** Creates the OAuth tables once per process, so hosts that set up tables by hand keep working. */
export function ensureOAuthSchema(pool: Pool): Promise<void> {
  if (!oauthSchemaReady) {
    oauthSchemaReady = (async () => {
      for (const statement of OAUTH_SCHEMA_SQL) await pool.query(statement);
    })().catch(error => {
      oauthSchemaReady = undefined;
      throw error;
    });
  }
  return oauthSchemaReady;
}

export async function queryRows<T extends Row = Row>(pool: Pool, sql: string, params: SqlParam[] = []): Promise<T[]> {
  const [rows] = await pool.query<T[]>(sql, params);
  return rows;
}

export async function execute(pool: Pool, sql: string, params: SqlParam[] = []): Promise<ResultSetHeader> {
  const [result] = await pool.execute<ResultSetHeader>(sql, params);
  return result;
}
