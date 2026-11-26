<?php
declare(strict_types=1);
/* Online leave requests: teachers (for themselves) and parents (for a child) apply, the school admin decides. */

const STUDENT_LEAVE_TYPES = ['Sick leave', 'Family function', 'Travel', 'Other'];
const REQUEST_STATUS = ['pending' => ['Waiting', 'amber'], 'approved' => ['Approved', 'green'], 'rejected' => ['Not approved', 'red'], 'cancelled' => ['Cancelled', 'gray']];

/** $kind 'faculty' (user applies for self) or 'student' ($student = students row with name, applied by the parent). */
function request_leave(array $user, string $kind, ?array $student, array $d): void {
    $from = (string)($d['from'] ?? ''); $to = (string)($d['to'] ?? '') ?: $from; $reason = trim((string)($d['reason'] ?? ''));
    $types = $kind === 'faculty' ? LEAVE_TYPES : STUDENT_LEAVE_TYPES;
    $type = in_array($d['type'] ?? '', $types, true) ? $d['type'] : 'Other';
    if (!valid_date($from) || !valid_date($to)) throw new RuntimeException('Choose valid dates.');
    if ($to < $from) throw new RuntimeException('"To" date cannot be before the "From" date.');
    if ($from < date('Y-m-d', strtotime('-30 day'))) throw new RuntimeException('Leave older than 30 days cannot be applied online.');
    if ((strtotime($to) - strtotime($from)) / 86400 > 60) throw new RuntimeException('One request can cover up to 60 days.');
    if ($reason === '') throw new RuntimeException('Please write the reason.');
    $who = $student ? ' AND student_id=' . (int)$student['id'] : ' AND user_id=' . (int)$user['id'] . " AND kind='faculty'";
    if (val("SELECT 1 FROM leave_requests WHERE status IN ('pending','approved') AND from_date<=? AND to_date>=?$who", [$to, $from])) throw new RuntimeException('There is already a request for some of these dates.');
    run('INSERT INTO leave_requests(school_id,user_id,kind,student_id,from_date,to_date,type,reason,created_at) VALUES(?,?,?,?,?,?,?,?,?)',
        [$user['school_id'], $user['id'], $kind, $student['id'] ?? null, $from, $to, $type, mb_substr($reason, 0, 500), date('Y-m-d H:i:s')]);
    audit('leave_requested', ($student['name'] ?? $user['name']) . " $from..$to");
    foreach (q("SELECT name, email FROM users WHERE school_id=? AND role='school_admin' AND active=1", [$user['school_id']]) as $a)
        if ($a['email']) send_mail($a['email'], $a['name'], 'New leave request: ' . ($student['name'] ?? $user['name']), mail_wrap('New leave request', '<p><b>' . e($student['name'] ?? $user['name']) . '</b> (' . ($student ? 'student' : 'teacher') . ') applied for leave: ' . e(date_range_label($from, $to)) . '.</p><p>Reason: ' . e($reason) . '</p><p>Open Leave requests in the portal to decide.</p>'), (int)$user['school_id']);
}
function cancel_request(int $id, int $userId): void { if (run("UPDATE leave_requests SET status='cancelled' WHERE id=? AND user_id=? AND status='pending'", [$id, $userId])) { audit('leave_request_cancelled', (string)$id); flash('Request cancelled.'); } }

