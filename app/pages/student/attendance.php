<?php
$st = my_student(); $sub = inp('subject');
$subs = q('SELECT DISTINCT sub.id, sub.name FROM attendance a JOIN subjects sub ON sub.id=a.subject_id WHERE a.student_id=? ORDER BY sub.name', [$st['id']]);
dash_header('My attendance');
?>
<div class="card"><form method="get" class="inline-form"><div><label>Subject</label><select name="subject" data-autosubmit><option value="">All subjects</option>
  <?php foreach ($subs as $s) echo '<option value="' . (int)$s['id'] . '"' . ($sub === (string)$s['id'] ? ' selected' : '') . '>' . e($s['name']) . '</option>'; ?></select></div></form></div>
<div class="card"><h3>Period-by-period record</h3><?= view_attendance_detail((int)$st['id'], $sub) ?></div>
<?php dash_footer(); ?>
