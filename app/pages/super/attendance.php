<?php
// Platform-wide view (read only) of student and faculty attendance, school by school.
$today = date('Y-m-d');
$schools = q("SELECT id, name, status FROM schools WHERE status IN ('active','suspended') ORDER BY (status='active') DESC, name");
$sidSel = (int)inp('school', (string)($schools[0]['id'] ?? 0)); $school = null;
foreach ($schools as $x) if ((int)$x['id'] === $sidSel) $school = $x;
$tab = inp('tab') === 'faculty' ? 'faculty' : 'students';
$month = inp('month', substr($today, 0, 7)); if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $month > substr($today, 0, 7)) $month = substr($today, 0, 7);
$from = "$month-01"; $to = date('Y-m-t', strtotime($from)); $cid = (int)inp('class');

if (inp('export')) {
    require_once APP . '/xlsx.php'; set_time_limit(0);
    $what = inp('export');
    if ($what === 'platform') export_or_back(platform_attendance_sheets(), 'attendance_all-schools', 'super/attendance', 'all schools');
    if (!$school) render_error(404, 'Not found', 'School not found.');
    $slug = slugify($school['name']);
    if ($what === 'faculty') {
        $fid = (int)inp('faculty');
        if ($fid && !val("SELECT 1 FROM users WHERE id=? AND school_id=? AND role='faculty'", [$fid, $sidSel])) render_error(404, 'Not found', 'Faculty not found.');
        export_or_back(staff_report_sheets($sidSel, $fid ?: null), "faculty_attendance_$slug", "super/attendance?school=$sidSel&tab=faculty", $school['name'] . ' faculty');
    }
    $classes = school_classes($sidSel);
    if ($cid) { $classes = array_values(array_filter($classes, fn($c) => (int)$c['id'] === $cid)); if (!$classes) render_error(404, 'Not found', 'Class not found.'); }
    export_or_back(attendance_report_sheets($sidSel, $classes), "student_attendance_$slug" . ($cid ? '_' . class_label($classes[0]) : ''), "super/attendance?school=$sidSel", $school['name'] . ' students');
}
$link = fn(array $p) => '?' . e(http_build_query(array_merge(['school' => $sidSel, 'month' => $month, 'tab' => $tab], $p)));
dash_header('Attendance', '<a class="btn btn-sm" href="?export=platform">&#8681; All schools summary</a>');
if (!$school) { echo '<div class="card muted center">No active school yet.</div>'; dash_footer(); return; }
?>
<div class="card"><form method="get" class="inline-form"><input type="hidden" name="tab" value="<?= $tab ?>">
  <div><label>School</label><select name="school" data-autosubmit><?php foreach ($schools as $x) echo '<option value="' . (int)$x['id'] . '"' . ((int)$x['id'] === $sidSel ? ' selected' : '') . '>' . e($x['name']) . ($x['status'] !== 'active' ? ' (' . e($x['status']) . ')' : '') . '</option>'; ?></select></div>
  <div><label>Month</label><input type="month" name="month" value="<?= e($month) ?>" max="<?= substr($today, 0, 7) ?>" data-autosubmit></div><div style="flex:0"><button class="btn">Show</button></div>
  <div class="push"><a class="btn btn-primary" href="<?= $link(['export' => $tab]) ?>">&#8681; Download <?= $tab === 'faculty' ? 'faculty' : 'student' ?> attendance (Excel)</a></div></form></div>
