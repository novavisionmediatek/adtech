<?php
declare(strict_types=1);
/* Homework completion tracking: who has done which homework. One row per (homework, student); no row = not checked yet. */

const HW_STATUS = ['done' => ['Completed', 'green'], 'late' => ['Done late', 'amber'], 'missing' => ['Not done', 'red']];

/** Created on demand (SQLite and MySQL), so existing installations get the table without a manual upgrade. */
function ensure_homework_status(PDO $pdo, bool $mysql): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS homework_status(homework_id INT' . ($mysql ? ' UNSIGNED' : '') . ' NOT NULL, student_id INT' . ($mysql ? ' UNSIGNED' : '') . ' NOT NULL,
        status VARCHAR(10) NOT NULL, remark VARCHAR(255) NULL, checked_by INT' . ($mysql ? ' UNSIGNED' : '') . ' NULL, updated_at DATETIME NOT NULL,
        PRIMARY KEY (homework_id, student_id)' . ($mysql ? ', KEY ix_hws_student (student_id)) ENGINE=InnoDB' : ')'));
}
function hw_badge(?string $status): string { return isset(HW_STATUS[$status]) ? badge(...HW_STATUS[$status]) : badge('Not checked'); }
function delete_homework(array $h): void {
    if ($h['file_path']) @unlink(UPLOAD_DIR . '/' . $h['file_path']);
    delete_submissions('homework_id=?', [$h['id']]);
    run('DELETE FROM homework_status WHERE homework_id=?', [$h['id']]); run('DELETE FROM homework WHERE id=?', [$h['id']]);
}
/** Completion counts for a list of homework ids: [id => ['done'=>n,'late'=>n,'missing'=>n]] (active students only). */
function hw_counts(array $ids): array {
    if (!$ids) return [];
    $out = [];
    foreach (q('SELECT s.homework_id, s.status, COUNT(*) n FROM homework_status s JOIN students st ON st.id=s.student_id AND st.active=1
                WHERE s.homework_id IN (' . implode(',', array_map('intval', $ids)) . ') GROUP BY s.homework_id, s.status') as $r) $out[$r['homework_id']][$r['status']] = (int)$r['n'];
    return $out;
}
/** Table cell for the homework lists: "5 / 8 done", a bar and the Track button. */
function hw_progress_cell(array $h, array $counts, int $students, string $area): string {
    $c = $counts[$h['id']] ?? []; $done = ($c['done'] ?? 0) + ($c['late'] ?? 0); $checked = $done + ($c['missing'] ?? 0);
    return '<div style="min-width:150px"><b>' . $done . ' / ' . $students . '</b> <span class="muted small">done' . ($checked < $students ? ', ' . ($students - $checked) . ' to check' : '') . '</span>' .
        bar($students ? $done * 100 / $students : 0) . '<a class="btn btn-sm btn-primary" style="margin-top:8px" href="/' . $area . '/homework_check?id=' . (int)$h['id'] . '">Track</a></div>';
}

/** The "who has done it" page for one homework. $h must already be authorised by the caller. */
function hw_check_page(array $h, string $area): void {
    $me = me();
    $students = q('SELECT st.id, st.roll_no, u.name FROM students st JOIN users u ON u.id=st.user_id WHERE st.class_id=? AND st.school_id=? AND st.active=1 ORDER BY st.roll_no, u.name', [$h['class_id'], $h['school_id']]);
    $have = []; foreach (q('SELECT * FROM homework_status WHERE homework_id=?', [$h['id']]) as $r) $have[$r['student_id']] = $r;
    $subs = submissions_for([(int)$h['id']]);
    if (is_post()) {
        $in = (array)($_POST['st'] ?? []); $notes = (array)($_POST['remark'] ?? []); $changed = 0;
        $pdo = db(); $pdo->beginTransaction();
        foreach ($students as $s) {
            $id = $s['id']; if (!isset($in[$id])) continue;
            $status = isset(HW_STATUS[$in[$id]]) ? (string)$in[$id] : 'pending';
            $note = mb_substr(trim((string)($notes[$id] ?? '')), 0, 255);
            $old = $have[$id] ?? null;
            if ($status === ($old['status'] ?? 'pending') && $note === (string)($old['remark'] ?? '')) continue; // unchanged: keep original check date
            run('DELETE FROM homework_status WHERE homework_id=? AND student_id=?', [$h['id'], $id]);
            if ($status !== 'pending' || $note !== '') run('INSERT INTO homework_status(homework_id,student_id,status,remark,checked_by,updated_at) VALUES(?,?,?,?,?,?)', [$h['id'], $id, $status, $note, $me['id'], date('Y-m-d H:i:s')]);
            $changed++;
        }
        $pdo->commit();
        if ($changed) audit('homework_checked', $h['title'] . " ($changed students)");
        flash($changed ? "Saved. $changed student record(s) updated." : 'No changes to save.');
        redirect("$area/homework_check?id=" . (int)$h['id']);
    }
    $n = ['done' => 0, 'late' => 0, 'missing' => 0];
    foreach ($students as $s) { $st = $have[$s['id']]['status'] ?? ''; if (isset($n[$st])) $n[$st]++; }
    dash_header('Track homework', '<a class="btn btn-sm" href="/' . $area . '/homework">&larr; All homework</a>');
    echo '<div class="card"><h3>' . e($h['title']) . '</h3><p class="muted">Class ' . e(cls($h)) . ' &middot; ' . e($h['sname']) . ' &middot; posted ' . e(fmt_date($h['created_at'])) .
        ($h['due_date'] ? ' &middot; due <b>' . e(fmt_date($h['due_date'])) . '</b>' : '') . '</p>' . ($h['description'] ? '<p>' . nl2br(e((string)$h['description'])) . '</p>' : '') . '</div>';
    echo '<div class="stats">' . kpi('Completed', $n['done'], '', 'green') . kpi('Done late', $n['late']) . kpi('Not done', $n['missing'], '', 'red') . kpi('Not checked', count($students) - array_sum($n)) . '</div>';
    echo '<div class="card"><form method="post">' . csrf_field() . '<p><button type="button" class="btn btn-sm" data-markall="done">All completed</button> <button type="button" class="btn btn-sm" data-markall="missing">All not done</button> <button type="button" class="btn btn-sm" data-markall="pending">Clear all</button></p>' .
        '<div class="table-wrap"><table><tr><th>Roll</th><th>Student</th><th>Student\'s work</th><th>Status</th><th>Teacher\'s note (student and parent can see it)</th><th>Checked on</th></tr>';
    foreach ($students as $s) {
        $r = $have[$s['id']] ?? null; $cur = $r['status'] ?? 'pending';
        echo '<tr><td>' . (int)$s['roll_no'] . '</td><td><b>' . e($s['name']) . '</b></td><td style="min-width:170px">' . submission_html($subs[$h['id'] . '-' . $s['id']] ?? null, $h['due_date']) . '</td><td><div class="seg">';
        foreach (['done' => ['Done', 'sp'], 'late' => ['Late', 'sl'], 'missing' => ['Not done', 'sa'], 'pending' => ['&ndash;', 'sn']] as $v => [$label, $class])
            echo '<label title="' . ($v === 'pending' ? 'Not checked' : e(HW_STATUS[$v][0])) . '"><input type="radio" name="st[' . (int)$s['id'] . ']" value="' . $v . '"' . ($cur === $v ? ' checked' : '') . '><span class="' . $class . '">' . $label . '</span></label>';
        echo '</div></td><td><input name="remark[' . (int)$s['id'] . ']" maxlength="255" value="' . e($r['remark'] ?? '') . '" placeholder="optional"></td><td class="muted small">' . ($r ? e(fmt_date($r['updated_at'], 'd M, h:i A')) : '-') . '</td></tr>';
    }
    if (!$students) echo empty_row(6, 'No students in this class.');
    echo '</table></div>' . ($students ? '<p><button class="btn btn-primary">Save homework status</button></p>' : '') . '</form></div>';
    dash_footer();
}

/** Whole-class tracking sheet (students x homework). $facultyId limits it to one teacher's homework. Handles the Excel download too. */
function hw_report_page(int $school, string $area, ?int $facultyId): void {
    $w = 'h.school_id=?'; $p = [$school];
    if ($facultyId) { $w .= ' AND h.faculty_id=?'; $p[] = $facultyId; }
    $classes = q("SELECT DISTINCT c.id, c.name, c.section FROM homework h JOIN classes c ON c.id=h.class_id WHERE $w ORDER BY (c.name+0), c.name, c.section", $p);
    $cid = (int)inp('class', (string)($classes[0]['id'] ?? 0)); $class = null;
    foreach ($classes as $c) if ((int)$c['id'] === $cid) $class = $c;
    $hws = $students = $map = [];
    if ($class) {
        $hws = q("SELECT h.*, sub.name sname, u.name fname FROM homework h JOIN subjects sub ON sub.id=h.subject_id LEFT JOIN users u ON u.id=h.faculty_id WHERE $w AND h.class_id=? ORDER BY h.created_at, h.id", array_merge($p, [$cid]));
        $students = q('SELECT st.id, st.roll_no, st.admission_no, u.name FROM students st JOIN users u ON u.id=st.user_id WHERE st.class_id=? AND st.school_id=? AND st.active=1 ORDER BY st.roll_no, u.name', [$cid, $school]);
        if ($hws) foreach (q('SELECT s.*, u.name checker FROM homework_status s LEFT JOIN users u ON u.id=s.checked_by WHERE s.homework_id IN (' . implode(',', array_map(fn($h) => (int)$h['id'], $hws)) . ')') as $r) $map[$r['student_id']][$r['homework_id']] = $r;
    }
    $totals = function (int $sid) use ($hws, $map) {
        $t = ['done' => 0, 'late' => 0, 'missing' => 0, 'pending' => 0];
        foreach ($hws as $h) { $st = $map[$sid][$h['id']]['status'] ?? 'pending'; $t[isset($t[$st]) ? $st : 'pending']++; }
        $checked = $t['done'] + $t['late'] + $t['missing'];
        $t['pct'] = $checked ? pct((float)($t['done'] + $t['late']), (float)$checked) : null; // share of checked homework that was done
        return $t;
    };
    if (inp('export') && $class && $hws) {
        require_once APP . '/xlsx.php';
        $name = (string)val('SELECT name FROM schools WHERE id=?', [$school]);
        $head = fn(array $cols) => array_map(fn($c) => [$c, 1], $cols);
        $code = ['done' => ['Done', 3], 'late' => ['Late', 5], 'missing' => ['Not done', 4]];
        $rows = [[[$name . ' - Class ' . class_label($class) . ' homework tracking', 2]], [['Generated ' . date('d M Y, h:i A') . ' | Completion % = done (including late) out of checked homework', 6]], []];
        $rows[] = $head(array_merge(['Roll', 'Admission No', 'Student'], array_map(fn($h) => $h['sname'] . ': ' . $h['title'] . ' (' . date('d M', (int)strtotime((string)$h['created_at'])) . ')', $hws), ['Done', 'Late', 'Not done', 'Not checked', 'Completion %']));
        $detail = [$head(['Posted', 'Due', 'Subject', 'Homework', 'Instructions', 'Teacher', 'Roll', 'Admission No', 'Student', 'Status', 'Note', 'Checked on', 'Checked by'])];
        foreach ($students as $s) {
            $line = [[(int)$s['roll_no'], 7], $s['admission_no'], $s['name']];
            foreach ($hws as $h) { $r = $map[$s['id']][$h['id']] ?? null; $line[] = $code[$r['status'] ?? ''] ?? ['-', 7]; }
            $t = $totals((int)$s['id']);
            array_push($line, [$t['done'], 7], [$t['late'], 7], [$t['missing'], 7], [$t['pending'], 7], $t['pct'] === null ? ['-', 7] : [(float)$t['pct'], $t['pct'] >= 75 ? 3 : 4]);
            $rows[] = $line;
        }
        foreach ($hws as $h) foreach ($students as $s) {
            $r = $map[$s['id']][$h['id']] ?? null;
            $detail[] = [date('d-m-Y', (int)strtotime((string)$h['created_at'])), $h['due_date'] ? date('d-m-Y', (int)strtotime((string)$h['due_date'])) : '', $h['sname'], $h['title'], (string)$h['description'], (string)$h['fname'],
                [(int)$s['roll_no'], 7], $s['admission_no'], $s['name'], $code[$r['status'] ?? ''] ?? ['Not checked', 7], (string)($r['remark'] ?? ''), $r ? date('d-m-Y H:i', (int)strtotime((string)$r['updated_at'])) : '', (string)($r['checker'] ?? '')];
        }
        audit('homework_export', class_label($class));
        xlsx_download('homework_' . class_label($class) . '_' . date('Y-m-d') . '.xlsx', [
            ['name' => 'Summary', 'rows' => $rows, 'freeze' => [4, 3], 'widths' => array_merge([7, 15, 26], array_fill(0, count($hws), 24), [8, 8, 10, 13, 14])],
            ['name' => 'All details', 'rows' => $detail, 'freeze' => [1, 0], 'widths' => [12, 12, 16, 30, 40, 20, 7, 15, 24, 12, 28, 17, 20]],
        ]);
    }
    dash_header('Homework report', '<a class="btn btn-sm" href="/' . $area . '/homework">&larr; All homework</a>');
    if (!$classes) { echo '<div class="card muted center">No homework has been posted yet.</div>'; dash_footer(); return; }
    echo '<div class="card"><form method="get" class="inline-form"><div><label>Class</label><select name="class" data-autosubmit>';
    foreach ($classes as $c) echo '<option value="' . (int)$c['id'] . '"' . ($cid === (int)$c['id'] ? ' selected' : '') . '>' . e(class_label($c)) . '</option>';
    echo '</select></div>' . ($class && $hws ? '<div style="flex:0"><a class="btn btn-primary" href="?class=' . $cid . '&amp;export=1">&#8681; Download all (Excel)</a></div>' : '') . '</form></div>';
    if ($class) {
        $mark = ['done' => badge('Done', 'green'), 'late' => badge('Late', 'amber'), 'missing' => badge('Not done', 'red')];
        echo '<div class="card"><h3>Class ' . e(class_label($class)) . ' &middot; ' . count($hws) . ' homework</h3><div class="table-wrap"><table><tr><th>Roll</th><th>Student</th>';
        foreach ($hws as $h) echo '<th title="' . e($h['title']) . '"><a href="/' . $area . '/homework_check?id=' . (int)$h['id'] . '">' . e(mb_strimwidth($h['title'], 0, 22, '...')) . '</a><br><span class="small">' . e($h['sname']) . ' &middot; ' . e(fmt_date($h['created_at'], 'd M')) . '</span></th>';
        echo '<th>Done</th><th>Not done</th><th>Not checked</th><th>Completion</th></tr>';
        foreach ($students as $s) {
            $t = $totals((int)$s['id']);
            echo '<tr><td>' . (int)$s['roll_no'] . '</td><td style="white-space:nowrap"><b>' . e($s['name']) . '</b></td>';
            foreach ($hws as $h) echo '<td>' . ($mark[$map[$s['id']][$h['id']]['status'] ?? ''] ?? '<span class="muted">&ndash;</span>') . '</td>';
            echo '<td>' . ($t['done'] + $t['late']) . '</td><td>' . $t['missing'] . '</td><td>' . $t['pending'] . '</td><td>' . ($t['pct'] === null ? badge('No data') : pct_badge((float)$t['pct'])) . '</td></tr>';
        }
        if (!$students) echo empty_row(6 + count($hws), 'No students in this class.');
        echo '</table></div></div>';
    }
    dash_footer();
}
