#!/usr/bin/env python3
"""
پنجرهٔ ورود به کلاس آنلاین باید به وقت تهران باشد، نه گرینویچ.

باگی که این آزمون نگهبانش است:

  start_time همان «۱۸:۰۰» است که مدیر در فرم کلاس تایپ می‌کند — ساعت
  دیواری آموزشگاه. ولی sessions.php و bootstrap.php آن را با پسوند
  ' UTC' می‌خواندند. ایران سه ساعت و نیم جلوتر است، پس لینک کلاس
  ۱۸:۰۰ برای زبان‌آموز ساعت ۲۱:۱۵ تهران باز می‌شد، وقتی کلاس نود
  دقیقه‌ای تمام شده بود. مدرس و مدیر لینک را همیشه می‌بینند، پس کلاس
  برگزار می‌شد و فقط زبان‌آموزها «هنوز زود است» می‌گرفتند.

  هیچ آزمون دیگری این را نمی‌گرفت چون همه ساعت جلسه را با gmdate()
  می‌ساختند — یعنی همان فرض غلطِ کد را تکرار می‌کردند.

پیش‌نیاز: سرور پنل و دسترسی به همان دیتابیس.
  TALKORA_TEST_URL      (پیش‌فرض http://127.0.0.1:8099/api)
  TALKORA_TEST_DSN_DB

اجرا:  python tests/session-window-tz.py
"""
import json
import os
import subprocess
import sys
import urllib.error
import urllib.request

try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

BASE = os.environ.get("TALKORA_TEST_URL", "http://127.0.0.1:8099/api")
DSN = os.environ.get("TALKORA_TEST_DSN_DB",
                     "mysql:host=127.0.0.1;port=3399;dbname=talkora_test;charset=utf8mb4")
PHP = os.environ.get("PHP_BIN", "php")
_pass = 0
_fail = 0


def check(cond, what, detail=""):
    global _pass, _fail
    if cond:
        _pass += 1
        print(f"  ✓ {what}")
    else:
        _fail += 1
        print(f"  ✗ {what}")
        if detail:
            print(f"      {str(detail)[:220]}")


def php(code, **env):
    r = subprocess.run([PHP, "-r", code], capture_output=True, text=True, encoding="utf-8",
                       env=dict(os.environ, DSN=DSN, **env))
    if r.returncode != 0:
        print("!! php:", r.stderr[:300])
        sys.exit(2)
    return r.stdout.strip()


def post(token, ep, body):
    data = json.dumps(body, ensure_ascii=False).encode("utf-8")
    req = urllib.request.Request(f"{BASE}/{ep}", data=data, method="POST",
                                 headers={"Content-Type": "application/json",
                                          "Cookie": f"tk_session={token}"})
    try:
        with urllib.request.urlopen(req, timeout=20) as r:
            return r.status, json.loads(r.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read().decode("utf-8"))


