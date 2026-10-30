/** Read-only SQL tools against the ERP database (enabled by ERP_DATABASE_URL). */
import type { McpServer } from "@modelcontextprotocol/server";
import * as z from "zod/v4";
import type { Principal } from "../auth.js";
import { CHARACTER_LIMIT, jsonResult, markdownTable, textResult } from "../format.js";
import { describeTable, listTables, runReadOnlySql, SQL_MAX_ROWS } from "../sql.js";
import { READ_ONLY, registerErpTool, responseFormatArg } from "./registry.js";

export function registerSqlTools(server: McpServer, principal: Principal): void {
  registerErpTool(
    server,
    principal,
    {
      name: "erp_sql_list_tables",
      title: "List ERP tables",
      description: "Lists the tables of the ERP database with approximate row counts. Optional filter matches table names (e.g. 'order', 'diamond', 'ledger'). Then use erp_sql_describe_table and erp_sql_query.",
      scope: "sql",
      input: z.strictObject({ filter: z.string().max(60).optional().describe("Substring to match in table names") }),
      annotations: READ_ONLY
    },
    async ({ filter }) => {
      const tables = await listTables();
      const needle = (filter ?? "").toLowerCase();
      const matched = needle === "" ? tables : tables.filter(t => t.table.toLowerCase().includes(needle));
      if (matched.length === 0) return textResult(`No tables match '${filter}'.`);
      return textResult(markdownTable(matched.map(t => ({ table: t.table, approx_rows: t.rows ?? "", comment: t.comment }))));
    }
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_sql_describe_table",
      title: "Describe ERP table",
      description: "Columns of one ERP table (name, type, nullable, key, default). Use before writing a query.",
      scope: "sql",
      input: z.strictObject({ table: z.string().min(1).max(64).regex(/^[A-Za-z0-9_]+$/).describe("Table name") }),
      annotations: READ_ONLY
    },
    async ({ table }) => textResult(`# ${table}\n\n${markdownTable(await describeTable(table))}`)
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_sql_query",
      title: "Run read-only SQL",
      description: `Run one read-only SQL statement (SELECT / WITH / SHOW / DESCRIBE / EXPLAIN) against the ERP MySQL database and return the rows. A LIMIT of max_rows (default 100, max ${SQL_MAX_ROWS}) is added when the query has none; the connection is READ ONLY with a 15 s execution cap. Write statements, multiple statements and comments are rejected.

Use it for reporting questions the API tools cannot answer directly (aggregations, joins across orders/customers/karigars/ledgers). Discover schema with erp_sql_list_tables and erp_sql_describe_table first.`,
      scope: "sql",
      input: z.strictObject({
        sql: z.string().min(6).max(8000).describe("The SELECT statement"),
        max_rows: z.number().int().min(1).max(SQL_MAX_ROWS).default(100),
        response_format: responseFormatArg
      }),
      annotations: READ_ONLY
    },
    async ({ sql, max_rows, response_format }) => {
      const result = await runReadOnlySql(sql, max_rows);
      if (response_format === "json") {
        return jsonResult({ columns: result.columns, row_count: result.rowCount, limited: result.limited, duration_ms: result.durationMs, rows: result.rows });
      }
      if (result.rows.length === 0) return textResult(`Query returned no rows (${result.durationMs} ms).`);
      let rows = result.rows;
      let table = markdownTable(rows, result.columns);
      while (rows.length > 1 && table.length > CHARACTER_LIMIT - 300) {
        rows = rows.slice(0, Math.ceil(rows.length / 2));
        table = markdownTable(rows, result.columns);
      }
      const notes = [`${result.rowCount} row(s) in ${result.durationMs} ms${result.limited ? `, LIMIT ${max_rows} applied` : ""}.`];
      if (rows.length < result.rows.length) notes.push(`Showing the first ${rows.length} rows to stay within the size limit; add WHERE filters or select fewer columns.`);
      return textResult(`${table}\n\n_${notes.join(" ")}_`);
    }
  );
}
