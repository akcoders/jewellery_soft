/**
 * Server factory + Streamable HTTP handler. createMcpHandler calls the factory
 * once per HTTP request, so no MCP state lives between requests (stateless
 * JSON mode: no sessions, no SSE streams to keep open, no background worker).
 */
import { createMcpHandler, McpServer, type McpRequestContext } from "@modelcontextprotocol/server";
import { principalFromAuthInfo } from "./auth.js";
import { SERVER_NAME, SERVER_VERSION } from "./config.js";
import { sqlEnabled } from "./sql.js";
import { registerMetaTools } from "./tools/meta.js";
import { registerReadTools } from "./tools/read.js";
import { registerSqlTools } from "./tools/sql.js";
import { registerWriteTools } from "./tools/write.js";

export function createServer(ctx: McpRequestContext): McpServer {
  const principal = principalFromAuthInfo(ctx.authInfo);
  const server = new McpServer(
    { name: SERVER_NAME, version: SERVER_VERSION, title: "Aabhushan ERP" },
    {
      instructions: `Tools for the Aabhushan jewellery ERP, acting as ERP user #${principal.adminUserId} ("${principal.keyName}" key, scopes: ${principal.scopes.join(", ")}).
Start with erp_whoami. Read tools are safe to call freely; write tools change live business data, so confirm intent before calling them. Dates are Asia/Kolkata. Use erp_api_catalog to discover endpoints that have no dedicated tool, then erp_api_get / erp_api_post.${sqlEnabled() && principal.scopes.includes("sql") ? " SQL tools (erp_sql_*) give read-only access to the database for reporting." : ""}`
    }
  );

  registerMetaTools(server, principal);
  registerReadTools(server, principal);
  registerWriteTools(server, principal);
  if (sqlEnabled()) registerSqlTools(server, principal);
  return server;
}

export const mcpHandler = createMcpHandler(createServer, {
  onerror: error => console.error("[mcp]", error)
});
