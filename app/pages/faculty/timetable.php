<?php
$u = me(); $cell = [];
$maxp = 1;
foreach (q('SELECT t.day, t.period, c.name cname, c.section, sub.name sname FROM timetable t JOIN classes c ON c.id=t.class_id JOIN subjects sub ON sub.id=t.subject_id WHERE t.faculty_id=?', [$u['id']]) as $r) { $cell[$r['day']][$r['period']] = $r; $maxp = max($maxp, (int)$r['period']); }
$total = array_sum(array_map('count', $cell));
dash_header('My timetable');
?>
<div class="card"><div class="card-head"><h3>Weekly schedule (<?= $total ?> periods)</h3><button class="btn btn-sm no-print" onclick="window.print()">Print</button></div>
<div class="table-wrap"><table class="tt"><tr><th style="width:90px">Day</th><?php for ($p = 1; $p <= $maxp; $p++) echo '<th>P' . $p . '<br><span class="muted" style="text-transform:none;font-weight:400">' . e(period_time($p)) . '</span></th>'; ?></tr>
<?php for ($d = 1; $d <= 6; $d++): ?><tr><th><?= e(DAYS[$d]) ?></th><?php for ($p = 1; $p <= $maxp; $p++): $x = $cell[$d][$p] ?? null; ?>
  <td><?= $x ? '<div class="cell"><b>Class ' . e(cls($x)) . '</b><small>' . e($x['sname']) . '</small></div>' : '<span class="muted">free</span>' ?></td><?php endfor; ?></tr><?php endfor; ?></table></div></div>
<?php dash_footer(); ?>
