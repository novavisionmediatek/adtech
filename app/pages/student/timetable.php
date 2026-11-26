<?php
$st = my_student();
dash_header('Timetable');
?>
<div class="card"><h3>Class <?= e(cls($st)) ?> &middot; weekly timetable</h3><?= view_timetable((int)$st['class_id']) ?></div>
<?php dash_footer(); ?>
