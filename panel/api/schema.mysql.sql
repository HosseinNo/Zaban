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
