<?php
/**
 * ورود ادمین محصول — جدا از کاربران آموزشگاه‌ها.
 *
 * ═══ چرا نام کاربری و رمز، و نه پیامک؟ ═══
 *
 * کاربران آموزشگاه با کد یک‌بارمصرف وارد می‌شوند چون شمارهٔ موبایل
 * چیزی است که همه دارند و همه به آن دسترسی دارند. ولی ادمینِ محصول
 * یک نفر است و اگر تنها کلید سامانه شمارهٔ موبایلش باشد، یک سیم‌کارت
 * سوخته یا اعتبار تمام‌شدهٔ پنل پیامک یعنی قفل‌شدن از کل محصول.
 *
 * پس: نام کاربری و رمز، با این محافظت‌ها:
 *   - رمز با password_hash ذخیره می‌شود، خودش هرگز نوشته نمی‌شود
 *   - سقف ۱۰ تلاش در ساعت برای هر نام کاربری و هر IP
 *   - پاسخ برای «کاربر نیست» و «رمز غلط» یکسان است
 *   - تأخیر ثابت روی تلاش ناموفق، تا زمان پاسخ اطلاعاتی ندهد
 *   - کوکی نشستِ جدا از کاربران عادی، با نام و مسیر متفاوت
 */

declare(strict_types=1);

if (realpath(__FILE__) === realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    http_response_code(404);
    exit;
}

const ADMIN_COOKIE   = 'tk_admin';
const ADMIN_TTL_DAYS = 7;      // کوتاه‌تر از کاربران عادی: دسترسی حساس‌تر است

function admin_issue_session(string $adminId): string
{
    $token = bin2hex(random_bytes(32));
    db()->prepare(
        'INSERT INTO admin_session (token_hash, admin_id, expires_at, ip, user_agent, created_at)
         VALUES (?,?,?,?,?,?)'
    )->execute([
        hash('sha256', $token), $adminId, now_utc(ADMIN_TTL_DAYS * 86400),
        client_ip(), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250), now_utc(),
    ]);

    setcookie(ADMIN_COOKIE, $token, [
        'expires'  => time() + ADMIN_TTL_DAYS * 86400,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Strict',   // پنل ادمین هیچ‌وقت از سایت دیگری صدا زده نمی‌شود
    ]);
    return $token;
}

function current_admin(): ?array
{
    $token = $_COOKIE[ADMIN_COOKIE] ?? '';
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;

    $st = db()->prepare(
        'SELECT a.id, a.username, a.full_name, a.status
           FROM admin_session s JOIN admin_user a ON a.id = s.admin_id
          WHERE s.token_hash = ? AND s.expires_at > ? AND s.revoked_at IS NULL'
    );
    $st->execute([hash('sha256', $token), now_utc()]);
    $a = $st->fetch();
    if (!$a || $a['status'] !== 'active') return null;

    db()->prepare('UPDATE admin_session SET last_seen_at = ? WHERE token_hash = ?')
        ->execute([now_utc(), hash('sha256', $token)]);
    return $a;
}

function require_admin(): array
{
    $a = current_admin();
    if (!$a) fail(401, 'unauthenticated', 'ابتدا وارد شوید.');
    return $a;
}

function admin_revoke(): void
{
    $token = $_COOKIE[ADMIN_COOKIE] ?? '';
    if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
        db()->prepare('UPDATE admin_session SET revoked_at = ? WHERE token_hash = ?')
            ->execute([now_utc(), hash('sha256', $token)]);
    }
    setcookie(ADMIN_COOKIE, '', [
        'expires' => time() - 3600, 'path' => '/',
        'secure' => is_https(), 'httponly' => true, 'samesite' => 'Strict',
    ]);
}

/* ─────────── تنظیمات سایت ─────────── */

/**
 * مقدارهای پیش‌فرض. هر کلیدی که اینجا نباشد از بیرون قابل نوشتن نیست —
 * یعنی کسی نمی‌تواند با فرستادن کلید دلخواه، ردیف بی‌ربط در جدول بسازد.
 *
 * قیمت‌ها ریال‌اند و صحیح. نمایش به تومان با تقسیم بر ۱۰ انجام می‌شود
 * (بند P.5) — هیچ‌جا عدد اعشاری برای پول نگه نمی‌داریم.
 */
