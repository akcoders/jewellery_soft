# Aabhushan Business Intelligence PWA

The separate, analytics-only PWA is served from:

`https://aabhushan.webignitors.in/analytics/`

Deploy the tracked `public/analytics/` directory with the PHP application. No Node or Flutter build is required. The PWA uses the existing admin mobile login and the read-only `api/mobile/analytics` endpoint.

After deployment, purge the hosting/CDN cache once. The analytics service worker updates its own `aabhushan-intelligence-*` cache without touching the operational PWA cache.
