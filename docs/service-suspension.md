# Server-wide service suspension

This release ships **suspended** (`public/service-suspended.flag` is present).
Deploy the complete commit to the Aabhushan PHP host; pushing to GitHub alone
does not change a server unless that host already pulls/deploys automatically.
No database migration, Composer install, Flutter build or APK update is required.

## Hosting deployment

From the existing production checkout:

```sh
git pull --ff-only origin main
php scripts/service-access.php status
```

Deploy `.htaccess` at the project root and `public/.htaccess` as well as all new
PHP/view files and the flag. Purge Hostinger/CDN caches for **all paths** and
disable any CDN rule that serves cached app pages, uploads or API responses
without reaching the origin. Confirm both domain aliases use the same checkout.

On Apache/LiteSpeed, mod_rewrite and `.htaccess` overrides must be enabled.
The domain document root must be this repository root or its `public/` folder
(no trailing slash in the document-root setting). The `[END]` suspension rules
run before static-file exceptions and nested PWA/analytics rewrites, so even
existing uploads and `pwa/index.html` are blocked. For other server layouts,
adjust the flag's absolute path in both rewrite conditions.

For **Nginx**, `.htaccess` has no effect. Add this inside the existing `server`
block before static-file locations, retaining the existing PHP-FPM handler:

```nginx
# Replace this with the actual absolute public-directory path. Reset on every
# internal redirect, and exclude the response handler to avoid a rewrite loop.
set $aabhushan_suspended 0;
if (-f /absolute/path/to/aabhushan/public/service-suspended.flag) {
    set $aabhushan_suspended 1;
}
if ($uri = /service-suspended.php) {
    set $aabhushan_suspended 0;
}
if ($aabhushan_suspended = 1) {
    rewrite ^ /service-suspended.php last;
}
location = /service-suspended.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME /absolute/path/to/aabhushan/public/service-suspended.php;
    # Use the same fastcgi_pass as the existing PHP location.
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

Validate with `nginx -t` before reloading. The PHP front controller independently blocks every
dynamic request even without web-server rewrite support, but static files must
also be gated at the web server.

## Change status without changing code or updating apps

Use the hosting Terminal or SSH, from the repository directory:

```sh
php scripts/service-access.php status
php scripts/service-access.php resume
php scripts/service-access.php suspend
```

Alternatively remove/create `public/service-suspended.flag` in the hosting file
manager. There is no public unlock URL, bypass token or admin-login exception.
The flag is read on every request, independently of framework/config caches.
The initial flag is versioned intentionally to activate this release; a later
deployment that restores it will suspend access again. Keep the chosen state
in mind when replacing the checkout or deploying a fresh release.

## Responses and limits

- Website, customer portal, PWA, analytics and downloads return HTTP 503 with a
  self-contained **Service suspended** page and no-store headers.
- All API paths (including `/index.php/api/...`), JSON and AJAX requests return
  HTTP 503 with `success: false`, `code: SERVICE_SUSPENDED`, and a readable
  `message`/`error`. Old bearer tokens do not bypass the gate. Controllers,
  sessions and database connections never start for these requests.
- Existing app versions using this backend are denied server data and writes.
  Their existing error UI determines how the message is displayed. A native
  screen cannot be replaced remotely with a new full-screen page if that
  version never implemented it.
- Known PWA service-worker URLs return a small HTTP 200 JavaScript update that
  clears Aabhushan shell caches and navigates open controlled windows to the
  server suspension page. This occurs when the browser checks for a worker
  update; no instant delivery to offline devices is possible.
- Suspension also blocks new `spark` CLI invocations before framework/database
  boot, pausing the documented mobile/Arattai/WhatsApp cron commands. A job
  already executing at activation must finish or be stopped by the hosting
  operator. It does not terminate separate long-running workers or the MCP Node process.
  MCP tools calling this PHP API receive the same denial; already cached
  responses outside this server cannot be recalled.
- Already-downloaded/offline device data and responses cached at an external
  CDN cannot be withdrawn by a GitHub commit. Purge the CDN at deployment.

Verify `/`, `/admin/login`, `/api/mobile/auth/login`, `/pwa/index.html`,
`/analytics/index.html`, and an existing `/uploads/...` URL return 503.
`/pwa/aabhushan_app_sw.js` must instead return HTTP 200 JavaScript (never HTML).
Test a valid old app login/token too. Restore access with `resume` and confirm
the normal website, API and static assets work again.

## Local gate checks

Run `python3 tests/service_suspension_http_test.py /path/to/php`.
These checks use a temporary directory and never resume the deployed service.
They cover real HTTP responses, all methods, API envelopes, worker delivery,
and suspend/resume without booting CodeIgniter. Web-server rewrite deployment
still requires the production path checks above.
