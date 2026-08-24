<?php
/**
 * پنل سوپرادمین پلتفرم: ورود، آموزشگاه‌ها، کاربران و نقش‌ها، ورود
 * به‌جای کاربر، تنظیمات سایت، درخواست‌های دمو، آمار.
 *
 * برخلاف پنل قدیمی محصول، این پنل به‌صورت مستقیم به دادهٔ همهٔ
 * آموزشگاه‌ها دسترسی دارد — چون کارش دقیقاً همین است: دادن/گرفتن نقش،
 * تعلیق آموزشگاه، جست‌وجوی کاربر در کل پلتفرم. ولی یک خط قرمز عمدی
 * باقی می‌ماند: محتوای واقعی یک آموزشگاه (کدام زبان‌آموز، کدام نمره،
 * کدام پرداخت) از اینجا دیده نمی‌شود — فقط لیست/شمار/نقش. برای دیدن
 * واقعی داخل یک پنل، سوپرادمین باید از impersonate.start با دلیل
 * اجباری استفاده کند؛ همان چیزی که ثبت و قابل بازبینی است.
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_super.php';
require __DIR__ . '/_platform_ctx.php';

/* محدوده‌های مجاز — همان فهرست PERM_SCOPES موتور پنل، به همان ترتیب */
const PLATFORM_SCOPES = ['own', 'assigned_students', 'own_classes', 'branch', 'institute', 'platform'];

