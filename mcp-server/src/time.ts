/** UTC "YYYY-MM-DD HH:MM:SS" for MySQL DATETIME columns (we never rely on the DB server clock). */
export function utcNowSql(date = new Date()): string {
  return date.toISOString().slice(0, 19).replace("T", " ");
}

/** Parses a MySQL DATETIME string (or Date) that was stored as UTC. */
export function parseUtcSql(value: unknown): Date | null {
  if (value instanceof Date) return value;
  if (typeof value !== "string" || value.trim() === "") return null;
  const normalized = value.trim().replace(" ", "T") + (value.includes("Z") || value.includes("+") ? "" : "Z");
  const parsed = new Date(normalized);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
}
