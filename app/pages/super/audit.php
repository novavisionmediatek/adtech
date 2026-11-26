<?php
[$rows, $pager] = paginate('SELECT a.*, u.name uname, u.role, s.name sname FROM audit a LEFT JOIN users u ON u.id=a.user_id LEFT JOIN schools s ON s.id=a.school_id ORDER BY a.id DESC', [], 20);
dash_header('Audit log');
?>
<div class="card"><p class="muted small">Immutable record of logins, failed logins and key administrative actions, newest first.</p><div class="table-wrap"><table><tr><th>Time</th><th>User</th><th>School</th><th>Action</th><th>Detail</th><th>IP</th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e(fmt_date($r['created_at'], 'd M Y, h:i:s A')) ?></td><td><?= e($r['uname'] ?? 'guest') ?> <span class="muted small"><?= e($r['role'] ?? '') ?></span></td><td><?= e($r['sname'] ?? '-') ?></td>
  <td><?= str_contains($r['action'], 'failed') ? badge($r['action'], 'red') : e($r['action']) ?></td><td><?= e($r['detail']) ?></td><td><?= e($r['ip']) ?></td></tr><?php endforeach; ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
