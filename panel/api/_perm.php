<?php
/**
 * موتور دسترسی — نسخهٔ ۶.
 *
 * تا اینجا کد می‌پرسید «آیا این کاربر مدیر است؟». از اینجا می‌پرسد
 * «آیا اجازهٔ ساخت کلاس دارد؟». تفاوت فنی به‌نظر می‌رسد ولی محصولی
 * است: تا وقتی نام نقش در کد حک شده باشد، ساخت نقش تازه یا دادن یک
 * دسترسی خاص به یک نفر نیازمند تغییر کد و انتشار دوباره است.
 *
 * ═══ چرا این فایل هنوز چیزی را عوض نمی‌کند ═══
 *
 * این فاز عمداً افزودنی محض است. تابع require_role() در _ctx.php
 * دست‌نخورده می‌ماند و همچنان تصمیم می‌گیرد؛ موتور تازه کنارش می‌نشیند
 * و قابل آزمون است ولی هنوز بار نمی‌برد.
 *
 * وسوسه این بود که همان حالا require_role() را به پوسته‌ای روی این
 * موتور تبدیل کنیم. این کار خطرناک است: اگر نگاشت نقش به مجوز کمی
 * گشادتر از اختیار امروز باشد، هیچ خطایی دیده نمی‌شود — فقط یک مدرس
 * ناگهان چیزی را می‌بیند که نباید. تبدیل نقطه‌به‌نقطه در فاز ۳ انجام
 * می‌شود و هر کدام با آزمون هم‌ارزی perm_equivalence_report() سنجیده
 * می‌شود: تصمیم قدیم و جدید باید برای هر نقش یکسان باشد.
 *
 * ═══ ترتیب تصمیم ═══
 *
 * زمینهٔ فعال ← اعتبار عضویت ← وضعیت آموزشگاه ← مجوز مؤثر ← محدوده.
 * ترتیب خودش بخشی از امنیت است؛ جابه‌جا کردنش حفره می‌سازد.
 *
 * مالک پلتفرم اینجا استثنا ندارد و لازم هم نیست: مالک از پنل خودش
 * (superadmin/) با کوکی جدا کار می‌کند، و برای دیدن داخل یک آموزشگاه
 * از «ورود به‌جای کاربر» استفاده می‌کند که نشست عادی همان کاربر را
 * می‌سازد. یعنی وقتی در این پنل است، عمداً به همان اندازهٔ آن کاربر
 * می‌بیند — که نکتهٔ اصلی impersonate است.
 */

declare(strict_types=1);

if (realpath(__FILE__) === realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    http_response_code(404);
    exit;
}

/* ═════════════════════ محدودهٔ داده ═════════════════════ */

/**
 * از باریک به گشاد. ترتیب معنادار است: widest_scope() فقط بزرگ‌ترین
 * اندیس را برمی‌دارد، پس افزودن سطح تازه یعنی گذاشتنش در جای درست
 * همین آرایه و نه جای دیگر.
 *
 * branch عمداً بین own_classes و institute است و فعلاً جدولش خالی
 * می‌ماند؛ تا وقتی شعبه‌ای تعریف نشده مثل institute رفتار می‌کند.
 */
const PERM_SCOPES = ['own', 'assigned_students', 'own_classes', 'branch', 'institute', 'platform'];

function scope_rank(string $scope): int
{
    $i = array_search($scope, PERM_SCOPES, true);
    return $i === false ? 0 : (int)$i;
}

function widest_scope(?string $a, ?string $b): string
{
    if ($a === null) return (string)($b ?? 'own');
    if ($b === null) return $a;
    return scope_rank($a) >= scope_rank($b) ? $a : $b;
}

/* ═════════════════════ زمینهٔ فعال ═════════════════════ */

