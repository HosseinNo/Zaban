#!/usr/bin/env python3
"""
چرخهٔ کامل یک آموزشگاه، از ثبت‌نام تا نمره — با همان payloadهایی که
app/index.html می‌فرستد، نه با حدس.

آزمون‌های دیگر هرکدام یک نقطهٔ پایانی را می‌سنجند. این یکی زنجیره را
می‌سنجد: مدیر ثبت‌نام می‌کند ← ترم و اتاق ← دعوت مدرس ← کد پیوستن ←
درخواست پیوستن و تأیید ← کلاس آنلاین و ظرفیت ← انتشار ← دسترسی جلسه ←
حضور و غیاب ← تکلیف و نمره ← اعلان ← انصراف و حذف عضو ← خروج.

دو باگ واقعی که اول بار همین اسکریپت پیدا کرد و حالا نگهبانشان است:

  ۱) دعوت، ظرفیت کلاس را دور می‌زد. دعوتِ شمارهٔ تازه هیچ بررسی
     ظرفیتی نداشت و otp-verify هنگام پذیرش، ثبت‌نام را بی‌شرط می‌نوشت.
     کلاسی با ظرفیت ۲ سه زبان‌آموز فعال گرفت.

  ۲) نمرهٔ اعشاری با کیبورد فارسی ثبت نمی‌شد. «۱۷٫۵» — ممیز U+066B —
     نه در enDigits رابط کاربری تبدیل می‌شد نه در en_digits سرور، و
     مدرس پیام «نمره را وارد کنید» می‌گرفت.

پیش‌نیاز:
  سرور پنل (TALKORA_TEST_URL، پیش‌فرض http://127.0.0.1:8099/api)
  حالت پیامک «پل» (پیش‌فرض نصب تازه) — کد ورود از otp_code خوانده می‌شود
  TALKORA_TEST_DSN_DB

اجرا:  python tests/lifecycle.py
"""
import http.cookiejar, json, os, random, subprocess, sys, urllib.error, urllib.request, uuid
sys.stdout.reconfigure(encoding="utf-8", errors="replace")

BASE = os.environ.get("TALKORA_TEST_URL", "http://127.0.0.1:8099/api")
DSN = os.environ.get("TALKORA_TEST_DSN_DB", "mysql:host=127.0.0.1;port=3399;dbname=talkora_test;charset=utf8mb4")
P = {"pass": 0, "fail": 0}
BUGS = []


def check(cond, what, detail=""):
    if cond:
        P["pass"] += 1; print(f"  ✓ {what}")
    else:
        P["fail"] += 1; print(f"  ✗ {what}"); BUGS.append(what)
        if detail: print(f"      {str(detail)[:240]}")


def sql(q, *args):
    code = ('$p=new PDO(getenv("DSN"),"root","",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);'
            '$a=json_decode(getenv("A"),true);$s=$p->prepare(getenv("Q"));$s->execute($a);'
            'echo json_encode(stripos(ltrim(getenv("Q")),"SELECT")===0?$s->fetchAll(PDO::FETCH_ASSOC):["n"=>$s->rowCount()]);')
    r = subprocess.run(["php", "-r", code], capture_output=True, text=True, encoding="utf-8",
                       env=dict(os.environ, DSN=DSN, Q=q, A=json.dumps(list(args))))
    if r.returncode: print("!! sql", r.stderr[:300]); sys.exit(2)
    return json.loads(r.stdout)


