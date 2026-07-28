/**
 * آداپتور BigBlueButton.
 *
 * BBB هر فراخوانی را با چک‌سام SHA-1 امضا می‌کند:
 *   checksum = SHA1(callName + queryString + sharedSecret)
 * کلید مشترک از `bbb-conf --secret` روی سرور BBB گرفته می‌شود.
 *
 * مرجع: https://docs.bigbluebutton.org/dev/api.html
 */

import { createHash, randomUUID } from 'node:crypto';
import { XMLParser } from 'fast-xml-parser';
import type {
  CreateMeetingInput, CreatedMeeting, JoinInput, MeetingProvider,
  MeetingState, Participant, RecordingInfo,
} from './provider.js';

interface BbbConfig {
  /** مثال: https://bbb.lingotalk.ir/bigbluebutton/api/ */
  endpoint: string;
  sharedSecret: string;
  /** وب‌هوکی که BBB رویداد پایان جلسه را به آن می‌فرستد */
  webhookUrl?: string;
  timeoutMs?: number;
}

const parser = new XMLParser({ ignoreAttributes: false, parseTagValue: true });

export class BigBlueButtonProvider implements MeetingProvider {
  readonly id = 'bbb' as const;
  readonly displayName = 'BigBlueButton';
  /** BBB شناسهٔ خودمان را در userID می‌پذیرد و برمی‌گرداند، پس تطبیق قطعی است */
  readonly attendanceReliable = true;
  readonly recordingRetrievable = true;

  constructor(private cfg: BbbConfig) {
    if (!cfg.endpoint.endsWith('/')) this.cfg.endpoint += '/';
  }

  private sign(call: string, params: Record<string, string>): string {
    const qs = new URLSearchParams(params).toString();
    const checksum = createHash('sha1')
      .update(call + qs + this.cfg.sharedSecret)
      .digest('hex');
    return `${this.cfg.endpoint}${call}?${qs}&checksum=${checksum}`;
  }

