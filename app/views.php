<?php
declare(strict_types=1);
/* Shared widgets used by both student and parent dashboards. */

function view_attendance(int $studentId): string {
    $from = date('Y-m-d', strtotime('-30 day'));
    $sum = student_attendance_summary($studentId);
    $o = '<div class="stats">' . kpi('Overall attendance', $sum['pct'] . '%', $sum['present'] . ' of ' . $sum['total'] . ' periods', $sum['total'] && $sum['pct'] < (int)cfg('min_attendance') ? 'red' : 'green') .
        kpi('Absent periods', $sum['total'] - $sum['present']) . '</div>';
    if ($sum['total'] && $sum['pct'] < (int)cfg('min_attendance')) {
        $o .= '<div class="alert alert-warn" style="width:auto">Attendance is below the required ' . (int)cfg('min_attendance') . '%. Please attend regularly.</div>';
    }
    $o .= '<div class="grid g2"><div class="card"><h3>Subject-wise attendance</h3><div class="table-wrap"><table><tr><th>Subject</th><th>Present</th><th>Total</th><th>%</th><th style="width:110px"></th></tr>';
    foreach ($sum['rows'] as $r) { $p = pct((float)$r['present'], (float)$r['total']); $o .= '<tr><td>' . e($r['subject']) . '</td><td>' . (int)$r['present'] . '</td><td>' . (int)$r['total'] . '</td><td>' . pct_badge($p) . '</td><td>' . bar($p) . '</td></tr>'; }
    if (!$sum['rows']) $o .= empty_row(5, 'No attendance recorded yet.');
    $o .= '</table></div></div>';

    $days = q("SELECT a.date, SUM(a.status IN ('P','L')) pres, SUM(a.status='A') absn, COUNT(*) total, GROUP_CONCAT(CASE WHEN a.status='A' THEN a.period END) ap
              FROM attendance a WHERE a.student_id=? GROUP BY a.date ORDER BY a.date DESC LIMIT 20", [$studentId]);
    $o .= '<div class="card"><h3>Date-wise (recent days)</h3><div class="table-wrap"><table><tr><th>Date</th><th>Periods present</th><th>Absent in</th></tr>';
    foreach ($days as $d) $o .= '<tr><td>' . e(fmt_date($d['date'], 'D, d M')) . '</td><td>' . (int)$d['pres'] . ' / ' . (int)$d['total'] . '</td><td>' . ($d['absn'] ? badge('P' . str_replace(',', ', P', (string)$d['ap']), 'red') : badge('Full day', 'green')) . '</td></tr>';
    if (!$days) $o .= empty_row(3);
    return $o . '</table></div></div></div>';
}

function view_attendance_detail(int $studentId, string $subject = ''): string {
    $p = [$studentId]; $w = '';
    if ($subject !== '') { $w = ' AND a.subject_id=?'; $p[] = (int)$subject; }
    [$rows, $pager] = paginate("SELECT a.date, a.period, a.status, sub.name sname, u.name fname FROM attendance a JOIN subjects sub ON sub.id=a.subject_id JOIN users u ON u.id=a.faculty_id
               WHERE a.student_id=? $w ORDER BY a.date DESC, a.period, a.id", $p, 20);
    $o = '<div class="table-wrap"><table><tr><th>Date</th><th>Period</th><th>Subject</th><th>Teacher</th><th>Status</th></tr>';
    foreach ($rows as $r) $o .= '<tr><td>' . e(fmt_date($r['date'], 'D, d M Y')) . '</td><td>P' . (int)$r['period'] . '</td><td>' . e($r['sname']) . '</td><td>' . e($r['fname']) . '</td><td>' . status_badge($r['status']) . '</td></tr>';
    if (!$rows) $o .= empty_row(5);
    return $o . '</table></div>' . $pager;
}

/** $studentId > 0 adds that student's own completion status and the teacher's note. */
/** $paged = true shows the full list with page numbers; otherwise only the newest $limit items. */
function view_homework(int $classId, int $limit = 50, int $studentId = 0, bool $paged = false, bool $canSubmit = false): string {
    $mine = [];
    if ($studentId) foreach (q('SELECT homework_id, status, remark FROM homework_status WHERE student_id=?', [$studentId]) as $r) $mine[$r['homework_id']] = $r;
    $sql = 'SELECT h.*, sub.name sname, u.name fname FROM homework h JOIN subjects sub ON sub.id=h.subject_id JOIN users u ON u.id=h.faculty_id WHERE h.class_id=? ORDER BY h.due_date IS NULL, h.due_date DESC, h.id DESC';
    $pager = '';
    if ($paged) [$rows, $pager] = paginate($sql, [$classId], 10); else $rows = q($sql . ' LIMIT ' . $limit, [$classId]);
    $o = ''; $subs = $studentId ? submissions_for(array_column($rows, 'id'), $studentId) : [];
    foreach ($rows as $h) {
        $sub = $subs[$h['id'] . '-' . $studentId] ?? null; $checked = in_array($mine[$h['id']]['status'] ?? '', ['done', 'late'], true);
        $over = $h['due_date'] && $h['due_date'] < date('Y-m-d');
        $o .= '<div class="slot"><div class="info"><b>' . e($h['title']) . '</b> ' . badge($h['sname'], 'blue') . ($studentId ? ' ' . hw_badge($mine[$h['id']]['status'] ?? null) : '') . '<div>' . nl2br(e((string)$h['description'])) . '</div>' .
            (!empty($mine[$h['id']]['remark']) ? '<div class="hw-note"><b>Teacher\'s note:</b> ' . e($mine[$h['id']]['remark']) . '</div>' : '') .
            '<span class="muted small">By ' . e($h['fname']) . ' &middot; posted ' . e(fmt_date($h['created_at'])) . '</span>' .
            ($sub ? '<div class="sub-box">' . submission_html($sub, $h['due_date']) . '</div>' : '') . ($canSubmit && !$checked ? submission_form((int)$h['id'], (bool)$sub) : '') . '</div><div class="right">' .
            ($h['due_date'] ? ($over ? badge('Due ' . fmt_date($h['due_date']), 'gray') : badge('Due ' . fmt_date($h['due_date']), 'amber')) : '') .
            ($h['file_path'] ? '<br><a class="btn btn-sm" style="margin-top:6px" href="/file/homework/' . (int)$h['id'] . '">Download</a>' : '') . '</div></div>';
    }
    return ($o ?: '<p class="muted">No homework yet.</p>') . $pager;
}

/** Lecture library for a class: video/audio play in the page, PDFs and other files open/download, links open in a new tab. */
function view_lectures(int $classId, int $subjectId = 0): string {
    [$rows, $pager] = paginate('SELECT l.*, sub.name sname, u.name fname FROM lectures l JOIN subjects sub ON sub.id=l.subject_id JOIN users u ON u.id=l.faculty_id WHERE l.class_id=?' .
        ($subjectId ? ' AND l.subject_id=?' : '') . ' ORDER BY COALESCE(l.lecture_date, substr(l.created_at,1,10)) DESC, l.id DESC', $subjectId ? [$classId, $subjectId] : [$classId], 10);
    $o = ''; $items = lecture_items(array_column($rows, 'id'));
    foreach ($rows as $l) {
        $media = ''; $btn = '';
        foreach ($items[$l['id']] ?? [] as $i) {
            if ($i['kind'] === 'link') { $btn .= '<a class="btn btn-sm btn-accent" target="_blank" rel="noopener noreferrer" href="' . e($i['url']) . '">' . e(preg_replace('#^https?://(www\.)?#', '', mb_strimwidth((string)$i['url'], 0, 38, '...'))) . ' &#8599;</a> '; continue; }
            $ext = strtolower(pathinfo((string)$i['file_name'], PATHINFO_EXTENSION)); $src = '/file/lecture/' . (int)$i['id'];
            if (in_array($ext, ['mp4', 'webm', 'ogg'], true)) $media .= '<div class="lec-cap">' . e($i['file_name']) . ' &middot; <a href="' . $src . '?dl=1">Download</a></div><video class="lec-media" controls preload="metadata" src="' . $src . '"></video>';
            elseif (in_array($ext, ['mp3', 'wav', 'm4a'], true)) $media .= '<div class="lec-cap">' . e($i['file_name']) . ' &middot; <a href="' . $src . '?dl=1">Download</a></div><audio class="lec-media" controls preload="none" src="' . $src . '"></audio>';
            else $btn .= '<a class="btn btn-sm btn-primary" target="_blank" rel="noopener" href="' . $src . '">' . e($i['file_name']) . '</a><a class="btn btn-sm" href="' . $src . '?dl=1" title="Download">&#8681;</a> ';
        }
        $d = $l['lecture_date'] ?: substr((string)$l['created_at'], 0, 10);
        $o .= '<div class="lecture"><div class="lec-head"><div><b>' . e($l['title']) . '</b> ' . badge($l['sname'], 'blue') . '</div><span class="muted small">' . e(fmt_date($d, 'D, d M Y')) . '</span></div>' .
            ($l['description'] ? '<p>' . nl2br(e((string)$l['description'])) . '</p>' : '') . $media .
            '<div class="lec-foot"><span class="muted small">By ' . e($l['fname']) . '</span><span class="lec-btns">' . $btn . '</span></div></div>';
    }
    return ($o ?: '<p class="muted">No lectures uploaded yet.</p>') . $pager;
}
function lecture_filter(int $classId, int $current): string {
    $subs = q('SELECT DISTINCT s.id, s.name FROM lectures l JOIN subjects s ON s.id=l.subject_id WHERE l.class_id=? ORDER BY s.name', [$classId]);
    if (!$subs) return '';
    $o = '<div class="tabs"><a href="?"' . (!$current ? ' class="active"' : '') . '>All subjects</a>';
    foreach ($subs as $s) $o .= '<a href="?subject=' . (int)$s['id'] . '"' . ($current === (int)$s['id'] ? ' class="active"' : '') . '>' . e($s['name']) . '</a>';
    return $o . '</div>';
}

function view_timetable(int $classId): string {
    $cell = []; $maxp = 1;
    foreach (q('SELECT t.day, t.period, sub.name sname, u.name fname FROM timetable t JOIN subjects sub ON sub.id=t.subject_id JOIN users u ON u.id=t.faculty_id WHERE t.class_id=?', [$classId]) as $r) {
        $cell[$r['day']][$r['period']] = $r; $maxp = max($maxp, (int)$r['period']);
    }
    $o = '<div class="table-wrap"><table class="tt"><tr><th style="width:90px">Day</th>';
    for ($p = 1; $p <= $maxp; $p++) $o .= '<th>P' . $p . '<br><span class="muted" style="text-transform:none;font-weight:400">' . e(period_time($p)) . '</span></th>';
    $o .= '</tr>';
    for ($d = 1; $d <= 6; $d++) {
        $o .= '<tr><th>' . e(DAYS[$d]) . '</th>';
        for ($p = 1; $p <= $maxp; $p++) { $x = $cell[$d][$p] ?? null; $o .= '<td>' . ($x ? '<div class="cell"><b>' . e($x['sname']) . '</b><small>' . e($x['fname']) . '</small></div>' : '-') . '</td>'; }
        $o .= '</tr>';
    }
    return $o . '</table></div>';
}

function my_student(): array {
    $st = row('SELECT st.*, c.name cname, c.section, u.name FROM students st JOIN classes c ON c.id=st.class_id JOIN users u ON u.id=st.user_id WHERE st.user_id=?', [me()['id']]);
    if (!$st) render_error(404, 'Student profile missing', 'No student record is linked to this account.');
    return $st;
}
function my_children(): array {
    return q('SELECT st.*, c.name cname, c.section, u.name FROM students st JOIN classes c ON c.id=st.class_id JOIN users u ON u.id=st.user_id WHERE st.parent_id=? AND st.active=1 ORDER BY u.name', [me()['id']]);
}
