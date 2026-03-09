# Advanced Jobwork API Map

Base URL example: `http://localhost/jewellery_soft/public/index.php/api`

## Orders / Production
- `GET /orders`
- `POST /orders`
- `POST /orders/{id}/status`
- `POST /jobcards`
- `POST /jobcards/{id}/assign`
- `POST /jobcards/{id}/stages`

## Purchase / Vendor Payment
- `POST /purchases/grn`
- `POST /purchases/invoices`
- `POST /payments/vendors`

## Diamond Bag (Chalni)
- `POST /diamond-bags`
- `POST /diamond-bags/{id}/transfer`
- `POST /diamond-bags/{id}/split`
- `POST /diamond-bags/merge`
- `GET /diamond-bags/{id}/history`

## Voucher Engine
- `POST /vouchers`
- `POST /vouchers/{id}/reverse`
- `POST /vouchers/{id}/correct`

## Ornament/QC/FG/Packing
- `POST /ornaments/receive`
- `POST /qc/{fgItemId}`
- `POST /packing-lists`
- `POST /packing-lists/{id}/dispatch`

## Invoice / Receipts
- `POST /invoices`
- `POST /receipts`

## Reports
- `GET /reports/stock-on-hand`
- `GET /reports/karigar-outstanding`
- `GET /reports/order-consumption`
- `GET /reports/wastage`
- `GET /reports/bag-history`
- `GET /reports/outstanding-ageing`
- `GET /reports/sql-templates`

## PDF Documents
- `GET /documents/job-card/{id}`
- `GET /documents/gold-issue/{voucherId}`
- `GET /documents/diamond-issue/{voucherId}`
- `GET /documents/return-voucher/{voucherId}`
- `GET /documents/packing-list/{id}`
- `GET /documents/invoice/{id}`
- `GET /documents/ledger/{accountId}`

## Demo Scenario
- `POST /demo/full-flow`

## Seeders
- `php spark db:seed AdvancedJobworkSeeder`
- `php spark db:seed DemoFullFlowSeeder`
