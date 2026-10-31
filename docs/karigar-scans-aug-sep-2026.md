# August–September 2026 karigar issues and receiving

The migration `2026-10-30-000118_ImportVerifiedKarigarScans` imports the
reviewed item data under `app/Database/Data/karigar-receipts-20260809/` for
Mahalaxmi Gold and Jewellers Hyderabad. Deploy the migration, the
`HistoricalKarigarReceiptImport` service, the issue and diamond-repair services,
all four scan migrations and both data directories together. On a database
where migration 118 has not run, it posts the reviewed material issues before
the related receipts in one transaction. Migrations 120–122 also support targets
where the receipts were already imported; their rerun checks prevent reposting.

Before running on another database, take a database backup and run the read-only
duplicate review on that database:

```sh
php scripts/karigar-receipt-preflight.php app/Database/Data/karigar-receipts-20260809/august.json
php scripts/karigar-receipt-preflight.php app/Database/Data/karigar-receipts-20260809/september.json
```

Review the results against the source bills. The preflight lists candidates; it
cannot prove that a missing match is unique. Check the configured database is
the intended target before running `php spark migrate`. The migration validates
the ten individually reviewed historical matches, customer and master data; it
aborts its transaction on an unexpected match, missing verified record, or
customer conflict. It can therefore stop on production if that database has
changed since the local snapshot. Do not bypass a failure without investigating.

The migration uses the bill date as the receiving date because the actual
receipt time is unavailable. It uses the printed packing-sheet labour, weights
and values, including the separately printed chain weights, and records zero for
the four unspecified Uttam Mal gold values as instructed. Existing in-scope
orders with verified matching receipts and no customer are linked to the named
customer. July records are not altered. It does not create labour bills or
fabricate diamond bags.

The issue scans cover 13 distinct slips; the duplicate photograph of 033 is
counted once. Existing August gold issues and stock-consuming diamond issues
are not posted again. The six missing accounting vouchers for the existing
August diamond issues are restored without moving stock twice. September gold,
diamond and the remaining stone amounts are posted with their issue dates.
Stone type and the diamond inventory bucket are inferred from matching packing
sheets because the issue slips show only total carats; details and previously
posted stone quantities are identified in the issue notes. The existing
Gourango stone backflush is dated to the verified 3 August issue instead of the
17 August receipt, retaining its original date in the notes. Related July
backflushes are not modified. Existing `PL-HIST` opening adjustments are left
unchanged; their aggregate nature means possible overlap with actual issues
cannot be established without earlier records.

The September lab-grown issue can make that stock negative. Negative quantities
are retained until replenished; the unpriced shortage has zero valuation.
Priced incoming stock covers the shortage first, and any positive remainder
uses its incoming unit cost. An unpriced return or reversal that would cross
into positive stock, and a reversal that would recreate a shortage after it
was covered, stop for cost reconciliation rather than guessing its value.

Migration rollback intentionally does not delete posted vouchers or finished
stock. A rerun checks the existing receipt and does not post it twice. Restoring
a database backup is a separate operational decision; do not use migration
rollback as an accounting reversal.
