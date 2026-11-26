<?php
$u = me(); $s = (int)$u['school_id'];
// a teacher may enter marks for the class + subject combinations they teach in the timetable
$mine = 'EXISTS (SELECT 1 FROM timetable t WHERE t.faculty_id=? AND t.class_id=p.class_id AND t.subject_id=p.subject_id)';
if (inp('paper')) {
    $paper = paper_row((int)inp('paper'), $s);
    if (!$paper || !val("SELECT 1 FROM exam_papers p WHERE p.id=? AND $mine", [$paper['id'], $u['id']])) render_error(404, 'Paper not found', 'This exam paper does not exist or is not yours.');
    marks_page($paper, 'faculty', !$paper['published']);
    return;
}
[$rows, $pager] = paginate("SELECT p.*, ex.name ename, ex.published, c.name cname, c.section, sub.name sname,
        (SELECT COUNT(*) FROM students st WHERE st.class_id=p.class_id AND st.active=1) n,
        (SELECT COUNT(*) FROM exam_marks m JOIN students st ON st.id=m.student_id AND st.active=1 WHERE m.paper_id=p.id) done
    FROM exam_papers p JOIN exams ex ON ex.id=p.exam_id JOIN classes c ON c.id=p.class_id JOIN subjects sub ON sub.id=p.subject_id
    WHERE ex.school_id=? AND $mine ORDER BY ex.published, ex.from_date DESC, p.exam_date, p.id", [$s, $u['id']], 20);
dash_header('Exam marks');
?>
<div class="card"><h3>My exam papers</h3><p class="muted small">You see the classes and subjects you teach in the timetable. Marks are locked once the school publishes the result.</p>
<div class="table-wrap"><table class="stack"><tr><th>Exam</th><th>Class</th><th>Subject</th><th>Exam date</th><th>Entered</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td class="who-cell"><b><?= e($r['ename']) ?></b></td><td data-label="Class"><?= e(cls($r)) ?></td><td data-label="Subject"><?= e($r['sname']) ?></td><td data-label="Exam date"><?= $r['exam_date'] ? e(fmt_date($r['exam_date'])) : '-' ?></td>
  <td data-label="Entered"><?= badge((int)$r['done'] . ' of ' . (int)$r['n'], $r['n'] && $r['done'] >= $r['n'] ? 'green' : ($r['done'] ? 'amber' : 'gray')) ?></td>
  <td class="right"><a class="btn btn-sm <?= $r['published'] ? '' : 'btn-primary' ?>" href="/faculty/marks?paper=<?= (int)$r['id'] ?>"><?= $r['published'] ? 'View (published)' : 'Enter marks' ?></a></td></tr>
<?php endforeach; if (!$rows) echo empty_row(6, 'No exam papers for you yet.'); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
