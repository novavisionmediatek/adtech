<?php
$schools = (int)val("SELECT COUNT(*) FROM schools WHERE status='active'");
$students = (int)val('SELECT COUNT(*) FROM students');
$marked = (int)val('SELECT COUNT(*) FROM (SELECT 1 FROM attendance GROUP BY class_id,date,period) AS x');
$homework = (int)val('SELECT COUNT(*) FROM homework');
public_header('Digital School Management for India', 'Attendance, homework, timetable and parent alerts for every school.');
$app = e(cfg('app_name'));

// Illustrative "today" board: [subject, teacher, state, note, (live only) final state, final note]
$board = [
  '6-A' => [['Maths', 'S. Gupta', 'ok', '8/8'], ['English', 'A. Verma', 'ok', '8/8'], ['Hindi', 'R. Kumar', 'abs', '1 absent'], ['Science', 'V. Singh', 'live', 'Marking now', 'ok', '8/8'], ['Computer', 'I. Khan', 'next', 'Next'], ['SST', 'P. Mishra', 'next', 'Later']],
  '7-A' => [['English', 'A. Verma', 'ok', '8/8'], ['Hindi', 'R. Kumar', 'abs', '2 absent'], ['Maths', 'S. Gupta', 'ok', '8/8'], ['Computer', 'I. Khan', 'live', 'Marking now', 'abs', '1 absent'], ['SST', 'P. Mishra', 'next', 'Next'], ['Science', 'V. Singh', 'next', 'Later']],
  '8-A' => [['Hindi', 'R. Kumar', 'ok', '8/8'], ['Maths', 'S. Gupta', 'ok', '8/8'], ['Science', 'V. Singh', 'ok', '8/8'], ['SST', 'P. Mishra', 'live', 'Marking now', 'ok', '8/8'], ['English', 'A. Verma', 'next', 'Next'], ['Computer', 'I. Khan', 'next', 'Later']],
];
?>
<section class="h-hero"><div class="container">
  <span class="tag"><i class="dot"></i>Built around the school day</span>
  <h1>Every period, <span class="mark">accounted for.</span></h1>
  <p><?= $app ?> gives your school one live picture: who is in which class, what was taught, and which parent needs to know.</p>
  <div class="h-cta"><a class="btn btn-primary btn-lg arrow" href="/login">Open the demo school</a><a class="btn btn-lg" href="/signup">Register your school</a></div>
  <ul class="h-points"><li>No app to install</li><li>Parents only need e-mail</li><li>Works on basic phones</li></ul>

  <div class="board">
    <div class="board-top"><b>Today at Demo Public School</b><div class="legend"><span class="ok">Marked</span><span class="abs">Absent found</span><span class="live">In progress</span></div></div>
    <div class="board-scroll"><div class="board-grid">
      <div class="bh">Class</div><?php for ($p = 1; $p <= 6; $p++) echo '<div class="bh">P' . $p . '<small>' . e(substr(period_time($p), 0, 5)) . '</small></div>'; ?>
      <?php $n = 0; foreach ($board as $class => $cells): ?>
        <div class="bc"><?= e($class) ?></div>
        <?php foreach ($cells as $col => $c): ?><div class="cell <?= $c[2] ?>" style="--i:<?= $col + $n ?>"<?= isset($c[4]) ? ' data-next="' . e($c[4]) . '" data-note="' . e($c[5]) . '"' : '' ?>><b><?= e($c[0]) ?></b><small><?= e($c[1]) ?></small><em><?= e($c[3]) ?></em></div><?php endforeach; $n++; ?>
      <?php endforeach; ?>
    </div></div>
    <div class="board-foot"><div><?= ico('&#9993;') ?> Parents of <b id="board-count">3</b> absent students have been e-mailed.</div><span>Illustrative view</span></div>
  </div>
</div></section>

<section class="facts"><div class="container">
  <div><b data-count><?= number_format($marked) ?></b><span>class periods recorded</span></div>
  <div><b data-count><?= number_format($students) ?></b><span>students on the platform</span></div>
  <div><b data-count><?= number_format($homework) ?></b><span>homework items shared</span></div>
  <div><b data-count><?= number_format($schools) ?></b><span>active demo school<?= $schools === 1 ? '' : 's' ?></span></div>
</div></section>

