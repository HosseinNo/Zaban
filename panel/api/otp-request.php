<?php
/**
 * درخواست کد ورود.
 *
 * قواعد بند S.8:
 *  - کد ۵ رقمی، اعتبار ۲ دقیقه
 *  - فقط HMAC کد ذخیره می‌شود، نه خود کد
 *  - فاصلهٔ ۶۰ ثانیه بین دو ارسال
 *  - سقف ۵ در ساعت برای شماره، ۲۰ در ساعت برای IP
 *  - پاسخ برای شمارهٔ موجود و ناموجود یکسان است تا فهرست کاربران
 *    قابل استخراج نباشد
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_sms.php';

require_post();

$in    = body_json();
$phone = normalize_phone((string)($in['phone'] ?? ''));

if ($phone === null) {
    fail(400, 'invalid_phone', 'شمارهٔ موبایل باید ۱۱ رقم و با ۰۹ شروع شود.');
}

$c  = cfg();
$ip = client_ip();

if (!rate_ok('otp_ip', $ip, (int)($c['rate_ip_hour'] ?? 20), 3600)) {
    audit('otp.rate_limited_ip', null, ['ip' => $ip]);
    fail(429, 'rate_limited', 'تعداد درخواست‌ها زیاد است. یک ساعت دیگر تلاش کنید.');
}
if (!rate_ok('otp_phone', $phone, (int)($c['rate_phone_hour'] ?? 5), 3600)) {
    audit('otp.rate_limited_phone', null, ['phone' => $phone]);
    fail(429, 'rate_limited', 'برای این شماره درخواست زیادی ثبت شده. یک ساعت دیگر تلاش کنید.');
}

$db = db();

// فاصلهٔ ارسال دوباره
$st = $db->prepare(
    'SELECT created_at FROM otp_code
      WHERE phone = ? AND consumed_at IS NULL
      ORDER BY created_at DESC LIMIT 1'
);
$st->execute([$phone]);
$last = $st->fetchColumn();
$cooldown = (int)($c['resend_cooldown'] ?? 60);
if ($last && (time() - strtotime((string)$last . ' UTC')) < $cooldown) {
    $wait = $cooldown - (time() - strtotime((string)$last . ' UTC'));
    fail(429, 'cooldown', 'کمی صبر کنید و دوباره تلاش کنید.', ['retryAfter' => $wait]);
}

// کدهای قبلیِ همین شماره باطل می‌شوند
$db->prepare('UPDATE otp_code SET consumed_at = ? WHERE phone = ? AND consumed_at IS NULL')
   ->execute([now_utc(), $phone]);

$code = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
$hash = hash_hmac('sha256', $phone . ':' . $code, (string)$c['otp_pepper']);
$ttl  = (int)($c['otp_ttl'] ?? 120);

$db->prepare(
    'INSERT INTO otp_code (phone, code_hash, expires_at, ip, created_at)
     VALUES (?, ?, ?, ?, ?)'
)->execute([$phone, $hash, now_utc($ttl), $ip, now_utc()]);

$sms = sms_send_verify($phone, $code);

if (!$sms['sent']) {
    // کد را باطل می‌کنیم تا کاربر با کدی که هرگز نرسیده گیر نکند
    $db->prepare('UPDATE otp_code SET consumed_at = ? WHERE phone = ? AND consumed_at IS NULL')
       ->execute([now_utc(), $phone]);
    audit('otp.send_failed', null, ['phone' => $phone, 'error' => $sms['error']]);
    fail(502, 'sms_failed', 'ارسال پیامک ممکن نشد. کمی بعد دوباره تلاش کنید.');
}

audit('otp.sent', null, ['phone' => $phone, 'messageId' => $sms['messageId']]);

ok([
    'resendAfter' => $cooldown,
    'expiresIn'   => $ttl,
    // فقط برای اینکه رابط کاربری بداند کاربر تازه است یا نه — هویتی لو نمی‌دهد
    'isNew'       => !user_exists($phone),
]);

function user_exists(string $phone): bool
{
    $st = db()->prepare('SELECT 1 FROM app_user WHERE phone = ? LIMIT 1');
    $st->execute([$phone]);
    return (bool)$st->fetchColumn();
}
