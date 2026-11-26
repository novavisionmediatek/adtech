<?php
if (is_post()) {
    if (post('action') === 'delete') run('DELETE FROM messages WHERE id=?', [(int)post('id')]);
    else run('UPDATE messages SET handled=1-handled WHERE id=?', [(int)post('id')]);
    redirect('super/messages');
}
[$rows, $pager] = paginate('SELECT * FROM messages ORDER BY handled, id DESC', [], 10);
dash_header('Contact messages');
?>
<div class="card"><?php foreach ($rows as $m): ?><div class="slot" style="align-items:flex-start"><div class="info"><b><?= e($m['subject'] ?: '(no subject)') ?></b> <?= $m['handled'] ? badge('Handled', 'green') : badge('New', 'amber') ?>
  <div class="muted small"><?= e($m['name']) ?> &middot; <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a> &middot; <?= e($m['phone']) ?> &middot; <?= e(fmt_date($m['created_at'], 'd M, h:i A')) ?></div><p><?= nl2br(e($m['message'])) ?></p></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm"><?= $m['handled'] ? 'Reopen' : 'Mark handled' ?></button> <button name="action" value="delete" class="btn btn-sm btn-danger"<?= confirm_attr('Delete message?') ?>>Delete</button></form></div>
<?php endforeach; if (!$rows) echo '<p class="muted center">No messages yet.</p>'; ?><?= $pager ?></div>
<?php dash_footer(); ?>
