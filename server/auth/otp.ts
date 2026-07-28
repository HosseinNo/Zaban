/**
 * ورود با کد یک‌بارمصرف پیامکی.
 *
 * قواعد بند S.8 و P.6:
 *  - کد ۵ رقمی، اعتبار ۲ دقیقه
 *  - در Redis فقط «هش» کد ذخیره می‌شود، نه خود کد
 *  - حداکثر ۵ تلاش اشتباه، بعد کد باطل
 *  - محدودیت نرخ: ۵ در ساعت برای شماره، ۲۰ در ساعت برای IP
 *  - ارسال از «خط خدماتی»، نه تبلیغاتی — وگرنه به دست کسی که تبلیغات را
 *    لغو کرده اصلاً نمی‌رسد
 *  - پاسخ برای شمارهٔ موجود و ناموجود یکسان است تا فهرست کاربران قابل
 *    استخراج نباشد
 */

import { createHmac, randomInt, timingSafeEqual } from 'node:crypto';

export interface OtpDeps {
  redis: {
    get(k: string): Promise<string | null>;
    setex(k: string, seconds: number, v: string): Promise<void>;
    del(k: string): Promise<void>;
    incr(k: string): Promise<number>;
    expire(k: string, seconds: number): Promise<void>;
  };
  sms: {
    /** حتماً از خط خدماتی؛ خط تبلیغاتی برای کد ورود قابل اتکا نیست */
    sendService(phone: string, text: string): Promise<{ providerMessageId: string }>;
  };
  /** کلید HMAC از متغیر محیطی؛ هرگز در کد */
  pepper: string;
  now?: () => number;
}

export const OTP_TTL_SECONDS = 120;
export const RESEND_COOLDOWN_SECONDS = 60;
export const MAX_VERIFY_ATTEMPTS = 5;
export const RATE_PER_PHONE_HOUR = 5;
export const RATE_PER_IP_HOUR = 20;

/** ۰۹xxxxxxxxx — ارقام فارسی و عربی هم پذیرفته می‌شود */
export function normalizePhone(raw: string): string | null {
  const latin = String(raw)
    .replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))
    .replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
    .replace(/[\s\-()]/g, '');
  const m = latin.match(/^(?:\+?98|0098|0)?(9\d{9})$/);
  return m ? `0${m[1]}` : null;
}

function hash(code: string, phone: string, pepper: string): string {
  return createHmac('sha256', pepper).update(`${phone}:${code}`).digest('hex');
}

function safeEqual(a: string, b: string): boolean {
  const ab = Buffer.from(a), bb = Buffer.from(b);
  return ab.length === bb.length && timingSafeEqual(ab, bb);
}

export type RequestResult =
  | { ok: true; resendAfterSeconds: number }
  | { ok: false; reason: 'invalid_phone' | 'cooldown' | 'rate_limited'; retryAfterSeconds?: number };

export async function requestOtp(
  rawPhone: string, ip: string, d: OtpDeps,
): Promise<RequestResult> {
  const phone = normalizePhone(rawPhone);
  if (!phone) return { ok: false, reason: 'invalid_phone' };

  // فاصلهٔ ارسال دوباره
  const cdKey = `otp:cd:${phone}`;
  if (await d.redis.get(cdKey)) {
    return { ok: false, reason: 'cooldown', retryAfterSeconds: RESEND_COOLDOWN_SECONDS };
  }

  // محدودیت نرخ — هم برای امنیت، هم چون هر پیامک هزینهٔ واقعی دارد
  const phoneCount = await d.redis.incr(`otp:rl:p:${phone}`);
  if (phoneCount === 1) await d.redis.expire(`otp:rl:p:${phone}`, 3600);
  if (phoneCount > RATE_PER_PHONE_HOUR) return { ok: false, reason: 'rate_limited' };

  const ipCount = await d.redis.incr(`otp:rl:i:${ip}`);
  if (ipCount === 1) await d.redis.expire(`otp:rl:i:${ip}`, 3600);
  if (ipCount > RATE_PER_IP_HOUR) return { ok: false, reason: 'rate_limited' };

  const code = String(randomInt(0, 100000)).padStart(5, '0');

  await d.redis.setex(
    `otp:c:${phone}`, OTP_TTL_SECONDS,
    JSON.stringify({ h: hash(code, phone, d.pepper), tries: 0 }),
  );
  await d.redis.setex(cdKey, RESEND_COOLDOWN_SECONDS, '1');

  await d.sms.sendService(
    phone,
    `کد ورود شما به لینگوتاک: ${code}\nاین کد ۲ دقیقه اعتبار دارد.\nلغو۱۱`,
  );

  return { ok: true, resendAfterSeconds: RESEND_COOLDOWN_SECONDS };
}

export type VerifyResult =
  | { ok: true; phone: string }
  | { ok: false; reason: 'invalid_phone' | 'expired' | 'wrong' | 'too_many_attempts' };

export async function verifyOtp(
  rawPhone: string, code: string, d: OtpDeps,
): Promise<VerifyResult> {
  const phone = normalizePhone(rawPhone);
  if (!phone) return { ok: false, reason: 'invalid_phone' };

  const key = `otp:c:${phone}`;
  const stored = await d.redis.get(key);
  if (!stored) return { ok: false, reason: 'expired' };

  const { h, tries } = JSON.parse(stored) as { h: string; tries: number };

  if (tries >= MAX_VERIFY_ATTEMPTS) {
    await d.redis.del(key);
    return { ok: false, reason: 'too_many_attempts' };
  }

  const clean = normalizePhone(code) ?? code.replace(/\D/g, '');
  const candidate = hash(String(clean).slice(-5), phone, d.pepper);

  if (!safeEqual(candidate, h)) {
    await d.redis.setex(key, OTP_TTL_SECONDS, JSON.stringify({ h, tries: tries + 1 }));
    return { ok: false, reason: 'wrong' };
  }

  // کد یک‌بارمصرف است: بلافاصله باطل می‌شود
  await d.redis.del(key);
  await d.redis.del(`otp:rl:p:${phone}`);
  return { ok: true, phone };
}

/**
 * پاسخ HTTP یکسان برای شمارهٔ موجود و ناموجود.
 *
 * اگر برای شمارهٔ ثبت‌نشده خطای متفاوتی برگردانیم، هر کسی می‌تواند با
 * امتحان‌کردن شماره‌ها بفهمد چه کسانی در سامانه هستند.
 */
export function publicResponse(r: RequestResult): { status: number; body: unknown } {
  if (r.ok) {
    return { status: 200, body: { sent: true, resendAfter: r.resendAfterSeconds } };
  }
  if (r.reason === 'invalid_phone') {
    return { status: 400, body: { error: 'شمارهٔ موبایل باید ۱۱ رقم و با ۰۹ شروع شود.' } };
  }
  if (r.reason === 'cooldown') {
    return { status: 429, body: { error: 'کمی صبر کنید و دوباره تلاش کنید.', retryAfter: r.retryAfterSeconds } };
  }
  return { status: 429, body: { error: 'تعداد درخواست‌ها زیاد است. یک ساعت دیگر تلاش کنید.' } };
}
