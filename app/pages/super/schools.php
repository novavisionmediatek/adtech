<?php
if (is_post()) {
    $id = (int)post('id'); $a = post('action'); $s = row('SELECT * FROM schools WHERE id=?', [$id]);
    if ($a === 'add') {
        try {
            $email = strtolower(post('email'));
            if (post('name') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('School name and valid admin e-mail are required.');
            if (val('SELECT 1 FROM users WHERE lower(email)=?', [$email])) throw new RuntimeException('E-mail already registered.');
            [$pw, $custom, $must] = new_password($_POST, 'password', 'Admin password');
            $sid = register_school(['name' => post('name'), 'city' => post('city'), 'state' => post('state'), 'principal' => post('principal'), 'board' => post('board')], ['name' => post('principal') ?: 'School Admin', 'email' => $email, 'password' => $pw], 'active');
            run('UPDATE users SET must_change=? WHERE school_id=? AND role=\'school_admin\'', [$must, $sid]);
            welcome_mail(['name' => post('principal') ?: 'School Admin', 'email' => $email, 'role' => 'school_admin'], $email, $pw, $sid, '', (bool)$must);
            audit('school_created', post('name')); flash(($custom ? 'School created. Admin login: ' . $email . ' with the password you set.' : 'School created. Admin login: ' . $email . ' / ' . $pw . '.') . mail_summary());
        } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    } elseif ($s && in_array($a, ['approve', 'suspend', 'activate', 'reject'], true)) {
        $new = ['approve' => 'active', 'activate' => 'active', 'suspend' => 'suspended', 'reject' => 'rejected'][$a];
        run('UPDATE schools SET status=? WHERE id=?', [$new, $id]); audit('school_' . $a, $s['name']);
        if ($new === 'active' && $s['status'] === 'pending' && $s['email']) send_mail($s['email'], $s['principal'] ?: 'Admin', 'Your school is approved', mail_wrap('Approved', '<p>Good news! <b>' . e($s['name']) . '</b> has been approved. You can now log in with your e-mail <b>' . e($s['email']) . '</b> and the password you chose while registering.</p>' . mail_button()), $id);
        flash($s['name'] . ' is now ' . $new . '.');
    }
    redirect('super/schools');
}
[$rows, $pager] = paginate("SELECT s.*, (SELECT COUNT(*) FROM students WHERE school_id=s.id) ns, (SELECT COUNT(*) FROM users WHERE school_id=s.id AND role='faculty') nf FROM schools s ORDER BY (s.status='pending') DESC, s.created_at DESC, s.id DESC", [], 10);
dash_header('Schools');
?>
<div class="card"><div class="table-wrap"><table><tr><th>School</th><th>Code / UDISE</th><th>Location</th><th>Admin</th><th>Students</th><th>Faculty</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $s): ?><tr><td><b><?= e($s['name']) ?></b><br><span class="muted small"><?= e($s['board']) ?> &middot; registered <?= e(fmt_date($s['created_at'])) ?></span></td><td><?= e($s['code']) ?><br><span class="muted small"><?= e($s['udise']) ?></span></td>
  <td><?= e($s['city']) ?>, <?= e($s['state']) ?></td><td><?= e($s['principal']) ?><br><span class="muted small"><?= e($s['email']) ?></span></td><td><?= (int)$s['ns'] ?></td><td><?= (int)$s['nf'] ?></td><td><?= status_badge($s['status']) ?></td>
  <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
    <?php if ($s['status'] === 'pending'): ?><button name="action" value="approve" class="btn btn-sm btn-primary">Approve</button> <button name="action" value="reject" class="btn btn-sm btn-danger"<?= confirm_attr('Reject this registration?') ?>>Reject</button>
    <?php elseif ($s['status'] === 'active'): ?><button name="action" value="suspend" class="btn btn-sm btn-danger"<?= confirm_attr('Suspend this school? Its users will be unable to log in.') ?>>Suspend</button>
    <?php else: ?><button name="action" value="activate" class="btn btn-sm">Activate</button><?php endif; ?></form></td></tr>
<?php endforeach; ?></table></div><?= $pager ?></div>
<div class="card"><h3>Add a school directly</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add"><div class="form-grid">
  <div><label>School name *</label><input name="name" required></div><div><label>Board</label><input name="board"></div><div><label>City</label><input name="city"></div><div><label>State</label><input name="state"></div>
  <div><label>Admin name</label><input name="principal"></div><div><label>Admin e-mail *</label><input type="email" name="email" required></div>
  <?= password_fields('password', 'Admin password') ?><?= password_options() ?></div><p><button class="btn btn-primary">Create &amp; activate</button></p></form></div>
<?php dash_footer(); ?>
