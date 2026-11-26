<?php
/* Timetable in three simple views: Day schedule (what happens on a date), Weekly timetable (read, then Edit) and History. */
$s = sid(); $classes = school_classes($s); $today = date('Y-m-d');
$tab = inp('tab', 'day'); if (!in_array($tab, ['day', 'week', 'record'], true)) $tab = 'day';
$cid = (int)inp('class', $tab === 'week' ? (string)($classes[0]['id'] ?? 0) : '0');
$class = null; foreach ($classes as $c) if ((int)$c['id'] === $cid) $class = $c;
$subjects = school_subjects($s); $allFaculty = school_faculty($s); $fac = array_values(array_filter($allFaculty, fn($f) => $f['active']));
$np = (int)(inp('np') ?: ($_POST['np'] ?? 6)); $np = max(1, min(8, $np));
$edit = $tab === 'week' && inp('edit') === '1';

if (is_post() && $class) {
    $err = [];
    $sv = $_POST['sub'] ?? []; $fv = $_POST['fac'] ?? [];
    $subOk = array_column($subjects, 'id'); $facOk = array_column($fac, 'id');
    $new = [];
    for ($d = 1; $d <= 6; $d++) for ($p = 1; $p <= $np; $p++) {
        $sub = (int)($sv[$d][$p] ?? 0); $fid = (int)($fv[$d][$p] ?? 0);
        if (!$sub && !$fid) continue;
        if (!$sub || !$fid) { $err[] = DAYS[$d] . " P$p: choose both subject and teacher."; continue; }
        if (!in_array((string)$sub, array_map('strval', $subOk), true) || !in_array((string)$fid, array_map('strval', $facOk), true)) { $err[] = DAYS[$d] . " P$p: invalid selection."; continue; }
        $clash = row('SELECT c.name, c.section FROM timetable t JOIN classes c ON c.id=t.class_id WHERE t.faculty_id=? AND t.day=? AND t.period=? AND t.class_id<>?', [$fid, $d, $p, $cid]);
        if ($clash) { $err[] = DAYS[$d] . " P$p: teacher already teaches " . $clash['name'] . '-' . $clash['section'] . ' at this time.'; continue; }
        $new["$d-$p"] = [$d, $p, $sub, $fid];
    }
    if ($err) { flash(implode(' ', array_slice($err, 0, 4)) . (count($err) > 4 ? ' ...' : '') . ' Nothing saved.', 'err'); redirect("admin/timetable?tab=week&class=$cid&np=$np&edit=1"); }
    $pdo = db(); $pdo->beginTransaction();
    // update periods in place so each one keeps its id (substitute assignments point to it)
    $old = []; foreach (q('SELECT id, day, period FROM timetable WHERE class_id=? AND school_id=?', [$cid, $s]) as $r) $old[$r['day'] . '-' . $r['period']] = (int)$r['id'];
    foreach ($new as $k => [$d, $p, $sub, $fid]) {
        if (isset($old[$k])) run('UPDATE timetable SET subject_id=?, faculty_id=? WHERE id=?', [$sub, $fid, $old[$k]]);
        else run('INSERT INTO timetable(school_id,class_id,day,period,subject_id,faculty_id) VALUES(?,?,?,?,?,?)', [$s, $cid, $d, $p, $sub, $fid]);
    }
    foreach (array_diff_key($old, $new) as $id) { run('DELETE FROM substitutions WHERE timetable_id=?', [$id]); run('DELETE FROM timetable WHERE id=?', [$id]); }
    $pdo->commit(); audit('timetable_saved', class_label($class) . ' (' . count($new) . ' periods)'); flash('Timetable saved (' . count($new) . ' periods).');
    redirect("admin/timetable?tab=week&class=$cid");
}

$fid = (int)inp('faculty'); if ($fid && !in_array($fid, array_map('intval', array_column($allFaculty, 'id')), true)) $fid = 0;
if ($cid && !$class) $cid = 0;

