# Arattai ERP Integration

## Implemented workflows

- Incoming `chat.message.received` webhook with exact challenge response, HMAC signature validation and event-ID deduplication.
- Incoming Arattai users stored in `arattai_users` and shown in Company Settings with name, phone (when supplied), user ID and last-seen time.
- Follow-up, issuement and karigar approvals sent as conversational quick-reply messages with signed Approve / Disapprove actions. Follow-up also supports Approve - No Point.
- Approval actions call the existing ERP approval service, update ERP data once, and send the requester and acting admin a result message.
- Follow-up approval contains current details, previous approved follow-up details and an order/follow-up image when available.
- Karigar assignment sends order number, work, item size, quantity, description, priority, due date and image. Customer name, budget and advance are intentionally excluded.
- Material issuement and karigar payment messages are sent after their database transaction succeeds.
- Employee point credit/deduction messages cover follow-up decisions, task completion, daily overdue tasks, order creation, purchase, issuement, return and receiving.
- Upcoming/overdue follow-up reminders are queued automatically. Approval-pending follow-ups continue to follow the performance exclusion rules in `StaffPerformanceService`.
- Incoming free-text chat asks for an order ID/number and returns status, priority, due date, latest approved follow-up and image without customer or financial data.
- Admin and CLI preview tools send every workflow design to a supplied Arattai user ID.

## Admin setup

1. Company Settings: save Client ID and Client Secret, enable Arattai, and select the Approval Audit Reviewer.
2. Click **Connect Arattai**. Reconnect once after deployment so the token includes message and webhook READ/CREATE/UPDATE scopes.
3. Add captured admin user IDs under **Approval Admin Arattai User IDs**.
4. Click **Configure Incoming Webhook**. The ERP verifies and creates/updates the business-number conversation webhook, creates/selects its signing key and stores the selected shared secret.
5. Ask each employee/karigar/customer to send a message to the Business Number first, then copy the captured User ID into their ERP Arattai field.

Webhook URL:

`https://aabhushan.webignitors.in/integrations/attrai/webhook`

Subscribed event:

`chat.message.received`

Approved transactional templates are not required for these conversational messages, but Arattai only permits them while the recipient's conversation window is active. Business verification is still required later for business-initiated transactional template messages outside that window.

## Preview and cron

Admin: Company Settings -> **Send All Arattai Workflow Previews**.

CLI:

`php spark arattai:send-template-preview ARATTAI_USER_ID`

Hostinger cron, every minute:

`/usr/bin/php /home/USERNAME/domains/aabhushan.webignitors.in/public_html/spark mobile:run-notification-cycle 200`

Use the actual PHP path and project path shown by Hostinger. This single cycle generates mobile overdue alerts, Arattai upcoming/overdue and point alerts, and dispatches both queues.

## Database deployment

Run once after pull:

`php spark migrate`

This creates `arattai_users`, `arattai_webhook_events`, and adds webhook ID/status fields to Company Settings. No manual SQL file is needed.

