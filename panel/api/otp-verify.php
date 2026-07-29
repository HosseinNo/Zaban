<?php
/**
 * تأیید کد و ساخت نشست.
 *
 * اگر کاربر تازه باشد و نام آموزشگاه داده شده باشد، همان‌جا ساخته می‌شود.
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

require_post();

$in       = body_json();
$phone    = normalize_phone((string)($in['phone'] ?? ''));
$codeRaw  = (string)($in['code'] ?? '');
$fullName = trim((string)($in['fullName'] ?? ''));
$instName = trim((string)($in['instituteName'] ?? ''));

if ($phone === null) fail(400, 'invalid_phone', 'شمارهٔ موبایل معتبر نیست.');

// ارقام فارسی در کد هم پذیرفته می‌شود
$fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
$en = ['0','1','2','3','4','5','6','7','8','9'];
$code = preg_replace('/\D/', '', str_replace($fa, $en, $codeRaw)) ?? '';
if (strlen($code) !== 5) fail(400, 'invalid_code', 'کد باید ۵ رقم باشد.');

$c  = cfg();
$db = db();
$ip = client_ip();

if (!rate_ok('otp_verify_ip', $ip, 30, 3600)) {
    fail(429, 'rate_limited', 'تلاش‌های زیاد. بعداً امتحان کنید.');
}

$st = $db->prepare(
    'SELECT id, code_hash, attempts FROM otp_code
      WHERE phone = ? AND consumed_at IS NULL AND expires_at > ?
      ORDER BY created_at DESC LIMIT 1'
);
$st->execute([$phone, now_utc()]);
$row = $st->fetch();

if (!$row) fail(400, 'expired', 'کد منقضی شده. دوباره درخواست کنید.');

$maxAttempts = (int)($c['otp_max_attempts'] ?? 5);
if ((int)$row['attempts'] >= $maxAttempts) {
    $db->prepare('UPDATE otp_code SET consumed_at = ? WHERE id = ?')->execute([now_utc(), $row['id']]);
    audit('otp.too_many_attempts', null, ['phone' => $phone]);
    fail(429, 'too_many_attempts', 'تلاش زیاد. کد باطل شد؛ دوباره درخواست کنید.');
}

$candidate = hash_hmac('sha256', $phone . ':' . $code, (string)$c['otp_pepper']);

if (!hash_equals((string)$row['code_hash'], $candidate)) {
    $db->prepare('UPDATE otp_code SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
    $left = $maxAttempts - ((int)$row['attempts'] + 1);
    fail(400, 'wrong_code', 'کد اشتباه است.', ['attemptsLeft' => max(0, $left)]);
}

$st = $db->prepare('SELECT id, full_name, role, institute_name, status FROM app_user WHERE phone = ?');
$st->execute([$phone]);
$user = $st->fetch();

if ($user && $user['status'] !== 'active') {
    $db->prepare('UPDATE otp_code SET consumed_at = ? WHERE id = ?')->execute([now_utc(), $row['id']]);
    fail(403, 'account_disabled', 'این حساب غیرفعال است. با پشتیبانی تماس بگیرید.');
}

/*
 * کاربر تازه است و هنوز نامش را نداریم.
 *
 * کد را اینجا عمداً مصرف نمی‌کنیم: اگر می‌سوزاندیمش، کاربر پیام
 * «نامت را بده» می‌گرفت ولی کدش دیگر معتبر نبود و هرگز نمی‌توانست
 * ثبت‌نام را تمام کند. کلاینت همین کد را با نام دوباره می‌فرستد.
 */
if (!$user && $fullName === '') {
    ok(['needsProfile' => true, 'authenticated' => false]);
}

// از اینجا به بعد ورود قطعی است، پس کد یک‌بارمصرف سوزانده می‌شود
$db->prepare('UPDATE otp_code SET consumed_at = ? WHERE id = ?')->execute([now_utc(), $row['id']]);

$isNew = false;
if (!$user) {
    $id = bin2hex(random_bytes(16));
    $db->prepare(
        'INSERT INTO app_user (id, phone, full_name, institute_name, role, phone_verified_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$id, $phone, $fullName, ($instName !== '' ? $instName : null),
                $instName !== '' ? 'manager' : 'student', now_utc(), now_utc()]);
    $user  = ['id' => $id, 'full_name' => $fullName, 'role' => $instName !== '' ? 'manager' : 'student',
              'institute_name' => $instName ?: null];
    $isNew = true;
    audit('user.created', $id, ['phone' => $phone]);
} else {
    $db->prepare('UPDATE app_user SET last_login_at = ?, phone_verified_at = COALESCE(phone_verified_at, ?) WHERE id = ?')
       ->execute([now_utc(), now_utc(), $user['id']]);
}

issue_session((string)$user['id']);
audit('auth.login', (string)$user['id'], ['new' => $isNew]);

ok([
    'isNew' => $isNew,
    'user'  => [
        'name'      => $user['full_name'],
        'role'      => $user['role'],
        'institute' => $user['institute_name'],
    ],
]);
