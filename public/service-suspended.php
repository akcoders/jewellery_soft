<?php

require_once __DIR__ . '/../app/Support/service_suspension.php';

if (aabhushan_service_is_suspended()) {
    aabhushan_send_suspended_response();
    exit;
}

header('Cache-Control: no-store');
header('Location: ./', true, 302);
