<?php
$st = my_student(); $sub = (int)inp('subject');
dash_header('Lectures');
?>
<div class="card"><h3>Class <?= e(cls($st)) ?> lectures</h3><p class="muted small">Recorded lectures, notes and links shared by your teachers. Revise any time.</p>
<?= lecture_filter((int)$st['class_id'], $sub) ?><?= view_lectures((int)$st['class_id'], $sub) ?></div>
<?php dash_footer(); ?>
