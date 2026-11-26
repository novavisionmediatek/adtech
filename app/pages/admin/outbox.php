<?php
[$rows, $pager] = paginate('SELECT * FROM outbox WHERE school_id=? ORDER BY id DESC', [sid()], 20);
dash_header('Email log');
?>
<div class="card"><p class="muted small">Every e-mail sent by the portal for your school (absence alerts, welcome mails). In demo mode (no SMTP configured) messages are stored here instead of being delivered. Click a subject to preview.</p>
<?= outbox_table($rows) ?><?= $pager ?></div>
<?php dash_footer(); ?>