<section class="section"><div class="container">
  <?= section_head('The problem we fix', 'A paper register cannot keep up', 'What changes when the register, the homework and the parent are connected.') ?>
  <div class="versus">
    <div class="vs-head"><span></span><b>Paper register &amp; chat groups</b><b><?= $app ?></b></div>
    <?php foreach ([
      ['Attendance', 'Once a day, easy to miss bunking', 'Every period, every subject'],
      ['Telling parents', 'Phone calls, if someone has time', 'Automatic e-mail the same day'],
      ['Homework', 'Lost in WhatsApp groups', 'One page per class, with files'],
      ['Absent students', 'Fall behind quietly', 'Recorded lectures and notes to catch up'],
      ['Attendance %', 'Hand calculation before inspection', 'Automatic, flagged below 75%'],
      ['Records &amp; proof', 'One book, can be lost or altered', 'Audit trail and e-mail log'],
    ] as [$k, $old, $new]): ?>
      <div class="vs-row"><span><?= $k ?></span><div class="old"><?= $old ?></div><div class="new"><?= $new ?></div></div>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="section alt"><div class="container">
  <?= section_head('What is inside', 'Six tools that talk to each other', 'Mark it once, and attendance, alerts and reports update together.') ?>
  <div class="bento">
    <article class="b-big b-ink">
      <h3>Period-wise attendance</h3><p>Everyone starts as Present. Tap only the children who are absent or late.</p>
      <div class="reg"><?php foreach ([['Aarav Sharma', 'a'], ['Diya Patel', 'p'], ['Kabir Yadav', 'p'], ['Myra Mishra', 'l'], ['Rohan Verma', 'p']] as $row => [$name, $s]) { echo '<div style="--i:' . $row . '"><span>' . $name . '</span>'; foreach (['p', 'a', 'l'] as $k) echo '<i class="' . ($s === $k ? 'on ' . $k : '') . '">' . strtoupper($k) . '</i>'; echo '</div>'; } ?></div>
      <div class="reg-sum"><span><b>3</b> present</span><span><b>1</b> late</span><span><b>1</b> absent &rarr; parent informed</span></div>
    </article>
    <article class="b-wide">
      <div class="b-title"><i><?= ico('&#128231;') ?></i><div><h3>Same-day parent alerts</h3><p>The e-mail names the child, period, subject and teacher.</p></div></div>
      <div class="mailchip"><b>Absence alert: Aarav Sharma</b><span>Period 3 &middot; English &middot; sent to parent</span></div>
    </article>
    <article class="b-tint"><i><?= ico('&#127909;') ?></i><h3>Lectures</h3><p>Several videos, PDFs and links in one post.</p></article>
    <article><i><?= ico('&#128221;') ?></i><h3>Homework</h3><p>With files and due dates, for the right class only.</p></article>
    <article class="b-wide"><div class="b-title"><i><?= ico('&#128197;') ?></i><div><h3>Clash-free timetable</h3><p>A teacher can never be booked into two rooms for the same period.</p></div></div><div class="mini-tt"><?php foreach (['Maths', 'Hindi', 'Science', 'English', 'Computer', 'SST', 'Maths', 'Hindi'] as $i => $s) echo '<span style="--i:' . $i . '">' . $s . '</span>'; ?></div></article>
    <article class="b-pink"><i><?= ico('&#128200;') ?></i><h3>Reports</h3><p>Percentages calculated for you, exported to CSV.</p></article>
    <article><i><?= ico('&#128274;') ?></i><h3>Security</h3><p>Roles, school isolation and an audit trail.</p></article>
  </div>
</div></section>

<section class="section"><div class="container">
  <?= section_head('Who are you?', 'See your own side of the school', 'Pick a role. Each login shows only what that person needs.') ?>
  <div class="roles-tabs">
    <?php
    $roles = [
      ['principal', '&#127979;', 'Principal', 'Run the whole school from one desk', ['Classes, subjects, staff and students', 'Timetable that blocks teacher clashes', 'Who is below 75%, with CSV export', 'Notices to everyone'], [['Present today', '91%'], ['Periods unmarked', '4'], ['Below 75%', '3']]],
      ['teacher', '&#128104;', 'Teacher', 'Your whole day, across every class', ['All periods in one list', 'Mark P / A / L in seconds', 'Share homework and lectures', 'Fix mistakes for 7 days'], [['P1 &middot; 7-A English', 'Done'], ['P2 &middot; 6-A English', 'Mark'], ['P6 &middot; 8-A English', 'Later']]],
      ['student', '&#127891;', 'Student', 'Know where you stand', ['Subject-wise attendance', 'Homework with due dates', 'Lectures to revise any time', 'Weekly timetable'], [['Overall attendance', '92%'], ['Homework due', '2'], ['New lectures', '1']]],
      ['parent', '&#128106;', 'Parent', 'Hear about it the same day', ['E-mail when your child is absent', 'All your children in one login', 'Homework, lectures and notices', 'History of every alert'], [['Aarav &middot; Period 3', 'Absent'], ['This month', '88%'], ['Unread notices', '1']]],
      ['admin', '&#128081;', 'Platform admin', 'Keep the platform safe', ['Verify and approve schools', 'Manage blog and careers', 'Reset passwords', 'Review the audit trail'], [['Schools awaiting approval', '1'], ['Active schools', '1'], ['Audit events', 'Live']]],
    ];
    foreach ($roles as $i => $r) echo '<input type="radio" name="role" id="role-' . $r[0] . '"' . ($i === 1 ? ' checked' : '') . '>';
    ?>
    <div class="rt-list"><?php foreach ($roles as [$k, $ic, $name]) echo '<label for="role-' . $k . '"><i>' . ico($ic) . '</i>' . $name . '</label>'; ?></div>
    <div class="rt-panels"><?php foreach ($roles as [$k, $ic, $name, $head, $points, $rows]): ?>
      <div class="rt-panel p-<?= $k ?>">
        <div><h3><?= $head ?></h3><ul class="check"><?php foreach ($points as $pt) echo '<li>' . e($pt) . '</li>'; ?></ul><a class="btn btn-primary arrow" href="/login">Try the <?= strtolower($name) ?> login</a></div>
        <div class="rt-screen"><div class="rt-bar"><i></i><i></i><i></i><span><?= $name ?> dashboard</span></div><?php foreach ($rows as $j => [$a, $b]) echo '<div class="rt-row" style="--i:' . $j . '"><span>' . $a . '</span><b>' . $b . '</b></div>'; ?><small>Sample values</small></div>
      </div>
    <?php endforeach; ?></div>
  </div>
