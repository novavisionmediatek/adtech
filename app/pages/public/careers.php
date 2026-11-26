<?php
$jobId = (int)(explode('/', trim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'))[1] ?? 0);
if ($jobId) {
    $j = row('SELECT * FROM jobs WHERE id=? AND active=1', [$jobId]);
    if (!$j) render_error(404, 'Position not found', 'This opening is closed or does not exist.');
    $err = null;
    if (is_post()) {
        try {
            $email = strtolower(post('email'));
            if (post('name') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Name and a valid e-mail are required.');
            $file = save_upload('resume', 'resumes', ['pdf', 'doc', 'docx'], 2 * 1048576);
            ins('INSERT INTO applications(job_id,name,email,phone,message,file_path,file_name) VALUES(?,?,?,?,?,?,?)',
                [$jobId, post('name'), $email, post('phone'), post('message'), $file['path'] ?? null, $file['name'] ?? null]);
            send_mail($email, post('name'), 'Application received: ' . $j['title'], mail_wrap('Application received', '<p>Dear ' . e(post('name')) . ', thank you for applying for <b>' . e($j['title']) . '</b>. We will contact you if your profile matches.</p>'));
            flash('Application submitted. Thank you!'); redirect('careers');
        } catch (RuntimeException $x) { $err = $x->getMessage(); }
    }
    public_header($j['title'] . ' - Careers');
    ?>
    <section class="page-hero"><div class="container"><h1><?= e($j['title']) ?></h1><p><?= e($j['dept']) ?> &middot; <?= e($j['location']) ?> &middot; <?= e($j['type']) ?></p></div></section>
    <section class="section"><div class="container grid g2">
      <div class="card"><h3>About the role</h3><p><?= nl2br(e($j['description'])) ?></p></div>
      <div class="card"><h3>Apply now</h3>
        <?php if ($err): ?><div class="alert alert-err" style="width:auto"><?= e($err) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
          <p><label>Full name *</label><input name="name" required value="<?= e(post('name')) ?>"></p>
          <p><label>E-mail *</label><input type="email" name="email" required value="<?= e(post('email')) ?>"></p>
          <p><label>Phone</label><input name="phone" value="<?= e(post('phone')) ?>"></p>
          <p><label>Resume (PDF/DOC, max 2 MB)</label><input type="file" name="resume" accept=".pdf,.doc,.docx"></p>
          <p><label>Why should we hire you?</label><textarea name="message"><?= e(post('message')) ?></textarea></p>
          <button class="btn btn-primary">Submit application</button></form></div>
    </div></section>
    <?php public_footer(); return;
}
$jobs = q('SELECT * FROM jobs WHERE active=1 ORDER BY created_at DESC');
public_header('Careers', 'Join the team building technology for Indian schools.');
?>
<section class="page-hero"><div class="container"><h1>Careers</h1><p>Help us bring simple, secure technology to every classroom.</p></div></section>
<section class="section"><div class="container split">
  <div class="prose">
    <span class="kicker" style="text-align:left">join us</span>
    <h2 style="margin-top:0">Build tools that reach real classrooms</h2>
    <p>We are a small team building software that teachers, parents and principals use every single day. A feature you ship on Monday can save a teacher ten minutes on Tuesday and tell a parent about their child's absence the same afternoon.</p>
    <p>We value people who care about schools, write clear code or content, and like to finish what they start. You do not need a famous employer on your CV &mdash; show us what you have built or taught.</p>
  </div>
  <div class="numbers" style="margin:0">
    <div><b><?= count($jobs) ?></b><span>open positions</span></div><div><b>Remote</b><span>friendly roles</span></div>
    <div><b>100%</b><span>focus on education</span></div><div><b>Small</b><span>team, big ownership</span></div>
  </div>
</div></section>

<section class="section alt"><div class="container">
  <?= section_head('open positions', 'Where you could fit in') ?><br>
  <div style="max-width:860px;margin:0 auto">
  <?php foreach ($jobs as $j): ?>
    <div class="card"><div class="card-head"><div><h3><?= e($j['title']) ?></h3><span class="muted"><?= e($j['dept']) ?> &middot; <?= e($j['location']) ?> &middot; <?= e($j['type']) ?></span></div>
      <a class="btn btn-primary" href="/careers/<?= (int)$j['id'] ?>">Apply</a></div><p><?= e($j['description']) ?></p></div>
  <?php endforeach; if (!$jobs) echo '<div class="card center muted">No open positions right now. Check back soon.</div>'; ?>
  <div class="callout center-text"><b>Do not see your role?</b> Tell us how you would help schools &mdash; send a short note from the <a href="/contact">Contact page</a> with the subject "Careers".</div>
  </div>
</div></section>

<section class="section"><div class="container">
  <?= section_head('life here', 'Why people enjoy working with us') ?>
  <div class="values">
    <?php foreach ([['&#127979;', 'Purpose you can see', 'Every release helps schools: faster attendance, informed parents, less paperwork.'], ['&#127968;', 'Flexible work', 'Remote-friendly roles with sensible hours. We care about output, not desk time.'], ['&#128218;', 'Learn constantly', 'Work across security, product and education. Time set aside for learning and reading.'], ['&#129309;', 'Real ownership', 'Small team means you own features from idea to launch and hear user feedback directly.'], ['&#128274;', 'Respect for privacy', 'We handle children\'s data, so we practise careful, ethical engineering.'], ['&#127881;', 'Fair and kind', 'Clear expectations, honest feedback, and no blame culture when things break.']] as $i => [$ic, $t, $d]): ?>
      <div class="value"><span class="no">0<?= $i + 1 ?></span><span class="ic"><?= ico($ic) ?></span><h3><?= e($t) ?></h3><p><?= $d ?></p></div>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="section alt"><div class="container">
  <?= section_head('how we hire', 'A simple, respectful process') ?>
  <div class="steps4">
    <div><h3>Apply</h3><p>Send your details and a resume (PDF/DOC). A short note about why you want this role helps.</p></div>
    <div><h3>Intro chat</h3><p>A 20-minute call to understand your experience and answer your questions.</p></div>
    <div><h3>Practical task</h3><p>A small, realistic exercise related to the role &mdash; paid or time-boxed, never a week of free work.</p></div>
    <div><h3>Decision</h3><p>We share feedback and a clear answer within a few working days.</p></div>
  </div>
</div></section>

<section class="section"><div class="container">
  <?= section_head('questions', 'Careers - FAQ') ?>
  <?= faq_html([
    ['Do I need prior education-sector experience?', '<p>No. Curiosity about how schools work matters more. We will teach you the domain.</p>'],
    ['Can I work remotely?', '<p>Most roles are remote-friendly. Each posting lists its location and type.</p>'],
    ['Do you hire freshers and interns?', '<p>We sometimes do. Apply for the closest role or write from the Contact page with your portfolio.</p>'],
    ['What happens to my resume?', '<p>It is stored privately and visible only to the hiring team. See our <a href="/privacy">privacy notice</a>.</p>'],
    ['How long until I hear back?', '<p>We try to respond within one week of your application.</p>'],
  ]) ?>
</div></section>
<?php public_footer(); ?>