/**
 * زمینهٔ فعال: کاربر، آموزشگاه، و نقشی که همین حالا با آن کار می‌کند.
 *
 * در این فاز از ctx() موجود ساخته می‌شود چون قید تک‌نقشی هنوز سر جایش
 * است و هر کاربر در هر آموزشگاه دقیقاً یک عضویت دارد. در فاز ۴ که
 * چند-نقشی فعال شود، منبع این اطلاعات ستون‌های active_* روی
 * session_token خواهد بود — امضای تابع همان می‌ماند تا کد صدازننده
 * دست نخورد.
 *
 * @return array{user_id:string, institute_id:string, role_id:string, role_key:string, plan:string}
 */
function active_context(): array
{
    static $ac = null;
    if ($ac !== null) return $ac;

    $c = ctx();

    $st = db()->prepare(
        'SELECT m.role_id, m.role, m.expires_at, i.plan
           FROM membership m
           JOIN institute i ON i.id = m.institute_id
          WHERE m.institute_id = ? AND m.user_id = ? AND m.status = ?
          LIMIT 1'
    );
    $st->execute([$c['institute_id'], $c['user']['id'], 'active']);
    $m = $st->fetch();

    /*
     * اگر ctx() عضویت پیدا کرده ولی اینجا نه، یعنی داده ناسازگار است.
     * به‌جای ادامه‌دادن با زمینهٔ نصفه، صریح شکست می‌خوریم.
     */
    if (!$m) {
        fail(403, 'no_membership', 'عضویت شما در این آموزشگاه پیدا نشد.');
    }

    // عضویت زمان‌دار که تاریخش گذشته، زمینه را باطل می‌کند — نه اینکه
    // دسترسی کمتری بدهد. این همان چیزی است که پایان دمو را اعمال می‌کند.
    if ($m['expires_at'] !== null && (string)$m['expires_at'] <= now_utc()) {
        fail(403, 'membership_expired',
            'دورهٔ دسترسی شما به این آموزشگاه تمام شده. برای تمدید با پشتیبانی تماس بگیرید.');
    }

    /*
     * role_id پس از مهاجرت همیشه پر است. اگر به هر دلیلی نبود، از روی
     * ستون قدیمی role بازسازی می‌شود تا نبود یک ردیف، کل درخواست را
     * نشکند. این پل تا پایان فاز ۳ می‌ماند.
     */
    $roleId = (string)($m['role_id'] ?? '');
    if ($roleId === '') {
        $roleId = 'r_' . (string)$m['role'];
    }

    $ac = [
        'user_id'      => (string)$c['user']['id'],
        'institute_id' => (string)$c['institute_id'],
        'role_id'      => $roleId,
        'role_key'     => (string)$m['role'],
        'plan'         => (string)($m['plan'] ?? 'active'),
    ];
    return $ac;
}

function is_readonly_institute(): bool
{
    return active_context()['plan'] === 'readonly';
}

/* ═════════════════════ مجوزهای مؤثر ═════════════════════ */

/**
 * مجوز مؤثر = (بستهٔ نقش ∪ اعطای موردی) − سلب موردی
 *
 * سلب همیشه برنده است، بی‌استثنا — حتی بر اعطای صریح. هر استثنایی روی
 * این قاعده، «این یکی نبیند» را غیرقابل‌اتکا می‌کند.
 *
 * @return array<string,string>  کلید مجوز ← محدوده
 */
