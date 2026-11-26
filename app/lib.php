<?php
declare(strict_types=1);

/* ------------------------------ basics ------------------------------ */
function cfg(string $k, $d = null) { return $GLOBALS['config'][$k] ?? $d; }
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $p = ''): string { return '/' . ltrim($p, '/'); }
function redirect(string $p): never {
    $to = url($p);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && parse_url($to, PHP_URL_PATH) === parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH)) { // keep the list page after a form post
        parse_str((string)parse_url($to, PHP_URL_QUERY), $have);
        $keep = array_diff_key(array_filter($_GET, fn($v, $k) => preg_match('/^pg\d?s?$/', (string)$k) && is_string($v), ARRAY_FILTER_USE_BOTH), $have);
        if ($keep) $to .= (str_contains($to, '?') ? '&' : '?') . http_build_query($keep);
    }
    header('Location: ' . $to); exit;
}
function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function post(string $k, string $d = ''): string { $v = $_POST[$k] ?? $d; return is_array($v) ? $d : trim((string)$v); }
function inp(string $k, string $d = ''): string { $v = $_GET[$k] ?? $d; return is_array($v) ? $d : trim((string)$v); }
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? ''; }

function flash(?string $msg = null, string $type = 'ok'): array {
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return []; }
    $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">'; }
