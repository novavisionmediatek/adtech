<?php
/* Is this installation ready for real use? Run it on the live server after uploading. */
$checks = []; // [group, name, state ok|warn|bad, detail]
$add = function (string $g, string $n, string $state, string $detail) use (&$checks) { $checks[] = [$g, $n, $state, $detail]; };

$add('Server', 'PHP version', PHP_VERSION_ID >= 80100 ? 'ok' : 'bad', PHP_VERSION . (PHP_VERSION_ID >= 80100 ? '' : ' - version 8.1 or newer is needed'));
foreach (['openssl' => 'needed to send e-mail', 'mbstring' => 'needed for text', 'dom' => 'needed for the blog editor', 'zlib' => 'needed for backups and Excel', is_mysql() ? 'pdo_mysql' : 'pdo_sqlite' => 'needed for the database'] as $x => $why)
    $add('Server', "PHP extension $x", extension_loaded($x) ? 'ok' : 'bad', extension_loaded($x) ? 'Enabled' : "Missing - $why");
foreach (['data' => DATA_DIR, 'data/uploads' => UPLOAD_DIR] as $label => $dir) $add('Server', "Folder $label", is_writable($dir) ? 'ok' : 'bad', is_writable($dir) ? 'Can be written' : 'Not writable - change the folder permission');
$add('Server', 'Largest upload', 'ok', 'upload_max_filesize ' . ini_get('upload_max_filesize') . ', post_max_size ' . ini_get('post_max_size'));

$missing = [];
foreach (array_merge(['schools', 'users', 'faculty', 'classes', 'subjects', 'students', 'timetable', 'attendance', 'absence_alerts', 'homework', 'notices', 'outbox', 'posts', 'jobs', 'applications', 'messages', 'audit', 'lectures', 'lecture_items', 'homework_status', 'holidays', 'faculty_leave', 'substitutions', 'faculty_attendance'], extra_tables()) as $t)
    try { db()->query("SELECT 1 FROM $t LIMIT 1"); } catch (PDOException) { $missing[] = $t; }
$add('Database', 'Connection', 'ok', is_mysql() ? 'MySQL ' . db()->getAttribute(PDO::ATTR_SERVER_VERSION) : 'SQLite (file in data/)');
$add('Database', 'Tables', $missing ? 'bad' : 'ok', $missing ? 'Missing: ' . implode(', ', $missing) . ' - import database/vidyasetu_mysql.sql' : 'All tables are present');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$base = (string)cfg('base_url'); $host = (string)($_SERVER['HTTP_HOST'] ?? '');
$local = str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1');
$add('Security', 'Secure connection (HTTPS)', $https ? 'ok' : ($local ? 'warn' : 'bad'), $https ? 'On' : ($local ? 'Off - fine on this computer, required on the live site' : 'Off - switch on SSL in your hosting panel'));
$add('Security', 'Website address in config.php', parse_url($base, PHP_URL_HOST) === explode(':', $host)[0] ? 'ok' : 'bad', $base . (parse_url($base, PHP_URL_HOST) === explode(':', $host)[0] ? '' : ' does not match the address you are using - links in e-mails would be wrong'));
$add('Security', 'Demo mode', cfg('demo_mode') ? 'warn' : 'ok', cfg('demo_mode') ? 'On - demo logins are shown on the login page. Set demo_mode to false before real use.' : 'Off');
$weak = []; $demo = demo_password();
if ($demo !== '') foreach (q("SELECT username, password_hash FROM users WHERE active=1 AND (role IN ('super_admin','school_admin') OR username LIKE '%.test' OR username LIKE 'dps-%') ORDER BY role='super_admin' DESC, id LIMIT 60") as $u)
    if (password_verify($demo, $u['password_hash'])) { $weak[] = $u['username']; if (count($weak) >= 5) break; }
if ($demo === '') $add('Security', 'Demo passwords', 'warn', "Not checked - put the demo password in 'demo_password' in config.php to check for accounts that still use it");
else $add('Security', 'Demo passwords', $weak ? 'bad' : 'ok', $weak ? 'Still using the demo password: ' . implode(', ', $weak) . (count($weak) >= 5 ? ' ...' : '') . ' - change or disable these accounts' : 'No account that was checked uses the demo password');

$mail = !empty(cfg('smtp', [])['host']);
$sent = (int)val("SELECT COUNT(*) FROM outbox WHERE status='sent'");
$add('E-mail and alerts', 'E-mail (SMTP)', !$mail ? 'bad' : ($sent ? 'ok' : 'warn'), !$mail ? 'Not set up - nothing is delivered. Fill in the smtp block in config.php.' : ($sent ? "$sent e-mail(s) delivered" : 'Set up but not yet proven - send a test e-mail from Email log'));
foreach (['sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $k => $label) $add('E-mail and alerts', $label . ' gateway', gateway_ready($k) ? 'ok' : 'warn', gateway_ready($k) ? 'Set up' : 'Not set up (optional, usually a paid service)');

$last = backup_list()[0] ?? null;
$add('Backup', 'Latest database backup', $last && time() - $last['time'] < 3 * 86400 ? 'ok' : 'warn', $last ? date('d M Y, h:i A', $last['time']) : 'None yet - open the Backup page');

$count = array_count_values(array_column($checks, 2));
dash_header('System check');
?>
<div class="stats"><?= kpi('Passed', $count['ok'] ?? 0, '', 'green') ?><?= kpi('To review', $count['warn'] ?? 0, '', 'saffron') ?><?= kpi('Must fix', $count['bad'] ?? 0, '', 'red') ?></div>
<div class="card"><div class="card-head"><h3>Checks</h3><span><a class="btn btn-sm" href="/super/outbox">Send a test e-mail</a> <a class="btn btn-sm" href="/super/backup">Backup</a></span></div>
<div class="table-wrap"><table class="stack"><tr><th>Check</th><th>Area</th><th>Result</th><th>Details</th></tr>
<?php foreach ($checks as [$g, $n, $state, $d]): ?><tr><td class="who-cell"><b><?= e($n) ?></b></td><td data-label="Area"><?= e($g) ?></td><td data-label="Result"><?= badge(['ok' => 'OK', 'warn' => 'Review', 'bad' => 'Fix'][$state], ['ok' => 'green', 'warn' => 'amber', 'bad' => 'red'][$state]) ?></td><td data-label="Details" class="wide"><?= e($d) ?></td></tr><?php endforeach; ?></table></div></div>
<?php dash_footer(); ?>
