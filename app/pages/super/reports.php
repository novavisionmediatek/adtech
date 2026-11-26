<?php
/* Government report: one page comparing all active schools. */
$today = date('Y-m-d'); $min = (int)cfg('min_attendance');
$from = inp('from', date('Y-m-01')); $to = inp('to', $today);
if (!valid_date($from)) $from = date('Y-m-01'); if (!valid_date($to)) $to = $today;
if ($to > $today) $to = $today; if ($from > $to) $from = $to;
$floor = date('Y-m-d', strtotime("$to -366 day")); if ($from < $floor) $from = $floor;
$states = array_column(q("SELECT DISTINCT state FROM schools WHERE status='active' AND state IS NOT NULL AND state<>'' ORDER BY state"), 'state');
$state = in_array(inp('state'), $states, true) ? inp('state') : '';
$schools = q("SELECT * FROM schools WHERE status='active'" . ($state ? ' AND state=?' : '') . ' ORDER BY state, city, name', $state ? [$state] : []);

$by = function (string $sql, array $p = []) { $o = []; foreach (q($sql, $p) as $r) $o[$r['school_id']] = $r; return $o; };
$students = $by('SELECT school_id, COUNT(*) n FROM students WHERE active=1 GROUP BY school_id');
$faculty = $by("SELECT school_id, COUNT(*) n FROM users WHERE role='faculty' AND active=1 GROUP BY school_id");
$att = $by("SELECT school_id, COUNT(*) total, SUM(status IN ('P','L')) present FROM attendance WHERE date BETWEEN ? AND ? GROUP BY school_id", [$from, $to]);
$staff = $by("SELECT school_id, SUM(status IN ('P','L')) full_days, SUM(status='H') half_days, SUM(status IN ('P','L','H','A')) counted FROM faculty_attendance WHERE date BETWEEN ? AND ? GROUP BY school_id", [$from, $to]);
$taken = $by('SELECT school_id, COUNT(*) n FROM (SELECT school_id, class_id, date, period FROM attendance WHERE date BETWEEN ? AND ? GROUP BY school_id, class_id, date, period) x GROUP BY school_id', [$from, $to]);
// classes that should have happened: timetable periods of each weekday, leaving out holidays
$slots = []; foreach (q('SELECT school_id, class_id, day, COUNT(*) n FROM timetable GROUP BY school_id, class_id, day') as $r) $slots[$r['school_id']][$r['day']][$r['class_id']] = (int)$r['n'];
$hol = []; foreach (q('SELECT school_id, class_id, from_date, to_date FROM holidays WHERE from_date<=? AND to_date>=?', [$to, $from]) as $h) $hol[$h['school_id']][] = $h;
$scheduled = [];
foreach ($schools as $sc) {
    $id = $sc['id']; $n = 0;
    for ($d = $from; $d <= $to; $d = date('Y-m-d', (int)strtotime("$d +1 day"))) {
        $dow = (int)date('N', (int)strtotime($d)); if (empty($slots[$id][$dow])) continue;
        $off = []; foreach ($hol[$id] ?? [] as $h) if ($h['from_date'] <= $d && $h['to_date'] >= $d) $off[(int)$h['class_id']] = true;
        if (isset($off[0])) continue;
        foreach ($slots[$id][$dow] as $cid => $c) if (!isset($off[(int)$cid])) $n += $c;
    }
    $scheduled[$id] = $n;
}
$lowSql = "SELECT st.id, sc.name school, sc.state, u.name, st.admission_no, c.name cname, c.section, pu.name pname, pu.phone, COUNT(*) total, SUM(a.status IN ('P','L')) present
    FROM attendance a JOIN students st ON st.id=a.student_id AND st.active=1 JOIN users u ON u.id=st.user_id JOIN classes c ON c.id=st.class_id JOIN schools sc ON sc.id=st.school_id AND sc.status='active' LEFT JOIN users pu ON pu.id=st.parent_id
    WHERE a.date BETWEEN ? AND ?" . ($state ? ' AND sc.state=?' : '') . " GROUP BY st.id, sc.name, sc.state, u.name, st.admission_no, c.name, c.section, pu.name, pu.phone
    HAVING SUM(a.status IN ('P','L'))*100.0 < $min*COUNT(*) ORDER BY SUM(a.status IN ('P','L'))*1.0/COUNT(*), st.id";
