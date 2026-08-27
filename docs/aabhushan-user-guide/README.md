# Aabhushan Jewellery ERP

## Administrator & Operations User Guide

**Application:** Aabhushan Jewellery ERP<br>
**Portal:** `https://aabhushan.webignitors.in/public/admin`<br>
**Audience:** Administrators, sales staff, production teams, inventory teams, accounts teams, showroom staff and reporting users

---

<!--
SCREENSHOT PLACEHOLDER CONTRACT

Every screenshot token uses the form shown by the real page placeholders below and
refers to one entry in pages.json.
The page-id is the route after /admin/ converted to hyphen-separated text:
  /admin/dashboard                 -> dashboard
  /admin/orders/dashboard          -> orders-dashboard
  /admin/accounts                  -> accounts
  /admin/gold-inventory/purchases  -> gold-inventory-purchases

The guide builder must resolve the token through the matching pages.json entry's
"screenshot" field. Do not turn page ids into final image filenames here.
-->

## Welcome

Aabhushan Jewellery ERP brings customer orders, jewellery production, material inventory, showroom operations, accounts, staff performance and management controls into one workspace. This guide explains what is available on each main page, how to find information quickly, and which actions require additional access.

The screens shown in this guide were captured with a fully authorized administrator account. Your menu may contain fewer options. This is normal: Aabhushan only displays the areas permitted for your role.

## Quick start

The self-contained PDF includes a clean administrator-login screenshot followed by all 68 primary application pages. Raw screenshot files are generated locally and intentionally excluded from Git.

1. Open the admin portal and sign in with the email address and password provided by your administrator.
2. Use the left sidebar to choose a module. Select a menu with an arrow to expand its pages.
3. Use the page heading to confirm where you are. The active menu item remains highlighted.
4. Use filters above a register before searching a large date range.
5. Select **View**, the eye icon, or a linked order/reference number to open more detail.
6. Save only after reviewing dates, party names, quantities, weights, rates and uploaded documents.
7. When finished, open the profile menu at the top right and select **Log Out**.

## Understanding the interface

### Sidebar navigation

The sidebar is grouped by business function: Main, Customers & Orders, Production, Inventory, Showroom, Accounts, Reports and Admin. A whole group may be hidden if your role cannot view any page in that group.

### Registers and tables

Most list pages use a register. You can normally:

- use the table search box for a quick text search;
- select a column heading to sort it;
- move between result pages with the pagination controls;
- change the number of rows shown, where that option is available;
- open linked references, attachments or detail pages from the Actions column.

### Filters

Date, party, employee, karigar, status and material filters change only the visible results; they do not alter saved data. Select **Reset** to return to the full register. A blank date range generally means all available history.

### Export and documents

Some ledgers include **Export Excel**. Exported data reflects the active filters. PDF, print and download buttons open transaction documents such as invoices, vouchers, return receipts, labour bills, packing lists and delivery challans. Allow pop-ups for the portal if a document does not open.

### Access and action buttons

Being able to view a page does not always permit changes. Create, edit, delete, payment, status, assignment and configuration actions are granted separately. If an action is missing, disabled or returns an access message, contact the system administrator instead of sharing another user's login.

### Safe working practices

- Check the selected customer, vendor, karigar, showroom, warehouse and order before saving.
- Confirm whether a weight is in grams, fine grams, carats, pieces or general quantity.
- Never use stock adjustments to disguise an incorrect purchase, issue or return. Correct the source transaction where possible.
- Add a clear reason or remark to cancellations, adjustments, transfers, deliveries, payments and follow-ups.
- Do not refresh, go back or submit a form twice while a save is in progress.
- Do not delete a master or transaction that has already been used without checking its downstream effect.
- Download or print financial documents only when business policy permits it.
- Keep integration keys, customer details and employee information confidential.

## First-login guided tour

On the first successful login, Aabhushan can display an interactive tour. The tour introduces the sidebar, dashboard cards, module navigation, registers and profile menu.

- Select **Next** and **Back** to move through the tour.
- Select **Skip** to close the current tour without permanently opting out.
- Select **Never show again** only if you do not want the first-login tour to open on future logins or devices.
- Tour steps automatically skip menu items that your role cannot access.
- The tour does not save or change business data.

You can replay the guide at any time from the profile menu by selecting **Application Tour**, even after permanently hiding its automatic prompt.

---

# 1. Main Dashboard

## 1.1 Admin Dashboard

**Open:** Main → Dashboard<br>
**Address:** `/public/admin/dashboard`

{{SCREENSHOT:dashboard}}

The dashboard is the daily starting point for operational work.

**What you can see**

- orders that still need a karigar assignment;
- active customers, active orders and orders dispatched today;
- fine-gold stock, pending gold requirement and average pure-gold price;
- an assignment queue with order, customer/source, type, due date and priority;
- recent orders with current status.

**Key actions**

- Open any linked order or customer register.
- Select **View Queue** for unassigned production orders.
- Use **New Order** or **Add Customer** when your role permits it.

**Access note:** Viewing requires Dashboard access. New orders and new customers require their own create permissions.

---

# 2. Customers & Orders

## 2.1 Customers

**Open:** Customers & Orders → Customers<br>
**Address:** `/public/admin/customers`

{{SCREENSHOT:customers}}

The customer directory shows customer code, name, phone, email, GSTIN, portal-access status and sales-team information.