/** یک نقش را می‌آورد یا با پیام روشن شکست می‌خورد */
function role_row(string $id): array
{
    $st = db()->prepare('SELECT * FROM role WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) fail(404, 'not_found', 'این نقش پیدا نشد.');
    return $r;
}
require __DIR__ . '/_settings.php';
require __DIR__ . '/_sms.php';

require_post();
$in     = body_json();
$action = trim((string)($in['action'] ?? ''));

switch ($action) {

/* ═════════════════════ نشست ═════════════════════ */

case 'me':
    $a = current_super();
    if (!$a) json_out(200, ['ok' => true, 'authenticated' => false, 'needsSetup' => !admin_exists()]);
    ok(['authenticated' => true, 'admin' => ['username' => $a['username'], 'name' => $a['full_name']]]);

/*
 * فقط وقتی هیچ سوپرادمینی وجود ندارد، و فقط با کلیدی که در config.php
 * نوشته‌اید. بدون این کلید، اولین کسی که آدرس پنل را پیدا کند
 * می‌توانست خودش را سوپرادمین کل پلتفرم کند.
 */
case 'bootstrap':
    if (admin_exists()) fail(409, 'already_setup', 'سوپرادمین از قبل ساخته شده. از صفحهٔ ورود استفاده کنید.');

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

    $id = new_id();
    db()->prepare('INSERT INTO admin_user (id, username, pass_hash, full_name, status, created_at) VALUES (?,?,?,?,?,?)')
        ->execute([$id, $u, password_hash($p, PASSWORD_DEFAULT),
                   mb_substr(trim((string)($in['fullName'] ?? '')), 0, 120), 'active', now_utc()]);

    super_issue_session($id);
    audit('super.created', null, ['username' => $u]);
    ok(['admin' => ['username' => $u]]);

case 'login':
    $u  = strtolower(trim((string)($in['username'] ?? '')));
    $p  = (string)($in['password'] ?? '');
    $ip = client_ip();

    if ($u === '' || $p === '') fail(400, 'invalid', 'نام کاربری و رمز را وارد کنید.');

    if (!rate_ok('super_login_ip', $ip, 10, 3600) ||
        !rate_ok('super_login_user', $u, 10, 3600)) {
        audit('super.rate_limited', null, ['username' => $u, 'ip' => $ip]);
        fail(429, 'rate_limited', 'تلاش‌های زیاد. یک ساعت دیگر امتحان کنید.');
    }

    $st = db()->prepare('SELECT id, pass_hash, status, full_name FROM admin_user WHERE username = ?');
    $st->execute([$u]);
    $a = $st->fetch();

    if (!$a || $a['status'] !== 'active' || !password_verify($p, (string)$a['pass_hash'])) {
        usleep(400000);
        audit('super.login_failed', null, ['username' => $u, 'ip' => $ip]);
        fail(401, 'bad_credentials', 'نام کاربری یا رمز درست نیست.');
    }

    if (password_needs_rehash((string)$a['pass_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE admin_user SET pass_hash = ? WHERE id = ?')
            ->execute([password_hash($p, PASSWORD_DEFAULT), $a['id']]);
    }

    db()->prepare('UPDATE admin_user SET last_login_at = ? WHERE id = ?')->execute([now_utc(), $a['id']]);
    super_issue_session((string)$a['id']);
    audit('super.login', null, ['username' => $u]);
    ok(['admin' => ['username' => $u, 'name' => $a['full_name']]]);

case 'logout':
    super_revoke();
    ok();

case 'password':
    $a   = require_super();
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
    db()->prepare('UPDATE admin_session SET revoked_at = ? WHERE admin_id = ? AND token_hash <> ?')
        ->execute([now_utc(), $a['id'], hash('sha256', (string)($_COOKIE[SUPER_COOKIE] ?? ''))]);
    audit('super.password_changed', null, ['username' => $a['username']]);
    ok();

/* ═════════════════════ آموزشگاه‌ها ═════════════════════ */

case 'institutes.list':
    require_super();
    $q      = s_in($in, 'q', 160);
    $status = s_in($in, 'status', 16);
    $limit  = max(1, min(100, (int)($in['limit'] ?? 50)));

    $sql = 'SELECT i.id, i.name, i.city, i.phone, i.term_weeks, i.status, i.suspended_reason, i.created_at, i.jitsi_enabled,
                   (SELECT COUNT(*) FROM membership m WHERE m.institute_id = i.id AND m.status = "active") AS members,
                   (SELECT COUNT(*) FROM membership m WHERE m.institute_id = i.id AND m.status = "active" AND m.role = "manager")   AS managers,
                   (SELECT COUNT(*) FROM membership m WHERE m.institute_id = i.id AND m.status = "active" AND m.role = "teacher")   AS teachers,
                   (SELECT COUNT(*) FROM membership m WHERE m.institute_id = i.id AND m.status = "active" AND m.role = "student")   AS students
              FROM institute i WHERE 1=1';
    $args = [];
    if ($q !== '') {
        $sql .= ' AND (i.name LIKE ? OR i.city LIKE ? OR i.phone LIKE ?)';
        $like = '%' . $q . '%';
        array_push($args, $like, $like, $like);
    }
    if (in_array($status, ['active', 'suspended'], true)) {
        $sql .= ' AND i.status = ?'; $args[] = $status;
    }
    $sql .= ' ORDER BY i.created_at DESC LIMIT ' . $limit;

    $st = db()->prepare($sql);
    $st->execute($args);
    ok(['institutes' => array_map(fn($r) => [
        'id' => (string)$r['id'], 'name' => (string)$r['name'], 'city' => $r['city'], 'phone' => $r['phone'],
        'termWeeks' => (int)$r['term_weeks'], 'status' => (string)$r['status'],
        'suspendedReason' => $r['suspended_reason'], 'createdAt' => (string)$r['created_at'],
        'jitsiEnabled' => (bool)$r['jitsi_enabled'],
        'members' => (int)$r['members'], 'managers' => (int)$r['managers'],
        'teachers' => (int)$r['teachers'], 'students' => (int)$r['students'],
    ], $st->fetchAll())]);

case 'institutes.get':
    require_super();
    $inst = require_institute(id_in($in, 'id', 'آموزشگاه'));
    $st = db()->prepare(
        'SELECT m.id, m.role, m.status, m.hourly_rate, m.can_host_meeting, m.created_at, u.id AS user_id, u.full_name, u.phone
           FROM membership m JOIN app_user u ON u.id = m.user_id
          WHERE m.institute_id = ? ORDER BY FIELD(m.role,"manager","teacher","student"), m.created_at ASC'
    );
    $st->execute([$inst['id']]);
    ok([
        'institute' => [
            'id' => (string)$inst['id'], 'name' => (string)$inst['name'], 'city' => $inst['city'],
            'phone' => $inst['phone'], 'termWeeks' => (int)$inst['term_weeks'],
            'status' => (string)$inst['status'], 'suspendedReason' => $inst['suspended_reason'],
            'createdAt' => (string)$inst['created_at'],
            'jitsiEnabled' => (bool)$inst['jitsi_enabled'],
        ],
        'members' => array_map(fn($r) => [
            'membershipId' => (string)$r['id'], 'userId' => (string)$r['user_id'],
            'name' => (string)$r['full_name'], 'phone' => (string)$r['phone'],
            'role' => (string)$r['role'], 'status' => (string)$r['status'],
            'hourlyRate' => (int)$r['hourly_rate'], 'since' => (string)$r['created_at'],
            'canHostMeeting' => (bool)$r['can_host_meeting'],
        ], $st->fetchAll()),
    ]);

/*
 * ساخت دستی آموزشگاه توسط سوپرادمین — همان چیزی که otp-verify.php برای
 * ثبت‌نام خوداتکا انجام می‌دهد، ولی اینجا بدون کد پیامکی، چون سوپرادمین
 * از قبل احراز هویت شده. برای وقتی است که یک آموزشگاه را خودتان
 * onboard می‌کنید، نه اینکه صاحبش خودش ثبت‌نام کند.
 */
case 'institutes.create':
    $a = require_super();
    $name  = s_in($in, 'name', 160);
    $phone = normalize_phone((string)($in['ownerPhone'] ?? ''));
    $owner = s_in($in, 'ownerName', 120);
    $city  = s_in($in, 'city', 80);
    if ($name === '') fail(400, 'invalid', 'نام آموزشگاه را وارد کنید.');
    if ($phone === null) fail(400, 'invalid_phone', 'شمارهٔ موبایل مدیر باید ۱۱ رقم و با ۰۹ شروع شود.');
    if ($owner === '') fail(400, 'invalid', 'نام مدیر را وارد کنید.');

    $db = db();
    $st = $db->prepare('SELECT id FROM app_user WHERE phone = ?');
    $st->execute([$phone]);
    $uid = $st->fetchColumn();
    if (!$uid) {
        $uid = new_id();
        $db->prepare('INSERT INTO app_user (id, phone, full_name, role, status, created_at) VALUES (?,?,?,?,?,?)')
           ->execute([$uid, $phone, $owner, 'manager', 'active', now_utc()]);
    }

    $iid = new_id();
    $db->prepare('INSERT INTO institute (id, name, owner_user_id, phone, city, term_weeks, status, created_at) VALUES (?,?,?,?,?,?,?,?)')
       ->execute([$iid, $name, $uid, $phone ?: null, $city ?: null, 12, 'active', now_utc()]);
    $db->prepare('INSERT INTO membership (id, institute_id, user_id, role, role_id, status, created_at) VALUES (?,?,?,?,?,?,?)')
       ->execute([new_id(), $iid, $uid, 'manager', system_role_id('manager'), 'active', now_utc()]);

    audit('super.institute_created', $a['id'], ['institute' => $iid, 'name' => $name, 'ownerPhone' => $phone]);
    ok(['id' => $iid]);

case 'institutes.suspend':
    $a = require_super();
    $inst = require_institute(id_in($in, 'id', 'آموزشگاه'));
    $reason = s_in($in, 'reason', 255);
    if ($reason === '') fail(400, 'invalid', 'دلیل تعلیق را بنویسید.');
    db()->prepare("UPDATE institute SET status = 'suspended', suspended_reason = ? WHERE id = ?")
        ->execute([$reason, $inst['id']]);
    audit('super.institute_suspended', $a['id'], ['institute' => $inst['id'], 'reason' => $reason]);
    ok();

case 'institutes.reactivate':
    $a = require_super();
    $inst = require_institute(id_in($in, 'id', 'آموزشگاه'));
    db()->prepare("UPDATE institute SET status = 'active', suspended_reason = NULL WHERE id = ?")
        ->execute([$inst['id']]);
    audit('super.institute_reactivated', $a['id'], ['institute' => $inst['id']]);
    ok();

/*
 * کلید اصلی پنل میت برای یک آموزشگاه — کل قابلیت را روشن یا خاموش
 * می‌کند، جدا از مجوز تک‌تک اعضا (membership.setMeetingAccess پایین‌تر).
 * خاموش‌کردن این، فوراً جلوی ساخت جلسهٔ تازه را می‌گیرد، حتی برای مدیر.
 */
case 'institutes.setJitsiEnabled':
    $a = require_super();
    $inst = require_institute(id_in($in, 'id', 'آموزشگاه'));
    $on = !empty($in['on']);
    db()->prepare('UPDATE institute SET jitsi_enabled = ? WHERE id = ?')->execute([$on ? 1 : 0, $inst['id']]);
    audit('super.institute_jitsi_changed', $a['id'], ['institute' => $inst['id'], 'on' => $on]);
    ok();

/* ═════════════════════ کاربران و نقش‌ها ═════════════════════ */

case 'users.search':
    require_super();
    $q = s_in($in, 'q', 160);
    if (mb_strlen($q) < 2) fail(400, 'too_short', 'حداقل دو حرف یا رقم وارد کنید.');
    $like = '%' . en_digits($q) . '%';
    $st = db()->prepare(
        'SELECT u.id, u.phone, u.full_name, u.status, u.created_at, u.last_login_at,
                (SELECT COUNT(*) FROM membership m WHERE m.user_id = u.id AND m.status = "active") AS memberships
           FROM app_user u WHERE u.phone LIKE ? OR u.full_name LIKE ?
          ORDER BY u.created_at DESC LIMIT 50'
    );
    $st->execute([$like, $like]);
    ok(['users' => array_map(fn($r) => [
        'id' => (string)$r['id'], 'phone' => (string)$r['phone'], 'name' => (string)$r['full_name'],
        'status' => (string)$r['status'], 'createdAt' => (string)$r['created_at'],
        'lastLoginAt' => $r['last_login_at'], 'memberships' => (int)$r['memberships'],
    ], $st->fetchAll())]);

case 'users.get':
    require_super();
    $u = require_platform_user(id_in($in, 'id', 'کاربر'));
    $st = db()->prepare(
        'SELECT m.id, m.institute_id, m.role, m.status, m.hourly_rate, m.created_at, i.name AS institute_name
           FROM membership m JOIN institute i ON i.id = m.institute_id
          WHERE m.user_id = ? ORDER BY m.created_at ASC'
    );
    $st->execute([$u['id']]);
    ok([
        'user' => [
            'id' => (string)$u['id'], 'phone' => (string)$u['phone'], 'name' => (string)$u['full_name'],
            'status' => (string)$u['status'], 'createdAt' => (string)$u['created_at'],
            'lastLoginAt' => $u['last_login_at'],
        ],
        'memberships' => array_map(fn($r) => [
            'membershipId' => (string)$r['id'], 'instituteId' => (string)$r['institute_id'],
            'instituteName' => (string)$r['institute_name'], 'role' => (string)$r['role'],
            'status' => (string)$r['status'], 'hourlyRate' => (int)$r['hourly_rate'],
            'since' => (string)$r['created_at'],
        ], $st->fetchAll()),
    ]);

/** قفل کامل ورود — همهٔ نشست‌های فعلی هم باطل می‌شوند */
case 'users.suspend':
    $a = require_super();
    $u = require_platform_user(id_in($in, 'id', 'کاربر'));
    db()->prepare("UPDATE app_user SET status = 'suspended' WHERE id = ?")->execute([$u['id']]);
    db()->prepare('UPDATE session_token SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL')
        ->execute([now_utc(), $u['id']]);
    audit('super.user_suspended', $a['id'], ['user' => $u['id']]);
    ok();

case 'users.reactivate':
    $a = require_super();
    $u = require_platform_user(id_in($in, 'id', 'کاربر'));
    db()->prepare("UPDATE app_user SET status = 'active' WHERE id = ?")->execute([$u['id']]);
    audit('super.user_reactivated', $a['id'], ['user' => $u['id']]);
    ok();

/*
 * دادن نقش تازه به کاربر در یک آموزشگاه — همان چیزی که کاربر خواسته:
 * «کدام کاربر به کدام پنل دسترسی داشته باشد». اگر عضویتی از قبل
 * (حتی غیرفعال) برای همین جفت آموزشگاه/کاربر باشد، آپدیت می‌شود —
 * چون uq_member یک ردیف در ازای هر (institute_id, user_id) اجازه
 * می‌دهد، نه یک ردیف در ازای هر نقش.
 */
case 'membership.add':
    $a = require_super();
    $u = require_platform_user(id_in($in, 'userId', 'کاربر'));
    $inst = require_institute(id_in($in, 'instituteId', 'آموزشگاه'));
    $role = s_in($in, 'role', 16);
    if (!in_array($role, PLATFORM_ROLES, true)) fail(400, 'invalid_role', 'نقش باید مدیر، مدرس یا زبان‌آموز باشد.');

    $st = db()->prepare('SELECT id, role FROM membership WHERE institute_id = ? AND user_id = ?');
    $st->execute([$inst['id'], $u['id']]);
    $existing = $st->fetch();

    if ($existing) {
        if ((string)$existing['role'] === 'manager' && $role !== 'manager'
            && is_last_active_manager($inst['id'], (string)$existing['id'])) {
            fail(409, 'last_manager', 'این تنها مدیر فعال این آموزشگاه است؛ اول یک مدیر دیگر تعیین کنید.');
        }
        db()->prepare("UPDATE membership SET role = ?, status = 'active' WHERE id = ?")
            ->execute([$role, $existing['id']]);
        $mid = (string)$existing['id'];
    } else {
        $mid = new_id();
        db()->prepare('INSERT INTO membership (id, institute_id, user_id, role, role_id, status, created_at) VALUES (?,?,?,?,?,?,?)')
            ->execute([$mid, $inst['id'], $u['id'], $role, system_role_id($role), 'active', now_utc()]);
    }
    audit('super.membership_granted', $a['id'], ['user' => $u['id'], 'institute' => $inst['id'], 'role' => $role]);
    ok(['membershipId' => $mid]);

case 'membership.setRole':
    $a = require_super();
    $m = membership_with_context(id_in($in, 'membershipId', 'عضویت'));
    $role = s_in($in, 'role', 16);
    if (!in_array($role, PLATFORM_ROLES, true)) fail(400, 'invalid_role', 'نقش باید مدیر، مدرس یا زبان‌آموز باشد.');
    if ((string)$m['role'] === 'manager' && $role !== 'manager'
        && is_last_active_manager((string)$m['institute_id'], (string)$m['id'])) {
        fail(409, 'last_manager', 'این تنها مدیر فعال این آموزشگاه است؛ اول یک مدیر دیگر تعیین کنید.');
    }
    db()->prepare('UPDATE membership SET role = ? WHERE id = ?')->execute([$role, $m['id']]);
    audit('super.membership_role_changed', $a['id'],
        ['membership' => $m['id'], 'institute' => $m['institute_id'], 'from' => $m['role'], 'to' => $role]);
    ok();

/*
 * برخلاف institute.php (که مدیر فقط اجازهٔ مدرسِ خودش را عوض می‌کند)،
 * این یکی هیچ محدودیتی به نقش ندارد — سوپرادمین می‌تواند حتی مجوز
 * یک مدیر را هم بگیرد. همان چیزی که کاربر خواسته: «هرکسی لازم ندارد».
 */
case 'membership.setMeetingAccess':
    $a = require_super();
    $m = membership_with_context(id_in($in, 'membershipId', 'عضویت'));
    $on = !empty($in['on']);
    db()->prepare('UPDATE membership SET can_host_meeting = ? WHERE id = ?')->execute([$on ? 1 : 0, $m['id']]);
    audit('super.membership_meeting_access_changed', $a['id'],
        ['membership' => $m['id'], 'institute' => $m['institute_id'], 'on' => $on]);
    ok();

case 'membership.suspend':
    $a = require_super();
    $m = membership_with_context(id_in($in, 'membershipId', 'عضویت'));
    if ((string)$m['role'] === 'manager' && is_last_active_manager((string)$m['institute_id'], (string)$m['id'])) {
        fail(409, 'last_manager', 'این تنها مدیر فعال این آموزشگاه است؛ اول یک مدیر دیگر تعیین کنید.');
    }
    db()->prepare("UPDATE membership SET status = 'inactive' WHERE id = ?")->execute([$m['id']]);
    audit('super.membership_suspended', $a['id'], ['membership' => $m['id'], 'institute' => $m['institute_id']]);
    ok();

case 'membership.reactivate':
    $a = require_super();
    $m = membership_with_context(id_in($in, 'membershipId', 'عضویت'));
    db()->prepare("UPDATE membership SET status = 'active' WHERE id = ?")->execute([$m['id']]);
    audit('super.membership_reactivated', $a['id'], ['membership' => $m['id'], 'institute' => $m['institute_id']]);
    ok();

case 'membership.remove':
    $a = require_super();
    $m = membership_with_context(id_in($in, 'membershipId', 'عضویت'));
    if ((string)$m['role'] === 'manager' && is_last_active_manager((string)$m['institute_id'], (string)$m['id'])) {
        fail(409, 'last_manager', 'این تنها مدیر فعال این آموزشگاه است؛ اول یک مدیر دیگر تعیین کنید.');
    }
    db()->prepare("UPDATE membership SET status = 'inactive' WHERE id = ?")->execute([$m['id']]);
    audit('super.membership_removed', $a['id'], ['membership' => $m['id'], 'institute' => $m['institute_id']]);
    ok();

/* ═════════════════════ ورود به‌جای کاربر ═════════════════════ */

/*
 * فقط یک تیکت تک‌مصرفی و کوتاه‌عمر می‌سازد؛ خودِ سوپرادمین هرگز رمز یا
 * کد ورود کاربر هدف را نمی‌بیند و نشستی هم اینجا صادر نمی‌شود. پنل
 * آموزشگاه (panel/api/impersonate.php) تیکت را مصرف می‌کند.
 */
case 'impersonate.start':
    $a = require_super();
    $u = require_platform_user(id_in($in, 'userId', 'کاربر'));
    $inst = require_institute(id_in($in, 'instituteId', 'آموزشگاه'));
    $reason = s_in($in, 'reason', 255);
    if (mb_strlen($reason) < 5) fail(400, 'reason_required', 'دلیل ورود را بنویسید (حداقل ۵ نویسه).');

    $st = db()->prepare(
        "SELECT 1 FROM membership WHERE institute_id = ? AND user_id = ? AND status = 'active'"
    );
    $st->execute([$inst['id'], $u['id']]);
    if (!$st->fetchColumn()) fail(409, 'no_membership', 'این کاربر عضو فعال این آموزشگاه نیست.');

    $tid = new_id();
    db()->prepare(
        'INSERT INTO impersonation_ticket (id, super_admin_id, target_user_id, institute_id, reason, expires_at, ip, created_at)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([$tid, $a['id'], $u['id'], $inst['id'], $reason, now_utc(60), client_ip(), now_utc()]);

    audit('super.impersonation_started', $a['id'],
        ['target' => $u['id'], 'institute' => $inst['id'], 'reason' => $reason]);

    ok(['url' => 'https://panel.talkora.ir/api/impersonate.php?ticket=' . $tid, 'expiresIn' => 60]);

/* ═════════════════════ سایت (پورت از پنل قدیمی) ═════════════════════ */

case 'settings':
    require_super();
    ok(['settings' => settings_all(), 'keys' => array_keys(setting_defaults())]);

case 'saveSettings':
    $a = require_super();
    $vals = (array)($in['settings'] ?? []);

    foreach (['contact_email', 'demo_email'] as $k) {
        if (isset($vals[$k]) && trim((string)$vals[$k]) !== ''
            && !filter_var(trim((string)$vals[$k]), FILTER_VALIDATE_EMAIL)) {
            fail(400, 'invalid_email', 'آدرس ایمیل معتبر نیست: ' . $vals[$k]);
        }
    }
    foreach (['price_basic', 'price_growth', 'price_pro', 'annual_discount', 'trial_days'] as $k) {
        if (isset($vals[$k])) {
            $v = preg_replace('/\D/', '', en_digits((string)$vals[$k]));
            if ($v === '') fail(400, 'invalid_number', 'عدد معتبر وارد کنید.');
            $vals[$k] = $v;
        }
    }
    if (isset($vals['annual_discount']) && (int)$vals['annual_discount'] > 90) {
        fail(400, 'invalid_number', 'تخفیف سالانه نمی‌تواند بیشتر از ۹۰ درصد باشد.');
    }

    if (isset($vals['sms_mode'])) {
        if (!in_array($vals['sms_mode'], ['bridge', 'smsir'], true)) {
            fail(400, 'invalid', 'حالت پیامک نامعتبر است.');
        }
        if ($vals['sms_mode'] === 'smsir') {
            $now  = settings_all();
            $key  = trim((string)($vals['smsir_api_key'] ?? $now['smsir_api_key']));
            $tpl  = (int)en_digits((string)($vals['smsir_template_id'] ?? $now['smsir_template_id']));
            if ($key === '' || $tpl <= 0) {
                fail(400, 'sms_incomplete',
                    'برای ارسال واقعی، هم کلید API و هم شناسهٔ قالب sms.ir لازم است. '
                  . 'تا وقتی قالب تأیید نشده، روی «کد در پنل» بمانید.');
            }
        }
    }
    if (isset($vals['smsir_template_id'])) {
        $vals['smsir_template_id'] = preg_replace('/\D/', '', en_digits((string)$vals['smsir_template_id'])) ?: '';
    }
    if (isset($vals['smsir_param_name'])) {
        $p = trim(str_replace('#', '', (string)$vals['smsir_param_name']));
        if ($p !== '' && !preg_match('/^[A-Za-z0-9_]{1,32}$/', $p)) {
            fail(400, 'invalid', 'نام متغیر فقط حروف انگلیسی، رقم و زیرخط — بدون فاصله و بدون #.');
        }
        $vals['smsir_param_name'] = $p ?: 'CODE';
    }
    if (isset($vals['otp_ttl'])) {
        $t = (int)preg_replace('/\D/', '', en_digits((string)$vals['otp_ttl']));
        if ($t < 60 || $t > 900) {
            fail(400, 'invalid_number', 'عمر کد باید بین ۶۰ و ۹۰۰ ثانیه باشد.');
        }
        $vals['otp_ttl'] = (string)$t;
    }

    $n = settings_save($vals, (string)$a['id']);
    audit('super.settings_saved', $a['id'], ['count' => $n, 'keys' => array_keys($vals)]);
    ok(['saved' => $n, 'settings' => settings_all()]);

case 'leads':
    require_super();
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
            'id' => (string)$r['id'], 'name' => (string)$r['name'], 'phone' => (string)$r['phone'],
            'email' => $r['email'], 'institute' => $r['institute'], 'students' => $r['students'],
            'note' => $r['note'], 'status' => (string)$r['status'], 'adminNote' => $r['admin_note'],
            'mailed' => (bool)$r['mailed'], 'at' => (string)$r['created_at'],
            'instituteId' => $r['institute_id'], 'trialDays' => $r['trial_days'] !== null ? (int)$r['trial_days'] : null,
        ], $st->fetchAll()),
    ]);

