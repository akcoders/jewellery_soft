/** Read-only tools backed by the ERP mobile API. */
import type { McpServer } from "@modelcontextprotocol/server";
import * as z from "zod/v4";
import type { Principal } from "../auth.js";
import { LOOKUP_KINDS } from "../catalog.js";
import { erpRequest, type QueryParams } from "../erp.js";
import { CHARACTER_LIMIT, jsonResult, textResult } from "../format.js";
import { idArg, pageArgs, READ_ONLY, registerErpTool, responseFormatArg } from "./registry.js";

interface OrderRow {
  id: number;
  order_no?: string;
  order_name?: string;
  status?: string;
  receiving_status?: string;
  priority?: string;
  due_date?: string;
  order_type?: string;
  customer_name?: string;
  karigar_name?: string;
  follower_name?: string;
  order_category_name?: string;
  followup_due_at?: string;
  last_followup_stage?: string;
  last_followup_on?: string;
  created_at?: string;
}

interface OrdersPage {
  items?: OrderRow[];
  pagination?: { page?: number; limit?: number; total?: number; total_pages?: number };
}

function orderLine(order: OrderRow): string {
  const parts = [
    `- **${order.order_no ?? `#${order.id}`}** (id ${order.id})${order.order_name ? ` ${order.order_name}` : ""}`,
    `status: ${order.status ?? "?"}`,
    order.customer_name ? `customer: ${order.customer_name}` : "",
    order.karigar_name ? `karigar: ${order.karigar_name}` : "karigar: unassigned",
    order.follower_name ? `follower: ${order.follower_name}` : "",
    order.due_date ? `due: ${order.due_date}` : "",
    order.priority ? `priority: ${order.priority}` : "",
    order.followup_due_at ? `next follow-up: ${order.followup_due_at}` : ""
  ].filter(part => part !== "");
  return parts.join(" · ");
}

