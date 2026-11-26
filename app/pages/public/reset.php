<?php
header('Referrer-Policy: no-referrer');
$token = (string)(is_post() ? post('token') : inp('token'));
$target = reset_lookup($token); $err = null;
if (is_post() && $target) {
    $new = (string)($_POST['new'] ?? '');
    if ($x = password_error($new)) $err = $x;
    elseif ($new !== ($_POST['new2'] ?? '')) $err = 'The two passwords do not match.';
    else { reset_complete($target, $new); $_SESSION = []; flash('Your password has been changed. Please log in with the new one.'); redirect('login'); }
}
public_header('Set a new password');
?>
<section class="page-hero"><div class="container"><h1>Set a new password</h1></div></section>
<section class="auth"><div class="card">
<?php if (!$target): ?>
  <h3>This link no longer works</h3><p class="muted">It may be old or already used.</p><p><a class="btn btn-primary" href="/forgot">Ask for a new link</a></p>
<?php else: ?>
  <?php if ($err): ?><div class="alert alert-err" style="width:auto"><?= e($err) ?></div><?php endif; ?>
  <p class="muted">Account: <b><?= e($target['role'] === 'student' ? strtoupper((string)$target['username']) : ($target['email'] ?: $target['username'])) ?></b></p>
  <form method="post" action="/reset"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
    <p><label>New password (8+ characters, letters and numbers)</label><input type="password" name="new" required minlength="8" autocomplete="new-password" autofocus></p>
    <p><label>Type it again</label><input type="password" name="new2" required minlength="8" autocomplete="new-password"></p>
    <button class="btn btn-primary btn-lg" style="width:100%">Save new password</button></form>
<?php endif; ?>
</div></section>
<?php public_footer(); ?>