if ($tab === 'record') {
    $from = inp('from', $today); $to = inp('to', $from);
    if (!valid_date($from)) $from = $today; if (!valid_date($to)) $to = $from;
    if ($to < $from) [$from, $to] = [$to, $from];
    $clipped = false; $min = date('Y-m-d', strtotime("$to -" . (LOG_MAX_DAYS - 1) . ' day')); if ($from < $min) { $from = $min; $clipped = true; }
    $log = class_log($s, $from, $to, $cid, $fid);
    if (inp('export')) {
        require_once APP . '/xlsx.php'; set_time_limit(0);
        $sheets = class_log_sheets($s, $log, $from, $to);
        if (!$sheets) { flash('There are no classes in this selection.', 'warn'); redirect("admin/timetable?tab=record&from=$from&to=$to"); }
        audit('class_record_export', "$from..$to");
        xlsx_download("class_record_{$from}_to_$to.xlsx", $sheets);
    }
    [$page, $pager] = paginate_array($log, 25);
    $n = array_count_values(array_column($log, 'status')); $due = count($log) - ($n['holiday'] ?? 0);
    $subs = count(array_filter($log, fn($r) => $r['by_id'] && $r['owner_id'] && $r['by_id'] !== $r['owner_id']));
    $quick = ['Today' => [$today, $today], 'Yesterday' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))], 'This week' => [date('Y-m-d', strtotime('monday this week')), $today], 'This month' => [date('Y-m-01'), $today]];
}
if ($tab === 'day') {
    $date = inp('date', $today); if (!valid_date($date)) $date = $today;
    $log = class_log($s, $date, $date);
    $cells = []; $maxp = 0; foreach ($log as $r) { $cells[$r['class_id']][$r['period']] = $r; $maxp = max($maxp, $r['period']); }
    $shown = array_values(array_filter($classes, fn($c) => isset($cells[(int)$c['id']])));
    [$rowsPage, $pager] = paginate_array($shown, 20);
    $n = array_count_values(array_column($log, 'status'));
    $step = function (int $dir) use ($date) { $d = date('Y-m-d', strtotime("$date $dir day")); if (date('N', strtotime($d)) == 7) $d = date('Y-m-d', strtotime("$d $dir day")); return $d; };
    $holiday = holidays_on($s, $date)[0] ?? null;
}
dash_header('Timetable');
?>
<div class="tabs"><a href="/admin/timetable"<?= $tab === 'day' ? ' class="active"' : '' ?>>Day schedule</a><a href="/admin/timetable?tab=week"<?= $tab === 'week' ? ' class="active"' : '' ?>>Weekly timetable</a><a href="/admin/timetable?tab=record"<?= $tab === 'record' ? ' class="active"' : '' ?>>History &amp; download</a></div>

<?php if ($tab === 'day'): ?>
<div class="card daybar">
  <a class="btn" href="?date=<?= e($step(-1)) ?><?= $fid ? '&amp;faculty=' . $fid : '' ?>">&lsaquo; Previous day</a>
  <div class="daybar-title"><h3><?= e(fmt_date($date, 'l, d F Y')) ?></h3><?= $date === $today ? badge('Today', 'green') : '' ?></div>
  <a class="btn" href="?date=<?= e($step(1)) ?><?= $fid ? '&amp;faculty=' . $fid : '' ?>">Next day &rsaquo;</a>
  <form method="get" class="inline-form"><div><label>Go to date</label><input type="date" name="date" value="<?= e($date) ?>" data-autosubmit></div>
    <div><label>Show one teacher</label><select name="faculty" data-autosubmit><option value="0">Everyone</option><?php foreach ($allFaculty as $f) echo '<option value="' . (int)$f['id'] . '"' . ($fid === (int)$f['id'] ? ' selected' : '') . '>' . e($f['name']) . '</option>'; ?></select></div>
    <?php if ($date !== $today): ?><div style="flex:0"><a class="btn btn-primary" href="/admin/timetable">Today</a></div><?php endif; ?></form>
