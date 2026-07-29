#!/usr/bin/env python3
"""
دو بستهٔ آمادهٔ آپلود می‌سازد، چون سایت و پنل روی دو دامنهٔ جدا بالا می‌آیند:

  dist/site/    →  httpdocs دامنهٔ اصلی      talkora.ir
  dist/panel/   →  httpdocs زیردامنهٔ پنل     panel.talkora.ir

چرا جدا؟ پنل بک‌اند PHP و پایگاه داده دارد و سایت معرفی هیچ‌کدام را ندارد.
با جداکردن‌شان، کوکی نشست فقط روی زیردامنهٔ پنل ست می‌شود، صفحهٔ فروش
هیچ‌وقت به دیتابیس دست نمی‌زند، و اگر پنل خطا داد سایت پایین نمی‌آید.

اجرا:  python3 build-dist.py
"""
import pathlib
import re
import shutil
import sys
import zipfile

ROOT = pathlib.Path(__file__).resolve().parent
DIST = ROOT / "dist"

SITE_DOMAIN = "talkora.ir"
PANEL_DOMAIN = "panel.talkora.ir"
PANEL_URL = f"https://{PANEL_DOMAIN}/"

# تنها مقصد بیرونی مجاز: سایت به پنل لینک می‌دهد و برعکس.
# هر ارجاع دیگری به دامنهٔ خارجی، ساخت را متوقف می‌کند — قاعدهٔ P.7
ALLOWED_EXTERNAL = (f"https://{SITE_DOMAIN}", f"https://{PANEL_DOMAIN}", f"https://www.{SITE_DOMAIN}")

# ─────────────────────────── تنظیمات وب‌سرور ───────────────────────────

_COMMON_SECURITY = """
<IfModule mod_headers.c>
  Header set X-Content-Type-Options "nosniff"
  Header set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
  <FilesMatch "\\.woff2$">
    Header set Cache-Control "public, max-age=31536000, immutable"
  </FilesMatch>
</IfModule>

<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript
  AddOutputFilterByType DEFLATE text/plain text/xml application/json image/svg+xml
</IfModule>

<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/html "access plus 0 seconds"
  ExpiresByType font/woff2 "access plus 1 year"
  ExpiresByType text/css "access plus 1 month"
  ExpiresByType application/javascript "access plus 1 month"
  ExpiresByType image/svg+xml "access plus 1 month"
</IfModule>

<IfModule mod_mime.c>
  AddType font/woff2 .woff2
</IfModule>

Options -Indexes
"""

_HTTPS_REDIRECT = """RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
"""

SITE_HTACCESS = f"""# تاکورا — سایت معرفی ({SITE_DOMAIN})

{_HTTPS_REDIRECT}
<IfModule mod_headers.c>
  Header set X-Frame-Options "SAMEORIGIN"
  Header set Content-Security-Policy "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'"
</IfModule>
{_COMMON_SECURITY}
ErrorDocument 404 /index.html
"""

PANEL_HTACCESS = f"""# تاکورا — پنل ({PANEL_DOMAIN})

{_HTTPS_REDIRECT}
<IfModule mod_headers.c>
  # پنل هرگز نباید داخل iframe سایت دیگری باز شود
  Header always set X-Frame-Options "DENY"
  Header set Content-Security-Policy "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
  # خود صفحهٔ پنل کش نشود؛ فونت‌ها با قاعدهٔ بالا یک‌ساله کش می‌شوند
  <FilesMatch "^index\\.html$">
    Header set Cache-Control "no-store"
  </FilesMatch>
</IfModule>
{_COMMON_SECURITY}
# پنل تک‌صفحه‌ای است و مسیریابی با hash انجام می‌شود
ErrorDocument 404 /index.html
"""

SITE_ROBOTS = f"""User-agent: *
Allow: /

Sitemap: https://{SITE_DOMAIN}/sitemap.xml
"""

# پنل محتوای عمومی ندارد؛ ایندکس‌شدنش فقط صفحهٔ ورود را وارد گوگل می‌کند
PANEL_ROBOTS = """User-agent: *
Disallow: /
"""

SITEMAP = f"""<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://{SITE_DOMAIN}/</loc><changefreq>weekly</changefreq><priority>1.0</priority></url>
</urlset>
"""

# ─────────────────────────── راهنماها ───────────────────────────