case 'leadUpdate':
    require_super();
    $id = id_in($in, 'id', 'سرنخ');
    $s = (string)($in['status'] ?? 'new');
    if (!in_array($s, ['new', 'contacted', 'won', 'lost'], true)) $s = 'new';
    db()->prepare('UPDATE demo_lead SET status = ?, admin_note = ? WHERE id = ?')
        ->execute([$s, mb_substr(trim((string)($in['note'] ?? '')), 0, 2000) ?: null, $id]);
    ok();

/* ═════════════════════ آمار پلتفرم ═════════════════════ */

case 'stats':
    require_super();
    $one = function (string $sql) {
        $r = db()->query($sql)->fetch();
        return (int)($r['n'] ?? 0);
    };
    ok(['stats' => [
        'institutes'          => $one('SELECT COUNT(*) AS n FROM institute'),
        'institutesSuspended' => $one("SELECT COUNT(*) AS n FROM institute WHERE status='suspended'"),
        'users'       => $one('SELECT COUNT(*) AS n FROM app_user'),
        'usersSuspended' => $one("SELECT COUNT(*) AS n FROM app_user WHERE status='suspended'"),
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
    require_super();
    $st = db()->query(
        "SELECT action, ip, meta, created_at FROM audit_log
          WHERE action IN ('institute.created','super.login','super.login_failed','super.settings_saved',
                           'otp.send_failed','super.rate_limited','otp.rate_limited_ip',
                           'super.membership_granted','super.membership_role_changed',
                           'super.membership_suspended','super.membership_removed',
                           'super.institute_suspended','super.institute_reactivated',
                           'super.user_suspended','super.impersonation_started')
          ORDER BY created_at DESC LIMIT 80"
    );
    ok(['events' => array_map(fn($r) => [
        'action' => (string)$r['action'], 'ip' => $r['ip'], 'meta' => $r['meta'], 'at' => (string)$r['created_at'],
    ], $st->fetchAll())]);

/* ═════════════════════ کدهای ورود (حالت پل) ═════════════════════ */

case 'loginCodes':
    require_super();
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
            'phone' => (string)$r['phone'], 'code' => (string)$r['pending_code'],
            'seconds' => max(0, strtotime((string)$r['expires_at'] . ' UTC') - time()),
        ], $st->fetchAll()),
    ]);

