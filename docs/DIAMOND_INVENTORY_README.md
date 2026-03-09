# Diamond Inventory + Purchase + Issuement Module (CI4)

This module is added as a **separate flow** and does not replace existing inventory/purchase pages.

## URLs
- `admin/diamond-inventory/items`
- `admin/diamond-inventory/purchases`
- `admin/diamond-inventory/issues`
- `admin/diamond-inventory/returns`
- `admin/diamond-inventory/stock`

## Migration
Run:

```bash
php spark migrate
```

This creates:
- `items`
- `stock`
- `purchase_headers`
- `purchase_lines`
- `issue_headers`
- `issue_lines`
- `return_headers`
- `return_lines`

## Seed sample master data
Run:

```bash
php spark db:seed DiamondInventorySeeder
```

Seed data includes:
- Round chalni 3-4 G VS
- Pan H SI
- Baguette F VS
- Polki Mix
- Rose Cut I SI
- Broken Mix

## Business logic implemented
- Purchase save: adds stock.
- Issue save: subtracts stock.
- Return save: adds stock.
- Negative stock blocked (pcs/carat).
- WAC (weighted average cost) maintained in `stock`.
- Purchase/Issue edit/delete uses reverse then apply in one transaction.
- Return edit/delete uses reverse then apply in one transaction.
- Item auto-create from signature when item is not selected.
- Issue/Return can be linked with `order_id` and is shown on order details page.

## UI behavior
- In Purchase/Issue line:
  - user can select existing item, or
  - enter signature fields (type/shape/chalni/color/clarity/cut) and auto-map.
- Line value auto-calculates client-side (`carat * rate`) and is recalculated server-side.

## Example flow
1. Create item manually (optional) from `Item Master`.
2. Create purchase with 1+ lines.
3. Check `Stock Summary` increased.
4. Create issue with 1+ lines.
5. Check `Stock Summary` reduced.
6. Edit/delete purchase or issue and verify stock is rebalanced automatically.
