<?php
if (me()) redirect('account/password');
if (is_post()) {
    reset_request(post('id'));
    flash('If an account exists for what you typed, a password reset link has been sent to its e-mail address (for a student: to the parent). It expires in ' . RESET_MINUTES . ' minutes.');
    redirect('forgot');
}
public_header('Forgot password');
$mailOn = !empty(cfg('smtp', [])['host']);
?>
<section class="page-hero"><div class="container"><h1>Forgot your password?</h1><p>We will e-mail you a link to set a new one.</p></div></section>
<section class="auth"><div class="card">
  <form method="post"><?= csrf_field() ?>
    <p><label>E-mail or User ID</label><input name="id" required autofocus autocomplete="username" placeholder="e.g. you@school.in or DPS-6A01"></p>
    <button class="btn btn-primary btn-lg" style="width:100%">Send reset link</button>
  </form>
  <?php if (!$mailOn): ?><p class="small muted" style="margin-top:14px"><?= cfg('demo_mode') ? 'Demo: e-mail is not set up, so the message is only saved in the Email log (school admin or super admin).' : 'E-mail sending is not set up on this portal yet. Please ask your school admin to reset your password.' ?></p><?php endif; ?>
  <p class="small muted" style="margin:14px 0 0"><a href="/login">&larr; Back to login</a></p>
</div></section>
<?php public_footer(); ?>
