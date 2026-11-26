<?php
$s = sid(); $today = date('Y-m-d');
$date = inp('date', $today); if (!valid_date($date) || $date > $today) $date = $today;
$month = inp('month', substr($date, 0, 7)); if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $month > substr($today, 0, 7)) $month = substr($today, 0, 7);
$tab = inp('tab') === 'report' ? 'report' : 'mark';
$faculty = array_values(array_filter(school_faculty($s), fn($f) => $f['active']));
$holiday = holidays_on($s, $date)[0] ?? null; $sunday = date('N', strtotime($date)) == 7;
$leaves = []; foreach (q('SELECT faculty_id, type FROM faculty_leave WHERE school_id=? AND from_date<=? AND to_date>=?', [$s, $date, $date]) as $l) $leaves[$l['faculty_id']] = $l['type'];
$saved = []; foreach (q('SELECT * FROM faculty_attendance WHERE school_id=? AND date=?', [$s, $date]) as $r) $saved[$r['faculty_id']] = $r;

if (is_post()) {
    // never save onto a different day than the one asked for (e.g. a future or malformed date)
    if (inp('date', $today) !== $date) { flash('Attendance can only be saved for today or an earlier date.', 'err'); redirect('admin/staff_attendance'); }
    if ($holiday !== null || $sunday) { flash('This is a holiday or Sunday; attendance is not marked.', 'err'); redirect("admin/staff_attendance?date=$date"); }
    $in = (array)($_POST['st'] ?? []); $times = (array)($_POST['in'] ?? []); $notes = (array)($_POST['note'] ?? []); $n = 0; $abs = 0;
    $pdo = db(); $pdo->beginTransaction();
    foreach ($faculty as $f) { // only this school's active teachers; teachers on leave are skipped
        $id = (int)$f['id']; $status = $in[$id] ?? '';
        if (isset($leaves[$id]) || !in_array($status, STAFF_MARKABLE, true)) continue;
        $time = trim((string)($times[$id] ?? '')); if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) || $status === 'A') $time = '';
        $note = mb_substr(trim((string)($notes[$id] ?? '')), 0, 255);
        run('DELETE FROM faculty_attendance WHERE faculty_id=? AND date=?', [$id, $date]);
        run('INSERT INTO faculty_attendance(school_id,faculty_id,date,status,check_in,note,marked_by,updated_at) VALUES(?,?,?,?,?,?,?,?)', [$s, $id, $date, $status, $time ?: null, $note, me()['id'], date('Y-m-d H:i:s')]);
        $n++; if ($status === 'A') $abs++;
    }
    $pdo->commit(); audit('faculty_attendance', "$date: $n marked, $abs absent");
    flash("Faculty attendance saved for " . fmt_date($date) . ": $n teacher(s)" . ($abs ? ", $abs absent. Assign substitutes under Holidays & leave." : '.'));
    redirect("admin/staff_attendance?date=$date");
}
if (inp('export')) {
    require_once APP . '/xlsx.php';
    $sheets = staff_report_sheets($s);
    if (!$sheets) { flash('No faculty attendance has been recorded yet.', 'warn'); redirect('admin/staff_attendance?tab=report'); }
    audit('faculty_attendance_export');
    xlsx_download('faculty_attendance_' . $today . '.xlsx', $sheets);
}
dash_header('Faculty attendance');
?>
<div class="tabs"><a href="?date=<?= e($date) ?>"<?= $tab === 'mark' ? ' class="active"' : '' ?>>Mark attendance</a><a href="?tab=report&amp;month=<?= e($month) ?>"<?= $tab === 'report' ? ' class="active"' : '' ?>>Monthly report</a></div>
<?php if ($tab === 'mark'):
    $count = ['P' => 0, 'L' => 0, 'H' => 0, 'A' => 0]; foreach ($saved as $id => $r) if (!isset($leaves[$id]) && isset($count[$r['status']])) $count[$r['status']]++; ?>
