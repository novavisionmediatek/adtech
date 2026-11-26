<?php
$st = my_student(); $dow = (int)date('N');
$off = holiday_title(holidays_on((int)$st['school_id'], date('Y-m-d')), (int)$st['class_id']);
$today = [];
if ($off === null) foreach (day_periods((int)$st['school_id'], date('Y-m-d')) as $p) if ((int)$p['class_id'] === (int)$st['class_id'])
    $today[] = ['period' => $p['period'], 'sname' => $p['sname'], 'fname' => $p['cover_id'] ? $p['cover_name'] . ' (substitute)' : $p['owner'] . ($p['away'] ? ' (absent today)' : '')];
dash_header('Hello, ' . explode(' ', $st['name'])[0]);
?>
<p class="muted">Class <b><?= e(cls($st)) ?></b> &middot; Roll no. <?= (int)$st['roll_no'] ?> &middot; Admission no. <?= e($st['admission_no']) ?></p>
<?= view_attendance((int)$st['id']) ?>
<div class="grid g2">
  <div class="card"><div class="card-head"><h3>Today's classes</h3><a class="btn btn-sm" href="/student/timetable">Timetable</a></div>
    <?php foreach ($today as $r): ?><div class="slot"><div class="pno">P<?= (int)$r['period'] ?></div><div class="info"><b><?= e($r['sname']) ?></b><br><span class="muted small"><?= e($r['fname']) ?> &middot; <?= e(period_time((int)$r['period'])) ?></span></div></div>
    <?php endforeach; if (!$today) echo '<p class="muted">' . ($off !== null ? 'Holiday: <b>' . e($off) . '</b>' : 'No classes today.') . '</p>'; ?></div>
  <div class="card"><div class="card-head"><h3>Latest homework</h3><a class="btn btn-sm" href="/student/homework">All</a></div><?= view_homework((int)$st['class_id'], 3, (int)$st['id']) ?></div>
</div>
<?= upcoming_holidays_html((int)$st['school_id'], (int)$st['class_id']) ?>
<div class="card"><h3>Notices</h3><?= notices_html(notices_for(sid(), 3)) ?></div>
<?php dash_footer(); ?>
