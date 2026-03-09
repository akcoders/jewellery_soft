<?php

/**
 * Shared-hosting fallback front controller.
 * If web root points to project root instead of /public, this forwards
 * execution to the real CI4 front controller.
 */
require __DIR__ . '/public/index.php';