$lowParams = $state ? [$from, $to, $state] : [$from, $to];
$lowBySchool = []; foreach (q($lowSql, $lowParams) as $r) $lowBySchool[$r['school']] = ($lowBySchool[$r['school']] ?? 0) + 1;

$rows = [];
foreach ($schools as $sc) {
    $id = $sc['id']; $a = $att[$id] ?? ['total' => 0, 'present' => 0]; $f = $staff[$id] ?? ['full_days' => 0, 'half_days' => 0, 'counted' => 0]; $tk = (int)($taken[$id]['n'] ?? 0);
    $rows[] = ['name' => $sc['name'], 'udise' => $sc['udise'], 'city' => $sc['city'], 'state' => $sc['state'], 'students' => (int)($students[$id]['n'] ?? 0), 'faculty' => (int)($faculty[$id]['n'] ?? 0),
        'att' => $a['total'] ? pct((float)$a['present'], (float)$a['total']) : null, 'low' => $lowBySchool[$sc['name']] ?? 0,
        'staff' => $f['counted'] ? pct($f['full_days'] + $f['half_days'] * 0.5, (float)$f['counted']) : null,
        'scheduled' => $scheduled[$id], 'taken' => $tk, 'takenp' => $scheduled[$id] ? min(100.0, pct((float)$tk, (float)$scheduled[$id])) : null, 'att_total' => (int)$a['total'], 'att_present' => (int)$a['present']];
}
if (inp('export')) {
    require_once APP . '/xlsx.php';
    $head = fn(array $cols) => array_map(fn($c) => [$c, 1], $cols);
    $top = fn(string $t) => [[[cfg('app_name') . ' - ' . $t, 2]], [[date_range_label($from, $to) . ($state ? ' | ' . $state : '') . ' | Generated ' . date('d M Y, h:i A'), 6]], []];
    $num = fn($v, $good = null) => $v === null ? ['-', 7] : [(float)$v, $good === null ? 7 : ($v >= $good ? 3 : 4)];
    $one = $top('School report'); $one[] = $head(['School', 'UDISE', 'City', 'State', 'Students', 'Teachers', 'Student attendance %', "Students below $min%", 'Teacher presence %', 'Classes scheduled', 'Classes taken', 'Classes taken %']);
    foreach ($rows as $r) $one[] = [$r['name'], (string)$r['udise'], (string)$r['city'], (string)$r['state'], [$r['students'], 7], [$r['faculty'], 7], $num($r['att'], $min), [$r['low'], 7], $num($r['staff'], 90), [$r['scheduled'], 7], [$r['taken'], 7], $num($r['takenp'], 90)];
    $two = $top('Students below minimum attendance'); $two[] = $head(['School', 'State', 'Class', 'Admission No', 'Student', 'Parent', 'Parent phone', 'Periods', 'Present', '%']);
    foreach (q($lowSql, $lowParams) as $r) $two[] = [$r['school'], (string)$r['state'], cls($r), $r['admission_no'], $r['name'], (string)$r['pname'], (string)$r['phone'], [(int)$r['total'], 7], [(int)$r['present'], 7], [pct((float)$r['present'], (float)$r['total']), 4]];
    audit('government_report_export', "$from..$to $state");
    xlsx_download("school_report_{$from}_to_$to.xlsx", [['name' => 'Schools', 'rows' => $one, 'freeze' => [4, 1], 'widths' => [30, 15, 16, 16, 10, 10, 20, 18, 18, 17, 14, 15]], ['name' => 'Low attendance', 'rows' => $two, 'freeze' => [4, 0], 'widths' => [30, 16, 8, 15, 26, 24, 14, 9, 9, 8]]]);
}
$sum = fn(string $k) => array_sum(array_column($rows, $k));
[$low, $pager] = paginate($lowSql, $lowParams, 20);
dash_header('Government report');
?>
<div class="card"><form method="get" class="inline-form"><div><label>From date</label><input type="date" name="from" value="<?= e($from) ?>" max="<?= $today ?>"></div><div><label>To date</label><input type="date" name="to" value="<?= e($to) ?>" max="<?= $today ?>"></div>
  <div><label>State</label><select name="state"><option value="">All states</option><?php foreach ($states as $x) echo '<option' . ($state === $x ? ' selected' : '') . '>' . e($x) . '</option>'; ?></select></div><div style="flex:0"><button class="btn btn-primary">Show</button></div>
  <div class="push"><a class="btn" href="?<?= e(http_build_query(['from' => $from, 'to' => $to, 'state' => $state, 'export' => 1])) ?>">&#8681; Download Excel</a></div></form></div>