function effective_perms(): array
{
    static $eff = null;
    if ($eff !== null) return $eff;

    $ac = active_context();
    $out = [];

    // ── لایهٔ یک: بستهٔ نقش ──
    $st = db()->prepare('SELECT perm_key, scope FROM role_permission WHERE role_id = ?');
    $st->execute([$ac['role_id']]);
    foreach ($st->fetchAll() as $r) {
        $out[(string)$r['perm_key']] = (string)$r['scope'];
    }

    // ── لایه‌های دو و سه: اعطا و سلب موردی ──
    // هر دو در یک کوئری می‌آیند تا رفت‌وبرگشت اضافه نشود، ولی سلب‌ها
    // در پایان اعمال می‌شوند تا ترتیب ردیف‌ها روی نتیجه اثر نگذارد.
    $st = db()->prepare(
        'SELECT perm_key, effect, scope FROM user_permission
          WHERE institute_id = ? AND user_id = ?
            AND (expires_at IS NULL OR expires_at > ?)'
    );
    $st->execute([$ac['institute_id'], $ac['user_id'], now_utc()]);

    $denied = [];
    foreach ($st->fetchAll() as $r) {
        $k = (string)$r['perm_key'];
        if ((string)$r['effect'] === 'deny') {
            $denied[$k] = true;
            continue;
        }
        // اعطای موردی می‌تواند محدوده را گشادتر کند، ولی هرگز باریک‌تر
        $out[$k] = widest_scope($out[$k] ?? null, $r['scope'] !== null ? (string)$r['scope'] : null);
    }

    foreach (array_keys($denied) as $k) {
        unset($out[$k]);
    }

    $eff = $out;
    return $eff;
}

/* ═════════════════════ رابط عمومی ═════════════════════ */

/** بررسی نرم — برای ساختن منو، بدون پرتاب خطا */
function can(string $perm): bool
{
    return isset(effective_perms()[$perm]);
}

/** محدودهٔ این مجوز در زمینهٔ فعال، یا null اگر مجوز ندارد */
function perm_scope(string $perm): ?string
{
    return effective_perms()[$perm] ?? null;
}

/**
 * تصمیم سخت. چند مجوز یعنی «هرکدام کافی است»، نه «همه لازم است» —
 * چون الگوی رایج در این کد require_role('manager','teacher') است که
 * همین معنا را دارد.
 */
function require_perm(string ...$perms): void
{
    $eff = effective_perms();

    $hit = null;
    foreach ($perms as $p) {
        if (isset($eff[$p])) { $hit = $p; break; }
    }

    if ($hit === null) {
        fail(403, 'forbidden', 'این کار در دسترس شما نیست.');
    }

    /*
     * آموزشگاه فقط-خواندنی (پایان دمو): مجوزهای نوشتنی رد می‌شوند،
     * خواندنی‌ها عبور می‌کنند. پیام عمدی و مشخص است — کاربر باید بداند
     * چه اتفاقی افتاده و راه برگشت چیست، نه اینکه فکر کند خراب شده.
     */
    if (is_readonly_institute() && perm_is_write($hit)) {
        fail(403, 'readonly',
            'دورهٔ آزمایشی این آموزشگاه تمام شده. اطلاعات‌تان سر جایش است و می‌بینیدش، '
          . 'ولی برای ثبت تغییر تازه باید اشتراک را فعال کنید.');
    }
}

/** همهٔ مجوزها لازم است، نه یکی */
function require_all_perms(string ...$perms): void
{
    foreach ($perms as $p) {
        require_perm($p);
    }
}

/**
 * آیا این مجوز نوشتنی است؟ از کاتالوگ خوانده می‌شود، نه از روی نام —
 * چون حدس‌زدن از روی پسوند (create/edit/…) دیر یا زود اشتباه می‌شود.
 */
function perm_is_write(string $perm): bool
{
    static $cache = [];
    if (array_key_exists($perm, $cache)) return $cache[$perm];

    $st = db()->prepare('SELECT is_write FROM permission WHERE perm_key = ?');
    $st->execute([$perm]);
    $v = $st->fetchColumn();

    // مجوز ناشناخته را نوشتنی فرض می‌کنیم؛ سخت‌گیری امن‌تر از سهل‌گیری است
    $cache[$perm] = $v === false ? true : ((int)$v === 1);
    return $cache[$perm];
}

/* ═════════════════════ فیلتر محدوده ═════════════════════ */

