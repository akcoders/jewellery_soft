/** Revokes an API key. Usage: npm run revoke-key -- --id 3 */
import { execute, requireStateDb } from "../src/db.js";
import { utcNowSql } from "../src/time.js";
import { arg, fail } from "./_cli.js";

const id = Number.parseInt(arg("id", "0"), 10);
if (!Number.isInteger(id) || id <= 0) fail("--id is required (see npm run list-keys).");

const pool = requireStateDb();
const result = await execute(pool, "UPDATE mcp_api_keys SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL", [utcNowSql(), id]);
await execute(pool, "DELETE FROM mcp_erp_sessions WHERE api_key_id = ?", [id]);
await pool.end();
console.log(result.affectedRows > 0 ? `API key #${id} revoked.` : `API key #${id} was not found or is already revoked.`);
