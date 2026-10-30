/**
 * Issues an API key. The plain key is printed once and only its hash is stored.
 * Usage: npm run create-key -- --name "Claude desktop" --user 1 --scopes read,write --expires-days 365
 */
import { parseScopes } from "../src/auth.js";
import { randomToken, sha256Hex } from "../src/crypto.js";
import { ensureStateSchema, execute, requireStateDb } from "../src/db.js";
import { utcNowSql } from "../src/time.js";
import { arg, fail } from "./_cli.js";

const name = arg("name").trim();
const adminUserId = Number.parseInt(arg("user", "0"), 10);
const scopes = parseScopes(arg("scopes", "read"));
const expiresDays = Number.parseInt(arg("expires-days", "0"), 10);

if (name === "") fail("--name is required (who or what will use this key).");
if (!Number.isInteger(adminUserId) || adminUserId <= 0) fail("--user must be the ERP admin_users.id the key acts as.");
if (scopes.length === 0) fail("--scopes must include at least one of read, write, sql.");

const pool = requireStateDb();
await ensureStateSchema(pool);
const key = randomToken("mcp_");
const expiresAt = expiresDays > 0 ? utcNowSql(new Date(Date.now() + expiresDays * 86_400_000)) : null;
const result = await execute(
  pool,
  "INSERT INTO mcp_api_keys (name, key_prefix, key_hash, admin_user_id, scopes, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
  [name.slice(0, 80), key.slice(0, 12), sha256Hex(key), adminUserId, scopes.join(","), expiresAt, utcNowSql()]
);
await pool.end();

console.log(`Created API key #${result.insertId} "${name}" for ERP user ${adminUserId} with scopes ${scopes.join(",")}${expiresAt ? `, expires ${expiresAt} UTC` : ""}.`);
console.log("");
console.log("Store this key now; it cannot be shown again:");
console.log("");
console.log(`  ${key}`);
console.log("");
console.log('Use it as the header  Authorization: Bearer <key>  on the /mcp endpoint.');
