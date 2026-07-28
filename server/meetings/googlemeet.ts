/**
 * آداپتور Google Meet.
 *
 * ⚠️ محدودیت‌های واقعی که در رابط کاربری هم به مدرس گفته می‌شود (بند Q.2):
 *
 *  ۱. شرکت‌کنندهٔ بدون اکانت Workspace فقط با «نام خوداظهاری» شناخته می‌شود.
 *     یعنی نمی‌توان او را قطعی به یک زبان‌آموز تطبیق داد → حضور و غیاب خودکار
 *     قابل اتکا نیست. به همین دلیل attendanceReliable = false.
 *  ۲. رکورد کنفرانس ۳۰ روز بعد از پایان جلسه حذف می‌شود.
 *  ۳. هر space فقط یک کنفرانس فعال دارد → برای هر جلسه یک space جدا می‌سازیم.
 *  ۴. سقف ۶۰ درخواست در دقیقه به‌ازای هر پروژه.
 *  ۵. اسکوپ meetings.space.created سطح‌کاربری است؛ حساب سرویس بدون
 *     domain-wide delegation نتیجهٔ خالی یا ناقص برمی‌گرداند.
 *
 * مرجع: https://developers.google.com/workspace/meet/api/guides/overview
 */

import type {
  CreateMeetingInput, CreatedMeeting, JoinInput, MeetingProvider,
  MeetingState, Participant, RecordingInfo,
} from './provider.js';

interface MeetConfig {
  /** توکن دسترسی؛ از حساب سرویس با domain-wide delegation گرفته می‌شود */
  getAccessToken: () => Promise<string>;
  /** کاربری که جلسه به نام او ساخته می‌شود، مثلاً classes@institute.ir */
  impersonateSubject: string;
  timeoutMs?: number;
}

const BASE = 'https://meet.googleapis.com/v2';

export class GoogleMeetProvider implements MeetingProvider {
  readonly id = 'meet' as const;
  readonly displayName = 'Google Meet';
  /** عمداً false — دلیلش بالای همین فایل توضیح داده شده */
  readonly attendanceReliable = false;
  /** ضبط فقط با پلن‌های بالای Workspace و دسترسی جداگانه در دسترس است */
  readonly recordingRetrievable = false;

  constructor(private cfg: MeetConfig) {}

  private async call(path: string, init?: RequestInit): Promise<any> {
    const token = await this.cfg.getAccessToken();
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), this.cfg.timeoutMs ?? 12_000);
    try {
      const res = await fetch(`${BASE}${path}`, {
        ...init,
        signal: ctrl.signal,
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          ...(init?.headers ?? {}),
        },
      });
      if (res.status === 429) throw new Error('Meet: سقف ۶۰ درخواست در دقیقه رد شد');
      if (!res.ok) throw new Error(`Meet ${path} → HTTP ${res.status}: ${await res.text()}`);
      return res.status === 204 ? null : await res.json();
    } finally {
      clearTimeout(t);
    }
  }

  async create(input: CreateMeetingInput): Promise<CreatedMeeting> {
    // هر جلسه یک space جدا: چون هر space فقط یک کنفرانس فعال می‌پذیرد،
    // استفادهٔ دوباره از یک space برای دو جلسهٔ هم‌زمان شکست می‌خورد.
    const space = await this.call('/spaces', {
      method: 'POST',
      body: JSON.stringify({
        config: {
          accessType: input.lobby ? 'TRUSTED' : 'OPEN',
          entryPointAccess: 'ALL',
        },
      }),
    });

    return {
      providerMeetingId: String(space.name),   // مثل: spaces/abc-defg-hij
      moderatorJoinUrl: String(space.meetingUri),
      publicRoomUrl: String(space.meetingUri),
      raw: space,
    };
  }

  async joinUrl(input: JoinInput): Promise<string> {
    // Meet لینک نقش‌محور یا امضاشده نمی‌دهد؛ همه یک URL می‌گیرند.
    // پس کنترل نقش و ورود، بیرون از Meet انجام می‌شود: لینک را فقط
    // به زبان‌آموز مجاز نشان می‌دهیم و ورودش را خودمان لاگ می‌کنیم.
    const space = await this.call(`/${input.providerMeetingId}`);
    return String(space.meetingUri);
  }

  async state(providerMeetingId: string): Promise<MeetingState> {
    const space = await this.call(`/${providerMeetingId}`);
    const active = space?.activeConference?.conferenceRecord;

    if (!active) {
      return { running: false, participantCount: 0, participants: [], attendanceReliable: false };
    }

    let participants: Participant[] = [];
    try {
      const res = await this.call(`/${active}/participants?pageSize=100`);
      participants = (res?.participants ?? []).map((p: any) => ({
        // فقط یکی از این سه پر می‌شود. برای anonymousUser هیچ هویتی نداریم.
        providerUserId: String(
          p.signedinUser?.user ?? p.anonymousUser?.displayName ?? p.phoneUser?.displayName ?? 'unknown',
        ),
        displayName: String(
          p.signedinUser?.displayName ?? p.anonymousUser?.displayName ?? 'ناشناس',
        ),
        // opaqueUserId عمداً خالی است: Meet شناسهٔ ما را برنمی‌گرداند.
        opaqueUserId: undefined,
        joinedAt: p.earliestStartTime,
        leftAt: p.latestEndTime,
      }));
    } catch {
      // بدون domain-wide delegation اینجا خالی برمی‌گردد — رفتار مورد انتظار است.
    }

    return {
      running: true,
      participantCount: participants.length,
      participants,
      attendanceReliable: false,
    };
  }

  async end(providerMeetingId: string): Promise<void> {
    await this.call(`/${providerMeetingId}:endActiveConference`, { method: 'POST' });
  }

  async recording(_providerMeetingId: string): Promise<RecordingInfo> {
    // ضبط Meet نیازمند پلن مناسب Workspace و دسترسی جداگانه است و
    // لینک قابل دانلود مستقیم نمی‌دهد. تا وقتی مشتری واقعی نخواهد، نمی‌سازیم.
    return { state: 'none' };
  }

  async healthy(): Promise<boolean> {
    try {
      await this.cfg.getAccessToken();
      return true;
    } catch {
      return false;
    }
  }
}
