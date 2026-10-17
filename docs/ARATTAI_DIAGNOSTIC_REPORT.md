# Arattai webhook and demo-send diagnosis — 17 October 2026 (superseded)

The diagnostic findings below describe the pre-correction implementation. The implemented workflow and current deployment steps are documented in `docs/ARATTAI_WORKFLOW.md`.

## Confirmed production checks

- `GET /public/integrations/attrai/webhook?challenge=arattai-check-20261017`
  returned HTTP 200 with the exact challenge JSON. HTTPS routing and the verification handler are reachable.
- A POST containing a deliberately invalid signature returned HTTP 401,
  `Invalid Arattai webhook signature.` No approval action or user was submitted in that probe.
- This proves signature rejection works and a nonempty webhook secret is configured on the responding server.
  It does **not** prove that a real Arattai `Hi` event arrived, or that the current capture code is deployed.
- Production account settings, sender-queue rows and server logs were not accessible in this session.
  The local configured database could not be connected to. No real outbound message was sent.

## Local test evidence

- `vendor/bin/phpunit --no-coverage tests/unit/ArattaiRuntimeDiagnosticsTest.php`:
  **7 tests, 25 assertions passed**. These execute the real signature/capture methods and request
  builders using temporary files, fake settings, an isolated in-memory database and an HTTP mock
  that forbids network calls. They verify capture/reload/deduplication, unrelated-event handling,
  valid/missing/tampered signatures, the actual demo endpoint/template requirement and generic error.
- `vendor/bin/phpunit --no-coverage tests/unit/AttraiIntegrationWorkflowTest.php`:
  **6 tests, 52 assertions passed**. These existing tests check wiring/source structure; they are
  not evidence of successful delivery through a live Arattai account.

The local tests confirm the documented payload is supported. They cannot verify the deployed
server's file permissions or reproduce a real provider callback without production access.

## Findings in the current code

### Demo uses production messaging

`AttraiIntegrationService::sendTestMessage()` enqueues `attrai_demo`.
`WhatsappSenderQueueService::providerSettings()` routes that event to `/api/v1/transactions`.
Arattai has a separate `/api/v1/transactions/test` endpoint requiring `mode: draft|published`.
The test recipient must be active for the selected business number.

The current demo therefore does not provide draft-template testing: production template/account/billing
restrictions still apply. This mismatch is confirmed; the actual reason for the user's failed send
still requires the corresponding queue row.

### Actual demo error is hidden

`sendTestMessage()` replaces any dispatch failure with a generic exception. The sender queue stores
the more useful `http_status_code`, `error_message` and `response_payload`, but Company Settings
does not show these. There is no dedicated queue viewer in the current admin routes.

To retrieve the evidence without changing data: in Hostinger phpMyAdmin, open
`whatsapp_sender_queue`, locate the newest row with `provider_key = attrai` and
`event_key = attrai_demo`, and inspect `http_status_code` and `error_message`.
Do not share OAuth tokens, secrets, or unrelated message payloads.

### User capture can fail invisibly

The controller verifies the signature **before** calling the capture service. A missing/mismatching
signature is therefore rejected without adding a user.

The documented inbound envelope (`event: chat.message.received`, `entity.user.id/name`)
matches the parser. It writes `writable/arattai-users.json`; database migrations are not involved.
The file's parent directory must be writable by the PHP process.

If capture throws, the controller catches it and logs a `warning`, then continues processing.
Default production logging has threshold 4, while warnings are level 5, so this warning is suppressed.
An HTTP 200 response therefore does not prove the sender was persisted.

The user list currently contains name, ID, last-seen time and message type. It does not capture
phone numbers. Arattai's documented text-message sample supplies a name and ID, not a phone number.

## What is still unconfirmed

The actual `Hi` delivery may be absent, rejected for its signature, arriving at another configured
endpoint/business number, or failing during capture. The invalid-signature probe cannot choose
between these explanations. Provider delivery details and/or server request evidence are required.

Arattai sends the signature only when a webhook Shared Secret is **selected**. This is separate
from the OAuth Client Secret. Check the Conversation subscription, `chat.message.received` event,
active selected secret, exact endpoint, and intended Business Number together.

## Narrow corrections indicated by the diagnosis

1. Give the demo an explicit test mode and use `/transactions/test` with `mode`.
2. Display the failed queue ID and sanitized provider error in Admin.
3. Record webhook arrival, signature result and capture result without saving message bodies or secrets.
4. Make capture-storage errors visible to the authorized admin; do not bypass signature validation.

These are recommendations from this diagnostic run, not claims that production has been fixed.

## Official references

- [Webhook verification, signatures and incoming envelope](https://www.arattaibusiness.in/business-platform/developers/webhooks/)
- [Production and test transactional messaging](https://www.arattaibusiness.in/business-platform/developers/transactional/)
- [OAuth and access-token requirements](https://www.arattaibusiness.in/business-platform/developers/oauth/)
