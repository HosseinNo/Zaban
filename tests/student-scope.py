#!/usr/bin/env python3
"""
زبان‌آموز فقط سهم خودش را می‌بیند.

سه نشتی واقعی که این آزمون نگهبانشان است. هر سه یک ریشه داشتند:

    own_class() فقط *دسترسی به کلاس* را می‌سنجد، نه اینکه چند ردیف
    باید برگردد. برای زبان‌آموز با دامنهٔ own تنها می‌پرسد «ثبت‌نام
    فعال داری؟» و بعد کد ادامه می‌دهد و کل فهرست کلاس را می‌دهد.

  ۱) attendance.php|get  → وضعیت حضور و شمار غیبتِ همهٔ هم‌کلاسی‌ها
  ۲) assignments.php|status → وضعیت تحویل و *نمرهٔ* همهٔ هم‌کلاسی‌ها
  ۳) classes.php|roster → نام و تلفن هم‌کلاسی‌ها

در پنل فقط مدرس این‌ها را صدا می‌زد، ولی پنهان‌بودن دکمه امنیت نیست:
هر کسی با یک کوکی معتبر می‌توانست مستقیم درخواست بزند.

و یک نشتی چهارم از جنس دیگر: لینک جلسهٔ آنلاین. قاعده (سند Q) می‌گوید
زبان‌آموز لینک را فقط از ۱۵ دقیقه پیش تا پایان جلسه ببیند، و
sessions.php|join این را درست اجرا می‌کرد — ولی همان لینک خام از
bootstrap.php و sessions.php|list بی‌قید بیرون می‌رفت. یعنی در همان
لحظه‌ای که join می‌گفت «هنوز زود است»، لینک از قبل در مرورگر بود.

آزمون عمداً هر دو سو را می‌سنجد: زبان‌آموز نباید ببیند، و مدرس باید
ببیند. بستنِ یک‌طرفه آسان است؛ چیزی که می‌شکند، بستنِ بیش از حد است.

پیش‌نیاز:
  php -S 127.0.0.1:8099 -t panel
  TALKORA_TEST_DSN_DB

اجرا:  python tests/student-scope.py
"""
import http.cookiejar
import json
import os
import subprocess
import sys
import urllib.error
import urllib.request

BASE = os.environ.get("TALKORA_TEST_URL", "http://127.0.0.1:8099/api")
DSN = os.environ.get("TALKORA_TEST_DSN_DB",
                     "mysql:host=127.0.0.1;port=3399;dbname=talkora_test;charset=utf8mb4")
PHP = os.environ.get("PHP_BIN", "php")
_pass = 0
_fail = 0


def ok(w):
    global _pass
    _pass += 1
    print(f"  ✓ {w}")


def bad(w, d=""):
    global _fail
    _fail += 1
    print(f"  ✗ {w}")
    if d:
        print(f"      {d[:260]}")


def check(c, w, d=""):
    ok(w) if c else bad(w, d)


def php_out(code, **env):
    r = subprocess.run([PHP, "-r", code], capture_output=True, text=True,
                       env=dict(os.environ, DSN=DSN, **env))
    if r.returncode != 0:
        print("      PHP ناموفق:", (r.stderr or r.stdout)[:200])
        return ""
    return r.stdout.strip()


def sql_one(query):
    return php_out('$p=new PDO(getenv("DSN"),"root","");'
                   'echo (string)$p->query(getenv("Q"))->fetchColumn();', Q=query)


def sql_exec(stmt):
    php_out('$p=new PDO(getenv("DSN"),"root","");$p->exec(getenv("S"));', S=stmt)


def session_for(uid, iid, role_key):
    """نشست می‌سازد و توکن خام را برمی‌گرداند."""
    code = ('$p=new PDO(getenv("DSN"),"root","");'
            '$t=bin2hex(random_bytes(32));'
            '$r=$p->prepare("SELECT id FROM role WHERE role_key=? AND is_system=1 AND institute_id=\'\'");'
            '$r->execute([getenv("RK")]); $rid=$r->fetchColumn();'
            '$p->prepare("INSERT INTO session_token (token_hash,user_id,expires_at,ip,user_agent,'
            'created_at,active_institute_id,active_role_id,context_set_at) VALUES (?,?,?,?,?,?,?,?,?)")'
            '->execute([hash("sha256",$t),getenv("U"),gmdate("Y-m-d H:i:s",time()+86400),'
            '"127.0.0.1","test",gmdate("Y-m-d H:i:s"),getenv("I"),$rid,gmdate("Y-m-d H:i:s")]);'
            'echo $t;')
    return php_out(code, U=uid, I=iid, RK=role_key)


