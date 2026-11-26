<?php public_header('Compliance & Security', 'How the portal protects student data, what is implemented today, and the path to formal certification.'); ?>
<section class="page-hero"><div class="container"><h1>Compliance &amp; Security</h1><p>Designed for public-sector scrutiny from day one.</p></div></section>

<section class="section"><div class="container">
  <?= section_head('at a glance', 'Security you can verify') ?>
  <div class="numbers">
    <div><b>5</b><span>roles with separate permissions</span></div>
    <div><b>5 tries</b><span>then the account locks for 15 minutes</span></div>
    <div><b>0</b><span>third-party trackers or ad scripts</span></div>
    <div><b>100%</b><span>of forms protected by CSRF tokens</span></div>
  </div>
  <div class="trust">
    <span><?= ico('&#128273;') ?> bcrypt password hashing</span><span><?= ico('&#128737;') ?> Role-based access</span><span><?= ico('&#127970;') ?> School-level data isolation</span><span><?= ico('&#128220;') ?> Audit trail</span><span><?= ico('&#128193;') ?> Private file storage</span><span><?= ico('&#11015;') ?> CSV export</span>
  </div>
</div></section>

<section class="section alt"><div class="container">
  <?= section_head('what is implemented', 'Security controls in the product', 'Everything below exists in the code today and can be demonstrated live.') ?>
  <div class="grid g3" style="margin-top:34px">
    <div class="card" style="margin:0"><h3><?= ico('&#128273;') ?> Access control</h3><ul class="check">
      <li>Passwords stored with bcrypt; strength rules enforced</li>
      <li>Account lockout after 5 failed logins (15 minutes)</li>
      <li>Temporary passwords with forced change on first login</li>
      <li>Five roles: super admin, school admin, faculty, student, parent</li>
      <li>Every page checks the role; students see only themselves, parents only their children</li>
      <li>School-level isolation: a school can never see another school's data</li></ul></div>
    <div class="card" style="margin:0"><h3><?= ico('&#128737;') ?> Application security</h3><ul class="check">
      <li>CSRF token on every form</li>
      <li>Prepared SQL statements throughout (no SQL injection)</li>
      <li>Output escaping against cross-site scripting (XSS)</li>
      <li>Rich-text content cleaned by a whitelist sanitizer</li>
      <li>Security headers: CSP, frame protection, nosniff, referrer policy</li>
      <li>Session cookies are HttpOnly and SameSite</li></ul></div>
    <div class="card" style="margin:0"><h3><?= ico('&#128193;') ?> Data &amp; files</h3><ul class="check">
      <li>Uploads limited by type and size, saved under random names</li>
      <li>Files stored outside the web root and served only to authorised users</li>
      <li>Audit log of logins and key administrative actions</li>
      <li>E-mail log keeps proof of every alert sent</li>
      <li>Attendance export (CSV) for inspections and audits</li>
      <li>No Aadhaar, biometrics or payment data collected</li></ul></div>
  </div>
</div></section>

<section class="section"><div class="container split">
  <div>
    <?= section_head('data protection', 'Principles we follow') ?>
    <div class="card" style="margin-top:30px"><ul class="check">
      <li><b>Purpose limitation</b> &mdash; data is used only for school administration</li>
      <li><b>Data minimisation</b> &mdash; we ask only for what the features need</li>
      <li><b>Parent contact</b> &mdash; used only for attendance and school communication</li>
      <li><b>No advertising</b> &mdash; no selling or sharing of data with third parties</li>
      <li><b>Children's data</b> &mdash; designed to support obligations under the Digital Personal Data Protection Act, 2023</li>
      <li><b>Correction &amp; deletion</b> &mdash; schools can correct records; deletion on request</li></ul></div>
  </div>
  <div>
    <h3 style="margin-top:6px">Life of a record</h3>
    <div class="timeline" style="margin-top:20px">
      <div class="tl-item"><small>1 &middot; Collect</small><p>School staff enter a child's details; the portal stores only what is needed.</p></div>
      <div class="tl-item"><small>2 &middot; Use</small><p>Teachers mark attendance and post homework. Parents see their child's data only.</p></div>
      <div class="tl-item"><small>3 &middot; Review</small><p>Admins run reports; every important action is logged with who and when.</p></div>
      <div class="tl-item"><small>4 &middot; Export</small><p>Records can be exported for audits. The school owns its data.</p></div>
      <div class="tl-item"><small>5 &middot; Delete</small><p>On request or when a school leaves, data is deleted or handed back.</p></div>
    </div>
  </div>
