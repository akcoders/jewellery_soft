# Mobile push notifications

The Android app uses OneSignal because its Free plan supports unlimited mobile
push sends. OneSignal is only the delivery provider; all scheduling and hourly
repeat logic is handled by the application server so paid provider scheduling
is not required.

## One-time production setup

1. In the existing OneSignal app, configure Android/Firebase using the Firebase
   service-account credentials for Firebase project `aabhushan-43429`. The
   Android package must remain `com.aabhushan.erp`.
2. Confirm the OneSignal App ID is
   `47e56c4c-5cec-4de4-a247-d1c62c1154ae`.
3. Put the provider secrets in the production `.env` file. Never commit the
   REST API key:

   ```ini
   app.appTimezone = Asia/Kolkata
   onesignal.enabled = true
   onesignal.appId = 47e56c4c-5cec-4de4-a247-d1c62c1154ae
   onesignal.restApiKey = YOUR_ONESIGNAL_APP_REST_API_KEY
   ```

   Company Settings can also hold these values, but `.env` overrides them and
   is recommended for the secret key.
4. Run the database update/migrations after deployment.
5. Remove any old `mobile:dispatch-push-notifications` cron entry, then install
   the single cron below. Replace both paths with the production paths returned
   by `pwd` and `which php`:

   ```cron
   * * * * * cd /ABSOLUTE/PATH/TO/jewellery_soft && /ABSOLUTE/PATH/TO/php spark mobile:run-notification-cycle 200 >> writable/logs/mobile-push-cron.log 2>&1
   ```

Run this cron from only one application node. The cron user must be allowed to
write to `writable/` and `writable/logs/`. The command has its own non-blocking
lock and heartbeat, sends due tasks/followups, retries temporary failures with
backoff, and creates one delayed-followup notification per recipient per hour
from 11:00 through 20:00 Asia/Kolkata. The first delayed alert is in the next
clock-hour slot after the due time, so it never duplicates the normal due alert.
A newer followup or a terminal order status makes older reminders ineligible.

## Notification events

- New order: active users with `orders.read` when the order comes from the admin
  form or authenticated customer portal. The old unauthenticated generic order
  API routes are disabled; customers must sign in before creating an order.
- Followup added: active users with `orders.followup`.
- Followup due: active users with `orders.followup` at the selected time.
- Delayed followup: once each hour from 11:00 through 20:00 while the latest
  followup remains overdue.
- Scheduled task: the user who created the task. If remote push cannot be
  queued, the Android app schedules a local fallback on that device.

## Verification

1. Install the current APK, open it, allow notifications, then sign in. Sign out
   and back in once after changing the OneSignal configuration.
2. In OneSignal, confirm the device subscription is opted in and its External ID
   is the lowercase admin email.
3. Run `php spark mobile:run-notification-cycle 20`. In the app's Notification
   Center, confirm Device, Server, and Scheduler all show healthy. Also inspect
   `mobile_push_notifications.status`, `error_message`, and `response_json`.
4. Create a test order, a followup, and a task a few minutes in the future.

Android does not allow an app to bypass a user's notification denial, a manual
channel disable, or force-stop. The app exposes its permission/subscription and
server-provider state so those device settings can be corrected.
