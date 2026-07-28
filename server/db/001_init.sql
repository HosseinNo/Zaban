-- ═══════════════════════════════════════════════════════════════════
-- لینگوتاک — مهاجرت اولیه
-- مطابق سند I (مدل داده) و Q (کلاس آنلاین)
--
-- راهبرد چندمستأجری: یک اسکیما، جداسازی سطر-به-سطر با RLS.
-- هر جدول مستأجر-محور institute_id دارد و RLS آن را اجبار می‌کند،
-- پس یک کوئری بدون شرط مستأجر، صفر سطر برمی‌گرداند نه دادهٔ آموزشگاه دیگر.
-- ═══════════════════════════════════════════════════════════════════

CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- نقشی که اپ با آن وصل می‌شود؛ عمداً BYPASSRLS ندارد.
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'lingotalk_app') THEN
    CREATE ROLE lingotalk_app LOGIN;
  END IF;
END $$;

-- ── مستأجر ──────────────────────────────────────────────────────────
CREATE TABLE institute (
  id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  slug              text UNIQUE NOT NULL,
  name              text NOT NULL,
  country           text NOT NULL DEFAULT 'IR',
  timezone          text NOT NULL DEFAULT 'Asia/Tehran',
  locale            text NOT NULL DEFAULT 'fa-IR',
  direction         text NOT NULL DEFAULT 'rtl' CHECK (direction IN ('rtl','ltr')),
  calendar_system   text NOT NULL DEFAULT 'jalali'
                    CHECK (calendar_system IN ('jalali','gregorian','hijri')),
  -- پول همیشه در واحد فرعی صحیح؛ نمایش (تومان) یک تنظیم است، نه ثابت کد
  currency          text NOT NULL DEFAULT 'IRR',
  display_unit      text NOT NULL DEFAULT 'toman',
  display_divisor   integer NOT NULL DEFAULT 10,
  status            text NOT NULL DEFAULT 'trial'
                    CHECK (status IN ('trial','active','past_due','suspended','cancelled')),
  settings          jsonb NOT NULL DEFAULT '{}'::jsonb,
  feature_flags     jsonb NOT NULL DEFAULT '{}'::jsonb,
  storage_quota_bytes bigint NOT NULL DEFAULT 53687091200,  -- ۵۰ گیگ
  storage_used_bytes  bigint NOT NULL DEFAULT 0,
  trial_ends_at     timestamptz,
  created_at        timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE branch (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institute_id  uuid NOT NULL REFERENCES institute(id) ON DELETE CASCADE,
  name          text NOT NULL,
  is_default    boolean NOT NULL DEFAULT false,
  timezone      text,
  status        text NOT NULL DEFAULT 'active',
  created_at    timestamptz NOT NULL DEFAULT now()
);

-- ── کاربر سراسری، عضویت به‌ازای مستأجر ──────────────────────────────
-- یک مدرس که در سه آموزشگاه کار می‌کند، یک حساب دارد.
CREATE TABLE app_user (
  id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  phone             text UNIQUE NOT NULL,
  email             text,
  full_name         text NOT NULL,
  password_hash     text,
  phone_verified_at timestamptz,
  mfa_enabled       boolean NOT NULL DEFAULT false,
  status            text NOT NULL DEFAULT 'active',
  last_login_at     timestamptz,
  created_at        timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON app_user (phone);

CREATE TABLE membership (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institute_id  uuid NOT NULL REFERENCES institute(id) ON DELETE CASCADE,
  user_id       uuid NOT NULL REFERENCES app_user(id) ON DELETE CASCADE,
  role          text NOT NULL CHECK (role IN
                ('owner','academic_manager','finance_manager','branch_manager',
                 'front_desk','teacher','student','guardian')),
  branch_ids    uuid[] NOT NULL DEFAULT '{}',
  status        text NOT NULL DEFAULT 'active',
  created_at    timestamptz NOT NULL DEFAULT now(),
  UNIQUE (institute_id, user_id, role)
);

-- ── آموزشی ──────────────────────────────────────────────────────────
CREATE TABLE level (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institute_id    uuid NOT NULL REFERENCES institute(id) ON DELETE CASCADE,
  name            text NOT NULL,
  sequence        integer NOT NULL,
  -- بند P.5: سطح در ایران با کتاب شناخته می‌شود، نه با A1/B2
  book_title      text,
  book_part       text,
  unit_from       integer,
  unit_to         integer,
  local_name      text,
  age_group       text CHECK (age_group IN ('kids','teen','adult')),
  cefr_equivalent text,
  status          text NOT NULL DEFAULT 'active'
);

CREATE TABLE term (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institute_id  uuid NOT NULL REFERENCES institute(id) ON DELETE CASCADE,
  name          text NOT NULL,
  -- تاریخ‌های تقویمی DATE می‌مانند، نه timestamp: سررسید یک «روز» است
  start_date    date NOT NULL,
  end_date      date NOT NULL,
  enrol_opens   date,
  enrol_closes  date,
  holidays      jsonb NOT NULL DEFAULT '[]'::jsonb,
  status        text NOT NULL DEFAULT 'draft'
                CHECK (status IN ('draft','published','active','ended','closed')),
  cloned_from   uuid REFERENCES term(id),
  created_at    timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE class (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institute_id  uuid NOT NULL REFERENCES institute(id) ON DELETE CASCADE,
  branch_id     uuid REFERENCES branch(id),
  term_id       uuid NOT NULL REFERENCES term(id) ON DELETE CASCADE,
  level_id      uuid REFERENCES level(id),
  name          text NOT NULL,
  teacher_user_id uuid REFERENCES app_user(id),
  room_id       uuid,
  -- بند P.2: الگوی روز همان چیزی است که آموزشگاه با آن فکر می‌کند
  day_pattern   text CHECK (day_pattern IN ('فرد','زوج','پنجشنبه','جمعه','فشرده','custom')),
  weekdays      smallint[] NOT NULL DEFAULT '{}',   -- ۰ = شنبه
  start_time    time NOT NULL,
  end_time      time NOT NULL,
  capacity_min  smallint NOT NULL DEFAULT 6,
  capacity_max  smallint NOT NULL DEFAULT 18,
  enrolled_count smallint NOT NULL DEFAULT 0,
  price_minor   bigint NOT NULL DEFAULT 0,
  delivery_mode text NOT NULL DEFAULT 'in_person'
                CHECK (delivery_mode IN ('in_person','online','hybrid')),
  preferred_provider text,
  provider_locked    boolean NOT NULL DEFAULT false,
  auto_attendance    boolean NOT NULL DEFAULT true,
  min_presence_percent smallint NOT NULL DEFAULT 60,
  status        text NOT NULL DEFAULT 'draft',
  created_at    timestamptz NOT NULL DEFAULT now(),
  -- بند I.11: کلاس منتشرشده باید مدرس داشته باشد
  CONSTRAINT published_needs_teacher
    CHECK (status <> 'published' OR teacher_user_id IS NOT NULL),
  CONSTRAINT capacity_sane CHECK (capacity_max >= capacity_min)
);
CREATE INDEX ON class (institute_id, term_id, status);

CREATE TABLE session (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institute_id    uuid NOT NULL REFERENCES institute(id) ON DELETE CASCADE,
  class_id        uuid NOT NULL REFERENCES class(id) ON DELETE CASCADE,
  session_number  smallint NOT NULL,
  scheduled_date  date NOT NULL,
  start_time      time NOT NULL,
  end_time        time NOT NULL,
  -- مدرسی که واقعاً تدریس کرد؛ ممکن است با class.teacher_user_id فرق کند.
  -- بازنویسی این مقدار، هم گزارش بار کاری و هم حقوق را خراب می‌کند (بند N.R4).
  teacher_user_id uuid REFERENCES app_user(id),
  status          text NOT NULL DEFAULT 'scheduled'
                  CHECK (status IN ('scheduled','held','cancelled','makeup')),
  cancellation_reason text,
  makeup_for      uuid REFERENCES session(id),
  topic_covered   text,
  attendance_marked_at timestamptz,
  attendance_marked_by uuid REFERENCES app_user(id),
  UNIQUE (class_id, session_number)
);
CREATE INDEX ON session (institute_id, scheduled_date);

CREATE TABLE student (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institute_id    uuid NOT NULL REFERENCES institute(id) ON DELETE CASCADE,
  user_id         uuid REFERENCES app_user(id),   -- ممکن است هرگز وارد نشود
  student_code    text NOT NULL,
  first_name      text NOT NULL,
  last_name       text NOT NULL,
  phone           text,
  national_id     text,
  birth_date      date,
  photo_url       text,
  primary_branch_id uuid REFERENCES branch(id),
  current_level_id  uuid REFERENCES level(id),
  source          text,
  status          text NOT NULL DEFAULT 'active',
  balance_cached_minor bigint NOT NULL DEFAULT 0,
  created_at      timestamptz NOT NULL DEFAULT now(),
  UNIQUE (institute_id, student_code)
);
-- کلید تشخیص تکراری (بند P.6)
CREATE INDEX ON student (institute_id, phone);
CREATE INDEX ON student (institute_id, national_id);

CREATE TABLE enrolment (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institute_id  uuid NOT NULL REFERENCES institute(id) ON DELETE CASCADE,
  student_id    uuid NOT NULL REFERENCES student(id) ON DELETE CASCADE,
  class_id      uuid NOT NULL REFERENCES class(id) ON DELETE CASCADE,
  term_id       uuid NOT NULL REFERENCES term(id),
  status        text NOT NULL DEFAULT 'active'
                CHECK (status IN ('pending_payment','reserved','active','completed',
                                  'withdrawn','transferred','waitlisted')),
  enrolled_at   timestamptz NOT NULL DEFAULT now(),
  price_minor       bigint NOT NULL DEFAULT 0,
  discount_minor    bigint NOT NULL DEFAULT 0,
  final_price_minor bigint NOT NULL DEFAULT 0,
  final_grade   numeric(4,2),
  passed        boolean,
  attendance_rate numeric(5,2),
  UNIQUE (student_id, class_id)
);
CREATE INDEX ON enrolment (institute_id, term_id, status);

CREATE TABLE attendance (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institute_id  uuid NOT NULL REFERENCES institute(id) ON DELETE CASCADE,
  session_id    uuid NOT NULL REFERENCES session(id) ON DELETE CASCADE,
  student_id    uuid NOT NULL REFERENCES student(id) ON DELETE CASCADE,
  -- not_applicable برای جلسهٔ لغوشده و تاریخ‌های قبل از ثبت‌نام.
  -- قاطی‌کردن آن با absent، هر نرخ حضوری را در کل محصول خراب می‌کند (بند N.R10).
  status        text NOT NULL CHECK (status IN ('present','absent','late','excused','not_applicable')),
  minutes_late  smallint,
  reason        text,
  source        text NOT NULL DEFAULT 'manual' CHECK (source IN ('manual','auto','mixed')),
  marked_by     uuid REFERENCES app_user(id),
  marked_at     timestamptz NOT NULL DEFAULT now(),
  UNIQUE (session_id, student_id)
);

-- ── کلاس آنلاین (بند Q.3) ───────────────────────────────────────────
CREATE TABLE online_session (
  id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institute_id      uuid NOT NULL REFERENCES institute(id) ON DELETE CASCADE,
  session_id        uuid NOT NULL UNIQUE REFERENCES session(id) ON DELETE CASCADE,
  class_id          uuid NOT NULL REFERENCES class(id) ON DELETE CASCADE,
  provider          text NOT NULL,
  provider_meeting_id text,
  state             text NOT NULL DEFAULT 'scheduled'
                    CHECK (state IN ('scheduled','starting','live','ended','failed')),
  started_at        timestamptz,
  ended_at          timestamptz,
  peak_participants smallint NOT NULL DEFAULT 0,
  record_enabled    boolean NOT NULL DEFAULT true,
  recording_state   text NOT NULL DEFAULT 'none'
                    CHECK (recording_state IN ('none','processing','ready','failed','deleted')),
  recording_url     text,
  recording_size_bytes bigint,
  recording_expires_at timestamptz,
  attendance_source text NOT NULL DEFAULT 'manual',
  error_code        text,
  created_at        timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE session_participant (
  id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institute_id      uuid NOT NULL REFERENCES institute(id) ON DELETE CASCADE,
  online_session_id uuid NOT NULL REFERENCES online_session(id) ON DELETE CASCADE,
  student_id        uuid REFERENCES student(id),
  provider_user_id  text,
  display_name_reported text,
  joined_at         timestamptz,
  left_at           timestamptz,
  total_seconds     integer NOT NULL DEFAULT 0,
  rejoin_count      smallint NOT NULL DEFAULT 0,
  -- unmatched یعنی نمی‌دانیم این چه کسی بود؛ به مدرس نشان داده می‌شود،
  -- هرگز خودکار غایب نمی‌شود (بند Q.5)
  matched_confidence text NOT NULL DEFAULT 'unmatched'
                    CHECK (matched_confidence IN ('exact','probable','unmatched'))
);

-- ── مالی: فقط افزودنی، هرگز ویرایش یا حذف ───────────────────────────
CREATE TABLE invoice (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institute_id    uuid NOT NULL REFERENCES institute(id) ON DELETE CASCADE,
  student_id      uuid NOT NULL REFERENCES student(id),
  enrolment_id    uuid REFERENCES enrolment(id),
  invoice_number  bigint NOT NULL,
  issue_date      date NOT NULL DEFAULT current_date,
  due_date        date,
  total_minor     bigint NOT NULL,
  paid_minor      bigint NOT NULL DEFAULT 0,
  status          text NOT NULL DEFAULT 'issued',
  line_items      jsonb NOT NULL DEFAULT '[]'::jsonb,
  created_by      uuid REFERENCES app_user(id),
  created_at      timestamptz NOT NULL DEFAULT now(),
  -- بند I.11: شماره‌گذاری بدون شکاف، به‌ازای هر آموزشگاه
  UNIQUE (institute_id, invoice_number)
);

CREATE TABLE instalment (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institute_id  uuid NOT NULL REFERENCES institute(id) ON DELETE CASCADE,
  invoice_id    uuid NOT NULL REFERENCES invoice(id) ON DELETE CASCADE,
  sequence      smallint NOT NULL,
  amount_minor  bigint NOT NULL,
  due_date      date NOT NULL,
  paid_minor    bigint NOT NULL DEFAULT 0,
  status        text NOT NULL DEFAULT 'pending',
  UNIQUE (invoice_id, sequence)
);

CREATE TABLE payment (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  institute_id    uuid NOT NULL REFERENCES institute(id) ON DELETE CASCADE,
  student_id      uuid NOT NULL REFERENCES student(id),
  invoice_id      uuid REFERENCES invoice(id),
  instalment_id   uuid REFERENCES instalment(id),
  amount_minor    bigint NOT NULL CHECK (amount_minor > 0),
  method          text NOT NULL CHECK (method IN
                  ('cash','card','bank_transfer','online_gateway','cheque','credit','pos')),
  gateway         text,
  gateway_txn_id  text,
  reference_number text,
  proof_url       text,           -- عکس فیش کارت به کارت
  status          text NOT NULL DEFAULT 'completed'
                  CHECK (status IN ('pending','pending_verification','completed','failed','reversed')),
  -- بدون این، callback تکراری درگاه دو بار پول ثبت می‌کند (بند N.R2)
  idempotency_key text,
  paid_at         timestamptz NOT NULL DEFAULT now(),
  recorded_by     uuid REFERENCES app_user(id),
  verified_by     uuid REFERENCES app_user(id),
  reversal_of     uuid REFERENCES payment(id),
  UNIQUE (institute_id, idempotency_key)
);
CREATE INDEX ON payment (institute_id, status, paid_at);

-- ── ردپای تغییرات ───────────────────────────────────────────────────
CREATE TABLE audit_log (
  id            bigserial PRIMARY KEY,
  institute_id  uuid,
  actor_user_id uuid,
  actor_type    text NOT NULL DEFAULT 'user',
  action        text NOT NULL,
  entity_type   text NOT NULL,
  entity_id     uuid,
  before        jsonb,
  after         jsonb,
  ip_address    inet,
  impersonated_by uuid,
  reason        text,
  created_at    timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON audit_log (institute_id, created_at DESC);

-- ═══ جداسازی مستأجرها ═══════════════════════════════════════════════
-- کوئری بدون تنظیم app.institute_id صفر سطر می‌دهد، نه دادهٔ مستأجر دیگر.
DO $$
DECLARE t text;
BEGIN
  FOREACH t IN ARRAY ARRAY[
    'branch','membership','level','term','class','session','student','enrolment',
    'attendance','online_session','session_participant','invoice','instalment','payment'
  ] LOOP
    EXECUTE format('ALTER TABLE %I ENABLE ROW LEVEL SECURITY', t);
    EXECUTE format('ALTER TABLE %I FORCE ROW LEVEL SECURITY', t);
    EXECUTE format($f$
      CREATE POLICY tenant_isolation ON %I
      USING (institute_id = nullif(current_setting('app.institute_id', true), '')::uuid)
      WITH CHECK (institute_id = nullif(current_setting('app.institute_id', true), '')::uuid)
    $f$, t);
    EXECUTE format('GRANT SELECT, INSERT, UPDATE, DELETE ON %I TO lingotalk_app', t);
  END LOOP;
END $$;

ALTER TABLE institute ENABLE ROW LEVEL SECURITY;
ALTER TABLE institute FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_self ON institute
  USING (id = nullif(current_setting('app.institute_id', true), '')::uuid);
GRANT SELECT, UPDATE ON institute TO lingotalk_app;

GRANT SELECT, INSERT, UPDATE ON app_user TO lingotalk_app;
GRANT INSERT, SELECT ON audit_log TO lingotalk_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO lingotalk_app;

-- ── سنجه‌های یکپارچگی (بند I.11) ────────────────────────────────────
-- ظرفیت کلاس به‌صورت تراکنشی چک می‌شود، وگرنه دو ثبت‌نام هم‌زمان
-- روی آخرین صندلی، هر دو موفق می‌شوند.
CREATE OR REPLACE FUNCTION enforce_class_capacity() RETURNS trigger AS $$
DECLARE current_count integer; max_cap smallint;
BEGIN
  IF NEW.status NOT IN ('active','reserved','pending_payment') THEN RETURN NEW; END IF;
  SELECT capacity_max INTO max_cap FROM class WHERE id = NEW.class_id FOR UPDATE;
  SELECT count(*) INTO current_count FROM enrolment
    WHERE class_id = NEW.class_id
      AND status IN ('active','reserved','pending_payment')
      AND id <> NEW.id;
  IF current_count >= max_cap THEN
    RAISE EXCEPTION 'ظرفیت کلاس تکمیل است (%/%)', current_count, max_cap
      USING ERRCODE = 'check_violation';
  END IF;
  RETURN NEW;
END $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_class_capacity
  BEFORE INSERT OR UPDATE OF status, class_id ON enrolment
  FOR EACH ROW EXECUTE FUNCTION enforce_class_capacity();

-- رکورد مالی هرگز حذف نمی‌شود؛ اصلاح یعنی سطر معکوس.
CREATE OR REPLACE FUNCTION block_financial_delete() RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION 'رکورد مالی حذف نمی‌شود؛ به‌جای آن سطر معکوس ثبت کنید';
END $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_no_payment_delete BEFORE DELETE ON payment
  FOR EACH ROW EXECUTE FUNCTION block_financial_delete();
CREATE TRIGGER trg_no_invoice_delete BEFORE DELETE ON invoice
  FOR EACH ROW EXECUTE FUNCTION block_financial_delete();