**Key actions**

- Select **Details** to review a customer profile, addresses, portal users and recent orders.
- Select **Add Customer** to create a customer.
- From Customer Details, authorized users can add a portal user or replace a portal user's password.

**Access note:** Customer viewing and customer creation are separate rights. Passwords are never displayed; they can only be replaced.

## 2.2 Order Dashboard

**Open:** Customers & Orders → Orders → Order Dashboard<br>
**Address:** `/public/admin/orders/dashboard`

{{SCREENSHOT:orders-dashboard}}

The Order Dashboard gives a visual view of the production pipeline. It highlights total orders, delayed orders, repeat orders, repeat designs and the count at each workflow stage.

**What you can see**

- order image and number;
- customer or source;
- design and repeat-design indicator;
- current workflow status and delay information;
- due date and assigned karigar.

**Key actions**

- Select a summary or workflow card to filter the register.
- Select **Timeline** to review all follow-ups, order images and status history without leaving the page.
- Select **View** to open the complete order.
- Use **Clear Filter** to return to all orders.

## 2.3 All Orders

**Open:** Customers & Orders → Orders → All Orders<br>
**Address:** `/public/admin/orders`

{{SCREENSHOT:orders}}

This is the main order register. It lists order number, source, customer, salesperson, karigar, type, status, priority and due date.

**Key actions**

- Open Order Details or Ornament Details.
- Create a regular order or receive a repair order.
- Edit an order, assign a karigar, receive finished material or cancel an order when authorized.
- Generate packing lists and delivery challans for eligible orders.

**Access note:** Viewing, creating, editing, assigning, receiving, changing status and producing documents are controlled separately.

## 2.4 Ready Orders

**Open:** Customers & Orders → Orders → Ready Orders<br>
**Address:** `/public/admin/orders/ready`

{{SCREENSHOT:orders-ready}}

Ready Orders focuses on completed production items that are ready for the next dispatch, showroom or delivery step. It uses the same register layout as All Orders.

**Key actions**

- Open the order and ornament details.
- Review the customer, karigar and completion status.
- Generate the packing list or delivery challan when permitted.

## 2.5 Repair Orders

**Open:** Customers & Orders → Orders → Repair Orders<br>
**Address:** `/public/admin/orders/repair`

{{SCREENSHOT:orders-repair}}

Repair Orders contains orders whose type is Repair. It keeps repair work separate from fresh production while retaining the normal order workflow.

**Key actions**

- Select **Repair Receive** to record a new repair intake.
- Open, edit, assign, receive or cancel repair work according to your role.

**Access note:** The Repair Orders menu is shown only to users who can both view orders and create orders.

## 2.6 Order Followups

**Open:** Customers & Orders → Orders → Followups<br>
**Address:** `/public/admin/orders/followups`

{{SCREENSHOT:orders-followups}}

The follow-up register helps production teams identify the next required contact or progress check. It shows customer, karigar, order status, due date, next follow-up, follow-up state, days left and the last follow-up time.

**Key actions**

- Open the linked order number for full context.
- Select **Take Followup** to record a stage, description, next follow-up date/time and optional image.
- Use the pending and delayed labels to prioritize work.

**Access note:** Saving a follow-up requires Follow-up access in addition to Order viewing.

---

# 3. Production

## 3.1 Karigars

**Open:** Production → Karigars<br>
**Address:** `/public/admin/karigars`

{{SCREENSHOT:karigars}}

The Karigar Master records each production worker or workshop with department, phone, city, labour rate, wastage percentage, documents and active status.

**Key actions**

- Open the profile for order, balance and transaction context.
- Add or edit karigar information.
- Activate or deactivate a karigar.
- Authorized accounts users can add karigar payment entries from the profile.

## 3.2 Material Issue

**Open:** Production → Material Issue<br>
**Address:** `/public/admin/issuements`

{{SCREENSHOT:issuements}}

Material Issue provides one combined register and separate Gold, Diamond and Stone issue registers. Each entry identifies its date, voucher, material, karigar, warehouse, purpose, quantity or weight, pieces and value.

**Key actions**

- Select **Create Issuement** to issue one or more materials.
- Open an issue to review its lines and attachments.
- Open or print the voucher where permitted.

**Access note:** Creating and printing issuements are separate from viewing them.

## 3.3 Designs

**Open:** Production → Designs<br>
**Address:** `/public/admin/designs`

{{SCREENSHOT:designs}}

The Design Master is the reusable design catalogue. It displays image preview, design code and name, classification, preferred karigar, expected gold weights, studded information and source.

**Key actions**

- Browse designs before creating an order.
- Select **Add Design** to upload a design and record its production details.

---

# 4. Gold Inventory

Gold Inventory tracks purity-based material from purchase through issue, return, correction and current balance. Gold weights are shown in grams; fine-gold figures represent the pure-gold equivalent.

## 4.1 Gold Purity Master

**Open:** Inventory → Gold Inventory → Purity Master<br>
**Address:** `/public/admin/gold-inventory/purities`

{{SCREENSHOT:gold-inventory-purities}}

This page defines the purity codes used by gold products. It shows purity code, percentage, color, linked-product count and status.

**Key actions:** Search by code, percentage or color; create, edit or delete a purity when authorized. Do not delete a purity already used by products or transactions.

## 4.2 Gold Product Master

