<?php
/**
 * تکالیف: ساخت، ارسال توسط زبان‌آموز، تصحیح.
 *
 * دربارهٔ فایل: آپلود پشتیبانی می‌شود ولی محتاطانه — پسوند از فهرست
 * سفید، سقف حجم، نام تصادفی روی دیسک (نام اصلی فقط برای نمایش)، و
 * پوشه‌ای که اجرای PHP در آن خاموش است. دانلود هم از همین‌جا و بعد از
 * بررسی دسترسی انجام می‌شود، نه با لینک مستقیم به فایل.
 *
 * صوت گفتاری: روی هاست اشتراکی فضا محدود است. ارسال صوت کار می‌کند،
 * ولی سقف حجم پایین است و برای استفادهٔ جدی باید فضای ابری اضافه شود.
 */
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_ctx.php';
require __DIR__ . '/_perm.php';

const ALLOWED_EXT  = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'mp3', 'm4a', 'ogg', 'wav', 'webm', 'docx', 'txt'];
const MAX_UPLOAD   = 8 * 1024 * 1024;   // ۸ مگابایت
const TYPES        = ['writing', 'speaking', 'quiz', 'file'];

/* دانلود فایل با GET انجام می‌شود؛ بقیهٔ کارها POST */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['download'])) {
    download_submission((string)$_GET['download']);
}

/* آپلود چندبخشی است، پس body_json آن را نمی‌خواند */
$isUpload = !empty($_FILES) || (($_POST['action'] ?? '') !== '');
$in       = $isUpload ? $_POST : body_json();
require_post();
$action = s_in($in, 'action', 40);

switch ($action) {

/* ─────────── ساخت ─────────── */
case 'create':
    require_perm('assignment.create');
    $cl = own_class(s_in($in, 'classId', 32));

    $title = s_in($in, 'title', 200);
    if ($title === '') fail(400, 'invalid', 'عنوان تکلیف را وارد کنید.');

    $due = date_in($in, 'dueDate');
    $dueAt = $due ? $due . ' ' . time_in($in, 'dueTime', '23:59') . ':00' : null;

    $id = new_id();
    db()->prepare(
        'INSERT INTO assignment (id, institute_id, class_id, title, type, description, due_at, max_score, status, created_by, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $id, inst_id(), $cl['id'], $title,
        enum_in($in, 'type', TYPES, 'writing'),
        s_in($in, 'desc', 4000) ?: null,
        $dueAt, i_in($in, 'max', 20, 1, 100), 'open', my_id(), now_utc(),
    ]);
    audit('assignment.created', my_id(), ['assignment' => $id, 'class' => $cl['id']]);
    ok(['id' => $id]);

case 'close':
    require_perm('assignment.edit');
    $a = own('assignment', s_in($in, 'id', 32), 'تکلیف');
    own_class((string)$a['class_id']);
    db()->prepare('UPDATE assignment SET status = ? WHERE id = ? AND institute_id = ?')->execute(['closed', $a['id'], inst_id()]);
    ok();

case 'delete':
    require_perm('assignment.delete');
    $a = own('assignment', s_in($in, 'id', 32), 'تکلیف');
    own_class((string)$a['class_id']);
    $n = (int)(t_one('SELECT COUNT(*) AS n FROM submission WHERE __I__ AND assignment_id = ?', [$a['id']])['n'] ?? 0);
    if ($n > 0) fail(409, 'has_submissions', "{$n} پاسخ برای این تکلیف ثبت شده و حذفش نمرهٔ زبان‌آموزها را پاک می‌کند. به‌جایش ببندیدش.");
    db()->prepare('DELETE FROM assignment WHERE id = ? AND institute_id = ?')->execute([$a['id'], inst_id()]);
    ok();

