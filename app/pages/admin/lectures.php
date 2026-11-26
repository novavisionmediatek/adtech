<?php
$s = sid();
if (is_post() && post('action') === 'delete') {
    $l = row('SELECT * FROM lectures WHERE id=? AND school_id=?', [(int)post('id'), $s]);
    if ($l) { delete_lecture($l); audit('lecture_deleted', (string)$l['id']); flash('Lecture removed.'); }
    redirect('admin/lectures');
}
$cf = (int)inp('class');
[$rows, $pager] = paginate('SELECT l.*, c.name cname, c.section, sub.name sname, u.name fname FROM lectures l JOIN classes c ON c.id=l.class_id JOIN subjects sub ON sub.id=l.subject_id JOIN users u ON u.id=l.faculty_id
   WHERE l.school_id=?' . ($cf ? ' AND l.class_id=?' : '') . ' ORDER BY l.created_at DESC, l.id DESC', $cf ? [$s, $cf] : [$s], 10);
$items = lecture_items(array_column($rows, 'id'));
dash_header('Lectures (all classes)');
?>
<div class="card"><form method="get" class="inline-form"><div><label>Class</label><select name="class" data-autosubmit><option value="">All</option><?php foreach (school_classes($s) as $c) echo '<option value="' . (int)$c['id'] . '"' . ($cf === (int)$c['id'] ? ' selected' : '') . '>' . e(class_label($c)) . '</option>'; ?></select></div></form></div>
<div class="card"><div class="table-wrap"><table><tr><th>Date</th><th>Class</th><th>Subject</th><th>Title</th><th>By</th><th>Content</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e(fmt_date($r['lecture_date'] ?: $r['created_at'])) ?></td><td><?= e(cls($r)) ?></td><td><?= e($r['sname']) ?></td><td><b><?= e($r['title']) ?></b></td><td><?= e($r['fname']) ?></td>
  <td><?= lecture_items_links($items[$r['id']] ?? []) ?></td>
  <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger"<?= confirm_attr('Delete this lecture?') ?>>Delete</button></form></td></tr>
<?php endforeach; if (!$rows) echo empty_row(7); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