**Open:** Inventory → Gold Inventory → Product Master<br>
**Address:** `/public/admin/gold-inventory/products`

{{SCREENSHOT:gold-inventory-products}}

Gold products combine purity, color and physical form. The register also shows weight balance, fine balance, average cost per gram and stock value.

**Key actions:** Search products; create or edit a gold form; review the current product-level valuation.

## 4.3 Gold Purchases

**Open:** Inventory → Gold Inventory → Purchases<br>
**Address:** `/public/admin/gold-inventory/purchases`

{{SCREENSHOT:gold-inventory-purchases}}

The purchase register shows date, supplier, invoice, purchased weight, taxable value, GST, invoice total, payment position and source document.

**Key actions**

- Filter by From and To dates.
- Create a purchase with its supplier, invoice and line details.
- View, edit or delete an eligible purchase.
- Open the uploaded invoice or production-import document.

**Access note:** Purchase changes require Gold Inventory management access. Some imported source documents additionally require Accounts access.

## 4.4 Gold Returns

**Open:** Inventory → Gold Inventory → Returns<br>
**Address:** `/public/admin/gold-inventory/returns`

{{SCREENSHOT:gold-inventory-returns}}

Gold Returns records material returned from a karigar or other party. The register includes receipt number, original issue reference, date, source, purpose, receiving location, line count, weight and value.

**Key actions:** Filter by date; create, view or edit a return; print its return receipt. Always choose the correct original issue when the return relates to issued material.

## 4.5 Gold Stock Adjustments

**Open:** Inventory → Gold Inventory → Adjustments<br>
**Address:** `/public/admin/gold-inventory/adjustments`

{{SCREENSHOT:gold-inventory-adjustments}}

Adjustments record controlled corrections to stock. The register shows date, adjustment type, location, line count, total weight and value.

**Key actions:** Filter by date and open the adjustment; authorized users can create, edit or delete an adjustment.

**Safety:** Use an adjustment only for a genuine, approved correction. Record a meaningful reason and verify whether the change increases or decreases stock.

## 4.6 Gold Stock Summary

**Open:** Inventory → Gold Inventory → Stock<br>
**Address:** `/public/admin/gold-inventory/stock`

{{SCREENSHOT:gold-inventory-stock}}

The stock summary shows weight balance, fine balance, average cost and stock value for each purity/color/form combination.

**Key actions:** Filter by purity, color and form; reset filters; open the masters; start a purchase, issue or return if authorized.

## 4.7 Gold Ledger

**Open:** Inventory → Gold Inventory → Ledger<br>
**Address:** `/public/admin/gold-inventory/ledger`

{{SCREENSHOT:gold-inventory-ledger}}

The Gold Ledger gives a dated running history of debit and credit weight, fine-gold movement, balances, rate, value, reference and notes.

**Key actions:** Filter by date, transaction type, item and karigar. Use references to reconcile the ledger with source purchases, issues, returns and adjustments.

---

# 5. Diamond Inventory

Diamond Inventory tracks items by attributes such as type, shape, chalni, color, clarity and cut. Quantities are recorded in pieces and carats.

## 5.1 Diamond Item Master

**Open:** Inventory → Diamond Inventory → Item Master<br>
**Address:** `/public/admin/diamond-inventory/items`

{{SCREENSHOT:diamond-inventory-items}}

The item master displays diamond attributes together with pieces balance, carat balance, average cost per carat and stock value.

**Key actions:** Search by type or grading attribute; create, edit or delete an item when authorized.

## 5.2 Diamond Purchases

**Open:** Inventory → Diamond Inventory → Purchases<br>
**Address:** `/public/admin/diamond-inventory/purchases`

{{SCREENSHOT:diamond-inventory-purchases}}

The register shows purchase date, supplier, invoice, due date, tax rate, line count, total carats, subtotal and invoice total.

**Key actions:** Filter by date; create, view, edit or delete a purchase. Check grading, pieces, carats, rate and tax before saving.

## 5.3 Diamond Returns

**Open:** Inventory → Diamond Inventory → Returns<br>
**Address:** `/public/admin/diamond-inventory/returns`

{{SCREENSHOT:diamond-inventory-returns}}

This register records diamond material returned from an issue. It includes receipt and issue references, date, return source, purpose, line count, total carats and value.

**Key actions:** Filter, create, view or edit a return; print the return receipt.

## 5.4 Diamond Stock Adjustments

**Open:** Inventory → Diamond Inventory → Adjustments<br>
**Address:** `/public/admin/diamond-inventory/adjustments`

{{SCREENSHOT:diamond-inventory-adjustments}}

Diamond adjustments document approved stock corrections by date, type, location, lines, carats and value.

**Key actions:** Filter by date; view the adjustment; create, edit or delete when permitted. Record a clear correction reason.

## 5.5 Diamond Stock Summary

**Open:** Inventory → Diamond Inventory → Stock<br>
**Address:** `/public/admin/diamond-inventory/stock`

{{SCREENSHOT:diamond-inventory-stock}}

The summary shows pieces balance, carat balance, average cost per carat and value for each diamond item.

**Key actions:** Filter by type, shape, color, clarity and chalni range; reset filters; start a purchase or issue when authorized.

---

# 6. Stone Inventory

Stone Inventory manages non-diamond stones and other quantity-based studded material.

