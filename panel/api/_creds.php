<?php
/**
 * نام کاربری و رمز کاربران آموزشگاه.
 *
 * ═══ چرا هم پیامک هم رمز ═══
 *
 * کد یک‌بارمصرف برای زبان‌آموزی که ترمی چند بار وارد می‌شود عالی است:
 * چیزی برای فراموش‌کردن ندارد. ولی برای منشی‌ای که روزی بیست بار وارد
 * می‌شود یعنی بیست بار انتظار پیامک، و روزی که اعتبار پنل پیامک تمام
 * شود یعنی هیچ‌کس نمی‌تواند وارد شود.
 *
 * پس هر دو: رمز اختیاری است و کنار پیامک می‌نشیند، نه به‌جایش. هیچ
 * حسابی مجبور به ساختن رمز نیست و هیچ حسابی با نداشتنش قفل نمی‌شود.
 *
 * بازیابی رمز هم از راه همان شماره است — چیزی که کاربر قبلاً ثابت
 * کرده مال اوست. ایمیل در کار نیست، چون بیشتر زبان‌آموزها ایمیلی که
 * بخوانند ندارند.
 */

declare(strict_types=1);

if (realpath(__FILE__) === realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    http_response_code(404);
    exit;
}

/**
 * نام کاربری را تمیز می‌کند و اگر بی‌ربط بود پیام می‌دهد.
 *
 * فقط حروف انگلیسی کوچک، رقم، نقطه، زیرخط و خط تیره. عمداً حروف فارسی
 * را نمی‌پذیریم: نام کاربری فارسی روی صفحه‌کلیدهای مختلف با نویسه‌های
 * متفاوتِ ظاهراً یکسان تایپ می‌شود (ی عربی در برابر ی فارسی، ک در برابر
 * ك) و کاربر بدون اینکه بفهمد چرا، نمی‌تواند وارد شود.
 */
function username_normalize(string $raw): string
{
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    $u  = str_replace($ar, $en, str_replace($fa, $en, $raw));
    return strtolower(trim($u));
}

function username_check(string $u): void
{
    if (!preg_match('/^[a-z0-9._-]{4,64}$/', $u)) {
        fail(400, 'invalid_username',
            'نام کاربری بین ۴ تا ۶۴ نویسه و فقط حروف انگلیسی کوچک، رقم، نقطه، زیرخط و خط تیره.');
    }
    if (preg_match('/^09\d{9}$/', $u)) {
        /*
         * شمارهٔ موبایل نام کاربری خوبی نیست: کاربر بعداً نمی‌فهمد که
         * در فرم ورود باید شماره بزند یا نام کاربری، و هر دو خانه شبیه
         * هم‌اند.
         */
        fail(400, 'invalid_username', 'نام کاربری نمی‌تواند شمارهٔ موبایل باشد. یک نام انتخاب کنید.');
    }
}

/**
 * قواعد رمز.
 *
 * حداقل هشت نویسه و نه‌فقط رقم. سخت‌گیری بیشتر — نماد و حرف بزرگ
 * اجباری — روی کاربر ایرانی که با صفحه‌کلید فارسی کار می‌کند بیشتر
 * باعث نوشتن رمز روی کاغذ می‌شود تا امنیت.
 */
function password_check(string $p): void
{
    if (mb_strlen($p) < 8) {
        fail(400, 'weak_password', 'رمز باید حداقل ۸ نویسه باشد.');
    }
    if (preg_match('/^\d+$/', $p)) {
        fail(400, 'weak_password', 'رمز نمی‌تواند فقط عدد باشد.');
    }
}

/** آیا این نام کاربری آزاد است؟ ($exceptId برای وقتی کاربر نام خودش را نگه می‌دارد) */
function username_free(string $u, ?string $exceptId = null): bool
{
    $sql  = 'SELECT id FROM app_user WHERE username = ?';
    $args = [$u];
    if ($exceptId !== null) { $sql .= ' AND id <> ?'; $args[] = $exceptId; }
    $st = db()->prepare($sql . ' LIMIT 1');
    $st->execute($args);
    return !$st->fetchColumn();
}

/**
 * رمز را روی حساب می‌نشاند و نشست‌های دیگر را باطل می‌کند.
 *
 * چرا باطل‌کردن: اگر کسی به حساب دسترسی پیدا کرده بود، عوض‌کردن رمز
 * باید بیرونش بیندازد. نشست خودِ همین درخواست می‌ماند تا کاربر بلافاصله
 * بعد از تغییر رمز، خودش بیرون نیفتد.
 */
function set_password(string $userId, string $plain, ?string $keepToken = null): void
{
    db()->prepare('UPDATE app_user SET pass_hash = ? WHERE id = ?')
        ->execute([password_hash($plain, PASSWORD_DEFAULT), $userId]);

    $sql  = 'UPDATE session_token SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL';
    $args = [now_utc(), $userId];
    if ($keepToken !== null && preg_match('/^[a-f0-9]{64}$/', $keepToken)) {
        $sql .= ' AND token_hash <> ?';
        $args[] = hash('sha256', $keepToken);
    }
    db()->prepare($sql)->execute($args);
}
