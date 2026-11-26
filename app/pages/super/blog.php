<?php
$edit = (int)inp('edit') ? row('SELECT * FROM posts WHERE id=?', [(int)inp('edit')]) : null;
if (is_post()) {
    $a = post('action');
    try {
        if ($a === 'save') {
            $body = sanitize_html((string)($_POST['body'] ?? ''));
            if (post('title') === '' || !html_has_content($body)) throw new RuntimeException('Title and body are required.');
            $excerpt = post('excerpt') !== '' ? post('excerpt') : mb_strimwidth(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8'))), 0, 160, '...');
            $pub = isset($_POST['published']) ? 1 : 0; $id = (int)post('id');
            if ($id) { run('UPDATE posts SET title=?, excerpt=?, body=?, category=?, published=? WHERE id=?', [post('title'), $excerpt, $body, post('category'), $pub, $id]); }
            else { $slug = slugify(post('title')); $n = 1; $base = $slug; while (val('SELECT 1 FROM posts WHERE slug=?', [$slug])) $slug = $base . '-' . (++$n);
                ins('INSERT INTO posts(slug,title,excerpt,body,category,published) VALUES(?,?,?,?,?,?)', [$slug, post('title'), $excerpt, $body, post('category'), $pub]); }
            audit('post_saved', post('title')); flash('Post saved.');
        } elseif ($a === 'delete') { run('DELETE FROM posts WHERE id=?', [(int)post('id')]); audit('post_deleted', post('id')); flash('Post deleted.'); }
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('super/blog');
}
[$rows, $pager] = paginate('SELECT * FROM posts ORDER BY created_at DESC, id DESC', [], 10);
dash_header('Blog manager');
?>
<div class="card"><h3><?= $edit ? 'Edit post' : 'New post' ?></h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <div class="form-grid"><div class="full"><label>Title *</label><input name="title" required value="<?= e($edit['title'] ?? '') ?>"></div><div><label>Category</label><input name="category" value="<?= e($edit['category'] ?? '') ?>"></div>
  <div><label>&nbsp;</label><label style="font-weight:400"><input type="checkbox" name="published" value="1"<?= !$edit || $edit['published'] ? ' checked' : '' ?>> Published</label></div>
  <div class="full"><label>Short summary <span class="muted">(optional &mdash; made from the post if left empty)</span></label><input name="excerpt" maxlength="200" value="<?= e($edit['excerpt'] ?? '') ?>"></div>
  <div class="full"><label>Content *</label><textarea name="body" data-editor><?= e(post_body_html((string)($edit['body'] ?? ''))) ?></textarea></div></div>
  <p><button class="btn btn-primary">Save post</button> <?php if ($edit) echo '<a class="btn" href="/super/blog">Cancel</a>'; ?></p></form></div>
<div class="card"><h3>All posts</h3><div class="table-wrap"><table><tr><th>Title</th><th>Category</th><th>Date</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><a href="/blog/<?= e($r['slug']) ?>" target="_blank"><?= e($r['title']) ?></a></td><td><?= e($r['category']) ?></td><td><?= e(fmt_date($r['created_at'])) ?></td><td><?= $r['published'] ? badge('Published', 'green') : badge('Draft') ?></td>
  <td class="right"><a class="btn btn-sm" href="?edit=<?= (int)$r['id'] ?>">Edit</a> <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger"<?= confirm_attr('Delete post?') ?>>Delete</button></form></td></tr>
<?php endforeach; ?></table></div><?= $pager ?></div>
<script src="/assets/js/editor.js"></script>
<?php dash_footer(); ?>
