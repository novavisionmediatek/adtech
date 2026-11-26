<?php
declare(strict_types=1);

function mail_wrap(string $title, string $body): string {
    $app = e(cfg('app_name'));
    return '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:auto;border:1px solid #e4e4ef;border-radius:12px;overflow:hidden">' .
        '<div style="background:#130d2e;color:#fff;border-bottom:5px solid #6c63ff;padding:16px 22px"><b style="font-size:18px">' . $app . '</b><div style="font-size:12px;opacity:.8">' . e($title) . '</div></div>' .
        '<div style="padding:22px;color:#130d2e;font-size:14px;line-height:1.6">' . $body . '</div>' .
        '<div style="background:#f4f4f9;color:#636780;padding:12px 22px;font-size:11px">Automated message from ' . $app . '. Please do not reply.</div></div>';
}

/**
 * Stores every mail in the outbox; delivers through SMTP when configured.
 * $secrets (passwords) are delivered to the recipient but masked in the stored copy, except in the local demo
 * where nothing is actually sent and the log is the only way to read the mail.
 */
function send_mail(string $to, string $name, string $subject, string $html, ?int $school = null, array $secrets = []): int {
    $smtp = cfg('smtp', []); $live = !empty($smtp['host']);
    $stored = $html;
    if ($live || !cfg('demo_mode')) foreach ($secrets as $s) if ((string)$s !== '') $stored = str_replace(e((string)$s), '&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;', $stored);
    $id = ins('INSERT INTO outbox(school_id,to_email,to_name,subject,body,status) VALUES(?,?,?,?,?,?)', [$school, $to, $name, $subject, $stored, 'queued']);
    if (!$live) {
        run('UPDATE outbox SET status=?, error=? WHERE id=?', ['logged', 'Demo mode – SMTP not configured, stored only', $id]);
        $GLOBALS['mail_results'][] = 'logged';
        return $id;
    }
    try {
        smtp_send($smtp, $to, $name, $subject, $html);
        run('UPDATE outbox SET status=?, sent_at=? WHERE id=?', ['sent', date('Y-m-d H:i:s'), $id]);
        $GLOBALS['mail_results'][] = 'sent';
    } catch (Throwable $t) {
        run('UPDATE outbox SET status=?, error=? WHERE id=?', ['failed', mb_substr($t->getMessage(), 0, 480), $id]);
        $GLOBALS['mail_results'][] = 'failed';
    }
    return $id;
}
/** One honest sentence about what happened to the mails sent during this request. */
function mail_summary(): string {
    $r = array_count_values($GLOBALS['mail_results'] ?? []);
    if (!$r) return '';
    if (!empty($r['failed'])) return ' WARNING: ' . $r['failed'] . ' e-mail(s) could NOT be delivered (see the Email log) - share the login details yourself.';
    if (!empty($r['logged'])) return ' Note: e-mail is not set up yet, so the message is only saved in the Email log and was NOT delivered.';
    return ' Login details e-mailed.';
}

function smtp_send(array $c, string $to, string $toName, string $subject, string $html): void {
    $clean = fn(string $s) => str_replace(["\r", "\n"], ' ', $s);
    $to = $clean($to); $secure = $c['secure'] ?? 'tls';
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : '') . $c['host'] . ':' . $c['port'], $en, $es, 15);
    if (!$fp) throw new RuntimeException("SMTP connect failed: $es");
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) { $r = ''; while (($l = fgets($fp, 515)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $r; };
    $cmd = function (string $line, string $expect) use ($fp, $read) {
        if ($line !== '') fwrite($fp, $line . "\r\n");
        $r = $read(); if (strpos($r, $expect) !== 0) throw new RuntimeException('SMTP: ' . trim($r)); return $r;
    };
    $cmd('', '220'); $cmd('EHLO localhost', '250');
    if ($secure === 'tls') {
        $cmd('STARTTLS', '220');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('TLS negotiation failed (is openssl enabled?)');
        $cmd('EHLO localhost', '250');
    }
    if (($c['user'] ?? '') !== '') { $cmd('AUTH LOGIN', '334'); $cmd(base64_encode($c['user']), '334'); $cmd(base64_encode($c['pass']), '235'); }
    $cmd('MAIL FROM:<' . $c['from'] . '>', '250'); $cmd('RCPT TO:<' . $to . '>', '250'); $cmd('DATA', '354');
    $h = 'From: =?UTF-8?B?' . base64_encode($c['from_name']) . '?= <' . $c['from'] . ">\r\n" .
         'To: =?UTF-8?B?' . base64_encode($clean($toName)) . '?= <' . $to . ">\r\n" .
         'Subject: =?UTF-8?B?' . base64_encode($clean($subject)) . "?=\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
    fwrite($fp, $h . "\r\n" . chunk_split(base64_encode($html)) . "\r\n.\r\n");
    $r = $read(); if (strpos($r, '250') !== 0) throw new RuntimeException('SMTP: ' . trim($r));
    fwrite($fp, "QUIT\r\n"); fclose($fp);
}
