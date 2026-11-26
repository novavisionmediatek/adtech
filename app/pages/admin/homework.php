<?php
$s = sid();
if (is_post() && post('action') === 'delete') {
    $h = row('SELECT * FROM homework WHERE id=? AND school_id=?', [(int)post('id'), $s]);
    if ($h) { delete_homework($h); audit('homework_deleted', (string)$h['id']); flash('Homework removed.'); }
    redirect('admin/homework');
}
$cf = (int)inp('class');
[$rows, $pager] = paginate('SELECT h.*, c.name cname, c.section, sub.name sname, u.name fname FROM homework h JOIN classes c ON c.id=h.class_id JOIN subjects sub ON sub.id=h.subject_id JOIN users u ON u.id=h.faculty_id
   WHERE h.school_id=?' . ($cf ? ' AND h.class_id=?' : '') . ' ORDER BY h.created_at DESC, h.id DESC', $cf ? [$s, $cf] : [$s], 10);
$counts = hw_counts(array_column($rows, 'id'));
$size = []; foreach (q('SELECT class_id, COUNT(*) n FROM students WHERE school_id=? AND active=1 GROUP BY class_id', [$s]) as $x) $size[$x['class_id']] = (int)$x['n'];
dash_header('Homework (all classes)', '<a class="btn btn-sm" href="/admin/homework_report' . ($cf ? '?class=' . $cf : '') . '">Tracking report &amp; download</a>');
?>
<div class="card"><form method="get" class="inline-form"><div><label>Class</label><select name="class" data-autosubmit><option value="">All</option><?php foreach (school_classes($s) as $c) echo '<option value="' . (int)$c['id'] . '"' . ($cf === (int)$c['id'] ? ' selected' : '') . '>' . e(class_label($c)) . '</option>'; ?></select></div></form></div>
<div class="card"><div class="table-wrap"><table><tr><th>Class</th><th>Subject</th><th>Title</th><th>By</th><th>Due</th><th>File</th><th>Completion</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e(cls($r)) ?></td><td><?= e($r['sname']) ?></td><td><b><?= e($r['title']) ?></b><br><span class="muted small"><?= e(mb_strimwidth((string)$r['description'], 0, 80, '...')) ?></span></td><td><?= e($r['fname']) ?></td><td><?= e(fmt_date($r['due_date'])) ?></td>
  <td><?= $r['file_path'] ? '<a href="/file/homework/' . (int)$r['id'] . '">' . e($r['file_name']) . '</a>' : '-' ?></td>
  <td><?= hw_progress_cell($r, $counts, $size[$r['class_id']] ?? 0, 'admin') ?></td>
  <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger"<?= confirm_attr('Delete this homework?') ?>>Delete</button></form></td></tr>
<?php endforeach; if (!$rows) echo empty_row(8); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
