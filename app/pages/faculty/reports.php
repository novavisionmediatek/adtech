<?php
$u = me();
$classes = q('SELECT DISTINCT c.id, c.name, c.section FROM timetable t JOIN classes c ON c.id=t.class_id WHERE t.faculty_id=? ORDER BY (c.name+0), c.section', [$u['id']]);
$cid = (int)inp('class', (string)($classes[0]['id'] ?? 0)); $ok = false; $class = null;
foreach ($classes as $c) if ((int)$c['id'] === $cid) { $ok = true; $class = $c; }
$from = inp('from', date('Y-m-d', strtotime('-30 day'))); $to = inp('to', date('Y-m-d'));
if (!valid_date($from)) $from = date('Y-m-d', strtotime('-30 day')); if (!valid_date($to)) $to = date('Y-m-d');
$rows = [];
if ($ok) $rows = q("SELECT st.roll_no, u.name, COUNT(a.id) total, COALESCE(SUM(a.status IN ('P','L')),0) pres, COALESCE(SUM(a.status='A'),0) absn
   FROM students st JOIN users u ON u.id=st.user_id LEFT JOIN attendance a ON a.student_id=st.id AND a.date BETWEEN ? AND ? AND a.faculty_id=?
   WHERE st.class_id=? AND st.active=1 GROUP BY st.id ORDER BY st.roll_no", [$from, $to, $u['id'], $cid]);
dash_header('Class reports');
?>
<div class="card"><form method="get" class="inline-form"><div><label>Class</label><select name="class"><?php foreach ($classes as $c) echo '<option value="' . (int)$c['id'] . '"' . ($cid === (int)$c['id'] ? ' selected' : '') . '>' . e(class_label($c)) . '</option>'; ?></select></div>
  <div><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div><div><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div><div style="flex:0"><button class="btn btn-primary">Show</button></div>
  <?php if ($ok): ?><div class="push"><a class="btn" href="/faculty/export?class=<?= $cid ?>">&#8681; This class (Excel)</a> <a class="btn btn-primary" href="/faculty/export">&#8681; All my classes (Excel)</a></div><?php endif; ?></form></div>
<?php if ($ok): ?><div class="card"><h3>Attendance in <i>your</i> periods &middot; Class <?= e(class_label($class)) ?></h3>
<div class="table-wrap"><table><tr><th>Roll</th><th>Student</th><th>Periods</th><th>Present</th><th>Absent</th><th>%</th><th style="width:140px"></th></tr>
<?php foreach ($rows as $r): $pc = pct((float)$r['pres'], (float)$r['total']); ?><tr><td><?= (int)$r['roll_no'] ?></td><td><?= e($r['name']) ?></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['pres'] ?></td><td><?= (int)$r['absn'] ?></td><td><?= pct_badge($pc, (int)$r['total']) ?></td><td><?= bar($pc) ?></td></tr>
<?php endforeach; ?></table></div></div><?php else: ?><div class="card muted center">No classes assigned yet.</div><?php endif; ?>
<?php dash_footer(); ?>
