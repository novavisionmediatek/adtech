<?php
$u = me();
$pairs = q('SELECT DISTINCT t.class_id, t.subject_id, c.name cname, c.section, sub.name sname FROM timetable t JOIN classes c ON c.id=t.class_id JOIN subjects sub ON sub.id=t.subject_id
   WHERE t.faculty_id=? ORDER BY (c.name+0), c.section, sub.name', [$u['id']]);
if (is_post()) {
    try {
        if (post('action') === 'delete') {
            $h = row('SELECT * FROM homework WHERE id=? AND faculty_id=?', [(int)post('id'), $u['id']]);
            if ($h) { delete_homework($h); flash('Homework deleted.'); }
        } else {
            [$cid, $sid2] = array_map('intval', explode('-', post('pair') . '-0'));
            $ok = false; foreach ($pairs as $p) if ((int)$p['class_id'] === $cid && (int)$p['subject_id'] === $sid2) $ok = true;
            if (!$ok) throw new RuntimeException('Choose one of your classes/subjects.');
            if (post('title') === '') throw new RuntimeException('Title is required.');
            $due = post('due'); if ($due !== '' && !valid_date($due)) throw new RuntimeException('Invalid due date.');
            $f = save_upload('file', 'homework', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'txt'], 5 * 1048576);
            ins('INSERT INTO homework(school_id,class_id,subject_id,faculty_id,title,description,due_date,file_path,file_name) VALUES(?,?,?,?,?,?,?,?,?)',
                [$u['school_id'], $cid, $sid2, $u['id'], post('title'), post('description'), $due ?: null, $f['path'] ?? null, $f['name'] ?? null]);
            audit('homework_added', post('title')); flash('Homework published for the class.');
        }
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('faculty/homework');
}
[$rows, $pager] = paginate('SELECT h.*, c.name cname, c.section, sub.name sname FROM homework h JOIN classes c ON c.id=h.class_id JOIN subjects sub ON sub.id=h.subject_id WHERE h.faculty_id=? ORDER BY h.created_at DESC, h.id DESC', [$u['id']], 10);
$counts = hw_counts(array_column($rows, 'id'));
$size = []; foreach (q('SELECT class_id, COUNT(*) n FROM students WHERE school_id=? AND active=1 GROUP BY class_id', [$u['school_id']]) as $x) $size[$x['class_id']] = (int)$x['n'];
dash_header('Homework', '<a class="btn btn-sm" href="/faculty/homework_report">Tracking report</a>');
?>
<div class="card"><h3>Upload homework</h3>
<?php if (!$pairs): ?><p class="muted">You have no classes in the timetable yet. Ask the school admin to assign periods.</p><?php else: ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><div class="form-grid">
  <div><label>Class &amp; subject *</label><select name="pair" required><?php foreach ($pairs as $p) echo '<option value="' . (int)$p['class_id'] . '-' . (int)$p['subject_id'] . '">Class ' . e(cls($p)) . ' - ' . e($p['sname']) . '</option>'; ?></select></div>
  <div><label>Due date</label><input type="date" name="due" min="<?= date('Y-m-d') ?>"></div>
  <div class="full"><label>Title *</label><input name="title" required maxlength="150"></div>
  <div class="full"><label>Instructions</label><textarea name="description"></textarea></div>
  <div class="full"><label>Attachment (PDF, DOC, PPT, image; max 5 MB)</label><input type="file" name="file"></div></div>
  <p><button class="btn btn-primary">Publish homework</button></p></form><?php endif; ?></div>
<div class="card"><div class="card-head"><h3>My homework</h3><a class="btn btn-sm" href="/faculty/homework_report">Tracking report &amp; download</a></div><div class="table-wrap"><table><tr><th>Posted</th><th>Class</th><th>Subject</th><th>Title</th><th>Due</th><th>File</th><th>Completion</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e(fmt_date($r['created_at'])) ?></td><td><?= e(cls($r)) ?></td><td><?= e($r['sname']) ?></td><td><b><?= e($r['title']) ?></b><br><span class="muted small"><?= e(mb_strimwidth((string)$r['description'], 0, 90, '...')) ?></span></td>
  <td><?= e(fmt_date($r['due_date'])) ?></td><td><?= $r['file_path'] ? '<a href="/file/homework/' . (int)$r['id'] . '">' . e($r['file_name']) . '</a>' : '-' ?></td>
  <td><?= hw_progress_cell($r, $counts, $size[$r['class_id']] ?? 0, 'faculty') ?></td>
  <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger"<?= confirm_attr('Delete homework?') ?>>Delete</button></form></td></tr>
<?php endforeach; if (!$rows) echo empty_row(8); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
