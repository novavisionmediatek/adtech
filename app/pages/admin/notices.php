<?php
$s = sid();
if (is_post()) {
    $a = post('action'); $id = (int)post('id');
    if ($a === 'add' || $a === 'edit') {
        if (post('title') === '' || post('body') === '') { flash('Title and message are required.', 'err'); redirect('admin/notices' . ($a === 'edit' ? "?edit=$id" : '')); }
        if ($a === 'add') {
            run('INSERT INTO notices(school_id,title,body,created_by) VALUES(?,?,?,?)', [$s, post('title'), post('body'), me()['id']]); audit('notice_added', post('title')); flash('Notice published.');
        } elseif (val('SELECT 1 FROM notices WHERE id=? AND school_id=?', [$id, $s])) { // only this school's notices
            run('UPDATE notices SET title=?, body=? WHERE id=? AND school_id=?', [post('title'), post('body'), $id, $s]); audit('notice_edited', post('title')); flash('Notice updated.');
        } else flash('Notice not found.', 'err');
    } elseif ($a === 'delete') { run('DELETE FROM notices WHERE id=? AND school_id=?', [$id, $s]); audit('notice_deleted', (string)$id); flash('Notice deleted.'); }
    redirect('admin/notices');
}
$edit = (int)inp('edit') ? row('SELECT * FROM notices WHERE id=? AND school_id=?', [(int)inp('edit'), $s]) : null;
[$rows, $pager] = paginate('SELECT * FROM notices WHERE school_id=? ORDER BY created_at DESC, id DESC', [$s], 10);
dash_header('Notices');
?>
<div class="card"><h3><?= $edit ? 'Edit notice' : 'Publish notice' ?></h3><p class="muted small">Visible to all faculty, students and parents of your school.</p>
  <form method="post" action="/admin/notices"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>"><?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
  <p><label>Title *</label><input name="title" required maxlength="150" value="<?= e($edit['title'] ?? '') ?>"<?= $edit ? ' autofocus' : '' ?>></p><p><label>Message *</label><textarea name="body" required><?= e($edit['body'] ?? '') ?></textarea></p>
  <button class="btn btn-primary"><?= $edit ? 'Save changes' : 'Publish' ?></button> <?php if ($edit): ?><a class="btn" href="/admin/notices">Cancel</a><?php endif; ?></form></div>
<div class="card"><h3>Published notices</h3>
<?php foreach ($rows as $n): ?><div class="slot"><div class="info"><b><?= e($n['title']) ?></b><?= $edit && (int)$edit['id'] === (int)$n['id'] ? ' ' . badge('Editing', 'blue') : '' ?><div><?= nl2br(e($n['body'])) ?></div><small class="muted"><?= e(fmt_date($n['created_at'], 'd M Y, h:i A')) ?></small></div>
  <a class="btn btn-sm" href="/admin/notices?edit=<?= (int)$n['id'] ?>">Edit</a>
  <form method="post" action="/admin/notices?<?= e(http_build_query(array_intersect_key($_GET, ['pg' => 1, 'pgs' => 1]))) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn btn-sm btn-danger"<?= confirm_attr('Delete notice?') ?>>Delete</button></form></div>
<?php endforeach; if (!$rows) echo '<p class="muted">No notices yet.</p>'; ?><?= $pager ?></div>
<?php dash_footer(); ?>
