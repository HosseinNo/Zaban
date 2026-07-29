#!/usr/bin/env python3
"""
بستهٔ آمادهٔ آپلود می‌سازد: پوشهٔ dist/ که مستقیم در httpdocs (پلسک) یا public_html می‌ریزید.

خروجی:
  dist/index.html      سایت معرفی
  dist/fonts/          فونت دانا
  dist/app/index.html  پنل (نمونهٔ تعاملی)
  dist/.htaccess       فشرده‌سازی، کش، ریدایرکت به HTTPS
  dist/robots.txt

روی هاست اشتراکی (سی‌پنل، دایرکت‌ادمین)، سرور مجازی، یا هر جای دیگری کار می‌کند.
هیچ چیزی از دامنهٔ خارجی بارگذاری نمی‌شود.

اجرا:  python3 build-dist.py
"""
import pathlib
import re
import shutil
import sys

ROOT = pathlib.Path(__file__).resolve().parent
DIST = ROOT / "dist"

HTACCESS = """# تاکورا — تنظیمات آپاچی

# ── هدایت به HTTPS ─────────────────────────────────────────────
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# ── فشرده‌سازی ─────────────────────────────────────────────────
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript
  AddOutputFilterByType DEFLATE text/plain text/xml application/json image/svg+xml
</IfModule>

# ── کش ─────────────────────────────────────────────────────────
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/html "access plus 0 seconds"
  ExpiresByType font/woff2 "access plus 1 year"
  ExpiresByType text/css "access plus 1 month"
  ExpiresByType application/javascript "access plus 1 month"
  ExpiresByType image/svg+xml "access plus 1 month"
</IfModule>

<IfModule mod_headers.c>
  Header set X-Content-Type-Options "nosniff"
  Header set X-Frame-Options "SAMEORIGIN"
  Header set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
  # بند P.7: هیچ منبعی از دامنهٔ خارجی بارگذاری نمی‌شود
  Header set Content-Security-Policy "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'"
  <FilesMatch "\\.woff2$">
    Header set Cache-Control "public, max-age=31536000, immutable"
  </FilesMatch>
</IfModule>

<IfModule mod_mime.c>
  AddType font/woff2 .woff2
</IfModule>

# صفحهٔ خطای ۴۰۴ به خانه برگردد
ErrorDocument 404 /index.html
"""

ROBOTS = """User-agent: *
Allow: /
Disallow: /app/

Sitemap: https://talkora.ir/sitemap.xml
"""

UPLOAD_GUIDE = """راهنمای آپلود روی پلسک — talkora.ir
════════════════════════════════════════════════

گام ۰) نیم‌سرور دامنه  ← اول این، چون ۲۴ ساعت طول می‌کشد
   دامنهٔ talkora.ir هنوز به هیچ‌جا اشاره نمی‌کند.
   در پنل جایی که دامنه را خریده‌اید، نیم‌سرورها را بگذارید:
        ns1.ihglobaldns.com
        ns2.ihglobaldns.com
   (اگر شرکت هاست نیم‌سرور دیگری داده، همان را بگذارید.)

گام ۱) وارد پلسک شوید
   https://ir-plesk02.ihglobaldns.com:8443

گام ۲) Websites & Domains  →  talkora.ir  →  File Manager

گام ۳) وارد پوشهٔ httpdocs شوید
   ⚠ در پلسک اسم پوشه httpdocs است، نه public_html

گام ۴) فایل‌های پیش‌فرض پلسک را پاک کنید
   index.html و صفحهٔ "Web Server's Default Page"

گام ۵) همین فایل زیپ را آپلود و استخراج کنید
   دکمهٔ Upload  →  فایل زیپ  →  بعد روی آن کلیک و Extract Files
   بعد از استخراج، خود فایل زیپ را پاک کنید.

   باید این‌ها را ببینید:
        httpdocs/index.html
        httpdocs/.htaccess
        httpdocs/robots.txt
        httpdocs/fonts/
        httpdocs/app/

   ⚠ .htaccess مخفی است. از منوی Settings گزینهٔ
     "Show hidden files" را روشن کنید.

گام ۶) گواهی SSL رایگان
   Websites & Domains → SSL/TLS Certificates
   → "Install a free basic certificate provided by Let's Encrypt"
   → هم talkora.ir و هم www.talkora.ir را تیک بزنید

گام ۷) هدایت خودکار به HTTPS
   Websites & Domains → Hosting & DNS → Hosting Settings
   → تیک "Permanent SEO-safe 301 redirect from HTTP to HTTPS"

گام ۸) باز کنید
        https://talkora.ir       ← سایت معرفی
        https://talkora.ir/app/  ← پنل نمونه

════════════════════════════════════════════════
اگر خطای ۵۰۰ گرفتید:
  پلسک ممکن است روی حالت "nginx only" باشد و .htaccess را نخواند.
  اسم .htaccess را به .htaccess.bak تغییر دهید؛ سایت بالا می‌آید،
  فقط بدون فشرده‌سازی و کش.

مهم: این نسخه فقط سایت و نمونهٔ پنل است. بک‌اند (ثبت‌نام واقعی،
پایگاه داده، پرداخت) روی هاست اشتراکی اجرا نمی‌شود و سرور مجازی
می‌خواهد. توضیح کامل در docs/T-going-live.md
"""


def main() -> int:
    site = ROOT / "site" / "index.html"
    app = ROOT / "app" / "index.html"
    fonts = ROOT / "site" / "fonts"

    for p in (site, app, fonts):
        if not p.exists():
            print(f"خطا: {p} پیدا نشد", file=sys.stderr)
            return 1

    if DIST.exists():
        shutil.rmtree(DIST)
    (DIST / "app").mkdir(parents=True)

    # سایت: مسیر فونت همان fonts/ می‌ماند
    (DIST / "index.html").write_text(site.read_text(encoding="utf-8"), encoding="utf-8")

    # پنل: مسیر فونت از ../site/fonts/ به ../fonts/ تغییر می‌کند
    app_html = app.read_text(encoding="utf-8").replace("../site/fonts/", "../fonts/")
    (DIST / "app" / "index.html").write_text(app_html, encoding="utf-8")

    shutil.copytree(fonts, DIST / "fonts")

    (DIST / ".htaccess").write_text(HTACCESS, encoding="utf-8")
    (DIST / "robots.txt").write_text(ROBOTS, encoding="utf-8")
    (DIST / "راهنمای-آپلود.txt").write_text(UPLOAD_GUIDE, encoding="utf-8")

    # هیچ ارجاعی به دامنهٔ خارجی نباید بماند — قاعدهٔ P.7
    bad = []
    for f in DIST.rglob("*.html"):
        for m in re.findall(r'(?:src|href)="(https?://[^"]+)"', f.read_text(encoding="utf-8")):
            bad.append(f"{f.name}: {m}")
    if bad:
        print("خطا: ارجاع به دامنهٔ خارجی:", bad, file=sys.stderr)
        return 1

    total = sum(f.stat().st_size for f in DIST.rglob("*") if f.is_file())
    print(f"ساخته شد: {DIST}")
    for f in sorted(DIST.rglob("*")):
        if f.is_file():
            print(f"  {f.relative_to(DIST)}  ({f.stat().st_size / 1024:.0f} کیلوبایت)")
    print(f"\nمجموع: {total / 1024:.0f} کیلوبایت")
    print("محتویات این پوشه را در httpdocs (پلسک) یا public_html آپلود کنید.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