</div>
<?php if ($holiday !== null): ?><div class="card center"><h3>Holiday: <?= e($holiday) ?></h3><p class="muted">No classes on this day.</p></div>
<?php elseif (!$shown): ?><div class="card center muted">No classes on this day<?= date('N', strtotime($date)) == 7 ? ' (Sunday)' : '' ?>.</div>
<?php else: ?>
<div class="card">
  <p class="sc-summary"><span class="sc-key k-taken"><b><?= $n['taken'] ?? 0 ?></b> Taken</span><span class="sc-key k-not"><b><?= ($n['not_taken'] ?? 0) + ($n['no_teacher'] ?? 0) ?></b> Not taken</span><span class="sc-key k-up"><b><?= $n['upcoming'] ?? 0 ?></b> Upcoming</span></p>
  <div class="table-wrap"><div class="sched" style="--p:<?= $maxp ?>">
    <div class="sc-row sc-head"><span>Class</span><?php for ($p = 1; $p <= $maxp; $p++) echo '<span>Period ' . $p . '<small>' . e(period_time($p)) . '</small></span>'; ?></div>
    <?php foreach ($rowsPage as $c): $id = (int)$c['id']; ?><div class="sc-row"><b class="sc-class"><?= e(class_label($c)) ?></b>
      <?php for ($p = 1; $p <= $maxp; $p++): $r = $cells[$id][$p] ?? null;
        if (!$r) { echo '<div class="sc-cell empty"><i>P' . $p . '</i><span>&ndash;</span></div>'; continue; }
        $dim = $fid && $r['by_id'] !== $fid && $r['owner_id'] !== $fid; $sub = $r['by_id'] && $r['owner_id'] && $r['by_id'] !== $r['owner_id'];
        $text = ['taken' => '&#10003; Taken &middot; ' . ((int)$r['present'] + (int)$r['late']) . ' of ' . ((int)$r['present'] + (int)$r['late'] + (int)$r['absent']) . ' present', 'not_taken' => '&#10007; Not marked', 'no_teacher' => '&#9888; No teacher', 'upcoming' => 'Upcoming', 'holiday' => 'Holiday'][$r['status']]; ?>
      <div class="sc-cell st-<?= $r['status'] ?><?= $dim ? ' dim' : '' ?>" title="<?= e($r['remark']) ?>"><i>P<?= $p ?> &middot; <?= e(period_time($p)) ?></i><b><?= e($r['subject']) ?></b>
        <span><?= e($r['by'] ?: $r['owner']) ?></span><?= $sub ? '<u>Substitute</u>' : '' ?><em><?= $text ?></em></div>
      <?php endfor; ?></div><?php endforeach; ?>
  </div></div><?= $pager ?>
</div>
<?php endif; ?>

<?php elseif ($tab === 'week'): ?>
<?php $cur = []; $used = 1; if ($class) foreach (q('SELECT t.*, sub.name sname, u.name fname FROM timetable t JOIN subjects sub ON sub.id=t.subject_id JOIN users u ON u.id=t.faculty_id WHERE t.class_id=?', [$cid]) as $r) { $cur[$r['day']][$r['period']] = $r; $np = max($np, (int)$r['period']); $used = max($used, (int)$r['period']); }
$tone = []; foreach ($subjects as $i => $sb) $tone[(int)$sb['id']] = $i % 6; ?>
<?php if (!$class): ?><div class="card muted center">Add a class first.</div><?php else: ?>
<div class="card"><form method="get" class="inline-form"><input type="hidden" name="tab" value="week"><?php if ($edit) echo '<input type="hidden" name="edit" value="1">'; ?>
  <div><label>Class</label><select name="class" data-autosubmit><?php foreach ($classes as $c) echo '<option value="' . (int)$c['id'] . '"' . ($cid === (int)$c['id'] ? ' selected' : '') . '>' . e(class_label($c)) . '</option>'; ?></select></div>
  <?php if ($edit): ?><div><label>Periods per day</label><select name="np" data-autosubmit><?php for ($i = $used; $i <= 8; $i++) echo '<option' . ($np === $i ? ' selected' : '') . '>' . $i . '</option>'; ?></select></div>
  <?php else: ?><div class="push"><a class="btn btn-primary" href="?tab=week&amp;class=<?= $cid ?>&amp;edit=1">&#9998; Edit timetable</a></div><?php endif; ?></form></div>
