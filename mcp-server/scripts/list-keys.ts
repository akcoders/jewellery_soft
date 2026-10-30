/** Lists API keys (never the keys themselves). Usage: npm run list-keys */
import { queryRows, requireStateDb } from "../src/db.js";
import "./_cli.js";

const pool = requireStateDb();
const rows = await queryRows(pool, "SELECT id, name, key_prefix, admin_user_id, scopes, expires_at, revoked_at, last_used_at, created_at FROM mcp_api_keys ORDER BY id");
await pool.end();
if (rows.length === 0) {
  console.log("No API keys yet. Create one with: npm run create-key -- --name <name> --user <erp user id> --scopes read,write");
} else {
  console.table(rows);
}
