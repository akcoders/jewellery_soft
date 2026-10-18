# Arattai Business Integration

## Admin setup

Open **Admin > Company Settings > Arattai Approval & Transaction Integration**.

1. Add the exact **Authorized Redirect URI** shown there to the server-based client in the Arattai API Console.
2. Enter Client ID and Client Secret, enable Arattai, select the Approval Audit Reviewer, and save.
3. Click **Connect Arattai**. The ERP obtains access/refresh tokens and detects Company ID, Business ID, and Business Number ID automatically.
4. Click **Create & Submit ERP Templates**. The ERP creates and submits:
   - `Aabhushan ERP approval alert`
   - `Aabhushan ERP order update`
5. Wait for Arattai to change each status from `under_review` to `approved`.

Bearer tokens are not entered manually. The ERP refreshes the one-hour access token using the saved refresh token.

## Webhook setup

Use the Webhook URL displayed by the deployed ERP. The expected current URL is:

`https://aabhushan.webignitors.in/public/integrations/attrai/webhook`

In **Arattai Business Platform > Developer space > Webhooks** configure **Conversation** and select `chat.message.received`, `chat.message.sent`, `chat.message.delivered`, and `chat.message.failed`.

Arattai verifies the URL with `GET ?challenge=...`; the ERP echoes the challenge as JSON. Generate/select a Shared Secret in Arattai, then paste the same value into the ERP **Webhook Secret** field. POST events are accepted only when `X-Hub-Signature-256` matches the Base64 HMAC-SHA256 signature of the raw body.

## Approval recipients

**Approval Admin Arattai User IDs** must contain Arattai user IDs, not phone numbers. The ID is available at `entity.user.id` in an incoming `chat.message.received` event.

For easy setup, ask the intended admin to send `Hi` to the Arattai Business Number and refresh Company Settings. The signed webhook stores the last 100 senders in `writable/arattai-users.json` and displays them under **Recent Arattai Users**. Copy the required ID into **Approval Admin Arattai User IDs**.

Direct approval buttons use Arattai conversational input messages, so the approval admin must be inside Arattai's active conversation window. Approval actions remain Approve, Approve - No Point (follow-up only), and Disapprove. Signed actions expire after seven days and already-reviewed requests are not processed twice.

## Hostinger cron

Create a **Custom** cron every minute (`* * * * *`):

```bash
cd /home/u897223014/domains/webignitors.in/public_html/aabhushan && /usr/bin/php spark whatsapp:dispatch-sender-queue 100
```
