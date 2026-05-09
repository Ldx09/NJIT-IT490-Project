<?php
function notify_log(string $message): void {
    $log_file = __DIR__ . '/logs/notifications.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}
