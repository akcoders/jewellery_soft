/** Write tools. Every write goes through the ERP API so ERP rules and permissions apply. */
import type { McpServer } from "@modelcontextprotocol/server";
import * as z from "zod/v4";
import type { Principal } from "../auth.js";
import { catalogMatches, ENDPOINTS } from "../catalog.js";
import { erpRequest, normalizeMobilePath } from "../erp.js";
import { jsonResult } from "../format.js";
import { DESTRUCTIVE, idArg, payloadArg, registerErpTool, WRITE } from "./registry.js";

const dateTimeArg = (what: string) => z.string().min(10).max(25).describe(`${what} as "YYYY-MM-DD HH:MM" (ERP local time, Asia/Kolkata)`);

export function registerWriteTools(server: McpServer, principal: Principal): void {
  registerErpTool(
    server,
    principal,
    {
      name: "erp_create_order",
      title: "Create order",
      description: `Create a new order. Call erp_order_form_options first for valid ids. The payload is sent as-is to POST api/mobile/orders.

Common fields: order_name, customer_id, order_type ("fresh" or "repair"), order_category_id, material_category, description, due_date (YYYY-MM-DD), priority, order_from, sales_person_user_id, gold_rate_per_gm, advance_amount, approximate_price, karigar_id, followup_assigned_to, followup_due_at, next_followup_date, items (array of item specs), attachments (base64 images), parent_order_id (for sub-orders), whatsapp_notify_order_created, repair_ornament_details / repair_work_details / repair_issue_weight_gm (repair orders).`,
      scope: "write",
      input: z.strictObject({ order: payloadArg("Order payload for POST api/mobile/orders") }),
      annotations: WRITE
    },
    async ({ order }, ctx) => jsonResult(await erpRequest(principal, "POST", "api/mobile/orders", { body: order, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_add_order_followup",
      title: "Add order follow-up",
      description: `Record a follow-up on an open order. Only the order's assigned follower (or an admin) may do this. 'stage' must be one of the ERP follow-up stages (see the order's followups or form options), 'description' is required, and 'next_followup_date' is required while the order stays open (future date/time).`,
      scope: "write",
      input: z.strictObject({
        order_id: idArg("Order"),
        stage: z.string().min(1).max(60).describe("Follow-up stage code/name accepted by the ERP"),
        description: z.string().min(1).max(2000).describe("What happened / what was agreed"),
        next_followup_date: dateTimeArg("Next follow-up").optional(),
        same_for_sub_orders: z.boolean().optional().describe("Apply the same follow-up to child orders"),
        extra: payloadArg("Any additional ERP fields (image_base64, image_source, child_followups)").optional()
      }),
      annotations: WRITE
    },
    async ({ order_id, extra, ...fields }, ctx) =>
      jsonResult(await erpRequest(principal, "POST", `api/mobile/orders/${order_id}/followups`, { body: { ...fields, ...(extra ?? {}) }, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_assign_karigar",
      title: "Assign karigar",
      description: "Assign an active karigar to an unassigned open order and set the follower and first follow-up time. Requires orders.assign permission. For repair orders pass repair_issue_weight_gm (old ornament gross weight).",
      scope: "write",
      input: z.strictObject({
        order_id: idArg("Order"),
        karigar_id: idArg("Karigar"),
        followup_assigned_to: idArg("Follower staff user"),
        followup_due_at: dateTimeArg("First follow-up time (future)"),
        customer_id: z.number().int().positive().optional().describe("Needed only when the order has no customer yet"),
        repair_issue_weight_gm: z.number().positive().optional()
      }),
      annotations: WRITE
    },
    async ({ order_id, ...body }, ctx) => jsonResult(await erpRequest(principal, "POST", `api/mobile/orders/${order_id}/assign`, { body, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_update_order_follower",
      title: "Change order follower",
      description: "Reassign the staff member who follows up an order, with the next follow-up time. Admin permission required.",
      scope: "write",
      input: z.strictObject({
        order_id: idArg("Order"),
        followup_assigned_to: idArg("New follower staff user"),
        followup_due_at: dateTimeArg("Next follow-up time (future)")
      }),
      annotations: WRITE
    },
    async ({ order_id, ...body }, ctx) => jsonResult(await erpRequest(principal, "POST", `api/mobile/orders/${order_id}/follower`, { body, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_receive_order",
      title: "Receive finished order",
      description: "Receive finished jewellery from the karigar. Call erp_get_order_receive_form first and send the weights/items using the field names it returns. This posts stock and ledger movements in the ERP.",
      scope: "write",
      input: z.strictObject({
        order_id: idArg("Order"),
        receipt: payloadArg("Receive payload (fields from erp_get_order_receive_form)")
      }),
      annotations: WRITE
    },
    async ({ order_id, receipt }, ctx) => jsonResult(await erpRequest(principal, "POST", `api/mobile/orders/${order_id}/receive`, { body: receipt, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_rate_order",
      title: "Rate order",
      description: "Rate a completed order from 1 to 5 with an optional comment.",
      scope: "write",
      input: z.strictObject({
        order_id: idArg("Order"),
        rating: z.number().int().min(1).max(5),
        comment: z.string().max(500).optional()
      }),
      annotations: WRITE
    },
    async ({ order_id, ...body }, ctx) => jsonResult(await erpRequest(principal, "POST", `api/mobile/orders/${order_id}/rating`, { body, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_create_work_request",
      title: "Raise work request",
      description: "Raise a request on an open order: gold_requirement (with gold_quantity_gm), diamond_bag, delivery_date (with requested_due_at) or other. Only the order's follower can raise it.",
      scope: "write",
      input: z.strictObject({
        order_id: idArg("Order"),
        request_type: z.string().min(1).max(40).describe("gold_requirement | diamond_bag | delivery_date | other (as accepted by the ERP)"),
        details: z.string().min(1).max(2000),
        requested_due_at: dateTimeArg("Requested delivery date").optional(),
        gold_quantity_gm: z.number().positive().optional()
      }),
      annotations: WRITE
    },
    async ({ order_id, ...body }, ctx) => jsonResult(await erpRequest(principal, "POST", `api/mobile/orders/${order_id}/work-requests`, { body, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_review_work_request",
      title: "Review work request",
      description: "Approve or reject an order work request (admin). decision is 'approve' or 'reject'; note is optional.",
      scope: "write",
      input: z.strictObject({
        request_id: idArg("Work request"),
        decision: z.enum(["approve", "reject"]),
        note: z.string().max(1000).optional(),
        extra: payloadArg("Additional ERP fields if the request type needs them").optional()
      }),
      annotations: WRITE
    },
    async ({ request_id, extra, ...body }, ctx) =>
      jsonResult(await erpRequest(principal, "POST", `api/mobile/order-work-requests/${request_id}/review`, { body: { ...body, ...(extra ?? {}) }, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_create_admin_task",
      title: "Create staff task",
      description: "Create a task for a staff member (admin). priority: low | normal | high | urgent. scheduled_at must be in the future.",
      scope: "write",
      input: z.strictObject({
        admin_user_id: idArg("Staff user"),
        title: z.string().min(1).max(160),
        scheduled_at: dateTimeArg("Due date and time (future)"),
        priority: z.enum(["low", "normal", "high", "urgent"]).default("normal"),
        note: z.string().max(2000).optional()
      }),
      annotations: WRITE
    },
    async (body, ctx) => jsonResult(await erpRequest(principal, "POST", "api/mobile/admin-tasks", { body, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_cancel_admin_task",
      title: "Cancel staff task",
      description: "Cancel an open staff task created by an admin.",
      scope: "write",
      input: z.strictObject({ task_id: idArg("Task") }),
      annotations: DESTRUCTIVE
    },
    async ({ task_id }, ctx) => jsonResult(await erpRequest(principal, "POST", `api/mobile/admin-tasks/${task_id}/cancel`, { body: {}, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_complete_task",
      title: "Complete my task",
      description: "Complete a task assigned to the current staff user. The ERP requires a proof image (base64) and accepts an optional note.",
      scope: "write",
      input: z.strictObject({
        task_id: idArg("Task"),
        proof_base64: z.string().min(1).describe("Base64 image proving completion"),
        proof_note: z.string().max(1000).optional()
      }),
      annotations: WRITE
    },
    async ({ task_id, ...body }, ctx) => jsonResult(await erpRequest(principal, "POST", `api/mobile/tasks/${task_id}/complete`, { body, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_review_approval_request",
      title: "Review approval request",
      description: "Approve or reject a pending approval request (admin). decision: approve | approve_no_point (follow-ups only) | reject.",
      scope: "write",
      input: z.strictObject({
        request_id: idArg("Approval request"),
        decision: z.enum(["approve", "approve_no_point", "reject"]),
        note: z.string().max(1000).optional()
      }),
      annotations: WRITE
    },
    async ({ request_id, ...body }, ctx) => jsonResult(await erpRequest(principal, "POST", `api/mobile/approval-requests/${request_id}/review`, { body, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_request_new_customer",
      title: "Request new customer",
      description: "Submit a new customer for admin approval (name and phone at least; optional email, address, city, state, pincode, country, gstin, attrai_number, notes).",
      scope: "write",
      input: z.strictObject({
        name: z.string().min(1).max(160),
        phone: z.string().min(5).max(30),
        email: z.string().max(120).optional(),
        address: z.string().max(500).optional(),
        city: z.string().max(80).optional(),
        state: z.string().max(80).optional(),
        pincode: z.string().max(20).optional(),
        country: z.string().max(80).optional(),
        gstin: z.string().max(30).optional(),
        attrai_number: z.string().max(30).optional(),
        notes: z.string().max(1000).optional()
      }),
      annotations: WRITE
    },
    async (body, ctx) => jsonResult(await erpRequest(principal, "POST", "api/mobile/approval-requests/customers", { body, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_request_new_karigar",
      title: "Request new karigar",
      description: "Submit a new karigar (artisan) for admin approval: name, phone, department, skills_text, rate_per_gm, wastage_percentage, notes.",
      scope: "write",
      input: z.strictObject({
        name: z.string().min(1).max(160),
        phone: z.string().min(5).max(30),
        department: z.string().max(80).optional(),
        skills_text: z.string().max(500).optional(),
        rate_per_gm: z.number().nonnegative().optional(),
        wastage_percentage: z.number().nonnegative().optional(),
        notes: z.string().max(1000).optional()
      }),
      annotations: WRITE
    },
    async (body, ctx) => jsonResult(await erpRequest(principal, "POST", "api/mobile/approval-requests/karigars", { body, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_create_issuement",
      title: "Create combined issuement",
      description: "Issue gold, diamonds and stones to a karigar in one voucher (POST api/mobile/issuements). Use erp_lookup for karigar/item/location ids and erp_list_issuements to see existing vouchers for the field layout.",
      scope: "write",
      input: z.strictObject({ issuement: payloadArg("Issuement payload (karigar_id, order_id, gold/diamond/stone lines)") }),
      annotations: WRITE
    },
    async ({ issuement }, ctx) => jsonResult(await erpRequest(principal, "POST", "api/mobile/issuements", { body: issuement, signal: ctx.mcpReq.signal }))
  );

  registerErpTool(
    server,
    principal,
    {
      name: "erp_api_post",
      title: "Generic ERP POST",
      description: `Call any POST endpoint of the ERP mobile API with a JSON body. Covers purchases, issues, returns, diamond bags, delivery challans, notification actions and everything not wrapped by a dedicated tool. Use erp_api_catalog to find the path and body fields. Known POST paths: ${ENDPOINTS.filter(e => e.method === "POST").map(e => e.path.replace("api/mobile/", "")).join(", ")}.`,
      scope: "write",
      input: z.strictObject({
        path: z.string().min(1).max(200).describe("Path under the ERP, e.g. api/mobile/gold/issues"),
        body: payloadArg("JSON body").default({})
      }),
      annotations: WRITE
    },
    async ({ path, body }, ctx) => {
      const clean = normalizeMobilePath(path);
      if (!catalogMatches("POST", clean)) {
        throw new Error(`"${clean}" is not a known POST endpoint of the ERP mobile API. Use erp_api_catalog to find the right path.`);
      }
      return jsonResult(await erpRequest(principal, "POST", clean, { body, signal: ctx.mcpReq.signal }));
    }
  );
}