<div class="stats"><?= kpi('Schools', count($rows)) ?><?= kpi('Students', $sum('students')) ?><?= kpi('Teachers', $sum('faculty')) ?><?= kpi('Student attendance', $sum('att_total') ? pct((float)$sum('att_present'), (float)$sum('att_total')) . '%' : '-', '', 'green') ?><?= kpi("Students below $min%", $sum('low'), '', 'red') ?><?= kpi('Classes taken', $sum('scheduled') ? min(100.0, pct((float)$sum('taken'), (float)$sum('scheduled'))) . '%' : '-', $sum('taken') . ' of ' . $sum('scheduled')) ?></div>
<div class="card"><h3>School by school &middot; <?= e(date_range_label($from, $to)) ?></h3><div class="table-wrap"><table class="stack stack-nums"><tr><th>School</th><th>Students</th><th>Teachers</th><th>Student attendance</th><th>Below <?= $min ?>%</th><th>Teacher presence</th><th>Classes taken</th></tr>
<?php foreach ($rows as $r): ?><tr><td class="who-cell"><b><?= e($r['name']) ?></b><br><span class="muted small"><?= e(implode(', ', array_filter([$r['city'], $r['state']]))) ?><?= $r['udise'] ? ' &middot; UDISE ' . e($r['udise']) : '' ?></span></td><td data-label="Students"><?= $r['students'] ?></td><td data-label="Teachers"><?= $r['faculty'] ?></td>
  <td data-label="Attendance"><?= $r['att'] === null ? badge('No data') : pct_badge((float)$r['att']) ?></td><td data-label="Below <?= $min ?>%"><?= $r['low'] ?></td><td data-label="Teachers present"><?= $r['staff'] === null ? badge('No data') : pct_badge((float)$r['staff']) ?></td>
  <td data-label="Classes taken"><?= $r['takenp'] === null ? badge('No data') : pct_badge((float)$r['takenp']) . '<br><span class="muted small">' . $r['taken'] . ' of ' . $r['scheduled'] . '</span>' ?></td></tr>
<?php endforeach; if (!$rows) echo empty_row(7, 'No active schools.'); ?></table></div>
<p class="small muted">"Classes taken" = periods with student attendance marked, compared with the present timetable (holidays left out). Today counts in full, so today's later periods lower the figure until they are marked. Teacher presence uses the days on which teacher attendance was recorded (half day = half).</p></div>
<div class="card"><h3>Students below <?= $min ?>% attendance</h3><div class="table-wrap"><table class="stack stack-nums"><tr><th>Student</th><th>School</th><th>Class</th><th>Parent</th><th>Present</th><th>%</th></tr>
<?php foreach ($low as $r): ?><tr><td class="who-cell"><b><?= e($r['name']) ?></b> <span class="muted small"><?= e($r['admission_no']) ?></span></td><td data-label="School"><?= e($r['school']) ?></td><td data-label="Class"><?= e(cls($r)) ?></td><td data-label="Parent"><?= e($r['pname'] ?: '-') ?><br><span class="muted small"><?= e($r['phone']) ?></span></td><td data-label="Present"><?= (int)$r['present'] ?> / <?= (int)$r['total'] ?></td><td data-label="%"><?= pct_badge(pct((float)$r['present'], (float)$r['total'])) ?></td></tr>
<?php endforeach; if (!$low) echo empty_row(6, 'No student is below the minimum in these dates.'); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
