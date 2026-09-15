-- ۰۱۵ — هماهنگ‌کردن membership.role با membership.role_id
--
-- پنل سوپرادمین دو جا فقط ستون متنی role را عوض می‌کرد:
--
--   membership.setRole                 (تغییر نقش عضو)
--   membership.add، مسیر «از قبل عضو است»
--
-- ولی مجوزها از role_id خوانده می‌شوند (active_context در _perm.php).
-- نتیجه: مدیری که سوپرادمین به «زبان‌آموز» تنزل می‌داد، برچسب
-- زبان‌آموز می‌گرفت و همهٔ اختیارات مدیر را نگه می‌داشت — تنظیمات
-- آموزشگاه را ذخیره می‌کرد، فهرست همهٔ اعضا را می‌دید، عضو حذف می‌کرد.
-- برعکسش هم: مدرسی که «مدیر» می‌شد، اختیارات مدرس را داشت.
--
-- کد در همان کامیت رفع شده. این مهاجرت ردیف‌هایی را ترمیم می‌کند که
-- پیش از رفع، با آن دکمه ناهماهنگ شده‌اند.
--
-- کدام ستون درست است؟ role — چون همان چیزی است که سوپرادمین
-- آخرین بار انتخاب کرد. role_id فقط عقب مانده بود.
--
-- نقش‌های سفارشی دست نمی‌خورند: فقط ردیف‌هایی که role_id‌شان به یک
-- نقشِ سیستمی اشاره می‌کند و آن نقش با role نمی‌خواند. عضویتی که به
-- نقش سفارشیِ یک آموزشگاه وصل است، عمداً بیرون می‌ماند.

-- ── پیش از اجرا: ببینید چند ردیف ناهماهنگ است ──
SELECT m.id, m.institute_id, m.user_id, m.role AS role_text, cur.role_key AS role_id_points_to
  FROM membership m
  JOIN role cur ON cur.id = m.role_id AND cur.is_system = 1 AND cur.institute_id = ''
 WHERE m.role IN ('manager', 'teacher', 'student')
   AND cur.role_key <> m.role;

START TRANSACTION;

-- IGNORE: اگر کاربر همان نقش را از قبل با عضویت دیگری در همان
-- آموزشگاه دارد، قید یکتای uq_member_role جلوی تکرار را می‌گیرد و آن
-- ردیف دست‌نخورده می‌ماند — آن‌وقت با پرس‌وجوی بالا دوباره پیدا می‌شود
-- و باید دستی تصمیم گرفت (معمولاً غیرفعال‌کردن عضویت اضافه).
UPDATE IGNORE membership m
  JOIN role cur ON cur.id = m.role_id AND cur.is_system = 1 AND cur.institute_id = ''
  JOIN role want ON want.role_key = m.role AND want.is_system = 1 AND want.institute_id = ''
   SET m.role_id = want.id,
       m.can_host_meeting = CASE WHEN m.role = 'manager' THEN 1 ELSE 0 END
 WHERE m.role IN ('manager', 'teacher', 'student')
   AND cur.role_key <> m.role;

COMMIT;

-- بررسی پس از اجرا — باید خالی باشد (یا فقط ردیف‌های تکراریِ بالا):
--   همان SELECT بالای فایل.
