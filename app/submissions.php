<?php
declare(strict_types=1);
/* Students hand in homework online: a note and/or files. One submission per student and homework; sending again replaces it. */

const SUB_TYPES = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'txt'];
const SUB_MAX_BYTES = 10485760; // 10 MB each
const SUB_MAX_FILES = 5;

/** $st = students row of the person submitting. */
function submit_homework(array $st, int $homeworkId, string $note): void {
    $h = row('SELECT * FROM homework WHERE id=? AND class_id=? AND school_id=?', [$homeworkId, $st['class_id'], $st['school_id']]);
    if (!$h) throw new RuntimeException('This homework is not for your class.');
    if (in_array(val('SELECT status FROM homework_status WHERE homework_id=? AND student_id=?', [$h['id'], $st['id']]), ['done', 'late'], true)) throw new RuntimeException('Your teacher has already checked this homework.');
    $chosen = count(array_filter((array)($_FILES['files']['name'] ?? []), fn($n) => $n !== ''));
    if ($chosen > SUB_MAX_FILES) throw new RuntimeException('Attach at most ' . SUB_MAX_FILES . ' files.');
    $note = mb_substr(trim($note), 0, 1000);
    $files = save_uploads('files', 'submissions', SUB_TYPES, SUB_MAX_BYTES);
    if (!$files && $note === '') throw new RuntimeException('Attach a file or write your answer.');
    delete_submissions('homework_id=? AND student_id=?', [$h['id'], $st['id']]);
    $sid = ins('INSERT INTO homework_submissions(homework_id,student_id,note,submitted_at) VALUES(?,?,?,?)', [$h['id'], $st['id'], $note, date('Y-m-d H:i:s')]);
    foreach ($files as $f) run('INSERT INTO submission_files(submission_id,file_name,file_path) VALUES(?,?,?)', [$sid, $f['name'], $f['path']]);
    audit('homework_submitted', $h['title']);
}
function delete_submissions(string $where, array $params): void {
    foreach (q("SELECT id FROM homework_submissions WHERE $where", $params) as $s) {
        foreach (q('SELECT file_path FROM submission_files WHERE submission_id=?', [$s['id']]) as $f) @unlink(UPLOAD_DIR . '/' . $f['file_path']);
        run('DELETE FROM submission_files WHERE submission_id=?', [$s['id']]); run('DELETE FROM homework_submissions WHERE id=?', [$s['id']]);
    }
}
/** Submissions with their files, keyed by "homeworkId-studentId". */
function submissions_for(array $homeworkIds, int $studentId = 0): array {
    if (!$homeworkIds) return [];
    $rows = q('SELECT * FROM homework_submissions WHERE homework_id IN (' . implode(',', array_map('intval', $homeworkIds)) . ')' . ($studentId ? ' AND student_id=' . $studentId : ''));
    if (!$rows) return [];
    $files = []; foreach (q('SELECT * FROM submission_files WHERE submission_id IN (' . implode(',', array_map(fn($r) => (int)$r['id'], $rows)) . ') ORDER BY id') as $f) $files[$f['submission_id']][] = $f;
    $out = []; foreach ($rows as $r) { $r['files'] = $files[$r['id']] ?? []; $out[$r['homework_id'] . '-' . $r['student_id']] = $r; }
    return $out;
}
/** What was handed in: date (flagged when after the due date), note and file links. */
function submission_html(?array $sub, ?string $due): string {
    if (!$sub) return '<span class="muted small">Not submitted online</span>';
    $o = '<span class="small"><b>Submitted</b> ' . e(fmt_date($sub['submitted_at'], 'd M, h:i A')) . '</span>' . ($due && substr((string)$sub['submitted_at'], 0, 10) > $due ? ' ' . badge('After due date', 'amber') : '');
    if ((string)$sub['note'] !== '') $o .= '<div class="small sub-note">' . nl2br(e($sub['note'])) . '</div>';
    foreach ($sub['files'] as $f) $o .= '<div class="small"><a href="/file/submission/' . (int)$f['id'] . '">&#8681; ' . e($f['file_name']) . '</a></div>';
    return $o;
}
/** Upload form shown to the student under a homework item. */
function submission_form(int $homeworkId, bool $again): string {
    return '<details class="sub-form"><summary class="btn btn-sm ' . ($again ? '' : 'btn-primary') . '">' . ($again ? 'Send again (replaces)' : 'Submit my work') . '</summary><form method="post" action="/student/homework" enctype="multipart/form-data">' . csrf_field() .
        '<input type="hidden" name="homework" value="' . $homeworkId . '"><p><label>Files (up to ' . SUB_MAX_FILES . ', ' . round(SUB_MAX_BYTES / 1048576) . ' MB each)</label><input type="file" name="files[]" multiple accept=".' . implode(',.', SUB_TYPES) . '"></p>' .
        '<p><label>Note or answer <span class="muted">(optional if you attach a file)</span></label><textarea name="note" maxlength="1000"></textarea></p><button class="btn btn-primary">Send to teacher</button></form></details>';
}
