<?php
$s = sid();
if (is_post()) {
    try {
        $a = post('action');
        if ($a === 'add') {
            $pw = create_faculty($s, $_POST); audit('faculty_added', post('email'));
            flash(($pw === null ? 'Faculty added with the password you set.' : 'Faculty added. Temporary password: ' . $pw . ' (shown only once).') . mail_summary());
        } elseif ($a === 'toggle') {
            run("UPDATE users SET active=1-active WHERE id=? AND school_id=? AND role='faculty'", [(int)post('id'), $s]); audit('faculty_toggled', post('id')); flash('Status changed.');
        } elseif ($a === 'reset') {
            $u = row("SELECT * FROM users WHERE id=? AND school_id=? AND role='faculty'", [(int)post('id'), $s]);
            if ($u) { $pw = temp_password(); run('UPDATE users SET password_hash=?, must_change=1, failed=0, locked_until=0 WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
                reset_mail($u, $pw); audit('password_reset', (string)$u['id']); flash('New temporary password for ' . $u['name'] . ': ' . $pw . '.' . mail_summary()); }
        }
    } catch (PDOException $x) { flash('Could not save: duplicate e-mail.', 'err'); }
    catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('admin/faculty');
}
[$rows, $pager, $total] = paginate("SELECT u.*, f.employee_id, f.designation, f.qualification,
   (SELECT COUNT(*) FROM timetable t WHERE t.faculty_id=u.id) periods,
   (SELECT COUNT(DISTINCT class_id) FROM timetable t WHERE t.faculty_id=u.id) classes
   FROM users u LEFT JOIN faculty f ON f.user_id=u.id WHERE u.school_id=? AND u.role='faculty' ORDER BY u.name, u.id", [$s], 20);
dash_header('Faculty');
?>
<div class="card"><h3>Add faculty</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div class="form-grid"><div><label>Full name *</label><input name="name" required></div><div><label>E-mail * (login)</label><input type="email" name="email" required></div>
  <div><label>Phone</label><input name="phone"></div><div><label>Employee ID</label><input name="employee_id"></div>
  <div><label>Designation</label><input name="designation" placeholder="PGT / TGT / PRT"></div><div><label>Qualification</label><input name="qualification"></div>
  <?= password_fields() ?><?= password_options() ?></div>
  <p><button class="btn btn-primary">Add faculty</button></p></form></div>
<div class="card"><div class="card-head"><h3>Faculty (<?= $total ?>)</h3><span><a class="btn btn-sm" href="/admin/staff_attendance?tab=report">View attendance</a> <a class="btn btn-primary btn-sm" href="/admin/export?what=faculty">&#8681; Download attendance (all faculty)</a></span></div><div class="table-wrap"><table><tr><th>Name</th><th>E-mail</th><th>Emp. ID</th><th>Designation</th><th>Weekly periods</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><b><?= e($r['name']) ?></b></td><td><?= e($r['email']) ?></td><td><?= e($r['employee_id']) ?></td><td><?= e($r['designation']) ?></td>
  <td><?= (int)$r['periods'] ?> <span class="muted small">in <?= (int)$r['classes'] ?> classes</span></td><td><?= $r['active'] ? badge('Active', 'green') : badge('Disabled', 'red') ?></td>
  <td class="right" style="white-space:nowrap"><a class="btn btn-sm" href="/admin/export?what=faculty&amp;faculty=<?= (int)$r['id'] ?>" title="Download this teacher's attendance">&#8681; Attendance</a> <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button name="action" value="reset" class="btn btn-sm"<?= confirm_attr('Generate a new temporary password?') ?>>Reset pwd</button>
  <button name="action" value="toggle" class="btn btn-sm"><?= $r['active'] ? 'Disable' : 'Enable' ?></button></form></td></tr>
<?php endforeach; if (!$rows) echo empty_row(7, 'No faculty yet.'); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