## 6.1 Stone Item Master

**Open:** Inventory → Stone Inventory → Item Master<br>
**Address:** `/public/admin/stone-inventory/items`

{{SCREENSHOT:stone-inventory-items}}

The Stone Item Master displays product name, stone type, default rate, quantity balance, average rate and stock value.

**Key actions:** Search by product, type or remark; create, edit or delete an item when authorized.

## 6.2 Stone Purchases

**Open:** Inventory → Stone Inventory → Purchases<br>
**Address:** `/public/admin/stone-inventory/purchases`

{{SCREENSHOT:stone-inventory-purchases}}

The register shows date, supplier, invoice, due date, tax rate, number of lines, total quantity, subtotal and invoice total.

**Key actions:** Filter by date; create, view, edit or delete a purchase. Confirm the item, quantity, rate and tax on every line.

## 6.3 Stone Returns

**Open:** Inventory → Stone Inventory → Returns<br>
**Address:** `/public/admin/stone-inventory/returns`

{{SCREENSHOT:stone-inventory-returns}}

Stone Returns links returned material to its receipt and original issue. It shows source, purpose, line count, total quantity and value.

**Key actions:** Filter by date; create, view or edit a return; print its receipt.

## 6.4 Stone Stock Adjustments

**Open:** Inventory → Stone Inventory → Adjustments<br>
**Address:** `/public/admin/stone-inventory/adjustments`

{{SCREENSHOT:stone-inventory-adjustments}}

This page records approved stone-quantity corrections with date, type, location, lines, quantity and value.

**Key actions:** Filter and inspect adjustments; create, edit or delete when authorized. Include an auditable reason.

## 6.5 Stone Stock Summary

**Open:** Inventory → Stone Inventory → Stock<br>
**Address:** `/public/admin/stone-inventory/stock`

{{SCREENSHOT:stone-inventory-stock}}

The summary lists the current quantity, average rate and stock value for each stone product and type.

**Key actions:** Filter by product name and stone type; reset filters; start a purchase or issue if your role permits it.

---

# 7. Inventory Setup

## 7.1 Warehouses

**Open:** Inventory → Inventory Setup → Warehouse<br>
**Address:** `/public/admin/inventory/warehouses`

{{SCREENSHOT:inventory-warehouses}}

The Warehouse page defines physical storage locations and their bins. The first register shows warehouse code, name, type, address, status and creation date; the second shows bins assigned to each warehouse.

**Key actions:** Create a warehouse; select a warehouse and create a bin code/name.

**Access note:** Viewing Inventory Setup does not automatically permit creating warehouses or bins.

---

# 8. Showroom

The Showroom module manages finished jewellery after production: showroom transfer, counter allocation, reservation, sale and delivery history.

## 8.1 Showroom Sales

**Open:** Showroom → Retail Showroom → Showroom Sales<br>
**Address:** `/public/admin/showroom-sales`

{{SCREENSHOT:showroom-sales}}

The sales register lists sale number, invoice, date, showroom, counter, customer, salesperson, total, amount paid and payment status.

**Key actions:** Create a sale, open sale details and download the invoice. Confirm the item tags, customer, salesperson, tax, payment mode and amount before billing.

## 8.2 Showroom Stock

**Open:** Showroom → Retail Showroom → Showroom Stock<br>
**Address:** `/public/admin/showroom-stock`

{{SCREENSHOT:showroom-stock}}

This page combines current stock, reservations and movement history.

**What you can see**

- tag and source order;
- showroom and counter location;
- gross weight, net gold and diamond weight;
- availability or reservation status;
- customer/order and expiry information for reservations;
- previous movement from one location or state to another.

**Key actions**

- Transfer finished goods to a showroom.
- Allocate showroom stock to a counter or return it from a counter.
- Reserve an item for a customer/order and release a reservation.
- Start billing an available or reserved item.

**Access note:** Stock movement, reservations and sales are separate responsibilities. Your role may permit one action but not the others.

## 8.3 Jewellery Inventory

**Open:** Showroom → Retail Showroom → Jewellery Inventory<br>
**Address:** `/public/admin/jewellery-inventory`

{{SCREENSHOT:jewellery-inventory}}

Jewellery Inventory is the visual finished-goods register. Summary cards show active and closed items, active gross weight, active net gold and active diamond carats.

The active register includes image, tag/order, karigar/group, design, metal and studded weights, value/labour and current status. A separate history records transfer and delivery actions.

**Key actions:** Filter by karigar; open an item image; mark an active item **Transferred** or **Delivered** with a required remark when authorized.

**Safety:** Closing an active inventory item removes it from the active list but preserves it in history. Confirm the physical movement before saving.

## 8.4 Showroom Master

**Open:** Showroom → Retail Showroom → Showrooms<br>
**Address:** `/public/admin/showrooms`

{{SCREENSHOT:showrooms}}

The Showroom Master lists code, name, type, manager, city, state, phone and active status.

**Key actions:** Add or edit a showroom and activate/deactivate it. Do not deactivate a location while stock is still assigned without first reviewing the stock position.

## 8.5 Counter Master

**Open:** Showroom → Retail Showroom → Counters<br>
**Address:** `/public/admin/showroom-counters`

{{SCREENSHOT:showroom-counters}}

The counter register identifies the showroom, counter code/name, counter type, in-charge and status.

**Key actions:** Add or edit a counter and change its active status.