function verify_csrf(): void {
    if (is_post() && !hash_equals(csrf(), (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(419); exit('Session expired or invalid request. Please go back, refresh and try again.');
    }
}
function fmt_date(?string $d, string $f = 'd M Y'): string { return $d ? date($f, strtotime($d)) : '—'; }
function valid_date(string $d): bool { $x = DateTime::createFromFormat('Y-m-d', $d); return $x && $x->format('Y-m-d') === $d; }
function pct(float $a, float $b): float { return $b > 0 ? round($a * 100 / $b, 1) : 0.0; }
function slugify(string $s): string { $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-')); return $s ?: 'post-' . time(); }

/* ------------------------------ database ----------------------------- */
function db_driver(): string { return strtolower((string)(cfg('db')['driver'] ?? 'sqlite')) === 'mysql' ? 'mysql' : 'sqlite'; }
function is_mysql(): bool { return db_driver() === 'mysql'; }
/** "INSERT IGNORE" / "INSERT OR IGNORE" depending on the database. */
function sql_insert_ignore(): string { return is_mysql() ? 'INSERT IGNORE' : 'INSERT OR IGNORE'; }
/** Upsert attendance row (unique on student_id,date,period). */
function sql_attendance_upsert(): string {
    $cols = 'INSERT INTO attendance(school_id,class_id,subject_id,faculty_id,student_id,date,period,status,marked_at) VALUES(?,?,?,?,?,?,?,?,?)';
    return is_mysql()
        ? $cols . ' ON DUPLICATE KEY UPDATE status=VALUES(status), subject_id=VALUES(subject_id), faculty_id=VALUES(faculty_id), marked_at=VALUES(marked_at)'
        : $cols . ' ON CONFLICT(student_id,date,period) DO UPDATE SET status=excluded.status, subject_id=excluded.subject_id, faculty_id=excluded.faculty_id, marked_at=excluded.marked_at';
}
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (is_mysql()) { // MySQL / MariaDB: schema comes from database/vidyasetu_mysql.sql (import it once)
        $c = cfg('db');
        $pdo = new PDO('mysql:host=' . ($c['host'] ?? '127.0.0.1') . ';port=' . (int)($c['port'] ?? 3306) . ';dbname=' . ($c['name'] ?? 'vidyasetu') . ';charset=utf8mb4', $c['user'] ?? 'root', $c['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET SESSION sql_mode=REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''), time_zone='+05:30'");
        ensure_homework_status($pdo, true);
        ensure_leave_tables($pdo, true);
        ensure_staff_attendance($pdo, true);
        ensure_extra_tables($pdo, true);
        return $pdo;
    }
    $fresh = !file_exists(DB_FILE);
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
    if ($fresh) { require_once APP . '/install.php'; install_database($pdo); }
    ensure_lectures($pdo);
    ensure_homework_status($pdo, false);
    ensure_leave_tables($pdo, false);
    ensure_staff_attendance($pdo, false);
    ensure_extra_tables($pdo, false);
    return $pdo;
}
/** Lectures table (added after first release, so created on demand for existing databases too). */
function ensure_lectures(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS lectures(id INTEGER PRIMARY KEY AUTOINCREMENT, school_id INTEGER NOT NULL, class_id INTEGER NOT NULL, subject_id INTEGER NOT NULL,
      faculty_id INTEGER NOT NULL, title TEXT NOT NULL, description TEXT, lecture_date TEXT, link_url TEXT, file_name TEXT, file_path TEXT,
      created_at TEXT DEFAULT (datetime('now','localtime')));
      CREATE INDEX IF NOT EXISTS lec_cls ON lectures(class_id, created_at)");
    // A lecture can have many files and many links -> lecture_items
    $pdo->exec("CREATE TABLE IF NOT EXISTS lecture_items(id INTEGER PRIMARY KEY AUTOINCREMENT, lecture_id INTEGER NOT NULL REFERENCES lectures(id) ON DELETE CASCADE,
      kind TEXT NOT NULL, file_name TEXT, file_path TEXT, url TEXT);
      CREATE INDEX IF NOT EXISTS lec_items ON lecture_items(lecture_id)");
    // one-time move of the old single file/link columns into items
    $pdo->exec("INSERT INTO lecture_items(lecture_id,kind,file_name,file_path) SELECT id,'file',file_name,file_path FROM lectures WHERE file_path IS NOT NULL AND file_path<>'';
      INSERT INTO lecture_items(lecture_id,kind,url) SELECT id,'link',link_url FROM lectures WHERE link_url IS NOT NULL AND link_url<>'';
      UPDATE lectures SET file_path=NULL, file_name=NULL, link_url=NULL WHERE file_path IS NOT NULL OR link_url IS NOT NULL");
    if (!is_dir(UPLOAD_DIR . '/lectures')) @mkdir(UPLOAD_DIR . '/lectures', 0775, true);
    $has = $pdo->query('SELECT 1 FROM lectures LIMIT 1')->fetchColumn();
    if (!$has && cfg('demo_mode')) { // a few sample lectures so the page is not empty in the demo
        $ins = $pdo->prepare('INSERT INTO lectures(school_id,class_id,subject_id,faculty_id,title,description,lecture_date) VALUES(?,?,?,?,?,?,?)');
        $it = $pdo->prepare("INSERT INTO lecture_items(lecture_id,kind,url) VALUES(?,'link',?)");
        $n = 0;
        foreach ($pdo->query('SELECT t.school_id,t.class_id,t.subject_id,t.faculty_id,s.name sname FROM timetable t JOIN subjects s ON s.id=t.subject_id GROUP BY t.class_id,t.subject_id ORDER BY t.class_id, t.subject_id')->fetchAll() as $r) {
            if ($n++ >= 12) break;
            $ins->execute([$r['school_id'], $r['class_id'], $r['subject_id'], $r['faculty_id'], $r['sname'] . ' - Chapter 1 revision', 'Recorded revision lecture. Watch before the next class and note down your doubts.', date('Y-m-d', strtotime('-' . ($n % 6) . ' days'))]);
            $it->execute([(int)$pdo->lastInsertId(), 'https://ncert.nic.in/textbook.php']);
        }
    }
}
/** Save several uploaded files from one <input type=file multiple name="field[]">. All-or-nothing: on any error, files already stored are removed. */
function save_uploads(string $field, string $dir, array $exts, int $max): array {
    $out = []; $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f['name'])) return [];
    try {
        foreach ($f['name'] as $i => $orig) {
            if ($orig === '' || $f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            if ($f['error'][$i] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload of "' . $orig . '" failed (code ' . $f['error'][$i] . ').');
            if ($f['size'][$i] > $max) throw new RuntimeException('"' . $orig . '" is too large (max ' . round($max / 1048576) . ' MB each).');
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, $exts, true)) throw new RuntimeException('"' . $orig . '": file type not allowed. Allowed: ' . implode(', ', $exts));
            $name = bin2hex(random_bytes(12)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'][$i], UPLOAD_DIR . "/$dir/$name")) throw new RuntimeException('Could not store "' . $orig . '".');
            $out[] = ['path' => "$dir/$name", 'name' => preg_replace('/[^\w.\- ]+/', '_', basename($orig))];
        }
    } catch (RuntimeException $x) { foreach ($out as $o) @unlink(UPLOAD_DIR . '/' . $o['path']); throw $x; }
    return $out;
}
function delete_lecture(array $l): void {
    foreach (q("SELECT file_path FROM lecture_items WHERE lecture_id=? AND kind='file'", [$l['id']]) as $i) @unlink(UPLOAD_DIR . '/' . $i['file_path']);
    run('DELETE FROM lecture_items WHERE lecture_id=?', [$l['id']]); run('DELETE FROM lectures WHERE id=?', [$l['id']]);
}
/** Items of many lectures at once, keyed by lecture id. */
function lecture_items(array $ids): array {
    if (!$ids) return [];
    $by = [];
    foreach (q('SELECT * FROM lecture_items WHERE lecture_id IN (' . implode(',', array_map('intval', $ids)) . ') ORDER BY id') as $i) $by[$i['lecture_id']][] = $i;
    return $by;
}
function lecture_items_links(array $items): string {
    $o = '';
    foreach ($items as $i) $o .= $i['kind'] === 'file'
        ? '<a href="/file/lecture/' . (int)$i['id'] . '" target="_blank">' . e($i['file_name']) . '</a><br>'
        : '<a href="' . e($i['url']) . '" target="_blank" rel="noopener noreferrer">' . e(mb_strimwidth(preg_replace('#^https?://#', '', (string)$i['url']), 0, 40, '...')) . ' &#8599;</a><br>';
    return $o ?: '-';
}
function q(string $sql, array $p = []): array { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }
function row(string $sql, array $p = []): ?array { $s = db()->prepare($sql); $s->execute($p); $r = $s->fetch(); return $r ?: null; }
function val(string $sql, array $p = []) { $s = db()->prepare($sql); $s->execute($p); $r = $s->fetchColumn(); return $r === false ? null : $r; }
function run(string $sql, array $p = []): int { $s = db()->prepare($sql); $s->execute($p); return $s->rowCount(); }
function ins(string $sql, array $p = []): int { run($sql, $p); return (int)db()->lastInsertId(); }

function audit(string $action, string $detail = ''): void {
    $u = me();
    run('INSERT INTO audit(user_id,school_id,action,detail,ip) VALUES(?,?,?,?,?)',
        [$u['id'] ?? null, $u['school_id'] ?? null, $action, $detail, client_ip()]);
}

/* -------------------------------- auth ------------------------------- */
const ROLE_HOME = ['super_admin' => 'super', 'school_admin' => 'admin', 'faculty' => 'faculty', 'student' => 'student', 'parent' => 'parent'];
const ROLE_LABEL = ['super_admin' => 'Super Admin', 'school_admin' => 'School Admin', 'faculty' => 'Faculty', 'student' => 'Student', 'parent' => 'Parent'];

function me(): ?array {
    static $u = false;
    if ($u === false) {
        $u = isset($_SESSION['uid']) ? row(
            'SELECT u.*, s.name school_name, s.status school_status, s.code school_code
             FROM users u LEFT JOIN schools s ON s.id=u.school_id WHERE u.id=? AND u.active=1', [$_SESSION['uid']]) : null;
        // a changed or reset password signs out other devices
        if ($u && isset($_SESSION['pwm']) && !hash_equals((string)$_SESSION['pwm'], password_mark($u['password_hash']))) $u = null;
        if ($u) $_SESSION['pwm'] ??= password_mark($u['password_hash']);
        if (!$u) unset($_SESSION['uid'], $_SESSION['pwm']);
    }
    return $u;
}
function sid(): int { return (int)(me()['school_id'] ?? 0); }

function require_login(): array {
    $u = me();
    if (!$u) { flash('Please log in to continue.', 'warn'); redirect('login'); }
    if ($u['role'] !== 'super_admin' && $u['school_status'] !== 'active') {
        session_destroy(); session_start();
        flash('Your school account is not active yet. It is under verification by the platform administrator.', 'warn');
        redirect('login');
    }
    if ($u['must_change'] && !str_starts_with(trim($_SERVER['REQUEST_URI'], '/'), 'account/password')) {
        flash('For security, please set a new password before continuing.', 'warn'); redirect('account/password');
    }
    return $u;
}
function require_role(string ...$roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) { http_response_code(403); render_error(403, 'Access denied', 'You do not have permission to open this page.'); }
    return $u;
}

function attempt_login(string $id, string $pass): ?array {
    $id = strtolower(trim($id)); $ipKey = 'login:' . client_ip();
    if (throttle_count($ipKey, 900) >= LOGIN_IP_LIMIT) { flash('Too many failed logins from this connection. Please wait 15 minutes.', 'err'); return null; }
    $u = row('SELECT * FROM users WHERE (lower(email)=? OR lower(username)=?) AND active=1', [$id, $id]);
    if (!$u) { throttle_hit($ipKey); password_verify($pass, '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234'); return null; }
    if ((int)$u['locked_until'] > time()) { flash('Too many failed attempts. Account locked for a few minutes.', 'err'); return null; }
    if (!password_verify($pass, $u['password_hash'])) {
        throttle_hit($ipKey);
        $f = (int)$u['failed'] + 1;
        run('UPDATE users SET failed=?, locked_until=? WHERE id=?', [$f, $f >= 5 ? time() + 900 : 0, $u['id']]);
        run('INSERT INTO audit(user_id,school_id,action,detail,ip) VALUES(?,?,?,?,?)', [$u['id'], $u['school_id'], 'login_failed', 'attempt ' . $f, client_ip()]);
        return null;
    }
    run('UPDATE users SET failed=0, locked_until=0, last_login=? WHERE id=?', [date('Y-m-d H:i:s'), $u['id']]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['pwm'] = password_mark($u['password_hash']);
    run('INSERT INTO audit(user_id,school_id,action,detail,ip) VALUES(?,?,?,?,?)', [$u['id'], $u['school_id'], 'login', '', client_ip()]);
    return $u;
}

/** Password of the sample accounts: 'demo_password' in config.php, otherwise the random one made at first install. */
function demo_password(): string {
    $p = (string)cfg('demo_password', ''); $f = DATA_DIR . '/demo_password.txt';
    return $p !== '' ? $p : (is_file($f) ? trim((string)file_get_contents($f)) : '');
}
function password_error(string $p): ?string {
    if (strlen($p) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'Password must contain letters and numbers.';
    return null;
}
function temp_password(): string {
    $c = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'; $p = '';
    for ($i = 0; $i < 8; $i++) $p .= $c[random_int(0, strlen($c) - 1)];
    return $p . random_int(10, 99);
}

/**
 * Password for a new account: the one typed by the person creating it, or a random temporary one.
 * Returns [password, isCustom, mustChangeOnFirstLogin]. The confirm field ($field.'2') is checked when a form sends it.
 */
function new_password(array $d, string $field = 'password', string $who = 'Password'): array {
    $p = (string)($d[$field] ?? ''); $c = (string)($d[$field . '2'] ?? ''); $form = array_key_exists($field . '2', $d);
    if ($p === '' && $c === '') return [temp_password(), false, 1];
    if ($err = password_error($p)) throw new RuntimeException("$who - $err");
    if ($form && $p !== $c) throw new RuntimeException("$who - the two passwords do not match.");
    return [$p, true, $form && empty($d['force_change']) ? 0 : 1];
}

/* --------------------------- file handling --------------------------- */
function save_upload(string $field, string $dir, array $exts, int $max): ?array {
    if (empty($_FILES[$field]['name'])) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('File upload failed (code ' . $f['error'] . ').');
    if ($f['size'] > $max) throw new RuntimeException('File is too large (max ' . round($max / 1048576) . ' MB).');
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $exts, true)) throw new RuntimeException('File type not allowed. Allowed: ' . implode(', ', $exts));
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . "/$dir/$name")) throw new RuntimeException('Could not store the file.');
    return ['path' => "$dir/$name", 'name' => preg_replace('/[^\w.\- ]+/', '_', basename($f['name']))];
}

/* ---------------------- domain helpers (multi-use) ------------------- */
function class_label(array $c): string { return $c['name'] . ($c['section'] ? '-' . $c['section'] : ''); }
function cls(array $r): string { return $r['cname'] . (!empty($r['section']) ? '-' . $r['section'] : ''); }
function school_classes(int $school): array { return q('SELECT * FROM classes WHERE school_id=? ORDER BY (name+0), name, section', [$school]); }
function school_subjects(int $school): array { return q('SELECT * FROM subjects WHERE school_id=? ORDER BY name', [$school]); }
function school_faculty(int $school): array {
    return q('SELECT u.*, f.employee_id, f.designation, f.qualification FROM users u LEFT JOIN faculty f ON f.user_id=u.id WHERE u.school_id=? AND u.role=\'faculty\' ORDER BY u.name', [$school]);
}
function period_time(int $n): string { $p = cfg('periods')[$n] ?? null; return $p ? $p[0] . '–' . $p[1] : ''; }
const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

/** Create a school + its admin. Returns school id. */
function register_school(array $s, array $admin, string $status): int {
    $pdo = db(); $pdo->beginTransaction();
    try {
        $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $s['name']), 0, 3)) ?: 'SCH';
        $base = $code; $i = 1;
        while (val('SELECT 1 FROM schools WHERE code=?', [$code])) { $code = $base . (++$i); }
        $id = ins('INSERT INTO schools(name,code,udise,board,address,city,state,pincode,phone,email,principal,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
            [$s['name'], $code, $s['udise'] ?? '', $s['board'] ?? '', $s['address'] ?? '', $s['city'] ?? '', $s['state'] ?? '', $s['pincode'] ?? '', $s['phone'] ?? '', $admin['email'], $s['principal'] ?? '', $status]);
        ins('INSERT INTO users(school_id,role,name,email,username,phone,password_hash) VALUES(?,?,?,?,?,?,?)',
            [$id, 'school_admin', $admin['name'], strtolower($admin['email']), strtolower($admin['email']), $admin['phone'] ?? '', password_hash($admin['password'], PASSWORD_DEFAULT)]);
        $pdo->commit(); return $id;
    } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
}

