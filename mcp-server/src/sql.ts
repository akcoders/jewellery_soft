/**
 * Read-only SQL gateway to the ERP database (optional, ERP_DATABASE_URL).
 * Defence in depth: statement whitelist + keyword blacklist + LIMIT + a
 * READ ONLY transaction + max_execution_time. Still, configure the MySQL user
 * with SELECT privileges only.
 */
import mysql, { type Pool } from "mysql2/promise";
import { loadConfig } from "./config.js";
import { poolOptionsFromUrl, type Row } from "./db.js";

export const SQL_MAX_ROWS = 500;
export const SQL_TIMEOUT_MS = 15_000;

let erpPool: Pool | undefined;
let erpPoolUrl = "";

export function sqlEnabled(): boolean {
  return loadConfig().erpDbUrl !== "";
}

export function erpDb(): Pool {
  const { erpDbUrl } = loadConfig();
  if (erpDbUrl === "") {
    throw new Error("SQL tools are disabled: ERP_DATABASE_URL is not configured on the MCP server. Use the erp_* API tools instead.");
  }
  if (!erpPool || erpPoolUrl !== erpDbUrl) {
    erpPool = mysql.createPool(poolOptionsFromUrl(erpDbUrl, { connectionLimit: 2, rowsAsArray: false }));
    erpPoolUrl = erpDbUrl;
  }
  return erpPool;
}

const ALLOWED_START = /^(select|with|show|describe|desc|explain)\b/i;
const FORBIDDEN =
  /\b(insert|update|delete|drop|alter|create|truncate|replace|grant|revoke|lock|unlock|call|load|handler|rename|set|into|outfile|dumpfile|shutdown|kill|purge|reset|flush|install|uninstall|start|commit|rollback|savepoint|xa|prepare|execute|deallocate|use|do|sleep|benchmark|load_file|get_lock|release_lock)\b/i;

export interface GuardedSql {
  sql: string;
  limited: boolean;
}

/** Validates a statement and appends a LIMIT when the query has none. */
export function guardReadOnlySql(input: string, maxRows = SQL_MAX_ROWS): GuardedSql {
  let sql = input.trim();
  if (sql.endsWith(";")) sql = sql.slice(0, -1).trim();
  if (sql === "") throw new Error("SQL is empty.");
  if (sql.includes(";")) throw new Error("Only a single statement is allowed (no ';' inside the query).");
  if (/\/\*|--|#/.test(sql)) throw new Error("Comments are not allowed in queries.");
  if (!ALLOWED_START.test(sql)) throw new Error("Only SELECT, WITH, SHOW, DESCRIBE and EXPLAIN statements are allowed.");

  // Strip string literals before keyword scanning so values like 'set' are not flagged.
  const withoutLiterals = sql.replace(/'(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*"|`[^`]*`/g, "''");
  const forbidden = withoutLiterals.match(FORBIDDEN);
  if (forbidden) throw new Error(`The keyword "${forbidden[0].toUpperCase()}" is not allowed in read-only queries.`);

  const rows = Math.min(Math.max(1, maxRows), SQL_MAX_ROWS);
  const isSelect = /^(select|with)\b/i.test(sql);
  if (isSelect && !/\blimit\s+\d+(\s*,\s*\d+|\s+offset\s+\d+)?\s*$/i.test(withoutLiterals)) {
    return { sql: `${sql} LIMIT ${rows}`, limited: true };
  }
  return { sql, limited: false };
}

export interface SqlResult {
  columns: string[];
  rows: Array<Record<string, unknown>>;
  rowCount: number;
  limited: boolean;
  durationMs: number;
}

export async function runReadOnlySql(statement: string, maxRows = SQL_MAX_ROWS): Promise<SqlResult> {
  const guarded = guardReadOnlySql(statement, maxRows);
  const pool = erpDb();
  const connection = await pool.getConnection();
  const started = Date.now();
  try {
    await connection.query(`SET SESSION max_execution_time = ${SQL_TIMEOUT_MS}`);
    await connection.query("SET SESSION TRANSACTION READ ONLY");
    await connection.query("START TRANSACTION READ ONLY");
    const [result, fields] = await connection.query<Row[]>(guarded.sql);
    await connection.query("ROLLBACK");
    const rows = (Array.isArray(result) ? result : []).slice(0, Math.min(maxRows, SQL_MAX_ROWS)) as Array<Record<string, unknown>>;
    const columns = Array.isArray(fields) && fields.length > 0 ? fields.map(field => String(field.name)) : Object.keys(rows[0] ?? {});
    return { columns, rows, rowCount: rows.length, limited: guarded.limited, durationMs: Date.now() - started };
  } catch (error) {
    try {
      await connection.query("ROLLBACK");
    } catch {
      /* ignore */
    }
    throw error;
  } finally {
    connection.release();
  }
}

export async function listTables(): Promise<Array<{ table: string; rows: number | null; comment: string }>> {
  const pool = erpDb();
  const [rows] = await pool.query<Row[]>(
    "SELECT TABLE_NAME AS table_name, TABLE_ROWS AS table_rows, TABLE_COMMENT AS table_comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME"
  );
  return rows.map(row => ({
    table: String(row.table_name),
    rows: row.table_rows === null || row.table_rows === undefined ? null : Number(row.table_rows),
    comment: String(row.table_comment ?? "")
  }));
}

export async function describeTable(table: string): Promise<Array<Record<string, unknown>>> {
  if (!/^[A-Za-z0-9_]+$/.test(table)) throw new Error("Table names may only contain letters, digits and underscores.");
  const pool = erpDb();
  const [rows] = await pool.query<Row[]>(
    "SELECT COLUMN_NAME AS column_name, COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable, COLUMN_KEY AS column_key, COLUMN_DEFAULT AS column_default, EXTRA AS extra, COLUMN_COMMENT AS column_comment FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION",
    [table]
  );
  if (rows.length === 0) throw new Error(`Table "${table}" was not found. Use erp_sql_list_tables to see available tables.`);
  return rows as Array<Record<string, unknown>>;
}