## 8.6 Staff Assignment

**Open:** Showroom → Retail Showroom → Staff Assignment<br>
**Address:** `/public/admin/showroom-staff`

{{SCREENSHOT:showroom-staff}}

This page records which employee works in each showroom, their designation and showroom role, whether the assignment is primary, its effective dates and status.

**Key actions:** Assign an employee, edit an assignment and activate/deactivate it. Avoid overlapping primary assignments unless that is intentional.

---

# 9. Accounts

Accounts pages bring together purchases, labour, sales, payments, journals, GST and party balances. Financial figures should be reviewed against approved source documents.

## 9.1 Accounts Dashboard

**Open:** Accounts → Accounts → Dashboard<br>
**Address:** `/public/admin/accounts`

{{SCREENSHOT:accounts}}

The Accounts Dashboard summarizes total payable, vendor payable, karigar payable, sales receivable, posted expenditure and pending-party counts.

**Key actions:** Open Journal Voucher, Payments, All Ledger, Issue Receive Ledger, GST Report or Outstanding Summary from the quick-action tiles. Select any balance card to drill into the corresponding account view.

## 9.2 Journal Vouchers

**Open:** Accounts → Accounts → Journal Voucher<br>
**Address:** `/public/admin/accounts/journal-vouchers`

{{SCREENSHOT:accounts-journal-vouchers}}

Journal Vouchers records party-to-party adjustments and expenditure entries. The upper form captures voucher type, date, amount, status, parties or expense head, payment mode, reference and notes. Voucher History appears below it.

**Key actions:** Save a Posted or Draft party adjustment/expense; review past vouchers.

**Safety:** A posted voucher affects accounts reporting. Confirm both sides, amount and date before saving. Use a specific expense head rather than a vague label.

## 9.3 Pending Parties

**Open:** Accounts → Accounts → Pending Parties<br>
**Address:** `/public/admin/accounts/party-balances/vendor`

{{SCREENSHOT:accounts-party-balances-vendor}}

The sidebar opens pending vendor balances. Summary cards show pending-party count, bills, total value and outstanding balance; the register shows each vendor's total, paid and balance amounts.

**Key actions:** Select **Open Account** for the party ledger or **View All Ledger** for the combined ledger. Customer and karigar pending accounts are also available from the Accounts Dashboard.

## 9.4 All Ledgers

**Open:** Accounts → Accounts → All Ledgers<br>
**Address:** `/public/admin/accounts/general-ledger`

{{SCREENSHOT:accounts-general-ledger}}

The General Ledger is a filterable register of accounting activity. It includes date, transaction type, reference, party, bill/invoice, order, material, debit, credit, open balance, status, mode, details, files and notes.

**Key actions:** Filter by date, transaction type, party type/party, status, reference or global search; select **Export Excel** to export the filtered register.

## 9.5 Issue Receive Ledger

**Open:** Accounts → Accounts → Issue Receive Ledger<br>
**Address:** `/public/admin/accounts/vendor-transaction-ledger`

{{SCREENSHOT:accounts-vendor-transaction-ledger}}

This ledger reconciles material issued and received with financial position. Summary cards show row count, gold issued/received, carats issued and payable/paid totals. The register includes material quantities, party, order, reference, balance, mode, document and notes.

**Key actions:** Filter by date, party, category, transaction, material or reference; export the filtered result; open source details/files.

## 9.6 Payments

**Open:** Accounts → Accounts → Payments<br>
**Address:** `/public/admin/accounts/payments`

{{SCREENSHOT:accounts-payments}}

The New Payment form pays a vendor or karigar. It shows the selected party's balance and accepts date, amount, mode, reference number, supporting file, optional bill and notes. Payment History appears below.

**Key actions:** Record a payment and attach proof; review previous payments.

**Safety:** Confirm the party and displayed balance, avoid paying more than the approved amount, and add the UTR/cheque/reference wherever available.

## 9.7 Outstanding Summary

**Open:** Accounts → Accounts → Outstanding Summary<br>
**Address:** `/public/admin/accounts/outstanding-summary`

{{SCREENSHOT:accounts-outstanding-summary}}

This page separates Customer Receivables, Vendor Payables and Karigar Payables. Each table shows party, bill count, original amount, paid/received amount and outstanding amount.

**Key actions:** Use the summary for collection and payment planning; reconcile differences in the relevant party ledger.

## 9.8 Purchase Bills

**Open:** Accounts → Accounts → Purchase Bills<br>
**Address:** `/public/admin/accounts/purchase-bills`

{{SCREENSHOT:accounts-purchase-bills}}

Purchase Bills consolidates supplier invoice, material, GST, bill value, payment position and supporting documents.

**Key actions:** Open the source document or bill detail; open the Production Purchase Register; update a bill payment when authorized.

## 9.9 Labour Ledger

**Open:** Accounts → Accounts → Labour Ledger<br>
**Address:** `/public/admin/accounts/labour-ledger`

{{SCREENSHOT:accounts-labour-ledger}}

The Labour Ledger combines karigar labour bills and payments. Summary cards show bill amount, paid amount and open balance; each line shows reference, order, bill/payment amount, open bill balance, status, file and notes.

**Key actions:** Filter by karigar, status, entry type and date; export the filtered ledger.

## 9.10 Labour Bills