/** Login URL, user ID and password as a small table, used inside account e-mails. */
function credential_table(string $loginId, string $pass): string {
    return '<table cellpadding="8" style="background:#f4f4f9;border-radius:8px;margin:6px 0"><tr><td>Login page</td><td><b><a href="' . e(cfg('base_url')) . '/login">' . e(cfg('base_url')) . '/login</a></b></td></tr>' .
        '<tr><td>User ID</td><td><b>' . e($loginId) . '</b></td></tr><tr><td>Password</td><td><b style="font-family:Consolas,monospace;font-size:15px">' . e($pass) . '</b></td></tr></table>';
}
function mail_button(): string {
    return '<p style="margin:18px 0"><a href="' . e(cfg('base_url')) . '/login" style="background:#6c63ff;color:#fff;text-decoration:none;padding:11px 22px;border-radius:8px;font-weight:bold">Log in now</a></p>';
}
function safety_note(bool $mustChange): string {
    return '<p style="color:#636780;font-size:12.5px">' . ($mustChange ? 'You will be asked to set a new password when you first log in. ' : 'You can change your password any time after logging in. ') .
        'Keep this e-mail private and never share your password. If you did not expect this message, please contact your school.</p>';
}
/**
 * Welcome message with login details, sent whenever an account is created.
 * $extra is shown before the login table, $after below it; $secrets are further passwords to mask in the e-mail log.
 */
