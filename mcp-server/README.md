# Aabhushan ERP MCP server

A Node.js [Model Context Protocol](https://modelcontextprotocol.io) server that exposes the Aabhushan jewellery ERP to AI clients (Claude, Cursor, Inspector, …) over **Streamable HTTP** at `https://<your-subdomain>/mcp`.

## How it fits the constraints

| Requirement | How it is met |
|---|---|
| Node.js, Streamable HTTP | `@modelcontextprotocol/server` v2 `createMcpHandler` in stateless JSON mode. No SSE stream is kept open, no `Mcp-Session-Id` state. |
| HTTPS subdomain | Deployed as Vercel Functions; add the subdomain (e.g. `mcp.yourdomain.com`) in the Vercel project. TLS is automatic. |
| Authenticated `/mcp` | Bearer API keys (SHA-256 hashed in the state DB) through the SDK's `requireBearerAuth`; 401/403 with `WWW-Authenticate` and an RFC 9728 resource-metadata document. Scopes: `read`, `write`, `sql`. |
| Short request lifecycle | Every HTTP request creates a fresh `McpServer`, answers, and ends (`maxDuration` 60 s). ERP calls time out after 30 s, SQL after 15 s. |
| External database for state | MySQL (`MCP_DATABASE_URL`): API keys, encrypted cached ERP sessions, audit log. Any MySQL host works, including the ERP database with Remote MySQL enabled. |
| Deployment through GitHub | Vercel Git integration deploys every push to `main`; `.github/workflows/mcp-server.yml` typechecks, builds and smoke-tests on every push/PR (optional Actions-based deploy job included). |
| No WebSockets / Docker / custom ports / Redis / background worker | Plain HTTPS functions on port 443, in-memory + MySQL caching only, nothing runs between requests. |

## Architecture

```
AI client ──Bearer key──▶ https://mcp.example.com/mcp  (Vercel function, api/mcp.ts → src/app.ts)
                              │  verify key (MySQL mcp_api_keys)
                              │  createServer() per request → erp_* tools
                              ├─▶ ERP JSON API  https://erp/.../api/mobile/*   (acts as the mapped ERP user)
                              │      session token from POST /api/mcp/session (shared secret),
                              │      cached AES-GCM encrypted in mcp_erp_sessions
                              └─▶ optional read-only MySQL (ERP_DATABASE_URL) for erp_sql_* tools
```

All business writes go through the ERP's own API, so ERP permissions, numbering, stock and ledger logic always apply. The MCP server never writes to ERP tables directly.

### Tools (all prefixed `erp_`)

* **Account / meta**: `erp_whoami`, `erp_server_info`, `erp_api_catalog`
* **Orders**: `erp_list_orders`, `erp_get_order`, `erp_list_order_followups`, `erp_order_form_options`, `erp_get_order_receive_form`, `erp_create_order`, `erp_add_order_followup`, `erp_assign_karigar`, `erp_update_order_follower`, `erp_receive_order`, `erp_rate_order`, `erp_create_work_request`, `erp_review_work_request`, `erp_list_work_requests`
* **Inventory & materials**: `erp_inventory_summary`, `erp_list_inventory`, `erp_list_material_transactions`, `erp_get_material_transaction`, `erp_list_issuements`, `erp_create_issuement`, `erp_list_diamond_bags`, `erp_get_diamond_bag`, `erp_list_diamond_requirements`, `erp_lookup`
* **Team**: `erp_list_tasks`, `erp_list_admin_tasks`, `erp_create_admin_task`, `erp_cancel_admin_task`, `erp_complete_task`, `erp_performance`, `erp_analytics`, `erp_list_notifications`
* **Approvals**: `erp_list_approval_requests`, `erp_review_approval_request`, `erp_request_new_customer`, `erp_request_new_karigar`
* **Documents**: `erp_list_documents`
* **Generic** (covers every other mobile endpoint): `erp_api_get`, `erp_api_post`
* **SQL** (only when `ERP_DATABASE_URL` is set and the key has the `sql` scope): `erp_sql_list_tables`, `erp_sql_describe_table`, `erp_sql_query`

Resources: `erp://api/catalog`, `erp://schema/{table}`. Prompts: `erp_daily_briefing`, `erp_order_review`.

## Setup

### 1. ERP side (one setting)

Add to the ERP `.env` a long random secret:

```
mcp.sharedSecret = 'paste-a-long-random-string'
```

The ERP route `POST /api/mcp/session` (already in the codebase) uses it to mint 12-hour mobile API tokens for the ERP user an MCP key maps to.

### 2. State database

Create an empty MySQL database (or reuse the ERP database) and allow connections from the internet (cPanel → *Remote MySQL*; for Vercel's dynamic IPs this means `%`, so use a strong password and SSL). Then, locally:

```bash
cd mcp-server
cp .env.example .env            # fill in the values
npm install
npm run migrate                 # creates mcp_api_keys, mcp_erp_sessions, mcp_audit_log
npm run create-key -- --name "Claude" --user 1 --scopes read,write --expires-days 365
```

`--user` is the `admin_users.id` the key acts as; the ERP role of that user decides what the key can see and do. Add `sql` to `--scopes` only for keys that may run read-only SQL.

**Keys from the ERP admin panel.** Admins can also issue per-user keys at *Admin → MCP API Keys* (`/admin/mcp-keys`, permission `company-settings.manage`). Those keys live hashed in the ERP database; when a bearer key is not found in `mcp_api_keys` of the state DB, the MCP server checks it with `POST /api/mcp/verify-key` (same `X-MCP-Secret`) and caches the answer for 60 s, so a key revoked in the ERP stops working within a minute.

### 3. Deploy on Vercel from GitHub

1. Push this repository to GitHub.
2. In Vercel: **Add New Project → Import** the repo, set **Root Directory** to `mcp-server`. Framework preset: *Other*.
3. Environment variables (Production): `ERP_BASE_URL`, `MCP_ERP_SHARED_SECRET`, `MCP_TOKEN_SECRET`, `MCP_DATABASE_URL`, `MCP_PUBLIC_URL=https://mcp.yourdomain.com/mcp`, optionally `ERP_DATABASE_URL`.
4. Deploy, then **Settings → Domains** → add `mcp.yourdomain.com` and create the CNAME it shows at your DNS provider.
5. Every later push to `main` redeploys automatically.

Check: `curl https://mcp.yourdomain.com/health` and

```bash
curl -s https://mcp.yourdomain.com/mcp -H "Authorization: Bearer mcp_..." \
  -H "Content-Type: application/json" -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

### 4. Connect a client

* **Claude** (custom connector / MCP settings): URL `https://mcp.yourdomain.com/mcp`, header `Authorization: Bearer <key>`.
* **MCP Inspector**: `npx @modelcontextprotocol/inspector`, transport *Streamable HTTP*, same URL and header.

## Local development

```bash
npm run dev          # tsx watch, http://127.0.0.1:3939/mcp
npm run build && npm run smoke   # self-check used by CI (no DB or ERP needed)
```

`MCP_BOOTSTRAP_KEY` + `MCP_BOOTSTRAP_ADMIN_USER_ID` accept one static key without a database (used by the smoke test and for first-time setup). Remove them in production.

## Security notes

* Keys are random 256-bit values shown once; only SHA-256 hashes are stored. Revoke with `npm run revoke-key -- --id N`.
* ERP session tokens are cached AES-256-GCM encrypted (`MCP_TOKEN_SECRET`) and expire after 12 hours; the ERP revokes the previous token whenever a new one is minted.
* `erp_sql_query` accepts a single SELECT/SHOW/DESCRIBE/EXPLAIN, strips nothing silently, rejects write keywords and comments, runs in a READ ONLY transaction with a 15 s cap and adds a LIMIT. Still give `ERP_DATABASE_URL` a MySQL user with **SELECT only**.
* Every tool call is written to `mcp_audit_log` (key, ERP user, tool, success, duration).
* Set `MCP_PUBLIC_URL` so tokens are bound to this resource, and `MCP_ALLOWED_HOSTS` when self-hosting without a platform in front.

## Scripts

| Command | Purpose |
|---|---|
| `npm run migrate` | Create / update state tables |
| `npm run create-key -- --name … --user … --scopes … [--expires-days …]` | Issue an API key |
| `npm run list-keys` / `npm run revoke-key -- --id N` | Manage keys |
| `npm run typecheck` / `npm run build` / `npm run smoke` | Verify |