</div></section>

<section class="section alt"><div class="container">
  <?= section_head('national priorities', 'Alignment with education initiatives') ?>
  <div class="compare"><table><tr><th style="width:30%">Initiative</th><th>How this portal supports it</th></tr>
    <tr><td><b>NEP 2020</b></td><td>Technology in school administration, parent involvement, data for learning outcomes</td></tr>
    <tr><td><b>Digital India</b></td><td>Paperless attendance and communication; mobile-friendly access</td></tr>
    <tr><td><b>UDISE+</b></td><td>Stores the school's UDISE+ code; attendance data can be exported for reporting</td></tr>
    <tr><td><b>RTE / attendance rules</b></td><td>Automatic percentage tracking against the minimum-attendance threshold</td></tr>
    <tr><td><b>Samagra Shiksha (monitoring)</b></td><td>Reliable, auditable, period-wise attendance evidence</td></tr>
    <tr><td><b>DPDP Act, 2023</b></td><td>Purpose limitation, minimisation and a clear grievance contact; parental-consent flows planned</td></tr></table></div>
</div></section>

<section class="section"><div class="container split">
  <div>
    <?= section_head('who does what', 'Shared responsibility') ?>
    <div class="compare"><table><tr><th>Area</th><th>Platform</th><th>School</th></tr>
      <tr><td>Software security &amp; updates</td><td>Yes</td><td>&ndash;</td></tr>
      <tr><td>Creating accounts &amp; roles</td><td>Provides tools</td><td>Yes</td></tr>
      <tr><td>Accuracy of student data</td><td>&ndash;</td><td>Yes</td></tr>
      <tr><td>Keeping passwords secret</td><td>Enforces rules</td><td>Every user</td></tr>
      <tr><td>Hosting &amp; backups</td><td>Recommends</td><td>Per agreement</td></tr>
      <tr><td>Responding to parent requests</td><td>Supports</td><td>Yes</td></tr></table></div>
  </div>
  <div class="callout" style="margin-top:0">
    <h3 style="margin-top:0">What we do <u>not</u> claim</h3>
    <p>These are <b>design alignments, not certifications</b>. This demo has not yet undergone an independent security audit, and we do not hold any government approval. Before a production roll-out we recommend:</p>
    <ul class="check"><li>A third-party security audit / VAPT by a CERT-In empanelled auditor</li><li>Hosting on government-approved cloud, with data stored in India</li><li>Encrypted backups and a tested disaster-recovery plan</li><li>HTTPS everywhere and optional two-factor login for admins</li><li>Parental consent and data-request workflows for DPDP</li></ul>
  </div>
</div></section>

<section class="section alt"><div class="container">
  <?= section_head('for officials', 'Questions an inspector might ask') ?>
  <?= faq_html([
    ['Can a school see another school\'s data?', '<p>No. Every query is restricted by school. Only the platform super admin has a cross-school view, and that role is audited.</p>'],
    ['Who can change attendance after it is marked?', '<p>Only the teacher who owns the period, within 7 days. Changes are recorded with the teacher and time.</p>'],
    ['How do we prove a parent was informed?', '<p>Every alert is stored in the e-mail log with recipient, subject, time and delivery status, visible to the school admin.</p>'],
    ['What happens if someone guesses passwords?', '<p>After 5 failed attempts the account is locked for 15 minutes, and each failed attempt is written to the audit log.</p>'],
    ['Where are uploaded files kept?', '<p>Outside the public web folder, under random names, and delivered only after a permission check for the specific class.</p>'],
    ['Can data be taken out of the system?', '<p>Yes. Attendance reports export to CSV, so the school is never locked in.</p>'],
    ['Is the system ready for a statewide roll-out today?', '<p>No &mdash; it is ready for a <b>pilot</b>. A statewide deployment would need the audit, hosting and scale steps listed above.</p>'],
  ]) ?>
</div></section>
<?= cta_band('Planning a government pilot?', 'We will walk your team through the security design and run a supervised pilot with a few classes.', 'Contact us', '/contact', 'Read the privacy notice', '/privacy') ?>
<?php public_footer(); ?>
