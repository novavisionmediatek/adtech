<?php public_header('Privacy & Terms', 'How student, parent and faculty data is collected, used, protected and deleted.'); ?>
<section class="page-hero"><div class="container"><h1>Privacy &amp; Data Protection</h1><p>Plain-language notice. Sample text &mdash; to be reviewed by legal counsel before launch.</p></div></section>
<section class="section"><div class="container split">
<div class="prose">
<h2 style="margin-top:0">Summary</h2>
<div class="callout"><ul class="check" style="margin:0"><li>We collect only what a school needs to run attendance, homework and communication.</li><li>We never sell data and never show advertisements.</li><li>Each person sees only the data needed for their role.</li><li>Parents can ask the school to correct or delete their child's data.</li></ul></div>

<h2>1. What we collect</h2>
<p>The categories below are entered by the school or by users themselves:</p>
<div class="compare" style="margin:0 0 1.4em"><table><tr><th>Who</th><th>Data</th></tr>
<tr><td>School</td><td>Name, UDISE+ code, board, address, phone, e-mail, principal's name</td></tr>
<tr><td>Student</td><td>Name, class, admission and roll number, date of birth, gender, address (optional)</td></tr>
<tr><td>School records</td><td>Exam marks, fee dues and payments, leave requests, homework files, certificates, library loans and bus stop</td></tr>
<tr><td>Admission applicant</td><td>Child's name, date of birth, class wanted and previous school; parent's name, mobile, e-mail and address</td></tr>
<tr><td>SMS / WhatsApp</td><td>Parent's mobile number, used only when the parent has switched these alerts on</td></tr>
<tr><td>Parent</td><td>Name, e-mail, phone</td></tr>
<tr><td>Faculty</td><td>Name, e-mail, phone, employee ID, designation, qualification</td></tr>
<tr><td>Activity</td><td>Attendance, homework, lectures, notices, login time, IP address for security logs</td></tr></table></div>
<p>We do <b>not</b> collect Aadhaar numbers, biometrics, health records or payment details.</p>

<h2>2. Why we use it</h2>
<ul><li>Recording attendance and calculating percentages</li><li>Sending absence alerts and notices to parents</li><li>Sharing homework and lectures with the right class</li><li>Producing reports for the school and education authorities</li><li>Keeping the platform secure (login records, lockouts, audit trail)</li></ul>

<h2>3. Who can see it</h2>
<ul><li><b>Students</b> &mdash; only their own records</li><li><b>Parents</b> &mdash; only their own children</li><li><b>Faculty</b> &mdash; the classes and periods they teach</li><li><b>School admin</b> &mdash; their own school only</li><li><b>Platform super admin</b> &mdash; for support, approvals and audit; every action is logged</li></ul>
<p>We do not sell, rent or share personal data with advertisers or other third parties. E-mail is sent only through the e-mail service configured for the school or platform.</p>

<h2>4. Children's data</h2>
<p>Student records are created by the school. Parents may ask the school to view, correct or delete their child's data. We design the system to support the obligations that apply to processing children's data under the Digital Personal Data Protection Act, 2023, including purpose limitation and no tracking or targeted advertising of children.</p>

<h2>5. How we protect it</h2>
<p>Passwords are hashed, access is role-based, schools are isolated from each other, forms are CSRF-protected, files are stored privately and key actions are logged. See <a href="/compliance">Compliance &amp; Security</a> for details and for what is still planned.</p>

<h2>6. Cookies</h2>
<p>We use one essential session cookie to keep you logged in. It is HttpOnly and is not used for tracking. We do not use advertising or analytics cookies.</p>

<h2>7. How long we keep it</h2>
<p>Records are kept while the school uses the portal. When a school leaves, its data is deleted or returned on request. Login and audit logs are kept for security and accountability for a limited period decided with the school.</p>

<h2>8. Your rights</h2>
<ul><li>Ask to see what we hold about you or your child</li><li>Ask for corrections</li><li>Ask for deletion where the law allows</li><li>Withdraw consent for non-essential communication</li><li>Complain to the school or to us (see Contact)</li></ul>

<h2>9. If something goes wrong</h2>
<p>If we learn of a security incident affecting personal data, we will inform the affected school without undue delay so that it can inform families and authorities as required.</p>

<h2 id="terms">10. Terms of use</h2>
<ul><li>The portal is for lawful school administration and learning.</li><li>Keep your password confidential; you are responsible for activity under your account.</li><li>Do not try to access data you are not authorised to see, or to disrupt the service.</li><li>Teachers must upload only material they have the right to share.</li><li>Accounts that misuse the portal may be suspended.</li><li>The demo is provided "as is"; production use should be covered by a written agreement with the school.</li></ul>

<h2>11. Contact &amp; grievances</h2>
<p>Questions, requests or complaints: <b><?= e(cfg('admin_email')) ?></b> or the <a href="/contact">Contact page</a>. We aim to reply within one working day.</p>
<p class="small muted">Last updated: <?= date('d M Y') ?>. This is a sample policy for demonstration and must be reviewed by legal counsel before real-world use.</p>
</div>
<aside>
  <div class="card"><h3>On this page</h3><ul class="check"><li>What we collect</li><li>Why we use it</li><li>Who can see it</li><li>Children's data</li><li>Security &amp; cookies</li><li>Retention &amp; your rights</li><li><a href="#terms">Terms of use</a></li></ul></div>
  <div class="card"><h3>Quick answers</h3>
    <p class="small"><b>Do you sell data?</b><br>Never.</p>
    <p class="small"><b>Any ads or trackers?</b><br>None.</p>
    <p class="small"><b>Can parents ask for deletion?</b><br>Yes, through the school.</p></div>
  <div class="card"><h3>Related</h3><p><a href="/compliance">Compliance &amp; Security</a><br><a href="/contact">Contact us</a></p></div>
</aside>
</div></section>
<?php public_footer(); ?>