<?php if ($edit && (!$subjects || !$fac)): ?><div class="card muted center">Add subjects and faculty first.</div>
<?php elseif ($edit): ?>
<form method="post" action="/admin/timetable?tab=week&amp;class=<?= $cid ?>"><?= csrf_field() ?><input type="hidden" name="np" value="<?= $np ?>">
<div class="card"><div class="card-head"><h3>Editing Class <?= e(class_label($class)) ?></h3><span><a class="btn" href="?tab=week&amp;class=<?= $cid ?>">Cancel</a> <button class="btn btn-primary">Save timetable</button></span></div>
<p class="muted small">Choose a subject and a teacher for each period. Leave both as "-" for a free period. A teacher cannot be in two classes at the same time.</p>
<div class="table-wrap"><table class="tt"><tr><th style="width:110px">Day</th><?php for ($p = 1; $p <= $np; $p++) echo '<th>P' . $p . '<br><span class="muted" style="text-transform:none;font-weight:400">' . e(period_time($p)) . '</span></th>'; ?></tr>
<?php for ($d = 1; $d <= 6; $d++): ?><tr><th><?= e(DAYS[$d]) ?></th>
  <?php for ($p = 1; $p <= $np; $p++): $x = $cur[$d][$p] ?? null; ?><td>
    <select name="sub[<?= $d ?>][<?= $p ?>]" aria-label="<?= e(DAYS[$d]) ?> period <?= $p ?> subject"><option value="">-</option><?php foreach ($subjects as $sb) echo '<option value="' . (int)$sb['id'] . '"' . ($x && (int)$x['subject_id'] === (int)$sb['id'] ? ' selected' : '') . '>' . e($sb['name']) . '</option>'; ?></select>
    <select name="fac[<?= $d ?>][<?= $p ?>]" aria-label="<?= e(DAYS[$d]) ?> period <?= $p ?> teacher"><option value="">-</option><?php foreach ($fac as $f) echo '<option value="' . (int)$f['id'] . '"' . ($x && (int)$x['faculty_id'] === (int)$f['id'] ? ' selected' : '') . '>' . e($f['name']) . '</option>'; ?></select></td><?php endfor; ?></tr>
<?php endfor; ?></table></div><p><button class="btn btn-primary">Save timetable</button></p></div></form>
<?php else: ?>
<div class="card"><h3>Class <?= e(class_label($class)) ?> &middot; every week</h3>
<?php if (!$cur): ?><p class="muted">No periods yet. Click <b>Edit timetable</b> to add them.</p><?php else: ?>
<div class="table-wrap"><table class="week"><tr><th>Day</th><?php for ($p = 1; $p <= $np; $p++) echo '<th>Period ' . $p . '<small>' . e(period_time($p)) . '</small></th>'; ?></tr>
<?php for ($d = 1; $d <= 6; $d++): ?><tr><th><?= e(DAYS[$d]) ?></th><?php for ($p = 1; $p <= $np; $p++): $x = $cur[$d][$p] ?? null;
  echo $x ? '<td><div class="wk t' . $tone[(int)$x['subject_id']] . '"><b>' . e($x['sname']) . '</b><span>' . e($x['fname']) . '</span></div></td>' : '<td><div class="wk free">Free</div></td>'; endfor; ?></tr>
<?php endfor; ?></table></div><?php endif; ?></div>
<?php endif; endif; ?>

<?php else: ?>
<div class="card"><form method="get" class="inline-form"><input type="hidden" name="tab" value="record">
  <div><label>From date</label><input type="date" name="from" value="<?= e($from) ?>"></div><div><label>To date</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <div><label>Class</label><select name="class"><option value="0">All classes</option><?php foreach ($classes as $c) echo '<option value="' . (int)$c['id'] . '"' . ($cid === (int)$c['id'] ? ' selected' : '') . '>' . e(class_label($c)) . '</option>'; ?></select></div>
  <div><label>Faculty</label><select name="faculty"><option value="0">All faculty</option><?php foreach ($allFaculty as $f) echo '<option value="' . (int)$f['id'] . '"' . ($fid === (int)$f['id'] ? ' selected' : '') . '>' . e($f['name']) . '</option>'; ?></select></div>
  <div style="flex:0"><button class="btn btn-primary">Show</button></div>
  <div class="push"><a class="btn" href="/admin/timetable?<?= e(http_build_query(['tab' => 'record', 'from' => $from, 'to' => $to, 'class' => $cid, 'faculty' => $fid, 'export' => 1])) ?>">&#8681; Download Excel</a></div></form>
  <p class="quick"><?php foreach ($quick as $label => [$a, $b]) echo '<a class="btn btn-sm' . ($from === $a && $to === $b ? ' btn-primary' : '') . '" href="/admin/timetable?' . e(http_build_query(['tab' => 'record', 'from' => $a, 'to' => $b, 'class' => $cid, 'faculty' => $fid])) . '">' . $label . '</a> '; ?></p>
  <?php if ($clipped): ?><p class="small muted">One search can cover up to <?= LOG_MAX_DAYS ?> days, so the start date was moved to <?= e(fmt_date($from)) ?>.</p><?php endif; ?></div>

