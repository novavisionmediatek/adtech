<?php
$u = me();
if (is_post()) {
    $cur = $_POST['current'] ?? ''; $new = $_POST['new'] ?? '';
    if (!password_verify($cur, $u['password_hash'])) flash('Current password is incorrect.', 'err');
    elseif ($e = password_error($new)) flash($e, 'err');
    elseif ($new !== ($_POST['new2'] ?? '')) flash('New passwords do not match.', 'err');
    elseif ($new === $cur) flash('New password must be different.', 'err');
    else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        run('UPDATE users SET password_hash=?, must_change=0 WHERE id=?', [$hash, $u['id']]);
        $_SESSION['pwm'] = password_mark($hash); // stay signed in here, sign out elsewhere
        audit('password_changed'); flash('Password updated.'); redirect(ROLE_HOME[$u['role']]);
    }
    redirect('account/password');
}
dash_header('Change password');
?>
<div class="card" style="max-width:480px"><form method="post"><?= csrf_field() ?>
  <p><label>Current password</label><input type="password" name="current" required autocomplete="current-password"></p>
  <p><label>New password (8+ chars, letters &amp; numbers)</label><input type="password" name="new" required minlength="8" autocomplete="new-password"></p>
  <p><label>Confirm new password</label><input type="password" name="new2" required minlength="8" autocomplete="new-password"></p>
  <button class="btn btn-primary">Update password</button></form></div>
<?php dash_footer(); ?>
