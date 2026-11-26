<?php
$s = sid();
if (is_post()) {
    try {
        if (post('action') === 'add') {
            if (post('name') === '') throw new RuntimeException('Subject name is required.');
            run('INSERT INTO subjects(school_id,name,code) VALUES(?,?,?)', [$s, post('name'), strtoupper(post('code'))]); flash('Subject added.');
        } else {
            if (val('SELECT 1 FROM timetable WHERE subject_id=? AND school_id=?', [(int)post('id'), $s])) throw new RuntimeException('Subject is used in a timetable; remove it from the timetable first.');
            run('DELETE FROM subjects WHERE id=? AND school_id=?', [(int)post('id'), $s]); flash('Subject deleted.');
        }
    } catch (PDOException $x) { flash('Subject already exists.', 'err'); }
    catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('admin/subjects');
}
[$rows, $pager] = paginate('SELECT * FROM subjects WHERE school_id=? ORDER BY name', [$s], 20);
dash_header('Subjects');
?>
<div class="card"><h3>Add subject</h3><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div><label>Subject name *</label><input name="name" required></div><div><label>Code</label><input name="code" maxlength="8"></div><div style="flex:0"><button class="btn btn-primary">Add</button></div></form></div>
<div class="card"><h3>Subjects</h3><div class="table-wrap"><table><tr><th>Subject</th><th>Code</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['code']) ?></td><td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger"<?= confirm_attr('Delete subject?') ?>>Delete</button></form></td></tr>
<?php endforeach; if (!$rows) echo empty_row(3); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