**Open:** Accounts → Accounts → Labour Bills<br>
**Address:** `/public/admin/accounts/labour-bills`

{{SCREENSHOT:accounts-labour-bills}}

The Labour Bills register shows bill/date, order, karigar, gold weight, rate, labour and other amounts, total, paid, pending, due date, days left and payment status.

**Key actions:** Download a labour bill; update its payment with date, amount, reference and notes.

## 9.11 Sale Bills

**Open:** Accounts → Accounts → Sale Bills<br>
**Address:** `/public/admin/accounts/sale-bills`

{{SCREENSHOT:accounts-sale-bills}}

Sale Bills lists retail showroom sales with sale/invoice number, date, showroom, counter, customer, salesperson, billed amount, paid amount and status.

**Key actions:** Open sale details, download the invoice and create a sale bill when Showroom Sales access is available. If the page reports a pending migration, contact the administrator.

## 9.12 Debit Notes

**Open:** Accounts → Accounts → Debit Notes<br>
**Address:** `/public/admin/accounts/debit-notes`

{{SCREENSHOT:accounts-debit-notes}}

The Debit Note form records an additional amount against a customer or vendor, optionally linked to an order and invoice. The register shows reason, taxable value, GST, total and status.

**Key actions:** Save a Posted or Draft debit note and review the register.

## 9.13 Credit Notes

**Open:** Accounts → Accounts → Credit Notes<br>
**Address:** `/public/admin/accounts/credit-notes`

{{SCREENSHOT:accounts-credit-notes}}

Credit Notes records discounts, returns, cancellations or reversals against a customer or vendor. It can be linked to an order/invoice and records taxable amount, GST, total and status.

**Key actions:** Save a Posted or Draft credit note and review existing notes.

**Safety for notes:** Confirm whether the situation requires a debit or credit note. Check the party, reason, taxable amount and GST before posting.

## 9.14 GST Report

**Open:** Accounts → Accounts → GST Report<br>
**Address:** `/public/admin/accounts/gst-report`

{{SCREENSHOT:accounts-gst-report}}

The GST Report shows sales taxable value, sales GST, purchase GST and estimated net GST payable. It also includes the GST working formula and separate sales, purchase and debit/credit-note registers.

**Key actions:** Apply a date range, reset filters and use the underlying registers to verify the summary. Treat the report as an operational aid and reconcile it before statutory filing.

---

# 10. Reports

Reports are designed for review and analysis. Filters affect only the displayed results. Keep a record of the date range and filters used when sharing an export.

## 10.1 All Transactions

**Open:** Reports → Reports → All Transactions<br>
**Address:** `/public/admin/reports/transactions`

{{SCREENSHOT:reports-transactions}}

All Transactions is the unified business-activity register. It combines purchases, issuements, receiving, returns and payments across materials and parties.

**What you can see**

- transaction counts by activity;
- total transaction value and gold, diamond and stone movement;
- date, activity, material, reference, order, party, status, movement detail, amount and notes.

**Key actions:** Filter by date, activity, transaction type, material, party type, karigar, customer, vendor, status, order number or global search.

## 10.2 Gold Ledger Report

**Open:** Reports → Reports → Gold Ledger<br>
**Address:** `/public/admin/reports/gold-ledger`

{{SCREENSHOT:reports-gold-ledger}}

The report summarizes debit, credit, running balance and fine-gold balance. Detail lines identify transaction type, karigar, order, gold item, location, weight/fine movement, rate, value and reference.

**Key actions:** Filter by date, karigar, transaction type and order number; use **Reset** to return to all available history.

## 10.3 Diamond Ledger Report

**Open:** Reports → Reports → Diamond Ledger<br>
**Address:** `/public/admin/reports/diamond-ledger`

{{SCREENSHOT:reports-diamond-ledger}}

Summary cards show opening, purchased, issued, returned and warehouse/karigar carat positions. Detail lines include party, purpose, grading attributes, pieces, carats, rate, value and reference.

**Key actions:** Filter by date, karigar and order number.

## 10.4 Inventory Report

**Open:** Reports → Reports → Inventory<br>
**Address:** `/public/admin/reports/inventory`

{{SCREENSHOT:reports-inventory}}

This report summarizes gold weight/fine/value and diamond pieces/carats/value. It also shows purchase, issue and return movement for the selected period, followed by Gold Stock and Diamond Stock valuation tables.

**Key actions:** Select a From/To date to review period movement; reset to return to the default view.

## 10.5 Karigar Performance Report

**Open:** Reports → Reports → Karigar Performance<br>
**Address:** `/public/admin/reports/karigar-performance`

{{SCREENSHOT:reports-karigar-performance}}

The report compares delivered orders, delivered gold/fine gold and average turnaround time. Results are grouped by karigar and period.

**Key actions:** Choose month-wise or custom-range mode and optionally select one karigar.

**Interpretation note:** Turnaround time should be reviewed alongside order complexity, priority and rework rather than used alone.

## 10.6 Staff Directory

**Open:** Reports → Reports → Staff Directory<br>
**Address:** `/public/admin/reports/staff-directory`

{{SCREENSHOT:reports-staff-directory}}

The directory shows total, active and inactive staff and location count. Each employee row includes code, department, designation, reporting manager, contact, location, joining date and status.

**Key actions:** Filter by department, designation, status and work location.

---

# 11. Admin Masters & Organization

