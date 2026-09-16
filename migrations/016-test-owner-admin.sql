-- ۰۱۶ — نام کاربری و رمز مالک پلتفرم: admin / admin  (فقط فاز تست)
--
-- ⚠ این رمز عمداً ضعیف است و درخواست خودِ مالک برای دورهٔ آزمایش بود.
--   پیش از اینکه اولین آموزشگاه واقعی وارد شود، باید عوض شود:
--   پنل سوپرادمین → «حساب من» → تغییر رمز.
--
--   تا وقتی این رمز روی سرور زنده است، هرکسی که آدرس admin.talkora.ir
--   را بداند می‌تواند وارد پنل پلتفرم شود: همهٔ آموزشگاه‌ها، همهٔ
--   کاربران، تنظیمات، و کلید پیامک.
--
-- هش با password_hash(PASSWORD_DEFAULT) ساخته شده؛ خودِ رشتهٔ «admin»
-- هیچ‌جا ذخیره نمی‌شود.

-- ── پیش از اجرا: ببینید مالک کیست ──
SELECT id, username, full_name, is_platform_owner, status
  FROM admin_user
 WHERE is_platform_owner = 1;

START TRANSACTION;

-- اگر ادمین دیگری از قبل نام «admin» را گرفته، اول کنارش می‌زنیم تا
-- قید یکتای نام کاربری نشکند.
UPDATE admin_user
   SET username = CONCAT('admin_old_', SUBSTRING(id, 1, 6))
 WHERE username = 'admin'
   AND is_platform_owner <> 1;

UPDATE admin_user
   SET username  = 'admin',
       pass_hash = '$2y$10$ZZ.xAvvLhFpfor99tvs1semf3G0LcNntAFd5XRWvFxNKD0tXS4A/S',
       status    = 'active'
 WHERE is_platform_owner = 1;

COMMIT;

-- ── بررسی پس از اجرا ──
--   SELECT username, status FROM admin_user WHERE is_platform_owner = 1;
--   باید بدهد: admin / active   — و ورود با admin/admin کار کند.
