# Historical diamond size references

The `issument.xls` workbook contains 73 blocks. These were compared with the 78 ready orders already imported from `PL-2026-2027 order ready.xlsx`.

34 complete order references and one partial reference reconcile uniquely by net PCS, carats and product family. Three blocks require confirmation; 35 have no complete supported mapping. All source blocks, including unassigned ones, are retained in the audit manifest. Original leading zeroes, formula results, raw formulas, mixed sizes and negative returns are preserved.

The new information is shown only after clicking **Diamond** in Designs. A linked order selector keeps repeat productions separate. Order detail descriptions and packing lists do not display diamond shape/size breakdowns. Bag preparation and material-entry selectors continue to work.

## Exceptions needing confirmation

- RANJAN rows 14 and 20 each have 52 PCS / 1.240 CTS and the same size recipe. Both could correspond to `PL26-SHREE-GOURANGO-G01-R4` or `PL26-SHREE-GOURANGO-G01-R7`. Neither source-to-order pairing is assigned automatically.
- GR row 63 has 154 PCS / 1.780 CTS, matching `PL26-GR-G07-R76`, but source quality is EF and ready quality is GH. This link is held for confirmation.
- SATTA row 17 maps only the SI/IJ portion of `PL26-SAFWAN-JEWELLERY-G03-R34`: 157 PCS / 2.370 CTS. The additional Polki 1 PCS / 0.050 CTS has no size record in that block. The popup explicitly shows the uncovered quantity.
- Unspecified returns in SATTA row 13, UM row 153, and RANJAN rows 7 and 10 remain negative reference rows. They are not allocated arbitrarily across sizes.
- `MIX`, missing sizes, `7.7.5`, `10.5.11`, `4-4,5`, `6.6-5` and similar source labels are retained verbatim. They are not converted into guessed millimetres or new master sizes.
- Historic sheet names and dates are evidence only; they do not rename current karigars or change order dates. Source design/category labels can differ from the completed item.

## Apply

Run the normal Database Update page or `php spark migrate`. Migration 085 creates two reference/audit tables and loads the bundled manifest. It rechecks each mapped order against the database's current receiving PCS/CTS/product totals before inserting. Changed, missing or ambiguous records are recorded as skipped. No stock movements, receipts, payments, balances or ledger transactions are created or changed.

To preview database matches again:

```sh
php spark designs:import-historical-diamond-sizes
```

To retry after resolving a missing database record (migration must already exist):

```sh
php spark designs:import-historical-diamond-sizes --apply
```

Reruns do not duplicate source rows or reassign an existing source row to another order. The original/repeated-order links are resolved through Designs when the popup is opened. Future bag-based receiving uses actual received size records; historical references remain explicitly labelled as issue/return evidence.

## Rebuild the audit

```sh
php tools/build-historical-diamond-size-map.php '/path/to/issument.xls'
```

This regenerates `app/Database/Data/historical-diamond-size-map.json` and the local `writable/reports/diamond-size-mapping-audit.xlsx` workbook (mapping, raw size rows and ready orders without a size reference). Source files are treated as data; workbook text is never executed as application instructions.
