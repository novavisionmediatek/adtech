<?php
declare(strict_types=1);

const FONT_LINK = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' .
    '<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700;800&display=swap" rel="stylesheet">';

/* ---------- small UI helpers ---------- */
function flash_html(): string {
    $o = '';
    foreach (flash() as [$t, $m]) { $o .= '<div class="alert alert-' . e($t) . '" role="alert">' . e($m) . '</div>'; }
    return $o;
}
function badge(string $text, string $kind = 'gray'): string { return '<span class="badge b-' . e($kind) . '">' . e($text) . '</span>'; }
function status_badge(string $s): string {
    return match ($s) { 'P' => badge('Present', 'green'), 'A' => badge('Absent', 'red'), 'L' => badge('Late', 'amber'),
        'active', 'sent' => badge($s, 'green'), 'pending', 'queued', 'logged' => badge($s, 'amber'), default => badge($s, 'red') };
}
function pct_badge(float $p, int $total = 1): string {
    if ($total === 0) return badge('No data');
    return badge($p . '%', $p >= (int)cfg('min_attendance') ? 'green' : 'red');
}
function bar(float $p): string {
    $c = $p >= (int)cfg('min_attendance') ? 'ok' : 'bad';
    return '<div class="bar"><span class="' . $c . '" style="width:' . max(2, min(100, $p)) . '%"></span></div>';
}
function kpi(string $label, $value, string $sub = '', string $tone = ''): string {
    return '<div class="stat ' . e($tone) . '"><div class="stat-v">' . e((string)$value) . '</div><div class="stat-l">' . e($label) . '</div>' . ($sub ? '<div class="stat-s">' . e($sub) . '</div>' : '') . '</div>';
}
/** Optional "set password" inputs used wherever one person creates an account for another. */
function password_fields(string $field = 'password', string $label = 'Password'): string {
    return '<div><label>' . e($label) . ' <span class="muted">(optional)</span></label><input type="password" name="' . e($field) . '" minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" title="At least 8 characters with letters and numbers" autocomplete="new-password" placeholder="Empty = auto-generate" data-pw></div>' .
        '<div><label>Confirm ' . e(strtolower($label)) . '</label><input type="password" name="' . e($field) . '2" autocomplete="new-password" placeholder="Type it again" data-pw data-pw-confirm="' . e($field) . '"></div>';
}
function password_options(): string {
    return '<div class="full pw-opts"><label><input type="checkbox" data-showpw> Show password</label><label><input type="checkbox" name="force_change" value="1" checked> Ask to change it at first login</label>' .
        '<span class="small muted">8+ characters with letters and numbers. A welcome e-mail with the login ID and password is sent to the user (a student\'s details go to the parent).</span></div>';
}
function empty_row(int $cols, string $msg = 'Nothing to show yet.'): string { return '<tr><td colspan="' . $cols . '" class="empty">' . e($msg) . '</td></tr>'; }
function confirm_attr(string $m): string { return ' data-confirm="' . e($m) . '"'; }

function render_error(int $code, string $title, string $msg): never {
    http_response_code($code);
    if (me()) { dash_header($title); echo '<div class="card center"><h2>' . e($title) . '</h2><p>' . e($msg) . '</p><a class="btn" href="' . url(ROLE_HOME[me()['role']]) . '">Go to dashboard</a></div>'; dash_footer(); }
    else { public_header($title); echo '<section class="section"><div class="container center"><h1>' . $code . '</h1><h2>' . e($title) . '</h2><p>' . e($msg) . '</p><a class="btn btn-primary" href="/">Back to home</a></div></section>'; public_footer(); }
    exit;
}

