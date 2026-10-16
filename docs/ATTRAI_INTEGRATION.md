# ATTRAI Integration

## Webhook URL

Current local URL:

`https://jewellery-soft.test/integrations/attrai/webhook`

Production route after deploying this change:

`https://aabhushan.webignitors.in/public/integrations/attrai/webhook`

If production `app.baseURL` does not contain `/public/`, use:

`https://aabhushan.webignitors.in/integrations/attrai/webhook`

The final URL is also shown read-only in **Admin > Company Settings > ATTRAI Approval & Assignment Integration**, so the deployed server's own base URL remains the source of truth.

ATTRAI must call the webhook with `POST`. Authenticate it using any one of:

- `X-Attrai-Webhook-Secret: <saved-secret>`
- `X-Webhook-Secret: <saved-secret>`
- `Authorization: Bearer <saved-secret>`
- `?token=<saved-secret>` on the webhook URL

The action selected in ATTRAI must be returned in an `action_id`, `actionId`, `button_id`, `buttonId`, `callback_data`, `callbackData`, `payload`, or nested `id` field. The value must be the signed action ID sent by the ERP in the button payload.

## Admin settings required

Configure these values in Company Settings:

1. Enable ATTRAI.
2. Enter the ATTRAI send API URL and HTTP method.
3. Enter auth type, header, token/key and sender ID supplied by ATTRAI.
4. Enter approval admin mobile numbers, including country code.
5. Select the admin/owner whose name will be used in the approval audit.
6. Save or generate the webhook secret and register the webhook URL + secret in ATTRAI.
7. Map ATTRAI's text-message and interactive-button JSON using the two body-template fields.

Details required from the ATTRAI account/provider:

- send-message API URL and HTTP method;
- authentication type, auth header name and API token/key;
- sender/device/channel ID;
- exact text/image message request JSON;
- exact interactive-button request JSON;
- sample button-click webhook JSON;
- public HTTPS webhook registration access.

ERP-side numbers to fill:

- approval admin numbers in Company Settings;
- order ATTRAI information number on the order;
- karigar ATTRAI number on the karigar profile;
- follower ATTRAI number on the linked employee profile.

The test form defaults to `918261973645`. It sends immediately and reports a real provider failure instead of showing a false success.

## Events connected

- All order-created, status, ready, delayed and over-budget updates go through ATTRAI when ATTRAI is enabled.
- Order messages use the order's dedicated ATTRAI information number first and include the order image (company logo fallback).
- Assignment immediately sends the order image and details to both the karigar and the assigned follower. Dedicated ATTRAI numbers are preferred; regular mobile/phone is the fallback.
- Follow-up, issuement, customer creation and karigar creation approval requests go to configured admin numbers.
- Approvals remain available from all three channels: ATTRAI, web/mobile Approval Desk, and the app. The first decision is final; another channel receives `already_reviewed`/no duplicate processing.
- Follow-up actions: **Approve (+2)**, **Approve - No Point (+0)** and **Disapprove (-2)**.
- Other approval actions: **Approve** and **Disapprove**.
- The callback calls `MobileApprovalService`, so all existing validation, stock posting, follow-up points and audit fields remain active.
- Signed action IDs expire after seven days. Replayed callbacks return `already_reviewed` and do not process the request twice.

Outbound `event` values:

- `approval_requested`
- `order_created`
- `order_status_changed`
- `order_ready`
- `order_delay_daily`
- `order_over_budget`
- `karigar_assigned`
- `follower_assigned`

Default outbound fields are `to`, `message`, `sender`, `message_type`, `media_url`, `event`, and `reference`. Approval messages additionally contain `actions` and `webhook_url`. The body templates allow adapting these fields to ATTRAI's exact API contract.

Minimal button callback example:

```json
{
  "event": "approval_action",
  "action_id": "attrai.<signed-payload>.<signature>"
}
```

## Suggested approval message

```text
*Approval Required: Follow-up*
Request: #123
Order ORD-001 follow-up: CAD Completed
Order: ORD-001
Stage: CAD Completed
Update: Customer design confirmation received
Next follow-up: 2026-10-17 14:00:00
Submitted by: Staff Name

Select *Approve* or *Disapprove* in ATTRAI.
For follow-up, *Approve - No Point* is also available.
```

Issuement messages similarly include karigar, location, purpose and material-line counts. Customer/karigar creation messages include name, phone and department where available.

## Queue cron

Normal event messages are queued. Keep this command running every minute:

```cron
* * * * * cd /ABSOLUTE/PATH/TO/jewellery_soft && /ABSOLUTE/PATH/TO/php spark whatsapp:dispatch-sender-queue 100 >> writable/logs/attrai-sender.log 2>&1
```

The admin demo button dispatches its own queue row immediately and does not wait for cron.
