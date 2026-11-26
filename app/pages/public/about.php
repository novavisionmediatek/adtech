<?php public_header('About us', 'Why we are building a simple, secure portal for Indian schools - our mission, values and roadmap.'); ?>
<section class="page-hero"><div class="container"><h1>About <?= e(cfg('app_name')) ?></h1><p>A simple, secure bridge between schools, teachers, students and parents.</p></div></section>

<section class="section"><div class="container split">
  <div class="prose">
    <span class="kicker" style="text-align:left">our mission</span>
    <h2 style="margin-top:0">Every child counted, every parent informed</h2>
    <p>In many schools the attendance register is still a paper book that is filled once a day. A child can be present in the morning and quietly leave after the second period &mdash; and nobody at home knows until the monthly report. Homework is shared in WhatsApp groups where it is buried within hours. Principals spend days adding up percentages by hand before an inspection.</p>
    <p><b><?= e(cfg('app_name')) ?></b> (<i>Vidya</i> = learning, <i>Setu</i> = bridge) is a school management portal that fixes these everyday problems. Attendance is recorded <b>period by period and subject by subject</b>, homework and recorded lectures live in one place, and when a child is absent the parent gets an e-mail the same day.</p>
    <p>We built it for the realities of Indian schools: teachers who handle several classes, parents with one phone for the whole family, principals who must show clean records to education officers, and tight budgets that cannot afford heavy software.</p>
    <h3>Who it is for</h3>
    <ul class="check"><li><b>Principals &amp; school admins</b> &mdash; one place to run classes, staff, timetable, notices and reports.</li><li><b>Teachers</b> &mdash; a single day view for every period, across every class they teach.</li><li><b>Students</b> &mdash; their own attendance, homework, lectures and timetable.</li><li><b>Parents</b> &mdash; live visibility of each child, with instant absence alerts.</li><li><b>Education officers</b> &mdash; reliable, exportable, auditable attendance evidence.</li></ul>
  </div>
  <div>
    <div class="card"><h3>Problems we solve</h3><ul class="check"><li>No visibility into period-wise bunking</li><li>Parents informed late &mdash; or never</li><li>Manual attendance-percentage calculation</li><li>Teachers juggling many classes with no single view</li><li>Homework and notes lost in chat groups</li><li>Absent students falling behind with no way to catch up</li><li>Messy records at inspection time</li></ul></div>
    <div class="card"><h3>Aligned with national priorities</h3><p class="muted">The portal supports goals of NEP 2020 (technology integration, parent involvement), Digital India and data-driven monitoring of attendance. See the <a href="/compliance"><b>compliance page</b></a> for exactly what is and is not claimed.</p></div>
    <div class="callout"><b>Honest note:</b> <?= e(cfg('app_name')) ?> is a young product. This site runs on demo data so you can try every dashboard safely. We would rather earn trust with a small successful pilot than make big promises.</div>
  </div>
</div></section>

<section class="section alt"><div class="container">
  <?= section_head('what we believe', 'Our values') ?>
  <div class="values">
    <?php foreach ([['&#127891;', 'Teachers first', 'If a teacher needs training for an hour, we failed. Marking a period takes a few taps, and Present is pre-selected.'], ['&#128274;', "Children's data is sacred", 'Student data belongs to the school and the family. No advertising, no selling, no sharing with third parties.'], ['&#128269;', 'Transparent by default', 'Logins and important actions are logged. Schools and authorities can see what happened and when.'], ['&#127970;', 'Fair for every school', 'A small village school deserves the same quality of tools as a big city campus. Lightweight, works on basic phones.'], ['&#129309;', 'Parents as partners', 'Communication should be respectful, timely and in plain language &mdash; not a fear-based message.'], ['&#128260;', 'Simple beats clever', 'We choose fewer, clearer features over a crowded screen nobody understands.']] as $i => [$ic, $t, $d]): ?>
      <div class="value"><span class="no">0<?= $i + 1 ?></span><span class="ic"><?= ico($ic) ?></span><h3><?= e($t) ?></h3><p><?= $d ?></p></div>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="section"><div class="container">
  <?= section_head('how we build', 'Principles behind the product') ?>
  <div class="steps4">
    <div><h3>Security from day one</h3><p>Hashed passwords, role-based access, school-level isolation, CSRF protection and an audit trail are part of the base &mdash; not add-ons.</p></div>
    <div><h3>Built with schools</h3><p>Features come from real school routines: periods, sections, class teachers, the 75% rule, parent-teacher communication.</p></div>
    <div><h3>Light &amp; fast</h3><p>No heavy frameworks or trackers. Pages load quickly even on slow connections and low-end phones.</p></div>
    <div><h3>Your data, portable</h3><p>Attendance can be exported as CSV at any time. A school is never locked in.</p></div>
  </div>
</div></section>

<section class="section alt"><div class="container">
  <?= section_head('where we are going', 'Roadmap', 'What exists today, and what we plan next. Priorities will follow what pilot schools ask for.') ?>
  <div class="timeline">
    <div class="tl-item"><small>Available now</small><h3>Core school operations</h3><p>Period-wise attendance, same-day parent e-mail alerts, homework with online submission, recorded lectures and notes, clash-free timetable, leave requests, notices, Excel reports, five role-based dashboards and an audit log.</p></div>
    <div class="tl-item"><small>Available now</small><h3>Exams, fees &amp; school office</h3><p>Marks and printable report cards, fee records and receipts, online admission applications, certificates, library and bus routes.</p></div>
    <div class="tl-item"><small>Available now</small><h3>Hindi, SMS &amp; WhatsApp ready</h3><p>Main menus and screens can be shown in Hindi. SMS and WhatsApp alerts work once a school connects an approved provider, and only for parents who agree.</p></div>
    <div class="tl-item"><small>Next</small><h3>Full Hindi &amp; regional languages</h3><p>Translate every page, then add other Indian languages. Online fee payment through an approved gateway.</p></div>
    <div class="tl-item"><small>Planned</small><h3>Security audit &amp; hosting in India</h3><p>Independent security audit and deployment on government-approved cloud infrastructure with data stored in India.</p></div>
  </div>
</div></section>

<section class="section"><div class="container">
  <?= section_head('questions', 'About us - FAQ') ?>
  <?= faq_html([
    ['Who is behind ' . cfg('app_name') . '?', '<p>A small product team that builds software for schools. We are in the pilot stage and work directly with school staff to improve the product.</p>'],
    ['Is this a government product?', '<p>No. It is an independent platform designed to <b>align with</b> government priorities such as NEP 2020 and Digital India. It is not certified or endorsed by any authority.</p>'],
    ['Can a school really start in a day?', '<p>Yes, for the basics: register the school, get approved, add classes and subjects, import students by CSV, build the timetable &mdash; and teachers can start marking attendance.</p>'],
    ['Where is the data stored?', '<p>In this demo, on the server where it runs. For real deployments we recommend hosting in India on government-approved infrastructure. See <a href="/compliance">Compliance</a>.</p>'],
    ['How can we work with you?', '<p>Write to us from the <a href="/contact">Contact page</a>. We are happy to run a pilot with one or two classes first.</p>'],
  ]) ?>
</div></section>
<?= cta_band('Want to see it in your school?', 'Start with a small pilot. We will help you set it up.') ?>
<?php public_footer(); ?>
