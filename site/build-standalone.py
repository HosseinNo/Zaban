#!/usr/bin/env python3
"""
از site/index.html یک فایل تک‌تکه می‌سازد که فونت‌ها به‌صورت data-URI داخلش‌اند.

خروجی: build/talkora-preview.html
کاربرد: پیش‌نمایش، ارسال برای بازبینی، یا هر جایی که فقط یک فایل می‌شود گذاشت.

نسخهٔ اصلی (site/index.html) فونت‌ها را از fonts/ می‌خواند و همان نسخه‌ای است
که باید روی هاست واقعی برود — فونتِ جدا کش می‌شود و صفحه سبک‌تر است.

اجرا:  python3 site/build-standalone.py
"""
import base64
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent
SRC = ROOT / "index.html"
OUT_DIR = ROOT.parent / "build"
OUT = OUT_DIR / "talkora-preview.html"

FONTS = {
    "fonts/dana-light.woff2": ROOT / "fonts" / "dana-light.woff2",
    "fonts/dana-regular.woff2": ROOT / "fonts" / "dana-regular.woff2",
    "fonts/dana-bold.woff2": ROOT / "fonts" / "dana-bold.woff2",
}


def main() -> int:
    if not SRC.exists():
        print(f"خطا: {SRC} پیدا نشد", file=sys.stderr)
        return 1

    html = SRC.read_text(encoding="utf-8")

    for ref, path in FONTS.items():
        if not path.exists():
            print(f"خطا: فونت {path} پیدا نشد", file=sys.stderr)
            return 1
        b64 = base64.b64encode(path.read_bytes()).decode("ascii")
        html = html.replace(f'url("{ref}")', f'url("data:font/woff2;base64,{b64}")')

    if "fonts/dana-" in html:
        print("هشدار: بعضی ارجاع‌های فونت جایگزین نشدند", file=sys.stderr)

    # هر ارجاع باقی‌مانده به دامنهٔ خارجی یک خطاست — قاعدهٔ P.7
    external = re.findall(r'(?:src|href)="(https?://[^"]+)"', html)
    if external:
        print("خطا: ارجاع به دامنهٔ خارجی پیدا شد:", external, file=sys.stderr)
        return 1

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    OUT.write_text(html, encoding="utf-8")
    print(f"ساخته شد: {OUT}  ({OUT.stat().st_size / 1024:.0f} کیلوبایت)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