function welcome_mail(array $user, string $loginId, string $pass, ?int $school, string $extra = '', bool $mustChange = true, string $after = '', array $secrets = []): void {
    if (empty($user['email'])) return;
    $tips = [
        'school_admin' => ['Add classes, subjects, faculty and students', 'Build the weekly timetable', 'Check attendance reports and publish notices'],
        'faculty' => ['Mark attendance for each of your periods', 'Upload homework and lectures', 'See your timetable and class reports'],
        'parent' => ['See your child\'s attendance and timetable', 'Get an e-mail whenever your child is marked absent', 'View homework, lectures and school notices'],
        'student' => ['Check your attendance', 'Download homework and watch lectures', 'See your weekly timetable'],
    ][$user['role'] ?? ''] ?? [];
    $schoolName = $school ? (string)val('SELECT name FROM schools WHERE id=?', [$school]) : '';
    $html = mail_wrap('Welcome to ' . cfg('app_name'),
        '<p>Dear ' . e($user['name']) . ',</p><p><b>Welcome to ' . e(cfg('app_name')) . ($schoolName ? ' - ' . e($schoolName) : '') . '!</b> Your account has been created. We are glad to have you with us.</p>' . $extra .
        '<p style="margin-bottom:4px"><b>Your login details</b></p>' . credential_table($loginId, $pass) . mail_button() .
        ($tips ? '<p style="margin-bottom:4px"><b>What you can do</b></p><ul style="margin-top:0"><li>' . implode('</li><li>', array_map('e', $tips)) . '</li></ul>' : '') . $after . safety_note($mustChange));
    send_mail($user['email'], $user['name'], 'Welcome to ' . cfg('app_name') . ' - your login details', $html, $school, array_merge([$pass], $secrets));
}
/** New password after a reset: to the user's own e-mail, or to the parent for a student (students have no e-mail). */
function reset_mail(array $u, string $pass): void {
    $to = ['name' => $u['name'], 'email' => $u['email'] ?? ''];
    $intro = 'Your password has been reset.';
    if ($u['role'] === 'student') {
        $par = row('SELECT p.name, p.email FROM students s JOIN users p ON p.id=s.parent_id WHERE s.user_id=?', [$u['id']]);
        if (!$par) return;
        $to = $par; $intro = 'The password of your child ' . $u['name'] . ' has been reset.';
    }
    if (empty($to['email'])) return;
    $html = mail_wrap('Password reset', '<p>Dear ' . e($to['name']) . ',</p><p>' . e($intro) . '</p>' . credential_table((string)($u['role'] === 'student' ? strtoupper($u['username']) : ($u['email'] ?: $u['username'])), $pass) . mail_button() . safety_note(true));
    send_mail($to['email'], $to['name'], 'Your ' . cfg('app_name') . ' password was reset', $html, $u['school_id'] ? (int)$u['school_id'] : null, [$pass]);
}

