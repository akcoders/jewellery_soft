# ERP System Understanding

This document captures the current understanding of the `jewellery_soft` ERP backend, its main modules, and the major business flows implemented in the codebase.

## 1. System Purpose

`jewellery_soft` is a CodeIgniter 4 based ERP for jewellery manufacturing and retail operations. The system covers:

- lead and customer management
- order booking and repair orders
- manufacturing / jobwork execution
- karigar assignment and material issue / return
- gold, diamond, and stone inventory
- finished ornament receiving
- packing list and delivery challan generation
- labour billing and account tracking
- retail showroom and billing
- reports, KPI, and staff performance
- RBAC / user access control
- WhatsApp notifications for order events

The repo also contains a Flutter mobile app in `app_kit`, but this document focuses on the ERP/admin application.

## 2. Technical Shape

Core application:

- Framework: CodeIgniter 4
- Language: PHP 8.2+
- Main backend root: `app/`
- Routes: `app/Config/Routes.php`
- Auth and permission filters: `app/Config/Filters.php`
- Admin controllers: `app/Controllers/Admin`
- API controllers: `app/Controllers/Api`
- Models: `app/Models`
- Views: `app/Views/admin`
- Migrations: `app/Database/Migrations`
- Seeders: `app/Database/Seeds`
- Shared business logic: `app/Services`

General codebase pattern:

- controllers are the main orchestration layer
- models are mostly thin table wrappers
- transactions are used for critical create/update flows
- stock integrity lives in services and inventory-specific posting logic
- edit flows usually reverse old stock effect first, then reapply new effect

## 3. Main Functional Modules

### 3.1 Lead and Customer Management

Primary purpose:

- capture leads
- store followups, notes, and images
- convert leads into customers
- maintain customer identities used across orders and billing

Important backend areas:

- `Admin\LeadController`
- `Admin\CustomerController`
- customer, lead, followup, note, and address models

### 3.2 Orders and Manufacturing Control

Primary purpose:

- create fresh or repair orders
- store order items and commitments
- assign orders to karigars
- manage order status lifecycle
- handle followups and attachments
- receive finished work and generate documents

Important backend areas:

- `Admin\OrderController`
- `OrderModel`
- `OrderItemModel`
- `OrderFollowupModel`
- `OrderAttachmentModel`
- `OrderStatusHistoryModel`
- `OrderMaterialMovementModel`
- `OrderReceiveSummaryModel`
- `OrderReceiveDetailModel`

Configured order status flow:

- Confirmed
- In Production
- QC
- Ready
- Packed
- Dispatched
- Completed

The order module is one of the deepest modules in the repo and acts as a bridge between customer demand, manufacturing, material movement, labour costing, and final delivery.

### 3.3 Karigar and Jobwork

Primary purpose:

- maintain karigar master records
- assign manufacturing work
- track issued material by karigar
- track received material / ornament output
- calculate labour and outstanding amounts

Important backend areas:

- `Admin\KarigarController`
- karigar payment ledgers
- labour bill creation inside `OrderController`
- karigar summaries and performance reporting

### 3.4 Inventory

The system has both:

- a generic inventory engine
- newer, domain-specific inventory modules

#### Generic inventory layer

Purpose:

- warehouse, bin, location, category, product, stock adjustment, and transaction management

Important areas:

- `Admin\InventoryController`
- `InventoryTransactionModel`
- inventory balances and warehouse/bin models

#### Material-specific inventory modules

These are the current manufacturing-critical stock modules.

##### Gold inventory

- purchases
- issues
- returns
- adjustments
- stock summary
- ledger
- purity master
- product master

Important areas:

- `Admin\GoldInventory\*`
- `App\Services\GoldInventory\StockService`
- `GoldInventoryLedgerEntryModel`

Special note:

- gold logic is richer because it tracks gross/fine values and manufacturing-sensitive weight calculations

##### Diamond inventory

- item master
- purchases
- issues
- returns
- adjustments
- stock
- bag management

Important areas:

- `Admin\DiamondInventory\*`
- `Admin\DiamondBagController`
- `App\Services\DiamondInventory\StockService`
- `DiamondLedgerEntryModel`

##### Stone inventory

- item master
- purchases
- issues
- returns
- adjustments
- stock

Important areas:

- `Admin\StoneInventory\*`
- `App\Services\StoneInventory\StockService`
- `StoneLedgerEntryModel`

### 3.5 Issuement

Primary purpose:

- issue raw materials against orders / karigars
- support combined material issuance with voucher references

Important area:

- `Admin\IssuementController`

The code supports grouped issue voucher behavior across gold, diamond, and stone so a manufacturing issuance can be tracked as one operational action while still updating inventory ledgers correctly.

### 3.6 Receiving and Final Ornament Handling

Primary purpose:

- receive manufacturing output
- record final ornament details
- record wastage / material movement snapshots
- move work into QC / ready / packed flow
- produce packing list and delivery challan

Important areas:

- `OrderController::addReceive`
- `OrderController::generatePackingList`
- `OrderController::deliveryChallan`
- delivery challan and packing list models

### 3.7 Accounts

The accounts side now covers:

- purchase bill tracking
- labour bill tracking
- sale bills
- debit notes
- credit notes
- GST reporting
- outstanding summary
- accounts dashboard

Important areas:

- `Admin\AccountsController`
- purchase bill payment tables
- labour bill and labour bill payment tables
- debit note and credit note tables
- showroom sales and invoices
- customer receipts

### 3.8 Reports

Important reports currently present:

