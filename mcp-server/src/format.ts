/** Response shaping helpers shared by every tool. */
import type { CallToolResult } from "@modelcontextprotocol/server";

export const CHARACTER_LIMIT = 25_000;

export type Json = null | boolean | number | string | Json[] | { [key: string]: Json };

export function stringify(value: unknown): string {
  return JSON.stringify(value, null, 2) ?? "null";
}

/**
 * Serialises `data` as JSON and keeps it under CHARACTER_LIMIT. When the payload
 * holds an array (top level, or under a common list key) the array is shortened
 * instead of cutting the text, and a note says how many items were dropped.
 */
export function fitJson(data: unknown, limit = CHARACTER_LIMIT): { text: string; truncated: boolean; note?: string } {
  let text = stringify(data);
  if (text.length <= limit) return { text, truncated: false };

  const listKeys = ["items", "rows", "data", "results", "orders", "list", "entries"];
  let container: Record<string, unknown> | null = null;
  let key: string | null = null;
  let list: unknown[] | null = null;

  if (Array.isArray(data)) {
    list = data;
  } else if (data && typeof data === "object") {
    container = { ...(data as Record<string, unknown>) };
    for (const candidate of listKeys) {
      if (Array.isArray(container[candidate])) {
        key = candidate;
        list = container[candidate] as unknown[];
        break;
      }
    }
    if (!key) {
      for (const [k, v] of Object.entries(container)) {
        if (Array.isArray(v) && v.length > 1) {
          key = k;
          list = v;
          break;
        }
      }
    }
  }

  if (list && list.length > 1) {
    const total = list.length;
    let kept = list;
    while (kept.length > 1 && text.length > limit) {
      kept = kept.slice(0, Math.ceil(kept.length / 2));
      const shaped = container && key ? { ...container, [key]: kept } : kept;
      text = stringify(shaped);
    }
    if (text.length <= limit) {
      return {
        text,
        truncated: true,
        note: `Showing ${kept.length} of ${total} items to stay within the response size limit. Use a smaller limit / page size or a narrower filter to see the rest.`
      };
    }
  }

  return {
    text: text.slice(0, limit - 200) + "\n…[truncated]",
    truncated: true,
    note: "The response was truncated to stay within the size limit. Narrow the request (filters, smaller limit, fewer columns)."
  };
}

export function jsonResult(data: unknown, structured = true): CallToolResult {
  const fitted = fitJson(data);
  const text = fitted.note ? `${fitted.text}\n\n_${fitted.note}_` : fitted.text;
  const result: CallToolResult = { content: [{ type: "text", text }] };
  if (structured && !fitted.truncated && data && typeof data === "object" && !Array.isArray(data)) {
    result.structuredContent = data as Record<string, unknown>;
  }
  return result;
}

export function textResult(text: string): CallToolResult {
  return { content: [{ type: "text", text: text.length > CHARACTER_LIMIT ? text.slice(0, CHARACTER_LIMIT - 20) + "\n…[truncated]" : text }] };
}

export function errorResult(message: string): CallToolResult {
  return { isError: true, content: [{ type: "text", text: message }] };
}

export function errorMessage(error: unknown): string {
  if (error instanceof Error) return error.message;
  return typeof error === "string" ? error : stringify(error);
}

/** Renders rows as a compact Markdown table (header from the first row's keys). */
export function markdownTable(rows: Array<Record<string, unknown>>, columns?: string[]): string {
  if (rows.length === 0) return "_No rows._";
  const cols = columns ?? Object.keys(rows[0] ?? {});
  const cell = (value: unknown): string => {
    if (value === null || value === undefined) return "";
    const text = typeof value === "object" ? JSON.stringify(value) : String(value);
    return text.replace(/\|/g, "\\|").replace(/\r?\n/g, " ");
  };
  const lines = [`| ${cols.join(" | ")} |`, `| ${cols.map(() => "---").join(" | ")} |`];
  for (const row of rows) lines.push(`| ${cols.map(col => cell(row[col])).join(" | ")} |`);
  return lines.join("\n");
}
