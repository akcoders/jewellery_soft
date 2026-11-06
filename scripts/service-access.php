<?php

// Run from SSH/hosting Terminal. There is deliberately no web unlock URL.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$flag = __DIR__ . '/../public/service-suspended.flag';
$action = $argv[1] ?? 'status';

if (!in_array($action, ['suspend', 'resume', 'status'], true)) {
    fwrite(STDERR, "Usage: php scripts/service-access.php suspend|resume|status\n");
    exit(2);
}

if ($action === 'suspend' && !is_file($flag)) {
    // Exclusive creation prevents a partially written flag from being needed.
    $handle = @fopen($flag, 'x');
    if ($handle === false) {
        fwrite(STDERR, "Could not create suspension flag. Check directory permissions.\n");
        exit(1);
    }
    fwrite($handle, "Service suspended. Managed by scripts/service-access.php\n");
    fclose($handle);
}
if ($action === 'resume' && is_file($flag) && !unlink($flag)) {
    fwrite(STDERR, "Could not remove suspension flag. Check directory permissions.\n");
    exit(1);
}
clearstatcache(true, $flag);
echo is_file($flag) ? "SUSPENDED\n" : "ACTIVE\n";
