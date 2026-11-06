<?php

/** Framework-independent gate: no database, session or environment dependency. */
function aabhushan_service_is_suspended(): bool
{
    $flag = __DIR__ . '/../../public/service-suspended.flag';
    clearstatcache(true, $flag);

    return is_file($flag);
}

function aabhushan_send_suspended_response(): void
{
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
    $path = rawurldecode($path);
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Service-Suspended: 1');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');

    // Worker updates must remain valid JavaScript/HTTP 200. This replaces old
    // cached shells on the next browser-managed worker update, without an APK.
    $workerPaths = [
        '/pwa/aabhushan_app_sw.js',
        '/pwa/flutter_service_worker.js',
        '/analytics/sw.js',
        '/customer-portal-sw.js',
    ];
    $isWorker = false;
    foreach ($workerPaths as $workerPath) {
        if (str_ends_with($path, $workerPath)) {
            $isWorker = true;
            break;
        }
    }
    if ($isWorker && in_array($method, ['GET', 'HEAD'], true)) {
        http_response_code(200);
        header('Content-Type: application/javascript; charset=UTF-8');
        if ($method !== 'HEAD') {
            readfile(__DIR__ . '/../Views/service_suspended_worker.js');
        }
        return;
    }

    http_response_code(503);
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $isJson = preg_match('~(?:^|/)api(?:/|$)~i', $path)
        || str_contains($accept, 'json')
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

    if ($isJson) {
        header('Content-Type: application/json; charset=UTF-8');
        if ($method !== 'HEAD') {
            echo json_encode([
                'success' => false,
                'status' => 'suspended',
                'code' => 'SERVICE_SUSPENDED',
                'message' => 'Service suspended. Please contact your service provider to restore access.',
                'error' => 'Service suspended. Please contact your service provider to restore access.',
                'data' => null,
            ], JSON_UNESCAPED_SLASHES);
        }
        return;
    }

    header('Content-Type: text/html; charset=UTF-8');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
    if ($method !== 'HEAD') {
        readfile(__DIR__ . '/../Views/service_suspended.html');
    }
}
