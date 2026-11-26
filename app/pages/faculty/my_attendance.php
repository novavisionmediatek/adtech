<?php
$u = me(); $today = date('Y-m-d');
$month = inp('month', substr($today, 0, 7)); if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $month > substr($today, 0, 7)) $month = substr($today, 0, 7);
[$days, $grid] = staff_month((int)$u['school_id'], $month, [(int)$u['id']]);
$cells = $grid[(int)$u['id']] ?? []; $t = staff_totals($cells);
dash_header('My attendance');
?>
<div class="card"><form method="get" class="inline-form"><div><label>Month</label><input type="month" name="month" value="<?= e($month) ?>" max="<?= substr($today, 0, 7) ?>" data-autosubmit></div><div style="flex:0"><button class="btn">Show</button></div></form></div>
<div class="stats"><?= kpi('My attendance', $t['pct'] === null ? '-' : $t['pct'] . '%', 'marked days', 'green') ?><?= kpi('Present', $t['P'] + $t['L'], $t['L'] . ' late') ?><?= kpi('Half days', $t['H'], '', 'saffron') ?><?= kpi('Absent', $t['A'], '', 'red') ?><?= kpi('Leave', $t['LV']) ?></div>
<div class="card"><h3><?= e(date('F Y', strtotime("$month-01"))) ?></h3><div class="table-wrap"><table><tr><th>Date</th><th>Status</th><th>Check-in</th><th>Note</th></tr>
<?php foreach (array_reverse($cells, true) as $d => $c): ?><tr><td><?= e(fmt_date($d, 'D, d M Y')) ?></td><td><?= staff_badge($c[0]) ?></td><td><?= $c[1] !== '' ? e(date('h:i A', strtotime($c[1]))) : '-' ?></td><td><?= e($c[2]) ?></td></tr>
<?php endforeach; if (!$cells) echo empty_row(4, 'No working days in this month yet.'); ?></table></div>
<p class="small muted">Attendance is marked by your school admin. Please contact them if something is wrong.</p></div>
<?php dash_footer(); ?>