/* ---------- public site layout ---------- */
function public_header(string $title, string $desc = ''): void {
    i18n_start();
    $app = cfg('app_name'); $path = trim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'); $first = explode('/', $path)[0];
    $nav = ['' => 'Home', 'about' => 'About', 'features' => 'Features', 'compliance' => 'Compliance', 'blog' => 'Blog', 'careers' => 'Careers', 'contact' => 'Contact'];
    $u = me();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' .
        '<title>' . e($title) . ' | ' . e($app) . '</title><meta name="description" content="' . e($desc ?: cfg('tagline')) . '">' .
        FONT_LINK . '<link rel="stylesheet" href="/assets/css/app.css"></head><body class="public">';
    echo '<div class="topline"><div class="container"><span>' . (cfg('demo_mode') ? 'Demo portal &middot; all names and records are sample data' : e(cfg('tagline'))) . '</span>' .
        '<span><a href="/compliance">Security</a><a href="mailto:' . e(cfg('admin_email')) . '">' . e(cfg('admin_email')) . '</a></span></div></div>' .
        '<header class="site-header"><div class="container nav-wrap">' .
        '<a class="brand" href="/"><span class="brand-mark">V</span><span>' . e($app) . '<small>School Portal</small></span></a>' .
        '<input type="checkbox" id="navtoggle" class="navtoggle"><label for="navtoggle" class="burger" aria-label="Menu">&#9776;</label><nav class="main-nav">';
    foreach ($nav as $k => $l) { echo '<a href="/' . $k . '"' . ($first === $k ? ' class="active"' : '') . '>' . e($l) . '</a>'; }
    echo '<div class="nav-actions">';
    if ($u) { echo '<a class="btn btn-primary" href="' . url(ROLE_HOME[$u['role']]) . '">My dashboard</a>'; }
    else { echo '<a class="btn" href="/login">Login</a><a class="btn btn-primary" href="/signup">Register school</a>'; }
    echo lang_switch_link() . '</div></nav></div></header><main>' . flash_html();
}
function public_footer(): void {
    $app = e(cfg('app_name'));
    echo '</main><footer class="site-footer"><div class="periodbar" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div><div class="container foot-grid"><div><div class="brand light"><span class="brand-mark">V</span><span>' . $app . '</span></div>' .
        '<p>' . e(cfg('tagline')) . '. Built for schools, teachers, students and parents across India.</p></div>' .
        '<div><h4>Portal</h4><a href="/features">Features</a><a href="/compliance">Compliance &amp; Security</a><a href="/login">Login</a><a href="/signup">Register your school</a><a href="/admission">Apply for admission</a></div>' .
        '<div><h4>Company</h4><a href="/about">About</a><a href="/blog">Blog</a><a href="/careers">Careers</a><a href="/contact">Contact</a></div>' .
        '<div><h4>Legal</h4><a href="/privacy">Privacy &amp; Data Protection</a><a href="/privacy#terms">Terms of use</a></div></div>' .
        '<div class="container copy">&copy; ' . date('Y') . ' ' . $app . '. All rights reserved. Student data is processed only for school administration.</div></footer>' .
        '<script src="/assets/js/app.js"></script></body></html>';
}

