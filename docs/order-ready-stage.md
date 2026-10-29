# Ready stage correction

Open `/admin/orders/ready-stage` after deploying the updated PHP code and sign in
with an account that has `orders.status` permission. Search by order number,
order name or customer, select an order and press **Mark selected order Ready**.

The page includes older Completed orders without an actual receiving entry.
It changes only the selected order and its items, clears stale receiving flags
and pending follow-up reminders, and records who changed the stage. Received,
cancelled and deleted orders cannot be moved to Ready through this page.

Follow-up stages are Confirmed, In Production, QC and Ready. Finished-jewellery
receiving records Completed. The mobile app reads these stages from the API;
the rebuilt PWA is included under `public/pwa/`. No database migration is needed.
