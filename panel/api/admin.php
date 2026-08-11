<?php
/**
 * پنل ادمین محصول: ورود، تنظیمات سایت، درخواست‌های دمو، آمار.
 *
 * همه‌چیز اینجا با نام کاربری و رمز محافظت می‌شود و هیچ ربطی به
 * حساب‌های آموزشگاه ندارد. یک ادمین به دادهٔ همهٔ آموزشگاه‌ها دسترسی
 * ندارد — فقط شمارش می‌بیند، نه محتوا. این عمدی است: مالک محصول برای
 * ادارهٔ کسب‌وکار به آمار نیاز دارد، نه به تکالیف زبان‌آموزها.
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_admin.php';
require __DIR__ . '/_sms.php';

require_post();
$in     = body_json();
$action = trim((string)($in['action'] ?? ''));

switch ($action) {

/* ─────────── وضعیت ─────────── */
case 'me':
    $a = current_admin();
    if (!$a) json_out(200, ['ok' => true, 'authenticated' => false, 'needsSetup' => !admin_exists()]);
    ok(['authenticated' => true, 'admin' => ['username' => $a['username'], 'name' => $a['full_name']]]);

/* ─────────── ساخت اولین ادمین ─────────── */
/*
 * فقط وقتی هیچ ادمینی وجود ندارد، و فقط با کلیدی که در config.php
 * نوشته‌اید. بدون این کلید، اولین کسی که آدرس پنل را پیدا کند
 * می‌توانست خودش را ادمین کند.
 */
case 'bootstrap':
    if (admin_exists()) fail(409, 'already_setup', 'ادمین از قبل ساخته شده. از صفحهٔ ورود استفاده کنید.');

    $c   = cfg();
    $key = (string)($c['admin_setup_key'] ?? '');
    if ($key === '' || strlen($key) < 16) {
        fail(500, 'no_setup_key', 'ابتدا admin_setup_key را در config.php بگذارید (حداقل ۱۶ کاراکتر).');
    }
    if (!hash_equals($key, (string)($in['setupKey'] ?? ''))) {
        usleep(400000);
        fail(403, 'bad_key', 'کلید راه‌اندازی درست نیست.');
    }

    $u = strtolower(trim((string)($in['username'] ?? '')));
    $p = (string)($in['password'] ?? '');
    if (!preg_match('/^[a-z0-9._-]{3,64}$/', $u)) {
        fail(400, 'invalid', 'نام کاربری فقط حروف انگلیسی کوچک، عدد، نقطه، خط تیره — بین ۳ تا ۶۴ نویسه.');
    }
    if (strlen($p) < 10) fail(400, 'weak', 'رمز باید حداقل ۱۰ نویسه باشد.');

    $id = bin2hex(random_bytes(16));
    db()->prepare('INSERT INTO admin_user (id, username, pass_hash, full_name, status, created_at) VALUES (?,?,?,?,?,?)')
        ->execute([$id, $u, password_hash($p, PASSWORD_DEFAULT),
                   mb_substr(trim((string)($in['fullName'] ?? '')), 0, 120), 'active', now_utc()]);

    admin_issue_session($id);
    audit('admin.created', null, ['username' => $u]);
    ok(['admin' => ['username' => $u]]);

