/**
 * Registration helper: scope check, audit trail and uniform error handling for
 * every tool. Tools only implement the happy path and throw on failure.
 */
import type { CallToolResult, McpServer, ServerContext } from "@modelcontextprotocol/server";
import * as z from "zod/v4";
import { recordAudit } from "../audit.js";
import { missingScopeMessage, type Principal } from "../auth.js";
import type { Scope } from "../config.js";
import { ErpError } from "../erp.js";
import { errorMessage, errorResult } from "../format.js";

export interface ToolAnnotationSet {
  readOnlyHint: boolean;
  destructiveHint: boolean;
  idempotentHint: boolean;
  openWorldHint: boolean;
}

export const READ_ONLY: ToolAnnotationSet = { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true };
export const WRITE: ToolAnnotationSet = { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: true };
export const DESTRUCTIVE: ToolAnnotationSet = { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true };

export type AnyObjectSchema = z.ZodObject<z.ZodRawShape>;

export interface ToolDef<S extends AnyObjectSchema> {
  name: string;
  title: string;
  description: string;
  scope: Scope;
  input: S;
  annotations: ToolAnnotationSet;
}

export type ToolRunner<S extends AnyObjectSchema> = (args: z.output<S>, ctx: ServerContext) => Promise<CallToolResult>;

export function registerErpTool<S extends AnyObjectSchema>(server: McpServer, principal: Principal, def: ToolDef<S>, run: ToolRunner<S>): void {
  server.registerTool(
    def.name,
    {
      title: def.title,
      description: def.description,
      inputSchema: def.input,
      annotations: def.annotations
    },
    (async (args: z.output<S>, ctx: ServerContext): Promise<CallToolResult> => {
      const denied = missingScopeMessage(principal, def.scope);
      if (denied) return errorResult(denied);

      const started = Date.now();
      try {
        const result = await run(args, ctx);
        void recordAudit(principal, def.name, !result.isError, Date.now() - started, result.isError ? firstText(result) : "ok");
        return result;
      } catch (error) {
        const message = error instanceof ErpError ? describeErpError(error) : errorMessage(error);
        void recordAudit(principal, def.name, false, Date.now() - started, message);
        return errorResult(message);
      }
    }) as never
  );
}

function firstText(result: CallToolResult): string {
  const block = result.content.find(item => item.type === "text");
  return block && block.type === "text" ? block.text : "error";
}

function describeErpError(error: ErpError): string {
  if (error.details === undefined || error.details === null) return error.message;
  return `${error.message}\nDetails: ${JSON.stringify(error.details)}`;
}

/** Common pagination arguments mirrored from the ERP mobile API. */
export const pageArgs = {
  page: z.number().int().min(1).default(1).describe("Page number (1-based)"),
  limit: z.number().int().min(1).max(100).default(20).describe("Items per page (max 100)")
};

export const idArg = (what: string) => z.number().int().positive().describe(`${what} id`);

export const responseFormatArg = z.enum(["markdown", "json"]).default("markdown").describe("'markdown' for a readable summary, 'json' for full structured data");

/** Free-form object passthrough for ERP payloads whose shape is documented in the description. */
export const payloadArg = (description: string) => z.record(z.string(), z.unknown()).describe(description);
