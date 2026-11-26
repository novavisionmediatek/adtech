<?php
declare(strict_types=1);
/* Request limits and "forgot password". (A 15-minute lock per account after 5 wrong passwords is in attempt_login().) */

const LOGIN_IP_LIMIT = 30;     // wrong logins from one network address in 15 minutes (many accounts)
const RESET_MINUTES = 60;

function throttle_count(string $bucket, int $seconds): int { return (int)val('SELECT COUNT(*) FROM throttle WHERE bucket=? AND at_time>?', [mb_substr($bucket, 0, 190), time() - $seconds]); }
function throttle_hit(string $bucket): void {
    run('INSERT INTO throttle(bucket,at_time) VALUES(?,?)', [mb_substr($bucket, 0, 190), time()]);
    if (random_int(1, 50) === 1) run('DELETE FROM throttle WHERE at_time<?', [time() - 86400]);
}

/**
 * E-mails a one-time reset link. Deliberately silent: the page shows the same message whether or not the account exists.
 * Students have no e-mail address, so their link goes to the parent.
 */
function reset_request(string $id): void {
    $id = strtolower(trim($id)); $ip = client_ip();
    if ($id === '' || throttle_count("forgot:ip:$ip", 3600) >= 10) return;
    throttle_hit("forgot:ip:$ip");
    $u = row('SELECT * FROM users WHERE (lower(email)=? OR lower(username)=?) AND active=1', [$id, $id]);
    if (!$u || throttle_count('forgot:user:' . $u['id'], 3600) >= 3) return;
    $to = ['name' => $u['name'], 'email' => (string)($u['email'] ?? '')]; $who = 'your account';
    if ($u['role'] === 'student') {
        $par = row('SELECT p.name, p.email FROM students s JOIN users p ON p.id=s.parent_id WHERE s.user_id=?', [$u['id']]);
        if (!$par) return;
        $to = $par; $who = 'the student account of ' . $u['name'] . ' (' . strtoupper((string)$u['username']) . ')';
    }
    if (empty($to['email'])) return;
    throttle_hit('forgot:user:' . $u['id']);
    $token = bin2hex(random_bytes(32));
    run('DELETE FROM password_resets WHERE user_id=?', [$u['id']]);
    run('INSERT INTO password_resets(user_id,token_hash,expires_at,created_at) VALUES(?,?,?,?)', [$u['id'], hash('sha256', $token), time() + RESET_MINUTES * 60, date('Y-m-d H:i:s')]);
    $link = rtrim((string)cfg('base_url'), '/') . '/reset?token=' . $token;
    $html = mail_wrap('Reset your password', '<p>Dear ' . e($to['name']) . ',</p><p>We received a request to reset the password of ' . e($who) . '.</p>' .
        '<p style="margin:18px 0"><a href="' . e($link) . '" style="background:#6c63ff;color:#fff;text-decoration:none;padding:11px 22px;border-radius:8px;font-weight:bold">Set a new password</a></p>' .
        '<p style="color:#636780;font-size:12.5px">This link works once and expires in ' . RESET_MINUTES . ' minutes. If you did not ask for this, ignore this e-mail - your password stays the same.</p>');
    send_mail($to['email'], $to['name'], 'Reset your ' . cfg('app_name') . ' password', $html, $u['school_id'] ? (int)$u['school_id'] : null, [$token]);
    run('INSERT INTO audit(user_id,school_id,action,detail,ip) VALUES(?,?,?,?,?)', [$u['id'], $u['school_id'], 'password_reset_requested', '', $ip]);
}
/** The user a reset token belongs to, or null when it is wrong, used or expired. */
function reset_lookup(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    return row('SELECT r.id reset_id, u.* FROM password_resets r JOIN users u ON u.id=r.user_id WHERE r.token_hash=? AND r.used=0 AND r.expires_at>=? AND u.active=1', [hash('sha256', $token), time()]);
}
function reset_complete(array $u, string $password): void {
    run('UPDATE password_resets SET used=1 WHERE user_id=?', [$u['id']]);
    run('UPDATE users SET password_hash=?, must_change=0, failed=0, locked_until=0 WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    run('INSERT INTO audit(user_id,school_id,action,detail,ip) VALUES(?,?,?,?,?)', [$u['id'], $u['school_id'], 'password_reset_by_link', '', client_ip()]);
}
/** Stored in the session: changes when the password changes, which signs out every other session. */
function password_mark(string $hash): string { return substr(hash('sha256', $hash), 0, 20); }
