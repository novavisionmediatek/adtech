<?php
[$rows, $pager] = paginate('SELECT * FROM notices WHERE school_id=? OR school_id IS NULL ORDER BY created_at DESC, id DESC', [sid()], 10);
dash_header('Notices');
?>
<div class="card"><?= notices_html($rows) ?><?= $pager ?></div>
<?php dash_footer(); ?>