  private async call(call: string, params: Record<string, string>): Promise<any> {
    const url = this.sign(call, params);
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), this.cfg.timeoutMs ?? 12_000);
    try {
      const res = await fetch(url, { signal: ctrl.signal });
      if (!res.ok) throw new Error(`BBB ${call} → HTTP ${res.status}`);
      const xml = await res.text();
      const doc = parser.parse(xml)?.response;
      if (!doc) throw new Error(`BBB ${call} → پاسخ نامعتبر`);
      if (doc.returncode !== 'SUCCESS') {
        throw new Error(`BBB ${call} → ${doc.messageKey}: ${doc.message}`);
      }
      return doc;
    } finally {
      clearTimeout(t);
    }
  }

  async create(input: CreateMeetingInput): Promise<CreatedMeeting> {
    // meetingID از شناسهٔ جلسهٔ خودمان ساخته می‌شود تا فراخوانی دوباره،
    // اتاق تکراری نسازد (BBB با meetingID یکسان همان اتاق را برمی‌گرداند).
    const meetingID = `lt-${input.onlineSessionId}`;
    const attendeePW = randomUUID();
    const moderatorPW = randomUUID();

    const params: Record<string, string> = {
      name: input.title,
      meetingID,
      attendeePW,
      moderatorPW,
      record: input.record ? 'true' : 'false',
      autoStartRecording: 'false',       // مدرس خودش شروع می‌کند
      allowStartStopRecording: 'true',
      duration: String(input.durationMinutes + 15), // حاشیه برای تأخیر
      muteOnStart: input.muteOnStart ? 'true' : 'false',
      guestPolicy: input.lobby ? 'ASK_MODERATOR' : 'ALWAYS_ACCEPT',
      logoutURL: `https://app.lingotalk.ir/${input.instituteId}/teach`,
      // متادیتا برای تطبیق در وب‌هوک و کارگر ضبط
      'meta-lt-session': input.onlineSessionId,
      'meta-lt-institute': input.instituteId,
      'meta-lt-class': input.classId,
    };
    if (this.cfg.webhookUrl) params['meta-lt-webhook'] = this.cfg.webhookUrl;

    const doc = await this.call('create', params);

    // رمز مدیر جلسه فقط سمت سرور نگه داشته می‌شود و در لینک ورود مدرس می‌آید.
    const moderatorJoinUrl = await this.joinUrl({
      providerMeetingId: `${meetingID}:${moderatorPW}:${attendeePW}`,
      opaqueUserId: 'moderator',
      displayName: 'مدرس',
      role: 'moderator',
    });

    return {
      providerMeetingId: `${meetingID}:${moderatorPW}:${attendeePW}`,
      moderatorJoinUrl,
      raw: doc,
    };
  }

  async joinUrl(input: JoinInput): Promise<string> {
    const [meetingID, modPW, attPW] = input.providerMeetingId.split(':');
    return this.sign('join', {
      meetingID,
      fullName: input.displayName,
      password: input.role === 'moderator' ? modPW : attPW,
      // این همان چیزی است که Meet نمی‌دهد: شناسهٔ ما برمی‌گردد،
      // پس شرکت‌کننده را می‌توان قطعی به زبان‌آموز تطبیق داد.
      userID: input.opaqueUserId,
      redirect: 'true',
    });
  }

  async state(providerMeetingId: string): Promise<MeetingState> {
    const [meetingID] = providerMeetingId.split(':');
    try {
      const doc = await this.call('getMeetingInfo', { meetingID });
      const raw = doc.attendees?.attendee;
      const list: any[] = raw ? (Array.isArray(raw) ? raw : [raw]) : [];
      const participants: Participant[] = list.map((a) => ({
        providerUserId: String(a.userID),
        displayName: String(a.fullName),
        opaqueUserId: String(a.userID),
        isModerator: a.role === 'MODERATOR',
      }));
      return {
        running: doc.running === true || doc.running === 'true',
        startedAt: doc.startTime ? new Date(Number(doc.startTime)).toISOString() : undefined,
        participantCount: participants.length,
        participants,
        attendanceReliable: true,
      };
    } catch {
      // اتاق هنوز ساخته نشده یا تمام شده
      return { running: false, participantCount: 0, participants: [], attendanceReliable: true };
    }
  }

  async end(providerMeetingId: string): Promise<void> {
    const [meetingID, modPW] = providerMeetingId.split(':');
    await this.call('end', { meetingID, password: modPW });
  }

  async recording(providerMeetingId: string): Promise<RecordingInfo> {
    const [meetingID] = providerMeetingId.split(':');
    const doc = await this.call('getRecordings', { meetingID });

    const raw = doc.recordings?.recording;
    if (!raw) return { state: 'processing' };
    const rec = Array.isArray(raw) ? raw[0] : raw;

    if (rec.state === 'processing' || rec.state === 'processed') return { state: 'processing' };
    if (rec.state !== 'published') return { state: 'failed' };

    const formats = rec.playback?.format;
    const list = Array.isArray(formats) ? formats : [formats];
    // فرمت video مستقیم قابل دانلود است؛ presentation فقط قابل پخش در مرورگر.
    const video = list.find((f: any) => f?.type === 'video') ?? list[0];
    if (!video?.url) return { state: 'failed' };

    return {
      state: 'ready',
      downloadUrl: String(video.url),
      durationSeconds: Number(video.length ?? 0) * 60,
      sizeBytes: Number(rec.size ?? 0),
      contentType: 'video/mp4',
    };
  }

  async deleteRecording(providerMeetingId: string): Promise<void> {
    const [meetingID] = providerMeetingId.split(':');
    const doc = await this.call('getRecordings', { meetingID });
    const raw = doc.recordings?.recording;
    if (!raw) return;
    const rec = Array.isArray(raw) ? raw[0] : raw;
    if (rec?.recordID) await this.call('deleteRecordings', { recordID: String(rec.recordID) });
  }

  async healthy(): Promise<boolean> {
    try {
      await this.call('getMeetings', {});
      return true;
    } catch {
      return false;
    }
  }
}
