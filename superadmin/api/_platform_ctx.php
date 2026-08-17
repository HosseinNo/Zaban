<?php
/**
 * کمکی‌های چندمستأجرهٔ سوپرادمین.
 *
 * ═══ چرا این فایل با panel/api/_ctx.php فرق دارد ═══
 *
 * _ctx.php برای کاربر *یک* آموزشگاه نوشته شده: ctx() یک عضویت فعال
 * پیدا می‌کند و هر کوئری بعدی خودش همان institute_id را به‌صورت ضمنی
 * تزریق می‌کند. سوپرادمین دقیقاً برعکس این نیاز را دارد — باید *عمداً*
 * بین آموزشگاه‌های مختلف حرکت کند. اگر از t_sql() چندمستأجرهٔ ضمنی
 * استفاده می‌کردیم، یا باید یک «مستأجر فعلی» فرضی می‌ساختیم (که خودش
 * منبع اشتباه است)، یا مجبور می‌شدیم institute_id را در صد جای دیگر
 * دستی تزریق کنیم.
 *
 * پس قاعده اینجا برعکس می‌شود: هیچ کوئری institute_id ضمنی ندارد؛
 * هر تابعی که به یک آموزشگاه مشخص محدود می‌شود، institute_id را
 * *صریح* به‌عنوان پارامتر ورودی می‌گیرد — هیچ‌وقت پیش‌فرض، هیچ‌وقت
 * از نشست خوانده نمی‌شود. انضباط قدیمی («هیچ کوئری بدون شرط مستأجر»)
 * حفظ می‌شود، فقط مستأجر از پارامتر می‌آید نه از نشست.
 */

declare(strict_types=1);

if (realpath(__FILE__) === realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    http_response_code(404);
    exit;
}

const PLATFORM_ROLES = ['manager', 'teacher', 'student'];

function new_id(): string { return bin2hex(random_bytes(16)); }

function id_ok(string $id): bool { return (bool)preg_match('/^[a-f0-9]{32}$/', $id); }

function s_in(array $in, string $key, int $max = 200, string $default = ''): string
{
    $v = trim((string)($in[$key] ?? $default));
    return mb_substr($v, 0, $max);
}

function id_in(array $in, string $key, string $what = 'شناسه'): string
{
    $v = trim((string)($in[$key] ?? ''));
    if (!id_ok($v)) fail(400, 'invalid_id', "$what نامعتبر است.");
    return $v;
}

/** ارقام فارسی و عربی → لاتین */
function en_digits(string $s): string
{
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($ar, $en, str_replace($fa, $en, $s));
}

/** آموزشگاه با این شناسه واقعاً وجود دارد؟ همان ردیف را برمی‌گرداند. */
function require_institute(string $instituteId): array
{
    $st = db()->prepare('SELECT * FROM institute WHERE id = ?');
    $st->execute([$instituteId]);
    $r = $st->fetch();
    if (!$r) fail(404, 'not_found', 'آموزشگاه پیدا نشد.');
    return $r;
}

/** کاربر با این شناسه واقعاً وجود دارد؟ */
function require_platform_user(string $userId): array
{
    $st = db()->prepare('SELECT * FROM app_user WHERE id = ?');
    $st->execute([$userId]);
    $r = $st->fetch();
    if (!$r) fail(404, 'not_found', 'کاربر پیدا نشد.');
    return $r;
}

/**
 * عضویت با این شناسه، به‌همراه آموزشگاه و کاربرش. برخلاف own() در
 * _ctx.php هیچ فرض تک‌مستأجره‌ای ندارد — سوپرادمین حق دارد عضویت هر
 * آموزشگاهی را ببیند، پس شرط مقایسه با یک institute_id ثابت اینجا
 * معنی ندارد.
 */
function membership_with_context(string $membershipId): array
{
    if (!id_ok($membershipId)) fail(404, 'not_found', 'عضویت پیدا نشد.');
    $st = db()->prepare(
        'SELECT m.*, u.full_name, u.phone, u.status AS user_status, i.name AS institute_name
           FROM membership m
           JOIN app_user u ON u.id = m.user_id
           JOIN institute i ON i.id = m.institute_id
          WHERE m.id = ?'
    );
    $st->execute([$membershipId]);
    $r = $st->fetch();
    if (!$r) fail(404, 'not_found', 'عضویت پیدا نشد.');
    return $r;
}

/**
 * جلوی خالی‌ماندن آموزشگاه از مدیر را می‌گیرد — بدون این، تعلیق یا
 * تغییر نقش آخرین مدیر یعنی آموزشگاهی که دیگر هیچ‌کس نمی‌تواند
 * ادارهٔ آن پنل را انجام دهد، و سوپرادمین هم به محتوای آن دسترسی
 * مستقیم ندارد (فقط با impersonation).
 */
function is_last_active_manager(string $instituteId, string $membershipIdToExclude): bool
{
    $st = db()->prepare(
        "SELECT COUNT(*) FROM membership
          WHERE institute_id = ? AND role = 'manager' AND status = 'active' AND id <> ?"
    );
    $st->execute([$instituteId, $membershipIdToExclude]);
    return (int)$st->fetchColumn() === 0;
}