</div></section>

<section class="section alt"><div class="container">
  <?= section_head('A day with ' . cfg('app_name'), 'From first bell to month end', 'An illustrative walk-through of a typical Monday.') ?>
  <div class="dayplan">
    <?php foreach ([
      ['08:00', 'Teacher', 'Period 1 begins', 'Mrs. Verma sees all her periods across three classes, taps the two missing children and saves.'],
      ['08:01', 'Parent', 'Alerts arrive', 'Two parents receive an e-mail naming the child, period, subject and teacher.'],
      ['10:30', 'Principal', 'A child leaves early', 'He was present for three periods and missing in the fourth. A daily roll call would never show it.'],
      ['13:00', 'Student', 'Lecture goes live', 'A recorded lecture, notes and a link are published together for the class.'],
      ['17:00', 'Parent', 'Evening check', 'A mother sees both children: attendance, homework, timetable and the school notice.'],
      ['Month end', 'Principal', 'Report ready', 'Filter by class and date, see who is below 75% and export a CSV.'],
    ] as [$time, $who, $t, $d]): ?>
      <div class="dp-row"><time><?= $time ?></time><span class="dp-who w-<?= strtolower($who) ?>"><?= $who ?></span><h3><?= $t ?></h3><p><?= $d ?></p></div>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="section"><div class="container">
  <?= section_head('Getting started', 'Four periods to go live', 'Think of it as one short school morning.') ?>
  <ol class="bells">
    <li><span>P1</span><h3>Register</h3><p>Send your school details. The platform team verifies and approves.</p></li>
    <li><span>P2</span><h3>Set up</h3><p>Add classes, subjects and faculty, then import students by CSV.</p></li>
    <li><span>P3</span><h3>Timetable</h3><p>Place periods on the weekly grid. Clashes are blocked for you.</p></li>
    <li><span>P4</span><h3>Go live</h3><p>Teachers mark the first period and parents start receiving alerts.</p></li>
  </ol>
</div></section>

<section class="section alt"><div class="container qa">
  <aside>
    <span class="kicker">Questions</span><h2 class="title">Asked by schools</h2>
    <div class="trustbox"><b><?= ico('&#128737;') ?> Built for careful scrutiny</b>
      <ul class="check"><li>bcrypt password hashing</li><li>Role-based access</li><li>School data isolation</li><li>Audit trail</li><li>Private file storage</li><li>No ads or trackers</li></ul>
      <a href="/compliance">Compliance and security details &rarr;</a></div>
  </aside>
  <?= faq_html([
    ['What is ' . cfg('app_name') . '?', '<p>A school management portal where teachers mark period-wise attendance, share homework and recorded lectures, and parents are informed automatically when a child is absent.</p>'],
    ['Do students and parents need a smartphone or an app?', '<p>No app is needed. Any phone or computer with a browser works. Parents just need an e-mail address to receive alerts.</p>'],
    ['Can one teacher manage periods of different classes?', '<p>Yes. A teacher\'s dashboard shows every period of the day across all the classes they teach, and each is marked from the same place.</p>'],
    ['How do we start with our existing students?', '<p>Register your school, get approved, add classes and subjects, then upload your student list as a CSV. Parent accounts are created automatically.</p>'],
    ['Is our data safe?', '<p>The portal uses hashed passwords, role-based access, school-level data isolation, CSRF protection and an audit trail. See <a href="/compliance">Compliance &amp; Security</a> for exactly what is in place and what is planned.</p>'],
    ['Is it approved or certified by the government?', '<p>No. It is designed to <b>align with</b> national priorities such as NEP 2020 and Digital India, and we are transparent that certification and a security audit come before any large roll-out.</p>'],
    ['What does it cost?', '<p>The demo is free to explore. For real schools we offer a pilot first; <a href="/contact">contact us</a> to discuss terms that suit your school.</p>'],
  ]) ?>
</div></section>
<?= cta_band('Ring the first digital bell', 'Try it with sample data today. No paperwork, no credit card.', 'Register your school', '/signup', 'Talk to us', '/contact') ?>
<?php public_footer(); ?>
