/**
 * کارگر بارگذاری خودکار ضبط کلاس.
 *
 * چرخه (بند S.7): جلسه تمام شد → صبر تا ضبط پردازش شود → دانلود جریانی از
 * ارائه‌دهنده → آپلود چندبخشی به آبجکت استوریج داخلی → ثبت در پایگاه داده →
 * انتشار برای زبان‌آموزان (اگر سیاست آموزشگاه اجازه دهد) → پاک‌کردن از
 * سرور ارائه‌دهنده.
 *
 * چیزی که این کارگر هرگز نمی‌کند: بی‌صدا شکست خوردن. هر مسیر خطا به مدرس
 * و مدیر اطلاع می‌دهد.
 */

import { Readable } from 'node:stream';
import { getProvider, type ProviderId } from '../meetings/provider.js';

/* ── وابستگی‌های تزریقی تا این فایل قابل تست باشد ── */
export interface Deps {
  db: {
    getOnlineSession(id: string): Promise<OnlineSessionRow | null>;
    updateOnlineSession(id: string, patch: Partial<OnlineSessionRow>): Promise<void>;
    getInstituteStorage(instituteId: string): Promise<{ usedBytes: number; quotaBytes: number }>;
    getRecordingPolicy(instituteId: string): Promise<'auto' | 'teacher_approval' | 'never'>;
  };
  storage: {
    /** آپلود چندبخشی و جریانی — فایل هرگز کامل در حافظه نمی‌آید */
    uploadStream(key: string, body: Readable, contentType: string): Promise<{ sizeBytes: number }>;
  };
  notify: {
    teacher(sessionId: string, msg: string): Promise<void>;
    manager(instituteId: string, msg: string): Promise<void>;
    classStudents(classId: string, msg: string): Promise<void>;
  };
  queue: {
    /** زمان‌بندی دوبارهٔ همین کار با تأخیر */
    retryIn(seconds: number, payload: JobPayload): Promise<void>;
  };
  log: (level: 'info' | 'warn' | 'error', msg: string, meta?: unknown) => void;
}

export interface OnlineSessionRow {
  id: string;
  institute_id: string;
  class_id: string;
  session_id: string;
  provider: ProviderId;
  provider_meeting_id: string;
  record_enabled: boolean;
  recording_state: 'none' | 'processing' | 'ready' | 'failed' | 'deleted';
  recording_url: string | null;
  term_code: string;
  class_code: string;
  session_number: number;
}

export interface JobPayload {
  onlineSessionId: string;
  /** چندمین تلاش — برای عقب‌نشینی نمایی و سقف تلاش */
  attempt: number;
}

/** ۱۰، ۲۰، ۴۰، ۸۰، ۱۶۰ دقیقه … تا سقف ۶ ساعت مجموع */
const BACKOFF_MINUTES = [10, 20, 40, 80, 160];
const MAX_ATTEMPTS = BACKOFF_MINUTES.length;

