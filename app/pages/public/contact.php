<?php
$err = null;
if (is_post()) {
    try {
        if (post('website') !== '') { redirect('contact'); } // honeypot
        if (time() - (int)($_SESSION['last_contact'] ?? 0) < 30) throw new RuntimeException('Please wait a few seconds before sending another message.');
        $email = strtolower(post('email'));
        if (post('name') === '' || post('message') === '') throw new RuntimeException('Name and message are required.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid e-mail address.');
        if (mb_strlen(post('message')) > 3000) throw new RuntimeException('Message is too long.');
        ins('INSERT INTO messages(name,email,phone,subject,message) VALUES(?,?,?,?,?)', [post('name'), $email, post('phone'), post('subject'), post('message')]);
        send_mail($email, post('name'), 'We received your message', mail_wrap('Thank you', '<p>Dear ' . e(post('name')) . ',</p><p>Thank you for contacting us. Our team will reply shortly.</p>'));
        send_mail(cfg('admin_email'), 'Support', 'Contact form: ' . (post('subject') ?: 'New message'), mail_wrap('Contact form', '<p><b>' . e(post('name')) . '</b> (' . e($email) . ')</p><p>' . nl2br(e(post('message'))) . '</p>'));
        $_SESSION['last_contact'] = time();
        flash('Thank you! Your message has been sent.'); redirect('contact');
    } catch (RuntimeException $x) { $err = $x->getMessage(); }
}
public_header('Contact us');
?>
<section class="page-hero"><div class="container"><h1>Contact us</h1><p>Questions, demo requests or support &mdash; we reply within one working day.</p></div></section>
<section class="section"><div class="container grid g2">
  <div class="card">
    <?php if ($err): ?><div class="alert alert-err" style="width:auto"><?= e($err) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?><div class="hp"><input name="website" tabindex="-1" autocomplete="off"></div>
      <div class="form-grid">
        <div><label>Your name *</label><input name="name" required value="<?= e(post('name')) ?>"></div>
        <div><label>E-mail *</label><input type="email" name="email" required value="<?= e(post('email')) ?>"></div>
        <div><label>Phone</label><input name="phone" value="<?= e(post('phone')) ?>"></div>
        <div><label>Subject</label><input name="subject" value="<?= e(post('subject')) ?>"></div>
        <div class="full"><label>Message *</label><textarea name="message" required><?= e(post('message')) ?></textarea></div>
      </div><p><button class="btn btn-primary">Send message</button></p>
    </form>
  </div>
  <div>
    <div class="card"><h3>Reach us</h3><p><b>E-mail:</b> <?= e(cfg('admin_email')) ?><br><b>Phone:</b> +91 00000 00000 (sample)<br><b>Hours:</b> Mon&ndash;Sat, 9:30 AM &ndash; 6:00 PM IST</p></div>
    <div class="card"><h3>For schools</h3><p class="muted">Want a walkthrough for your school or education department? Send us a message and we will schedule a live demo.</p><a class="btn" href="/signup">Register your school</a></div>
  </div>
</div></section>

<section class="section alt"><div class="container">
  <?= section_head('how can we help', 'Pick what describes you best') ?>
  <div class="values v4">
    <div class="value"><span class="ic"><?= ico('&#127979;') ?></span><h3>I run a school</h3><p>Want a walkthrough or to start a pilot? <a href="/signup"><b>Register your school</b></a> or write to us for a live demo with your own timetable.</p></div>
    <div class="value"><span class="ic"><?= ico('&#128295;') ?></span><h3>I need support</h3><p>Forgot a password? Use "Forgot password" on the login page, or ask your school admin. For anything else, send details and screenshots through the form.</p></div>
    <div class="value"><span class="ic"><?= ico('&#127963;&#65039;') ?></span><h3>I represent a department</h3><p>Education officers can request a presentation covering security, data handling and reporting. Mention your office and region.</p></div>
    <div class="value"><span class="ic"><?= ico('&#129309;') ?></span><h3>Careers &amp; partnerships</h3><p>Looking to join the team or collaborate? See <a href="/careers"><b>Careers</b></a> or use the subject "Partnership" in the form.</p></div>
  </div>
</div></section>

<section class="section"><div class="container">
  <?= section_head('what happens next', 'After you send a message') ?>
  <div class="steps4">
    <div><h3>You get a confirmation</h3><p>An automatic e-mail confirms we received your message.</p></div>
    <div><h3>We read it</h3><p>A real person from the team reads every message, usually the same day.</p></div>
    <div><h3>We reply</h3><p>You hear from us within one working day (Mon&ndash;Sat, 9:30 AM &ndash; 6:00 PM IST).</p></div>
    <div><h3>Demo or pilot</h3><p>If you want, we schedule a demo and plan a small pilot for your school.</p></div>
  </div>
</div></section>

<section class="section alt"><div class="container">
  <?= section_head('quick answers', 'Contact - FAQ') ?>
  <?= faq_html([
    ['How do I get a demo?', '<p>Log in with the demo accounts on the <a href="/login">login page</a>, or send a message asking for a guided demo.</p>'],
    ['I am a parent and cannot log in.', '<p>Ask your child\'s school office. The school admin can reset your password and confirm the e-mail address on file.</p>'],
    ['I did not receive an absence alert.', '<p>Check your spam folder and ask the school to verify your e-mail address. The school admin can also see the alert in the e-mail log.</p>'],
    ['How do I report a security problem?', '<p>Please write to us immediately with "Security" as the subject and a short description. Do not share personal data publicly.</p>'],
    ['Can I visit your office?', '<p>Currently we work remotely and meet schools online or at their campus by appointment.</p>'],
  ]) ?>
</div></section>
<?php public_footer(); ?>