SITE_GUIDE = f"""راهنمای آپلود سایت معرفی — {SITE_DOMAIN}
════════════════════════════════════════════════

این پوشه فقط سایت معرفی است. پنل بستهٔ جداگانه‌ای دارد
(فایل talkora-panel.zip، راهنمای خودش را دارد).

گام ۰) نیم‌سرور دامنه  ← اول این، چون تا ۲۴ ساعت طول می‌کشد
   در پنل جایی که دامنه را خریده‌اید نیم‌سرورها را بگذارید:
        ns1.ihglobaldns.com
        ns2.ihglobaldns.com
   تا وقتی این کار را نکنید {SITE_DOMAIN} به هیچ‌جا وصل نیست.

گام ۱) وارد پلسک شوید
   https://ir-plesk02.ihglobaldns.com:8443

گام ۲) Websites & Domains ← {SITE_DOMAIN} ← File Manager

گام ۳) وارد پوشهٔ httpdocs شوید
   ⚠ در پلسک اسم پوشه httpdocs است، نه public_html

گام ۴) فایل‌های پیش‌فرض پلسک را پاک کنید
   index.html و صفحهٔ "Web Server's Default Page"

گام ۵) فایل talkora-site.zip را آپلود و استخراج کنید
   Upload ← فایل زیپ ← بعد روی آن کلیک و Extract Files
   بعد از استخراج خود زیپ را پاک کنید.

   باید این‌ها را ببینید:
        httpdocs/index.html
        httpdocs/.htaccess
        httpdocs/robots.txt
        httpdocs/sitemap.xml
        httpdocs/fonts/

   ⚠ .htaccess مخفی است. از منوی Settings گزینهٔ
     "Show hidden files" را روشن کنید.

گام ۶) گواهی SSL رایگان
   Websites & Domains ← SSL/TLS Certificates
   ← "Install a free basic certificate provided by Let's Encrypt"
   ← هم {SITE_DOMAIN} و هم www.{SITE_DOMAIN} را تیک بزنید

گام ۷) هدایت خودکار به HTTPS
   Hosting & DNS ← Hosting Settings
   ← تیک "Permanent SEO-safe 301 redirect from HTTP to HTTPS"

گام ۸) باز کنید:  https://{SITE_DOMAIN}

════════════════════════════════════════════════
دکمه‌های «ورود» در این سایت به {PANEL_URL} می‌روند.
تا وقتی بستهٔ پنل را بالا نیاورید، آن دکمه‌ها به صفحهٔ خطا می‌رسند.

اگر خطای ۵۰۰ گرفتید: پلسک ممکن است روی حالت "nginx only" باشد و
.htaccess را نخواند. اسمش را به .htaccess.bak تغییر دهید؛ سایت بالا
می‌آید، فقط بدون فشرده‌سازی و کش.

قبل از عمومی‌کردن، فایل CUSTOMIZE.md را بخوانید: شمارهٔ تماس، قیمت‌ها
و آمار مشتری هنوز جای‌نگهدارند.
"""

