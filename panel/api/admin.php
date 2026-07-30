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
