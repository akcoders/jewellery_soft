/**
 * Self-check: starts the built local server with a bootstrap key and no
 * database, then exercises auth, tool listing, a tool call, a resource and
 * validation errors through a real Streamable HTTP client.
 * Run with: npm run build && npm run smoke
 */
import { strict as assert } from "node:assert";
import { spawn } from "node:child_process";
import { setTimeout as delay } from "node:timers/promises";
import { Client, StreamableHTTPClientTransport } from "@modelcontextprotocol/client";

const PORT = 3977;
const KEY = "smoke-test-key-0123456789";
const BASE = `http://127.0.0.1:${PORT}`;

const child = spawn(process.execPath, ["dist/src/local.js"], {
  env: {
    PATH: process.env.PATH ?? "",
    PORT: String(PORT),
    HOST: "127.0.0.1",
    ERP_BASE_URL: "http://127.0.0.1:9/erp",
    MCP_ERP_SHARED_SECRET: "smoke",
    MCP_TOKEN_SECRET: "smoke-token-secret-0123456789",
    MCP_BOOTSTRAP_KEY: KEY,
    MCP_BOOTSTRAP_ADMIN_USER_ID: "1",
    MCP_DATABASE_URL: "",
    ERP_DATABASE_URL: ""
  },
  stdio: ["ignore", "inherit", "inherit"]
});

const stop = () => {
  if (!child.killed) child.kill();
};
process.on("exit", stop);

try {
  let ready = false;
  for (let attempt = 0; attempt < 50 && !ready; attempt++) {
    try {
      const response = await fetch(`${BASE}/health`);
      ready = response.ok;
    } catch {
      await delay(100);
    }
  }
  assert.ok(ready, "server did not start");

  const health = (await (await fetch(`${BASE}/health`)).json()) as { ok: boolean; state_db: string };
  assert.equal(health.ok, true);
  assert.equal(health.state_db, "not-configured");

  // Unauthenticated: 401 with a WWW-Authenticate challenge
  const unauthenticated = await fetch(`${BASE}/mcp`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json, text/event-stream" },
    body: JSON.stringify({ jsonrpc: "2.0", id: 1, method: "initialize", params: { protocolVersion: "2025-06-18", capabilities: {}, clientInfo: { name: "smoke", version: "1" } } })
  });
  assert.equal(unauthenticated.status, 401, "missing token must be rejected");
  assert.match(unauthenticated.headers.get("www-authenticate") ?? "", /Bearer/);

  // Wrong token
  const wrong = await fetch(`${BASE}/mcp`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json, text/event-stream", Authorization: "Bearer nope" },
    body: JSON.stringify({ jsonrpc: "2.0", id: 1, method: "initialize", params: { protocolVersion: "2025-06-18", capabilities: {}, clientInfo: { name: "smoke", version: "1" } } })
  });
  // The key is not in the (absent) state DB, so the ERP is asked to vouch for it; the ERP is
  // unreachable here, so the request fails closed with a server error instead of being accepted.
  assert.equal(wrong.status, 500, "unknown token must be rejected when the ERP cannot verify it");

  // Resource metadata document
  const metadata = (await (await fetch(`${BASE}/.well-known/oauth-protected-resource`)).json()) as { resource: string };
  assert.equal(metadata.resource, `${BASE}/mcp`);

  // Authenticated client
  const client = new Client({ name: "smoke-test", version: "1.0.0" });
  await client.connect(new StreamableHTTPClientTransport(new URL(`${BASE}/mcp`), { requestInit: { headers: { Authorization: `Bearer ${KEY}` } } }));

  const { tools } = await client.listTools();
  const names = tools.map(tool => tool.name);
  for (const expected of ["erp_whoami", "erp_list_orders", "erp_get_order", "erp_api_get", "erp_api_post", "erp_create_order", "erp_api_catalog", "erp_server_info"]) {
    assert.ok(names.includes(expected), `missing tool ${expected}`);
  }
  assert.ok(!names.includes("erp_sql_query"), "SQL tools must be hidden when ERP_DATABASE_URL is empty");
  assert.ok(tools.every(tool => tool.name.startsWith("erp_")), "all tools carry the erp_ prefix");
  assert.ok(tools.every(tool => tool.description && tool.description.length > 20), "every tool has a description");

  const catalog = await client.callTool({ name: "erp_api_catalog", arguments: { query: "gold issue" } });
  assert.ok(!catalog.isError, `catalog failed: ${JSON.stringify(catalog.content)}`);
  const catalogText = (catalog.content as Array<{ type: string; text?: string }>).find(block => block.type === "text")?.text ?? "";
  assert.match(catalogText, /api\/mobile\/gold\/issues/);

  const info = await client.callTool({ name: "erp_server_info", arguments: {} });
  assert.ok(!info.isError);
  const structured = info.structuredContent as { scopes: string[]; sql_tools_enabled: boolean };
  assert.deepEqual(structured.scopes, ["read", "write", "sql"]);
  assert.equal(structured.sql_tools_enabled, false);

  // Invalid arguments are a tool error, not a crash
  const invalid = await client.callTool({ name: "erp_get_order", arguments: { order_id: -5 } });
  assert.equal(invalid.isError, true, "negative id must be rejected");

  // Unknown POST path is refused before touching the ERP
  const badPost = await client.callTool({ name: "erp_api_post", arguments: { path: "api/mobile/does-not-exist", body: {} } });
  assert.equal(badPost.isError, true);
  assert.match((badPost.content as Array<{ type: string; text?: string }>)[0]?.text ?? "", /not a known POST endpoint/);

  // ERP unreachable surfaces as a readable tool error
  const whoami = await client.callTool({ name: "erp_whoami", arguments: {} });
  assert.equal(whoami.isError, true);
  assert.match((whoami.content as Array<{ type: string; text?: string }>)[0]?.text ?? "", /Could not reach the ERP/);

  const { resources } = await client.listResources();
  assert.ok(resources.some(resource => resource.uri === "erp://api/catalog"));
  const resource = await client.readResource({ uri: "erp://api/catalog" });
  assert.ok(resource.contents.length === 1);

  const { prompts } = await client.listPrompts();
  assert.ok(prompts.some(prompt => prompt.name === "erp_daily_briefing"));

  await client.close();
  console.error(`Smoke test passed: ${tools.length} tools, ${resources.length} resources, ${prompts.length} prompts.`);
} finally {
  stop();
}