/* ---------- dashboard layout ---------- */
/** Menu entries [url, label, icon]. An entry without url is a group heading. */
function nav_for(string $role): array {
    $g = fn(string $label) => ['', $label, ''];
    return match ($role) {
        'super_admin' => [['super', 'Overview', '&#9776;'], ['super/schools', 'Schools', '&#127979;'], ['super/users', 'All users', '&#128101;'], ['super/attendance', 'Attendance', '&#9989;'], ['super/reports', 'Government report', '&#128200;'],
            $g('Other'), ['super/blog', 'Blog manager', '&#9998;'], ['super/careers', 'Careers', '&#128188;'], ['super/messages', 'Contact messages', '&#9993;'], ['super/outbox', 'Email log', '&#128231;'], ['super/audit', 'Audit log', '&#128274;'], ['super/backup', 'Backup', '&#128193;'], ['super/system', 'System check', '&#128295;']],
        'school_admin' => [['admin', 'Overview', '&#9776;'],
            $g('Daily work'), ['admin/attendance', 'Attendance', '&#9989;'], ['admin/staff_attendance', 'Faculty attendance', '&#128339;'], ['admin/timetable', 'Timetable', '&#128197;'], ['admin/requests', 'Leave requests', '&#128196;'], ['admin/leave', 'Holidays & leave', '&#9728;'], ['admin/homework', 'Homework', '&#128221;'], ['admin/lectures', 'Lectures', '&#127909;'], ['admin/notices', 'Notices', '&#128226;'],
            $g('School'), ['admin/classes', 'Classes', '&#127979;'], ['admin/subjects', 'Subjects', '&#128214;'], ['admin/faculty', 'Faculty', '&#128104;'], ['admin/students', 'Students', '&#127891;'],
            $g('Exams & fees'), ['admin/exams', 'Exams & results', '&#128200;'], ['admin/fees', 'Fees', '&#128188;'],
            $g('Office'), ['admin/admissions', 'Admissions', '&#128273;'], ['admin/certificates', 'Certificates', '&#128220;'], ['admin/library', 'Library', '&#128218;'], ['admin/transport', 'Transport', '&#127970;'], ['admin/alerts', 'SMS & WhatsApp', '&#9993;'], ['admin/outbox', 'Email log', '&#128231;']],
        'faculty' => [['faculty', 'My day', '&#9776;'], ['faculty/attendance', 'Mark attendance', '&#9989;'], ['faculty/homework', 'Homework', '&#128221;'], ['faculty/marks', 'Exam marks', '&#9998;'], ['faculty/lectures', 'Lectures', '&#127909;'], ['faculty/timetable', 'My timetable', '&#128197;'], ['faculty/reports', 'Class reports', '&#128200;'], ['faculty/my_attendance', 'My attendance', '&#128339;'], ['faculty/leave', 'My leave', '&#128196;']],
        'student' => [['student', 'My dashboard', '&#9776;'], ['student/attendance', 'Attendance', '&#9989;'], ['student/homework', 'Homework', '&#128221;'], ['student/results', 'Results', '&#128200;'], ['student/lectures', 'Lectures', '&#127909;'], ['student/timetable', 'Timetable', '&#128197;'], ['student/fees', 'Fees', '&#128188;'], ['student/certificates', 'Certificates', '&#128220;'], ['student/campus', 'Library & bus', '&#128218;']],
        'parent' => [['parent', 'My children', '&#9776;'], ['parent/notices', 'Notices', '&#128226;'], ['parent/emails', 'Alerts received', '&#128231;'], ['parent/alerts', 'Alert settings', '&#128295;']],
        default => [],
    };
}
function dash_header(string $title, string $actions = ''): void {
    i18n_start();
    $u = me(); $app = e(cfg('app_name')); $path = trim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $waiting = $u['role'] === 'school_admin' ? pending_leave_count((int)$u['school_id']) : 0;
    $newAdm = $u['role'] === 'school_admin' ? (int)val("SELECT COUNT(*) FROM admissions WHERE school_id=? AND status='new'", [$u['school_id']]) : 0;
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' .
        '<title>' . e($title) . ' | ' . $app . '</title>' . FONT_LINK . '<link rel="stylesheet" href="/assets/css/app.css"></head><body class="dash">';
    echo '<input type="checkbox" id="sidetoggle" class="navtoggle"><aside class="sidebar"><a class="brand light" href="/"><span class="brand-mark">V</span><span>' . $app . '</span></a>';
    echo '<div class="who"><b>' . e($u['name']) . '</b><span>' . e(ROLE_LABEL[$u['role']]) . ($u['school_name'] ? ' &middot; ' . e($u['school_name']) : '') . '</span></div><nav>';
    foreach (nav_for($u['role']) as [$href, $label, $icon]) {
        if ($href === '') { echo '<span class="nav-group">' . e($label) . '</span>'; continue; }
        $active = $path === $href || ($href !== ROLE_HOME[$u['role']] && str_starts_with($path, $href)) ? ' class="active"' : '';
        $count = $href === 'admin/requests' ? $waiting : ($href === 'admin/admissions' ? $newAdm : 0);
        echo '<a href="' . url($href) . '"' . $active . '><i>' . ico($icon) . '</i>' . e($label) . ($count ? '<em class="nav-count">' . $count . '</em>' : '') . '</a>';
    }
    echo '</nav><div class="side-foot">' . lang_switch_link() . '<a href="/account/password">Change password</a><a href="/logout">Logout</a></div></aside>';
    echo '<div class="content"><header class="topbar"><label for="sidetoggle" class="burger" aria-label="Menu">&#9776;</label><h1>' . e($title) . '</h1><div class="top-actions">' . $actions .
        '<span class="muted">' . date('D, d M Y') . '</span></div></header><div class="page">' . flash_html();
}
function dash_footer(): void { echo '</div></div><script src="/assets/js/app.js"></script></body></html>'; }

