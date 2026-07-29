<?php
/**
 * کلاینت sms.ir
 *
 * از سرویس «ارسال وریفای» استفاده می‌کند:
 *   POST https://api.sms.ir/v1/send/verify
 *   هدرها: x-api-key, Content-Type: application/json, Accept: text/plain
 *   بدنه:  {"mobile":"09...","templateId":123,"parameters":[{"name":"CODE","value":"12345"}]}
 *   پاسخ:  {"status":1,"message":"موفق","data":{"messageId":...,"cost":...}}
 *
 * چرا سرویس وریفای و نه ارسال معمولی (بند P.4):
 * پیامک وریفای از خط خدماتی می‌رود، پس به دست کسی که «لغو تبلیغات» را
 * فعال کرده هم می‌رسد. اگر کد ورود را با خط تبلیغاتی بفرستید، برای بخشی
 * از کاربران هرگز نمی‌رسد و شما هم متوجه نمی‌شوید.
 */

declare(strict_types=1);

/*
 * محافظ دسترسی مستقیم.
 *
 * .htaccess فقط روی آپاچی کار می‌کند؛ اگر پلسک روی حالت «nginx only»
 * باشد نادیده گرفته می‌شود. این بررسی مستقل از وب‌سرور است.
 */
if (realpath(__FILE__) === realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    http_response_code(404);
    exit;
}


/**
 * @return array{sent:bool, messageId:?int, cost:?float, error:?string}
 */
function sms_send_verify(string $phone, string $code): array
{
    $c = cfg();

    // حالت توسعه: کد در لاگ می‌رود و پیامکی ارسال نمی‌شود
    if (!empty($c['sms_dry_run'])) {
        error_log("SMS DRY-RUN → {$phone} : {$code}");
        return ['sent' => true, 'messageId' => null, 'cost' => 0.0, 'error' => null];
    }

    if (empty($c['smsir_api_key']) || empty($c['smsir_template_id'])) {
        return ['sent' => false, 'messageId' => null, 'cost' => null,
                'error' => 'کلید یا شناسهٔ قالب sms.ir تنظیم نشده'];
    }

    $payload = json_encode([
        'mobile'     => $phone,
        'templateId' => (int)$c['smsir_template_id'],
        'parameters' => [
            // نام پارامتر باید دقیقاً با متغیر داخل قالب پنل sms.ir یکی باشد
            ['name' => $c['smsir_param_name'] ?? 'CODE', 'value' => $code],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.sms.ir/v1/send/verify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: text/plain',
            'x-api-key: ' . $c['smsir_api_key'],
        ],
    ]);

    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        error_log("sms.ir curl error: {$err}");
        return ['sent' => false, 'messageId' => null, 'cost' => null, 'error' => 'اتصال به sms.ir برقرار نشد'];
    }

    $res = json_decode((string)$raw, true);
    if (!is_array($res)) {
        error_log("sms.ir bad response ({$http}): {$raw}");
        return ['sent' => false, 'messageId' => null, 'cost' => null, 'error' => 'پاسخ نامعتبر از sms.ir'];
    }

    // status = 1 یعنی موفق
    if ((int)($res['status'] ?? 0) === 1) {
        return [
            'sent'      => true,
            'messageId' => isset($res['data']['messageId']) ? (int)$res['data']['messageId'] : null,
            'cost'      => isset($res['data']['cost']) ? (float)$res['data']['cost'] : null,
            'error'     => null,
        ];
    }

    $msg = (string)($res['message'] ?? 'خطای نامشخص');
    error_log("sms.ir failed ({$http}) status={$res['status']}: {$msg}");
    return ['sent' => false, 'messageId' => null, 'cost' => null, 'error' => $msg];
}