These pages maintain core parties and the staff structure used throughout the ERP.

## 11.1 Vendors

**Open:** Admin → Vendors<br>
**Address:** `/public/admin/vendors`

{{SCREENSHOT:vendors}}

The Vendor List shows name, contact person, phone, email and GSTIN. Users with management access also see an Add Vendor form.

**Key actions:** Create a vendor and open **Account & Ledger** for financial history. Search carefully before adding a vendor to avoid duplicates.

## 11.2 Department Master

**Open:** Admin → Staff & Organization → Departments<br>
**Address:** `/public/admin/departments`

{{SCREENSHOT:departments}}

Departments define the main organizational units. The register shows code, name, sort order, status and notes.

**Key actions:** Add or edit a department and toggle its status.

## 11.3 Designation Master

**Open:** Admin → Staff & Organization → Designations<br>
**Address:** `/public/admin/designations`

{{SCREENSHOT:designations}}

Designations define job level and reporting structure within a department. The register shows code, department, level, reports-to designation, manager capability and status.

**Key actions:** Add or edit a designation and change its status. Set reporting levels consistently so hierarchy and performance reports remain meaningful.

## 11.4 Employee Master

**Open:** Admin → Staff & Organization → Employees<br>
**Address:** `/public/admin/employees`

{{SCREENSHOT:employees}}

The Employee Master lists code, employee/contact details, department, designation, work location, linked admin login and status.

**Key actions:** Add/edit an employee, activate/deactivate the record and open Employee Hierarchy. Linking an employee to an admin login does not by itself grant roles or permissions.

## 11.5 Employee Hierarchy

**Open:** Admin → Staff & Organization → Employee Hierarchy<br>
**Address:** `/public/admin/employee-hierarchy`

{{SCREENSHOT:employee-hierarchy}}

Select an employee to view their profile, current structure, direct team and hierarchy history. The hierarchy can record Reporting, Observing, Reviewing and Approving managers plus Department Head.

**Key actions:** Assign manager relationships with an effective date and remark; review previous assignments.

**Safety:** Do not assign an employee as their own manager or create circular reporting chains.

---

# 12. Performance

## 12.1 KPI Dashboard

**Open:** Admin → Performance → KPI Dashboard<br>
**Address:** `/public/admin/performance/dashboard`

{{SCREENSHOT:performance-dashboard}}

The KPI Dashboard shows target count, employee count, total target value and estimated incentive for a selected month. The register compares target, achievement percentage and incentive by employee/KPI.

**Key actions:** Filter by year, month and employee; create a new target when permitted.

## 12.2 KPI Master

**Open:** Admin → Performance → KPI Master<br>
**Address:** `/public/admin/performance/kpis`

{{SCREENSHOT:performance-kpis}}

KPI Master defines each measurable indicator with code, name, module, metric key, unit, period and status.

**Key actions:** Create or edit a KPI. Keep the unit and period consistent with how achievement is calculated.

## 12.3 KPI Targets

**Open:** Admin → Performance → KPI Targets<br>
**Address:** `/public/admin/performance/targets`

{{SCREENSHOT:performance-targets}}

Targets assign a KPI to an employee for a month/year with target value, weightage and status.

**Key actions:** Assign or edit a target. Review duplicate employee/KPI/period combinations before saving.

## 12.4 Incentive Rules

**Open:** Admin → Performance → Incentive Rules<br>
**Address:** `/public/admin/performance/incentives`

{{SCREENSHOT:performance-incentives}}

Incentive Rules map an achievement range to an incentive type and value, optionally for one designation or KPI.

**Key actions:** Create or edit a rule. Avoid unintended overlapping ranges and confirm whether the value is fixed or percentage-based.

---

# 13. Company & System Settings

## 13.1 Company Settings

**Open:** Admin → Company Settings<br>
**Address:** `/public/admin/company-settings`

{{SCREENSHOT:company-settings}}

Company Settings controls information used across documents and notifications.

**What you can see**

- company name, phone, email, GSTIN, address, city/state/pincode and logo;
- issuement, delivery-challan and sale-bill prefixes;
- OneSignal push enablement, App ID, API key and Sender ID;
- WhatsApp endpoint, method, authentication, sender, internal recipients and extra headers;
- event controls and templates for order creation, status, ready, over-budget and daily delay messages.

**Key actions:** Update company details, logo, document prefixes or notification integrations and select **Save Settings**.

**Safety:** Integration tokens are confidential. Test changes in a controlled way, keep a secure copy of the previous working configuration, and never place live API keys in screenshots or public documents.

## 13.2 Database Update

**Open:** Admin → Database Update<br>
**Address:** `/public/admin/system/database-update`

{{SCREENSHOT:system-database-update}}

The page reports available, applied and pending database migrations and lists recently applied changes.

**Key action:** Select **Run Database Update** only when pending migrations exist and an approved deployment requires them.

**Critical safety**

- Take and verify a current database backup first.
- Ensure no long-running import or transaction is in progress.
- Do not close or refresh the page while an update is running.
- If an update reports an error, preserve the message and contact technical support; do not repeatedly run it.

This page is intentionally restricted to high-level administrators.

---

# 14. Users & Access

Access Control determines who can see information and who can change it. Follow the principle of least privilege: give each person only the access required for their work.

## 14.1 Roles