function outbox_table(array $rows, bool $showSchool = false): string {
    $o = '<div class="table-wrap"><table><tr><th>When</th><th>To</th><th>Subject</th><th>Status</th></tr>';
    foreach ($rows as $r) {
        $o .= '<tr><td>' . e(fmt_date($r['created_at'], 'd M, h:i A')) . '</td><td>' . e($r['to_name']) . '<br><span class="muted small">' . e($r['to_email']) . '</span></td><td>' .
            '<details><summary>' . e($r['subject']) . '</summary><div class="mail-preview">' . $r['body'] . '</div></details></td><td>' . status_badge((string)$r['status']) .
            ($r['error'] ? '<br><span class="muted small">' . e($r['error']) . '</span>' : '') . '</td></tr>';
    }
    if (!$rows) $o .= empty_row(4, 'No e-mails yet.');
    return $o . '</table></div>';
}

/** Notices visible to the current user (school notices + platform notices). */
function notices_for(int $school, int $limit = 5): array {
    return q('SELECT * FROM notices WHERE school_id=? OR school_id IS NULL ORDER BY created_at DESC, id DESC LIMIT ' . (int)$limit, [$school]);
}
function notices_html(array $rows): string {
    if (!$rows) return '<p class="muted">No notices.</p>';
    $o = '<ul class="notice-list">';
    foreach ($rows as $n) { $o .= '<li><b>' . e($n['title']) . '</b>' . ($n['school_id'] === null ? ' ' . badge('Platform', 'blue') : '') . '<p>' . nl2br(e($n['body'])) . '</p><small>' . e(fmt_date($n['created_at'], 'd M Y, h:i A')) . '</small></li>'; }
    return $o . '</ul>';
}

/* ---------- reusable public-page blocks ---------- */
function section_head(string $kicker, string $title, string $sub = ''): string {
    return '<div class="sh"><div><span class="kicker">' . e($kicker) . '</span><h2 class="title">' . e($title) . '</h2></div>' . ($sub !== '' ? '<p class="sub">' . e($sub) . '</p>' : '') . '</div>';
}
/** $items = [[question, answerHtml], ...] (answers are trusted template HTML) */
function faq_html(array $items): string {
    $o = '<div class="faq">';
    foreach ($items as [$qq, $a]) $o .= '<details><summary>' . e($qq) . '</summary><div>' . $a . '</div></details>';
    return $o . '</div>';
}
function cta_band(string $title, string $text, string $btn1 = 'Register your school', string $url1 = '/signup', string $btn2 = 'Talk to us', string $url2 = '/contact'): string {
    return '<section class="cta-band"><div class="container"><div><h2>' . e($title) . '</h2><p>' . e($text) . '</p></div><div class="cta-actions"><a class="btn btn-primary btn-lg" href="' . e($url1) . '">' . e($btn1) . '</a><a class="btn btn-lg" href="' . e($url2) . '">' . e($btn2) . '</a></div></div></section>';
}