PANEL_GUIDE = f"""راهنمای بالا آوردن پنل — {PANEL_DOMAIN}
════════════════════════════════════════════════════
پنل، برخلاف سایت معرفی، بک‌اند دارد: PHP ۸ و MySQL و اتصال به sms.ir.
همهٔ گام‌ها لازم است؛ اگر گام ۴ (پیکربندی) را رد کنید پنل در حالت
نمایشی بالا می‌آید و ورود واقعی کار نمی‌کند.


گام ۱) ساخت زیردامنه در پلسک
   Websites & Domains ← Add Subdomain
        Subdomain name : panel
        Parent domain  : {SITE_DOMAIN}
        Document root  : پیش‌فرض پلسک را دست نزنید
   ← OK


گام ۲) نسخهٔ PHP
   روی زیردامنهٔ {PANEL_DOMAIN} ← PHP Settings
        PHP version : ۸.۱ یا بالاتر
        اطمینان از فعال بودن افزونه‌های  pdo_mysql  و  curl
   اگر curl فعال نباشد پیامک ارسال نمی‌شود.


گام ۳) ساخت پایگاه داده
   Databases ← Add Database
        Database name : talkora_panel
        User          : talkora_user
        Password      : یک رمز قوی بسازید و همان‌جا کپی کنید
   بعد: همان دیتابیس ← phpMyAdmin ← تب SQL
   محتویات فایل  _نصب-آپلود-نکنید/پایگاه-داده.sql  را آن‌جا Paste و Go کنید.
   باید ۵ جدول ساخته شود:
        app_user   otp_code   session_token   rate_limit   audit_log


گام ۴) پیکربندی  ← مهم‌ترین گام
   در File Manager وارد  httpdocs/api/  شوید.
   فایل  config.sample.php  را به  config.php  تغییر نام دهید
   و این مقدارها را پر کنید:

        'db_name'  => نام کامل دیتابیس که پلسک ساخت
        'db_user'  => نام کامل کاربر که پلسک ساخت
        'db_pass'  => رمزی که در گام ۳ ساختید

        'otp_pepper' => یک رشتهٔ تصادفی ۶۴ کاراکتری
           ⚠ مقدار نمونه را نگذارید. اگر لینوکس یا مک دارید:
                openssl rand -hex 32
           وگرنه از هر تولیدکنندهٔ رمز تصادفی ۶۴ کاراکتری استفاده کنید.
           این رشته را جایی امن نگه دارید؛ اگر عوضش کنید کدهای
           در جریان باطل می‌شوند.

        'smsir_api_key'     => کلید API از پنل sms.ir
        'smsir_template_id' => شناسهٔ قالب وریفای (گام ۵)
        'sms_dry_run'       => false

   امن‌تر، اگر بلدید: config.php را به‌جای api/ در پوشه‌ای بیرون از
   httpdocs بگذارید، با این مسیر و نام:
        private/talkora-config.php     (کنار httpdocs، نه داخلش)
   کد خودش اول آن‌جا را می‌گردد.


گام ۵) قالب پیامک در sms.ir
   وارد پنل sms.ir شوید ← «ارسال وریفای» ← افزودن قالب
   متن قالب، دقیقاً با همین نام پارامتر:

        کد ورود شما به تاکورا: #CODE#
        این کد تا ۲ دقیقه معتبر است.

   بعد از تأیید قالب، شناسهٔ عددی‌اش را در 'smsir_template_id' بگذارید
   و نام پارامتر را در 'smsir_param_name' بنویسید (اینجا: CODE).

   چرا «وریفای» و نه ارسال معمولی: پیامک وریفای از خط خدماتی می‌رود،
   پس به کسی که «لغو تبلیغات» را فعال کرده هم می‌رسد. با خط تبلیغاتی،
   کد ورودِ بخشی از کاربران هرگز نمی‌رسد و شما هم خبردار نمی‌شوید.


گام ۶) آپلود
   محتویات این بسته را در  httpdocs  زیردامنهٔ پنل بریزید:
        httpdocs/index.html
        httpdocs/.htaccess
        httpdocs/robots.txt
        httpdocs/fonts/
        httpdocs/api/

   ⚠ پوشهٔ  _نصب-آپلود-نکنید  را آپلود نکنید. فقط برای گام ۳ است.


گام ۷) گواهی SSL
   SSL/TLS Certificates ← Let's Encrypt ← {PANEL_DOMAIN}
   ⚠ بدون HTTPS کوکی نشست با پرچم Secure ست نمی‌شود و ورود کار نمی‌کند.


گام ۸) آزمایش، به همین ترتیب
   ۱. باز کنید:  https://{PANEL_DOMAIN}/api/health.php
      انتظار:  {{"ok":true, ... ,"database":true,"sms":"configured"}}

      اگر  "ok":false        ← اطلاعات دیتابیس در config.php غلط است
      اگر  "sms":"missing"   ← کلید sms.ir را نگذاشته‌اید
      اگر  "sms":"dry_run"   ← sms_dry_run را false نکرده‌اید

   ۲. باز کنید:  https://{PANEL_DOMAIN}/api/config.php
      انتظار: خطای ۴۰۴ یا ۴۰۳ یا صفحهٔ خالی.
      اگر محتوای فایل را دیدید، فوراً رمز دیتابیس و کلید sms.ir را
      عوض کنید و با پشتیبانی هاست تماس بگیرید.

   ۳. باز کنید:  https://{PANEL_DOMAIN}
      نوار «نسخهٔ نمایشی» نباید دیده شود.
      شمارهٔ خودتان را بزنید؛ پیامک باید برسد.
      اولین کسی که با نام آموزشگاه ثبت‌نام کند نقش «مدیر» می‌گیرد.


════════════════════════════════════════════════════
مشکلات رایج

«ارسال پیامک ممکن نشد»
    کلید API غلط است، یا اعتبار پنل sms.ir تمام شده، یا افزونهٔ curl
    خاموش است. لاگ خطای PHP در پلسک (Logs) علت دقیق را می‌نویسد.

«اتصال به پایگاه داده برقرار نشد»
    نام کاربر و دیتابیس در پلسک معمولاً پیشوند دارند. همان چیزی را
    بنویسید که در فهرست Databases می‌بینید، نه چیزی که تایپ کرده‌اید.

پنل بالا می‌آید ولی نوار «نسخهٔ نمایشی» دارد
    یعنی api/health.php جواب نداده. گام ۸.۱ را انجام دهید.

کد پیامک می‌شود ولی «کد منقضی شده» می‌گیرید
    ساعت سرور خیلی جلو یا عقب است. از پشتیبانی هاست بخواهید ساعت
    سرور را با NTP هماهنگ کند.

خطای ۵۰۰ روی کل پنل
    پلسک روی حالت "nginx only" است و .htaccess خوانده نمی‌شود.
    مهم: کد PHP خودش هم جلوی دسترسی مستقیم به config.php را می‌گیرد،
    پس رمزها لو نمی‌رود — ولی بهتر است در Apache & nginx Settings
    حالت proxy را روشن کنید.

قبل از استفادهٔ واقعی: بندهای S.8 (قواعد کد یک‌بارمصرف) و P.4
(خط خدماتی در برابر تبلیغاتی) در پوشهٔ docs را بخوانید.
"""