class Actor:
    def __init__(self, name):
        self.name = name
        self.jar = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))

    def call(self, ep, body=None, multipart=None):
        if multipart is not None:
            b = uuid.uuid4().hex
            parts = []
            for k, v in multipart.items():
                parts.append(f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n')
            data = ("".join(parts) + f"--{b}--\r\n").encode("utf-8")
            h = {"Content-Type": f"multipart/form-data; boundary={b}"}
        else:
            data = json.dumps(body or {}, ensure_ascii=False).encode("utf-8")
            h = {"Content-Type": "application/json"}
        req = urllib.request.Request(f"{BASE}/{ep}", data=data, headers=h, method="POST")
        try:
            with self.op.open(req, timeout=30) as r:
                txt = r.read().decode("utf-8", "replace"); code = r.status
        except urllib.error.HTTPError as e:
            txt = e.read().decode("utf-8", "replace"); code = e.code
        try:
            return code, json.loads(txt)
        except Exception:
            return code, {"_raw": txt[:300]}

    def login_otp(self, phone, **extra):
        sql("DELETE FROM rate_limit")
        c, r = self.call("otp-request.php", {"phone": phone})
        if c != 200: return c, r
        code = sql("SELECT pending_code FROM otp_code WHERE phone=? AND consumed_at IS NULL ORDER BY created_at DESC LIMIT 1", phone)
        body = {"phone": phone, "code": code[0]["pending_code"] if code else "00000"}
        body.update(extra)
        return self.call("otp-verify.php", body)


def ph():
    return "0912" + str(random.randint(1000000, 9999999))


def boot(a):
    return a.call("bootstrap.php", {})


print("\n═══ ۱. ثبت‌نام آموزشگاه تازه با پیامک ═══")
M = Actor("مدیر"); mph = ph()
c, r = M.login_otp(mph, fullName="مدیر چرخه", instituteName="آموزشگاه چرخهٔ کامل")
check(c == 200 and r.get("ok"), "مدیر ثبت‌نام کرد و وارد شد", r)
c, b = boot(M)
check(c == 200 and b.get("role") == "manager", "نقش مدیر است", (c, b.get("role"), b.get("error")))
INST = sql("SELECT m.institute_id FROM membership m JOIN app_user u ON u.id=m.user_id WHERE u.phone=?", mph)
INST = INST[0]["institute_id"] if INST else ""
check(bool(INST), "آموزشگاه در پایگاه ساخته شد")
check((b.get("institute") or {}).get("jitsiEnabled") is not None, "bootstrap وضعیت جیتسی را می‌دهد")

print("\n═══ ۲. تنظیمات، ترم، اتاق ═══")
c, r = M.call("institute.php", {"action": "update", "name": "آموزشگاه چرخه", "city": "مشهد", "phone": "05138000000"})
check(c == 200, "ذخیرهٔ مشخصات آموزشگاه", r)
row = sql("SELECT name, city, phone FROM institute WHERE id=?", INST)[0]
check(row["city"] == "مشهد" and row["name"] == "آموزشگاه چرخه", "مشخصات در پایگاه نشست", row)
c, r = M.call("institute.php", {"action": "setup", "termName": "پاییز", "termStart": "2026-09-20", "weeks": 10,
                                "rooms": [{"name": "کلاس ۱", "cap": 12}, {"name": "کلاس ۲", "cap": 16}]})
check(c == 200 and r.get("rooms") == 2, "ترم و دو اتاق ساخته شد", r)
c, r = M.call("institute.php", {"action": "setup", "termName": "دوم", "termStart": "2026-09-20", "weeks": 10})
check(c == 409, "ترم فعال دوم رد شد", r)
c, r = M.call("institute.php", {"action": "addRoom", "name": "اتاق کوچک", "cap": 2})
ROOM = r.get("id"); check(c == 200 and ROOM, "اتاق سوم ساخته شد", r)

print("\n═══ ۳. دعوت مدرس با شماره، و پذیرفتن با پیامک ═══")
tph = ph()
c, r = M.call("institute.php", {"action": "invite", "fullName": "مدرس چرخه", "phone": tph, "role": "teacher", "classId": ""})
check(c == 200, "دعوت مدرس ثبت شد", r)
T = Actor("مدرس")
c, r = T.login_otp(tph, fullName="مدرس چرخه")
check(c == 200 and r.get("ok"), "مدرس با کد وارد شد", r)
c, b = boot(T)
check(c == 200 and b.get("role") == "teacher", "مدرس عضو آموزشگاه شد", (c, b.get("role"), b.get("error")))
TID = sql("SELECT id FROM app_user WHERE phone=?", tph)[0]["id"]

print("\n═══ ۴. کد پیوستن، ثبت‌نام زبان‌آموز با کد ═══")
c, r = M.call("institute.php", {"action": "joinCodeSet", "role": "student", "active": True, "rotate": True})
check(c == 200, "کد پیوستن ساخته شد", r)
c, r = M.call("institute.php", {"action": "joinCode"})
CODE = r.get("code") or (r.get("joinCode") or {}).get("code")
check(bool(CODE), "کد پیوستن خوانده شد", r)
S1 = Actor("زبان‌آموز۱"); s1ph = ph()
c, r = S1.call("signup.php", {"action": "checkCode", "code": CODE})
check(c == 200 and r.get("ok"), "کد معتبر شناخته شد", r)
c, r = S1.call("signup.php", {"action": "register", "mode": "code", "code": CODE, "phone": s1ph, "password": "Pass!word123",
                               "firstNameFa": "سارا", "lastNameFa": "محمدی", "firstNameEn": "Sara", "lastNameEn": "Mohammadi",
                               "nationalId": "", "gender": "", "email": "", "city": ""})
check(c == 200 and r.get("ok"), "زبان‌آموز با کد ثبت‌نام کرد", r)
c, r = S1.call("login.php", {"username": s1ph, "password": "Pass!word123"})
check(c == 200 and r.get("ok"), "زبان‌آموز با رمز وارد شد", r)
c, b = boot(S1)
check(c == 200 and b.get("role") == "student", "زبان‌آموز عضو شد", (c, b.get("role"), b.get("error")))
S1ID = sql("SELECT id FROM app_user WHERE phone=?", s1ph)[0]["id"]
c, r = S1.call("signup.php", {"action": "checkCode", "code": "ZZZZZZ"})
check(c in (400, 404), "کد ساختگی رد شد", (c, r))

print("\n═══ ۵. درخواست پیوستن و تأیید مدیر ═══")
S2 = Actor("زبان‌آموز۲"); s2ph = ph()
c, r = S2.call("signup.php", {"action": "register", "mode": "request", "instituteId": INST, "role": "student", "phone": s2ph,
                               "password": "Pass!word123", "firstNameFa": "رضا", "lastNameFa": "کریمی", "firstNameEn": "Reza",
                               "lastNameEn": "Karimi", "message": "لطفاً تأیید کنید"})
check(c == 200 and r.get("ok"), "درخواست پیوستن ثبت شد", r)
c, r = M.call("institute.php", {"action": "requests", "status": "pending"})
reqs = r.get("requests") or r.get("items") or []
RID = next((x.get("id") for x in reqs if s2ph[-4:] in json.dumps(x, ensure_ascii=False) or "رضا" in json.dumps(x, ensure_ascii=False)), None)
check(bool(RID), "درخواست در صف مدیر دیده شد", r)
c, r = M.call("institute.php", {"action": "approveRequest", "id": RID, "role": "student"})
check(c == 200, "مدیر تأیید کرد", r)
S2.call("login.php", {"username": s2ph, "password": "Pass!word123"})
c, b = boot(S2)
check(c == 200 and b.get("role") == "student", "زبان‌آموز دوم بعد از تأیید عضو است", (c, b.get("error")))
S2ID = sql("SELECT id FROM app_user WHERE phone=?", s2ph)[0]["id"]
c, r = M.call("institute.php", {"action": "approveRequest", "id": RID, "role": "student"})
check(c == 409, "تأیید دوبارهٔ همان درخواست رد شد", (c, r))

print("\n═══ ۶. کلاس، ثبت‌نام، ظرفیت، انتشار ═══")
cls_body = {"action": "create", "name": "مکالمه ۱", "level": "B1", "teacherId": TID, "roomId": ROOM, "dayPattern": "زوج",
            "time": "17:30", "cap": 2, "totalSessions": 6, "mode": "online", "provider": "jitsi", "joinUrl": "", "price": 12000000,
            "startsOn": "2026-09-20", "endsOn": "", "midtermOn": "", "finalOn": ""}
c, r = M.call("classes.php", cls_body); CL = r.get("id")
check(c == 200 and CL, "کلاس آنلاین ساخته شد", r)
k = sql("SELECT teacher_user_id, room_id, capacity, price, provider, join_url FROM klass WHERE id=?", CL)[0]
check(k["teacher_user_id"] == TID and k["room_id"] == ROOM and int(k["capacity"]) == 2, "مدرس، اتاق و ظرفیت درست نشست", k)
check(k["join_url"] and "talkora-" in k["join_url"], "لینک جیتسی خودکار ساخته شد", k)
for sid, who in ((S1ID, "اول"), (S2ID, "دوم")):
    c, r = M.call("classes.php", {"action": "enrol", "classId": CL, "studentId": sid})
    check(c == 200, f"ثبت‌نام زبان‌آموز {who}", r)
c, r = M.call("classes.php", {"action": "enrol", "classId": CL, "studentId": S1ID})
check(c in (200, 409), "ثبت‌نام تکراری خطای ۵۰۰ نمی‌دهد", (c, r))
n = sql("SELECT COUNT(*) n FROM enrolment WHERE class_id=? AND status='active'", CL)[0]["n"]
check(int(n) == 2, "ثبت‌نام تکراری ردیف دوم نساخت", n)
X = Actor("زبان‌آموز۳"); xph = ph()
X.login_otp(xph, fullName="نفر سوم")
sql("INSERT INTO membership (id,institute_id,user_id,role,role_id,status,can_host_meeting,created_at) SELECT ?,?,id,'student',(SELECT id FROM role WHERE role_key='student' AND is_system=1 AND institute_id=''),'active',0,UTC_TIMESTAMP() FROM app_user WHERE phone=?",
    uuid.uuid4().hex, INST, xph)
XID = sql("SELECT id FROM app_user WHERE phone=?", xph)[0]["id"]
c, r = M.call("classes.php", {"action": "enrol", "classId": CL, "studentId": XID})
check(c == 409 and r.get("error") == "class_full", "ظرفیت پر: نفر سوم رد شد", (c, r))
c, r = M.call("institute.php", {"action": "invite", "fullName": "نفر چهارم", "phone": ph(), "role": "student", "classId": CL})
inv_phone = sql("SELECT phone FROM invite WHERE class_id=? ORDER BY created_at DESC LIMIT 1", CL)
if inv_phone:
    Y = Actor("دعوتی"); Y.login_otp(inv_phone[0]["phone"], fullName="نفر چهارم")
    n = sql("SELECT COUNT(*) n FROM enrolment WHERE class_id=? AND status='active'", CL)[0]["n"]
    check(int(n) <= 2, "دعوت با کلاسِ پر، ظرفیت را دور نمی‌زند", f"ثبت‌نام فعال: {n} از ظرفیت ۲")
c, r = M.call("classes.php", {"action": "publish", "id": CL})
check(c == 200 and r.get("sessions") == 6, "انتشار: ۶ جلسه ساخته شد", r)
c, r = M.call("classes.php", {"action": "publish", "id": CL})
n = sql("SELECT COUNT(*) n FROM class_session WHERE class_id=?", CL)[0]["n"]
check(int(n) == 6, "انتشار دوباره جلسهٔ تکراری نساخت", f"{n} جلسه")

print("\n═══ ۷. دسترسی جلسه، شروع، حضور و غیاب ═══")
c, r = M.call("institute.php", {"action": "members"})
mem = next((x for x in (r.get("members") or []) if x.get("userId") == TID or x.get("phone", "")[-3:] == tph[-3:]), None)
check(bool(mem and mem.get("id")), "مدرس در فهرست اعضا هست", r)
MID = mem.get("id") if mem else ""
SES = sql("SELECT id FROM class_session WHERE class_id=? ORDER BY seq LIMIT 1", CL)[0]["id"]
c, r = T.call("sessions.php", {"action": "start", "id": SES})
check(c == 403, "مدرس بدون دسترسی جلسه: ۴۰۳", (c, r))
c, r = M.call("institute.php", {"action": "setMeetingAccess", "id": MID, "on": True})
check(c == 200, "مدیر دسترسی جلسه داد (از فهرست اعضا)", r)
c, r = T.call("sessions.php", {"action": "start", "id": SES})
check(c == 200 and r.get("joinUrl") and r.get("drafted") == 2, "مدرس جلسه را شروع کرد، پیش‌نویس حضور ۲ نفر", r)
c, r = T.call("attendance.php", {"action": "get", "id": SES})
roster = r.get("roster") or []
check(len(roster) == 2 and all(x["status"] == "present" for x in roster), "فهرست حضور: ۲ نفر، پیش‌فرض حاضر", r)
c, r = T.call("attendance.php", {"action": "save", "id": SES, "marks": [{"id": S1ID, "status": "present"}, {"id": S2ID, "status": "absent"}]})
check(c == 200 and r.get("saved") == 2, "ذخیرهٔ حضور", r)
c, r = T.call("sessions.php", {"action": "end", "id": SES})
check(c == 200, "پایان جلسه", r)
c, b = boot(M)
cl = next((x for x in b.get("classes", []) if x["id"] == CL), {})
check(cl.get("attendance") == 50, "درصد حضور کلاس = ۵۰٪", cl.get("attendance"))

print("\n═══ ۸. تکلیف، تحویل با فایل‌نشده، نمره ═══")
c, r = T.call("assignments.php", {"action": "create", "title": "نامه‌نگاری", "classId": CL, "type": "writing",
                                  "desc": "یک نامه بنویسید", "dueDate": "2026-12-01", "max": 20})
check(c == 200, "مدرس تکلیف ساخت", r)
AID = sql("SELECT id FROM assignment WHERE class_id=? ORDER BY created_at DESC LIMIT 1", CL)[0]["id"]
due = sql("SELECT due_at FROM assignment WHERE id=?", AID)[0]["due_at"]
check(bool(due), "مهلت تکلیف ذخیره شد", due)
c, r = S1.call("assignments.php", multipart={"action": "submit", "id": AID, "text": "Dear Sir, ..."})
check(c == 200 and r.get("ok"), "زبان‌آموز پاسخ فرستاد (multipart مثل رابط)", r)
SUB = sql("SELECT id FROM submission WHERE assignment_id=? AND student_user_id=?", AID, S1ID)
SUB = SUB[0]["id"] if SUB else ""
c, r = T.call("assignments.php", {"action": "grade", "id": SUB, "score": "۱۷٫۵", "feedback": "خوب بود"})
check(c == 200, "نمره با رقم و ممیز فارسی «۱۷٫۵» (کیبورد فارسی)", r)
sc = sql("SELECT score FROM submission WHERE id=?", SUB)
check(bool(sc) and sc[0]["score"] is not None and float(sc[0]["score"]) == 17.5, "نمره ۱۷.۵ ذخیره شد", sc)
c, b = boot(S1)
mine = next((x for x in b.get("submissions", []) if x.get("aid") == AID), {})
check(mine.get("score") is not None, "زبان‌آموز نمره‌اش را در bootstrap می‌بیند", mine)
c, r = S2.call("assignments.php", {"action": "status", "id": AID})
check(c == 403, "زبان‌آموز وضعیت همهٔ تحویل‌ها را نمی‌بیند", (c, r))

print("\n═══ ۹. اعلان به کلاس ═══")
c, r = M.call("notify.php", {"action": "send", "audience": "class:" + CL, "title": "تغییر ساعت", "body": "کلاس ساعت ۱۸ است", "kind": "warn"})
check(c == 200 and r.get("recipients") == 3, "اعلان کلاس به ۳ نفر (۲ زبان‌آموز + مدرس)", r)
c, r = S1.call("notify.php", {"action": "inbox", "limit": 30})
items = r.get("items") or []
check(any(x["title"] == "تغییر ساعت" for x in items) and r.get("unread", 0) >= 1, "در صندوق زبان‌آموز رسید", r)
nid = next((x["id"] for x in items if x["title"] == "تغییر ساعت"), None)
S1.call("notify.php", {"action": "read", "ids": [nid]})
c, r = S1.call("notify.php", {"action": "inbox", "limit": 30})
check(not next((x for x in r.get("items", []) if x["id"] == nid), {}).get("read") is False, "خوانده‌شده علامت خورد", r)

print("\n═══ ۱۰. انصراف، حذف عضو، حذف اتاق در استفاده، خروج ═══")
c, r = M.call("classes.php", {"action": "withdraw", "classId": CL, "studentId": S2ID})
check(c == 200, "انصراف زبان‌آموز دوم", r)
c, r = S2.call("attendance.php", {"action": "student"})
c2, b2 = boot(S2)
check(not any(x["id"] == CL for x in b2.get("classes", [])), "کلاس از پنل زبان‌آموز منصرف حذف شد", [x["id"] for x in b2.get("classes", [])])
c, r = M.call("institute.php", {"action": "deleteRoom", "id": ROOM})
check(c == 409, "حذف اتاقی که کلاس دارد رد شد", (c, r))
c, r = M.call("institute.php", {"action": "removeMember", "id": MID})
check(c == 200, "حذف مدرس از آموزشگاه", r)
c, b = boot(T)
check(c in (401, 403), "مدرس حذف‌شده دیگر به پنل دسترسی ندارد", (c, b.get("error")))
c, r = S1.call("logout.php", {})
c, b = boot(S1)
check(c == 401, "بعد از خروج نشست باطل است", (c, b.get("error")))

print("\n" + "─" * 58)
print(f"موفق: {P['pass']}    ناموفق: {P['fail']}")
for x in BUGS: print("   ✗", x)
sys.exit(1 if P["fail"] else 0)