case 'issueCode':
    $a = require_super();
    $phone = normalize_phone((string)($in['phone'] ?? ''));
    if ($phone === null) fail(400, 'invalid_phone', 'شمارهٔ موبایل باید ۱۱ رقم و با ۰۹ شروع شود.');

    $c      = cfg();
    $bridge = sms_is_bridge();
    $code   = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $ttl    = otp_ttl();

    db()->prepare('UPDATE otp_code SET consumed_at = ?, pending_code = NULL WHERE phone = ? AND consumed_at IS NULL')
        ->execute([now_utc(), $phone]);
    db()->prepare(
        'INSERT INTO otp_code (phone, code_hash, pending_code, expires_at, ip, created_at) VALUES (?,?,?,?,?,?)'
    )->execute([
        $phone, hash_hmac('sha256', $phone . ':' . $code, (string)$c['otp_pepper']),
        $bridge ? $code : null, now_utc($ttl), client_ip(), now_utc(),
    ]);

    $sms = sms_send_verify($phone, $code);
    audit('super.issued_code', $a['id'], ['phone' => $phone, 'bridge' => $bridge]);

    if (!$bridge && !$sms['sent']) {
        fail(502, 'sms_failed', 'ارسال پیامک ممکن نشد: ' . (string)($sms['error'] ?? ''));
    }
    ok(['phone' => $phone, 'code' => $bridge ? $code : null, 'expiresIn' => $ttl, 'bridge' => $bridge]);

