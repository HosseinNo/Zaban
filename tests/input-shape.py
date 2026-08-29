#!/usr/bin/env python3
"""
ورودیِ بدشکل نباید پاسخ را از JSON خارج کند.

کلاینت می‌تواند به‌جای رشته، آرایه یا شیء بفرستد — از روی اشتباه یا
عمداً. تبدیل آرایه به رشته در PHP یک Warning چاپ می‌کند، و آن Warning
*پیش از* بدنهٔ JSON روی خروجی می‌نشیند:

    <br /><b>Warning</b>: Array to string conversion in
    _platform_ctx.php on line 37<br />{"ok":true,...}

پاسخ دیگر JSON معتبر نیست. پنل روی r.json() می‌شکند و هیچ پیامی هم در
رابط کاربری نمی‌آید — دکمه فقط کار نمی‌کند. یک بار همین دسته باگ در
notify.php افتاد و بار دوم در فهرست کاربران سوپرادمین پیدا شد؛ کافی
بود کسی {"q":["a"]} بفرستد.

این آزمون هر دو بسته را می‌سنجد، چون کمکی‌های ورودی در هر دو تکرار
شده‌اند (superadmin جدا منتشر می‌شود و panel/api/ را نمی‌بیند).

نکتهٔ مهم: آزمون به *وضعیت* پاسخ کار ندارد. ۴۰۰ گرفتن کاملاً درست
است — چیزی که سنجیده می‌شود این است که پاسخ JSON بماند و خروجی هیچ
هشدار PHP نداشته باشد.

پیش‌نیاز:
  php -S 127.0.0.1:8099 -t panel
  php -S 127.0.0.1:8101 -t superadmin
  TALKORA_TEST_DSN_DB

اجرا:  python tests/input-shape.py
"""
import http.cookiejar
import json
import os
import subprocess
import sys
import urllib.error
import urllib.request

PANEL = os.environ.get("TALKORA_TEST_URL", "http://127.0.0.1:8099/api")
SUPER = os.environ.get("TALKORA_SUPER_URL", "http://127.0.0.1:8101/api")
DSN = os.environ.get("TALKORA_TEST_DSN_DB",
                     "mysql:host=127.0.0.1;port=3399;dbname=talkora_test;charset=utf8mb4")
PHP = os.environ.get("PHP_BIN", "php")
_pass = 0
_fail = 0

# شکل‌هایی که کلاینت نباید بفرستد ولی می‌تواند
BAD_SHAPES = [["a", "b"], {"x": 1}, True, None, 12345]


def ok(w):
    global _pass
    _pass += 1
    print(f"  ✓ {w}")


def bad(w, d=""):
    global _fail
    _fail += 1
    print(f"  ✗ {w}")
    if d:
        print(f"      {d[:220]}")


def check(c, w, d=""):
    ok(w) if c else bad(w, d)


def php_out(code, **env):
    r = subprocess.run([PHP, "-r", code], capture_output=True, text=True,
                       env=dict(os.environ, DSN=DSN, **env))
    return r.stdout.strip() if r.returncode == 0 else ""


def sql_one(q):
    return php_out('$p=new PDO(getenv("DSN"),"root","");'
                   'echo (string)$p->query(getenv("Q"))->fetchColumn();', Q=q)


def session_for(uid, iid, role_key):
    return php_out(
        '$p=new PDO(getenv("DSN"),"root","");$t=bin2hex(random_bytes(32));'
        '$r=$p->prepare("SELECT id FROM role WHERE role_key=? AND is_system=1 AND institute_id=\'\'");'
        '$r->execute([getenv("RK")]);$rid=$r->fetchColumn();'
        '$p->prepare("INSERT INTO session_token (token_hash,user_id,expires_at,ip,user_agent,'
        'created_at,active_institute_id,active_role_id,context_set_at) VALUES (?,?,?,?,?,?,?,?,?)")'
        '->execute([hash("sha256",$t),getenv("U"),gmdate("Y-m-d H:i:s",time()+86400),'
        '"127.0.0.1","test",gmdate("Y-m-d H:i:s"),getenv("I"),$rid,gmdate("Y-m-d H:i:s")]);'
        'echo $t;', U=uid, I=iid, RK=role_key)