/* ─────────── ورود ─────────── */
case 'login':
    $u  = strtolower(trim((string)($in['username'] ?? '')));
    $p  = (string)($in['password'] ?? '');
    $ip = client_ip();

    if ($u === '' || $p === '') fail(400, 'invalid', 'نام کاربری و رمز را وارد کنید.');

    if (!rate_ok('admin_login_ip', $ip, 10, 3600) ||
        !rate_ok('admin_login_user', $u, 10, 3600)) {
        audit('admin.rate_limited', null, ['username' => $u, 'ip' => $ip]);
        fail(429, 'rate_limited', 'تلاش‌های زیاد. یک ساعت دیگر امتحان کنید.');
    }

    $st = db()->prepare('SELECT id, pass_hash, status, full_name FROM admin_user WHERE username = ?');
    $st->execute([$u]);
    $a = $st->fetch();

    /*
     * تأخیر ثابت روی هر شکست، و پیام یکسان برای «کاربر نیست» و
     * «رمز غلط» — وگرنه از تفاوت زمان یا متن می‌شد فهمید کدام نام
     * کاربری واقعی است.
     */
    if (!$a || $a['status'] !== 'active' || !password_verify($p, (string)$a['pass_hash'])) {
        usleep(400000);
        audit('admin.login_failed', null, ['username' => $u, 'ip' => $ip]);
        fail(401, 'bad_credentials', 'نام کاربری یا رمز درست نیست.');
    }

    // اگر الگوریتم هش پیش‌فرض PHP عوض شده، همین‌جا ارتقا می‌دهیم
    if (password_needs_rehash((string)$a['pass_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE admin_user SET pass_hash = ? WHERE id = ?')
            ->execute([password_hash($p, PASSWORD_DEFAULT), $a['id']]);
    }

    db()->prepare('UPDATE admin_user SET last_login_at = ? WHERE id = ?')->execute([now_utc(), $a['id']]);
    admin_issue_session((string)$a['id']);
    audit('admin.login', null, ['username' => $u]);
    ok(['admin' => ['username' => $u, 'name' => $a['full_name']]]);

case 'logout':
    admin_revoke();
    ok();

case 'password':
    $a   = require_admin();
    $old = (string)($in['current'] ?? '');
    $new = (string)($in['new'] ?? '');
    if (strlen($new) < 10) fail(400, 'weak', 'رمز تازه باید حداقل ۱۰ نویسه باشد.');

    $st = db()->prepare('SELECT pass_hash FROM admin_user WHERE id = ?');
    $st->execute([$a['id']]);
    if (!password_verify($old, (string)$st->fetchColumn())) {
        usleep(400000);
        fail(401, 'bad_credentials', 'رمز فعلی درست نیست.');
    }
    db()->prepare('UPDATE admin_user SET pass_hash = ? WHERE id = ?')
        ->execute([password_hash($new, PASSWORD_DEFAULT), $a['id']]);
    // همهٔ نشست‌های دیگر باطل می‌شوند؛ تغییر رمز باید مهاجم را بیرون بیندازد
    db()->prepare('UPDATE admin_session SET revoked_at = ? WHERE admin_id = ? AND token_hash <> ?')
        ->execute([now_utc(), $a['id'], hash('sha256', (string)($_COOKIE[ADMIN_COOKIE] ?? ''))]);
    audit('admin.password_changed', null, ['username' => $a['username']]);
    ok();

/* ─────────── تنظیمات ─────────── */
case 'settings':
    require_admin();
    ok(['settings' => settings_all(), 'keys' => array_keys(setting_defaults())]);

case 'saveSettings':
    $a = require_admin();
    $vals = (array)($in['settings'] ?? []);

    // اعتبارسنجی چیزهایی که شکل مشخص دارند، پیش از ذخیره
    foreach (['contact_email', 'demo_email'] as $k) {
        if (isset($vals[$k]) && trim((string)$vals[$k]) !== ''
            && !filter_var(trim((string)$vals[$k]), FILTER_VALIDATE_EMAIL)) {
            fail(400, 'invalid_email', 'آدرس ایمیل معتبر نیست: ' . $vals[$k]);
        }
    }
    foreach (['price_basic', 'price_growth', 'price_pro', 'annual_discount', 'trial_days'] as $k) {
        if (isset($vals[$k])) {
            $v = preg_replace('/\D/', '', en_digits_admin((string)$vals[$k]));
            if ($v === '') fail(400, 'invalid_number', 'عدد معتبر وارد کنید.');
            $vals[$k] = $v;
        }
    }
    if (isset($vals['annual_discount']) && (int)$vals['annual_discount'] > 90) {
        fail(400, 'invalid_number', 'تخفیف سالانه نمی‌تواند بیشتر از ۹۰ درصد باشد.');
    }

    /*
     * جلوگیری از حالتی که سامانه در آن قفل می‌شود: اگر روی «ارسال
     * واقعی» بروید ولی کلید یا قالب نداشته باشید، هیچ‌کس دیگر نمی‌تواند
     * وارد شود و راه برگشت هم از همین پنل است که خودتان با کد وارد
     * نمی‌شوید. پس اینجا جلویش را می‌گیریم، نه بعد از اولین شکایت.
     */
    if (isset($vals['sms_mode'])) {
        if (!in_array($vals['sms_mode'], ['bridge', 'smsir'], true)) {
            fail(400, 'invalid', 'حالت پیامک نامعتبر است.');
        }
        if ($vals['sms_mode'] === 'smsir') {
            $now  = settings_all();
            $key  = trim((string)($vals['smsir_api_key'] ?? $now['smsir_api_key']));
            $tpl  = (int)en_digits_admin((string)($vals['smsir_template_id'] ?? $now['smsir_template_id']));
            if ($key === '' || $tpl <= 0) {
                fail(400, 'sms_incomplete',
                    'برای ارسال واقعی، هم کلید API و هم شناسهٔ قالب sms.ir لازم است. '
                  . 'تا وقتی قالب تأیید نشده، روی «کد در پنل» بمانید.');
            }
        }
    }
    if (isset($vals['smsir_template_id'])) {
        $vals['smsir_template_id'] = preg_replace('/\D/', '', en_digits_admin((string)$vals['smsir_template_id'])) ?: '';
    }

    $n = settings_save($vals, (string)$a['id']);
    audit('admin.settings_saved', null, ['count' => $n, 'keys' => array_keys($vals)]);
    ok(['saved' => $n, 'settings' => settings_all()]);

/* ─────────── درخواست‌های دمو ─────────── */
case 'leads':
    require_admin();
    $status = (string)($in['status'] ?? '');
    $sql = 'SELECT * FROM demo_lead';
    $args = [];
    if (in_array($status, ['new', 'contacted', 'won', 'lost'], true)) {
        $sql .= ' WHERE status = ?'; $args[] = $status;
    }
    $sql .= ' ORDER BY created_at DESC LIMIT 200';
    $st = db()->prepare($sql); $st->execute($args);

    $counts = [];
    foreach (db()->query('SELECT status, COUNT(*) AS n FROM demo_lead GROUP BY status')->fetchAll() as $r) {
        $counts[(string)$r['status']] = (int)$r['n'];
    }

    ok([
        'counts' => $counts,
        'leads'  => array_map(fn($r) => [
            'id'        => (string)$r['id'],
            'name'      => (string)$r['name'],
            'phone'     => (string)$r['phone'],
            'email'     => $r['email'],
            'institute' => $r['institute'],
            'students'  => $r['students'],
            'note'      => $r['note'],
            'status'    => (string)$r['status'],
            'adminNote' => $r['admin_note'],
            'mailed'    => (bool)$r['mailed'],
            'at'        => (string)$r['created_at'],
        ], $st->fetchAll()),
    ]);

case 'leadUpdate':
    require_admin();
    $id = (string)($in['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $id)) fail(404, 'not_found', 'پیدا نشد.');
    $s = (string)($in['status'] ?? 'new');
    if (!in_array($s, ['new', 'contacted', 'won', 'lost'], true)) $s = 'new';
    db()->prepare('UPDATE demo_lead SET status = ?, admin_note = ? WHERE id = ?')
        ->execute([$s, mb_substr(trim((string)($in['note'] ?? '')), 0, 2000) ?: null, $id]);
    ok();

/* ─────────── آمار ─────────── */
/*
 * فقط شمارش. ادمین محتوای هیچ آموزشگاهی را نمی‌بیند — نه زبان‌آموز،
 * نه تکلیف، نه نمره. برای ادارهٔ کسب‌وکار عدد لازم است، نه داده.
 */
case 'stats':
    require_admin();
    $one = function (string $sql) {
        $r = db()->query($sql)->fetch();
        return (int)($r['n'] ?? 0);
    };
    ok(['stats' => [
        'institutes'  => $one('SELECT COUNT(*) AS n FROM institute'),
        'users'       => $one('SELECT COUNT(*) AS n FROM app_user'),
        'managers'    => $one("SELECT COUNT(*) AS n FROM membership WHERE role='manager' AND status='active'"),
        'teachers'    => $one("SELECT COUNT(*) AS n FROM membership WHERE role='teacher' AND status='active'"),
        'students'    => $one("SELECT COUNT(*) AS n FROM membership WHERE role='student' AND status='active'"),
        'classes'     => $one('SELECT COUNT(*) AS n FROM klass'),
        'published'   => $one("SELECT COUNT(*) AS n FROM klass WHERE status='published'"),
        'sessions'    => $one('SELECT COUNT(*) AS n FROM class_session'),
        'held'        => $one("SELECT COUNT(*) AS n FROM class_session WHERE status='done'"),
        'submissions' => $one('SELECT COUNT(*) AS n FROM submission'),
        'leadsNew'    => $one("SELECT COUNT(*) AS n FROM demo_lead WHERE status='new'"),
        'otpToday'    => $one("SELECT COUNT(*) AS n FROM audit_log WHERE action='otp.sent' AND created_at >= '" . gmdate('Y-m-d') . " 00:00:00'"),
    ]]);

case 'recent':
    require_admin();
    $st = db()->query(
        "SELECT action, ip, meta, created_at FROM audit_log
          WHERE action IN ('institute.created','admin.login','admin.login_failed','admin.settings_saved',
                           'otp.send_failed','admin.rate_limited','otp.rate_limited_ip')
          ORDER BY created_at DESC LIMIT 60"
    );
    ok(['events' => array_map(fn($r) => [
        'action' => (string)$r['action'],
        'ip'     => $r['ip'],
        'meta'   => $r['meta'],
        'at'     => (string)$r['created_at'],
    ], $st->fetchAll())]);

/* ─────────── کدهای ورود (حالت پل) ─────────── */
/*
 * فهرست کدهایی که همین حالا زنده‌اند. هر کد حداکثر دو دقیقه اینجاست
 * و به محض استفاده ناپدید می‌شود. فقط ادمینِ واردشده می‌بیندش.
 *
 * این صفحه عمداً کوتاه‌عمر است: وقتی sms.ir راه افتاد و حالت روی
 * «ارسال واقعی» رفت، ستون pending_code دیگر هرگز پر نمی‌شود و این
 * فهرست برای همیشه خالی می‌ماند.
 */
case 'loginCodes':
    require_admin();
    $st = db()->prepare(
        'SELECT phone, pending_code, expires_at, created_at FROM otp_code
          WHERE pending_code IS NOT NULL AND consumed_at IS NULL AND expires_at > ?
          ORDER BY created_at DESC LIMIT 20'
    );
    $st->execute([now_utc()]);

    $s = sms_conf();
    ok([
        'mode'  => $s['mode'],
        'codes' => array_map(fn($r) => [
            'phone'   => (string)$r['phone'],
            'code'    => (string)$r['pending_code'],
            'seconds' => max(0, strtotime((string)$r['expires_at'] . ' UTC') - time()),
        ], $st->fetchAll()),
    ]);

/*
 * ساخت کد برای یک شماره، بدون اینکه صاحب شماره کاری کرده باشد.
 *
 * برای وقتی است که مدیر آموزشگاه پای تلفن با استاد صحبت می‌کند و
 * می‌خواهد همان لحظه واردش کند. در حالت پیامک واقعی، پیامک هم
 * فرستاده می‌شود و کد به خود ادمین نشان داده نمی‌شود — چون آنجا
 * دیگر لازم نیست و نمایشش فقط یک راه لو رفتن است.
 */
case 'issueCode':
    require_admin();
    $phone = normalize_phone((string)($in['phone'] ?? ''));
    if ($phone === null) fail(400, 'invalid_phone', 'شمارهٔ موبایل باید ۱۱ رقم و با ۰۹ شروع شود.');

    $c      = cfg();
    $bridge = sms_is_bridge();
    $code   = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $ttl    = (int)($c['otp_ttl'] ?? 120);

    db()->prepare('UPDATE otp_code SET consumed_at = ?, pending_code = NULL WHERE phone = ? AND consumed_at IS NULL')
        ->execute([now_utc(), $phone]);
    db()->prepare(
        'INSERT INTO otp_code (phone, code_hash, pending_code, expires_at, ip, created_at) VALUES (?,?,?,?,?,?)'
    )->execute([
        $phone,
        hash_hmac('sha256', $phone . ':' . $code, (string)$c['otp_pepper']),
        $bridge ? $code : null,
        now_utc($ttl), client_ip(), now_utc(),
    ]);

    $sms = sms_send_verify($phone, $code);
    audit('admin.issued_code', null, ['phone' => $phone, 'bridge' => $bridge]);

    if (!$bridge && !$sms['sent']) {
        fail(502, 'sms_failed', 'ارسال پیامک ممکن نشد: ' . (string)($sms['error'] ?? ''));
    }
    ok(['phone' => $phone, 'code' => $bridge ? $code : null, 'expiresIn' => $ttl, 'bridge' => $bridge]);

/* ─────────── سلامت سامانه ─────────── */
/*
 * همان چیزهایی که نصب‌کننده قبل از نصب بررسی می‌کرد، ولی این بار
 * روی سامانهٔ زنده. هدف این است که وقتی چیزی کار نمی‌کند، جواب
 * «کدام قطعه خراب است» یک کلیک دورتر باشد، نه یک تماس پشتیبانی.
 */
case 'diagnostics':
    require_admin();
    $c = cfg();
    $d = [];
    $add = function (string $t, string $state, string $detail) use (&$d): void {
        $d[] = ['title' => $t, 'state' => $state, 'detail' => $detail];   // ok | warn | bad
    };

    $add('نسخهٔ PHP', PHP_VERSION_ID >= 80000 ? 'ok' : 'bad', 'PHP ' . PHP_VERSION);

    $tables = 0;
    try { $tables = count(db()->query('SHOW TABLES')->fetchAll()); }
    catch (Throwable $e) {
        try { $tables = count(db()->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll()); }
        catch (Throwable $e2) { /* بی‌خیال؛ پایین گزارش می‌شود */ }
    }
    $add('پایگاه داده', $tables >= 20 ? 'ok' : ($tables > 0 ? 'warn' : 'bad'),
         $tables > 0 ? "متصل — {$tables} جدول" : 'اتصال برقرار نشد');

    $confPath = null;
    foreach (CONFIG_PATHS as $p) if (is_file($p)) { $confPath = $p; break; }
    $outside = $confPath !== null && !str_starts_with((string)realpath($confPath), (string)realpath(__DIR__));
    $add('فایل پیکربندی', $outside ? 'ok' : 'warn',
         $outside ? 'بیرون از پوشهٔ وب — امن‌ترین حالت'
                  : 'داخل پوشهٔ api است. کار می‌کند، ولی اگر بتوانید بیرون از httpdocs ببریدش بهتر است.');

    $add('گواهی SSL', is_https() ? 'ok' : 'bad',
         is_https() ? 'پنل روی https باز شده'
                    : 'پنل روی http است؛ کوکی ورود پرچم Secure دارد و کاربران بیرون می‌افتند.');

    $setupLeft = is_dir(__DIR__ . '/../setup');
    $add('پوشهٔ نصب', $setupLeft ? 'warn' : 'ok',
         $setupLeft ? 'پوشهٔ setup/ هنوز روی هاست است. حالا که نصب تمام شده پاکش کنید.'
                    : 'پاک شده');

    $s = sms_conf();
    if ($s['mode'] === 'smsir') {
        $add('پیامک', extension_loaded('curl') ? 'ok' : 'bad',
             extension_loaded('curl') ? 'ارسال واقعی از طریق sms.ir، قالب ' . $s['template']
                                      : 'حالت ارسال واقعی است ولی افزونهٔ curl خاموش است — هیچ پیامکی نمی‌رود.');
    } else {
        $add('پیامک', 'warn', 'حالت پل: کد ورود در همین پنل دیده می‌شود و پیامکی فرستاده نمی‌شود.');
    }

    $add('ارسال ایمیل', function_exists('mail') ? 'ok' : 'warn',
         function_exists('mail') ? 'تابع mail در دسترس است — با دکمهٔ «ایمیل آزمایشی» واقعی‌اش را امتحان کنید.'
                                 : 'تابع mail خاموش است؛ اعلان درخواست دمو نمی‌رود ولی در همین پنل ثبت می‌شود.');

    $pepper = (string)($c['otp_pepper'] ?? '');
    $add('کلید امضای کدها', strlen($pepper) >= 32 && !str_contains($pepper, 'CHANGE') ? 'ok' : 'bad',
         strlen($pepper) >= 32 && !str_contains($pepper, 'CHANGE')
            ? 'یک کلید تصادفی سالم' : 'کلید نمونه یا کوتاه است — کدهای ورود قابل حدس می‌شوند.');

    $stale = 0;
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM otp_code WHERE pending_code IS NOT NULL AND expires_at < ?');
        $st->execute([now_utc()]);
        $stale = (int)$st->fetchColumn();
    } catch (Throwable $e) { /* جدول قدیمی */ }
    if ($stale > 0) {
        $add('کدهای منقضی', 'warn', "{$stale} کد منقضی هنوز خوانا مانده — با اولین درخواست ورود پاک می‌شود.");
    }

    ok(['checks' => $d, 'serverTime' => gmdate('c')]);

/* ─────────── آزمایش ایمیل ─────────── */
case 'testMail':
    $a  = require_admin();
    $to = trim((string)($in['to'] ?? setting('demo_email')));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) fail(400, 'invalid_email', 'آدرس ایمیل معتبر نیست.');

    $sent = send_mail($to, 'آزمایش ایمیل تاکورا',
        "این یک پیام آزمایشی از پنل ادمین تاکورا است.\n\n"
      . "اگر این را می‌بینید، ارسال ایمیل روی سرور کار می‌کند و درخواست‌های\n"
      . "دموی رایگان به همین آدرس خواهد رسید.\n\n"
      . 'زمان سرور: ' . gmdate('c'));

    audit('admin.test_mail', null, ['to' => $to, 'sent' => $sent]);
    if (!$sent) {
        fail(502, 'mail_failed',
            'ارسال ناموفق بود. در پلسک سرویس Mail دامنه را روشن کنید و صندوق ' . htmlspecialchars($to) . ' را بسازید.');
    }
    ok(['sent' => true]);

default:
    fail(400, 'unknown_action', 'درخواست نامشخص.');
}

function admin_exists(): bool
{
    return (bool)db()->query('SELECT 1 FROM admin_user LIMIT 1')->fetchColumn();
}

function en_digits_admin(string $s): string
{
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($ar, $en, str_replace($fa, $en, $s));
}
