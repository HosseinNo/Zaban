-- تاکورا — اسکیمای احراز هویت برای MySQL هاست اشتراکی
-- در phpMyAdmin پلسک اجرا کنید (Databases → phpMyAdmin → SQL)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS app_user (
  id                CHAR(32)     NOT NULL PRIMARY KEY,
  phone             VARCHAR(15)  NOT NULL,
  full_name         VARCHAR(120) NOT NULL,
  institute_name    VARCHAR(160) NULL,
  role              VARCHAR(24)  NOT NULL DEFAULT 'student',
  status            VARCHAR(16)  NOT NULL DEFAULT 'active',
  phone_verified_at DATETIME     NULL,
  last_login_at     DATETIME     NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_phone (phone),
  KEY ix_user_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS otp_code (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phone       VARCHAR(15)  NOT NULL,
  -- فقط HMAC کد ذخیره می‌شود؛ خود کد هیچ‌جا نوشته نمی‌شود
  code_hash   CHAR(64)     NOT NULL,
  -- فقط در «حالت پل» پر می‌شود: وقتی هنوز sms.ir راه نیفتاده و کد را
  -- مدیر از پنل ادمین می‌خواند و به کاربر می‌گوید. حداکثر دو دقیقه
  -- زنده می‌ماند و لحظهٔ مصرف‌شدن پاک می‌شود. در حالت پیامک واقعی
  -- همیشه NULL است.
  pending_code VARCHAR(8)  NULL,
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at  DATETIME     NOT NULL,
  consumed_at DATETIME     NULL,
  ip          VARCHAR(45)  NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_otp_lookup (phone, consumed_at, expires_at),
  KEY ix_otp_cleanup (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS session_token (
  token_hash   CHAR(64)    NOT NULL PRIMARY KEY,
  user_id      CHAR(32)    NOT NULL,
  expires_at   DATETIME    NOT NULL,
  last_seen_at DATETIME    NULL,
  revoked_at   DATETIME    NULL,
  ip           VARCHAR(45) NULL,
  user_agent   VARCHAR(255) NULL,
  created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_session_user (user_id),
  KEY ix_session_exp (expires_at),
  CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limit (
  bucket       VARCHAR(32) NOT NULL,
  rl_key       VARCHAR(64) NOT NULL,
  window_start BIGINT      NOT NULL,
  hits         INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket, rl_key, window_start),
  KEY ix_rl_cleanup (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_user_id CHAR(32)    NULL,
  action        VARCHAR(64) NOT NULL,
  ip            VARCHAR(45) NULL,
  meta          TEXT        NULL,
  created_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_audit_time (created_at),
  KEY ix_audit_actor (actor_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═══════════════════════════════════════════════════════════════════
--  نسخهٔ ۲ — جدول‌های عملیاتی آموزشگاه
--
--  چند نکتهٔ طراحی که عمدی است:
--
--  ۱) روی MySQL هاست اشتراکی، Row Level Security پستگرس را نداریم.
--     پس هر جدول institute_id دارد و *هر* کوئری باید با آن فیلتر شود.
--     این کار در _ctx.php متمرکز شده تا در نقاط پایانی فراموش نشود.
--
--  ۲) تاریخ‌ها میلادی و به شکل DATE ذخیره می‌شوند، نمایش‌شان شمسی است
--     (بند P.2). تبدیل در مرورگر انجام می‌شود، نه در دیتابیس.
--
--  ۳) مبلغ‌ها ریال و صحیح‌اند، هرگز اعشاری. نمایش به تومان با تقسیم بر ۱۰.
-- ═══════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS institute (
  id            CHAR(32)     NOT NULL PRIMARY KEY,
  name          VARCHAR(160) NOT NULL,
  owner_user_id CHAR(32)     NOT NULL,
  phone         VARCHAR(20)  NULL,
  city          VARCHAR(80)  NULL,
  term_weeks    TINYINT UNSIGNED NOT NULL DEFAULT 12,
  created_at    DATETIME     NOT NULL,
  KEY ix_inst_owner (owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- کاربر می‌تواند در چند آموزشگاه عضو باشد و در هرکدام نقش دیگری داشته باشد
CREATE TABLE IF NOT EXISTS membership (
  id           CHAR(32)    NOT NULL PRIMARY KEY,
  institute_id CHAR(32)    NOT NULL,
  user_id      CHAR(32)    NOT NULL,
  role         VARCHAR(16) NOT NULL,            -- manager | teacher | student
  status       VARCHAR(16) NOT NULL DEFAULT 'active',
  hourly_rate  BIGINT      NOT NULL DEFAULT 0,  -- ریال، فقط برای مدرس
  created_at   DATETIME    NOT NULL,
  UNIQUE KEY uq_member (institute_id, user_id),
  KEY ix_member_user (user_id),
  KEY ix_member_role (institute_id, role),
  CONSTRAINT fk_member_inst FOREIGN KEY (institute_id) REFERENCES institute(id) ON DELETE CASCADE,
  CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- مدیر با شماره دعوت می‌کند؛ کاربر هنوز ثبت‌نام نکرده است.
-- اولین باری که با همان شماره وارد شود، عضویتش خودکار ساخته می‌شود.
CREATE TABLE IF NOT EXISTS invite (
  id           CHAR(32)     NOT NULL PRIMARY KEY,
  institute_id CHAR(32)     NOT NULL,
  phone        VARCHAR(15)  NOT NULL,
  full_name    VARCHAR(120) NOT NULL,
  role         VARCHAR(16)  NOT NULL,
  class_id     CHAR(32)     NULL,               -- اگر زبان‌آموز است، مستقیم در این کلاس ثبت شود
  accepted_at  DATETIME     NULL,
  created_at   DATETIME     NOT NULL,
  UNIQUE KEY uq_invite (institute_id, phone),
  KEY ix_invite_phone (phone, accepted_at),
  CONSTRAINT fk_invite_inst FOREIGN KEY (institute_id) REFERENCES institute(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS room (
  id           CHAR(32)    NOT NULL PRIMARY KEY,
  institute_id CHAR(32)    NOT NULL,
  name         VARCHAR(80) NOT NULL,
  capacity     SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  created_at   DATETIME    NOT NULL,
  KEY ix_room_inst (institute_id),
  CONSTRAINT fk_room_inst FOREIGN KEY (institute_id) REFERENCES institute(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS term (
  id           CHAR(32)    NOT NULL PRIMARY KEY,
  institute_id CHAR(32)    NOT NULL,
  name         VARCHAR(80) NOT NULL,
  starts_on    DATE        NOT NULL,
  weeks        TINYINT UNSIGNED NOT NULL DEFAULT 12,
  status       VARCHAR(16) NOT NULL DEFAULT 'active',   -- draft | active | closed
  created_at   DATETIME    NOT NULL,
  KEY ix_term_inst (institute_id, status),
  CONSTRAINT fk_term_inst FOREIGN KEY (institute_id) REFERENCES institute(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS klass (
  id              CHAR(32)     NOT NULL PRIMARY KEY,
  institute_id    CHAR(32)     NOT NULL,
  term_id         CHAR(32)     NULL,
  name            VARCHAR(160) NOT NULL,
  level           VARCHAR(40)  NOT NULL DEFAULT '',
  teacher_user_id CHAR(32)     NULL,
  room_id         CHAR(32)     NULL,
  day_pattern     VARCHAR(16)  NOT NULL DEFAULT 'فرد',   -- فرد | زوج | پنجشنبه | جمعه | فشرده
  start_time      VARCHAR(5)   NOT NULL DEFAULT '18:00', -- HH:MM ۲۴ ساعته، لاتین
  duration_min    SMALLINT UNSIGNED NOT NULL DEFAULT 90,
  capacity        SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  total_sessions  SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  mode            VARCHAR(16)  NOT NULL DEFAULT 'in_person', -- in_person | online | hybrid
  provider        VARCHAR(16)  NOT NULL DEFAULT 'meet',      -- bbb | meet | skyroom | custom
  join_url        VARCHAR(500) NULL,
  price           BIGINT       NOT NULL DEFAULT 0,           -- ریال
  status          VARCHAR(16)  NOT NULL DEFAULT 'draft',     -- draft | published | closed
  created_at      DATETIME     NOT NULL,
  KEY ix_class_inst (institute_id, status),
  KEY ix_class_teacher (institute_id, teacher_user_id),
  CONSTRAINT fk_class_inst FOREIGN KEY (institute_id) REFERENCES institute(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enrolment (
  id              CHAR(32)    NOT NULL PRIMARY KEY,
  institute_id    CHAR(32)    NOT NULL,
  class_id        CHAR(32)    NOT NULL,
  student_user_id CHAR(32)    NOT NULL,
  status          VARCHAR(16) NOT NULL DEFAULT 'active',   -- active | withdrawn
  created_at      DATETIME    NOT NULL,
  UNIQUE KEY uq_enrol (class_id, student_user_id),
  KEY ix_enrol_student (institute_id, student_user_id),
  CONSTRAINT fk_enrol_class FOREIGN KEY (class_id) REFERENCES klass(id) ON DELETE CASCADE,
  CONSTRAINT fk_enrol_user FOREIGN KEY (student_user_id) REFERENCES app_user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_session (
  id           CHAR(32)    NOT NULL PRIMARY KEY,
  institute_id CHAR(32)    NOT NULL,
  class_id     CHAR(32)    NOT NULL,
  seq          SMALLINT UNSIGNED NOT NULL,
  session_date DATE        NOT NULL,
  start_time   VARCHAR(5)  NOT NULL,
  status       VARCHAR(16) NOT NULL DEFAULT 'scheduled', -- scheduled | live | done | cancelled
  join_url     VARCHAR(500) NULL,
  started_at   DATETIME    NULL,
  ended_at     DATETIME    NULL,
  note         VARCHAR(255) NULL,
  UNIQUE KEY uq_session (class_id, seq),
  KEY ix_session_date (institute_id, session_date),
  KEY ix_session_status (institute_id, status),
  CONSTRAINT fk_session_class FOREIGN KEY (class_id) REFERENCES klass(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance (
  id              CHAR(32)    NOT NULL PRIMARY KEY,
  institute_id    CHAR(32)    NOT NULL,
  session_id      CHAR(32)    NOT NULL,
  student_user_id CHAR(32)    NOT NULL,
  status          VARCHAR(16) NOT NULL,   -- present | absent | late | excused
  note            VARCHAR(255) NULL,
  marked_by       CHAR(32)    NULL,
  marked_at       DATETIME    NOT NULL,
  -- یک ردیف برای هر جلسه و هر زبان‌آموز؛ ثبت دوباره باید به‌روزرسانی باشد نه تکرار
  UNIQUE KEY uq_attendance (session_id, student_user_id),
  KEY ix_att_student (institute_id, student_user_id),
  CONSTRAINT fk_att_session FOREIGN KEY (session_id) REFERENCES class_session(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignment (
  id           CHAR(32)     NOT NULL PRIMARY KEY,
  institute_id CHAR(32)     NOT NULL,
  class_id     CHAR(32)     NOT NULL,
  title        VARCHAR(200) NOT NULL,
  type         VARCHAR(16)  NOT NULL DEFAULT 'writing',  -- writing | speaking | quiz | file
  description  TEXT         NULL,
  due_at       DATETIME     NULL,
  max_score    SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  status       VARCHAR(16)  NOT NULL DEFAULT 'open',     -- open | closed
  created_by   CHAR(32)     NULL,
  created_at   DATETIME     NOT NULL,
  KEY ix_asg_class (institute_id, class_id),
  CONSTRAINT fk_asg_class FOREIGN KEY (class_id) REFERENCES klass(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS submission (
  id              CHAR(32)  NOT NULL PRIMARY KEY,
  institute_id    CHAR(32)  NOT NULL,
  assignment_id   CHAR(32)  NOT NULL,
  student_user_id CHAR(32)  NOT NULL,
  body_text       MEDIUMTEXT NULL,
  file_path       VARCHAR(200) NULL,   -- نام تصادفی روی دیسک، نه نام اصلی کاربر
  file_name       VARCHAR(200) NULL,   -- نام اصلی، فقط برای نمایش و دانلود
  file_size       INT UNSIGNED NULL,
  submitted_at    DATETIME  NOT NULL,
  is_late         TINYINT(1) NOT NULL DEFAULT 0,
  score           DECIMAL(5,2) NULL,
  feedback        TEXT      NULL,
  graded_by       CHAR(32)  NULL,
  graded_at       DATETIME  NULL,
  UNIQUE KEY uq_submission (assignment_id, student_user_id),
  KEY ix_sub_student (institute_id, student_user_id),
  CONSTRAINT fk_sub_asg FOREIGN KEY (assignment_id) REFERENCES assignment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═══════════════════════════════════════════════════════════════════
--  نسخهٔ ۳ — پنل ادمین تاکورا
--
--  این‌ها مال شما (صاحب محصول) است، نه مال آموزشگاه‌ها. برای همین
--  institute_id ندارند و ورودشان با نام کاربری و رمز است، نه پیامک:
--  ادمین یک نفر است و شمارهٔ موبایلش نباید تنها کلید سامانه باشد.
-- ═══════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS admin_user (
  id            CHAR(32)    NOT NULL PRIMARY KEY,
  username      VARCHAR(64) NOT NULL,
  -- password_hash با الگوریتم پیش‌فرض PHP؛ خود رمز هیچ‌جا ذخیره نمی‌شود
  pass_hash     VARCHAR(255) NOT NULL,
  full_name     VARCHAR(120) NOT NULL DEFAULT '',
  status        VARCHAR(16) NOT NULL DEFAULT 'active',
  last_login_at DATETIME    NULL,
  created_at    DATETIME    NOT NULL,
  UNIQUE KEY uq_admin_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_session (
  token_hash   CHAR(64)    NOT NULL PRIMARY KEY,
  admin_id     CHAR(32)    NOT NULL,
  expires_at   DATETIME    NOT NULL,
  last_seen_at DATETIME    NULL,
  revoked_at   DATETIME    NULL,
  ip           VARCHAR(45) NULL,
  user_agent   VARCHAR(255) NULL,
  created_at   DATETIME    NOT NULL,
  KEY ix_asession_admin (admin_id),
  CONSTRAINT fk_asession_admin FOREIGN KEY (admin_id) REFERENCES admin_user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تنظیمات سایت: کلید و مقدار، تا اضافه‌کردن تنظیم تازه مهاجرت نخواهد
CREATE TABLE IF NOT EXISTS site_setting (
  skey       VARCHAR(64) NOT NULL PRIMARY KEY,
  svalue     TEXT        NULL,
  updated_at DATETIME    NOT NULL,
  updated_by CHAR(32)    NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- درخواست‌های دموی رایگان از فرم سایت
CREATE TABLE IF NOT EXISTS demo_lead (
  id         CHAR(32)     NOT NULL PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  phone      VARCHAR(20)  NOT NULL,
  email      VARCHAR(160) NULL,
  institute  VARCHAR(160) NULL,
  students   VARCHAR(40)  NULL,
  note       TEXT         NULL,
  status     VARCHAR(16)  NOT NULL DEFAULT 'new',   -- new | contacted | won | lost
  admin_note TEXT         NULL,
  ip         VARCHAR(45)  NULL,
  mailed     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL,
  KEY ix_lead_status (status, created_at),
  KEY ix_lead_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
