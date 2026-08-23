<?php
/**
 * آموزشگاه: راه‌اندازی اولیه، اعضا، دعوت، تنظیمات.
 *
 * دعوت چطور کار می‌کند و چرا این‌طور:
 * مدیر شمارهٔ مدرس یا زبان‌آموز را وارد می‌کند، ولی آن شخص هنوز حساب
 * ندارد. به‌جای ساختن حساب بی‌صاحب با رمز موقت، فقط یک «دعوت» ثبت
 * می‌شود. اولین باری که همان شماره با کد یک‌بارمصرف وارد شود، عضویتش
 * خودکار ساخته و دعوت مصرف می‌شود. یعنی هیچ حسابی بدون تأیید شمارهٔ
 * واقعی به وجود نمی‌آید.
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_ctx.php';
require __DIR__ . '/_perm.php';

require_post();
$in     = body_json();
$action = s_in($in, 'action', 40);

switch ($action) {

/* ─────────── راه‌اندازی: ترم و کلاس‌ها را از صفر می‌سازد ─────────── */
case 'setup':
    require_perm('institute.edit');

    $termName  = s_in($in, 'termName', 80) ?: 'ترم جاری';
    $termStart = date_in($in, 'termStart', gmdate('Y-m-d'));
    $weeks     = i_in($in, 'weeks', 12, 1, 52);

    $existing = t_one('SELECT id FROM term WHERE __I__ AND status = ?', ['active']);
    if ($existing) {
        fail(409, 'term_exists', 'ترم فعالی از قبل هست. برای ساخت ترم تازه، اول ترم فعلی را ببندید.');
    }

    $termId = new_id();
    db()->prepare('INSERT INTO term (id, institute_id, name, starts_on, weeks, status, created_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([$termId, inst_id(), $termName, $termStart, $weeks, 'active', now_utc()]);

    // کلاس‌های پیش‌فرض تا پنل خالی بالا نیاید
    $rooms = (array)($in['rooms'] ?? []);
    $made  = 0;
    foreach ($rooms as $r) {
        if (!is_array($r)) continue;
        $n = s_in($r, 'name', 80);
        if ($n === '') continue;
        db()->prepare('INSERT INTO room (id, institute_id, name, capacity, created_at) VALUES (?,?,?,?,?)')
            ->execute([new_id(), inst_id(), $n, i_in($r, 'cap', 12, 1, 200), now_utc()]);
        $made++;
    }

    audit('institute.setup', my_id(), ['term' => $termName, 'rooms' => $made]);
    ok(['termId' => $termId, 'rooms' => $made]);

/* ─────────── تنظیمات ─────────── */
case 'update':
    require_perm('institute.edit');
    $name = s_in($in, 'name', 160);
    if ($name === '') fail(400, 'invalid', 'نام آموزشگاه را وارد کنید.');
    db()->prepare('UPDATE institute SET name = ?, city = ?, phone = ?, term_weeks = ? WHERE id = ?')
        ->execute([$name, s_in($in, 'city', 80) ?: null, s_in($in, 'phone', 20) ?: null,
                   i_in($in, 'termWeeks', 12, 1, 52), inst_id()]);
    audit('institute.update', my_id());
    ok();

/* ─────────── اعضا ─────────── */
case 'members':
    require_perm('member.view');
    $members = t_all(
        'SELECT m.id, m.user_id, m.role, m.status, m.hourly_rate, m.can_host_meeting, u.full_name, u.phone
           FROM membership m JOIN app_user u ON u.id = m.user_id
          WHERE m.__I__ ORDER BY m.role, u.full_name'
    );
    $invites = t_all('SELECT id, phone, full_name, role, created_at FROM invite WHERE __I__ AND accepted_at IS NULL ORDER BY created_at DESC');
    ok([
        'members' => array_map(fn($m) => [
            'id'             => (string)$m['id'],
            'userId'         => (string)$m['user_id'],
            'name'           => (string)$m['full_name'],
            'phone'          => (string)$m['phone'],
            'role'           => (string)$m['role'],
            'status'         => (string)$m['status'],
            'rate'           => (int)$m['hourly_rate'],
            'canHostMeeting' => (bool)$m['can_host_meeting'],
        ], $members),
        'invites' => array_map(fn($i) => [
            'id'    => (string)$i['id'],
            'phone' => (string)$i['phone'],
            'name'  => (string)$i['full_name'],
            'role'  => (string)$i['role'],
        ], $invites),
    ]);

/* ─────────── دعوت ─────────── */
case 'invite':
    require_perm('member.invite');
    $phone = normalize_phone(s_in($in, 'phone', 20));
    $name  = s_in($in, 'fullName', 120);
    $role  = enum_in($in, 'role', ['teacher', 'student'], 'student');
    $clsId = s_in($in, 'classId', 32);

    if ($phone === null) fail(400, 'invalid_phone', 'شمارهٔ موبایل باید ۱۱ رقم و با ۰۹ شروع شود.');
    if ($name === '')    fail(400, 'invalid', 'نام را وارد کنید.');
    if ($clsId !== '')   own('klass', $clsId, 'کلاس');

    // اگر کاربر از قبل حساب دارد، همان لحظه عضو می‌شود — دعوتی لازم نیست
    $st = db()->prepare('SELECT id FROM app_user WHERE phone = ?');
    $st->execute([$phone]);
    $uid = $st->fetchColumn();

    if ($uid) {
        $has = t_one('SELECT id FROM membership WHERE __I__ AND user_id = ?', [$uid]);
        if ($has) fail(409, 'already_member', 'این شماره از قبل عضو آموزشگاه است.');
        db()->prepare('INSERT INTO membership (id, institute_id, user_id, role, status, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([new_id(), inst_id(), $uid, $role, 'active', now_utc()]);
        if ($role === 'student' && $clsId !== '') enrol_student((string)$uid, $clsId);
        audit('member.added', my_id(), ['phone' => $phone, 'role' => $role]);
        ok(['joined' => true, 'message' => 'عضو شد. دفعهٔ بعد که وارد شود پنل خودش را می‌بیند.']);
    }

    $dup = t_one('SELECT id FROM invite WHERE __I__ AND phone = ? AND accepted_at IS NULL', [$phone]);
    if ($dup) fail(409, 'already_invited', 'برای این شماره از قبل دعوت ثبت شده.');

    db()->prepare('INSERT INTO invite (id, institute_id, phone, full_name, role, class_id, created_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([new_id(), inst_id(), $phone, $name, $role, $clsId ?: null, now_utc()]);
    audit('invite.created', my_id(), ['phone' => $phone, 'role' => $role]);
    ok(['joined' => false, 'message' => 'دعوت ثبت شد. به او بگویید با همین شماره وارد شود.']);

/* ─────────── حذف عضو ─────────── */
case 'removeMember':
    require_perm('member.remove');
    $m = own('membership', s_in($in, 'id', 32), 'عضو');
    if ((string)$m['user_id'] === my_id()) {
        fail(400, 'self', 'خودتان را نمی‌توانید حذف کنید.');
    }
    // ردیف عضویت غیرفعال می‌شود، نه پاک — تاریخچهٔ حضور و نمره باید بماند
    db()->prepare('UPDATE membership SET status = ? WHERE id = ?')->execute(['inactive', $m['id']]);
    audit('member.removed', my_id(), ['user' => $m['user_id']]);
    ok();

case 'setRate':
    require_perm('member.edit');
    $m = own('membership', s_in($in, 'id', 32), 'عضو');
    db()->prepare('UPDATE membership SET hourly_rate = ? WHERE id = ?')
        ->execute([i_in($in, 'rate', 0, 0, 999999999), $m['id']]);
    ok();

/*
 * مدیر فقط می‌تواند دسترسیِ «ساخت جلسهٔ میت» را به مدرسِ خودِ آموزشگاه
 * بدهد یا بگیرد — نه به خودش (که از ابتدا دارد) و نه به آموزشگاه دیگر.
 * بستنِ کامل این قابلیت برای یک آموزشگاه، یا گرفتنِ آن از خودِ مدیر،
 * فقط دست سوپرادمین است (super.php: membership.setMeetingAccess).
 */
case 'setMeetingAccess':
    require_perm('member.grant');
    $m = own('membership', s_in($in, 'id', 32), 'عضو');
    if ((string)$m['role'] !== 'teacher') {
        fail(400, 'invalid_target', 'این دسترسی فقط برای مدرس‌ها قابل تغییر است.');
    }
    $on = !empty($in['on']);
    if ($on && !ctx()['institute']['jitsiEnabled']) {
        fail(409, 'meeting_disabled', 'قابلیت جلسهٔ میت برای آموزشگاه شما فعال نیست.');
    }
    db()->prepare('UPDATE membership SET can_host_meeting = ? WHERE id = ?')->execute([$on ? 1 : 0, $m['id']]);
    audit('member.meeting_access_changed', my_id(), ['membership' => $m['id'], 'on' => $on]);
    ok();

/* ─────────── سالن‌ها ─────────── */
case 'addRoom':
    require_perm('room.manage');
    $n = s_in($in, 'name', 80);
    if ($n === '') fail(400, 'invalid', 'نام کلاس را وارد کنید.');
    $id = new_id();
    db()->prepare('INSERT INTO room (id, institute_id, name, capacity, created_at) VALUES (?,?,?,?,?)')
        ->execute([$id, inst_id(), $n, i_in($in, 'cap', 12, 1, 200), now_utc()]);
    ok(['id' => $id]);

case 'deleteRoom':
    require_perm('room.manage');
    $r = own('room', s_in($in, 'id', 32), 'کلاس');
    $used = t_one('SELECT id FROM klass WHERE __I__ AND room_id = ?', [$r['id']]);
    if ($used) fail(409, 'in_use', 'این کلاس در برنامهٔ درسی استفاده شده. اول کلاس‌ها را جابه‌جا کنید.');
    db()->prepare('DELETE FROM room WHERE id = ?')->execute([$r['id']]);
    ok();

default:
    fail(400, 'unknown_action', 'درخواست نامشخص.');
}

/** ثبت‌نام زبان‌آموز در کلاس، با رعایت ظرفیت */
function enrol_student(string $userId, string $classId): void
{
    $cl = own('klass', $classId, 'کلاس');
    $n = t_one('SELECT COUNT(*) AS n FROM enrolment WHERE __I__ AND class_id = ? AND status = ?', [$classId, 'active']);
    if ((int)($n['n'] ?? 0) >= (int)$cl['capacity']) {
        fail(409, 'class_full', 'ظرفیت این کلاس پر است.');
    }
    try {
        db()->prepare('INSERT INTO enrolment (id, institute_id, class_id, student_user_id, status, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([new_id(), inst_id(), $classId, $userId, 'active', now_utc()]);
    } catch (PDOException $e) {
        // کلید یکتا: از قبل ثبت‌نام است، دوباره‌کاری خطا نیست
        db()->prepare('UPDATE enrolment SET status = ? WHERE class_id = ? AND student_user_id = ?')
            ->execute(['active', $classId, $userId]);
    }
}
