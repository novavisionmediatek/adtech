<?php
$s = sid(); $classes = school_classes($s); $subjects = school_subjects($s);
$id = (int)inp('id'); $exam = $id ? row('SELECT * FROM exams WHERE id=? AND school_id=?', [$id, $s]) : null;
if ($id && !$exam) render_error(404, 'Exam not found', 'This exam does not exist.');
if (is_post()) {
    $a = post('action');
    try {
        if ($a === 'create') {
            $name = post('name'); $from = post('from'); $to = post('to') ?: $from; $max = (int)post('max'); $pass = (int)post('pass');
            if ($name === '') throw new RuntimeException('Give the exam a name.');
            if (!valid_date($from) || !valid_date($to) || $to < $from) throw new RuntimeException('Choose valid exam dates.');
            if ($max < 1 || $max > 1000) throw new RuntimeException('Maximum marks must be between 1 and 1000.');
            if ($pass < 0 || $pass > 100) throw new RuntimeException('Pass percentage must be between 0 and 100.');
            $chosen = array_intersect(array_map('intval', (array)($_POST['classes'] ?? [])), array_map(fn($c) => (int)$c['id'], $classes));
            if (!$chosen) throw new RuntimeException('Tick at least one class.');
            if (!$subjects) throw new RuntimeException('Add subjects first.');
            $pdo = db(); $pdo->beginTransaction();
            $eid = ins('INSERT INTO exams(school_id,name,from_date,to_date,pass_pct,created_by,created_at) VALUES(?,?,?,?,?,?,?)', [$s, mb_substr($name, 0, 150), $from, $to, $pass, me()['id'], date('Y-m-d H:i:s')]);
            foreach ($chosen as $cid) {
                $subs = array_column(q('SELECT DISTINCT subject_id FROM timetable WHERE class_id=? AND school_id=?', [$cid, $s]), 'subject_id') ?: array_column($subjects, 'id');
                foreach ($subs as $sb) run('INSERT INTO exam_papers(exam_id,class_id,subject_id,max_marks) VALUES(?,?,?,?)', [$eid, $cid, $sb, $max]);
            }
            $pdo->commit(); audit('exam_created', $name); flash('Exam created. Now set the date of each paper, then teachers can enter marks.');
            redirect("admin/exams?id=$eid");
        }
        if (!$exam) throw new RuntimeException('Exam not found.');
        if ($a === 'papers') {
            $dates = (array)($_POST['date'] ?? []); $maxes = (array)($_POST['pmax'] ?? []); $bad = [];
            foreach (exam_papers($id) as $p) {
                if (!isset($maxes[$p['id']])) continue;
                $d = trim((string)($dates[$p['id']] ?? '')); $m = (int)$maxes[$p['id']];
                $top = (float)val('SELECT MAX(marks) FROM exam_marks WHERE paper_id=?', [$p['id']]);
                if (($d !== '' && !valid_date($d)) || $m < 1 || $m > 1000 || $m < $top) { $bad[] = cls($p) . ' ' . $p['sname']; continue; }
                run('UPDATE exam_papers SET exam_date=?, max_marks=? WHERE id=?', [$d ?: null, $m, $p['id']]);
            }
            flash($bad ? 'Not saved (check the date; maximum marks cannot be lower than marks already entered): ' . implode(', ', array_slice($bad, 0, 5)) : 'Exam schedule saved.', $bad ? 'warn' : 'ok');
        } elseif ($a === 'add_paper') {
            $cid = (int)post('class_id'); $sb = (int)post('subject_id'); $m = (int)post('max');
            if (!in_array($cid, array_map(fn($c) => (int)$c['id'], $classes), true) || !in_array($sb, array_map(fn($x) => (int)$x['id'], $subjects), true) || $m < 1 || $m > 1000) throw new RuntimeException('Choose class, subject and maximum marks.');
            if (val('SELECT 1 FROM exam_papers WHERE exam_id=? AND class_id=? AND subject_id=?', [$id, $cid, $sb])) throw new RuntimeException('That paper is already in this exam.');
            run('INSERT INTO exam_papers(exam_id,class_id,subject_id,max_marks) VALUES(?,?,?,?)', [$id, $cid, $sb, $m]); flash('Paper added.');
        } elseif ($a === 'del_paper') {
            $pid = (int)post('paper');
            if (val('SELECT 1 FROM exam_papers WHERE id=? AND exam_id=?', [$pid, $id])) { run('DELETE FROM exam_marks WHERE paper_id=?', [$pid]); run('DELETE FROM exam_papers WHERE id=?', [$pid]); audit('exam_paper_removed', (string)$pid); flash('Paper removed.'); }
        } elseif ($a === 'publish') {
            $on = $exam['published'] ? 0 : 1;
            run('UPDATE exams SET published=? WHERE id=?', [$on, $id]); audit($on ? 'result_published' : 'result_unpublished', $exam['name']);
            if ($on) run('INSERT INTO notices(school_id,title,body,created_by) VALUES(?,?,?,?)', [$s, 'Result published: ' . $exam['name'], 'Marks and report cards can now be seen in the student and parent login under Results.', me()['id']]);
            flash($on ? 'Result published. Students and parents can see it now.' : 'Result hidden from students and parents. Teachers can edit marks again.');
        } elseif ($a === 'delete') {
            run('DELETE FROM exam_marks WHERE paper_id IN (SELECT id FROM exam_papers WHERE exam_id=?)', [$id]); run('DELETE FROM exam_papers WHERE exam_id=?', [$id]); run('DELETE FROM exams WHERE id=?', [$id]);
            audit('exam_deleted', $exam['name']); flash('Exam deleted.'); redirect('admin/exams');
        }
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('admin/exams' . ($id ? "?id=$id" : ''));
}

if ($exam) {
    $cf = (int)inp('class'); $papers = exam_papers($id);
    $entered = []; foreach (q('SELECT m.paper_id, COUNT(*) n FROM exam_marks m JOIN exam_papers p ON p.id=m.paper_id JOIN students st ON st.id=m.student_id AND st.active=1 WHERE p.exam_id=? GROUP BY m.paper_id', [$id]) as $r) $entered[$r['paper_id']] = (int)$r['n'];
    $size = []; foreach (q('SELECT class_id, COUNT(*) n FROM students WHERE school_id=? AND active=1 GROUP BY class_id', [$s]) as $r) $size[$r['class_id']] = (int)$r['n'];
    $in = []; foreach ($papers as $p) $in[(int)$p['class_id']] = cls($p);
    if (!isset($in[$cf])) $cf = (int)(array_key_first($in) ?? 0);
    $show = array_values(array_filter($papers, fn($p) => (int)$p['class_id'] === $cf));
    dash_header($exam['name'], '<a class="btn btn-sm" href="/admin/exams">&larr; All exams</a>');
    ?>
<div class="card"><div class="card-head"><h3><?= e(date_range_label($exam['from_date'], $exam['to_date'])) ?> <?= $exam['published'] ? badge('Published', 'green') : badge('Not published', 'amber') ?></h3>
  <span><a class="btn" href="/admin/results?exam=<?= $id ?>">See results</a>
  <form method="post" style="display:inline"><?= csrf_field() ?><button class="btn btn-primary" name="action" value="publish"<?= confirm_attr($exam['published'] ? 'Hide this result from students and parents?' : 'Publish this result? Students and parents will see it.') ?>><?= $exam['published'] ? 'Unpublish result' : 'Publish result' ?></button></form></span></div>
  <p class="muted" style="margin:0">Pass mark <?= (int)$exam['pass_pct'] ?>%. Steps: 1) set paper dates, 2) teachers enter marks, 3) check results, 4) publish.</p></div>
