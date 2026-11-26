<?php
$st = my_student();
if (is_post()) {
    try { submit_homework($st, (int)post('homework'), post('note')); flash('Your work was sent to the teacher.'); }
    catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('student/homework');
}
dash_header('Homework');
?>
<div class="card"><h3>Class <?= e(cls($st)) ?> homework</h3><?= view_homework((int)$st['class_id'], 50, (int)$st['id'], true, true) ?></div>
<?php dash_footer(); ?>
