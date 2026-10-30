/** Per-call audit trail in the state database. Never fails the tool call. */
import type { Principal } from "./auth.js";
import { execute, stateDb } from "./db.js";
import { utcNowSql } from "./time.js";

export async function recordAudit(principal: Principal, toolName: string, ok: boolean, durationMs: number, summary: string): Promise<void> {
  const pool = stateDb();
  if (!pool) return;
  try {
    await execute(
      pool,
      "INSERT INTO mcp_audit_log (api_key_id, admin_user_id, tool_name, ok, duration_ms, summary, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
      [principal.keyId, principal.adminUserId, toolName.slice(0, 120), ok ? 1 : 0, Math.max(0, Math.round(durationMs)), summary.slice(0, 255), utcNowSql()]
    );
  } catch (error) {
    console.error("audit insert failed:", error);
  }
}
