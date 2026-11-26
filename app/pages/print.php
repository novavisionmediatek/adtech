<?php
/* Printable documents: report card, fee receipt, certificate. One place for the access check. */
$u = require_login();
$seg = array_values(array_filter(explode('/', trim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/')), 'strlen'));
$type = $seg[1] ?? ''; $id = (int)($seg[2] ?? 0);
$deny = fn() => render_error(404, 'Document not found', 'The document does not exist or you do not have access.');
$can = function (array $st) use ($u): bool {
    return match ($u['role']) { 'super_admin' => true, 'school_admin' => (int)$st['school_id'] === sid(), 'student' => (int)$st['user_id'] === (int)$u['id'], 'parent' => (int)$st['parent_id'] === (int)$u['id'], default => false };
};
$student = fn(int $sid) => row('SELECT st.*, su.name, c.name cname, c.section, p.name pname FROM students st JOIN users su ON su.id=st.user_id JOIN classes c ON c.id=st.class_id LEFT JOIN users p ON p.id=st.parent_id WHERE st.id=?', [$sid]);
$sheet = function (string $title, array $school, string $body) use ($u): never {
    dash_header($title, '<button class="btn btn-sm btn-primary" data-print>&#128424; Print / Save as PDF</button>');
    echo '<div class="paper"><div class="paper-head"><h2>' . e($school['name']) . '</h2><p>' . e(implode(', ', array_filter([$school['address'], $school['city'], $school['state'], $school['pincode']]))) . '</p><p>' .
        e(implode('  |  ', array_filter([$school['board'] ? 'Board: ' . $school['board'] : '', $school['udise'] ? 'UDISE: ' . $school['udise'] : '', $school['phone'] ? 'Phone: ' . $school['phone'] : '']))) . '</p></div>' . $body . '</div>';
    dash_footer(); exit;
};
$facts = function (array $pairs): string { $o = '<table class="paper-facts">'; foreach ($pairs as $k => $v) $o .= '<tr><th>' . e($k) . '</th><td>' . e((string)$v === '' ? '-' : $v) . '</td></tr>'; return $o . '</table>'; };
$signs = fn(string ...$who) => '<div class="signs">' . implode('', array_map(fn($w) => '<span>' . e($w) . '</span>', $who)) . '</div>';

if ($type === 'report') {
    $exam = row('SELECT * FROM exams WHERE id=?', [(int)inp('exam')]); $st = $student((int)inp('student'));
    if (!$exam || !$st || (int)$exam['school_id'] !== (int)$st['school_id'] || !$can($st) || (in_array($u['role'], ['student', 'parent'], true) && !$exam['published'])) $deny();
    $class = exam_class_of((int)$exam['id'], (int)$st['id']) ?: (int)$st['class_id'];
    $papers = exam_papers((int)$exam['id'], $class); if (!$papers) $deny();
    $marks = []; foreach (q('SELECT m.* FROM exam_marks m JOIN exam_papers p ON p.id=m.paper_id WHERE p.exam_id=? AND m.student_id=?', [$exam['id'], $st['id']]) as $m) $marks[$m['paper_id']] = $m;
    $sc = exam_score($papers, $marks, (int)$exam['pass_pct']); $att = student_attendance_summary((int)$st['id']);
    $body = '<h3 class="paper-title">Report card &middot; ' . e($exam['name']) . ($exam['published'] ? '' : ' (not yet published)') . '</h3>' .
        $facts(['Student' => $st['name'], 'Class' => cls($papers[0]), 'Roll no.' => $st['roll_no'], 'Admission no.' => $st['admission_no'], 'Parent' => $st['pname'], 'Attendance' => $att['total'] ? $att['pct'] . '% (' . $att['present'] . ' of ' . $att['total'] . ' periods)' : '-']) .
        '<table class="paper-marks"><tr><th>Subject</th><th>Maximum</th><th>Marks</th><th>Grade</th></tr>';
    foreach ($papers as $p) { $m = $marks[$p['id']] ?? null; $body .= '<tr><td>' . e($p['sname']) . '</td><td>' . (int)$p['max_marks'] . '</td><td>' . (!$m ? '-' : ($m['absent'] ? 'Absent' : e(mark_text($m['marks'])))) . '</td><td>' . ($m && !$m['absent'] ? grade_of(pct((float)$m['marks'], (float)$p['max_marks'])) : '-') . '</td></tr>'; }
    $body .= '<tr class="total"><td>Total</td><td>' . $sc['max'] . '</td><td>' . e(mark_text($sc['total'])) . '</td><td>' . e($sc['grade']) . '</td></tr></table>' .
        '<p class="paper-result">Percentage: <b>' . ($sc['pct'] === null ? '-' : e($sc['pct']) . '%') . '</b> &nbsp; Result: <b>' . e($sc['result']) . '</b></p>' .
        '<p class="small muted">Grades: A1 91-100, A2 81-90, B1 71-80, B2 61-70, C1 51-60, C2 41-50, D 33-40, E below 33. Pass mark ' . (int)$exam['pass_pct'] . '% in each subject.</p>' . $signs('Class teacher', 'Parent', 'Principal');
    $sheet('Report card', row('SELECT * FROM schools WHERE id=?', [$st['school_id']]), $body);
}
if ($type === 'receipt') {
    $p = row('SELECT p.*, d.title, d.amount total, d.student_id FROM fee_payments p JOIN fee_dues d ON d.id=p.due_id WHERE p.id=?', [$id]); $st = $p ? $student((int)$p['student_id']) : null;
    if (!$p || !$st || !$can($st) || ($p['cancelled'] && $u['role'] !== 'school_admin')) $deny();
    $paid = (float)val('SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE due_id=? AND cancelled=0 AND id<=?', [$p['due_id'], $p['id']]);
    $body = '<h3 class="paper-title">Fee receipt' . ($p['cancelled'] ? ' &middot; CANCELLED' : '') . '</h3>' .
        $facts(['Receipt no.' => $p['receipt_no'], 'Date' => fmt_date($p['paid_on']), 'Student' => $st['name'], 'Class' => cls($st), 'Admission no.' => $st['admission_no'], 'Parent' => $st['pname']]) .
        '<table class="paper-marks"><tr><th>Particulars</th><th>Fee amount</th><th>Received now</th><th>Balance</th></tr><tr><td>' . e($p['title']) . '</td><td>' . money($p['total']) . '</td><td><b>' . money($p['amount']) . '</b></td><td>' . money((float)$p['total'] - $paid) . '</td></tr></table>' .
        '<p>Paid by: <b>' . e($p['mode']) . '</b>' . ($p['reference'] ? ' &middot; Reference: ' . e($p['reference']) : '') . '</p>' . $signs('Received by', 'School seal');
    $sheet('Fee receipt', row('SELECT * FROM schools WHERE id=?', [$st['school_id']]), $body);
}
if ($type === 'certificate') {
    $c = row('SELECT * FROM certificates WHERE id=?', [$id]); $st = $c ? $student((int)$c['student_id']) : null;
    if (!$c || !$st || !$can($st) || ($c['revoked'] && $u['role'] !== 'school_admin')) $deny();
    $d = json_decode((string)$c['details'], true) ?: []; $school = row('SELECT * FROM schools WHERE id=?', [$c['school_id']]);
    $g = fn(string $k) => e($d[$k] ?? ''); $child = ($d['gender'] ?? '') === 'F' ? 'daughter' : (($d['gender'] ?? '') === 'M' ? 'son' : 'ward');
    $intro = 'This is to certify that <b>' . $g('name') . '</b>, ' . $child . ' of <b>' . ($g('parent') ?: '-') . '</b>, admission number <b>' . $g('admission_no') . '</b>';
    $dob = !empty($d['dob']) ? ' The date of birth as per school records is <b>' . e(fmt_date($d['dob'], 'd F Y')) . '</b>.' : '';
    $text = match ($c['type']) {
        'bonafide' => "<p>$intro, is a bona fide student of this school studying in Class <b>{$g('class')}</b>.$dob</p>",
        'character' => "<p>$intro, has been a student of Class <b>{$g('class')}</b> in this school. To the best of our knowledge the conduct and character of the student has been <b>{$g('conduct')}</b>.$dob</p>",
        default => "<p>$intro, was a student of this school in Class <b>{$g('class')}</b> and left on <b>" . e(fmt_date($d['leaving_date'] ?? null, 'd F Y')) . "</b>. Reason for leaving: <b>{$g('reason')}</b>. Conduct: <b>{$g('conduct')}</b>.$dob</p>",
    };
    $body = '<h3 class="paper-title">' . e(CERT_TYPES[$c['type']][0] ?? 'Certificate') . ($c['revoked'] ? ' &middot; CANCELLED' : '') . '</h3><p class="paper-meta"><span>No. ' . e($c['serial_no']) . '</span><span>Date: ' . e(fmt_date($c['issued_on'], 'd F Y')) . '</span></p>' .
        '<div class="paper-text">' . $text . (!empty($d['purpose']) ? '<p>This certificate is issued for the purpose of: ' . $g('purpose') . '.</p>' : '') . (!empty($d['remarks']) ? '<p>Remarks: ' . $g('remarks') . '</p>' : '') . '</div>' . $signs('Prepared by', 'School seal', 'Principal');
    $sheet('Certificate', $school, $body);
}
$deny();
