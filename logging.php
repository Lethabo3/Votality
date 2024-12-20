<?php
if (!function_exists('logMessage')) {
    function logMessage($message, $filename = 'debug_log.txt') {
        file_put_contents($filename, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
    }
}