case 'smsTest':
    $a = require_super();
    $phone = normalize_phone((string)($in['phone'] ?? ''));
    if ($phone === null) fail(400, 'invalid_phone', 'شمارهٔ موبایل باید ۱۱ رقم و با ۰۹ شروع شود.');

    $s = sms_conf();
    if ($s['mode'] !== 'smsir') {
        fail(400, 'bridge_mode', 'الان در حالت پل هستید و پیامکی فرستاده نمی‌شود.');
    }
    $demo = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $res  = sms_send_verify($phone, $demo);
    audit('super.sms_test', $a['id'], ['phone' => $phone, 'sent' => $res['sent'], 'error' => $res['error']]);

    if (!$res['sent']) {
        fail(502, 'sms_failed', 'sms.ir قبول نکرد: ' . (string)($res['error'] ?? 'خطای نامشخص'),
             ['template' => $s['template'], 'param' => $s['param']]);
    }
    ok(['sent' => true, 'code' => $demo, 'param' => $s['param'], 'template' => $s['template']]);

/* ═════════════════════ سلامت سامانه ═════════════════════ */

case 'diagnostics':
    require_super();
    $c = cfg();
    $d = [];
    $add = function (string $t, string $state, string $detail) use (&$d): void {
        $d[] = ['title' => $t, 'state' => $state, 'detail' => $detail];
    };

    $add('نسخهٔ PHP', PHP_VERSION_ID >= 80000 ? 'ok' : 'bad', 'PHP ' . PHP_VERSION);

    $tables = 0;
    try { $tables = count(db()->query('SHOW TABLES')->fetchAll()); }
    catch (Throwable $e) {
        try { $tables = count(db()->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll()); }
        catch (Throwable $e2) { /* پایین گزارش می‌شود */ }
    }
    $add('پایگاه داده', $tables >= 20 ? 'ok' : ($tables > 0 ? 'warn' : 'bad'),
         $tables > 0 ? "متصل — {$tables} جدول (مشترک با پنل آموزشگاه‌ها)" : 'اتصال برقرار نشد');

    $add('گواهی SSL', is_https() ? 'ok' : 'bad',
         is_https() ? 'پنل روی https باز شده' : 'پنل روی http است؛ کوکی ورود پرچم Secure دارد.');

    $s = sms_conf();
    if ($s['mode'] === 'smsir') {
        $add('پیامک', extension_loaded('curl') ? 'ok' : 'bad',
             extension_loaded('curl') ? 'ارسال واقعی از طریق sms.ir، قالب ' . $s['template']
                                      : 'حالت ارسال واقعی است ولی افزونهٔ curl خاموش است.');
    } else {
        $add('پیامک', 'warn', 'حالت پل: کد ورود در همین پنل دیده می‌شود.');
    }

    $add('ارسال ایمیل', function_exists('mail') ? 'ok' : 'warn',
         function_exists('mail') ? 'تابع mail در دسترس است.' : 'تابع mail خاموش است.');

    $pepper = (string)($c['otp_pepper'] ?? '');
    $add('کلید امضای کدها', strlen($pepper) >= 32 && !str_contains($pepper, 'CHANGE') ? 'ok' : 'bad',
         strlen($pepper) >= 32 && !str_contains($pepper, 'CHANGE')
            ? 'یک کلید تصادفی سالم — باید عیناً با otp_pepper پنل آموزشگاه یکی باشد'
            : 'کلید نمونه یا کوتاه است، یا با پنل آموزشگاه یکی نیست — کدهای صادرشده از اینجا تأیید نمی‌شوند.');

    $imp = 0;
    try {
        $r = db()->query("SELECT COUNT(*) AS n FROM impersonation_ticket WHERE consumed_at IS NULL AND expires_at > NOW()")->fetch();
        $imp = (int)($r['n'] ?? 0);
    } catch (Throwable $e) { /* ستون‌های نسخهٔ ۴ هنوز اجرا نشده */ }
    if ($imp > 0) $add('تیکت‌های ورود به‌جای کاربر', 'warn', "{$imp} تیکت هنوز مصرف‌نشده و زنده است.");

    ok(['checks' => $d, 'serverTime' => gmdate('c')]);

