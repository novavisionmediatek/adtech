<?php
if (me()) redirect(ROLE_HOME[me()['role']]);
if (is_post()) {
    $u = attempt_login(post('id'), $_POST['password'] ?? '');
    if ($u) {
        flash('Welcome back, ' . $u['name'] . '.');
        redirect($u['must_change'] ? 'account/password' : ROLE_HOME[$u['role']]);
    }
    if (empty($_SESSION['flash'])) flash('Invalid user ID or password.', 'err');
}
public_header('Login');
?>
<section class="login-split">
  <div class="login-art">
    <span class="doodle d1" style="top:10%;left:60%">&#10022;</span><span class="doodle d3" style="top:70%;right:10%;font-size:44px">&#9733;</span>
    <h2>Good morning, <span class="hl" style="color:var(--gold2)">class!<svg viewBox="0 0 200 12" preserveAspectRatio="none"><path d="M2 8 Q 25 0 50 7 T 100 6 T 150 7 T 198 4" fill="none" stroke="#f5a524" stroke-width="5" stroke-linecap="round"/></svg></span></h2>
    <p>Attendance, homework and parents &mdash; all one login away.</p>
    <div class="eq">&#10003; 5 roles &nbsp;&middot;&nbsp; &#10003; same-day alerts &nbsp;&middot;&nbsp; &#10003; secure</div>
  </div>
  <div class="login-form"><div class="wrapf">
    <div class="card">
      <h2>Login</h2><p class="muted">Admins, faculty and parents use e-mail. Students use their Student ID (e.g. DPS-6A01).</p>
      <form method="post"><?= csrf_field() ?>
        <p><label>E-mail / User ID</label><input name="id" required autofocus autocomplete="username" value="<?= e(post('id')) ?>"></p>
        <p><label>Password</label><input type="password" name="password" required autocomplete="current-password"></p>
        <button class="btn btn-primary btn-lg" style="width:100%">Login &rarr;</button>
      </form>
      <p class="small muted" style="margin:16px 0 0">New school? <a href="/signup"><b>Register here</b></a> &middot; <a href="/forgot"><b>Forgot password?</b></a></p>
      <p class="small muted" style="margin:8px 0 0">New admission? <a href="/admission"><b>Apply for admission</b></a></p>
    </div>
    <?php if (cfg('demo_mode') && demo_password() !== ''): ?>
    <div class="demo-box"><b>Demo logins</b> &mdash; password for all: <code><?= e(demo_password()) ?></code>
      <table>
        <tr><td>Super Admin</td><td>superadmin@vidyasetu.test</td></tr>
        <tr><td>School Admin</td><td>admin@dps.test</td></tr>
        <tr><td>Faculty</td><td>anita.verma@dps.test</td></tr>
        <tr><td>Student</td><td>DPS-6A01</td></tr>
        <tr><td>Parent</td><td>parent1@example.test</td></tr>
      </table></div>
    <?php endif; ?>
  </div></div>
</section>
<?php public_footer(); ?>
