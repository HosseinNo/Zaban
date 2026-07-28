/**
 * اینترفیس مشترک ارائه‌دهندگان کلاس آنلاین.
 *
 * قاعدهٔ بند Q.1: ما اتاق جلسه نمی‌سازیم؛ اتاق‌ها را پشت یک اینترفیس یکسان
 * قرار می‌دهیم تا بقیهٔ محصول نداند BigBlueButton است یا Google Meet.
 * اضافه‌کردن ارائه‌دهندهٔ جدید نباید هیچ فایل دیگری را لمس کند.
 */

export type ProviderId = 'bbb' | 'meet' | 'skyroom' | 'adobe_connect' | 'jitsi';

export interface CreateMeetingInput {
  /** شناسهٔ جلسه در سیستم ما — برای idempotency استفاده می‌شود */
  onlineSessionId: string;
  instituteId: string;
  classId: string;
  title: string;
  /** زمان شروع برنامه‌ریزی‌شده (UTC ISO-8601) */
  startsAt: string;
  durationMinutes: number;
  record: boolean;
  /** اتاق انتظار: کسی تا تأیید مدرس وارد نمی‌شود */
  lobby: boolean;
  /** میکروفن ورودی‌ها بسته باشد */
  muteOnStart: boolean;
}

export interface CreatedMeeting {
  providerMeetingId: string;
  /** لینک مدیر جلسه — فقط سمت سرور صادر می‌شود، هرگز به کلاینت نمی‌رود مگر برای همان مدرس */
  moderatorJoinUrl: string;
  /** اگر ارائه‌دهنده لینک عمومی بدهد (مثل Meet) اینجا می‌آید */
  publicRoomUrl?: string;
  raw?: unknown;
}

export interface JoinInput {
  providerMeetingId: string;
  /** شناسهٔ مبهم، نه student_id واقعی (بند Q.9) */
  opaqueUserId: string;
  displayName: string;
  role: 'moderator' | 'attendee';
  /** عمر لینک به ثانیه؛ پیش‌فرض ۳۰ دقیقه */
  ttlSeconds?: number;
}

export interface Participant {
  providerUserId: string;
  displayName: string;
  /** اگر ارائه‌دهنده شناسهٔ ما را برگرداند، اینجا پر می‌شود. Meet معمولاً نمی‌دهد. */
  opaqueUserId?: string;
  joinedAt?: string;
  leftAt?: string;
  totalSeconds?: number;
  isModerator?: boolean;
}

export interface MeetingState {
  running: boolean;
  startedAt?: string;
  endedAt?: string;
  participantCount: number;
  participants: Participant[];
  /** آیا این ارائه‌دهنده می‌تواند حضور و غیاب قابل‌اتکا بدهد؟ */
  attendanceReliable: boolean;
}

export interface RecordingInfo {
  state: 'none' | 'processing' | 'ready' | 'failed';
  /** لینک دانلود مستقیم از ارائه‌دهنده — موقت است، فوراً به استوریج خودمان منتقل می‌شود */
  downloadUrl?: string;
  durationSeconds?: number;
  sizeBytes?: number;
  contentType?: string;
}

export interface MeetingProvider {
  readonly id: ProviderId;
  readonly displayName: string;

  /**
   * آیا حضور و غیاب خودکار این ارائه‌دهنده قابل اتکاست؟
   * این مقدار مستقیم در رابط کاربری به مدرس نشان داده می‌شود (بند Q.2).
   * Google Meet عمداً false است: شرکت‌کنندهٔ بدون اکانت Workspace فقط با نام
   * خوداظهاری شناخته می‌شود، پس نمی‌توان او را به زبان‌آموز ما تطبیق داد.
   */
  readonly attendanceReliable: boolean;

  /** آیا ضبط را از طریق API می‌شود گرفت و منتقل کرد؟ */
  readonly recordingRetrievable: boolean;

  create(input: CreateMeetingInput): Promise<CreatedMeeting>;
  joinUrl(input: JoinInput): Promise<string>;
  state(providerMeetingId: string): Promise<MeetingState>;
  end(providerMeetingId: string): Promise<void>;
  recording(providerMeetingId: string): Promise<RecordingInfo>;
  /** بعد از انتقال موفق به استوریج خودمان، از سرور ارائه‌دهنده پاک می‌شود */
  deleteRecording?(providerMeetingId: string): Promise<void>;
  /** بررسی سلامت برای failover */
  healthy(): Promise<boolean>;
}

/** ثبت ارائه‌دهنده‌ها؛ انتخاب در زمان اجرا بر اساس تنظیمات آموزشگاه و کلاس */
const registry = new Map<ProviderId, MeetingProvider>();

export function registerProvider(p: MeetingProvider): void {
  registry.set(p.id, p);
}

export function getProvider(id: ProviderId): MeetingProvider {
  const p = registry.get(id);
  if (!p) throw new Error(`ارائه‌دهندهٔ ثبت‌نشده: ${id}`);
  return p;
}

export function listProviders(): MeetingProvider[] {
  return [...registry.values()];
}

/**
 * ساخت جلسه با جایگزینی خودکار.
 *
 * بند Q.8: کلاس نباید به‌خاطر یک خطای API لغو شود. اگر ارائه‌دهندهٔ اول
 * جواب نداد، سراغ بعدی می‌رویم و به مدرس اطلاع می‌دهیم که ارائه‌دهنده عوض شد.
 */
export async function createWithFallback(
  preferred: ProviderId,
  fallbacks: ProviderId[],
  input: CreateMeetingInput,
): Promise<{ provider: ProviderId; meeting: CreatedMeeting; switched: boolean }> {
  const order = [preferred, ...fallbacks.filter((f) => f !== preferred)];
  const errors: string[] = [];

  for (const id of order) {
    let provider: MeetingProvider;
    try {
      provider = getProvider(id);
    } catch {
      continue;
    }
    try {
      const meeting = await provider.create(input);
      return { provider: id, meeting, switched: id !== preferred };
    } catch (err) {
      errors.push(`${id}: ${(err as Error).message}`);
    }
  }

  throw new Error(`ساخت اتاق با هیچ ارائه‌دهنده‌ای ممکن نشد — ${errors.join(' | ')}`);
}
