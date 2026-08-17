<?php
/**
 * حساب کاربر: ساخت رمز، تغییر رمز، و بازیابی رمز با پیامک.
 *
 * بازیابی عمداً از راه همان شمارهٔ موبایل است. کاربر قبلاً ثابت کرده
 * که شماره مال اوست، و ایمیل هم در کار نیست چون بیشتر زبان‌آموزها
 * ایمیلی که واقعاً بخوانند ندارند.
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_creds.php';

require_post();
$in     = body_json();
$action = trim((string)($in['action'] ?? ''));

switch ($action) {

/* ─────────── وضعیت حساب ─────────── */
case 'state':
    $u  = require_user();
    $st = db()->prepare('SELECT username, pass_hash FROM app_user WHERE id = ?');
    $st->execute([$u['id']]);
    $r = $st->fetch() ?: [];
    ok([
        'username'    => $r['username'] ?? null,
        'hasPassword' => !empty($r['pass_hash']),
        'phone'       => mask_phone((string)$u['phone']),
    ]);

/* ─────────── آیا این نام کاربری آزاد است؟ ─────────── */
/*
 * پیش از فرستادن فرم صدا زده می‌شود تا کاربر وسط ثبت‌نام با «این نام
 * گرفته شده» غافلگیر نشود. فهرست کاربران را لو نمی‌دهد: فقط برای
 * کسی که وارد شده یا در حال ساخت حساب است کار می‌کند، و پاسخ فقط
 * آزاد/نیست است.
 */
case 'checkUsername':
    $u = username_normalize((string)($in['username'] ?? ''));
    if (!preg_match('/^[a-z0-9._-]{4,64}$/', $u) || preg_match('/^09\d{9}$/', $u)) {
        ok(['username' => $u, 'free' => false, 'reason' => 'invalid']);
    }
    if (!rate_ok('uname_ip', client_ip(), 60, 3600)) {
        fail(429, 'rate_limited', 'تلاش‌های زیاد.');
    }
    ok(['username' => $u, 'free' => username_free($u)]);

/* ─────────── ساخت یا تغییر رمز ─────────── */
/*
 * اگر کاربر رمز دارد، رمز فعلی لازم است. اگر ندارد — یعنی تا امروز
 * فقط با پیامک وارد می‌شده — نشست معتبرش کافی است، چون همین حالا با
 * کد یک‌بارمصرف هویتش را ثابت کرده.
 */
case 'setPassword':
    $u   = require_user();
    $new = (string)($in['password'] ?? '');
    password_check($new);

    $st = db()->prepare('SELECT pass_hash FROM app_user WHERE id = ?');
    $st->execute([$u['id']]);
    $current = $st->fetchColumn();

    if ($current) {
        if (!password_verify((string)($in['current'] ?? ''), (string)$current)) {
            usleep(400000);
            fail(401, 'bad_credentials', 'رمز فعلی درست نیست.');
        }
    }

    // نام کاربری فقط وقتی ست می‌شود که هنوز نداشته باشد یا خودش بخواهد عوضش کند
    $wantName = username_normalize((string)($in['username'] ?? ''));
    if ($wantName !== '') {
        username_check($wantName);
        if (!username_free($wantName, (string)$u['id'])) {
            fail(409, 'username_taken', 'این نام کاربری قبلاً گرفته شده. یکی دیگر انتخاب کنید.');
        }
        db()->prepare('UPDATE app_user SET username = ? WHERE id = ?')->execute([$wantName, $u['id']]);
    }

    set_password((string)$u['id'], $new, $_COOKIE[SESSION_COOKIE] ?? null);
    audit('account.password_set', (string)$u['id'], ['hadPassword' => (bool)$current]);
    ok(['username' => $wantName !== '' ? $wantName : null]);

/* ─────────── بازیابی رمز با کد پیامکی ─────────── */
/*
 * کد را همان otp-request.php می‌فرستد. اینجا فقط مصرفش می‌کنیم و رمز
 * تازه را می‌نشانیم — بدون اینکه لازم باشد کاربر رمز قبلی را بداند،
 * چون کل ماجرای «فراموش کردم» همین است.
 */
case 'resetPassword':
    $phone = normalize_phone((string)($in['phone'] ?? ''));
    if ($phone === null) fail(400, 'invalid_phone', 'شمارهٔ موبایل معتبر نیست.');
    $new = (string)($in['password'] ?? '');
    password_check($new);

    $c   = cfg();
    $ip  = client_ip();
    if (!rate_ok('reset_ip', $ip, 20, 3600)) {
        fail(429, 'rate_limited', 'تلاش‌های زیاد. بعداً امتحان کنید.');
    }

    $code = preg_replace('/\D/', '', en_digits_creds((string)($in['code'] ?? ''))) ?? '';
    if (strlen($code) !== 5) fail(400, 'invalid_code', 'کد باید ۵ رقم باشد.');

    $st = db()->prepare(
        'SELECT id, code_hash, attempts FROM otp_code
          WHERE phone = ? AND consumed_at IS NULL AND expires_at > ?
          ORDER BY created_at DESC LIMIT 1'
    );
    $st->execute([$phone, now_utc()]);
    $row = $st->fetch();
    if (!$row) fail(400, 'expired', 'کد منقضی شده. دوباره درخواست کنید.');

    $max = (int)($c['otp_max_attempts'] ?? 5);
    if ((int)$row['attempts'] >= $max) {
        db()->prepare('UPDATE otp_code SET consumed_at = ?, pending_code = NULL WHERE id = ?')
            ->execute([now_utc(), $row['id']]);
        fail(429, 'too_many_attempts', 'تلاش زیاد. کد باطل شد؛ دوباره درخواست کنید.');
    }

    $candidate = hash_hmac('sha256', $phone . ':' . $code, (string)$c['otp_pepper']);
    if (!hash_equals((string)$row['code_hash'], $candidate)) {
        db()->prepare('UPDATE otp_code SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
        fail(400, 'wrong_code', 'کد اشتباه است.', ['attemptsLeft' => max(0, $max - ((int)$row['attempts'] + 1))]);
    }

    db()->prepare('UPDATE otp_code SET consumed_at = ?, pending_code = NULL WHERE id = ?')
        ->execute([now_utc(), $row['id']]);

    $st = db()->prepare("SELECT id FROM app_user WHERE phone = ? AND status = 'active'");
    $st->execute([$phone]);
    $uid = $st->fetchColumn();
    /*
     * اگر حسابی با این شماره نیست، همان پیام موفق را می‌دهیم.
     * پاسخ متفاوت یعنی هر کسی می‌تواند با امتحان‌کردن شماره‌ها بفهمد
     * چه کسی در سامانه حساب دارد.
     */
    if (!$uid) {
        audit('account.reset_unknown', null, ['phone' => $phone]);
        ok(['reset' => true]);
    }

    set_password((string)$uid, $new);
    audit('account.password_reset', (string)$uid, []);
    ok(['reset' => true]);

default:
    fail(400, 'unknown_action', 'درخواست نامشخص.');
}

function en_digits_creds(string $s): string
{
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($ar, $en, str_replace($fa, $en, $s));
}

function mask_phone(string $p): string
{
    return mb_strlen($p) >= 11 ? mb_substr($p, 0, 4) . '****' . mb_substr($p, -3) : $p;
}
