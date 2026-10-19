# Arattai ERP Integration

## Implemented workflows

- Incoming message and outbound sent/delivered/failed webhooks with exact challenge response, HMAC signature validation and event-ID deduplication.
- Incoming Arattai users stored in `arattai_users` and shown in Company Settings with name, phone (when supplied), user ID and last-seen time.
- Follow-up, issuement and karigar approvals sent as conversational quick-reply messages with signed Approve / Disapprove actions. Follow-up also supports Approve - No Point.
- Approval actions call the existing ERP approval service, update ERP data once, and send the requester and acting admin a result message.
- Follow-up approval contains current details, previous approved follow-up details and an order/follow-up image when available.
- Karigar assignment sends order number, work, item size, quantity, description, priority, due date and image. Customer name, budget and advance are intentionally excluded.
- Material issuement and karigar payment messages are sent after their database transaction succeeds.
- Employee point credit/deduction messages cover follow-up decisions, task completion, daily overdue tasks, order creation, purchase, issuement, return and receiving.
- When finished jewellery is received (admin panel, PWA, Android app or the Arattai receiving conversation), every approval admin gets an `order_rating_request` quick-reply card with ⭐ 1-5 options and the finish photo. Tapping a star stores `completion_rating` / `rated_by` / `rated_at` on the order (same as the PWA "Rate completed order" action), confirms back to the admin, and sends the order follower the matching ±points message. The card is sent once per order per admin; the reply requires the mapped ERP user to hold `orders.assign`.
- Upcoming/overdue follow-up reminders are queued automatically. Approval-pending follow-ups continue to follow the performance exclusion rules in `StaffPerformanceService`.
- Incoming free-text chat asks for an order ID/number and returns status, priority, due date, latest approved follow-up and image without customer or financial data.
- Admin and CLI preview tools send one selected workflow design at a time to stay below the conversational API rate limit.
- **Admin > Arattai Message Audit** lists the reason each message was sent, its image, API result, delivery state and exact failure reason.

## Admin setup

1. Company Settings: save Client ID and Client Secret, enable Arattai, and select the Approval Audit Reviewer.
2. Click **Connect Arattai**. Reconnect once after deployment so the token includes message and webhook READ/CREATE/UPDATE scopes.
3. Add captured admin user IDs under **Approval Admin Arattai User IDs**.
4. Click **Configure Incoming Webhook**. The ERP verifies and creates/updates the business-number conversation webhook, creates/selects its signing key and stores the selected shared secret.
5. Ask each employee/karigar/customer to send a message to the Business Number first, then copy the captured User ID into their ERP Arattai field.

Webhook URL:

`https://aabhushan.webignitors.in/integrations/attrai/webhook`

Subscribed events:

`chat.message.received`, `chat.message.sent`, `chat.message.delivered`, `chat.message.failed`

Approved transactional templates are not required for these conversational messages, but Arattai only permits them while the recipient's conversation window is active. Business verification is still required later for business-initiated transactional template messages outside that window.

## Preview and cron

Admin: Company Settings -> **Send One Arattai Workflow Preview**.

Arattai's live quick-reply endpoint currently accepts each option as a `label` only, even though one generated OpenAPI table also lists `id`. Approval decisions are therefore resolved from the provider `ack_key`, the queued approval request, the authenticated Arattai admin user, and the selected label. Image notifications use the documented `display/image` shape (`link` plus a caption of at most 200 characters); approval messages use an image header with their full interactive body.

CLI:

`php spark arattai:send-template-preview ARATTAI_USER_ID order_status`

Send the current pending approval backlog (duplicates are skipped; at most eight new messages are sent immediately and the rest remain queued):

`php spark arattai:send-pending-approvals 25`

Only use `--force` when an already-queued approval must deliberately be resent.

Hostinger cron, every minute:

`/usr/bin/php /home/USERNAME/domains/aabhushan.webignitors.in/public_html/spark mobile:run-notification-cycle 200`

Use the actual PHP path and project path shown by Hostinger. This single cycle generates mobile overdue alerts, Arattai upcoming/overdue and point alerts, and dispatches both queues.

## Database deployment

Run once after pull:

`php spark migrate`

This creates `arattai_users`, `arattai_webhook_events`, adds webhook ID/status fields to Company Settings, and adds outbound delivery tracking fields to the sender queue. No manual SQL file is needed.

