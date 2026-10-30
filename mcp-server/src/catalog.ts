/**
 * Catalogue of the ERP mobile API (api/mobile/*). Used by erp_api_catalog, the
 * erp://api/catalog resource and the generic erp_api_get / erp_api_post tools.
 * Paths use {id}-style placeholders.
 */
export interface Endpoint {
  method: "GET" | "POST";
  path: string;
  group: string;
  summary: string;
  params?: string[];
  body?: string[];
}

export const ENDPOINTS: readonly Endpoint[] = [
  { method: "GET", path: "api/mobile/me", group: "account", summary: "Current ERP user profile and role codes" },
  { method: "GET", path: "api/mobile/notification-sounds", group: "account", summary: "Bundled notification sounds" },
  { method: "POST", path: "api/mobile/notification-sound", group: "account", summary: "Change notification sound", body: ["sound"] },

  { method: "GET", path: "api/mobile/orders", group: "orders", summary: "List orders (paginated, role filtered)", params: ["q", "status", "scope=followups", "page", "limit"] },
  { method: "POST", path: "api/mobile/orders", group: "orders", summary: "Create a fresh or repair order", body: ["order_name", "customer_id", "order_type", "order_category_id", "material_category", "description", "due_date", "priority", "karigar_id", "followup_assigned_to", "followup_due_at", "items", "gold_rate_per_gm", "advance_amount", "approximate_price", "attachments", "parent_order_id", "repair_*"] },
  { method: "GET", path: "api/mobile/orders-form-options", group: "orders", summary: "Dropdown options for the order form (customers, karigars, categories, staff)" },
  { method: "GET", path: "api/mobile/orders/{id}", group: "orders", summary: "Order detail with items, attachments and follow-up history" },
  { method: "GET", path: "api/mobile/orders/{id}/followups", group: "orders", summary: "Follow-up timeline of an order" },
  { method: "POST", path: "api/mobile/orders/{id}/followups", group: "orders", summary: "Add a follow-up entry", body: ["stage", "description", "next_followup_date", "image_base64", "image_source", "same_for_sub_orders", "child_followups"] },
  { method: "POST", path: "api/mobile/orders/{id}/rating", group: "orders", summary: "Rate a completed order", body: ["rating", "comment"] },
  { method: "GET", path: "api/mobile/orders/{id}/receive", group: "orders", summary: "Receiving form (expected weights, diamonds, stones)" },
  { method: "POST", path: "api/mobile/orders/{id}/receive", group: "orders", summary: "Receive finished jewellery from the karigar", body: ["received weights/items as returned by the receive form"] },
  { method: "POST", path: "api/mobile/orders/{id}/follower", group: "orders", summary: "Change the order follower", body: ["followup_assigned_to", "followup_due_at"] },
  { method: "POST", path: "api/mobile/orders/{id}/assign", group: "orders", summary: "Assign a karigar to an order", body: ["karigar_id", "customer_id", "followup_assigned_to", "followup_due_at", "repair_issue_weight_gm"] },
  { method: "GET", path: "api/mobile/order-work-requests", group: "orders", summary: "Work requests raised on orders", params: ["order_id"] },
  { method: "POST", path: "api/mobile/orders/{id}/work-requests", group: "orders", summary: "Raise a work request (gold requirement, diamond bag, delivery date, other)", body: ["request_type", "details", "requested_due_at", "gold_quantity_gm"] },
  { method: "POST", path: "api/mobile/order-work-requests/{id}/review", group: "orders", summary: "Approve or reject a work request", body: ["decision", "note"] },
  { method: "POST", path: "api/mobile/orders/{id}/diamond-requirements", group: "diamonds", summary: "Raise a diamond requirement for an order", body: ["items", "notes"] },

  { method: "GET", path: "api/mobile/delivery-challans", group: "documents", summary: "List delivery challans" },
  { method: "GET", path: "api/mobile/delivery-challans/create", group: "documents", summary: "Delivery challan form data" },
  { method: "POST", path: "api/mobile/delivery-challans", group: "documents", summary: "Create a delivery challan", body: ["customer_name", "order ids / lines as in the create form"] },
  { method: "GET", path: "api/mobile/delivery-challans/{id}/pdf", group: "documents", summary: "Delivery challan PDF (binary)" },
  { method: "GET", path: "api/mobile/packing-lists", group: "documents", summary: "Orders available for packing lists" },
  { method: "POST", path: "api/mobile/packing-lists/pdf", group: "documents", summary: "Packing list PDF for orders (binary)", body: ["order_ids"] },

  { method: "GET", path: "api/mobile/tasks", group: "tasks", summary: "Tasks assigned to the current staff user", params: ["q"] },
  { method: "POST", path: "api/mobile/tasks/{id}/complete", group: "tasks", summary: "Complete an assigned task with proof", body: ["proof_base64", "proof_note"] },
  { method: "GET", path: "api/mobile/admin-tasks", group: "tasks", summary: "Tasks created by admins" },
  { method: "POST", path: "api/mobile/admin-tasks", group: "tasks", summary: "Create a staff task", body: ["admin_user_id", "title", "note", "priority", "scheduled_at"] },
  { method: "POST", path: "api/mobile/admin-tasks/{id}/cancel", group: "tasks", summary: "Cancel a staff task" },

  { method: "GET", path: "api/mobile/performance", group: "performance", summary: "Own performance score", params: ["month", "year"] },
  { method: "GET", path: "api/mobile/performance/team", group: "performance", summary: "Team performance (admins)", params: ["month", "year"] },
  { method: "GET", path: "api/mobile/performance/team/{id}", group: "performance", summary: "One team member's performance", params: ["month", "year"] },
  { method: "GET", path: "api/mobile/analytics", group: "performance", summary: "Business analytics summary", params: ["days"] },

  { method: "GET", path: "api/mobile/notifications", group: "notifications", summary: "Notification inbox" },
  { method: "GET", path: "api/mobile/notifications/status", group: "notifications", summary: "Unread / pending notification counts" },
  { method: "POST", path: "api/mobile/notifications/{id}/done", group: "notifications", summary: "Mark a notification as done" },
  { method: "GET", path: "api/mobile/app-update", group: "notifications", summary: "App update availability", params: ["platform", "version_code"] },

  { method: "GET", path: "api/mobile/approval-requests", group: "approvals", summary: "Pending approval requests (customers, karigars, follow-ups)" },
  { method: "POST", path: "api/mobile/approval-requests/customers", group: "approvals", summary: "Request approval to add a customer", body: ["name", "phone", "email", "address", "city", "state", "pincode", "country", "gstin", "attrai_number", "notes"] },
  { method: "POST", path: "api/mobile/approval-requests/karigars", group: "approvals", summary: "Request approval to add a karigar", body: ["name", "phone", "department", "skills_text", "rate_per_gm", "wastage_percentage", "notes"] },
  { method: "POST", path: "api/mobile/approval-requests/{id}/review", group: "approvals", summary: "Approve / reject an approval request", body: ["decision=approve|approve_no_point|reject", "note"] },
  { method: "GET", path: "api/mobile/approval-staff-settings", group: "approvals", summary: "Per-staff approval settings" },
  { method: "POST", path: "api/mobile/approval-staff-settings/{id}", group: "approvals", summary: "Update a staff member's approval settings" },

  { method: "GET", path: "api/mobile/inventory/summary", group: "inventory", summary: "Gold, diamond and stone stock summary" },
  { method: "GET", path: "api/mobile/inventory/diamonds", group: "inventory", summary: "Diamond stock by item/size", params: ["q"] },
  { method: "GET", path: "api/mobile/inventory/gold", group: "inventory", summary: "Gold stock by purity/item", params: ["q"] },
  { method: "GET", path: "api/mobile/inventory/stones", group: "inventory", summary: "Stone stock", params: ["q"] },
  { method: "GET", path: "api/mobile/issuements", group: "inventory", summary: "Combined issuements (gold + diamond + stone to karigar)", params: ["q"] },
  { method: "GET", path: "api/mobile/issuements/detail", group: "inventory", summary: "Combined issuement detail", params: ["voucher / ids as listed"] },
  { method: "POST", path: "api/mobile/issuements", group: "inventory", summary: "Create a combined issuement to a karigar", body: ["karigar_id", "order_id", "gold lines", "diamond lines", "stone lines"] },

  { method: "GET", path: "api/mobile/diamond/issues", group: "diamonds", summary: "Diamond issues", params: ["q"] },
  { method: "GET", path: "api/mobile/diamond/returns", group: "diamonds", summary: "Diamond returns", params: ["q"] },
  { method: "GET", path: "api/mobile/diamond/purchases", group: "diamonds", summary: "Diamond purchases", params: ["q"] },
  { method: "GET", path: "api/mobile/diamond/issues/{id}", group: "diamonds", summary: "Diamond issue detail" },
  { method: "GET", path: "api/mobile/diamond/returns/{id}", group: "diamonds", summary: "Diamond return detail" },
  { method: "GET", path: "api/mobile/diamond/purchases/{id}", group: "diamonds", summary: "Diamond purchase detail" },
  { method: "POST", path: "api/mobile/diamond/purchases", group: "diamonds", summary: "Record a diamond purchase", body: ["vendor_id", "lines", "..."] },
  { method: "POST", path: "api/mobile/diamond/issues", group: "diamonds", summary: "Issue diamonds to a karigar", body: ["karigar_id", "order_id", "lines"] },
  { method: "POST", path: "api/mobile/diamond/returns", group: "diamonds", summary: "Record a diamond return", body: ["issue_id", "lines"] },
  { method: "GET", path: "api/mobile/diamond-bags", group: "diamonds", summary: "Diamond bags (chalni)", params: ["order_id", "work_request_id"] },
  { method: "GET", path: "api/mobile/diamond-bags/create", group: "diamonds", summary: "Diamond bag form data" },
  { method: "POST", path: "api/mobile/diamond-bags", group: "diamonds", summary: "Create a diamond bag", body: ["order_id", "work_request_id", "location_id", "prepared_date", "notes", "rows"] },
  { method: "POST", path: "api/mobile/diamond-bags/sizes", group: "diamonds", summary: "Add a diamond size master", body: ["shape_id", "chalni_group_id", "size_label"] },
  { method: "GET", path: "api/mobile/diamond-bags/{id}", group: "diamonds", summary: "Diamond bag detail" },
  { method: "POST", path: "api/mobile/diamond-bags/{id}/issue", group: "diamonds", summary: "Issue a diamond bag to a karigar" },
  { method: "GET", path: "api/mobile/diamond-requirements", group: "diamonds", summary: "Diamond requirements raised on orders" },
  { method: "GET", path: "api/mobile/diamond-requirements/{id}", group: "diamonds", summary: "Diamond requirement detail" },
  { method: "POST", path: "api/mobile/diamond-requirements/{id}/prepare", group: "diamonds", summary: "Prepare a diamond requirement (bag)", body: ["location_id", "prepared_date", "notes", "rows"] },

  { method: "GET", path: "api/mobile/gold/issues", group: "gold", summary: "Gold issues", params: ["q"] },
  { method: "GET", path: "api/mobile/gold/returns", group: "gold", summary: "Gold returns", params: ["q"] },
  { method: "GET", path: "api/mobile/gold/purchases", group: "gold", summary: "Gold purchases", params: ["q"] },
  { method: "GET", path: "api/mobile/gold/issues/{id}", group: "gold", summary: "Gold issue detail" },
  { method: "GET", path: "api/mobile/gold/returns/{id}", group: "gold", summary: "Gold return detail" },
  { method: "GET", path: "api/mobile/gold/purchases/{id}", group: "gold", summary: "Gold purchase detail" },
  { method: "POST", path: "api/mobile/gold/purchases", group: "gold", summary: "Record a gold purchase" },
  { method: "POST", path: "api/mobile/gold/issues", group: "gold", summary: "Issue gold to a karigar" },
  { method: "POST", path: "api/mobile/gold/returns", group: "gold", summary: "Record a gold return" },

  { method: "GET", path: "api/mobile/stone/issues", group: "stones", summary: "Stone issues", params: ["q"] },
  { method: "GET", path: "api/mobile/stone/returns", group: "stones", summary: "Stone returns", params: ["q"] },
  { method: "GET", path: "api/mobile/stone/purchases", group: "stones", summary: "Stone purchases", params: ["q"] },
  { method: "GET", path: "api/mobile/stone/issues/{id}", group: "stones", summary: "Stone issue detail" },
  { method: "GET", path: "api/mobile/stone/returns/{id}", group: "stones", summary: "Stone return detail" },
  { method: "GET", path: "api/mobile/stone/purchases/{id}", group: "stones", summary: "Stone purchase detail" },
  { method: "POST", path: "api/mobile/stone/purchases", group: "stones", summary: "Record a stone purchase" },
  { method: "POST", path: "api/mobile/stone/issues", group: "stones", summary: "Issue stones to a karigar" },
  { method: "POST", path: "api/mobile/stone/returns", group: "stones", summary: "Record a stone return" },

  { method: "GET", path: "api/mobile/lookups/karigars", group: "lookups", summary: "Active karigars" },
  { method: "GET", path: "api/mobile/lookups/vendors", group: "lookups", summary: "Vendors" },
  { method: "GET", path: "api/mobile/lookups/gst-masters", group: "lookups", summary: "GST rates" },
  { method: "GET", path: "api/mobile/lookups/locations", group: "lookups", summary: "Inventory locations" },
  { method: "GET", path: "api/mobile/lookups/diamond-items", group: "lookups", summary: "Diamond items" },
  { method: "GET", path: "api/mobile/lookups/diamond-bag-items", group: "lookups", summary: "Diamond bag items" },
  { method: "GET", path: "api/mobile/lookups/diamond-order-allocations", group: "lookups", summary: "Diamond allocations per order" },
  { method: "GET", path: "api/mobile/lookups/gold-items", group: "lookups", summary: "Gold items" },
  { method: "GET", path: "api/mobile/lookups/stone-items", group: "lookups", summary: "Stone items" },
  { method: "GET", path: "api/mobile/lookups/gold-purities", group: "lookups", summary: "Gold purities" },
  { method: "GET", path: "api/mobile/lookups/diamond-shapes", group: "lookups", summary: "Diamond shapes" },
  { method: "GET", path: "api/mobile/lookups/diamond-chalni-groups", group: "lookups", summary: "Diamond chalni (sieve) groups" },
  { method: "GET", path: "api/mobile/lookups/diamond-issues", group: "lookups", summary: "Open diamond issues (for returns)" },
  { method: "GET", path: "api/mobile/lookups/diamond-issues/{id}/lines", group: "lookups", summary: "Lines of a diamond issue" },
  { method: "GET", path: "api/mobile/lookups/gold-issues", group: "lookups", summary: "Open gold issues (for returns)" },
  { method: "GET", path: "api/mobile/lookups/stone-issues", group: "lookups", summary: "Open stone issues (for returns)" }
];

