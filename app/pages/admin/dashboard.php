<?php
$s = sid(); $today = date('Y-m-d'); $dow = (int)date('N');
$stu = (int)val('SELECT COUNT(*) FROM students WHERE school_id=? AND active=1', [$s]);
$fac = (int)val("SELECT COUNT(*) FROM users WHERE school_id=? AND role='faculty' AND active=1", [$s]);
$cls = (int)val('SELECT COUNT(*) FROM classes WHERE school_id=?', [$s]);
$absToday = (int)val("SELECT COUNT(DISTINCT student_id) FROM attendance WHERE school_id=? AND date=? AND status='A'", [$s, $today]);
$tot = (int)val('SELECT COUNT(*) FROM attendance WHERE school_id=? AND date>=?', [$s, date('Y-m-d', strtotime('-30 day'))]);
$pre = (int)val("SELECT COUNT(*) FROM attendance WHERE school_id=? AND date>=? AND status IN ('P','L')", [$s, date('Y-m-d', strtotime('-30 day'))]);
$slots = day_periods($s, $today);
foreach ($slots as &$r) $r['marked'] = (int)val('SELECT COUNT(*) FROM attendance WHERE class_id=? AND date=? AND period=?', [$r['class_id'], $today, $r['period']]);
unset($r);
$due = array_filter($slots, fn($r) => $r['holiday'] === null);
$pending = array_filter($due, fn($r) => !$r['marked']);
$uncovered = count(array_filter($due, fn($r) => $r['away'] && !$r['cover_id'] && !$r['marked']));
$offToday = holidays_on($s, $today)[0] ?? null; $awayIds = array_keys(away_on($s, $today));
$awayNames = $awayIds ? q('SELECT name FROM users WHERE id IN (' . implode(',', array_map('intval', $awayIds)) . ') ORDER BY name') : [];
$low = q("SELECT u.name, c.name cname, c.section, COUNT(*) total, SUM(a.status IN ('P','L')) pres FROM attendance a
    JOIN students st ON st.id=a.student_id JOIN users u ON u.id=st.user_id JOIN classes c ON c.id=st.class_id
    WHERE a.school_id=? AND a.date>=? GROUP BY st.id HAVING (pres*100.0/total) < ? ORDER BY (pres*1.0/total) LIMIT 8",
    [$s, date('Y-m-d', strtotime('-30 day')), (int)cfg('min_attendance')]);
dash_header('School overview');
?>
<div class="stats">
  <?= kpi('Students', $stu) ?><?= kpi('Faculty', $fac, '', 'saffron') ?><?= kpi('Classes', $cls) ?>
  <?= kpi('Absent today', $absToday, 'unique students', 'red') ?><?= kpi('30-day attendance', pct((float)$pre, (float)$tot) . '%', 'all classes', 'green') ?>
</div>
<?php if ($offToday !== null): ?><div class="alert alert-warn">Today is a holiday: <b><?= e($offToday) ?></b>. <a href="/admin/leave">Manage holidays</a></div>
<?php elseif ($awayNames): ?><div class="alert<?= $uncovered ? ' alert-warn' : '' ?>">Faculty off today: <b><?= e(implode(', ', array_column($awayNames, 'name'))) ?></b>. <?= $uncovered ? "$uncovered period(s) have no substitute. " : 'No period is waiting for a substitute. ' ?><a href="/admin/leave#substitutes">Assign substitutes</a></div><?php endif; ?>
<div class="grid g2">
  <div class="card"><div class="card-head"><h3>Today's periods &middot; marking status</h3><span class="muted small"><?= e(fmt_date($today, 'D d M')) ?></span></div>
    <?php if ($dow > 6): ?><p class="muted">Sunday &mdash; no classes.</p><?php else: ?>
    <p class="small"><?= count($pending) ?> of <?= count($due) ?> class-periods not marked yet.</p>
    <div class="table-wrap"><table><tr><th>P</th><th>Class</th><th>Subject</th><th>Faculty</th><th>Status</th></tr>
    <?php foreach ($slots as $r): ?><tr><td><?= (int)$r['period'] ?></td><td><?= e(cls($r)) ?></td><td><?= e($r['sname']) ?></td><td><?= $r['cover_id'] ? e($r['cover_name']) . '<br><span class="muted small">for ' . e($r['owner']) . '</span>' : e($r['owner']) ?></td>
      <td><?= $r['holiday'] !== null ? badge('Holiday', 'blue') : ($r['marked'] ? badge('Marked', 'green') : ($r['away'] && !$r['cover_id'] ? badge('No teacher', 'red') : badge('Pending', 'amber'))) ?></td></tr><?php endforeach; if (!$slots) echo empty_row(5, 'No timetable set for today.'); ?></table></div><?php endif; ?></div>
  <div class="card"><h3>Students below <?= (int)cfg('min_attendance') ?>% (last 30 days)</h3>
    <div class="table-wrap"><table><tr><th>Student</th><th>Class</th><th>Attendance</th></tr>
    <?php foreach ($low as $r): $p = pct((float)$r['pres'], (float)$r['total']); ?><tr><td><?= e($r['name']) ?></td><td><?= e(cls($r)) ?></td><td><?= pct_badge($p) ?></td></tr><?php endforeach;
    if (!$low) echo empty_row(3, 'Everyone is above the threshold.'); ?></table></div></div>
</div>
<div class="card"><h3>Notices</h3><?= notices_html(notices_for($s, 3)) ?> <a class="btn btn-sm" href="/admin/notices">Manage notices</a></div>
<?php dash_footer(); ?>
