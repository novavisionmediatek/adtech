<?php
declare(strict_types=1);
/* Exams, marks and report cards. An exam has one "paper" per class and subject; marks are stored per paper and student. */

/** CBSE-style grade from a percentage. */
function grade_of(float $pct): string {
    foreach (['A1' => 91, 'A2' => 81, 'B1' => 71, 'B2' => 61, 'C1' => 51, 'C2' => 41, 'D' => 33] as $g => $min) if ($pct >= $min) return $g;
    return 'E';
}
function mark_text($v): string { return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'); }
function result_badge(string $r): string { return badge($r, ['Pass' => 'green', 'Fail' => 'red'][$r] ?? 'amber'); }

function exam_papers(int $examId, int $classId = 0): array {
    return q('SELECT p.*, sub.name sname, c.name cname, c.section FROM exam_papers p JOIN subjects sub ON sub.id=p.subject_id JOIN classes c ON c.id=p.class_id WHERE p.exam_id=?' .
        ($classId ? ' AND p.class_id=' . $classId : '') . ' ORDER BY (c.name+0), c.name, c.section, p.exam_date, sub.name', [$examId]);
}
/**
 * Result of one student from the papers of their class and the marks found: [total, max, pct|null, grade, result].
 * Result: Incomplete while any paper has no entry; Fail if absent or below the pass mark in any paper; otherwise Pass.
 */
function exam_score(array $papers, array $marks, int $passPct): array {
    $total = 0.0; $max = 0; $missing = false; $failed = false;
    foreach ($papers as $p) {
        $m = $marks[$p['id']] ?? null; $max += (int)$p['max_marks'];
        if (!$m) { $missing = true; continue; }
        if ($m['absent']) { $failed = true; continue; }
        $total += (float)$m['marks']; if ((float)$m['marks'] * 100 < $passPct * (int)$p['max_marks']) $failed = true;
    }
    $pct = $max && !$missing ? pct($total, (float)$max) : null;
    return ['total' => $total, 'max' => $max, 'pct' => $pct, 'grade' => $pct === null ? '-' : grade_of($pct), 'result' => !$papers || $missing ? 'Incomplete' : ($failed ? 'Fail' : 'Pass')];
}
/** [papers, rows] for one class in one exam; each row has roll_no, name, admission_no, id, marks[paper_id] and the score. */
function exam_class_result(array $exam, int $classId): array {
    $papers = exam_papers((int)$exam['id'], $classId);
    $students = q('SELECT st.id, st.roll_no, st.admission_no, u.name FROM students st JOIN users u ON u.id=st.user_id WHERE st.class_id=? AND st.school_id=? AND st.active=1 ORDER BY st.roll_no, u.name', [$classId, $exam['school_id']]);
    $marks = [];
    if ($papers) foreach (q('SELECT * FROM exam_marks WHERE paper_id IN (' . implode(',', array_map(fn($p) => (int)$p['id'], $papers)) . ')') as $m) $marks[$m['student_id']][$m['paper_id']] = $m;
    foreach ($students as &$s) { $s['marks'] = $marks[$s['id']] ?? []; $s += exam_score($papers, $s['marks'], (int)$exam['pass_pct']); }
    unset($s);
    return [$papers, $students];
}
/** The class in which a student sat an exam (from their marks, so old report cards survive a promotion). */
function exam_class_of(int $examId, int $studentId): int {
    return (int)val('SELECT p.class_id FROM exam_marks m JOIN exam_papers p ON p.id=m.paper_id WHERE p.exam_id=? AND m.student_id=? LIMIT 1', [$examId, $studentId]);
}

/** Marks entry for one paper. $paper must already be authorised by the caller. */
function marks_page(array $paper, string $area, bool $canEdit): void {
    $back = $area === 'admin' ? 'admin/exams?id=' . (int)$paper['exam_id'] : 'faculty/marks';
    $students = q('SELECT st.id, st.roll_no, u.name FROM students st JOIN users u ON u.id=st.user_id WHERE st.class_id=? AND st.school_id=? AND st.active=1 ORDER BY st.roll_no, u.name', [$paper['class_id'], $paper['school_id']]);
    $have = []; foreach (q('SELECT * FROM exam_marks WHERE paper_id=?', [$paper['id']]) as $m) $have[$m['student_id']] = $m;
    $max = (int)$paper['max_marks'];
    if (is_post()) {
        if (!$canEdit) { flash('These marks are locked because the result is published. Ask the school admin.', 'err'); redirect("$area/marks?paper=" . (int)$paper['id']); }
        $in = (array)($_POST['m'] ?? []); $abs = (array)($_POST['ab'] ?? []); $bad = []; $saved = 0;
        $pdo = db(); $pdo->beginTransaction();
        foreach ($students as $s) {
            $id = $s['id']; if (!array_key_exists($id, $in)) continue;
            $raw = is_string($in[$id]) ? trim($in[$id]) : ''; $absent = !empty($abs[$id]);
            if (!$absent && $raw !== '' && (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > $max)) { $bad[] = $s['name']; continue; }
            $value = $absent || $raw === '' ? null : round((float)$raw, 2);
            $old = $have[$id] ?? null;
            if ($old && (bool)$old['absent'] === $absent && ($old['marks'] === null ? $value === null : $value !== null && abs((float)$old['marks'] - $value) < 0.001)) continue;
            if (!$old && !$absent && $value === null) continue;
            run('DELETE FROM exam_marks WHERE paper_id=? AND student_id=?', [$paper['id'], $id]);
            if ($absent || $value !== null) run('INSERT INTO exam_marks(paper_id,student_id,marks,absent,updated_by,updated_at) VALUES(?,?,?,?,?,?)', [$paper['id'], $id, $value, (int)$absent, me()['id'], date('Y-m-d H:i:s')]);
            $saved++;
        }
        $pdo->commit();
        if ($saved) audit('marks_saved', $paper['ename'] . ' ' . cls($paper) . ' ' . $paper['sname'] . " ($saved)");
        flash(($saved ? "Saved marks of $saved student(s)." : 'No changes to save.') . ($bad ? " Not saved (marks must be 0 to $max): " . implode(', ', array_slice($bad, 0, 5)) : ''), $bad ? 'warn' : 'ok');
        redirect("$area/marks?paper=" . (int)$paper['id']);
    }
    dash_header('Enter marks', '<a class="btn btn-sm" href="/' . $back . '">&larr; Back</a>');
    echo '<div class="card"><h3>' . e($paper['ename']) . ' &middot; Class ' . e(cls($paper)) . ' &middot; ' . e($paper['sname']) . '</h3><p class="muted">Maximum marks: <b>' . $max . '</b> &middot; pass mark: ' . (int)$paper['pass_pct'] . '%' . ($paper['exam_date'] ? ' &middot; exam on ' . e(fmt_date($paper['exam_date'])) : '') . ' &middot; entered for ' . count($have) . ' of ' . count($students) . '</p>' .
        (!$canEdit ? '<p>' . badge('Locked - result published', 'amber') . '</p>' : '') . '</div>';
    echo '<div class="card"><form method="post">' . csrf_field() . '<div class="table-wrap"><table><tr><th>Roll</th><th>Student</th><th>Marks (out of ' . $max . ')</th><th>Absent</th></tr>';
    foreach ($students as $s) {
        $m = $have[$s['id']] ?? null; $off = $canEdit ? '' : ' disabled';
        echo '<tr><td>' . (int)$s['roll_no'] . '</td><td><b>' . e($s['name']) . '</b></td><td><input type="number" class="mark-in" name="m[' . (int)$s['id'] . ']" min="0" max="' . $max . '" step="0.25" value="' . ($m && !$m['absent'] ? e(mark_text($m['marks'])) : '') . '" aria-label="Marks of ' . e($s['name']) . '"' . $off . '></td>' .
            '<td><input type="checkbox" name="ab[' . (int)$s['id'] . ']" value="1"' . ($m && $m['absent'] ? ' checked' : '') . ' aria-label="Absent"' . $off . '></td></tr>';
    }
    if (!$students) echo empty_row(4, 'No students in this class.');
    echo '</table></div>' . ($students && $canEdit ? '<p><button class="btn btn-primary">Save marks</button> <span class="muted small">Leave a box empty if the marks are not ready yet.</span></p>' : '') . '</form></div>';
    dash_footer();
}
/** A paper with its exam, class and subject, limited to one school. */
function paper_row(int $paperId, int $school): ?array {
    return row('SELECT p.*, ex.name ename, ex.pass_pct, ex.published, ex.school_id, c.name cname, c.section, sub.name sname FROM exam_papers p JOIN exams ex ON ex.id=p.exam_id JOIN classes c ON c.id=p.class_id JOIN subjects sub ON sub.id=p.subject_id WHERE p.id=? AND ex.school_id=?', [$paperId, $school]);
}

/** Published results and the coming exam dates of one student (student and parent screens). $st = students row. */
function view_results(array $st): string {
    $o = '';
    $coming = q('SELECT ex.name ename, p.exam_date, p.max_marks, sub.name sname FROM exam_papers p JOIN exams ex ON ex.id=p.exam_id JOIN subjects sub ON sub.id=p.subject_id
        WHERE ex.school_id=? AND p.class_id=? AND p.exam_date>=? AND ex.published=0 ORDER BY p.exam_date, sub.name LIMIT 30', [$st['school_id'], $st['class_id'], date('Y-m-d')]);
    if ($coming) {
        $o .= '<div class="card"><h3>Coming exams</h3><div class="table-wrap"><table><tr><th>Date</th><th>Exam</th><th>Subject</th><th>Max marks</th></tr>';
        foreach ($coming as $c) $o .= '<tr><td><b>' . e(fmt_date($c['exam_date'], 'D, d M Y')) . '</b></td><td>' . e($c['ename']) . '</td><td>' . e($c['sname']) . '</td><td>' . (int)$c['max_marks'] . '</td></tr>';
        $o .= '</table></div></div>';
    }
    $exams = q('SELECT DISTINCT ex.* FROM exams ex JOIN exam_papers p ON p.exam_id=ex.id JOIN exam_marks m ON m.paper_id=p.id WHERE m.student_id=? AND ex.published=1 ORDER BY ex.from_date DESC, ex.id DESC', [$st['id']]);
    foreach ($exams as $ex) {
        $papers = exam_papers((int)$ex['id'], exam_class_of((int)$ex['id'], (int)$st['id']));
        $marks = []; foreach (q('SELECT m.* FROM exam_marks m JOIN exam_papers p ON p.id=m.paper_id WHERE p.exam_id=? AND m.student_id=?', [$ex['id'], $st['id']]) as $m) $marks[$m['paper_id']] = $m;
        $sc = exam_score($papers, $marks, (int)$ex['pass_pct']);
        $o .= '<div class="card"><div class="card-head"><h3>' . e($ex['name']) . ' ' . result_badge($sc['result']) . '</h3><a class="btn btn-sm btn-primary" href="/print/report?exam=' . (int)$ex['id'] . '&amp;student=' . (int)$st['id'] . '">Print report card</a></div>' .
            '<div class="table-wrap"><table><tr><th>Subject</th><th>Marks</th><th>Out of</th><th>Grade</th></tr>';
        foreach ($papers as $p) { $m = $marks[$p['id']] ?? null; $o .= '<tr><td>' . e($p['sname']) . '</td><td><b>' . (!$m ? '-' : ($m['absent'] ? 'Absent' : e(mark_text($m['marks'])))) . '</b></td><td>' . (int)$p['max_marks'] . '</td><td>' . ($m && !$m['absent'] ? grade_of(pct((float)$m['marks'], (float)$p['max_marks'])) : '-') . '</td></tr>'; }
        $o .= '<tr><td><b>Total</b></td><td><b>' . e(mark_text($sc['total'])) . '</b></td><td><b>' . $sc['max'] . '</b></td><td><b>' . ($sc['pct'] === null ? '-' : e($sc['pct']) . '% &middot; ' . $sc['grade']) . '</b></td></tr></table></div></div>';
    }
    return $o ?: '<div class="card center muted">No results have been published yet.</div>';
}