/* ─────────── ارسال پاسخ ─────────── */
case 'submit':
    require_perm('assignment.submit');
    $a  = own('assignment', s_in($in, 'id', 32), 'تکلیف');
    own_class((string)$a['class_id']);   // ثبت‌نام بودن را بررسی می‌کند

    if ($a['status'] === 'closed') fail(409, 'closed', 'این تکلیف بسته شده.');

    $text = s_in($in, 'text', 20000);
    $file = save_upload();
    if ($text === '' && !$file) fail(400, 'empty', 'متن یا فایل پاسخ را بدهید.');

    $late = $a['due_at'] && strtotime((string)$a['due_at'] . ' UTC') < time();

    $existing = t_one('SELECT id, file_path FROM submission WHERE __I__ AND assignment_id = ? AND student_user_id = ?', [$a['id'], my_id()]);

    if ($existing) {
        if ($existing['file_path'] && $file) unlink_quiet((string)$existing['file_path']);
        db()->prepare(
            'UPDATE submission SET body_text = ?, file_path = COALESCE(?, file_path), file_name = COALESCE(?, file_name),
                    file_size = COALESCE(?, file_size), submitted_at = ?, is_late = ?
              WHERE id = ? AND institute_id = ?'
        )->execute([$text ?: null, $file['path'] ?? null, $file['name'] ?? null, $file['size'] ?? null,
                    now_utc(), $late ? 1 : 0, $existing['id'], inst_id()]);
        $sid = (string)$existing['id'];
    } else {
        $sid = new_id();
        db()->prepare(
            'INSERT INTO submission (id, institute_id, assignment_id, student_user_id, body_text, file_path, file_name, file_size, submitted_at, is_late)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([$sid, inst_id(), $a['id'], my_id(), $text ?: null,
                    $file['path'] ?? null, $file['name'] ?? null, $file['size'] ?? null, now_utc(), $late ? 1 : 0]);
    }

    audit('submission.saved', my_id(), ['assignment' => $a['id'], 'late' => $late]);
    ok(['id' => $sid, 'late' => $late, 'hasFile' => (bool)$file]);

/* ─────────── تصحیح ─────────── */
case 'grade':
    require_perm('assignment.grade');
    $sub = own('submission', s_in($in, 'id', 32), 'پاسخ');
    $a   = own('assignment', (string)$sub['assignment_id'], 'تکلیف');
    own_class((string)$a['class_id']);

    $raw = en_digits(trim((string)($in['score'] ?? '')));
    if ($raw === '' || !is_numeric($raw)) fail(400, 'invalid', 'نمره را وارد کنید.');
    $score = (float)$raw;
    $max   = (float)$a['max_score'];
    if ($score < 0 || $score > $max) {
        fail(400, 'out_of_range', 'نمره باید بین ۰ و ' . rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.') . ' باشد.');
    }

    db()->prepare('UPDATE submission SET score = ?, feedback = ?, graded_by = ?, graded_at = ? WHERE id = ? AND institute_id = ?')
        ->execute([$score, s_in($in, 'feedback', 4000) ?: null, my_id(), now_utc(), $sub['id'], inst_id()]);

    audit('submission.graded', my_id(), ['submission' => $sub['id'], 'score' => $score]);
    ok();

/* ─────────── صف تصحیح ─────────── */
case 'queue':
    require_perm('assignment.grade');
    /*
     * محدوده از مجوز می‌آید، نه از نام نقش. پیش از این نوشته بود
     * «اگر مدرس است فیلتر بزن، وگرنه نه» — که یعنی هر نقش سفارشیِ
     * تازه‌ای که مجوز تصحیح بگیرد، بی‌فیلتر کل صف آموزشگاه را می‌دید.
     */
    [$where, $args] = class_scope_sql(perm_scope('assignment.grade') ?? 'own', 'k');
    $rows  = t_all(
        "SELECT s.id, s.submitted_at, s.is_late, s.body_text, s.file_name,
                a.title, a.type, a.max_score, u.full_name, k.name AS class_name
           FROM submission s
           JOIN assignment a ON a.id = s.assignment_id
           JOIN klass k ON k.id = a.class_id
           JOIN app_user u ON u.id = s.student_user_id
          WHERE s.__I__ AND s.score IS NULL{$where}
          ORDER BY s.submitted_at LIMIT 100",
        $args
    );
    ok(['queue' => array_map(fn($r) => [
        'id'       => (string)$r['id'],
        'student'  => (string)$r['full_name'],
        'class'    => (string)$r['class_name'],
        'title'    => (string)$r['title'],
        'type'     => (string)$r['type'],
        'max'      => (int)$r['max_score'],
        'at'       => (string)$r['submitted_at'],
        'late'     => (bool)$r['is_late'],
        'text'     => $r['body_text'],
        'fileName' => $r['file_name'],
    ], $rows)]);

default:
    fail(400, 'unknown_action', 'درخواست نامشخص.');
}