# ── داده: یک آموزشگاه، یک کلاس آنلاین، یک زبان‌آموزِ ثبت‌نام‌شده، یک جلسه ──
ids = php(r'''
$p=new PDO(getenv("DSN"),"root","",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$h=function(){return bin2hex(random_bytes(16));};
$now=gmdate("Y-m-d H:i:s");
$inst=$h(); $stu=$h(); $cls=$h(); $ses=$h();
$p->prepare("INSERT INTO app_user (id,full_name,phone,status,created_at) VALUES (?,?,?,'active',?)")
  ->execute([$stu,"زبان‌آموز ساعت","0912".substr((string)random_int(1000000,9999999),0,7),$now]);
$p->prepare("INSERT INTO institute (id,name,owner_user_id,status,created_at) VALUES (?,?,?,'active',?)")->execute([$inst,"آزمون منطقهٔ زمانی",$stu,$now]);
$rid=$p->query("SELECT id FROM role WHERE role_key='student' AND is_system=1 AND institute_id=''")->fetchColumn();
$p->prepare("INSERT INTO membership (id,institute_id,user_id,role,role_id,status,can_host_meeting,created_at) VALUES (?,?,?,'student',?,'active',0,?)")
  ->execute([$h(),$inst,$stu,$rid,$now]);
$p->prepare("INSERT INTO klass (id,institute_id,name,day_pattern,start_time,duration_min,capacity,total_sessions,mode,provider,join_url,price,status,created_at)
             VALUES (?,?,'کلاس ساعت','فرد','18:00',90,10,8,'online','custom','https://example.test/room',0,'published',?)")
  ->execute([$cls,$inst,$now]);
$p->prepare("INSERT INTO enrolment (id,institute_id,class_id,student_user_id,status,created_at) VALUES (?,?,?,?,'active',?)")
  ->execute([$h(),$inst,$cls,$stu,$now]);
$p->prepare("INSERT INTO class_session (id,institute_id,class_id,seq,session_date,start_time,status,join_url) VALUES (?,?,?,1,'2000-01-01','00:00','scheduled','https://example.test/room')")
  ->execute([$ses,$inst,$cls]);
$t=bin2hex(random_bytes(32));
$p->prepare("INSERT INTO session_token (token_hash,user_id,expires_at,ip,user_agent,created_at,active_institute_id,active_role_id,context_set_at) VALUES (?,?,?,?,?,?,?,?,?)")
  ->execute([hash("sha256",$t),$stu,gmdate("Y-m-d H:i:s",time()+3600),"127.0.0.1","tz-test",$now,$inst,$rid,$now]);
echo json_encode(["ses"=>$ses,"tok"=>$t]);
''')
ids = json.loads(ids)
SES, TOK = ids["ses"], ids["tok"]


def place(offset_minutes):
    """جلسه را offset دقیقه نسبت به «الانِ تهران» می‌گذارد."""
    php(r'''
$p=new PDO(getenv("DSN"),"root","");
$t=new DateTime("now",new DateTimeZone("Asia/Tehran"));
$t->modify(getenv("OFF")." minutes");
$p->prepare("UPDATE class_session SET session_date=?, start_time=? WHERE id=?")
  ->execute([$t->format("Y-m-d"),$t->format("H:i"),getenv("SES")]);
''', OFF=f"{offset_minutes:+d}", SES=SES)


print("\n═══ ورود زبان‌آموز، با ساعتی که مدیر به وقت تهران وارد کرده ═══")

place(+5)
c, b = post(TOK, "sessions.php", {"action": "join", "id": SES})
check(c == 200 and b.get("joinUrl"), "۵ دقیقه پیش از شروع (تهران): لینک می‌دهد", f"{c} {b}")

place(-30)
c, b = post(TOK, "sessions.php", {"action": "join", "id": SES})
check(c == 200 and b.get("joinUrl"), "۳۰ دقیقه بعد از شروع، وسط کلاس: لینک می‌دهد", f"{c} {b}")

place(+60)
c, b = post(TOK, "sessions.php", {"action": "join", "id": SES})
check(c == 409 and b.get("error") == "too_early", "یک ساعت مانده: هنوز زود است", f"{c} {b}")

place(-300)
c, b = post(TOK, "sessions.php", {"action": "join", "id": SES})
check(c == 409 and b.get("error") == "too_late", "پنج ساعت گذشته: تمام شده", f"{c} {b}")

print("\n═══ همان قاعده در bootstrap.php ═══")

place(+5)
c, b = post(TOK, "bootstrap.php", {})
s = next((x for x in b.get("sessions", []) if x["id"] == SES), {})
check(bool(s.get("joinUrl")), "۵ دقیقه پیش از شروع: لینک در bootstrap هست", s)

place(+60)
c, b = post(TOK, "bootstrap.php", {})
s = next((x for x in b.get("sessions", []) if x["id"] == SES), {})
check(s.get("joinUrl") is None, "یک ساعت مانده: لینک در bootstrap پنهان است", s)

print("\n" + "─" * 58)
print(f"موفق: {_pass}    ناموفق: {_fail}")
sys.exit(1 if _fail else 0)
