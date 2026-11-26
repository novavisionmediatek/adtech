<?php
declare(strict_types=1);
/*
 * SMS and WhatsApp alerts. Only sent to parents who switched them on (consent).
 * Any provider with an HTTP API can be used - see the 'sms' and 'whatsapp' blocks in config.php.
 * Without a provider the message is only recorded in the log, never sent.
 */
const ALERT_CHANNELS = ['sms' => 'SMS', 'whatsapp' => 'WhatsApp'];

function user_prefs(int $userId): array { return row('SELECT * FROM user_prefs WHERE user_id=?', [$userId]) ?: ['user_id' => $userId, 'sms' => 0, 'whatsapp' => 0, 'lang' => 'en']; }
function save_prefs(int $userId, bool $sms, bool $whatsapp, string $lang): void {
    run('DELETE FROM user_prefs WHERE user_id=?', [$userId]);
    run('INSERT INTO user_prefs(user_id,sms,whatsapp,lang) VALUES(?,?,?,?)', [$userId, (int)$sms, (int)$whatsapp, $lang === 'hi' ? 'hi' : 'en']);
}
/** Indian mobile number with country code (91XXXXXXXXXX), or null when it does not look usable. */
function phone_number(string $raw): ?string {
    $d = preg_replace('/\D+/', '', $raw);
    if (strlen($d) === 11 && $d[0] === '0') $d = substr($d, 1);
    if (strlen($d) === 10 && $d[0] >= '6') return '91' . $d;
    return strlen($d) >= 11 && strlen($d) <= 15 ? $d : null;
}
function gateway_ready(string $channel): bool { $c = cfg($channel, []); return !empty($c['enabled']) && !empty($c['url']); }
/** Calls the provider. {phone} and {message} in the URL and body are replaced (escaped for JSON or form data). */
function gateway_send(array $c, string $phone, string $message): void {
    $headers = array_values((array)($c['headers'] ?? []));
    $json = (bool)preg_grep('#^content-type:\s*application/json#i', $headers);
    $method = strtoupper((string)($c['method'] ?? 'POST'));
    $url = strtr((string)$c['url'], ['{phone}' => rawurlencode($phone), '{message}' => rawurlencode($message)]);
    $esc = fn(string $v) => $json ? substr((string)json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1) : urlencode($v);
    $body = strtr((string)($c['body'] ?? ''), ['{phone}' => $esc($phone), '{message}' => $esc($message)]);
    if ($method !== 'GET' && !preg_grep('#^content-type:#i', $headers)) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $method === 'GET' ? '' : $body, 'timeout' => 8, 'ignore_errors' => true]]);
    $res = @file_get_contents($url, false, $ctx);
    $status = isset($http_response_header[0]) && preg_match('#\s(\d{3})#', $http_response_header[0], $m) ? (int)$m[1] : 0;
    if ($res === false || $status < 200 || $status >= 300) throw new RuntimeException($status ? "Provider answered $status: " . mb_substr(trim((string)$res), 0, 150) : 'Could not reach the provider.');
}
/** Sends $message (plus $hindi for parents who chose Hindi) on every channel the user switched on. */
function send_alert(int $userId, string $message, ?int $school, string $hindi = ''): void {
    $p = user_prefs($userId); if (!$p['sms'] && !$p['whatsapp']) return;
    if ($p['lang'] === 'hi' && $hindi !== '') $message = $hindi;
    $phone = phone_number((string)val('SELECT phone FROM users WHERE id=?', [$userId]));
    foreach (ALERT_CHANNELS as $ch => $label) {
        if (!$p[$ch]) continue;
        $status = 'logged'; $error = 'Provider not set up - not sent';
        if (!$phone) { $status = 'failed'; $error = 'No valid mobile number'; }
        elseif (gateway_ready($ch)) {
            try { gateway_send(cfg($ch), $phone, $message); $status = 'sent'; $error = null; }
            catch (Throwable $t) { $status = 'failed'; $error = mb_substr($t->getMessage(), 0, 250); }
        }
        run('INSERT INTO msg_log(school_id,channel,phone,message,status,error,created_at) VALUES(?,?,?,?,?,?,?)', [$school, $ch, $phone, mb_substr($message, 0, 690), $status, $error, date('Y-m-d H:i:s')]);
    }
}