<div class="tabs"><?php foreach ($in as $cid => $label) echo '<a href="?id=' . $id . '&amp;class=' . $cid . '"' . ($cf === $cid ? ' class="active"' : '') . '>Class ' . e($label) . '</a>'; ?></div>
<div class="card"><h3>Papers</h3><form method="post" id="papers" action="/admin/exams?id=<?= $id ?>"><?= csrf_field() ?><input type="hidden" name="action" value="papers"></form>
<div class="table-wrap"><table class="stack"><tr><th>Subject</th><th>Exam date</th><th>Max marks</th><th>Marks entered</th><th></th></tr>
<?php foreach ($show as $p): $e = $entered[$p['id']] ?? 0; $n = $size[$p['class_id']] ?? 0; ?><tr><td class="who-cell"><b><?= e($p['sname']) ?></b></td>
  <td data-label="Exam date"><input type="date" form="papers" name="date[<?= (int)$p['id'] ?>]" value="<?= e($p['exam_date']) ?>" aria-label="Exam date"></td>
  <td data-label="Max marks"><input type="number" class="mark-in" form="papers" name="pmax[<?= (int)$p['id'] ?>]" min="1" max="1000" value="<?= (int)$p['max_marks'] ?>" aria-label="Maximum marks"></td>
  <td data-label="Marks entered"><?= badge("$e of $n", $n && $e >= $n ? 'green' : ($e ? 'amber' : 'gray')) ?></td>
  <td class="right"><a class="btn btn-sm btn-primary" href="/admin/marks?paper=<?= (int)$p['id'] ?>">Marks</a> <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="del_paper"><input type="hidden" name="paper" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-danger"<?= confirm_attr('Remove this paper and its marks?') ?>>Remove</button></form></td></tr>