/** Approve or reject a waiting request of this school. An approved teacher leave is entered in Holidays & leave. */
function decide_leave(int $id, int $school, bool $approve, string $note): array {
    $r = row('SELECT r.*, u.name uname, u.email, su.name sname FROM leave_requests r JOIN users u ON u.id=r.user_id LEFT JOIN students st ON st.id=r.student_id LEFT JOIN users su ON su.id=st.user_id WHERE r.id=? AND r.school_id=?', [$id, $school]);
    if (!$r || $r['status'] !== 'pending') throw new RuntimeException('This request was already decided or cancelled.');
    $leaveId = null; $pdo = db(); $pdo->beginTransaction();
    try {
        if ($approve && $r['kind'] === 'faculty') {
            if (val('SELECT 1 FROM faculty_leave WHERE faculty_id=? AND from_date<=? AND to_date>=?', [$r['user_id'], $r['to_date'], $r['from_date']])) throw new RuntimeException($r['uname'] . ' already has leave on some of these dates (see Holidays & leave).');
            $leaveId = ins('INSERT INTO faculty_leave(school_id,faculty_id,from_date,to_date,type,note,created_by,created_at) VALUES(?,?,?,?,?,?,?,?)', [$school, $r['user_id'], $r['from_date'], $r['to_date'], $r['type'], mb_substr($r['reason'], 0, 255), me()['id'], date('Y-m-d H:i:s')]);
        }
        run('UPDATE leave_requests SET status=?, decided_by=?, decided_note=?, decided_at=?, leave_id=? WHERE id=?', [$approve ? 'approved' : 'rejected', me()['id'], mb_substr($note, 0, 255), date('Y-m-d H:i:s'), $leaveId, $id]);
        $pdo->commit();
    } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
    audit($approve ? 'leave_approved' : 'leave_rejected', ($r['sname'] ?: $r['uname']) . ' ' . $r['from_date'] . '..' . $r['to_date']);
    if ($r['email']) send_mail($r['email'], $r['uname'], 'Leave request ' . ($approve ? 'approved' : 'not approved'), mail_wrap('Leave request',
        '<p>Dear ' . e($r['uname']) . ',</p><p>The leave request for <b>' . e($r['sname'] ?: $r['uname']) . '</b> (' . e(date_range_label($r['from_date'], $r['to_date'])) . ') was <b>' . ($approve ? 'approved' : 'not approved') . '</b>.</p>' . ($note !== '' ? '<p>Note from school: ' . e($note) . '</p>' : '')), $school);
    return $r;
}
/** Takes back an approval (current or future leave only). */
function withdraw_leave(int $id, int $school): void {
    $r = row("SELECT * FROM leave_requests WHERE id=? AND school_id=? AND status='approved' AND to_date>=?", [$id, $school, date('Y-m-d')]);
    if (!$r) throw new RuntimeException('Only a current or coming approved leave can be cancelled.');
    if ($r['leave_id']) { // a teacher: remove the leave entry and its substitutes as well
        $l = row('SELECT * FROM faculty_leave WHERE id=? AND school_id=?', [$r['leave_id'], $school]);
        if ($l) {
            run('DELETE FROM substitutions WHERE school_id=? AND date BETWEEN ? AND ? AND timetable_id IN (SELECT id FROM timetable WHERE faculty_id=?)', [$school, $l['from_date'], $l['to_date'], $l['faculty_id']]);
            run('DELETE FROM faculty_leave WHERE id=?', [$l['id']]);
        }
    }
    run("UPDATE leave_requests SET status='cancelled', decided_note='Cancelled by the school' WHERE id=?", [$id]);
    audit('leave_withdrawn', (string)$id);
}
/** Students of a class with approved leave on a date: [student_id => leave type]. */
function students_on_leave(int $classId, string $date): array {
    $out = [];
    foreach (q("SELECT r.student_id, r.type FROM leave_requests r JOIN students s ON s.id=r.student_id WHERE s.class_id=? AND r.status='approved' AND r.from_date<=? AND r.to_date>=?", [$classId, $date, $date]) as $r) $out[(int)$r['student_id']] = $r['type'];
    return $out;
}
function pending_leave_count(int $school): int { return (int)val("SELECT COUNT(*) FROM leave_requests WHERE school_id=? AND status='pending'", [$school]); }

/** Application form plus the applicant's own requests. $action = form URL. */
function leave_panel(int $userId, ?array $student, string $action = ''): string {
    $types = $student ? STUDENT_LEAVE_TYPES : LEAVE_TYPES; $min = date('Y-m-d', strtotime('-30 day'));
    $o = '<div class="card"><h3>Apply for leave' . ($student ? ' for ' . e($student['name']) : '') . '</h3><form method="post"' . ($action ? ' action="' . e($action) . '"' : '') . '>' . csrf_field() . '<input type="hidden" name="action" value="apply"><div class="form-grid">' .
        '<div><label>From *</label><input type="date" name="from" required min="' . $min . '"></div><div><label>To <span class="muted">(same day if empty)</span></label><input type="date" name="to" min="' . $min . '"></div><div><label>Type</label><select name="type">';
    foreach ($types as $t) $o .= '<option>' . e($t) . '</option>';
    $o .= '</select></div><div class="full"><label>Reason *</label><textarea name="reason" required maxlength="500"></textarea></div></div><p><button class="btn btn-primary">Send request</button></p></form></div>';
    $where = $student ? 'student_id=' . (int)$student['id'] : "kind='faculty'";
    [$rows, $pager] = paginate("SELECT * FROM leave_requests WHERE user_id=? AND $where ORDER BY id DESC", [$userId], 10);
    $o .= '<div class="card"><h3>My requests</h3><div class="table-wrap"><table class="stack"><tr><th>Dates</th><th>Type</th><th>Reason</th><th>Status</th><th></th></tr>';
    foreach ($rows as $r) $o .= '<tr><td class="who-cell"><b>' . e(date_range_label($r['from_date'], $r['to_date'])) . '</b></td><td data-label="Type">' . e($r['type']) . '</td><td data-label="Reason" class="wide">' . e($r['reason']) . '</td><td data-label="Status">' . badge(...REQUEST_STATUS[$r['status']]) . ($r['decided_note'] ? '<br><span class="muted small">' . e($r['decided_note']) . '</span>' : '') . '</td><td class="right">' .
        ($r['status'] === 'pending' ? '<form method="post"' . ($action ? ' action="' . e($action) . '"' : '') . '>' . csrf_field() . '<input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="btn btn-sm"' . confirm_attr('Cancel this request?') . '>Cancel</button></form>' : '') . '</td></tr>';
    if (!$rows) $o .= empty_row(5, 'No requests yet.');
    return $o . '</table></div>' . $pager . '</div>';
}