/** Returns the temporary password, or null when a password was chosen by the person adding the account. */
function create_faculty(int $school, array $d): ?string {
    $name = trim($d['name'] ?? ''); $email = strtolower(trim($d['email'] ?? ''));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Faculty name and a valid e-mail are required.');
    if (val('SELECT 1 FROM users WHERE lower(email)=? OR lower(username)=?', [$email, $email])) throw new RuntimeException("E-mail $email is already registered.");
    [$pw, $custom, $must] = new_password($d);
    $uid = ins('INSERT INTO users(school_id,role,name,email,username,phone,password_hash,must_change) VALUES(?,?,?,?,?,?,?,?)',
        [$school, 'faculty', $name, $email, $email, trim($d['phone'] ?? ''), password_hash($pw, PASSWORD_DEFAULT), $must]);
    run('INSERT INTO faculty(user_id,school_id,employee_id,designation,qualification) VALUES(?,?,?,?,?)',
        [$uid, $school, trim($d['employee_id'] ?? ''), trim($d['designation'] ?? ''), trim($d['qualification'] ?? '')]);
    welcome_mail(['name' => $name, 'email' => $email, 'role' => 'faculty'], $email, $pw, $school, '', (bool)$must);
    return $custom ? null : $pw;
}

/**
 * Creates student (+ parent if needed). Returns [studentLogin, studentPw|null, parentPw|null, parentIsNew].
 * A null password means it was chosen by hand (or, for the parent, that an existing account was linked).
 */