export const LOOKUP_KINDS = [
  "karigars",
  "vendors",
  "gst-masters",
  "locations",
  "diamond-items",
  "diamond-bag-items",
  "diamond-order-allocations",
  "gold-items",
  "stone-items",
  "gold-purities",
  "diamond-shapes",
  "diamond-chalni-groups",
  "diamond-issues",
  "gold-issues",
  "stone-issues"
] as const;

export function searchCatalog(query: string, group?: string): Endpoint[] {
  const needle = query.trim().toLowerCase();
  return ENDPOINTS.filter(endpoint => {
    if (group && endpoint.group !== group) return false;
    if (needle === "") return true;
    const haystack = `${endpoint.method} ${endpoint.path} ${endpoint.group} ${endpoint.summary} ${(endpoint.params ?? []).join(" ")} ${(endpoint.body ?? []).join(" ")}`.toLowerCase();
    return needle.split(/\s+/).every(part => haystack.includes(part));
  });
}

/** True when a concrete path (ids filled in) matches a catalogued endpoint. */
export function catalogMatches(method: "GET" | "POST", concretePath: string): Endpoint | undefined {
  const clean = concretePath.replace(/^\/+/, "");
  return ENDPOINTS.find(endpoint => {
    if (endpoint.method !== method) return false;
    const pattern = "^" + endpoint.path.replace(/[.*+?^${}()|[\]\\]/g, "\\$&").replace(/\\\{id\\\}/g, "\\d+") + "$";
    return new RegExp(pattern).test(clean);
  });
}
