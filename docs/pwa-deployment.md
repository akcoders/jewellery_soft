# Aabhushan ERP PWA deployment

The Flutter PWA is built for this public path:

`https://aabhushan.webignitors.in/pwa/`

## Upload

Generate the upload-ready build and ZIP with:

```bash
cd app_kit/FlutKit
bash tool/build_pwa.sh
```

Upload **the contents** of `app_kit/FlutKit/build/web/` to:

`/home/u897223014/domains/webignitors.in/public_html/aabhushan/public/pwa/`

Keep the directory structure intact, including these files:

- `.htaccess`
- `index.html`
- `manifest.json`
- `flutter_service_worker.js`
- `aabhushan_pwa_bridge.js`
- `push/onesignal/OneSignalSDKWorker.js`

Do not password-protect the PWA or its service-worker directory.

## Production environment

Add this to the CodeIgniter `.env` file:

```ini
pwa.baseURL = 'https://aabhushan.webignitors.in/pwa/'
```

This makes a web notification open the correct order or task inside the PWA.

## OneSignal web platform (one-time dashboard setup)

Use the existing OneSignal app ID so the same external user ID can receive push on both Android and the PWA.

In OneSignal, open **Settings > Push & In-App > Web** and choose **Custom Code**. Configure:

- Site name: `Aabhushan ERP`
- Site URL / origin: `https://aabhushan.webignitors.in`
- Service worker path: `/pwa/push/onesignal/OneSignalSDKWorker.js`
- Service worker scope: `/pwa/push/onesignal/`
- Default icon: `https://aabhushan.webignitors.in/pwa/icons/Icon-512.png`
- Auto resubscribe: enabled

The worker must be publicly reachable with HTTP 200 and JavaScript content type:

`https://aabhushan.webignitors.in/pwa/push/onesignal/OneSignalSDKWorker.js`

Web push requires HTTPS, a supported non-private browser window and user permission. On iPhone/iPad, web push requires iOS/iPadOS 16.4 or newer and the PWA must first be added to the Home Screen.

## Existing notification cron

Keep the existing notification cycle cron running every minute. No separate PWA cron is required because Android and web subscriptions share the same OneSignal external user identity.

## Rebuild for another URL path

If the PWA is deployed at a path other than `/pwa/`, rebuild with the matching path and update `RewriteBase` in `web/.htaccess`:

```bash
cd app_kit/FlutKit
bash tool/build_pwa.sh /your-path/ Aabhushan-ERP-PWA-custom.zip
```

Also update `pwa.baseURL` and the OneSignal service-worker path/scope to that same public path.