def call(token, ep, body):
    jar = http.cookiejar.CookieJar()
    op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    data = json.dumps(body, ensure_ascii=False).encode("utf-8")
    req = urllib.request.Request(f"{BASE}/{ep}", data=data, method="POST",
                                 headers={"Content-Type": "application/json",
                                          "Cookie": "tk_session=" + token})
    try:
        with op.open(req, timeout=20) as r:
            return json.loads(r.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        return json.loads(e.read().decode("utf-8"))


print("\n═══ ۱. پیدا کردن یک کلاس با مدرس، زبان‌آموز و جلسه ═══")
cls = sql_one("SELECT k.id FROM klass k "
              "JOIN enrolment e ON e.class_id=k.id AND e.status='active' "
              "JOIN class_session s ON s.class_id=k.id "
              "WHERE k.teacher_user_id IS NOT NULL LIMIT 1")
check(len(cls) == 32, "کلاس مناسب پیدا شد", cls or "(هیچ)")
if len(cls) != 32:
    print("\nبدون داده نمی‌شود سنجید. اول tests/schema.php و بعد classdates.py را اجرا کنید.")
    sys.exit(1)

inst = sql_one(f"SELECT institute_id FROM klass WHERE id='{cls}'")
teacher = sql_one(f"SELECT teacher_user_id FROM klass WHERE id='{cls}'")
student = sql_one(f"SELECT student_user_id FROM enrolment WHERE class_id='{cls}' "
                  "AND status='active' LIMIT 1")
sess_id = sql_one(f"SELECT id FROM class_session WHERE class_id='{cls}' ORDER BY seq LIMIT 1")
asg = sql_one(f"SELECT id FROM assignment WHERE class_id='{cls}' LIMIT 1")

s_tok = session_for(student, inst, "r_student")
t_tok = session_for(teacher, inst, "r_teacher")
check(len(s_tok) == 64 and len(t_tok) == 64, "نشست زبان‌آموز و مدرس ساخته شد")

print("\n═══ ۲. زبان‌آموز نباید فهرست کل کلاس را ببیند ═══")
r = call(s_tok, "attendance.php", {"action": "get", "id": sess_id})
check(r.get("ok") is not True and r.get("error") == "forbidden",
      "حضور و غیاب کل کلاس بسته است", str(r)[:180])
check("roster" not in r, "هیچ فهرستی همراه خطا نمی‌آید", str(r)[:180])

if asg:
    r = call(s_tok, "assignments.php", {"action": "status", "id": asg})
    check(r.get("ok") is not True and r.get("error") == "forbidden",
          "وضعیت و نمرهٔ کل کلاس بسته است", str(r)[:180])
    check("students" not in r, "هیچ فهرست نمره‌ای همراه خطا نمی‌آید", str(r)[:180])
else:
    print("  (این کلاس تکلیفی ندارد — از این بند گذشتیم)")

r = call(s_tok, "classes.php", {"action": "roster", "classId": cls})
check(r.get("ok") is not True, "فهرست هم‌کلاسی‌ها بسته است", str(r)[:180])
check("roster" not in r, "هیچ نام و تلفنی همراه خطا نمی‌آید", str(r)[:180])

print("\n═══ ۳. ولی مدرس باید همه را ببیند ═══")
r = call(t_tok, "attendance.php", {"action": "get", "id": sess_id})
check(r.get("ok") is True, "مدرس حضور و غیاب را می‌گیرد", str(r)[:180])
check(isinstance(r.get("roster"), list), "و فهرست واقعی همراهش است", str(r)[:150])

r = call(t_tok, "classes.php", {"action": "roster", "classId": cls})
check(r.get("ok") is True, "مدرس فهرست کلاس را می‌گیرد", str(r)[:180])

if asg:
    r = call(t_tok, "assignments.php", {"action": "status", "id": asg})
    check(r.get("ok") is True, "مدرس وضعیت تکلیف را می‌گیرد", str(r)[:180])

print("\n═══ ۴. لینک جلسه بیرون از پنجرهٔ زمانی ═══")
MARK_S = "https://meet.example.test/SESSION-SECRET"
MARK_C = "https://meet.example.test/CLASS-SECRET"
sql_exec(f"UPDATE class_session SET session_date=DATE_ADD(CURDATE(),INTERVAL 3 DAY), "
         f"join_url='{MARK_S}' WHERE id='{sess_id}'")
sql_exec(f"UPDATE klass SET join_url='{MARK_C}' WHERE id='{cls}'")

raw = json.dumps(call(s_tok, "bootstrap.php", {}), ensure_ascii=False)
check(MARK_S not in raw, "لینک جلسهٔ آینده در bootstrap نیست")
check(MARK_C not in raw, "لینک ثابت کلاس هم در bootstrap نیست")

raw = json.dumps(call(s_tok, "sessions.php", {"action": "list", "classId": cls}),
                 ensure_ascii=False)
check("SECRET" not in raw, "و در sessions|list هم نیست")

r = call(s_tok, "sessions.php", {"action": "join", "id": sess_id})
check(r.get("error") == "too_early", "join هم می‌گوید هنوز زود است", str(r)[:180])

print("\n═══ ۵. همان لینک برای مدرس باز است ═══")
raw = json.dumps(call(t_tok, "sessions.php", {"action": "list", "classId": cls}),
                 ensure_ascii=False)
check(MARK_S in raw, "مدرس لینک جلسه را می‌بیند، حتی جلسهٔ آینده را")
raw = json.dumps(call(t_tok, "bootstrap.php", {}), ensure_ascii=False)
check(MARK_C in raw, "و لینک ثابت کلاس را هم در bootstrap دارد")

print("\n═══ ۶. داخل پنجره، زبان‌آموز هم می‌بیند ═══")
# جلسه را به همین حالا بیاور — هر دو ستون به وقت UTC.
#
# CURDATE() تاریخ *محلی* سرور را می‌دهد و UTC_TIME() ساعت UTC را.
# روی سروری با ساعت تهران (+۳:۳۰) این دو با هم نمی‌خوانند و جلسه
# ۲۴ ساعت جلو می‌افتد — که خودِ همین آزمون را دروغ‌گو می‌کرد.
sql_exec(f"UPDATE class_session SET session_date=UTC_DATE(), "
         f"start_time=DATE_FORMAT(UTC_TIME(),'%H:%i') WHERE id='{sess_id}'")
raw = json.dumps(call(s_tok, "bootstrap.php", {}), ensure_ascii=False)
check(MARK_S in raw, "در پنجرهٔ زمانی، لینک به زبان‌آموز می‌رسد",
      "اگر اینجا شکست، قاعده بیش از حد بسته شده")

print("\n" + "─" * 58)
print(f"موفق: {_pass}    ناموفق: {_fail}")
sys.exit(1 if _fail else 0)