function create_student(int $school, array $d): array {
    $name = trim($d['name'] ?? ''); $adm = preg_replace('/\s+/', '', trim($d['admission_no'] ?? ''));
    $class = (int)($d['class_id'] ?? 0); $pemail = strtolower(trim($d['parent_email'] ?? ''));
    if ($name === '' || $adm === '' || !$class) throw new RuntimeException('Student name, admission number and class are required.');
    if (!val('SELECT 1 FROM classes WHERE id=? AND school_id=?', [$class, $school])) throw new RuntimeException('Invalid class.');
    if (!filter_var($pemail, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('A valid parent e-mail is required (absence alerts are sent here).');
    if (trim($d['parent_name'] ?? '') === '') throw new RuntimeException('Parent name is required.');
    $code = (string)val('SELECT code FROM schools WHERE id=?', [$school]);
    $login = strtoupper($code) . '-' . strtoupper($adm);
    if (val('SELECT 1 FROM users WHERE lower(username)=?', [strtolower($login)])) throw new RuntimeException("Admission no. $adm already exists ($login).");
    $dob = trim($d['dob'] ?? ''); if ($dob !== '' && !valid_date($dob)) throw new RuntimeException('Date of birth must be YYYY-MM-DD.');
    $gender = in_array($d['gender'] ?? '', ['M', 'F', 'O'], true) ? $d['gender'] : '';

    [$spw, $sCustom, $sMust] = new_password($d, 'password', 'Student password');

    $pdo = db(); $pdo->beginTransaction();
    try {
        $par = row('SELECT * FROM users WHERE lower(email)=?', [$pemail]); $ppw = null; $pCustom = false; $pMust = 1;
        if ($par) {
            if ($par['role'] !== 'parent' || (int)$par['school_id'] !== $school) throw new RuntimeException("E-mail $pemail belongs to a different account type/school.");
            $pid = (int)$par['id'];
        } else {
            [$ppw, $pCustom, $pMust] = new_password($d, 'parent_password', 'Parent password');
            $pid = ins('INSERT INTO users(school_id,role,name,email,username,phone,password_hash,must_change) VALUES(?,?,?,?,?,?,?,?)',
                [$school, 'parent', trim($d['parent_name']), $pemail, $pemail, trim($d['parent_phone'] ?? ''), password_hash($ppw, PASSWORD_DEFAULT), $pMust]);
        }
        $uid = ins('INSERT INTO users(school_id,role,name,username,password_hash,must_change) VALUES(?,?,?,?,?,?)',
            [$school, 'student', $name, strtolower($login), password_hash($spw, PASSWORD_DEFAULT), $sMust]);
        run('INSERT INTO students(school_id,user_id,class_id,admission_no,roll_no,dob,gender,parent_id,address) VALUES(?,?,?,?,?,?,?,?,?)',
            [$school, $uid, $class, $adm, (int)($d['roll_no'] ?? 0), $dob !== '' ? $dob : null, $gender, $pid, trim($d['address'] ?? '')]);
        $pdo->commit();
    } catch (Throwable $t) { $pdo->rollBack(); throw $t; }

    // Students have no e-mail address, so their login details go to the parent.
    $child = '<p style="margin-bottom:4px"><b>Student login for ' . e($name) . '</b> (Admission No. ' . e($adm) . ')</p>' . credential_table($login, $spw);
    if ($ppw !== null) {
        welcome_mail(['name' => trim($d['parent_name']), 'email' => $pemail, 'role' => 'parent'], $pemail, $ppw, $school,
            '<p>Your child <b>' . e($name) . '</b> has been registered and linked to this parent account.</p>', (bool)$pMust, $child, [$spw]);
    } else {
        $html = mail_wrap('New student account', '<p>Dear ' . e($par['name']) . ',</p><p><b>' . e($name) . '</b> has been registered and linked to your existing parent account. Your own password is unchanged.</p>' .
            $child . mail_button() . safety_note((bool)$sMust));
        send_mail($pemail, $par['name'], 'Welcome ' . $name . ' - student login details', $html, $school, [$spw]);
    }
    return [$login, $sCustom ? null : $spw, $pCustom ? null : $ppw, $ppw !== null];
}

/** Send absence alert to the parent (respects alert mode + de-duplication). */
function notify_absence(array $st, array $slot, string $date, string $teacher): void {
    $key = cfg('absence_alert_mode') === 'daily' ? 0 : (int)$slot['period'];
    if (val('SELECT 1 FROM absence_alerts WHERE student_id=? AND date=? AND period_key=?', [$st['id'], $date, $key])) return;
    $par = row('SELECT name,email FROM users WHERE id=?', [$st['parent_id']]);
    if (!$par || empty($par['email'])) return;
    $school = (string)val('SELECT name FROM schools WHERE id=?', [$st['school_id']]);
    $html = mail_wrap('Absence alert', '<p>Dear ' . e($par['name']) . ',</p>' .
        '<p>Your child <b>' . e($st['name']) . '</b> (Class ' . e($slot['cname'] . ($slot['section'] ? '-' . $slot['section'] : '')) . ') was marked <b style="color:#c0392b">ABSENT</b> on <b>' . e(fmt_date($date, 'D, d M Y')) . '</b>' .
        ' in <b>Period ' . (int)$slot['period'] . ' – ' . e($slot['sname']) . '</b> (' . e(period_time((int)$slot['period'])) . ') by ' . e($teacher) . '.</p>' .
        '<p>If this is unexpected, please contact the school office. Regular attendance (minimum ' . (int)cfg('min_attendance') . '%) is important for your child\'s progress.</p>' .
        '<p>Regards,<br>' . e($school) . '</p>');
    send_mail($par['email'], $par['name'], 'Absence alert: ' . $st['name'] . ' – ' . fmt_date($date), $html, (int)$st['school_id']);
    send_alert((int)$st['parent_id'], "$school: {$st['name']} was marked ABSENT on " . fmt_date($date) . ', period ' . (int)$slot['period'] . " ({$slot['sname']}).", (int)$st['school_id'],
        "$school: {$st['name']} " . fmt_date($date) . ' को पीरियड ' . (int)$slot['period'] . " ({$slot['sname']}) में अनुपस्थित रहे।");
    run(sql_insert_ignore() . ' INTO absence_alerts(student_id,date,period_key) VALUES(?,?,?)', [$st['id'], $date, $key]);
}

/** Attendance summary rows for a student (optionally restricted by date range). */
function student_attendance_summary(int $studentId, string $from = '', string $to = ''): array {
    $p = [$studentId]; $w = '';
    if ($from !== '') { $w .= ' AND a.date>=?'; $p[] = $from; }
    if ($to !== '') { $w .= ' AND a.date<=?'; $p[] = $to; }
    $rows = q("SELECT sub.name subject, COUNT(*) total, SUM(a.status IN ('P','L')) present, SUM(a.status='A') absent
               FROM attendance a JOIN subjects sub ON sub.id=a.subject_id WHERE a.student_id=? $w GROUP BY sub.id ORDER BY sub.name", $p);
    $t = array_sum(array_column($rows, 'total')); $pr = array_sum(array_column($rows, 'present'));
    return ['rows' => $rows, 'total' => (int)$t, 'present' => (int)$pr, 'pct' => pct((float)$pr, (float)$t)];
}

function csv_out(string $filename, array $header, array $rows): never {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, $header);
    foreach ($rows as $r) fputcsv($o, $r);
    exit;
}

/* ------------------- rich-text (blog editor) sanitising ------------------- */
/** Whitelist-based HTML cleaner for editor content. Only basic formatting, links and our own uploaded images survive. */
function sanitize_html(string $html): string {
    $html = trim($html); if ($html === '') return '';
    $dom = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors(); libxml_use_internal_errors($prev);
    $root = $dom->getElementsByTagName('div')->item(0);
    $out = new DOMDocument('1.0', 'UTF-8'); $box = $out->createElement('div'); $out->appendChild($box);
    if ($root) foreach ($root->childNodes as $c) html_clean_node($c, $out, $box);
    $res = ''; foreach ($box->childNodes as $c) $res .= $out->saveHTML($c);
    $res = preg_replace_callback('/&#(\d+);/', fn($m) => (int)$m[1] > 127 ? mb_chr((int)$m[1], 'UTF-8') : $m[0], $res);
    return trim($res);
}
function html_clean_node(DOMNode $n, DOMDocument $out, DOMNode $parent): void {
    static $allow = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'ul', 'ol', 'li', 'strong', 'em', 'u', 's', 'sub', 'sup', 'span', 'a', 'img', 'br', 'hr', 'code', 'pre'];
    static $map = ['b' => 'strong', 'i' => 'em', 'strike' => 's', 'del' => 's', 'div' => 'p', 'font' => 'span'];
    static $drop = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'link', 'meta', 'noscript', 'head', 'title', 'template', 'video', 'audio', 'canvas', 'frame', 'frameset'];
    if ($n->nodeType === XML_TEXT_NODE || $n->nodeType === XML_CDATA_SECTION_NODE) { $parent->appendChild($out->createTextNode((string)$n->nodeValue)); return; }
    if ($n->nodeType !== XML_ELEMENT_NODE) return;
    $tag = strtolower($n->nodeName);
    if (in_array($tag, $drop, true)) return;
    $tag = $map[$tag] ?? $tag;
    if (!in_array($tag, $allow, true)) { foreach ($n->childNodes as $c) html_clean_node($c, $out, $parent); return; } // unknown tag: keep its text
    /** @var DOMElement $n */
    $el = $out->createElement($tag);
    if ($tag === 'a') {
        $href = trim((string)$n->getAttribute('href')); $test = preg_replace('/[\x00-\x20\x7f]+/', '', $href);
        if ($href === '' || !preg_match('#^(https?://|mailto:|/(?!/)|\#)#i', $test)) { foreach ($n->childNodes as $c) html_clean_node($c, $out, $parent); return; }
        $el->setAttribute('href', $href);
        if (preg_match('#^https?://#i', $test)) { $el->setAttribute('target', '_blank'); $el->setAttribute('rel', 'noopener noreferrer nofollow'); }
    } elseif ($tag === 'img') {
        $src = trim((string)$n->getAttribute('src'));
        if (!preg_match('#^(/blogimg/[a-f0-9]{24}\.(jpe?g|png|gif|webp)|https://[^\s"\'<>]+)$#i', $src)) return;
        $el->setAttribute('src', $src); $el->setAttribute('alt', mb_substr((string)$n->getAttribute('alt'), 0, 200)); $el->setAttribute('loading', 'lazy');
    } elseif ($tag === 'span') { // only a text colour survives (from <font color> or style="color:...")
        $col = trim((string)$n->getAttribute('color'));
        if ($col === '' && preg_match('/(?<![-\w])color:\s*([^;]+)/i', (string)$n->getAttribute('style'), $m)) $col = trim($m[1]);
        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $col, $m)) $col = sprintf('#%02x%02x%02x', min(255, (int)$m[1]), min(255, (int)$m[2]), min(255, (int)$m[3]));
        if (!preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $col)) { foreach ($n->childNodes as $c) html_clean_node($c, $out, $parent); return; }
        $el->setAttribute('style', 'color:' . strtolower($col));
    } elseif (in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'li'], true) && preg_match('/text-align:\s*(left|center|right|justify)/i', (string)$n->getAttribute('style'), $m)) {
        $el->setAttribute('style', 'text-align:' . strtolower($m[1]));
    }
    if (!in_array($tag, ['br', 'hr', 'img'], true)) foreach ($n->childNodes as $c) html_clean_node($c, $out, $el);
    $parent->appendChild($el);
}
/** Post body as safe HTML. Old posts were plain text (blank line = paragraph); new ones are editor HTML. */
function post_body_html(string $body): string {
    $body = trim($body);
    if ($body === '') return '';
    if (!preg_match('#<(p|h[1-6]|ul|ol|div|br|img|a|strong|em|u|blockquote|hr)[\s>/]#i', $body)) {
        $o = ''; foreach (preg_split('/\n{2,}/', $body) as $para) $o .= '<p>' . nl2br(e($para)) . '</p>'; return $o;
    }
    return sanitize_html($body);
}
function html_has_content(string $html): bool { return trim(strip_tags($html, '<img><hr>')) !== '' || str_contains($html, '<img'); }
