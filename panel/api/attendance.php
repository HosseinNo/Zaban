<?php
/**
 * حضور و غیاب.
 *
 * دو قاعده که در بند Q آمده و اینجا اجرا می‌شود:
 *
 * ۱) پیش‌فرض «حاضر» است، نه خالی. مدرس فقط غایب‌ها را می‌زند. این تنها
 *    راهی است که ثبت حضور بیست‌ثانیه‌ای می‌شود؛ اگر مدرس مجبور باشد
 *    برای هر نفر تیک بزند، نصف جلسه‌ها ثبت‌نشده می‌ماند.
 *
 * ۲) ثبت دوباره، به‌روزرسانی است نه ردیف تازه — کلید یکتای
 *    (session_id, student_user_id) در اسکیما همین را تضمین می‌کند.
 *    مدرسی که دوبار submit بزند نباید حضور تکراری بسازد.
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_ctx.php';

require_post();
$in     = body_json();
$action = s_in($in, 'action', 40);

const STATUSES = ['present', 'absent', 'late', 'excused'];

switch ($action) {

/* ─────────── فهرست حضور یک جلسه ─────────── */
case 'get':
    $s  = own('class_session', s_in($in, 'id', 32), 'جلسه');
    $cl = own_class((string)$s['class_id']);

    $rows = t_all(
        'SELECT u.id, u.full_name, a.status, a.note
           FROM enrolment e
           JOIN app_user u ON u.id = e.student_user_id
           LEFT JOIN attendance a ON a.session_id = ? AND a.student_user_id = u.id
          WHERE e.__I__ AND e.class_id = ? AND e.status = ?
          ORDER BY u.full_name',
        [$s['id'], $cl['id'], 'active']
    );

    ok([
        'session' => [
            'id'     => (string)$s['id'],
            'seq'    => (int)$s['seq'],
            'date'   => (string)$s['session_date'],
            'time'   => (string)$s['start_time'],
            'status' => (string)$s['status'],
        ],
        'className' => (string)$cl['name'],
        'roster'    => array_map(fn($r) => [
            'id'     => (string)$r['id'],
            'name'   => (string)$r['full_name'],
            // هنوز ثبت نشده؟ پیش‌فرض حاضر — قاعدهٔ ۱
            'status' => $r['status'] ?: 'present',
            'saved'  => $r['status'] !== null,
            'note'   => $r['note'],
        ], $rows),
    ]);

/* ─────────── ثبت ─────────── */
case 'save':
    require_role('manager', 'teacher');
    $s  = own('class_session', s_in($in, 'id', 32), 'جلسه');
    $cl = own_class((string)$s['class_id']);

    if ($s['status'] === 'cancelled') {
        fail(409, 'cancelled', 'این جلسه لغو شده؛ حضور و غیاب ندارد.');
    }

    $marks = (array)($in['marks'] ?? []);
    if (!$marks) fail(400, 'invalid', 'چیزی برای ثبت نیست.');

    // فقط زبان‌آموزهای همین کلاس — تا شناسهٔ دستکاری‌شده ردیف بی‌ربط نسازد
    $allowed = [];
    foreach (t_all('SELECT student_user_id FROM enrolment WHERE __I__ AND class_id = ? AND status = ?', [$cl['id'], 'active']) as $r) {
        $allowed[(string)$r['student_user_id']] = true;
    }

    $db = db();
    $ins = $db->prepare('INSERT INTO attendance (id, institute_id, session_id, student_user_id, status, note, marked_by, marked_at) VALUES (?,?,?,?,?,?,?,?)');
    $upd = $db->prepare('UPDATE attendance SET status = ?, note = ?, marked_by = ?, marked_at = ? WHERE session_id = ? AND student_user_id = ? AND institute_id = ?');

    $n = 0; $skipped = 0;
    foreach ($marks as $m) {
        if (!is_array($m)) continue;
        $uid = s_in($m, 'id', 32);
        if (!isset($allowed[$uid])) { $skipped++; continue; }
        $st   = enum_in($m, 'status', STATUSES, 'present');
        $note = s_in($m, 'note', 255) ?: null;

        // درج‌کن‌وگرنه‌به‌روزرسان، قابل حمل بین MySQL و SQLite
        try {
            $ins->execute([new_id(), inst_id(), $s['id'], $uid, $st, $note, my_id(), now_utc()]);
        } catch (PDOException $e) {
            $upd->execute([$st, $note, my_id(), now_utc(), $s['id'], $uid, inst_id()]);
        }
        $n++;
    }

    // ثبت حضور یعنی جلسه برگزار شده
    if ($s['status'] !== 'done') {
        $db->prepare('UPDATE class_session SET status = ?, ended_at = COALESCE(ended_at, ?) WHERE id = ? AND institute_id = ?')
           ->execute(['done', now_utc(), $s['id'], inst_id()]);
    }

    audit('attendance.saved', my_id(), ['session' => $s['id'], 'marks' => $n]);
    ok(['saved' => $n, 'skipped' => $skipped]);

/* ─────────── تاریخچهٔ یک زبان‌آموز ─────────── */
case 'student':
    $uid = s_in($in, 'studentId', 32) ?: my_id();
    if ($uid !== my_id()) require_role('manager', 'teacher');

    $rows = t_all(
        'SELECT a.status, a.marked_at, s.session_date, s.seq, k.name AS class_name
           FROM attendance a
           JOIN class_session s ON s.id = a.session_id
           JOIN klass k ON k.id = s.class_id
          WHERE a.__I__ AND a.student_user_id = ?
          ORDER BY s.session_date DESC LIMIT 100',
        [$uid]
    );

    $tot = count($rows);
    $here = 0;
    foreach ($rows as $r) if ($r['status'] === 'present' || $r['status'] === 'late') $here++;

    ok([
        'percent' => $tot > 0 ? (int)round($here / $tot * 100) : null,
        'total'   => $tot,
        'rows'    => array_map(fn($r) => [
            'date'   => (string)$r['session_date'],
            'seq'    => (int)$r['seq'],
            'class'  => (string)$r['class_name'],
            'status' => (string)$r['status'],
        ], $rows),
    ]);

default:
    fail(400, 'unknown_action', 'درخواست نامشخص.');
}
