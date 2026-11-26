<?php
$kids = my_children(); $sel = (int)inp('s', (string)($kids[0]['id'] ?? 0)); $child = null;
foreach ($kids as $k) if ((int)$k['id'] === $sel) $child = $k;
$tabs = ['attendance' => 'Attendance', 'homework' => 'Homework', 'results' => 'Results', 'lectures' => 'Lectures', 'timetable' => 'Timetable', 'fees' => 'Fees', 'leave' => 'Leave', 'certificates' => 'Certificates', 'campus' => 'Library & bus'];
$tab = isset($tabs[inp('tab')]) ? inp('tab') : 'attendance';
if (is_post() && $child) {
    try {
        if (post('action') === 'cancel') cancel_request((int)post('id'), (int)me()['id']);
        else { request_leave(me(), 'student', $child, $_POST); flash('Your leave request was sent to the school.'); }
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('parent?s=' . (int)$child['id'] . '&tab=leave');
}
dash_header('My children');
if (!$kids) { echo '<div class="card muted center">No child is linked to your account. Please contact the school.</div>'; dash_footer(); return; }
?>
<div class="grid g3" style="margin-bottom:20px">
<?php foreach ($kids as $k): $sm = student_attendance_summary((int)$k['id']); $on = $child && (int)$child['id'] === (int)$k['id']; ?>
  <a href="?s=<?= (int)$k['id'] ?>" style="text-decoration:none;color:inherit"><div class="card" style="margin:0;<?= $on ? 'border-color:var(--navy);box-shadow:0 0 0 4px rgba(108,99,255,.22)' : '' ?>">
    <h3><?= e($k['name']) ?></h3><p class="muted small">Class <?= e(cls($k)) ?> &middot; Roll <?= (int)$k['roll_no'] ?></p>
    <div style="display:flex;justify-content:space-between;align-items:center"><span>Attendance</span><?= pct_badge($sm['pct'], $sm['total']) ?></div><?= bar($sm['pct']) ?>
    <p class="small muted" style="margin:8px 0 0"><?= $sm['total'] - $sm['present'] ?> absent periods of <?= $sm['total'] ?></p></div></a>
<?php endforeach; ?></div>
<?php if ($child): ?>
<?= upcoming_holidays_html((int)$child['school_id'], (int)$child['class_id'], 3) ?>
<div class="tabs"><?php foreach ($tabs as $t => $l) echo '<a href="?s=' . (int)$child['id'] . '&tab=' . $t . '"' . ($tab === $t ? ' class="active"' : '') . '>' . $l . '</a>'; ?></div>
<?php if ($tab === 'attendance'): echo view_attendance((int)$child['id']); ?>
  <div class="card"><h3>Detailed record</h3><?= view_attendance_detail((int)$child['id']) ?></div>
<?php elseif ($tab === 'homework'): ?><div class="card"><h3>Homework for Class <?= e(cls($child)) ?></h3><?= view_homework((int)$child['class_id'], 50, (int)$child['id'], true) ?></div>
<?php elseif ($tab === 'results'): echo view_results($child); ?>
<?php elseif ($tab === 'fees'): echo view_fees($child); ?>
<?php elseif ($tab === 'leave'): echo leave_panel((int)me()['id'], $child, '/parent?s=' . (int)$child['id'] . '&tab=leave'); ?>
<?php elseif ($tab === 'certificates'): echo view_certificates($child); ?>
<?php elseif ($tab === 'campus'): echo view_campus($child); ?>
<?php elseif ($tab === 'lectures'): ?><div class="card"><h3>Lectures for Class <?= e(cls($child)) ?></h3><?= view_lectures((int)$child['class_id']) ?></div>
<?php else: ?><div class="card"><h3>Timetable &middot; Class <?= e(cls($child)) ?></h3><?= view_timetable((int)$child['class_id']) ?></div><?php endif; endif; ?>
<?php dash_footer(); ?>
