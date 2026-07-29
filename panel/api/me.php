<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$u = current_user();
if (!$u) json_out(200, ['ok' => true, 'authenticated' => false]);

ok([
    'authenticated' => true,
    'user' => [
        'name'      => $u['full_name'],
        'role'      => $u['role'],
        'institute' => $u['institute_name'],
        'phone'     => preg_replace('/^(\d{4})\d{4}(\d{3})$/', '$1****$2', (string)$u['phone']),
    ],
]);
