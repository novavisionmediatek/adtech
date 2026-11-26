<?php
$s = sid();
$rid = (int)inp('route'); $route = $rid ? row('SELECT * FROM transport_routes WHERE id=? AND school_id=?', [$rid, $s]) : null;
if ($rid && !$route) render_error(404, 'Route not found', 'This route does not exist.');
if (is_post()) {
    try {
        $a = post('action');
        if ($a === 'route') {
            $name = mb_substr(post('name'), 0, 120); if ($name === '') throw new RuntimeException('Give the route a name.');
            $v = [$name, mb_substr(post('vehicle_no'), 0, 30), mb_substr(post('driver_name'), 0, 120), mb_substr(post('driver_phone'), 0, 20), mb_substr(post('stops'), 0, 700)];
            if ($route) { run('UPDATE transport_routes SET name=?, vehicle_no=?, driver_name=?, driver_phone=?, stops=? WHERE id=?', array_merge($v, [$rid])); flash('Route saved.'); }
            else { $rid = ins('INSERT INTO transport_routes(school_id,name,vehicle_no,driver_name,driver_phone,stops,created_at) VALUES(?,?,?,?,?,?,?)', array_merge([$s], $v, [date('Y-m-d H:i:s')])); flash('Route added. Now add students to it.'); }
            audit('route_saved', $name);
        } elseif ($a === 'assign' && $route) {
            $st = row('SELECT st.id, u.name FROM students st JOIN users u ON u.id=st.user_id WHERE st.school_id=? AND lower(st.admission_no)=? AND st.active=1', [$s, strtolower(preg_replace('/\s+/', '', post('admission_no')))]);
            if (!$st) throw new RuntimeException('No active student with that admission number.');
            $time = post('pickup_time'); if ($time !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) throw new RuntimeException('Enter the time like 07:15.');
            $moved = (bool)val('SELECT 1 FROM transport_students WHERE student_id=? AND route_id<>?', [$st['id'], $rid]);
            run('DELETE FROM transport_students WHERE student_id=?', [$st['id']]);
            run('INSERT INTO transport_students(student_id,school_id,route_id,stop,pickup_time) VALUES(?,?,?,?,?)', [$st['id'], $s, $rid, mb_substr(post('stop'), 0, 120), $time]);
            audit('transport_assigned', $st['name']); flash($st['name'] . ($moved ? ' moved to this route.' : ' saved on this route.'));
        } elseif ($a === 'unassign' && $route) { run('DELETE FROM transport_students WHERE student_id=? AND route_id=?', [(int)post('student'), $rid]); flash('Student removed from the route.'); }
        elseif ($a === 'delete' && $route) { run('DELETE FROM transport_students WHERE route_id=?', [$rid]); run('DELETE FROM transport_routes WHERE id=?', [$rid]); audit('route_deleted', $route['name']); flash('Route deleted.'); $rid = 0; }
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('admin/transport' . ($rid ? "?route=$rid" : ''));
}
$form = function (?array $r) {
    $v = fn(string $k) => e($r[$k] ?? '');
    echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="route"><div class="form-grid">' .
        '<div><label>Route name *</label><input name="name" required maxlength="120" value="' . $v('name') . '" placeholder="e.g. Route 1 - City centre"></div><div><label>Vehicle number</label><input name="vehicle_no" maxlength="30" value="' . $v('vehicle_no') . '"></div>' .
        '<div><label>Driver name</label><input name="driver_name" maxlength="120" value="' . $v('driver_name') . '"></div><div><label>Driver phone</label><input name="driver_phone" maxlength="20" value="' . $v('driver_phone') . '"></div>' .
        '<div class="full"><label>Stops (in order)</label><input name="stops" maxlength="700" value="' . $v('stops') . '" placeholder="Stop 1, Stop 2, Stop 3"></div></div><p><button class="btn btn-primary">' . ($r ? 'Save route' : 'Add route') . '</button></p></form>';
};
if ($route) {
    [$rows, $pager] = paginate('SELECT t.*, u.name, st.admission_no, c.name cname, c.section FROM transport_students t JOIN students st ON st.id=t.student_id JOIN users u ON u.id=st.user_id JOIN classes c ON c.id=st.class_id WHERE t.route_id=? ORDER BY t.pickup_time, t.stop, u.name, t.student_id', [$rid], 20);
    $students = q('SELECT st.admission_no, u.name FROM students st JOIN users u ON u.id=st.user_id WHERE st.school_id=? AND st.active=1 ORDER BY u.name LIMIT 1000', [$s]);
    dash_header($route['name'], '<a class="btn btn-sm" href="/admin/transport">&larr; All routes</a>');
    ?>
<div class="card"><h3>Add a student to this route</h3><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="assign">
  <div><label>Admission no. *</label><input name="admission_no" list="students" required autocomplete="off"><datalist id="students"><?php foreach ($students as $x) echo '<option value="' . e($x['admission_no']) . '">' . e($x['name']) . '</option>'; ?></datalist></div>
  <div><label>Stop</label><input name="stop" maxlength="120"></div><div><label>Pick-up time</label><input type="time" name="pickup_time"></div><div style="flex:0"><button class="btn btn-primary">Add</button></div></form></div>
<div class="card"><h3>Students on this route</h3><div class="table-wrap"><table class="stack"><tr><th>Student</th><th>Class</th><th>Stop</th><th>Pick-up</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td class="who-cell"><b><?= e($r['name']) ?></b> <span class="muted small"><?= e($r['admission_no']) ?></span></td><td data-label="Class"><?= e(cls($r)) ?></td><td data-label="Stop"><?= e($r['stop'] ?: '-') ?></td><td data-label="Pick-up"><?= e($r['pickup_time'] ?: '-') ?></td>
  <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unassign"><input type="hidden" name="student" value="<?= (int)$r['student_id'] ?>"><button class="btn btn-sm btn-danger"<?= confirm_attr('Remove from this route?') ?>>Remove</button></form></td></tr>
<?php endforeach; if (!$rows) echo empty_row(5, 'No students yet.'); ?></table></div><?= $pager ?></div>
<div class="card"><h3>Route details</h3><?php $form($route); ?></div>
<form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-danger" name="action" value="delete"<?= confirm_attr('Delete this route and remove its students from it?') ?>>Delete route</button></form>
    <?php dash_footer(); return;
}
$routes = q('SELECT r.*, (SELECT COUNT(*) FROM transport_students t WHERE t.route_id=r.id) n FROM transport_routes r WHERE r.school_id=? ORDER BY r.name', [$s]);
dash_header('Transport');
?>
<div class="card"><h3>Bus routes</h3><div class="table-wrap"><table class="stack"><tr><th>Route</th><th>Vehicle</th><th>Driver</th><th>Students</th><th></th></tr>
<?php foreach ($routes as $r): ?><tr><td class="who-cell"><b><?= e($r['name']) ?></b><br><span class="muted small"><?= e($r['stops']) ?></span></td><td data-label="Vehicle"><?= e($r['vehicle_no'] ?: '-') ?></td><td data-label="Driver"><?= e($r['driver_name'] ?: '-') ?><br><span class="muted small"><?= e($r['driver_phone']) ?></span></td><td data-label="Students"><?= (int)$r['n'] ?></td>
  <td class="right"><a class="btn btn-sm btn-primary" href="?route=<?= (int)$r['id'] ?>">Open</a></td></tr>
<?php endforeach; if (!$routes) echo empty_row(5, 'No routes yet.'); ?></table></div></div>
<div class="card"><h3>Add a route</h3><?php $form(null); ?></div>
<?php dash_footer(); ?>
