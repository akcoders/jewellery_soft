# August–September 2026 karigar scan receiving

The migration `2026-10-30-000118_ImportVerifiedKarigarScans` imports the
reviewed item data under `app/Database/Data/karigar-receipts-20260809/` for
Mahalaxmi Gold and Jewellers Hyderabad. Deploy the migration, the
`HistoricalKarigarReceiptImport` service, and both JSON files together.

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
fabricate diamond issues.

The accounting voucher deducts the received materials from the karigar, allowing
negative balances, and the finished items enter finished stock. The receipt
voucher does **not** issue loose gold or diamonds from warehouse stock; the
scans do not establish the original issued lots. A separate stock adjustment
would require verified issue details.

Migration rollback intentionally does not delete posted vouchers or finished
stock. A rerun checks the existing receipt and does not post it twice. Restoring
a database backup is a separate operational decision; do not use migration
rollback as an accounting reversal.
