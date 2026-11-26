<?php
$s = sid();
if (is_post()) {
    try {
        $a = post('action');
        if ($a === 'add') {
            $n = post('name'); if ($n === '') throw new RuntimeException('Class name is required.');
            $ct = (int)post('teacher') ?: null;
            if ($ct && !val("SELECT 1 FROM users WHERE id=? AND school_id=? AND role='faculty'", [$ct, $s])) throw new RuntimeException('Invalid teacher.');
            run('INSERT INTO classes(school_id,name,section,class_teacher_id) VALUES(?,?,?,?)', [$s, $n, strtoupper(post('section')), $ct]);
            audit('class_added', $n . post('section')); flash('Class added.');
        } elseif ($a === 'teacher') {
            $ct = (int)post('teacher') ?: null;
            run('UPDATE classes SET class_teacher_id=? WHERE id=? AND school_id=?', [$ct, (int)post('id'), $s]); flash('Class teacher updated.');
        } elseif ($a === 'delete') {
            if (val('SELECT 1 FROM students WHERE class_id=? AND school_id=?', [(int)post('id'), $s])) throw new RuntimeException('Cannot delete a class that has students.');
            run('DELETE FROM classes WHERE id=? AND school_id=?', [(int)post('id'), $s]); audit('class_deleted', post('id')); flash('Class deleted.');
        }
    } catch (PDOException $x) { flash('This class and section already exist.', 'err'); }
    catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('admin/classes');
}
[$rows, $pager] = paginate('SELECT c.*, c.name cname, (SELECT COUNT(*) FROM students st WHERE st.class_id=c.id AND st.active=1) n FROM classes c WHERE c.school_id=? ORDER BY (c.name+0), c.name, c.section', [$s], 20);
$fac = school_faculty($s);
$opts = '<option value="">&mdash; none &mdash;</option>'; foreach ($fac as $f) $opts .= '<option value="' . (int)$f['id'] . '">' . e($f['name']) . '</option>';
dash_header('Classes');
?>
<div class="card"><h3>Add class</h3><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div><label>Class / Standard *</label><input name="name" placeholder="e.g. 6" required></div><div><label>Section</label><input name="section" placeholder="A" maxlength="3"></div>
  <div><label>Class teacher</label><select name="teacher"><?= $opts ?></select></div><div style="flex:0"><button class="btn btn-primary">Add</button></div></form></div>
<div class="card"><h3>All classes</h3><div class="table-wrap"><table><tr><th>Class</th><th>Students</th><th>Class teacher</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><b><?= e(cls($r)) ?></b></td><td><?= (int)$r['n'] ?></td>
  <td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="teacher"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <select name="teacher" style="max-width:220px"><option value="">&mdash; none &mdash;</option><?php foreach ($fac as $f) echo '<option value="' . (int)$f['id'] . '"' . ((int)$r['class_teacher_id'] === (int)$f['id'] ? ' selected' : '') . '>' . e($f['name']) . '</option>'; ?></select>
    <button class="btn btn-sm">Save</button></form></td>
  <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger"<?= confirm_attr('Delete class ' . cls($r) . '?') ?>>Delete</button></form></td></tr>
<?php endforeach; if (!$rows) echo empty_row(4, 'No classes yet.'); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