<div class="stats"><?= kpi('Classes scheduled', $due, date_range_label($from, $to)) ?><?= kpi('Taken', $n['taken'] ?? 0, 'attendance marked', 'green') ?><?= kpi('Not taken', ($n['not_taken'] ?? 0) + ($n['no_teacher'] ?? 0), ($n['no_teacher'] ?? 0) . ' without a teacher', 'red') ?><?= kpi('Upcoming', $n['upcoming'] ?? 0) ?><?= kpi('Substituted', $subs, '', 'saffron') ?></div>

<div class="card"><h3>Who took which class &middot; <?= e(date_range_label($from, $to)) ?></h3>
<div class="table-wrap"><table class="stack stack-nums log"><tr><th>Date</th><th>Period &amp; time</th><th>Class</th><th>Subject</th><th>Taken by / assigned</th><th>Faculty attendance</th><th>Class status</th><th>Students</th></tr>
<?php foreach ($page as $r): ?><tr>
  <td class="who-cell"><b><?= e(fmt_date($r['date'], 'D, d M Y')) ?></b></td>
  <td data-label="Period &amp; time"><b>P<?= $r['period'] ?></b><br><span class="muted small"><?= e(period_time($r['period'])) ?></span></td>
  <td data-label="Class"><b><?= e($r['class']) ?></b></td>
  <td data-label="Subject"><?= e($r['subject']) ?></td>
  <td data-label="Faculty"><?= $r['by'] ? '<b>' . e($r['by']) . '</b>' : '<span class="muted">&ndash;</span>' ?><?= $r['remark'] !== '' ? '<br><span class="muted small">' . e($r['remark']) . '</span>' : '' ?></td>
  <td data-label="Faculty attendance"><?= $r['status'] === 'holiday' ? '&ndash;' : staff_badge($r['fstatus']) ?><?= $r['check_in'] !== '' ? '<br><span class="muted small">in ' . e(date('h:i A', strtotime($r['check_in']))) . '</span>' : '' ?></td>
  <td data-label="Class status"><?= badge(...LOG_STATUS[$r['status']]) ?><?= $r['marked_at'] ? '<br><span class="muted small">marked ' . e(fmt_date($r['marked_at'], 'd M, h:i A')) . '</span>' : '' ?></td>
  <td data-label="Students"><?= $r['present'] === null ? '<span class="muted">&ndash;</span>' : '<b>' . ($r['present'] + $r['late']) . '</b> present' . ($r['late'] ? ' (' . $r['late'] . ' late)' : '') . '<br><span class="small' . ($r['absent'] ? '' : ' muted') . '">' . $r['absent'] . ' absent</span>' ?></td></tr>
<?php endforeach; if (!$page) echo empty_row(8, 'No classes on these dates (Sunday, or no timetable yet).'); ?></table></div><?= $pager ?></div>

<?php $byFaculty = class_log_by_faculty($log); if ($byFaculty): ?>
<div class="card"><h3>Faculty summary</h3><div class="table-wrap"><table class="stack stack-nums"><tr><th>Faculty</th><th>Scheduled</th><th>Taken</th><th>Missed</th><th>Upcoming</th><th>Taken for others</th><th>Taken %</th></tr>
<?php foreach ($byFaculty as $f): $d = $f['taken'] + $f['missed']; ?><tr><td class="who-cell"><b><?= e($f['name']) ?></b></td><td data-label="Scheduled"><?= $f['planned'] ?></td><td data-label="Taken"><?= $f['taken'] ?></td><td data-label="Missed"><?= $f['missed'] ?></td><td data-label="Upcoming"><?= $f['upcoming'] ?></td><td data-label="Taken for others"><?= $f['extra'] ?></td><td data-label="Taken %"><?= $d ? pct_badge(pct((float)$f['taken'], (float)$d)) : badge('No data') ?></td></tr>
<?php endforeach; ?></table></div>
<p class="small muted">A class counts as taken when its student attendance was marked. Periods without attendance are listed from the current timetable.</p></div><?php endif; ?>
<?php endif; ?>
<?php dash_footer(); ?>
