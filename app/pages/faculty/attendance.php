<?php
$u = me(); $today = date('Y-m-d');
$date = inp('date', $today);
if (!valid_date($date) || $date > $today) $date = $today;
$tooOld = $date < date('Y-m-d', strtotime('-7 day'));
// own periods plus any covered as substitute; holidays and own leave are marked "blocked"
$slots = faculty_slots((int)$u['id'], (int)$u['school_id'], $date);
$slotId = (int)(inp('slot') ?: ($_POST['slot'] ?? 0)); $slot = null;
foreach ($slots as $r) if ((int)$r['id'] === $slotId) $slot = $r;

$students = []; $onLeave = $slot ? students_on_leave((int)$slot['class_id'], $date) : [];
if ($slot) {
    $students = q('SELECT st.id, st.roll_no, st.school_id, st.parent_id, u.name FROM students st JOIN users u ON u.id=st.user_id WHERE st.class_id=? AND st.active=1 ORDER BY st.roll_no, u.name', [$slot['class_id']]);
    $existing = [];
    foreach (q('SELECT student_id, status FROM attendance WHERE class_id=? AND date=? AND period=?', [$slot['class_id'], $date, $slot['period']]) as $r) $existing[$r['student_id']] = $r['status'];
}
if (is_post() && $slot) {
    if ($slot['blocked'] !== '') { flash('Attendance cannot be marked. ' . $slot['blocked'] . '.', 'err'); redirect("faculty/attendance?date=$date"); }
    if ($tooOld) { flash('Attendance can only be edited for the last 7 days.', 'err'); redirect("faculty/attendance?date=$date"); }
    $in = $_POST['st'] ?? []; $abs = 0; $new = 0; $pdo = db(); $pdo->beginTransaction();
    foreach ($students as $st) {
        $status = $in[$st['id']] ?? 'P'; if (!in_array($status, ['P', 'A', 'L'], true)) $status = 'P';
        $prev = $existing[$st['id']] ?? null;
        run(sql_attendance_upsert(), [$u['school_id'], $slot['class_id'], $slot['subject_id'], $u['id'], $st['id'], $date, $slot['period'], $status, date('Y-m-d H:i:s')]);
        if ($status === 'A') { $abs++; if ($prev !== 'A') $new++; }
    }
    $pdo->commit();
    $sent = 0;
    // no alert when the parent's leave request was approved
    foreach ($students as $st) if (($in[$st['id']] ?? 'P') === 'A' && !isset($onLeave[(int)$st['id']])) { $before = (int)val('SELECT COUNT(*) FROM absence_alerts WHERE student_id=? AND date=?', [$st['id'], $date]); notify_absence($st, $slot, $date, $u['name']); if ((int)val('SELECT COUNT(*) FROM absence_alerts WHERE student_id=? AND date=?', [$st['id'], $date]) > $before) $sent++; }
    audit('attendance_marked', cls($slot) . " P{$slot['period']} $date, $abs absent");
    flash('Attendance saved: ' . (count($students) - $abs) . ' present/late, ' . $abs . ' absent.' . ($sent ? " $sent parent alert e-mail(s) generated." : ''));
    redirect("faculty/attendance?date=$date&slot={$slot['id']}");
}
dash_header('Mark attendance');
?>
<div class="card"><form method="get" class="inline-form"><div><label>Date</label><input type="date" name="date" value="<?= e($date) ?>" max="<?= $today ?>"></div><div style="flex:0"><button class="btn">Show periods</button></div></form></div>
<?php if ($tooOld): ?><div class="alert alert-warn">This date is older than 7 days; attendance is read-only.</div><?php endif; ?>
<div class="grid g2">
<div class="card"><h3>Your periods on <?= e(fmt_date($date, 'l, d M Y')) ?></h3>
  <?php foreach ($slots as $r): $on = $slot && (int)$slot['id'] === (int)$r['id']; ?>
    <div class="slot" style="<?= $on ? 'border-color:var(--navy);box-shadow:var(--shadow)' : '' ?>"><div class="pno">P<?= (int)$r['period'] ?><small><?= e(substr(period_time((int)$r['period']), 0, 5)) ?></small></div>
      <div class="info"><b>Class <?= e(cls($r)) ?></b> &middot; <?= e($r['sname']) ?><?= $r['covering'] ? ' ' . badge('Substitute for ' . $r['covering'], 'amber') : '' ?><?= $r['blocked'] !== '' ? '<br><span class="muted small">' . e($r['blocked']) . '</span>' : '' ?></div>
      <?php if ($r['blocked'] !== ''): ?><?= badge($r['holiday'] !== null ? 'Holiday' : ($r['absent'] ? 'Absent' : 'On leave'), 'blue') ?><?php else: ?><a class="btn btn-sm" href="?date=<?= e($date) ?>&slot=<?= (int)$r['id'] ?>"><?= $on ? 'Open' : 'Select' ?></a><?php endif; ?></div>
  <?php endforeach; if (!$slots) echo '<p class="muted">No periods assigned on this day.</p>'; ?></div>
<?php if ($slot && $slot['blocked'] === ''): ?>
<div class="card"><div class="card-head"><h3>Class <?= e(cls($slot)) ?> &middot; P<?= (int)$slot['period'] ?> &middot; <?= e($slot['sname']) ?></h3><span id="att-counts" class="badge b-blue"></span></div>
<form method="post"><?= csrf_field() ?><input type="hidden" name="slot" value="<?= (int)$slot['id'] ?>">
  <p class="no-print"><button type="button" class="btn btn-sm" data-markall="P">All present</button> <button type="button" class="btn btn-sm" data-markall="A">All absent</button></p>
  <div class="table-wrap"><table><tr><th>Roll</th><th>Student</th><th>Status</th></tr>
  <?php foreach ($students as $st): $leave = $onLeave[(int)$st['id']] ?? null; $cur = $existing[$st['id']] ?? ($leave ? 'A' : 'P'); ?><tr><td><?= (int)$st['roll_no'] ?></td><td><?= e($st['name']) ?><?= $leave ? ' ' . badge('Approved leave', 'blue') : '' ?></td><td><div class="seg">
    <label><input type="radio" name="st[<?= (int)$st['id'] ?>]" value="P"<?= $cur === 'P' ? ' checked' : '' ?>><span class="sp">P</span></label>
    <label><input type="radio" name="st[<?= (int)$st['id'] ?>]" value="A"<?= $cur === 'A' ? ' checked' : '' ?>><span class="sa">A</span></label>
    <label><input type="radio" name="st[<?= (int)$st['id'] ?>]" value="L"<?= $cur === 'L' ? ' checked' : '' ?>><span class="sl">L</span></label></div></td></tr>
  <?php endforeach; if (!$students) echo empty_row(3, 'No students in this class.'); ?></table></div>
  <?php if (!$tooOld && $students): ?><p><button class="btn btn-primary btn-lg">Save attendance</button> <span class="muted small">Parents of absent students are e-mailed automatically.</span></p><?php endif; ?>
</form></div>
<?php endif; ?>
</div>
<?php dash_footer(); ?>