export function registerReadTools(server: McpServer, principal: Principal): void {
  registerErpTool(
    server,
    principal,
    {
      name: "erp_whoami",
      title: "Who am I",
      description: "Returns the ERP user this API key acts as (name, email, role codes, admin flag). Call it first to learn what the key can do.",
      scope: "read",
      input: z.strictObject({}),
      annotations: READ_ONLY
    },
    async (_args, ctx) => jsonResult({ key: principal.keyName, scopes: principal.scopes, erp_user: await erpRequest(principal, "GET", "api/mobile/me", { signal: ctx.mcpReq.signal }) })
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_list_orders",
      title: "List orders",
      description: `List jewellery orders with their status, customer, karigar, follower and due date.

Filters: q (matches order no, order name, customer, karigar or follower), status (exact ERP status such as "Pending", "In Progress", "Ready", "Completed"), scope="followups" (only open orders with a scheduled follow-up). Paginated; the result says how to get the next page.
Use erp_get_order for full detail of one order.`,
      scope: "read",
      input: z.strictObject({
        q: z.string().max(120).optional().describe("Search text (order no, order name, customer, karigar, follower)"),
        status: z.string().max(40).optional().describe("Exact order status to filter by"),
        scope: z.enum(["all", "followups"]).default("all").describe("'followups' limits to open orders that have a follow-up scheduled"),
        ...pageArgs,
        response_format: responseFormatArg
      }),
      annotations: READ_ONLY
    },
    async ({ q, status, scope, page, limit, response_format }, ctx) => {
      const data = (await erpRequest(principal, "GET", "api/mobile/orders", {
        query: { q, status, scope: scope === "followups" ? "followups" : undefined, page, limit },
        signal: ctx.mcpReq.signal
      })) as OrdersPage;
      if (response_format === "json") return jsonResult(data);

      const items = data.items ?? [];
      const pagination = data.pagination ?? {};
      if (items.length === 0) return textResult(`No orders match${q ? ` '${q}'` : ""}${status ? ` with status '${status}'` : ""}.`);
      const lines = [`# Orders (page ${pagination.page ?? page} of ${pagination.total_pages ?? "?"}, ${pagination.total ?? items.length} total)`, ""];
      let shown = items;
      let text = "";
      do {
        text = [...lines, ...shown.map(orderLine)].join("\n");
        if (text.length > CHARACTER_LIMIT && shown.length > 1) shown = shown.slice(0, Math.ceil(shown.length / 2));
        else break;
      } while (true);
      if (shown.length < items.length) text += `\n\n_Showing ${shown.length} of ${items.length} items on this page; use a smaller limit._`;
      if ((pagination.total_pages ?? 1) > (pagination.page ?? page)) text += `\n\nMore results: call again with page=${(pagination.page ?? page) + 1}.`;
      return textResult(text);
    }
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_get_order",
      title: "Get order",
      description: "Full detail of one order: header, customer, karigar, items (gold/diamond/stone specs), attachments, follow-up history and receiving status. Use erp_list_orders to find order ids.",
      scope: "read",
      input: z.strictObject({ order_id: idArg("Order") }),
      annotations: READ_ONLY
    },
    async ({ order_id }, ctx) => jsonResult(await erpRequest(principal, "GET", `api/mobile/orders/${order_id}`, { signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_list_order_followups",
      title: "Order follow-ups",
      description: "Follow-up timeline of an order (stage, note, who, when, next follow-up date).",
      scope: "read",
      input: z.strictObject({ order_id: idArg("Order") }),
      annotations: READ_ONLY
    },
    async ({ order_id }, ctx) => jsonResult(await erpRequest(principal, "GET", `api/mobile/orders/${order_id}/followups`, { signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_order_form_options",
      title: "Order form options",
      description: "Dropdown data needed to create or assign orders: customers, karigars, order categories, staff followers, material categories, priorities. Call before erp_create_order or erp_assign_karigar to pick valid ids.",
      scope: "read",
      input: z.strictObject({}),
      annotations: READ_ONLY
    },
    async (_args, ctx) => jsonResult(await erpRequest(principal, "GET", "api/mobile/orders-form-options", { signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_get_order_receive_form",
      title: "Order receive form",
      description: "Data required to receive finished jewellery for an order (issued gold/diamond/stone, expected weights). Use its field names when calling erp_receive_order.",
      scope: "read",
      input: z.strictObject({ order_id: idArg("Order") }),
      annotations: READ_ONLY
    },
    async ({ order_id }, ctx) => jsonResult(await erpRequest(principal, "GET", `api/mobile/orders/${order_id}/receive`, { signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_inventory_summary",
      title: "Inventory summary",
      description: "Current gold, diamond and stone stock totals (in stock vs issued to karigars).",
      scope: "read",
      input: z.strictObject({}),
      annotations: READ_ONLY
    },
    async (_args, ctx) => jsonResult(await erpRequest(principal, "GET", "api/mobile/inventory/summary", { signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_list_inventory",
      title: "List inventory",
      description: "Stock lines for one material: diamonds (by item/shape/size), gold (by purity/item) or stones. Optional text search.",
      scope: "read",
      input: z.strictObject({
        material: z.enum(["diamonds", "gold", "stones"]).describe("Which stock list to return"),
        q: z.string().max(120).optional().describe("Search text")
      }),
      annotations: READ_ONLY
    },
    async ({ material, q }, ctx) => jsonResult(await erpRequest(principal, "GET", `api/mobile/inventory/${material}`, { query: { q }, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_list_material_transactions",
      title: "List material transactions",
      description: "Issues, returns or purchases for diamond, gold or stone inventory (e.g. all gold issues to karigars). Optional search text. Use erp_get_material_transaction for one document.",
      scope: "read",
      input: z.strictObject({
        material: z.enum(["diamond", "gold", "stone"]),
        kind: z.enum(["issues", "returns", "purchases"]),
        q: z.string().max(120).optional().describe("Search text (voucher no, karigar, vendor)")
      }),
      annotations: READ_ONLY
    },
    async ({ material, kind, q }, ctx) => jsonResult(await erpRequest(principal, "GET", `api/mobile/${material}/${kind}`, { query: { q }, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_get_material_transaction",
      title: "Get material transaction",
      description: "Detail of one diamond/gold/stone issue, return or purchase (header + lines).",
      scope: "read",
      input: z.strictObject({
        material: z.enum(["diamond", "gold", "stone"]),
        kind: z.enum(["issues", "returns", "purchases"]),
        id: idArg("Transaction")
      }),
      annotations: READ_ONLY
    },
    async ({ material, kind, id }, ctx) => jsonResult(await erpRequest(principal, "GET", `api/mobile/${material}/${kind}/${id}`, { signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_list_issuements",
      title: "List issuements",
      description: "Combined issuements (gold + diamond + stone handed to a karigar in one voucher). Optional search text.",
      scope: "read",
      input: z.strictObject({ q: z.string().max(120).optional().describe("Search text") }),
      annotations: READ_ONLY
    },
    async ({ q }, ctx) => jsonResult(await erpRequest(principal, "GET", "api/mobile/issuements", { query: { q }, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_lookup",
      title: "Lookup master data",
      description: `Master lists used across the ERP: ${LOOKUP_KINDS.join(", ")}. Returns ids and names to use in other tools (e.g. karigar_id, location_id, vendor_id). Extra query parameters (such as order_id) can be passed in 'query'.`,
      scope: "read",
      input: z.strictObject({
        kind: z.enum(LOOKUP_KINDS),
        query: z.record(z.string(), z.union([z.string(), z.number(), z.boolean()])).optional().describe("Optional query-string parameters")
      }),
      annotations: READ_ONLY
    },
    async ({ kind, query }, ctx) => jsonResult(await erpRequest(principal, "GET", `api/mobile/lookups/${kind}`, { query: query as QueryParams | undefined, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_list_tasks",
      title: "List my tasks",
      description: "Tasks assigned to the ERP user behind this key (staff view). Admin users should use erp_list_admin_tasks.",
      scope: "read",
      input: z.strictObject({ q: z.string().max(120).optional().describe("Search text") }),
      annotations: READ_ONLY
    },
    async ({ q }, ctx) => jsonResult(await erpRequest(principal, "GET", "api/mobile/tasks", { query: { q }, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_list_admin_tasks",
      title: "List staff tasks (admin)",
      description: "Tasks that admins created for staff, with status and due time. Requires task-management permission in the ERP.",
      scope: "read",
      input: z.strictObject({}),
      annotations: READ_ONLY
    },
    async (_args, ctx) => jsonResult(await erpRequest(principal, "GET", "api/mobile/admin-tasks", { signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_performance",
      title: "Staff performance",
      description: "Performance scores. target='me' for the current user, 'team' for every staff member (admin), or a staff user id for one member. Optional month (1-12) and year.",
      scope: "read",
      input: z.strictObject({
        target: z.union([z.literal("me"), z.literal("team"), z.number().int().positive()]).default("me").describe("'me', 'team' or a staff admin_user id"),
        month: z.number().int().min(1).max(12).optional(),
        year: z.number().int().min(2020).max(2100).optional()
      }),
      annotations: READ_ONLY
    },
    async ({ target, month, year }, ctx) => {
      const path = target === "me" ? "api/mobile/performance" : target === "team" ? "api/mobile/performance/team" : `api/mobile/performance/team/${target}`;
      return jsonResult(await erpRequest(principal, "GET", path, { query: { month, year }, signal: ctx.mcpReq.signal }));
    }
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_analytics",
      title: "Business analytics",
      description: "Business analytics summary for the last N days (orders, completions, delays, sales signals).",
      scope: "read",
      input: z.strictObject({ days: z.number().int().min(1).max(365).default(30).describe("Window in days") }),
      annotations: READ_ONLY
    },
    async ({ days }, ctx) => jsonResult(await erpRequest(principal, "GET", "api/mobile/analytics", { query: { days }, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_list_notifications",
      title: "Notifications",
      description: "Notification inbox of the ERP user, or just the unread/pending counts with status_only=true.",
      scope: "read",
      input: z.strictObject({ status_only: z.boolean().default(false).describe("true returns only counts") }),
      annotations: READ_ONLY
    },
    async ({ status_only }, ctx) => jsonResult(await erpRequest(principal, "GET", status_only ? "api/mobile/notifications/status" : "api/mobile/notifications", { signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_list_approval_requests",
      title: "Approval requests",
      description: "Pending approval requests (new customers, new karigars, follow-up approvals) with their ids for erp_review_approval_request.",
      scope: "read",
      input: z.strictObject({}),
      annotations: READ_ONLY
    },
    async (_args, ctx) => jsonResult(await erpRequest(principal, "GET", "api/mobile/approval-requests", { signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_list_work_requests",
      title: "Order work requests",
      description: "Work requests raised on orders (gold requirement, diamond bag, delivery date change, other), optionally for one order.",
      scope: "read",
      input: z.strictObject({ order_id: z.number().int().positive().optional() }),
      annotations: READ_ONLY
    },
    async ({ order_id }, ctx) => jsonResult(await erpRequest(principal, "GET", "api/mobile/order-work-requests", { query: { order_id }, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_list_documents",
      title: "Delivery challans / packing lists",
      description: "kind='delivery-challans' lists delivery challans; kind='packing-lists' lists orders available for packing lists.",
      scope: "read",
      input: z.strictObject({ kind: z.enum(["delivery-challans", "packing-lists"]) }),
      annotations: READ_ONLY
    },
    async ({ kind }, ctx) => jsonResult(await erpRequest(principal, "GET", `api/mobile/${kind}`, { signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_list_diamond_bags",
      title: "Diamond bags",
      description: "Diamond bags (chalni packets) prepared for orders, optionally filtered by order or work request. Use erp_get_diamond_bag for one bag.",
      scope: "read",
      input: z.strictObject({
        order_id: z.number().int().positive().optional(),
        work_request_id: z.number().int().positive().optional()
      }),
      annotations: READ_ONLY
    },
    async ({ order_id, work_request_id }, ctx) => jsonResult(await erpRequest(principal, "GET", "api/mobile/diamond-bags", { query: { order_id, work_request_id }, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_get_diamond_bag",
      title: "Get diamond bag",
      description: "Detail of one diamond bag with its size lines and issue status.",
      scope: "read",
      input: z.strictObject({ bag_id: idArg("Diamond bag") }),
      annotations: READ_ONLY
    },
    async ({ bag_id }, ctx) => jsonResult(await erpRequest(principal, "GET", `api/mobile/diamond-bags/${bag_id}`, { signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_list_diamond_requirements",
      title: "Diamond requirements",
      description: "Diamond requirements raised on orders. Pass requirement_id for one requirement's detail.",
      scope: "read",
      input: z.strictObject({ requirement_id: z.number().int().positive().optional() }),
      annotations: READ_ONLY
    },
    async ({ requirement_id }, ctx) =>
      jsonResult(await erpRequest(principal, "GET", requirement_id ? `api/mobile/diamond-requirements/${requirement_id}` : "api/mobile/diamond-requirements", { signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_api_get",
      title: "Generic ERP GET",
      description: `Call any GET endpoint of the ERP mobile API (api/mobile/...). Use erp_api_catalog to discover paths and query parameters. Prefer the dedicated erp_* tools when one exists; this tool covers everything else (e.g. "api/mobile/lookups/diamond-issues/12/lines").`,
      scope: "read",
      input: z.strictObject({
        path: z.string().min(1).max(200).describe("Path under the ERP, e.g. api/mobile/inventory/summary"),
        query: z.record(z.string(), z.union([z.string(), z.number(), z.boolean()])).optional().describe("Query-string parameters")
      }),
      annotations: READ_ONLY
    },
    async ({ path, query }, ctx) => jsonResult(await erpRequest(principal, "GET", path, { query: query as QueryParams | undefined, signal: ctx.mcpReq.signal }))
  );
}
