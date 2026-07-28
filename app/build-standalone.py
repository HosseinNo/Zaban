#!/usr/bin/env python3
"""
از app/index.html یک فایل تک‌تکه با فونت‌های جاسازی‌شده می‌سازد.

خروجی: build/lingotalk-app-preview.html

نسخهٔ اصلی (app/index.html) فونت را از site/fonts/ می‌خواند و همان است
که باید روی هاست برود؛ این خروجی فقط برای پیش‌نمایش و بازبینی است.

اجرا:  python3 app/build-standalone.py
"""
import base64
import pathlib
import re
import sys

APP = pathlib.Path(__file__).resolve().parent
ROOT = APP.parent
SRC = APP / "index.html"
OUT = ROOT / "build" / "lingotalk-app-preview.html"

FONTS = {
    "../site/fonts/dana-light.woff2": ROOT / "site" / "fonts" / "dana-light.woff2",
    "../site/fonts/dana-regular.woff2": ROOT / "site" / "fonts" / "dana-regular.woff2",
    "../site/fonts/dana-bold.woff2": ROOT / "site" / "fonts" / "dana-bold.woff2",
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

    if "site/fonts/dana-" in html:
        print("هشدار: بعضی ارجاع‌های فونت جایگزین نشدند", file=sys.stderr)

    external = re.findall(r'(?:src|href)="(https?://[^"]+)"', html)
    if external:
        print("خطا: ارجاع به دامنهٔ خارجی پیدا شد:", external, file=sys.stderr)
        return 1

    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(html, encoding="utf-8")
    print(f"ساخته شد: {OUT}  ({OUT.stat().st_size / 1024:.0f} کیلوبایت)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
