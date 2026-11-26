<?php
$c = fn(string $sql) => (int)val($sql);
$pending = q("SELECT * FROM schools WHERE status='pending' ORDER BY created_at DESC");
$recent = q('SELECT a.*, u.name uname FROM audit a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 8');
dash_header('Platform overview');
?>
<div class="stats">
  <?= kpi('Active schools', $c("SELECT COUNT(*) FROM schools WHERE status='active'"), '', 'green') ?><?= kpi('Awaiting approval', count($pending), '', 'saffron') ?>
  <?= kpi('Students', $c('SELECT COUNT(*) FROM students')) ?><?= kpi('Faculty', $c("SELECT COUNT(*) FROM users WHERE role='faculty'")) ?><?= kpi('Parents', $c("SELECT COUNT(*) FROM users WHERE role='parent'")) ?>
  <?= kpi('Attendance records', number_format($c('SELECT COUNT(*) FROM attendance'))) ?><?= kpi('Absence alerts', $c("SELECT COUNT(*) FROM outbox WHERE subject LIKE 'Absence alert%'")) ?>
  <?= kpi('Unread messages', $c('SELECT COUNT(*) FROM messages WHERE handled=0'), '', 'red') ?>
</div>
<div class="grid g2">
<div class="card"><div class="card-head"><h3>Schools awaiting approval</h3><a class="btn btn-sm" href="/super/schools">All schools</a></div>
  <?php foreach ($pending as $s): ?><div class="slot"><div class="info"><b><?= e($s['name']) ?></b><br><span class="muted small"><?= e($s['city']) ?>, <?= e($s['state']) ?> &middot; <?= e($s['principal']) ?> &middot; <?= e($s['email']) ?></span></div><a class="btn btn-sm btn-primary" href="/super/schools">Review</a></div>
  <?php endforeach; if (!$pending) echo '<p class="muted">No pending registrations.</p>'; ?></div>
<div class="card"><div class="card-head"><h3>Recent activity</h3><a class="btn btn-sm" href="/super/audit">Audit log</a></div>
  <div class="table-wrap"><table><?php foreach ($recent as $r) echo '<tr><td>' . e(fmt_date($r['created_at'], 'd M h:i A')) . '</td><td>' . e($r['uname'] ?? 'guest') . '</td><td>' . e($r['action']) . '</td></tr>'; ?></table></div></div>
</div>
<?php dash_footer(); ?>