case 'testMail':
    $a  = require_super();
    $to = trim((string)($in['to'] ?? setting('demo_email')));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) fail(400, 'invalid_email', 'آدرس ایمیل معتبر نیست.');

    $sent = send_mail($to, 'آزمایش ایمیل تاکورا',
        "این یک پیام آزمایشی از پنل سوپرادمین تاکورا است.\n\n"
      . "اگر این را می‌بینید، ارسال ایمیل روی سرور کار می‌کند.\n\n"
      . 'زمان سرور: ' . gmdate('c'));

    audit('super.test_mail', $a['id'], ['to' => $to, 'sent' => $sent]);
    if (!$sent) fail(502, 'mail_failed', 'ارسال ناموفق بود.');
    ok(['sent' => true]);

/* ═════════════════════ نقش‌ها و مجوزها ═════════════════════ */

/*
 * کاتالوگ کامل: هر مجوز با گروه و برچسبش، و هر نقش با بستهٔ خودش.
 * رابط ماتریس دسترسی را از همین می‌سازد.
 *
 * مجوزهای سطح پلتفرم هم برمی‌گردند ولی با پرچم خودشان، تا رابط
 * بتواند نشانشان بدهد و هم‌زمان قفلشان کند — دیدنشان اشکالی ندارد،
 * چسباندنشان به نقش را دیتابیس رد می‌کند.
 */
