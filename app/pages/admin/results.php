<?php
$s = sid();
$exam = row('SELECT * FROM exams WHERE id=? AND school_id=?', [(int)inp('exam'), $s]);
if (!$exam) render_error(404, 'Exam not found', 'This exam does not exist.');
$classes = q('SELECT DISTINCT c.id, c.name, c.section FROM exam_papers p JOIN classes c ON c.id=p.class_id WHERE p.exam_id=? ORDER BY (c.name+0), c.name, c.section', [$exam['id']]);
$cid = (int)inp('class', (string)($classes[0]['id'] ?? 0)); $class = null; foreach ($classes as $c) if ((int)$c['id'] === $cid) $class = $c;
[$papers, $rows] = $class ? exam_class_result($exam, $cid) : [[], []];
if (inp('export') && $class) {
    require_once APP . '/xlsx.php';
    $head = fn(array $cols) => array_map(fn($c) => [$c, 1], $cols);
    $sheet = [[[(string)val('SELECT name FROM schools WHERE id=?', [$s]) . ' - ' . $exam['name'] . ' - Class ' . class_label($class), 2]], [['Generated ' . date('d M Y, h:i A') . ' | Pass mark ' . $exam['pass_pct'] . '%', 6]], []];
    $sheet[] = $head(array_merge(['Roll', 'Admission No', 'Student'], array_map(fn($p) => $p['sname'] . ' (' . $p['max_marks'] . ')', $papers), ['Total', 'Out of', '%', 'Grade', 'Result']));
    foreach ($rows as $r) {
        $line = [[(int)$r['roll_no'], 7], $r['admission_no'], $r['name']];
        foreach ($papers as $p) { $m = $r['marks'][$p['id']] ?? null; $line[] = !$m ? ['-', 7] : ($m['absent'] ? ['AB', 4] : [(float)$m['marks'], 7]); }
        array_push($line, [(float)$r['total'], 7], [$r['max'], 7], $r['pct'] === null ? ['-', 7] : [(float)$r['pct'], 7], [$r['grade'], 7], [$r['result'], ['Pass' => 3, 'Fail' => 4][$r['result']] ?? 5]);
        $sheet[] = $line;
    }
    audit('result_export', $exam['name'] . ' ' . class_label($class));
    xlsx_download('result_' . class_label($class) . '_' . date('Y-m-d') . '.xlsx', [['name' => 'Result', 'rows' => $sheet, 'freeze' => [4, 3], 'widths' => array_merge([7, 15, 26], array_fill(0, count($papers), 16), [9, 9, 8, 8, 11])]]);
}
$n = array_count_values(array_column($rows, 'result'));
$scored = array_filter(array_column($rows, 'pct'), fn($p) => $p !== null);
dash_header('Results: ' . $exam['name'], '<a class="btn btn-sm" href="/admin/exams?id=' . (int)$exam['id'] . '">&larr; Exam</a>');
?>
<div class="card"><form method="get" class="inline-form"><input type="hidden" name="exam" value="<?= (int)$exam['id'] ?>"><div><label>Class</label><select name="class" data-autosubmit><?php foreach ($classes as $c) echo '<option value="' . (int)$c['id'] . '"' . ($cid === (int)$c['id'] ? ' selected' : '') . '>' . e(class_label($c)) . '</option>'; ?></select></div>
  <?php if ($class): ?><div class="push"><a class="btn btn-primary" href="?exam=<?= (int)$exam['id'] ?>&amp;class=<?= $cid ?>&amp;export=1">&#8681; Download Excel</a></div><?php endif; ?></form></div>
<div class="stats"><?= kpi('Students', count($rows)) ?><?= kpi('Passed', $n['Pass'] ?? 0, '', 'green') ?><?= kpi('Failed', $n['Fail'] ?? 0, '', 'red') ?><?= kpi('Incomplete', $n['Incomplete'] ?? 0, 'marks missing') ?><?= kpi('Class average', $scored ? round(array_sum($scored) / count($scored), 1) . '%' : '-') ?></div>
<div class="card"><div class="table-wrap"><table><tr><th>Roll</th><th>Student</th><?php foreach ($papers as $p) echo '<th>' . e($p['sname']) . '<br><span class="small">of ' . (int)$p['max_marks'] . '</span></th>'; ?><th>Total</th><th>%</th><th>Grade</th><th>Result</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= (int)$r['roll_no'] ?></td><td style="white-space:nowrap"><b><?= e($r['name']) ?></b></td>
  <?php foreach ($papers as $p) { $m = $r['marks'][$p['id']] ?? null; echo '<td>' . (!$m ? '<span class="muted">&ndash;</span>' : ($m['absent'] ? badge('AB', 'red') : e(mark_text($m['marks'])))) . '</td>'; } ?>
  <td><b><?= e(mark_text($r['total'])) ?></b> / <?= (int)$r['max'] ?></td><td><?= $r['pct'] === null ? '-' : e($r['pct']) . '%' ?></td><td><b><?= e($r['grade']) ?></b></td><td><?= result_badge($r['result']) ?></td>
  <td><a class="btn btn-sm" href="/print/report?exam=<?= (int)$exam['id'] ?>&amp;student=<?= (int)$r['id'] ?>">Report card</a></td></tr>
<?php endforeach; if (!$rows) echo empty_row(7 + count($papers), 'No students.'); ?></table></div></div>
<?php dash_footer(); ?>
