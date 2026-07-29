<?php
/**
 * بررسی سلامت. پنل از این استفاده می‌کند تا بفهمد بک‌اند نصب شده یا نه؛
 * اگر نبود، خودش را در حالت نمایشی بالا می‌آورد.
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$dbOk = false;
try { db()->query('SELECT 1'); $dbOk = true; } catch (Throwable $e) {}

$c = cfg();
json_out($dbOk ? 200 : 503, [
    'ok'       => $dbOk,
    'api'      => 'talkora',
    'database' => $dbOk,
    'sms'      => !empty($c['sms_dry_run']) ? 'dry_run'
                 : (!empty($c['smsir_api_key']) ? 'configured' : 'missing'),
    'time'     => gmdate('c'),
]);
