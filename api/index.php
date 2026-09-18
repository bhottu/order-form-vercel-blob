<?php

try {
    require __DIR__.'/../public/index.php';
} catch (\Throwable $e) {
    error_log('=== LARAVEL VERCEL ERROR ===');
    error_log('LOG_CHANNEL=' . ($_ENV['LOG_CHANNEL'] ?? 'NOT_SET'));
    error_log('LOG_STACK=' . ($_ENV['LOG_STACK'] ?? 'NOT_SET'));
    error_log(get_class($e));
    error_log($e->getMessage());
    error_log($e->getFile().':'.$e->getLine());
    error_log($e->getTraceAsString());

    http_response_code(500);
    echo 'Laravel startup error: '.$e->getMessage();
}