<div class="tabs"><a href="<?= $link(['tab' => 'students']) ?>"<?= $tab === 'students' ? ' class="active"' : '' ?>>Student attendance</a><a href="<?= $link(['tab' => 'faculty']) ?>"<?= $tab === 'faculty' ? ' class="active"' : '' ?>>Faculty attendance</a></div>
<?php if ($tab === 'students'):
    $classes = q("SELECT c.id, c.name cname, c.section, COUNT(a.id) total, COALESCE(SUM(a.status='P'),0) p, COALESCE(SUM(a.status='L'),0) l, COALESCE(SUM(a.status='A'),0) ab,
        (SELECT COUNT(*) FROM students st WHERE st.class_id=c.id AND st.active=1) n FROM classes c LEFT JOIN attendance a ON a.class_id=c.id AND a.date BETWEEN ? AND ?
        WHERE c.school_id=? GROUP BY c.id ORDER BY (c.name+0), c.name, c.section", [$from, $to, $sidSel]);
    $t = array_sum(array_column($classes, 'total')); $pr = array_sum(array_column($classes, 'p')) + array_sum(array_column($classes, 'l')); ?>
<div class="stats"><?= kpi('Attendance', $t ? pct((float)$pr, (float)$t) . '%' : '-', date('F Y', strtotime($from)), 'green') ?><?= kpi('Students', array_sum(array_column($classes, 'n'))) ?><?= kpi('Classes', count($classes), '', 'saffron') ?><?= kpi('Absent periods', array_sum(array_column($classes, 'ab')), '', 'red') ?></div>
<div class="card"><h3>Class-wise &middot; <?= e(date('F Y', strtotime($from))) ?></h3><div class="table-wrap"><table class="stack stack-nums"><tr><th>Class</th><th>Students</th><th>Periods marked</th><th>Present</th><th>Late</th><th>Absent</th><th>%</th><th></th></tr>
<?php foreach ($classes as $c): $tt = (int)$c['total']; ?><tr><td class="who-cell"><b>Class <?= e(cls($c)) ?></b></td><td data-label="Students"><?= (int)$c['n'] ?></td><td data-label="Periods marked"><?= $tt ?></td><td data-label="Present"><?= (int)$c['p'] ?></td><td data-label="Late"><?= (int)$c['l'] ?></td><td data-label="Absent"><?= (int)$c['ab'] ?></td>
  <td data-label="%"><?= $tt ? pct_badge(pct((float)($c['p'] + $c['l']), (float)$tt)) : badge('No data') ?></td><td class="wide right"><a class="btn btn-sm" href="<?= $link(['class' => $c['id']]) ?>#students">View students</a> <a class="btn btn-sm" href="<?= $link(['export' => 'students', 'class' => $c['id']]) ?>">&#8681; Excel</a></td></tr>
<?php endforeach; if (!$classes) echo empty_row(8, 'No classes yet.'); ?></table></div></div>
<?php $sel = null; foreach ($classes as $c) if ((int)$c['id'] === $cid) $sel = $c; if ($sel):
    $list = q("SELECT st.roll_no, st.admission_no, u.name, COUNT(a.id) total, COALESCE(SUM(a.status='P'),0) p, COALESCE(SUM(a.status='L'),0) l, COALESCE(SUM(a.status='A'),0) ab
        FROM students st JOIN users u ON u.id=st.user_id LEFT JOIN attendance a ON a.student_id=st.id AND a.date BETWEEN ? AND ? WHERE st.class_id=? AND st.school_id=? AND st.active=1 GROUP BY st.id ORDER BY st.roll_no", [$from, $to, $cid, $sidSel]); ?>
<div class="card" id="students"><h3>Students of Class <?= e(cls($sel)) ?></h3><div class="table-wrap"><table class="stack stack-nums"><tr><th>Student</th><th>Roll</th><th>Admission no.</th><th>Periods</th><th>Present</th><th>Late</th><th>Absent</th><th>%</th></tr>
<?php foreach ($list as $r): $tt = (int)$r['total']; ?><tr><td class="who-cell"><b><?= e($r['name']) ?></b></td><td data-label="Roll"><?= (int)$r['roll_no'] ?></td><td data-label="Admission no."><?= e($r['admission_no']) ?></td><td data-label="Periods"><?= $tt ?></td><td data-label="Present"><?= (int)$r['p'] ?></td><td data-label="Late"><?= (int)$r['l'] ?></td><td data-label="Absent"><?= (int)$r['ab'] ?></td><td data-label="%"><?= $tt ? pct_badge(pct((float)($r['p'] + $r['l']), (float)$tt)) : badge('No data') ?></td></tr>
<?php endforeach; if (!$list) echo empty_row(8, 'No students.'); ?></table></div></div><?php endif; ?>
<?php else: $faculty = array_values(array_filter(school_faculty($sidSel), fn($f) => $f['active'])); [$days, $grid] = staff_month($sidSel, $month, array_map(fn($f) => (int)$f['id'], $faculty)); ?>
<div class="card"><h3><?= e(date('F Y', strtotime($from))) ?> &middot; <?= count($days) ?> working day(s)</h3><div class="table-wrap"><table class="stack stack-nums"><tr><th>Faculty</th><th>Present</th><th>Late</th><th>Half day</th><th>Absent</th><th>Leave</th><th>%</th><th>Day by day</th><th></th></tr>
<?php foreach ($faculty as $f): $cells = $grid[$f['id']] ?? []; $t = staff_totals($cells); ?><tr><td class="who-cell"><b><?= e($f['name']) ?></b></td><td data-label="Present"><?= $t['P'] ?></td><td data-label="Late"><?= $t['L'] ?></td><td data-label="Half day"><?= $t['H'] ?></td><td data-label="Absent"><?= $t['A'] ?></td><td data-label="Leave"><?= $t['LV'] ?></td><td data-label="%"><?= $t['pct'] === null ? badge('No data') : pct_badge((float)$t['pct']) ?></td>
  <td class="wide" data-label="Day by day"><div class="daydots"><?php foreach ($cells as $d => $c) echo '<span class="dd dd-' . ($c[0] ?: 'none') . '" title="' . e(fmt_date($d, 'd M') . ': ' . (STAFF_STATUS[$c[0]][0] ?? 'Not marked')) . '">' . (int)substr($d, 8) . '</span>'; ?></div></td>
  <td class="wide right"><a class="btn btn-sm" href="<?= $link(['export' => 'faculty', 'faculty' => $f['id']]) ?>">&#8681; Excel</a></td></tr>
<?php endforeach; if (!$faculty) echo empty_row(9, 'No faculty yet.'); ?></table></div></div>
<?php endif; dash_footer(); ?>
