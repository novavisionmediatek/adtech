<?php
if (is_post()) {
    $u = row('SELECT * FROM users WHERE id=? AND role<>\'super_admin\'', [(int)post('id')]);
    if ($u && post('action') === 'toggle') { run('UPDATE users SET active=1-active WHERE id=?', [$u['id']]); audit('user_toggled', $u['username']); flash('Status changed for ' . $u['name'] . '.'); }
    elseif ($u && post('action') === 'reset') { $pw = temp_password(); run('UPDATE users SET password_hash=?, must_change=1, failed=0, locked_until=0 WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $u['id']]); audit('password_reset', $u['username']); reset_mail($u, $pw); flash('Temporary password for ' . $u['name'] . ': ' . $pw . '.' . mail_summary()); }
    redirect('super/users?' . http_build_query(['role' => inp('role'), 'q' => inp('q')]));
}
$role = inp('role'); $qs = inp('q'); $w = '1=1'; $p = [];
if (isset(ROLE_LABEL[$role])) { $w .= ' AND u.role=?'; $p[] = $role; }
if ($qs !== '') { $w .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)'; array_push($p, "%$qs%", "%$qs%", "%$qs%"); }
[$rows, $pager] = paginate("SELECT u.*, s.name sname FROM users u LEFT JOIN schools s ON s.id=u.school_id WHERE $w ORDER BY u.id DESC", $p, 20);
dash_header('All users');
?>
<div class="card"><form method="get" class="inline-form"><div><label>Role</label><select name="role" data-autosubmit><option value="">All</option><?php foreach (ROLE_LABEL as $k => $l) echo '<option value="' . $k . '"' . ($role === $k ? ' selected' : '') . '>' . $l . '</option>'; ?></select></div>
  <div><label>Search</label><input name="q" value="<?= e($qs) ?>" placeholder="name / e-mail / ID"></div><div style="flex:0"><button class="btn btn-primary">Search</button></div></form></div>
<div class="card"><div class="table-wrap"><table><tr><th>Name</th><th>Role</th><th>Login</th><th>School</th><th>Last login</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e(ROLE_LABEL[$r['role']]) ?></td><td><code><?= e($r['username']) ?></code></td><td><?= e($r['sname'] ?? '-') ?></td><td><?= e($r['last_login'] ? fmt_date($r['last_login'], 'd M, h:i A') : 'never') ?></td><td><?= $r['active'] ? badge('Active', 'green') : badge('Disabled', 'red') ?></td>
  <td class="right"><?php if ($r['role'] !== 'super_admin'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button name="action" value="reset" class="btn btn-sm"<?= confirm_attr('Reset password?') ?>>Reset pwd</button> <button name="action" value="toggle" class="btn btn-sm"><?= $r['active'] ? 'Disable' : 'Enable' ?></button></form><?php endif; ?></td></tr>
<?php endforeach; ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
