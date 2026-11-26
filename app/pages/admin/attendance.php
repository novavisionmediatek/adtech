<?php
$s = sid(); $classes = school_classes($s);
$cid = (int)inp('class', (string)($classes[0]['id'] ?? 0));
$from = inp('from', date('Y-m-d', strtotime('-30 day'))); $to = inp('to', date('Y-m-d'));
if (!valid_date($from)) $from = date('Y-m-d', strtotime('-30 day')); if (!valid_date($to)) $to = date('Y-m-d');
$class = null; foreach ($classes as $c) if ((int)$c['id'] === $cid) $class = $c;
$rows = [];
if ($class) $rows = q("SELECT st.id, st.roll_no, st.admission_no, u.name, COUNT(a.id) total, COALESCE(SUM(a.status='P'),0) p, COALESCE(SUM(a.status='L'),0) l, COALESCE(SUM(a.status='A'),0) a
   FROM students st JOIN users u ON u.id=st.user_id LEFT JOIN attendance a ON a.student_id=st.id AND a.date BETWEEN ? AND ?
   WHERE st.class_id=? AND st.school_id=? AND st.active=1 GROUP BY st.id ORDER BY st.roll_no", [$from, $to, $cid, $s]);
if (inp('export') === 'months' && $class) { // full history of the class, month by month, as one Excel file
    require_once APP . '/xlsx.php';
    $sheets = attendance_report_sheets($s, [$class]);
    if (!$sheets) { flash('No attendance has been recorded for this class yet.', 'warn'); redirect('admin/attendance?class=' . $cid); }
    audit('attendance_export', class_label($class) . ' month-wise Excel');
    xlsx_download('attendance_' . class_label($class) . '_month-wise_' . date('Y-m-d') . '.xlsx', $sheets);
}
if (inp('export') && $class) {
    audit('attendance_export', class_label($class) . " $from..$to");
    csv_out('attendance_' . class_label($class) . "_{$from}_$to.csv", ['Roll', 'Admission No', 'Name', 'Periods', 'Present', 'Late', 'Absent', 'Percentage'],
        array_map(fn($r) => [$r['roll_no'], $r['admission_no'], $r['name'], $r['total'], $r['p'], $r['l'], $r['a'], pct((float)($r['p'] + $r['l']), (float)$r['total'])], $rows));
}
$day = inp('day', date('Y-m-d')); if (!valid_date($day)) $day = date('Y-m-d');
[$absent, $pager] = paginate("SELECT u.name, c.name cname, c.section, GROUP_CONCAT(a.period ORDER BY a.period) periods, COUNT(*) n FROM attendance a
   JOIN students st ON st.id=a.student_id JOIN users u ON u.id=st.user_id JOIN classes c ON c.id=a.class_id
   WHERE a.school_id=? AND a.date=? AND a.status='A' GROUP BY st.id ORDER BY c.name, u.name", [$s, $day], 20);
dash_header('Attendance reports');
?>
<div class="card no-print"><form method="get" class="inline-form">
  <div><label>Class</label><select name="class"><?php foreach ($classes as $c) echo '<option value="' . (int)$c['id'] . '"' . ($cid === (int)$c['id'] ? ' selected' : '') . '>' . e(class_label($c)) . '</option>'; ?></select></div>
  <div><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div><div><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <div style="flex:0"><button class="btn btn-primary">Show</button></div>
  <div style="flex:0"><a class="btn" href="?class=<?= $cid ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&export=1">CSV</a></div>
  <div class="push"><a class="btn btn-primary" href="/admin/export?what=students">&#8681; All classes (Excel)</a></div></form></div>
<?php if ($class): ?>
<div class="card"><div class="card-head"><h3>Class <?= e(class_label($class)) ?> &middot; <?= e(fmt_date($from)) ?> to <?= e(fmt_date($to)) ?></h3>
  <a class="btn btn-primary btn-sm no-print" href="?class=<?= $cid ?>&amp;export=months">&#8681; Download all (month-wise Excel)</a></div>
<div class="table-wrap"><table><tr><th>Roll</th><th>Student</th><th>Periods</th><th>Present</th><th>Late</th><th>Absent</th><th>%</th><th style="width:140px"></th></tr>
<?php foreach ($rows as $r): $pc = pct((float)($r['p'] + $r['l']), (float)$r['total']); ?><tr><td><?= (int)$r['roll_no'] ?></td><td><?= e($r['name']) ?></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['p'] ?></td><td><?= (int)$r['l'] ?></td><td><?= (int)$r['a'] ?></td>
  <td><?= pct_badge($pc, (int)$r['total']) ?></td><td><?= bar($pc) ?></td></tr><?php endforeach; if (!$rows) echo empty_row(8); ?></table></div></div><?php endif; ?>
<div class="card"><div class="card-head"><h3>Absentees on a day</h3><form method="get" class="inline-form no-print"><input type="hidden" name="class" value="<?= $cid ?>"><input type="date" name="day" value="<?= e($day) ?>"><button class="btn btn-sm">Go</button></form></div>
<div class="table-wrap"><table><tr><th>Student</th><th>Class</th><th>Absent in periods</th></tr>
<?php foreach ($absent as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e(cls($r)) ?></td><td><?= e($r['periods']) ?> <span class="muted small">(<?= (int)$r['n'] ?>)</span></td></tr><?php endforeach; if (!$absent) echo empty_row(3, 'No absences recorded for this day.'); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