case 'access.catalogue':
    require_owner();

    $perms = [];
    foreach (db()->query('SELECT * FROM permission ORDER BY group_key, sort_order')->fetchAll() as $r) {
        $perms[] = [
            'key'      => (string)$r['perm_key'],
            'group'    => (string)$r['group_key'],
            'label'    => (string)$r['label_fa'],
            'platform' => (bool)$r['is_platform'],
            'write'    => (bool)$r['is_write'],
        ];
    }

    $roles = [];
    foreach (db()->query(
        'SELECT r.*, (SELECT COUNT(*) FROM membership m WHERE m.role_id = r.id AND m.status = \'active\') AS n_users
           FROM role r ORDER BY r.is_system DESC, r.name_fa')->fetchAll() as $r) {
        $rid = (string)$r['id'];
        $rp  = db()->prepare('SELECT perm_key, scope FROM role_permission WHERE role_id = ?');
        $rp->execute([$rid]);
        $bundle = [];
        foreach ($rp->fetchAll() as $x) $bundle[(string)$x['perm_key']] = (string)$x['scope'];

        $roles[] = [
            'id'        => $rid,
            'key'       => (string)$r['role_key'],
            'name'      => (string)$r['name_fa'],
            'desc'      => $r['description'],
            'scope'     => (string)$r['default_scope'],
            'system'    => (bool)$r['is_system'],
            'instituteId' => (string)$r['institute_id'],
            'users'     => (int)$r['n_users'],
            'perms'     => $bundle,
        ];
    }

    ok(['permissions' => $perms, 'roles' => $roles, 'scopes' => PLATFORM_SCOPES]);

case 'roles.create':
    $a    = require_owner();
    $name = s_in($in, 'name', 80);
    $key  = strtolower(preg_replace('/[^a-z0-9_]/i', '', s_in($in, 'key', 32)));
    if ($name === '' || $key === '') fail(400, 'invalid', 'نام و کلید نقش را وارد کنید.');

    $scope = enum_in($in, 'scope', PLATFORM_SCOPES, 'own');
    $iid   = s_in($in, 'instituteId', 32);   // خالی = نقش سیستمی، در دسترس همه

    $dup = db()->prepare('SELECT 1 FROM role WHERE institute_id = ? AND role_key = ?');
    $dup->execute([$iid, $key]);
    if ($dup->fetchColumn()) fail(409, 'dup', 'نقشی با این کلید از قبل هست.');

    $id = new_id();
    db()->prepare(
        'INSERT INTO role (id, institute_id, role_key, name_fa, description, default_scope, is_system, created_by, created_at)
         VALUES (?,?,?,?,?,?,0,?,?)'
    )->execute([$id, $iid, $key, $name, s_in($in, 'desc', 255) ?: null, $scope, $a['id'], now_utc()]);

    audit('role.created', $a['id'], ['role' => $id, 'key' => $key, 'name' => $name]);
    ok(['id' => $id]);

case 'roles.update':
    $a = require_owner();
    $r = role_row(id_in($in, 'id', 'نقش'));
    if ($r['is_system']) fail(403, 'system_role', 'نقش‌های سیستمی قابل تغییر نام نیستند.');

    $name = s_in($in, 'name', 80);
    if ($name === '') fail(400, 'invalid', 'نام نقش را وارد کنید.');

    db()->prepare('UPDATE role SET name_fa = ?, description = ?, default_scope = ? WHERE id = ?')
        ->execute([$name, s_in($in, 'desc', 255) ?: null,
                   enum_in($in, 'scope', PLATFORM_SCOPES, (string)$r['default_scope']), $r['id']]);
    audit('role.updated', $a['id'], ['role' => $r['id']]);
    ok();

case 'roles.delete':
    $a = require_owner();
    $r = role_row(id_in($in, 'id', 'نقش'));
    if ($r['is_system']) fail(403, 'system_role', 'نقش‌های سیستمی حذف نمی‌شوند.');

    $st = db()->prepare('SELECT COUNT(*) FROM membership WHERE role_id = ? AND status = ?');
    $st->execute([$r['id'], 'active']);
    $n = (int)$st->fetchColumn();
    if ($n > 0) {
        fail(409, 'in_use', "این نقش هنوز به {$n} نفر داده شده. اول نقششان را عوض کنید.");
    }

    db()->prepare('DELETE FROM role WHERE id = ?')->execute([$r['id']]);
    audit('role.deleted', $a['id'], ['role' => $r['id'], 'key' => $r['role_key']]);
    ok();

/*
 * بستهٔ مجوزهای یک نقش، یک‌جا نوشته می‌شود نه ردیف‌به‌ردیف — تا حالت
 * نیمه‌کاره پیش نیاید اگر وسط کار چیزی بشکند.
 *
 * مجوز سطح پلتفرم را دیتابیس با کلید خارجی مرکب رد می‌کند؛ اینجا هم
 * پیش از تلاش فیلترش می‌کنیم تا پیام خطای روشن بدهیم به‌جای خطای SQL.
 */
case 'roles.setPerms':
    $a = require_owner();
    $r = role_row(id_in($in, 'id', 'نقش'));
    if ($r['is_system'] && (string)$r['role_key'] === 'manager') {
        fail(403, 'system_role', 'بستهٔ مدیر آموزشگاه ثابت است — او همه‌کارهٔ آموزشگاه خودش است.');
    }

    $want = (array)($in['perms'] ?? []);   // { perm_key: scope }

    $plat = [];
    foreach (db()->query('SELECT perm_key FROM permission WHERE is_platform = 1')->fetchAll() as $x) {
        $plat[(string)$x['perm_key']] = true;
    }

    $rows = [];
    foreach ($want as $k => $scope) {
        $k = (string)$k;
        if (isset($plat[$k])) {
            fail(403, 'platform_perm', 'مجوز سطح پلتفرم به هیچ نقشی داده نمی‌شود: ' . $k);
        }
        if (!in_array($scope, PLATFORM_SCOPES, true)) $scope = 'own';
        $rows[] = [$k, $scope];
    }

    $db = db();
    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM role_permission WHERE role_id = ?')->execute([$r['id']]);
        $ins = $db->prepare('INSERT INTO role_permission (role_id, perm_key, is_platform, scope) VALUES (?,?,0,?)');
        foreach ($rows as [$k, $sc]) $ins->execute([$r['id'], $k, $sc]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        fail(400, 'bad_perm', 'یکی از مجوزها معتبر نیست: ' . $e->getMessage());
    }

    audit('role.perms_set', $a['id'], ['role' => $r['id'], 'count' => count($rows)]);
    ok(['count' => count($rows)]);

/* ═════════════════════ دسترسی یک کاربر ═════════════════════ */

/*
 * همه‌چیزِ یک کاربر در یک پاسخ: عضویت‌هایش در همهٔ آموزشگاه‌ها، و
 * اعطا/سلب‌های موردی‌اش. صفحهٔ «کاربران و دسترسی» از همین ساخته می‌شود.
 */
case 'user.access':
    require_owner();
    $u = require_platform_user(id_in($in, 'userId', 'کاربر'));

    $st = db()->prepare(
        'SELECT m.id, m.institute_id, m.role_id, m.role, m.status, m.expires_at, m.hourly_rate,
                i.name AS institute_name, i.plan, r.name_fa AS role_name, r.role_key
           FROM membership m
           JOIN institute i ON i.id = m.institute_id
           LEFT JOIN role r ON r.id = m.role_id
          WHERE m.user_id = ? ORDER BY i.name'
    );
    $st->execute([$u['id']]);
    $memberships = array_map(fn($r) => [
        'id'         => (string)$r['id'],
        'instituteId'=> (string)$r['institute_id'],
        'institute'  => (string)$r['institute_name'],
        'roleId'     => (string)($r['role_id'] ?? ''),
        'role'       => (string)($r['role_name'] ?? $r['role']),
        'roleKey'    => (string)($r['role_key'] ?? $r['role']),
        'status'     => (string)$r['status'],
        'expiresAt'  => $r['expires_at'] !== null ? (string)$r['expires_at'] : null,
        'rate'       => (int)$r['hourly_rate'],
        'plan'       => (string)$r['plan'],
    ], $st->fetchAll());

    $st = db()->prepare(
        'SELECT up.*, i.name AS institute_name, p.label_fa
           FROM user_permission up
           JOIN institute i ON i.id = up.institute_id
           LEFT JOIN permission p ON p.perm_key = up.perm_key
          WHERE up.user_id = ? ORDER BY i.name, up.perm_key'
    );
    $st->execute([$u['id']]);
    $overrides = array_map(fn($r) => [
        'id'          => (string)$r['id'],
        'instituteId' => (string)$r['institute_id'],
        'institute'   => (string)$r['institute_name'],
        'perm'        => (string)$r['perm_key'],
        'label'       => (string)($r['label_fa'] ?? $r['perm_key']),
        'effect'      => (string)$r['effect'],
        'scope'       => $r['scope'],
        'expiresAt'   => $r['expires_at'] !== null ? (string)$r['expires_at'] : null,
        'reason'      => $r['reason'],
    ], $st->fetchAll());

    ok([
        'user' => [
            'id' => (string)$u['id'], 'name' => (string)$u['full_name'],
            'phone' => (string)$u['phone'], 'status' => (string)$u['status'],
        ],
        'memberships' => $memberships,
        'overrides'   => $overrides,
    ]);

/*
 * اعطا یا سلب موردی. effect=clear یعنی برداشتن استثنا و بازگشت به
 * بستهٔ نقش.
 *
 * سلب همیشه بر اعطا مقدم است — این قاعده در موتور اعمال می‌شود، نه
 * اینجا؛ اینجا فقط ردیف نوشته می‌شود.
 */
case 'user.permSet':
    $a    = require_owner();
    $u    = require_platform_user(id_in($in, 'userId', 'کاربر'));
    $inst = require_institute(id_in($in, 'instituteId', 'آموزشگاه'));
    $perm = s_in($in, 'perm', 64);
    $eff  = enum_in($in, 'effect', ['allow', 'deny', 'clear'], 'clear');

    $pr = db()->prepare('SELECT is_platform FROM permission WHERE perm_key = ?');
    $pr->execute([$perm]);
    $isPlat = $pr->fetchColumn();
    if ($isPlat === false) fail(404, 'no_perm', 'چنین مجوزی وجود ندارد.');
    if ((int)$isPlat === 1) {
        fail(403, 'platform_perm', 'مجوز سطح پلتفرم به هیچ کاربری داده نمی‌شود.');
    }

    if ($eff === 'clear') {
        db()->prepare('DELETE FROM user_permission WHERE institute_id = ? AND user_id = ? AND perm_key = ?')
            ->execute([$inst['id'], $u['id'], $perm]);
        audit('access.override_cleared', $a['id'], ['user' => $u['id'], 'perm' => $perm, 'institute' => $inst['id']]);
        ok();
    }

    $days  = i_in($in, 'days', 0, 0, 3650);
    $until = $days > 0 ? now_utc($days * 86400) : null;
    $scope = $eff === 'allow' ? enum_in($in, 'scope', PLATFORM_SCOPES, 'own') : null;

    db()->prepare(
        'INSERT INTO user_permission (id, institute_id, user_id, perm_key, is_platform, effect, scope, expires_at, granted_by, reason, created_at)
         VALUES (?,?,?,?,0,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE effect = VALUES(effect), scope = VALUES(scope),
                                 expires_at = VALUES(expires_at), granted_by = VALUES(granted_by),
                                 reason = VALUES(reason), created_at = VALUES(created_at)'
    )->execute([new_id(), $inst['id'], $u['id'], $perm, $eff, $scope, $until,
                $a['id'], s_in($in, 'reason', 255) ?: null, now_utc()]);

    audit('access.override_set', $a['id'], [
        'user' => $u['id'], 'institute' => $inst['id'], 'perm' => $perm,
        'effect' => $eff, 'scope' => $scope, 'until' => $until,
    ]);
    ok();

/* ═════════════════════ چرخهٔ دمو ═════════════════════ */

/*
 * تأیید درخواست دمو: آموزشگاه ساخته می‌شود، کاربر (یا حساب تازه با
 * همان شماره) عضویت مدیرِ زمان‌دار می‌گیرد، و درخواست به هر دو وصل
 * می‌شود.
 *
 * پیش‌فرض ۱۴ روز است چون سایت همین را تبلیغ می‌کند، ولی مالک هنگام
 * تأیید می‌تواند عوضش کند.
 */
case 'demo.approve':
    $a  = require_owner();
    $id = id_in($in, 'id', 'درخواست');

    $st = db()->prepare('SELECT * FROM demo_lead WHERE id = ?');
    $st->execute([$id]);
    $lead = $st->fetch();
    if (!$lead) fail(404, 'not_found', 'این درخواست پیدا نشد.');
    if ($lead['institute_id'] !== null) fail(409, 'already', 'برای این درخواست از قبل آموزشگاه ساخته شده.');

    $days  = i_in($in, 'days', 14, 1, 365);
    $until = now_utc($days * 86400);
    $phone = normalize_phone((string)$lead['phone']);
    if ($phone === null) fail(400, 'bad_phone', 'شمارهٔ این درخواست معتبر نیست.');

    $db = db();
    $db->beginTransaction();
    try {
        // کاربر: اگر با این شماره هست همان، وگرنه تازه
        $uq = $db->prepare('SELECT id FROM app_user WHERE phone = ?');
        $uq->execute([$phone]);
        $uid = $uq->fetchColumn();
        if (!$uid) {
            $uid = new_id();
            $db->prepare('INSERT INTO app_user (id, phone, full_name, role, status, created_at) VALUES (?,?,?,?,?,?)')
               ->execute([$uid, $phone, (string)$lead['name'], 'manager', 'active', now_utc()]);
        }

        $iid = new_id();
        $db->prepare(
            'INSERT INTO institute (id, name, owner_user_id, phone, term_weeks, plan, trial_ends_at, created_at)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute([$iid, (string)($lead['institute'] ?: $lead['name']), $uid, $phone, 12, 'trial', $until, now_utc()]);

        $db->prepare(
            'INSERT INTO membership (id, institute_id, user_id, role, role_id, status, expires_at, granted_by, granted_reason, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([new_id(), $iid, $uid, 'manager', system_role_id('manager'), 'active', $until,
                    $a['id'], 'دورهٔ آزمایشی ' . $days . ' روزه', now_utc()]);

        $db->prepare(
            'UPDATE demo_lead SET status = ?, institute_id = ?, user_id = ?, trial_days = ?, approved_by = ?, approved_at = ?
              WHERE id = ?'
        )->execute(['won', $iid, $uid, $days, $a['id'], now_utc(), $id]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        fail(500, 'provision_failed', 'ساخت آموزشگاه دمو انجام نشد: ' . $e->getMessage());
    }

    audit('demo.approved', $a['id'], ['lead' => $id, 'institute' => $iid, 'user' => $uid, 'days' => $days]);
    ok(['instituteId' => $iid, 'userId' => $uid, 'until' => $until]);

/* تمدید، تبدیل به مشتری دائم، یا بازگرداندن از فقط-خواندنی */
case 'demo.extend':
    $a    = require_owner();
    $inst = require_institute(id_in($in, 'instituteId', 'آموزشگاه'));
    $days = i_in($in, 'days', 14, 1, 365);
    $until = now_utc($days * 86400);

    db()->prepare('UPDATE institute SET plan = ?, trial_ends_at = ? WHERE id = ?')
        ->execute(['trial', $until, $inst['id']]);
    db()->prepare('UPDATE membership SET expires_at = ? WHERE institute_id = ? AND expires_at IS NOT NULL')
        ->execute([$until, $inst['id']]);

    audit('demo.extended', $a['id'], ['institute' => $inst['id'], 'days' => $days, 'until' => $until]);
    ok(['until' => $until]);

case 'demo.convert':
    $a    = require_owner();
    $inst = require_institute(id_in($in, 'instituteId', 'آموزشگاه'));

    db()->prepare('UPDATE institute SET plan = ?, trial_ends_at = NULL WHERE id = ?')
        ->execute(['active', $inst['id']]);
    db()->prepare('UPDATE membership SET expires_at = NULL WHERE institute_id = ?')
        ->execute([$inst['id']]);

    audit('demo.converted', $a['id'], ['institute' => $inst['id']]);
    ok();

/*
 * انقضای دموهایی که وقتشان رسیده.
 *
 * به‌جای cron که روی هاست اشتراکی همیشه در دسترس نیست، هر بار که مالک
 * پنل را باز می‌کند اجرا می‌شود. دقتش ثانیه‌ای نیست ولی برای این کار
 * کافی است، و مهم‌تر: وابسته به چیزی نیست که ممکن است تنظیم نشود.
 */
case 'demo.sweep':
    $a = require_owner();
    $n = db()->prepare('UPDATE institute SET plan = ? WHERE plan = ? AND trial_ends_at IS NOT NULL AND trial_ends_at <= ?');
    $n->execute(['readonly', 'trial', now_utc()]);
    $c = $n->rowCount();
    if ($c > 0) audit('demo.expired', $a['id'], ['count' => $c]);
    ok(['expired' => $c]);

default:
    fail(400, 'unknown_action', 'درخواست نامشخص.');
}

function admin_exists(): bool
{
    return (bool)db()->query('SELECT 1 FROM admin_user LIMIT 1')->fetchColumn();
}
