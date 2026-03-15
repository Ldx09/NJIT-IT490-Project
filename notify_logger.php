<?php

require_once __DIR__ . '/notify_config.php';

function notify_log(string $message): void {
    $log_dir = dirname(NOTIFY_LOG);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents(NOTIFY_LOG, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}