/* ═══════════ فایل ═══════════ */

function upload_dir(): string
{
    $c = cfg();
    $d = $c['upload_dir'] ?? (__DIR__ . '/../uploads');
    if (!is_dir($d)) @mkdir($d, 0750, true);
    // اگر پوشه ناچار داخل webroot است، اجرای PHP در آن خاموش شود
    $ht = $d . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\nOptions -Indexes\nphp_flag engine off\n");
    }
    return $d;
}

/** @return array{path:string,name:string,size:int}|null */
function save_upload(): ?array
{
    if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) fail(400, 'upload_failed', 'آپلود فایل کامل نشد.');
    if ($f['size'] > MAX_UPLOAD)       fail(413, 'too_large', 'حجم فایل بیشتر از ۸ مگابایت است.');

    $orig = (string)$f['name'];
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        fail(400, 'bad_type', 'این نوع فایل پذیرفته نمی‌شود. مجاز: ' . implode('، ', ALLOWED_EXT));
    }
    if (!is_uploaded_file($f['tmp_name'])) fail(400, 'upload_failed', 'فایل معتبر نیست.');

    // نام روی دیسک تصادفی است: نام کاربر هرگز به مسیر فایل تبدیل نمی‌شود
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = upload_dir() . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        error_log('move_uploaded_file failed: ' . $dest);
        fail(500, 'upload_failed', 'ذخیرهٔ فایل ممکن نشد.');
    }
    @chmod($dest, 0640);

    return ['path' => $name, 'name' => mb_substr($orig, 0, 200), 'size' => (int)$f['size']];
}

function unlink_quiet(string $name): void
{
    $p = upload_dir() . '/' . basename($name);
    if (is_file($p)) @unlink($p);
}

/** دانلود فقط بعد از بررسی دسترسی — لینک مستقیم به پوشهٔ فایل‌ها وجود ندارد */
function download_submission(string $id): never
{
    $sub = own('submission', $id, 'پاسخ');
    $a   = own('assignment', (string)$sub['assignment_id'], 'تکلیف');

    // محدودهٔ own یعنی فقط فایل خودش؛ گشادتر از آن، از راه own_class بررسی می‌شود
    if ((perm_scope('assignment.view') ?? 'own') === 'own') {
        if ((string)$sub['student_user_id'] !== my_id()) fail(403, 'forbidden', 'این پاسخ شما نیست.');
    } else {
        own_class((string)$a['class_id'], 'assignment.view');
    }

    if (empty($sub['file_path'])) fail(404, 'no_file', 'فایلی ثبت نشده.');
    $p = upload_dir() . '/' . basename((string)$sub['file_path']);
    if (!is_file($p)) fail(404, 'no_file', 'فایل روی سرور پیدا نشد.');

    $name = (string)($sub['file_name'] ?: 'file');
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($p));
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^\w.\-]/u', '_', $name) . '"; '
         . "filename*=UTF-8''" . rawurlencode($name));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($p);
    exit;
}