def raw(url, body, cookie=None, opener=None):
    """بدنهٔ خام برمی‌گرداند، نه JSON — چون دقیقاً همین را می‌سنجیم."""
    data = json.dumps(body, ensure_ascii=False).encode("utf-8")
    h = {"Content-Type": "application/json"}
    if cookie:
        h["Cookie"] = cookie
    req = urllib.request.Request(url, data=data, headers=h, method="POST")
    o = opener or urllib.request.build_opener()
    try:
        with o.open(req, timeout=20) as r:
            return r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.read().decode("utf-8", "replace")


def clean(body):
    """پاسخ سالم: با { شروع شود و هیچ هشدار PHP نداشته باشد."""
    t = body.lstrip()
    dirty = ("Warning" in body or "Notice" in body or "Deprecated" in body
             or "<br" in body or "<b>" in body)
    return t.startswith("{") and not dirty


print("\n═══ ۱. پنل سوپرادمین ═══")
jar = http.cookiejar.CookieJar()
sa = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
u = os.environ.get("TALKORA_SUPER_USER", "owner")
pw = os.environ.get("TALKORA_SUPER_PASS", "admin12345")
r = raw(f"{SUPER}/super.php", {"action": "login", "username": u, "password": pw}, opener=sa)
if '"ok":true' not in r.replace(" ", ""):
    print("  ✗ ورود سوپرادمین نشد — سرور ۸۱۰۱ بالاست؟")
    sys.exit(1)
ok("وارد شد")

SUPER_FIELDS = ["q", "status", "role", "instituteId", "limit", "page"]
for f in SUPER_FIELDS:
    dirty = []
    for shape in BAD_SHAPES:
        body = raw(f"{SUPER}/super.php", {"action": "users.list", f: shape}, opener=sa)
        if not clean(body):
            dirty.append((shape, body[:90]))
    check(not dirty, f"users.list با «{f}» بدشکل، JSON سالم می‌ماند",
          str(dirty[:1]))

# چند اکشن دیگر که رشته می‌گیرند
for act, field in (("institutes.list", "q"), ("users.get", "id"), ("leads", "status")):
    dirty = []
    for shape in BAD_SHAPES:
        body = raw(f"{SUPER}/super.php", {"action": act, field: shape}, opener=sa)
        if not clean(body):
            dirty.append((shape, body[:90]))
    check(not dirty, f"{act} با «{field}» بدشکل", str(dirty[:1]))

print("\n═══ ۲. پنل آموزشگاه ═══")
mgr = sql_one("SELECT user_id FROM membership WHERE role='manager' AND status='active' LIMIT 1")
inst = sql_one("SELECT institute_id FROM membership WHERE role='manager' "
               "AND status='active' LIMIT 1")
if len(mgr) != 32:
    print("  ✗ مدیری در پایگاه آزمون نیست — اول tests/schema.php را اجرا کنید")
    sys.exit(1)
tok = "tk_session=" + session_for(mgr, inst, "r_manager")
ok("نشست مدیر ساخته شد")

PANEL_CASES = [
    ("classes.php", "create", "name"),
    ("classes.php", "create", "time"),
    ("classes.php", "create", "startsOn"),
    ("classes.php", "create", "joinUrl"),
    ("classes.php", "create", "dayPattern"),
    ("notify.php", "send", "title"),
    ("notify.php", "send", "audience"),
    ("institute.php", "invite", "phone"),
    ("institute.php", "invite", "fullName"),
    ("assignments.php", "create", "title"),
]
for ep, act, field in PANEL_CASES:
    dirty = []
    for shape in BAD_SHAPES:
        body = raw(f"{PANEL}/{ep}", {"action": act, field: shape}, cookie=tok)
        if not clean(body):
            dirty.append((shape, body[:90]))
    check(not dirty, f"{ep}|{act} با «{field}» بدشکل", str(dirty[:1]))

print("\n═══ ۳. سایت عمومی ═══")
SITE = os.environ.get("TALKORA_SITE_URL", "http://127.0.0.1:8102/api")
for field in ("name", "phone", "email", "institute", "city", "note"):
    dirty = []
    for shape in BAD_SHAPES:
        body = raw(f"{SITE}/public.php", {"action": "demo", field: shape})
        if not clean(body):
            dirty.append((shape, body[:90]))
    check(not dirty, f"فرم دمو با «{field}» بدشکل", str(dirty[:1]))

print("\n" + "─" * 58)
print(f"موفق: {_pass}    ناموفق: {_fail}")
sys.exit(1 if _fail else 0)