export async function handleRecordingJob(payload: JobPayload, d: Deps): Promise<void> {
  const { onlineSessionId, attempt } = payload;
  const s = await d.db.getOnlineSession(onlineSessionId);

  if (!s) { d.log('warn', 'جلسهٔ آنلاین پیدا نشد', { onlineSessionId }); return; }
  if (!s.record_enabled) return;
  // idempotency: اگر قبلاً منتقل شده، دوباره کاری نمی‌کنیم
  if (s.recording_state === 'ready' && s.recording_url) return;
  if (s.recording_state === 'deleted') return;

  const provider = getProvider(s.provider);

  if (!provider.recordingRetrievable) {
    await d.db.updateOnlineSession(s.id, { recording_state: 'none' });
    await d.notify.teacher(s.id,
      `ضبط این جلسه از طریق ${provider.displayName} قابل انتقال نیست. ` +
      `اگر ضبط لازم دارید، کلاس بعدی را روی BigBlueButton بگذارید.`);
    return;
  }

  /* ── ۱) آماده است؟ ── */
  let info;
  try {
    info = await provider.recording(s.provider_meeting_id);
  } catch (err) {
    d.log('error', 'خطا در استعلام ضبط', { onlineSessionId, err: String(err) });
    return retryOrGiveUp(s, payload, d, 'استعلام ضبط از سرور جلسه ناموفق بود');
  }

  if (info.state === 'processing' || info.state === 'none') {
    return retryOrGiveUp(s, payload, d, 'ضبط بعد از ۶ ساعت آماده نشد');
  }
  if (info.state === 'failed' || !info.downloadUrl) {
    await d.db.updateOnlineSession(s.id, { recording_state: 'failed' });
    await d.notify.teacher(s.id, 'ضبط این جلسه روی سرور جلسه خراب شد و قابل بازیابی نیست.');
    await d.notify.manager(s.institute_id,
      `ضبط جلسهٔ ${s.session_number} کلاس ${s.class_code} از دست رفت.`);
    return;
  }

  /* ── ۲) سهمیهٔ فضا ── */
  const quota = await d.db.getInstituteStorage(s.institute_id);
  const expected = info.sizeBytes ?? 0;
  if (expected > 0 && quota.usedBytes + expected > quota.quotaBytes) {
    await d.db.updateOnlineSession(s.id, { recording_state: 'processing' });
    await d.notify.manager(s.institute_id,
      'فضای ذخیره‌سازی پر شده و بارگذاری ضبط متوقف شد. ' +
      'ضبط فعلاً روی سرور جلسه می‌ماند؛ فضا را افزایش دهید یا ضبط‌های قدیمی را پاک کنید.');
    d.log('warn', 'سهمیهٔ فضا پر است', { institute: s.institute_id });
    return;   // عمداً تلاش دوباره نمی‌کنیم؛ تصمیم با مدیر است
  }

  /* ── ۳) دانلود جریانی و آپلود ── */
  const key = `institutes/${s.institute_id}/recordings/${s.term_code}/${s.class_code}/` +
              `session-${s.session_number}.mp4`;

  try {
    const res = await fetch(info.downloadUrl);
    if (!res.ok || !res.body) throw new Error(`دانلود ضبط: HTTP ${res.status}`);

    // Web ReadableStream → Node Readable، بدون بافر کردن کل فایل
    const body = Readable.fromWeb(res.body as any);
    const { sizeBytes } = await d.storage.uploadStream(
      key, body, info.contentType ?? 'video/mp4',
    );

    await d.db.updateOnlineSession(s.id, {
      recording_state: 'ready',
      recording_url: key,
    });
    d.log('info', 'ضبط منتقل شد', { onlineSessionId, key, sizeBytes });
  } catch (err) {
    d.log('error', 'انتقال ضبط ناموفق', { onlineSessionId, err: String(err) });
    return retryOrGiveUp(s, payload, d, 'انتقال فایل ضبط ناموفق بود');
  }

  /* ── ۴) انتشار ── */
  const policy = await d.db.getRecordingPolicy(s.institute_id);
  if (policy === 'auto') {
    await d.notify.classStudents(s.class_id,
      `ضبط جلسهٔ ${s.session_number} آماده است و در پرتال قابل پخش است.`);
  } else if (policy === 'teacher_approval') {
    await d.notify.teacher(s.id, 'ضبط جلسه آماده شد. برای انتشار برای زبان‌آموزها تأیید کنید.');
  }

  /* ── ۵) پاک‌کردن از سرور ارائه‌دهنده ── */
  // فقط بعد از آپلود موفق، وگرنه فایل را از دست می‌دهیم.
  try {
    await provider.deleteRecording?.(s.provider_meeting_id);
  } catch (err) {
    // شکست اینجا مهم نیست؛ فقط دیسک BBB را اشغال نگه می‌دارد.
    d.log('warn', 'پاک‌کردن ضبط از سرور جلسه ناموفق', { onlineSessionId, err: String(err) });
  }
}

async function retryOrGiveUp(
  s: OnlineSessionRow, payload: JobPayload, d: Deps, giveUpMsg: string,
): Promise<void> {
  if (payload.attempt < MAX_ATTEMPTS) {
    const mins = BACKOFF_MINUTES[payload.attempt];
    await d.queue.retryIn(mins * 60, { ...payload, attempt: payload.attempt + 1 });
    d.log('info', 'تلاش دوباره برای ضبط', { id: s.id, inMinutes: mins });
    return;
  }
  await d.db.updateOnlineSession(s.id, { recording_state: 'failed' });
  await d.notify.teacher(s.id, giveUpMsg);
  await d.notify.manager(s.institute_id,
    `${giveUpMsg} — جلسهٔ ${s.session_number} کلاس ${s.class_code}.`);
  d.log('error', 'ضبط نهایتاً شکست خورد', { id: s.id });
}
