<?php
$u = me(); $today = date('Y-m-d'); $dow = (int)date('N');
$slots = faculty_slots((int)$u['id'], (int)$u['school_id'], $today);
foreach ($slots as &$r) {
    $r['marked'] = (int)val('SELECT COUNT(*) FROM attendance WHERE class_id=? AND date=? AND period=? AND subject_id=?', [$r['class_id'], $today, $r['period'], $r['subject_id']]);
    $r['nabs'] = (int)val("SELECT COUNT(*) FROM attendance WHERE class_id=? AND date=? AND period=? AND status='A'", [$r['class_id'], $today, $r['period']]);
}
unset($r);
$myLeave = q('SELECT * FROM faculty_leave WHERE faculty_id=? AND to_date>=? ORDER BY from_date LIMIT 3', [$u['id'], $today]);
$offToday = holidays_on((int)$u['school_id'], $today)[0] ?? null;
$week = (int)val('SELECT COUNT(*) FROM timetable WHERE faculty_id=?', [$u['id']]);
$ncls = (int)val('SELECT COUNT(DISTINCT class_id) FROM timetable WHERE faculty_id=?', [$u['id']]);
$hw = q('SELECT h.*, c.name cname, c.section, sub.name sname FROM homework h JOIN classes c ON c.id=h.class_id JOIN subjects sub ON sub.id=h.subject_id WHERE h.faculty_id=? ORDER BY h.created_at DESC LIMIT 4', [$u['id']]);
$pending = count(array_filter($slots, fn($r) => !$r['marked'] && $r['blocked'] === ''));
dash_header('My day', '<a class="btn btn-sm btn-primary" href="/faculty/export">&#8681; Student attendance (Excel)</a>');
?>
<div class="stats"><?= kpi('Periods today', count($slots), e(fmt_date($today, 'l'))) ?><?= kpi('Attendance pending', $pending, 'today', $pending ? 'red' : 'green') ?><?= kpi('Periods / week', $week, '', 'saffron') ?><?= kpi('Classes taught', $ncls) ?></div>
<?php if ($offToday !== null): ?><div class="alert alert-warn">Today is a holiday: <b><?= e($offToday) ?></b>.</div><?php endif; ?>
<?php foreach ($myLeave as $l): ?><div class="alert"><?= $l['from_date'] <= $today ? 'You are on leave' : 'Your upcoming leave' ?>: <b><?= e($l['type']) ?></b>, <?= e(date_range_label($l['from_date'], $l['to_date'])) ?>.</div><?php endforeach; ?>
<div class="grid g2">
<div class="card"><div class="card-head"><h3>Today's schedule</h3><a class="btn btn-sm" href="/faculty/timetable">Full timetable</a></div>
  <?php if ($dow > 6): ?><p class="muted">Sunday &mdash; no classes.</p><?php endif; ?>
  <?php foreach ($slots as $r): ?>
    <div class="slot<?= $r['marked'] ? ' done' : '' ?>"><div class="pno">P<?= (int)$r['period'] ?><small><?= e(substr(period_time((int)$r['period']), 0, 5)) ?></small></div>
      <div class="info"><b>Class <?= e(cls($r)) ?></b> &middot; <?= e($r['sname']) ?><?= $r['covering'] ? ' ' . badge('Substitute for ' . $r['covering'], 'amber') : '' ?><br><span class="muted small"><?= $r['blocked'] !== '' ? e($r['blocked']) : ($r['marked'] ? 'Marked &middot; ' . (int)$r['nabs'] . ' absent' : 'Attendance not marked') ?></span></div>
      <?php if ($r['blocked'] !== ''): ?><?= badge($r['holiday'] !== null ? 'Holiday' : ($r['absent'] ? 'Absent' : 'On leave'), 'blue') ?><?php else: ?><a class="btn btn-sm <?= $r['marked'] ? '' : 'btn-primary' ?>" href="/faculty/attendance?date=<?= $today ?>&slot=<?= (int)$r['id'] ?>"><?= $r['marked'] ? 'Edit' : 'Mark' ?></a><?php endif; ?></div>
  <?php endforeach; if (!$slots && $dow <= 6) echo '<p class="muted">No periods assigned today.</p>'; ?></div>
<div class="card"><div class="card-head"><h3>Recent homework</h3><a class="btn btn-sm btn-primary" href="/faculty/homework">+ New</a></div>
  <?php foreach ($hw as $h): ?><div class="slot"><div class="info"><b><?= e($h['title']) ?></b><br><span class="muted small">Class <?= e(cls($h)) ?> &middot; <?= e($h['sname']) ?> &middot; due <?= e(fmt_date($h['due_date'])) ?></span></div></div>
  <?php endforeach; if (!$hw) echo '<p class="muted">No homework posted yet.</p>'; ?></div>
</div>
<?= upcoming_holidays_html(sid()) ?>
<div class="card"><h3>Notices</h3><?= notices_html(notices_for(sid(), 3)) ?></div>
<?php dash_footer(); ?>
