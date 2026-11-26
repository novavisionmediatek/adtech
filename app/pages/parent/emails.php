<?php
[$rows, $pager] = paginate('SELECT * FROM outbox WHERE lower(to_email)=lower(?) ORDER BY id DESC', [me()['email']], 20);
dash_header('Alerts received');
?>
<div class="card"><p class="muted small">Copy of every e-mail the school portal has sent to <b><?= e(me()['email']) ?></b>, including absence alerts.</p><?= outbox_table($rows) ?><?= $pager ?></div>
<?php dash_footer(); ?>