**Open:** Admin → Users & Access → Roles<br>
**Address:** `/public/admin/access/roles`

{{SCREENSHOT:access-roles}}

The Role Master shows role name/code, description, permission count, assigned-user count and status.

**Key actions:** Create/edit a role, choose its permissions and activate/deactivate it. Review affected users before changing a widely assigned role.

## 14.2 Permissions

**Open:** Admin → Users & Access → Permissions<br>
**Address:** `/public/admin/access/permissions`

{{SCREENSHOT:access-permissions}}

Permission Master is the catalogue of available rights, grouped by module and action with sort order and status.

**Key actions:** Create/edit a permission or change its status. This is an advanced administration page; incorrect changes can hide essential functions or grant excessive access.

## 14.3 User Access

**Open:** Admin → Users & Access → User Access<br>
**Address:** `/public/admin/access/users`

{{SCREENSHOT:access-users}}

User Access lists each admin user, employee link, designation, role count, individual overrides and status.

**Key actions**

- Create a user with name, email, password and at least one active role.
- Select **Details** to review identity and access.
- Link an employee, assign roles and apply carefully chosen user-specific overrides.
- Activate/deactivate the account or replace its password.

**Security guidance**

- Never reveal, copy or request an existing password; replace it securely when needed.
- Use a unique password of at least eight characters and confirm it before saving.
- Deactivate users promptly when access is no longer required.
- Prefer role-based access over many individual overrides.
- Review privileged accounts regularly.

---

# 15. Common Tasks

## Create and begin a new order

1. Confirm the customer exists in **Customers**; add the customer if needed.
2. Open **All Orders** and select **Create Order**.
3. Choose the customer/source, design, order type, priority and due date.
4. Record material and production requirements carefully and upload the available reference images.
5. Save the order, then assign a karigar from the order register or details page.
6. Use **Followups** and the Order Timeline to monitor progress.

## Receive finished work and prepare dispatch

1. Open the order and verify the assigned karigar and issued material.
2. Use the authorized Receive action to record finished weights, studded material, labour and supporting details.
3. Check the item in **Jewellery Inventory** or **Ready Orders**.
4. Generate packing/delivery documents, or transfer the item to Showroom Stock as required.

## Record material movement

1. Create the purchase if new material has arrived.
2. Issue material through **Material Issue**, choosing the correct warehouse, karigar, order and purpose.
3. Record returned unused material through the matching Gold, Diamond or Stone Return page.
4. Reconcile balances in Stock, Ledgers and Reports.

## Record a supplier or karigar payment

1. Review Pending Parties, Purchase Bills or Labour Bills.
2. Confirm the approved pending amount and supporting document.
3. Open **Payments**, select the correct party and optional bill, then enter amount, mode and reference.
4. Attach proof and save.
5. Recheck the party or labour ledger.

## Investigate a delayed order

1. Open **Order Dashboard** and select the Delayed card.
2. Open **Timeline** to review status history, images and earlier follow-ups.
3. Record a meaningful follow-up with next date/time.
4. Open Order Details if assignment, status, due date or supporting documents need attention.

---

# 16. Troubleshooting

## A menu or button is missing

Your role probably does not include that page or action. Ask an administrator to review your role; do not use another person's account.

## A register is empty

- Select **Reset** to clear filters.
- Check the From and To dates.
- Confirm that the required master or transaction has been created.
- Verify that you are viewing the correct status-specific page, such as Ready or Repair Orders.

## A PDF or attachment does not open

- Allow pop-ups for the portal.
- Try opening the link in a new tab.
- Confirm that the original file was uploaded and that your role can access the document.

## A save fails

- Read the validation message and correct the highlighted field.
- Confirm required parties, dates, quantities and remarks are present.
- Check that uploaded files use an accepted type and reasonable size.
- Do not repeatedly submit the same form.

## Data looks incorrect

Note the page, filter, order/voucher/reference number and expected value. Take a screenshot that does not expose passwords or API keys, then report it to the administrator. Avoid creating an adjustment until the source transaction has been checked.

---

# 17. Access Summary for Administrators

The following plain-language access groups correspond to the main sidebar areas:

| Area | View access | Additional action access |
|---|---|---|
| Dashboard | Dashboard view | Order/customer create rights for quick actions |
| Customers | Customer view | Customer creation and portal-user management |
| Orders | Order view | Create, edit, assign, receive, follow-up, status and document rights |
| Production masters | Karigar/design view | Master management and karigar-payment rights |
| Material Issue | Issuement view | Create and print rights |
| Gold/Diamond/Stone Inventory | Material inventory view | Material inventory management |
| Inventory Setup | Setup view | Warehouse/bin management |
| Showroom | Masters, stock or sales view | Master, stock-movement, reservation and sales management |
| Accounts | Accounts view | Payment/posting rights |
| Reports | Reports view | Reports are primarily read-only |
| Organization | Department/designation/employee/hierarchy view | Matching management right for each area |
| Performance | Dashboard/KPI/target/incentive view | Matching management right for each area |
| Company Settings | Settings view | Settings management |
| Database Update | Restricted system-management access | Same restricted access runs migrations |
| Users & Access | Role/permission/user view | Matching access-management right |

---

## End of guide

For application access, master-data corrections or workflow questions, contact your Aabhushan system administrator. For technical errors, include the page name, date/time and relevant order, voucher, invoice or reference number.