/**
 * قطعهٔ SQL که محدودهٔ داده را روی جدول کلاس اعمال می‌کند.
 *
 * شرط آموزشگاه جداگانه و از راه t_sql() اضافه می‌شود؛ این تابع فقط
 * لایهٔ باریک‌ترکننده را می‌سازد. برمی‌گرداند [قطعهٔ SQL، آرگومان‌ها].
 *
 * institute و بالاتر قطعه‌ای اضافه نمی‌کنند چون خود t_sql() آموزشگاه
 * را بسته است. branch تا وقتی جدول شعبه خالی است، همان رفتار را دارد.
 *
 * @param string $alias نام مستعار جدول klass در کوئری، مثلاً 'k'
 * @return array{0:string,1:array}
 */
function class_scope_sql(string $scope, string $alias = 'k'): array
{
    $a  = $alias === '' ? '' : $alias . '.';
    $me = active_context()['user_id'];

    switch ($scope) {
        case 'platform':
        case 'institute':
        case 'branch':
            return ['', []];

        case 'own_classes':
        case 'assigned_students':
            return [" AND {$a}teacher_user_id = ?", [$me]];

        case 'own':
        default:
            // زبان‌آموز: فقط کلاسی که در آن ثبت‌نام فعال دارد
            return [
                " AND {$a}id IN (SELECT class_id FROM enrolment
                                  WHERE student_user_id = ? AND status = 'active')",
                [$me],
            ];
    }
}

/**
 * همان برای فهرست کاربران آموزشگاه.
 *
 * assigned_students یعنی «زبان‌آموزان کلاس‌های خودم» — همان چیزی که
 * مدرس برای صفحهٔ پشتیبانی و گفت‌وگو لازم دارد و بیش از آن نه.
 *
 * @param string $col ستونی که شناسهٔ کاربر در آن است
 * @return array{0:string,1:array}
 */
function user_scope_sql(string $scope, string $col = 'm.user_id'): array
{
    $me = active_context()['user_id'];

    switch ($scope) {
        case 'platform':
        case 'institute':
        case 'branch':
            return ['', []];

        case 'own_classes':
        case 'assigned_students':
            return [
                " AND {$col} IN (SELECT e.student_user_id FROM enrolment e
                                   JOIN klass k ON k.id = e.class_id
                                  WHERE k.teacher_user_id = ? AND e.status = 'active')",
                [$me],
            ];

        case 'own':
        default:
            return [" AND {$col} = ?", [$me]];
    }
}

/* ═════════════════════ تفکیک وظایف ═════════════════════ */

/**
 * جلوی کاری را می‌گیرد که کاربر روی دادهٔ خودش نباید بکند.
 *
 * تا وقتی چند-نقشی نبود این حالت ممکن نبود. با فاز ۴ ممکن می‌شود:
 * مدرسی که در کلاس همکارش زبان‌آموز است، نباید بتواند تکلیف خودش را
 * تصحیح کند یا نمرهٔ خودش را ثبت کند — حتی با داشتن هر دو مجوز.
 *
 * از حالا نوشته می‌شود تا فاز ۳ که نقاط تصحیح و نمره را تبدیل می‌کند،
 * جایش آماده باشد.
 */
function deny_self_action(string $subjectUserId, string $what = 'این کار'): void
{
    if ($subjectUserId === active_context()['user_id']) {
        fail(409, 'self_action', $what . ' روی دادهٔ خودتان ممکن نیست.');
    }
}

/* ═════════════════════ ابزار مهاجرت ═════════════════════ */

/**
 * گزارش هم‌ارزی — ابزار فاز ۳، نه نقطهٔ پایانی عمومی.
 *
 * برای یک نقش مشخص، مجموعهٔ مجوزهایش را برمی‌گرداند تا بشود با
 * رفتار require_role() امروز مقایسه کرد. پیش از حذف هر بررسی قدیمی،
 * تصمیم قدیم و جدید باید یکی باشد؛ اولین تفاوت یعنی توقف، نه انتشار.
 *
 * @return array<string,string>
 */
function role_perm_map(string $roleId): array
{
    $st = db()->prepare('SELECT perm_key, scope FROM role_permission WHERE role_id = ? ORDER BY perm_key');
    $st->execute([$roleId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[(string)$r['perm_key']] = (string)$r['scope'];
    }
    return $out;
}
