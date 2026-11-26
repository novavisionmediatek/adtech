<?php
public_header('Features', 'Period-wise attendance, parent alerts, homework, recorded lectures, timetable, reports and role-based dashboards.');
$groups = [
 ['&#127979;', 'School Admin', ['Create classes, sections and subjects', 'Add faculty and students one by one or by CSV bulk upload', 'Parent accounts auto-created and linked to children', 'Timetable builder with teacher-clash detection', 'Attendance reports by class and date range, with CSV export', 'Oversee homework and lectures of every class', 'Publish notices to the whole school', 'E-mail log of every message sent']],
 ['&#128105;&#8205;&#127979;', 'Faculty', ['One dashboard for all periods, even across different classes', 'Mark Present / Absent / Late for each period in seconds', 'Edit attendance for up to 7 days back', 'Upload homework with attachments', 'Upload lectures: several videos, PDFs, slides and links at once', 'Class-wise attendance reports']],
 ['&#127891;', 'Students', ['Subject-wise and date-wise attendance with percentage', 'Warning when below the minimum attendance rule', 'Homework with due dates and downloads', 'Lecture library with in-page video player and subject filter', 'Personal weekly timetable']],
 ['&#128106;', 'Parents', ['Same-day e-mail when a child is absent', 'Dashboard for each child (siblings supported)', 'View homework, lectures, timetable and notices', 'History of all alerts received']],
 ['&#128081;', 'Super Admin', ['Verify and approve registered schools', 'Suspend or reactivate a school', 'Manage blog (with a rich editor), careers and contact messages', 'Platform-wide audit log and e-mail log', 'Reset any user password']],
 ['&#10024;', 'Extras we added', ['Minimum-attendance (75%) flagging', 'Absence alert mode: per period or once per day', 'Audit trail of logins and key actions', 'Mobile-friendly, works on low-end phones', 'Lightweight: no external libraries or trackers']],
];
?>
<section class="page-hero"><div class="container"><h1>Features</h1><p>Built around how Indian schools actually work.</p></div></section>

<section class="section"><div class="container">
  <?= section_head('the big four', 'What makes it different') ?>
  <div class="values v4">
    <div class="value"><span class="ic"><?= ico('&#9989;') ?></span><h3>Period-wise attendance</h3><p>Not one tick per day. Each teacher marks their own period, so a child leaving after period 2 is visible to everyone.</p></div>
    <div class="value"><span class="ic"><?= ico('&#128231;') ?></span><h3>Instant parent alerts</h3><p>Marked absent? An e-mail reaches the parent right away with the period, subject and teacher. No duplicates.</p></div>
    <div class="value"><span class="ic"><?= ico('&#127909;') ?></span><h3>Homework &amp; lectures</h3><p>Teachers share homework, notes and recorded lectures. Students who missed a class can catch up at home.</p></div>
    <div class="value"><span class="ic"><?= ico('&#128197;') ?></span><h3>Clash-free timetable</h3><p>The system will not let a teacher be in two classrooms at the same time, and shows every teacher one clean day view.</p></div>
  </div>
</div></section>

<section class="section alt"><div class="container">
  <?= section_head('role by role', 'Everything each person gets') ?>
  <div class="role-grid">
    <?php foreach ($groups as [$ic, $g, $items]): ?>
      <div class="card role-card"><div class="role-head"><span class="role-ic"><?= ico($ic) ?></span><h3><?= e($g) ?></h3></div><ul class="check"><?php foreach ($items as $i) echo '<li>' . e($i) . '</li>'; ?></ul></div>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="section"><div class="container">
  <?= section_head('how it works', 'One period, four steps', 'From the teacher\'s tap to the parent\'s inbox.') ?>
  <div class="steps4">
    <div><h3>Open your day</h3><p>The faculty dashboard lists today's periods across all classes you teach. Pick the period that is running.</p></div>
    <div><h3>Mark the class</h3><p>Everyone starts as Present. Tap Absent or Late for the few who need it, or use "mark all" and correct exceptions.</p></div>
    <div><h3>Save once</h3><p>The record is stored with date, period, subject and teacher. You can correct mistakes for 7 days.</p></div>
    <div><h3>Parents are told</h3><p>For each absent child an e-mail goes to the parent, and the school's e-mail log keeps a copy as proof.</p></div>
  </div>
</div></section>

<section class="section alt"><div class="container split">
  <div class="prose">
    <span class="kicker" style="text-align:left">new</span>
    <h2 style="margin-top:0">A lecture library for every class</h2>
    <p>Teachers can publish a lecture with <b>many items at once</b> &mdash; recorded videos, audio, PDFs, slides and links to YouTube or Google Drive. Students see them under <b>Lectures</b> grouped by subject, with a built-in player that supports seeking.</p>
    <ul class="check"><li>Each file up to 300 MB; MP4 plays in every browser</li><li>Only the right class, its parents, the teacher and the school admin can open a file</li><li>Files are stored privately, never on a public link</li><li>Perfect for absent students, revision before exams and flipped classrooms</li></ul>
  </div>
  <div class="card" style="margin:0"><h3>Example: Class 7-A &middot; Science</h3>
    <div class="slot"><div class="info"><b>Chapter 4 &ndash; Heat</b> <span class="badge b-blue">Science</span><div class="muted small">3 items &middot; by Mrs. Verma</div></div></div>
    <div class="slot"><div class="info"><b><?= ico('&#127916;') ?> heat-recording.mp4</b><div class="muted small">Plays in the page</div></div></div>
    <div class="slot"><div class="info"><b><?= ico('&#128196;') ?> chapter-4-notes.pdf</b><div class="muted small">Open or download</div></div></div>
    <div class="slot"><div class="info"><b><?= ico('&#128279;') ?> youtube.com/...</b><div class="muted small">Opens in a new tab</div></div></div></div>
</div></section>

<section class="section"><div class="container">
  <?= section_head('reports', 'Numbers you can show an inspector') ?>
  <div class="numbers">
    <div><b>75%</b><span>minimum-attendance flag, configurable</span></div>
    <div><b>5</b><span>role-based dashboards</span></div>
    <div><b>CSV</b><span>export of attendance for any range</span></div>
    <div><b>7 days</b><span>edit window with full audit trail</span></div>
  </div>
</div></section>

<section class="section alt"><div class="container">
  <?= section_head('questions', 'Features - FAQ') ?>
  <?= faq_html([
    ['Can one teacher handle periods in different classes?', '<p>Yes. This is the core idea: the faculty dashboard shows every period of the day across all classes, and each one is marked from the same place.</p>'],
    ['What if a teacher is absent?', '<p>The school admin can change the timetable, assigning another teacher to the slot. That teacher then sees the period on their own dashboard.</p>'],
    ['Can we upload our existing student list?', '<p>Yes. School admins can bulk-import students (with parent details) from a CSV file; parent accounts are created automatically and siblings share one parent login.</p>'],
    ['Are alerts sent to parents automatically?', '<p>Yes. As soon as a student is marked absent, an e-mail is generated. You can choose one e-mail per absent period, or only one per student per day.</p>'],
    ['Do students need a smartphone?', '<p>No. Any phone or computer with a browser works. Parents just need an e-mail address.</p>'],
    ['Is there a mobile app?', '<p>The website is mobile-friendly today. A dedicated app is on the longer-term roadmap.</p>'],
  ]) ?>
</div></section>
<?= cta_band('See every dashboard yourself', 'Log in with the demo accounts and explore as a teacher, student, parent or principal.', 'Try the demo', '/login', 'Register your school', '/signup') ?>
<?php public_footer(); ?>