<div class="card"><form method="get" class="inline-form"><div><label>Date</label><input type="date" name="date" value="<?= e($date) ?>" max="<?= $today ?>" data-autosubmit></div><div style="flex:0"><button class="btn">Show</button></div></form></div>
<?php if ($holiday !== null || $sunday): ?><div class="card center muted"><?= $holiday !== null ? 'Holiday: <b>' . e($holiday) . '</b>' : 'Sunday' ?> &mdash; no attendance to mark on <?= e(fmt_date($date, 'D, d M Y')) ?>.</div>
<?php else: ?>
<div class="stats"><?= kpi('Present', $count['P'], '', 'green') ?><?= kpi('Late / half day', $count['L'] + $count['H']) ?><?= kpi('Absent', $count['A'], '', 'red') ?><?= kpi('On leave', count($leaves), '', 'saffron') ?></div>
<div class="card"><div class="card-head"><h3><?= e(fmt_date($date, 'l, d M Y')) ?></h3><?= $saved ? badge('Saved', 'green') : badge('Not saved yet', 'amber') ?></div>
<form method="post" action="/admin/staff_attendance?date=<?= e($date) ?>"><?= csrf_field() ?>
<p><button type="button" class="btn btn-sm" data-markall="P">All present</button> <button type="button" class="btn btn-sm" data-markall="A">All absent</button></p>
<div class="table-wrap"><table class="stack"><tr><th>Faculty</th><th>Emp. ID</th><th>Status</th><th style="width:130px">Check-in</th><th>Note</th></tr>
<?php foreach ($faculty as $f): $id = (int)$f['id']; $r = $saved[$id] ?? null; $cur = $r['status'] ?? 'P'; ?>
<tr><td class="who-cell"><b><?= e($f['name']) ?></b><br><span class="muted small"><?= e($f['designation']) ?></span></td><td data-label="Emp. ID"><?= e($f['employee_id']) ?></td>
<?php if (isset($leaves[$id])): ?><td colspan="3" class="wide"><?= staff_badge('LV') ?> <span class="muted small"><?= e($leaves[$id]) ?> &mdash; to change this, cancel the leave under <a href="/admin/leave">Holidays &amp; leave</a>.</span></td>
<?php else: ?><td class="wide" data-label="Status"><div class="seg"><?php foreach (['P' => ['P', 'sp'], 'L' => ['Late', 'sl'], 'H' => ['Half', 'sl'], 'A' => ['A', 'sa']] as $v => [$label, $class]) echo '<label title="' . e(STAFF_STATUS[$v][0]) . '"><input type="radio" name="st[' . $id . ']" value="' . $v . '"' . ($cur === $v ? ' checked' : '') . '><span class="' . $class . '">' . $label . '</span></label>'; ?></div></td>
<td data-label="Check-in"><input type="time" name="in[<?= $id ?>]" value="<?= e($r['check_in'] ?? '') ?>"></td><td data-label="Note"><input name="note[<?= $id ?>]" maxlength="255" value="<?= e($r['note'] ?? '') ?>" placeholder="optional"></td><?php endif; ?></tr>
<?php endforeach; if (!$faculty) echo empty_row(5, 'No faculty yet.'); ?></table></div>
<?php if ($faculty): ?><p><button class="btn btn-primary">Save faculty attendance</button></p><?php endif; ?></form></div>
<?php endif; ?>
<?php else: [$days, $grid] = staff_month($s, $month, array_map(fn($f) => (int)$f['id'], $faculty)); ?>
<div class="card"><form method="get" class="inline-form toolbar"><input type="hidden" name="tab" value="report"><div><label>Month</label><input type="month" name="month" value="<?= e($month) ?>" max="<?= substr($today, 0, 7) ?>" data-autosubmit></div><div style="flex:0"><button class="btn">Show</button></div>
  <div class="push"><a class="btn btn-primary" href="?export=1">&#8681; Download all (month-wise Excel)</a></div></form></div>
<div class="card"><h3><?= e(date('F Y', strtotime("$month-01"))) ?> &middot; <?= count($days) ?> working day(s)</h3>
<div class="table-wrap"><table class="stack stack-nums"><tr><th>Faculty</th><th>Present</th><th>Late</th><th>Half day</th><th>Absent</th><th>Leave</th><th>Not marked</th><th>%</th><th>Day by day</th></tr>
<?php foreach ($faculty as $f): $cells = $grid[$f['id']] ?? []; $t = staff_totals($cells); ?><tr><td class="who-cell"><b><?= e($f['name']) ?></b></td><td data-label="Present"><?= $t['P'] ?></td><td data-label="Late"><?= $t['L'] ?></td><td data-label="Half day"><?= $t['H'] ?></td><td data-label="Absent"><?= $t['A'] ?></td><td data-label="Leave"><?= $t['LV'] ?></td><td data-label="Not marked"><?= $t[''] ?></td>
  <td data-label="%"><?= $t['pct'] === null ? badge('No data') : pct_badge((float)$t['pct']) ?></td>
  <td class="wide" data-label="Day by day"><div class="daydots"><?php foreach ($cells as $d => $c) echo '<a class="dd dd-' . ($c[0] ?: 'none') . '" href="?date=' . e($d) . '" title="' . e(fmt_date($d, 'd M') . ': ' . (STAFF_STATUS[$c[0]][0] ?? 'Not marked') . ($c[2] !== '' ? ' - ' . $c[2] : '')) . '">' . (int)substr($d, 8) . '</a>'; ?></div></td></tr>
<?php endforeach; if (!$faculty) echo empty_row(9, 'No faculty yet.'); ?></table></div>
<p class="small muted">Green present &middot; amber late or half day &middot; pink absent &middot; violet leave &middot; grey holiday &middot; outline not marked. Click a date to mark or correct it.</p></div>
<?php endif; ?>
<?php dash_footer(); ?>