# ─────────────────────────── ساخت ───────────────────────────


def copy_tree_filtered(src: pathlib.Path, dst: pathlib.Path, skip: set) -> None:
    dst.mkdir(parents=True, exist_ok=True)
    for f in sorted(src.iterdir()):
        if f.name in skip:
            continue
        if f.is_dir():
            copy_tree_filtered(f, dst / f.name, skip)
        else:
            shutil.copy2(f, dst / f.name)


def check_external(folder: pathlib.Path) -> list:
    bad = []
    for f in folder.rglob("*.html"):
        text = f.read_text(encoding="utf-8")
        for m in re.findall(r'(?:src|href)=["\'](https?://[^"\']+)', text):
            if not m.startswith(ALLOWED_EXTERNAL):
                bad.append(f"{f.relative_to(folder)}: {m}")
        for m in re.findall(r'url\(["\']?(https?://[^"\')]+)', text):
            bad.append(f"{f.relative_to(folder)}: url({m})")
    return bad


def zip_folder(folder: pathlib.Path, out: pathlib.Path) -> None:
    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for f in sorted(folder.rglob("*")):
            if f.is_file():
                z.write(f, f.relative_to(folder).as_posix())


def main() -> int:
    site_src = ROOT / "site" / "index.html"
    app_src = ROOT / "app" / "index.html"
    fonts = ROOT / "site" / "fonts"
    api_src = ROOT / "panel" / "api"

    for p in (site_src, app_src, fonts, api_src):
        if not p.exists():
            print(f"خطا: {p} پیدا نشد", file=sys.stderr)
            return 1

    # config.php واقعی نباید هرگز داخل بسته برود
    if (api_src / "config.php").exists():
        print("خطا: panel/api/config.php وجود دارد و ممکن است رمز واقعی داشته باشد.\n"
              "      قبل از ساخت بسته پاکش کنید؛ فقط config.sample.php منتشر می‌شود.",
              file=sys.stderr)
        return 1

    if DIST.exists():
        shutil.rmtree(DIST)

    # ── بستهٔ سایت ─────────────────────────────────────────────
    site_out = DIST / "site"
    site_out.mkdir(parents=True)

    site_html = site_src.read_text(encoding="utf-8")
    # لینک‌های ورود به زیردامنهٔ پنل می‌روند، نه به یک لنگر خالی
    site_html, n_links = re.subn(r'href="#login"', f'href="{PANEL_URL}"', site_html)
    if n_links == 0:
        print("هشدار: هیچ لینک #login در سایت پیدا نشد؛ دکمهٔ ورود بررسی شود.", file=sys.stderr)
    (site_out / "index.html").write_text(site_html, encoding="utf-8")
    shutil.copytree(fonts, site_out / "fonts")
    (site_out / ".htaccess").write_text(SITE_HTACCESS, encoding="utf-8")
    (site_out / "robots.txt").write_text(SITE_ROBOTS, encoding="utf-8")
    (site_out / "sitemap.xml").write_text(SITEMAP, encoding="utf-8")
    (site_out / "راهنمای-آپلود.txt").write_text(SITE_GUIDE, encoding="utf-8")

    # ── بستهٔ پنل ─────────────────────────────────────────────
    panel_out = DIST / "panel"
    panel_out.mkdir(parents=True)

    # فونت‌ها کنار خود پنل می‌آیند: زیردامنه به فایل‌های دامنهٔ اصلی دسترسی ندارد
    app_html = app_src.read_text(encoding="utf-8").replace("../site/fonts/", "fonts/")
    (panel_out / "index.html").write_text(app_html, encoding="utf-8")
    shutil.copytree(fonts, panel_out / "fonts")
    # schema فقط در phpMyAdmin کپی می‌شود و هرگز روی سرور لازم نیست.
    # جدا نگهش می‌داریم تا اگر پلسک روی حالت nginx-only بود و .htaccess را
    # نخواند، ساختار جدول‌ها از روی وب قابل خواندن نباشد.
    copy_tree_filtered(api_src, panel_out / "api", skip={"config.php", "schema.mysql.sql"})
    setup = panel_out / "_نصب-آپلود-نکنید"
    setup.mkdir()
    shutil.copy2(api_src / "schema.mysql.sql", setup / "پایگاه-داده.sql")
    (setup / "بخوانید.txt").write_text(
        "محتویات پایگاه-داده.sql را در phpMyAdmin پلسک (تب SQL) بچسبانید و Go بزنید.\n"
        "خودِ این پوشه را روی هاست آپلود نکنید؛ روی سرور به آن نیازی نیست.\n",
        encoding="utf-8")
    (panel_out / ".htaccess").write_text(PANEL_HTACCESS, encoding="utf-8")
    (panel_out / "robots.txt").write_text(PANEL_ROBOTS, encoding="utf-8")
    (panel_out / "راهنمای-پنل.txt").write_text(PANEL_GUIDE, encoding="utf-8")

    # ── بررسی‌ها ───────────────────────────────────────────────
    bad = check_external(site_out) + check_external(panel_out)
    if bad:
        print("خطا: ارجاع به دامنهٔ خارجی:", file=sys.stderr)
        for b in bad:
            print("   ", b, file=sys.stderr)
        return 1

    if (panel_out / "api" / "config.php").exists():
        print("خطا: config.php داخل بستهٔ پنل رفته است.", file=sys.stderr)
        return 1

    if (panel_out / "api" / "schema.mysql.sql").exists():
        print("خطا: schema داخل پوشهٔ api مانده است.", file=sys.stderr)
        return 1

    # پنل باید بداند بک‌اند کجاست، وگرنه همیشه نمایشی می‌ماند
    if "api/health.php" not in (panel_out / "index.html").read_text(encoding="utf-8"):
        print("خطا: پنل به api/health.php وصل نیست.", file=sys.stderr)
        return 1

    if "../site/fonts/" in (panel_out / "index.html").read_text(encoding="utf-8"):
        print("خطا: پنل هنوز فونت را از دامنهٔ اصلی می‌خواند.", file=sys.stderr)
        return 1

    # ── زیپ‌ها ────────────────────────────────────────────────
    site_zip = ROOT / "talkora-site.zip"
    panel_zip = ROOT / "talkora-panel.zip"
    zip_folder(site_out, site_zip)
    zip_folder(panel_out, panel_zip)

    for label, folder, zf in (("سایت", site_out, site_zip), ("پنل", panel_out, panel_zip)):
        total = sum(f.stat().st_size for f in folder.rglob("*") if f.is_file())
        print(f"\n── بستهٔ {label}  →  {folder.relative_to(ROOT)}")
        for f in sorted(folder.rglob("*")):
            if f.is_file():
                print(f"   {f.relative_to(folder)}  ({f.stat().st_size / 1024:.0f} کیلوبایت)")
        print(f"   مجموع {total / 1024:.0f} کیلوبایت  →  {zf.name} ({zf.stat().st_size / 1024:.0f} کیلوبایت)")

    print(f"\nبستهٔ سایت را در httpdocs دامنهٔ {SITE_DOMAIN} بریزید.")
    print(f"بستهٔ پنل را در httpdocs زیردامنهٔ {PANEL_DOMAIN} بریزید (راهنمای-پنل.txt را بخوانید).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
