<?php
$s = sid(); $today = date('Y-m-d');
$day = inp('day', $today); if (!valid_date($day)) $day = $today;
if (is_post()) {
    $a = post('action');
    try {
        $range = function (): array {
            $from = post('from'); $to = post('to') ?: $from;
            if (!valid_date($from) || !valid_date($to)) throw new RuntimeException('Choose valid dates.');
            if ($to < $from) throw new RuntimeException('"To" date cannot be before the "From" date.');
            if ((strtotime($to) - strtotime($from)) / 86400 > 366) throw new RuntimeException('The range is too long.');
            return [$from, $to];
        };
        if ($a === 'holiday') {
            [$from, $to] = $range(); $title = post('title'); $cid = (int)post('class_id');
            if ($title === '') throw new RuntimeException('Give the holiday a name.');
            if ($cid && !val('SELECT 1 FROM classes WHERE id=? AND school_id=?', [$cid, $s])) throw new RuntimeException('Invalid class.');
            run('INSERT INTO holidays(school_id,class_id,title,from_date,to_date,created_by,created_at) VALUES(?,?,?,?,?,?,?)', [$s, $cid ?: null, $title, $from, $to, me()['id'], date('Y-m-d H:i:s')]);
            if (post('notice') && !$cid) run('INSERT INTO notices(school_id,title,body,created_by) VALUES(?,?,?,?)', [$s, 'Holiday: ' . $title, 'The school will remain closed on ' . date_range_label($from, $to) . '.', me()['id']]);
            $had = (int)val('SELECT COUNT(*) FROM attendance WHERE school_id=? AND date BETWEEN ? AND ?' . ($cid ? ' AND class_id=' . $cid : ''), [$s, $from, $to]);
            audit('holiday_added', "$title $from..$to");
            flash('Holiday added.' . (post('notice') && !$cid ? ' A notice was published.' : '') . ($had ? " Note: $had attendance record(s) already exist on these dates and were kept." : ''), $had ? 'warn' : 'ok');
        } elseif ($a === 'holiday_delete') {
            run('DELETE FROM holidays WHERE id=? AND school_id=?', [(int)post('id'), $s]); audit('holiday_deleted', post('id')); flash('Holiday removed.');
        } elseif ($a === 'leave') {
            [$from, $to] = $range(); $fid = (int)post('faculty_id'); $type = in_array(post('type'), LEAVE_TYPES, true) ? post('type') : 'Other';
            $name = val("SELECT name FROM users WHERE id=? AND school_id=? AND role='faculty'", [$fid, $s]);
            if (!$name) throw new RuntimeException('Choose a faculty member.');
            if (val('SELECT 1 FROM faculty_leave WHERE faculty_id=? AND from_date<=? AND to_date>=?', [$fid, $to, $from])) throw new RuntimeException("$name already has leave on some of these dates.");
            run('INSERT INTO faculty_leave(school_id,faculty_id,from_date,to_date,type,note,created_by,created_at) VALUES(?,?,?,?,?,?,?,?)', [$s, $fid, $from, $to, $type, mb_substr(post('note'), 0, 255), me()['id'], date('Y-m-d H:i:s')]);
            audit('leave_added', "$name $from..$to"); flash("$name marked off. Now choose substitute teachers below.");
            redirect('admin/leave?day=' . $from . '#substitutes');
        } elseif ($a === 'leave_delete') {
            $l = row('SELECT * FROM faculty_leave WHERE id=? AND school_id=?', [(int)post('id'), $s]);
            if ($l) {
                run('DELETE FROM substitutions WHERE school_id=? AND date BETWEEN ? AND ? AND timetable_id IN (SELECT id FROM timetable WHERE faculty_id=?)', [$s, $l['from_date'], $l['to_date'], $l['faculty_id']]);
                run("UPDATE leave_requests SET status='cancelled', decided_note='Cancelled by the school' WHERE leave_id=? AND school_id=?", [$l['id'], $s]);
                run('DELETE FROM faculty_leave WHERE id=?', [$l['id']]); audit('leave_deleted', (string)$l['id']); flash('Leave cancelled.');
            }
        } elseif ($a === 'substitute') {
            $periods = day_periods($s, $day); $away = away_on($s, $day); $chosen = (array)($_POST['sub'] ?? []);
            $busy = []; // [period][faculty] = true : teaching their own class or already covering
            foreach ($periods as $p) if ($p['holiday'] === null && !$p['away']) $busy[$p['period']][(int)$p['faculty_id']] = true;
            $saved = 0; $errors = [];
            $pdo = db(); $pdo->beginTransaction();
            foreach ($periods as $p) {
                if (!$p['away'] || $p['holiday'] !== null || !isset($chosen[$p['id']])) continue;
                $fid = (int)$chosen[$p['id']];
                run('DELETE FROM substitutions WHERE date=? AND timetable_id=?', [$day, $p['id']]);
                if (!$fid) continue;
                if (!val("SELECT 1 FROM users WHERE id=? AND school_id=? AND role='faculty' AND active=1", [$fid, $s]) || isset($away[$fid])) { $errors[] = 'P' . $p['period'] . ' ' . cls($p) . ': teacher not available.'; continue; }
                if (isset($busy[$p['period']][$fid])) { $errors[] = 'P' . $p['period'] . ' ' . cls($p) . ': that teacher already has a class in this period.'; continue; }
                $busy[$p['period']][$fid] = true;
                run('INSERT INTO substitutions(school_id,date,timetable_id,faculty_id,created_by) VALUES(?,?,?,?,?)', [$s, $day, $p['id'], $fid, me()['id']]); $saved++;
            }
            $pdo->commit(); audit('substitutes_saved', "$day: $saved");
            flash("Substitutes saved for " . fmt_date($day) . ": $saved period(s) covered." . ($errors ? ' ' . implode(' ', $errors) : ''), $errors ? 'warn' : 'ok');
            redirect('admin/leave?day=' . $day . '#substitutes');
        }
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('admin/leave?day=' . $day);
}
$classes = school_classes($s); $faculty = school_faculty($s);
[$holidays, $holidayPager] = paginate('SELECT h.*, c.name cname, c.section FROM holidays h LEFT JOIN classes c ON c.id=h.class_id WHERE h.school_id=? ORDER BY h.from_date DESC, h.id DESC', [$s], 10);
[$leaves, $leavePager] = paginate('SELECT l.*, u.name FROM faculty_leave l JOIN users u ON u.id=l.faculty_id WHERE l.school_id=? ORDER BY l.from_date DESC, l.id DESC', [$s], 10, 'pg2');
$periods = day_periods($s, $day); $away = away_on($s, $day);
$need = array_values(array_filter($periods, fn($p) => $p['away'] && $p['holiday'] === null));
$busy = []; foreach ($periods as $p) if ($p['holiday'] === null && !$p['away']) $busy[$p['period']][(int)$p['faculty_id']] = true;
foreach ($need as $p) if ($p['cover_id']) $busy[$p['period']][$p['cover_id']] = $p['id'];
$when = fn(string $from, string $to) => $to < $today ? badge('Past') : ($from <= $today ? badge('Today', 'green') : badge('Upcoming', 'blue'));
dash_header('Holidays & leave');
?>
<div class="grid g2">
<div class="card"><h3>Mark a holiday</h3><p class="muted small">Attendance cannot be marked on a holiday.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="holiday"><div class="form-grid">
    <div class="full"><label>Holiday name *</label><input name="title" required maxlength="150" placeholder="e.g. Guru Nanak Jayanti"></div>
    <div><label>From *</label><input type="date" name="from" required value="<?= e($day) ?>"></div><div><label>To <span class="muted">(same day if empty)</span></label><input type="date" name="to"></div>
    <div class="full"><label>Applies to</label><select name="class_id"><option value="0">Whole school</option><?php foreach ($classes as $c) echo '<option value="' . (int)$c['id'] . '">Only class ' . e(class_label($c)) . '</option>'; ?></select></div>
    <div class="full pw-opts"><label><input type="checkbox" name="notice" value="1" checked> Also publish a notice (whole-school holidays)</label></div></div>
    <p><button class="btn btn-primary">Add holiday</button></p></form></div>
<div class="card"><h3>Mark faculty off</h3><p class="muted small">Their periods can then be given to a substitute.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="leave"><div class="form-grid">
    <div class="full"><label>Faculty *</label><select name="faculty_id" required><option value="">Choose...</option><?php foreach ($faculty as $f) if ($f['active']) echo '<option value="' . (int)$f['id'] . '">' . e($f['name']) . '</option>'; ?></select></div>
    <div><label>From *</label><input type="date" name="from" required value="<?= e($day) ?>"></div><div><label>To <span class="muted">(same day if empty)</span></label><input type="date" name="to"></div>
    <div><label>Type</label><select name="type"><?php foreach (LEAVE_TYPES as $t) echo '<option>' . e($t) . '</option>'; ?></select></div><div><label>Note</label><input name="note" maxlength="255" placeholder="optional"></div></div>
    <p><button class="btn btn-primary">Mark off</button></p></form></div>
</div>

<div class="card" id="substitutes"><div class="card-head"><h3>Substitute teachers &middot; <?= e(fmt_date($day, 'l, d M Y')) ?></h3>
  <form method="get" class="inline-form"><input type="date" name="day" value="<?= e($day) ?>"><button class="btn btn-sm">Show day</button></form></div>
  <?php if (!$periods): ?><p class="muted">No classes on this day.</p>
  <?php elseif (isset(holidays_on($s, $day)[0])): ?><p class="muted">Holiday: <b><?= e(holidays_on($s, $day)[0]) ?></b>. No substitutes needed.</p>
  <?php elseif (!$need): ?><p class="muted">No periods need a substitute on this day.</p>
  <?php else: ?>
  <form method="post" action="/admin/leave?day=<?= e($day) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="substitute">
  <div class="table-wrap"><table><tr><th>Period</th><th>Class</th><th>Subject</th><th>Teacher off</th><th>Substitute</th><th>Status</th></tr>
  <?php foreach ($need as $p): ?><tr><td>P<?= (int)$p['period'] ?> <span class="muted small"><?= e(period_time((int)$p['period'])) ?></span></td><td><?= e(cls($p)) ?></td><td><?= e($p['sname']) ?></td><td><?= e($p['owner']) ?></td>
    <td><select name="sub[<?= (int)$p['id'] ?>]"><option value="0">-- none --</option><?php foreach ($faculty as $f) { $fid = (int)$f['id']; if (!$f['active'] || isset($away[$fid])) continue; $taken = isset($busy[$p['period']][$fid]) && $busy[$p['period']][$fid] !== $p['id']; if ($taken && $p['cover_id'] !== $fid) continue; echo '<option value="' . $fid . '"' . ($p['cover_id'] === $fid ? ' selected' : '') . '>' . e($f['name']) . '</option>'; } ?></select></td>
    <td><?= $p['cover_id'] ? badge('Covered', 'green') : badge('Not covered', 'red') ?></td></tr><?php endforeach; ?></table></div>
  <p class="small muted">Only teachers who are free in that period are listed.</p><p><button class="btn btn-primary">Save substitutes</button></p></form><?php endif; ?></div>

<div class="grid g2">
<div class="card"><h3>Holidays</h3><div class="table-wrap"><table><tr><th>Holiday</th><th>Dates</th><th>For</th><th></th></tr>
<?php foreach ($holidays as $h): ?><tr><td><b><?= e($h['title']) ?></b><br><?= $when($h['from_date'], $h['to_date']) ?></td><td><?= e(date_range_label($h['from_date'], $h['to_date'])) ?></td><td><?= $h['class_id'] ? 'Class ' . e(cls($h)) : 'Whole school' ?></td>
  <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="holiday_delete"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="btn btn-sm btn-danger"<?= confirm_attr('Remove this holiday?') ?>>Remove</button></form></td></tr>
<?php endforeach; if (!$holidays) echo empty_row(4, 'No holidays added yet.'); ?></table></div><?= $holidayPager ?></div>
<div class="card"><h3>Faculty leave</h3><div class="table-wrap"><table><tr><th>Faculty</th><th>Dates</th><th>Type</th><th></th></tr>
<?php foreach ($leaves as $l): ?><tr><td><b><?= e($l['name']) ?></b><br><?= $when($l['from_date'], $l['to_date']) ?></td><td><a href="/admin/leave?day=<?= e(max($l['from_date'], min($today, $l['to_date']))) ?>#substitutes"><?= e(date_range_label($l['from_date'], $l['to_date'])) ?></a></td><td><?= e($l['type']) ?><br><span class="muted small"><?= e($l['note']) ?></span></td>
  <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="leave_delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-danger"<?= confirm_attr('Cancel this leave? Substitutes for it are removed too.') ?>>Cancel</button></form></td></tr>
<?php endforeach; if (!$leaves) echo empty_row(4, 'No leave recorded.'); ?></table></div><?= $leavePager ?></div>
</div>
<?php dash_footer(); ?>