<?php endforeach; if (!$show) echo empty_row(5, 'No papers.'); ?></table></div>
<?php if ($show): ?><p><button class="btn btn-primary" form="papers">Save schedule</button></p><?php endif; ?></div>
<div class="card"><h3>Add a paper</h3><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="add_paper">
  <div><label>Class</label><select name="class_id"><?php foreach ($classes as $c) echo '<option value="' . (int)$c['id'] . '"' . ($cf === (int)$c['id'] ? ' selected' : '') . '>' . e(class_label($c)) . '</option>'; ?></select></div>
  <div><label>Subject</label><select name="subject_id"><?php foreach ($subjects as $x) echo '<option value="' . (int)$x['id'] . '">' . e($x['name']) . '</option>'; ?></select></div>
  <div><label>Max marks</label><input type="number" name="max" value="100" min="1" max="1000"></div><div style="flex:0"><button class="btn">Add</button></div></form></div>
<form method="post" action="/admin/exams?id=<?= $id ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger" name="action" value="delete"<?= confirm_attr('Delete this exam with ALL its marks? This cannot be undone.') ?>>Delete this exam</button></form>
    <?php dash_footer(); return;
}
[$rows, $pager] = paginate('SELECT ex.*, (SELECT COUNT(DISTINCT p.class_id) FROM exam_papers p WHERE p.exam_id=ex.id) nclass, (SELECT COUNT(*) FROM exam_papers p WHERE p.exam_id=ex.id) npaper FROM exams ex WHERE ex.school_id=? ORDER BY ex.from_date DESC, ex.id DESC', [$s], 10);
dash_header('Exams & results');
?>
<div class="card"><h3>New exam</h3><?php if (!$classes || !$subjects): ?><p class="muted">Add classes and subjects first.</p><?php else: ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create"><div class="form-grid">
  <div class="full"><label>Exam name *</label><input name="name" required maxlength="150" placeholder="e.g. Half Yearly Examination"></div>
  <div><label>Starts *</label><input type="date" name="from" required></div><div><label>Ends</label><input type="date" name="to"></div>
  <div><label>Max marks per paper *</label><input type="number" name="max" value="100" min="1" max="1000" required></div><div><label>Pass percentage *</label><input type="number" name="pass" value="33" min="0" max="100" required></div>
  <div class="full"><label>Classes *</label><div class="checks"><?php foreach ($classes as $c) echo '<label><input type="checkbox" name="classes[]" value="' . (int)$c['id'] . '" checked> ' . e(class_label($c)) . '</label>'; ?></div></div></div>
  <p class="small muted">A paper is made for every subject each class has in its timetable. You can add or remove papers afterwards.</p>
  <p><button class="btn btn-primary">Create exam</button></p></form><?php endif; ?></div>
<div class="card"><h3>Exams</h3><div class="table-wrap"><table class="stack"><tr><th>Exam</th><th>Dates</th><th>Classes</th><th>Papers</th><th>Result</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td class="who-cell"><b><?= e($r['name']) ?></b></td><td data-label="Dates"><?= e(date_range_label($r['from_date'], $r['to_date'])) ?></td><td data-label="Classes"><?= (int)$r['nclass'] ?></td><td data-label="Papers"><?= (int)$r['npaper'] ?></td><td data-label="Result"><?= $r['published'] ? badge('Published', 'green') : badge('Not published', 'amber') ?></td>
  <td class="right"><a class="btn btn-sm btn-primary" href="/admin/exams?id=<?= (int)$r['id'] ?>">Open</a> <a class="btn btn-sm" href="/admin/results?exam=<?= (int)$r['id'] ?>">Results</a></td></tr>
<?php endforeach; if (!$rows) echo empty_row(6, 'No exams yet.'); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
