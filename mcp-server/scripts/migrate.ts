/** Creates the state tables (idempotent). Usage: npm run migrate */
import { ensureStateSchema, requireStateDb } from "../src/db.js";
import "./_cli.js";

const pool = requireStateDb();
await ensureStateSchema(pool);
console.log("State schema is up to date (mcp_api_keys, mcp_erp_sessions, mcp_audit_log, mcp_oauth_*).");
await pool.end();