- gold ledger
- diamond ledger
- karigar performance
- inventory reports
- staff and hierarchy reports
- all orders analysis
- combined transactions report

Important area:

- `Admin\ReportController`

The combined transactions report is designed as a wide operational report that brings together order, inventory, and account movements with strong filters.

### 3.9 Retail Showroom

Purpose:

- showroom master and counters
- staff assignment
- showroom stock
- transfers and allocations
- reservations
- retail billing / sales

Important areas:

- `Admin\ShowroomController`
- `Admin\ShowroomCounterController`
- `Admin\ShowroomStaffController`
- `Admin\ShowroomStockController`
- `Admin\ShowroomSalesController`

This allows the ERP to support both manufacturing operations and retail front-end sales.

### 3.10 Performance and KPI

Purpose:

- KPI definitions
- employee targets
- incentive rules
- performance dashboard

Important area:

- `Admin\PerformanceController`

### 3.11 Access Control

Purpose:

- role master
- permission master
- user-role mapping
- per-user access governance

Important areas:

- `Admin\Access\RolesController`
- `Admin\Access\PermissionsController`
- `Admin\Access\UsersController`

The route layer depends heavily on permission filters, and the sidebar is permission-aware.

### 3.12 Company Settings and Notifications

Purpose:

- company profile fields
- GST/company metadata
- WhatsApp API settings
- message templates and event toggles

Important areas:

- `Admin\CompanySettingsController`
- `CompanySettingModel`
- `OrderWhatsAppService`
- `DispatchOrderWhatsappAlerts` command

## 4. Core Business Flow

### 4.1 Customer to Order

1. Lead or customer is created.
2. Order is created with order items.
3. Job card / related order context is generated and tracked.
4. Initial order status is stored and history starts.

### 4.2 Order to Manufacturing

1. Order is assigned to a karigar.
2. Required material is issued:
   - gold
   - diamond
   - stone
3. Stock reduces from warehouse / inventory ledgers.
4. Karigar-linked movement history is stored.

Key rule in code:

- material issue is blocked unless the order is active and assigned correctly

### 4.3 Manufacturing to Receive

1. Material or final work is returned / received.
2. Receive summary and receive details are saved.
3. Labour amount may be computed from gold weight and karigar rate.
4. Labour bill and karigar ledger entries can be generated.
5. Order status can progress further toward QC / ready.

### 4.4 Ready to Delivery

1. QC status is captured.
2. Packing list is created.
3. Delivery challan is created.
4. Final ornament detail pages and printable documents are available.
5. In retail or final billing flow, invoice and receipt records are created.

## 5. Inventory Logic Understanding

The most important recurring stock logic pattern in the ERP is:

1. validate movement request
2. use DB transaction
3. reverse previous movement effect on edit, if needed
4. apply new movement effect
5. update stock summary / balance tables
6. write ledger / movement history rows

This pattern is visible especially in:

- gold inventory
- diamond inventory
- stone inventory
- order-linked material movement

Implication for future work:

- any new feature that edits existing material transactions must preserve reversal-first logic
- direct table updates without stock service integration will likely corrupt balances

## 6. Accounts Logic Understanding

Current account logic is operationally centered rather than full ERP-double-entry-only UI:

- purchase bills derive from purchase headers and lines
- payments are stored separately in purchase bill payment tables
- labour bills derive from karigar / receive flows
- sales receivables derive from showroom sales + invoices + customer receipts
- debit and credit notes are now separate structured registers
- GST reporting derives from invoice and purchase tax fields plus note adjustments

Important practical rule:

- many account reports are aggregations over operational tables, not isolated “accounting-only” documents

## 7. Reporting Logic Understanding

Reporting is driven mostly by query composition inside controllers rather than by a separate reporting service layer.

This means:

- new filters are usually added at controller query level
- combined reports often merge data from multiple modules into normalized arrays
- report work requires awareness of optional tables and backward-compatible fallbacks

## 8. WhatsApp Integration Understanding

The WhatsApp integration currently supports dynamic configuration from company settings and event-driven sends for:

- order created
- order status updated
- order ready
- over-budget condition
- delayed order daily alerts

Key design:

- API details are configurable from admin settings
- logs are stored in a message log table
- daily delayed notifications are executed through a Spark command

## 9. Demo Data and Environment Understanding

The repo now includes:

- `CompleteSystemDemoSeeder`
- migration-based DB updates
- documented update flow in `docs/DB_UPDATE_AND_DEMO.md`

Purpose of the complete seeder:

- bring up a meaningful demo environment quickly
- give localhost and server the same bootstrap path
- include customer, vendor, karigar, order, invoice, showroom, and accounts sample data

## 10. Important Developer Guidance

When adding or modifying features in this ERP, keep these constraints in mind:

- preserve transaction boundaries in critical save flows
- avoid bypassing stock services for material logic
- preserve permission filters in routes
- many screens expect tables to exist conditionally, so check table existence when adding cross-module reports
- maintain backward compatibility with older generic inventory data where the code already supports fallback
- notes, ledgers, and status history are important for auditability in this system

## 11. Current High-Level Mental Model

The ERP can be understood as three tightly connected layers:

1. Demand and execution layer
   - leads, customers, orders, karigars, followups, jobwork

2. Stock and production layer
   - gold, diamond, stone, issue, return, receive, ornament movement

3. Commercial and control layer
   - showroom, invoices, receipts, accounts, GST, reports, KPI, RBAC, notifications

That is the current working understanding of the backend ERP flow and its main code responsibilities.
