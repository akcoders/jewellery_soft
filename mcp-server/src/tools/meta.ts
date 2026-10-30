/** Catalogue tool, resources and prompts. */
import { McpServer, ResourceTemplate, ResourceNotFoundError } from "@modelcontextprotocol/server";
import * as z from "zod/v4";
import type { Principal } from "../auth.js";
import { ENDPOINTS, searchCatalog } from "../catalog.js";
import { SERVER_NAME, SERVER_VERSION } from "../config.js";
import { jsonResult, markdownTable, textResult } from "../format.js";
import { describeTable, listTables, sqlEnabled } from "../sql.js";
import { READ_ONLY, registerErpTool } from "./registry.js";

const GROUPS = [...new Set(ENDPOINTS.map(endpoint => endpoint.group))];

export function registerMetaTools(server: McpServer, principal: Principal): void {
  registerErpTool(
    server,
    principal,
    {
      name: "erp_api_catalog",
      title: "ERP API catalogue",
      description: `Search the catalogue of ERP mobile API endpoints (method, path, purpose, parameters). Groups: ${GROUPS.join(", ")}. Use it to find paths for erp_api_get / erp_api_post. Example: query="gold issue" or group="diamonds".`,
      scope: "read",
      input: z.strictObject({
        query: z.string().max(100).default("").describe("Words to match against path and summary"),
        group: z.enum(GROUPS as [string, ...string[]]).optional()
      }),
      annotations: { ...READ_ONLY, openWorldHint: false }
    },
    async ({ query, group }) => {
      const matches = searchCatalog(query, group);
      if (matches.length === 0) return textResult(`No endpoints match '${query}'. Try a broader word or leave the query empty.`);
      return textResult(
        markdownTable(
          matches.map(endpoint => ({
            method: endpoint.method,
            path: endpoint.path,
            group: endpoint.group,
            summary: endpoint.summary,
            params: (endpoint.params ?? []).join(", "),
            body: (endpoint.body ?? []).join(", ")
          }))
        )
      );
    }
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_server_info",
      title: "MCP server info",
      description: "Describes this MCP server: version, the API key in use, its scopes, and which optional features (SQL) are enabled.",
      scope: "read",
      input: z.strictObject({}),
      annotations: { ...READ_ONLY, openWorldHint: false }
    },
    async () =>
      jsonResult({
        server: SERVER_NAME,
        version: SERVER_VERSION,
        key: principal.keyName,
        erp_user_id: principal.adminUserId,
        scopes: principal.scopes,
        sql_tools_enabled: sqlEnabled(),
        endpoint_count: ENDPOINTS.length
      })
  );

  server.registerResource(
    "erp-api-catalog",
    "erp://api/catalog",
    { title: "ERP API catalogue", description: "Every ERP mobile API endpoint this server can call", mimeType: "application/json" },
    async uri => ({ contents: [{ uri: uri.href, mimeType: "application/json", text: JSON.stringify(ENDPOINTS, null, 2) }] })
  );

  if (sqlEnabled() && principal.scopes.includes("sql")) {
    server.registerResource(
      "erp-schema-table",
      new ResourceTemplate("erp://schema/{table}", {
        list: async () => ({
          resources: (await listTables()).map(t => ({ uri: `erp://schema/${t.table}`, name: t.table, description: t.comment || undefined, mimeType: "application/json" }))
        })
      }),
      { title: "ERP table schema", description: "Column definitions of one ERP database table", mimeType: "application/json" },
      async (uri, { table }) => {
        const name = String(table);
        if (!/^[A-Za-z0-9_]+$/.test(name)) throw new ResourceNotFoundError(uri.href);
        try {
          const columns = await describeTable(name);
          return { contents: [{ uri: uri.href, mimeType: "application/json", text: JSON.stringify(columns, null, 2) }] };
        } catch {
          throw new ResourceNotFoundError(uri.href);
        }
      }
    );
  }

  server.registerPrompt(
    "erp_daily_briefing",
    {
      title: "Daily operations briefing",
      description: "Summarise today's open orders, overdue follow-ups, pending approvals and stock position.",
      argsSchema: z.object({ focus: z.string().optional().describe("Optional area to emphasise (orders, inventory, team)") })
    },
    async ({ focus }) => ({
      messages: [
        {
          role: "user",
          content: {
            type: "text",
            text: `Prepare a concise daily briefing for the jewellery workshop${focus ? ` with emphasis on ${focus}` : ""}. Use erp_list_orders (scope="followups"), erp_list_approval_requests, erp_list_work_requests, erp_inventory_summary and erp_list_notifications(status_only=true). Report: orders due or overdue, follow-ups due today, pending approvals/requests needing a decision, and notable stock levels. End with the three most urgent actions.`
          }
        }
      ]
    })
  );

  server.registerPrompt(
    "erp_order_review",
    {
      title: "Order review",
      description: "Deep-dive on one order: status, timeline, materials issued and risks.",
      argsSchema: z.object({ order_id: z.string().describe("Order id") })
    },
    async ({ order_id }) => ({
      messages: [
        {
          role: "user",
          content: {
            type: "text",
            text: `Review order ${order_id}. Call erp_get_order and erp_list_order_followups, then erp_list_work_requests and erp_list_diamond_bags for that order. Summarise: current stage and due date, who follows it up, materials issued vs expected, open requests, and any delay risk. Suggest the next follow-up note if one is due.`
          }
        }
      ]
    })
  );
}
