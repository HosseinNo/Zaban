<?php
/**
 * کاربر فعلی. نقش از عضویت می‌آید نه از ستون app_user.role — چون یک
 * کاربر می‌تواند در یک آموزشگاه مدرس و در دیگری زبان‌آموز باشد.
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$u = current_user();
if (!$u) json_out(200, ['ok' => true, 'authenticated' => false]);

$st = db()->prepare(
    'SELECT m.role, i.name FROM membership m JOIN institute i ON i.id = m.institute_id
      WHERE m.user_id = ? AND m.status = ? ORDER BY m.created_at ASC LIMIT 1'
);
$st->execute([$u['id'], 'active']);
$mem = $st->fetch();

ok([
    'authenticated' => true,
    'user' => [
        'name'         => $u['full_name'],
        'role'         => $mem ? (string)$mem['role'] : (string)$u['role'],
        'institute'    => $mem ? (string)$mem['name'] : $u['institute_name'],
        'hasWorkspace' => (bool)$mem,
        'phone'        => preg_replace('/^(\d{4})\d{4}(\d{3})$/', '$1****$2', (string)$u['phone']),
    ],
]);
