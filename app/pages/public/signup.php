<?php
if (me()) redirect(ROLE_HOME[me()['role']]);
$f = $_POST; $err = null;
if (is_post()) {
    try {
        $email = strtolower(post('email'));
        if (post('name') === '' || post('principal') === '' || post('city') === '' || post('state') === '') throw new RuntimeException('Please fill all required fields.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid e-mail address.');
        if (val('SELECT 1 FROM users WHERE lower(email)=?', [$email])) throw new RuntimeException('This e-mail is already registered.');
        if ($pe = password_error($_POST['password'] ?? '')) throw new RuntimeException($pe);
        if (($_POST['password'] ?? '') !== ($_POST['password2'] ?? '')) throw new RuntimeException('Passwords do not match.');
        if (empty($_POST['agree'])) throw new RuntimeException('Please accept the terms and data-protection notice.');
        $id = register_school(
            ['name' => post('name'), 'udise' => post('udise'), 'board' => post('board'), 'address' => post('address'), 'city' => post('city'), 'state' => post('state'), 'pincode' => post('pincode'), 'phone' => post('phone'), 'principal' => post('principal')],
            ['name' => post('principal'), 'email' => $email, 'phone' => post('phone'), 'password' => $_POST['password']], 'pending');
        run('INSERT INTO audit(school_id,action,detail,ip) VALUES(?,?,?,?)', [$id, 'school_registered', post('name'), client_ip()]);
        welcome_mail(['name' => post('principal'), 'email' => $email, 'role' => 'school_admin'], $email, (string)$_POST['password'], $id,
            '<p>We have received the registration of <b>' . e(post('name')) . '</b>. <b>You can log in after our team verifies and approves your school</b> - we will e-mail you then.</p>', false);
        $mailed = mail_summary();
        send_mail(cfg('admin_email'), 'Platform admin', 'New school registration: ' . post('name'), mail_wrap('New registration', '<p>' . e(post('name')) . ', ' . e(post('city')) . ', ' . e(post('state')) . ' is awaiting approval.</p>'), null);
        flash('Registration submitted. You can log in after the platform administrator approves your school.' . (str_contains($mailed, 'e-mailed') ? ' A welcome e-mail with your login details has been sent to ' . $email . '.' : ''));
        redirect('login');
    } catch (RuntimeException $x) { $err = $x->getMessage(); }
}
$v = fn(string $k) => e($f[$k] ?? '');
public_header('Register your school');
?>
<section class="page-hero"><div class="container"><h1>Register your school</h1><p>Takes two minutes. Your account is activated after verification.</p></div></section>
<section class="auth wide"><div class="card">
  <?php if ($err): ?><div class="alert alert-err" style="width:auto"><?= e($err) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <h3>School details</h3>
    <div class="form-grid">
      <div class="full"><label>School name *</label><input name="name" required value="<?= $v('name') ?>"></div>
      <div><label>UDISE+ code</label><input name="udise" maxlength="20" value="<?= $v('udise') ?>"></div>
      <div><label>Board</label><select name="board"><?php foreach (['CBSE', 'ICSE', 'State Board', 'Other'] as $b) echo '<option' . (($f['board'] ?? '') === $b ? ' selected' : '') . '>' . $b . '</option>'; ?></select></div>
      <div class="full"><label>Address</label><input name="address" value="<?= $v('address') ?>"></div>
      <div><label>City / District *</label><input name="city" required value="<?= $v('city') ?>"></div>
      <div><label>State *</label><input name="state" required value="<?= $v('state') ?>"></div>
      <div><label>PIN code</label><input name="pincode" maxlength="6" value="<?= $v('pincode') ?>"></div>
      <div><label>School phone</label><input name="phone" value="<?= $v('phone') ?>"></div>
    </div>
    <h3 style="margin-top:22px">Administrator account</h3>
    <div class="form-grid">
      <div><label>Principal / Admin name *</label><input name="principal" required value="<?= $v('principal') ?>"></div>
      <div><label>E-mail (login) *</label><input type="email" name="email" required value="<?= $v('email') ?>"></div>
      <div><label>Password * (8+ chars, letters &amp; numbers)</label><input type="password" name="password" required minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" title="At least 8 characters with letters and numbers" autocomplete="new-password" data-pw></div>
      <div><label>Confirm password *</label><input type="password" name="password2" required minlength="8" autocomplete="new-password" data-pw data-pw-confirm="password"></div>
      <div class="full pw-opts"><label><input type="checkbox" data-showpw> Show password</label></div>
    </div>
    <p><label style="font-weight:400"><input type="checkbox" name="agree" value="1"> I agree to the <a href="/privacy#terms" target="_blank">terms</a> and the <a href="/privacy" target="_blank">data-protection notice</a>. I am authorised to register this school.</label></p>
    <button class="btn btn-primary btn-lg">Submit registration</button>
  </form>
</div></section>
<?php public_footer(); ?>