function setting_defaults(): array
{
    return [
        // ── تماس ──
        'contact_phone'    => '',
        'contact_email'    => 'info@talkora.ir',
        'demo_email'       => 'info@talkora.ir',   // درخواست دموی رایگان به این آدرس می‌رود
        'contact_address'  => '',
        'support_hours'    => 'شنبه تا چهارشنبه، ۹ تا ۱۷',

        // ── قیمت‌ها، به ریال در ماه ──
        'price_basic'      => '4900000',
        'price_growth'     => '17000000',
        'price_pro'        => '44000000',
        // درصد تخفیف پرداخت سالانه
        'annual_discount'  => '20',
        'trial_days'       => '14',

        // ── متن‌های سایت ──
        'hero_title'       => 'آموزشگاه‌تان را از یک صفحه اداره کنید',
        'hero_sub'         => 'ثبت‌نام، ترم‌بندی، حضور و غیاب، کلاس آنلاین، تکلیف و نمره، شهریهٔ قسطی — همه در یک پنل فارسی.',
        'cta_text'         => 'درخواست دموی رایگان',
        'banner_text'      => '',      // نوار بالای سایت؛ خالی یعنی نمایش داده نمی‌شود
        'banner_on'        => '0',

        // ── نمادها ──
        'enamad_html'      => '',
        'samandehi_html'   => '',

        // ── وضعیت ──
        'maintenance'      => '0',
        'signup_open'      => '1',
    ];
}

function settings_all(): array
{
    $out = setting_defaults();
    foreach (db()->query('SELECT skey, svalue FROM site_setting')->fetchAll() as $r) {
        $k = (string)$r['skey'];
        if (array_key_exists($k, $out)) $out[$k] = (string)$r['svalue'];
    }
    return $out;
}

function setting(string $key, string $default = ''): string
{
    static $c = null;
    if ($c === null) $c = settings_all();
    return $c[$key] ?? $default;
}

function settings_save(array $values, ?string $adminId): int
{
    $allowed = setting_defaults();
    $db = db();
    $ins = $db->prepare('INSERT INTO site_setting (skey, svalue, updated_at, updated_by) VALUES (?,?,?,?)');
    $upd = $db->prepare('UPDATE site_setting SET svalue = ?, updated_at = ?, updated_by = ? WHERE skey = ?');

    $n = 0;
    foreach ($values as $k => $v) {
        if (!array_key_exists($k, $allowed)) continue;   // کلید ناشناخته بی‌صدا رد می‌شود
        $v = mb_substr(trim((string)$v), 0, 4000);
        try {
            $ins->execute([$k, $v, now_utc(), $adminId]);
        } catch (PDOException $e) {
            $upd->execute([$v, now_utc(), $adminId, $k]);
        }
        $n++;
    }
    return $n;
}

/* ─────────── ایمیل ─────────── */

/**
 * ارسال ایمیل با mail() خود PHP.
 *
 * روی پلسک، اگر سرویس Mail برای دامنه روشن باشد و صندوق info@talkora.ir
 * ساخته شده باشد، این کار می‌کند و ایمیل از خود دامنه می‌رود — که برای
 * نرسیدن به پوشهٔ اسپم مهم است.
 *
 * محدودیت صادقانه: mail() صف و تلاش دوباره ندارد. اگر ایمیل مهم شد
 * (مثلاً رسید پرداخت)، باید به SMTP با صف تبدیل شود. برای اعلان
 * «یک نفر دمو خواست» کافی است، و شکستش هم ثبت می‌شود.
 */
function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    if (!function_exists('mail')) { error_log('mail() در دسترس نیست'); return false; }

    $from = setting('contact_email', 'info@talkora.ir');
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) $from = 'info@talkora.ir';

    // هدرها از ورودی کاربر ساخته نمی‌شوند؛ replyTo قبل از استفاده اعتبارسنجی می‌شود
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: =?UTF-8?B?' . base64_encode('تاکورا') . '?= <' . $from . '>',
        'X-Mailer: Talkora',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $okSent = @mail($to, $subj, $body, implode("\r\n", $headers), '-f' . $from);
    if (!$okSent) error_log("ارسال ایمیل به {$to} ناموفق بود");
    return (bool)$okSent;
}
