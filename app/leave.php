<?php
declare(strict_types=1);
/* Holidays (whole school or one class), faculty leave and substitute teachers. */

const LEAVE_TYPES = ['Casual leave', 'Sick leave', 'Official duty', 'Training', 'Other'];

/** Created on demand for SQLite and MySQL, so existing installations need no manual upgrade. */
function ensure_leave_tables(PDO $pdo, bool $mysql): void {
    $id = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $n = $mysql ? 'INT UNSIGNED' : 'INTEGER'; $end = $mysql ? ' ENGINE=InnoDB' : '';
    $pdo->exec("CREATE TABLE IF NOT EXISTS holidays(id $id, school_id $n NOT NULL, class_id $n NULL, title VARCHAR(150) NOT NULL, from_date DATE NOT NULL, to_date DATE NOT NULL, created_by $n NULL, created_at DATETIME NOT NULL)$end");
    $pdo->exec("CREATE TABLE IF NOT EXISTS faculty_leave(id $id, school_id $n NOT NULL, faculty_id $n NOT NULL, from_date DATE NOT NULL, to_date DATE NOT NULL, type VARCHAR(40) NOT NULL, note VARCHAR(255) NULL, created_by $n NULL, created_at DATETIME NOT NULL)$end");
    $pdo->exec("CREATE TABLE IF NOT EXISTS substitutions(school_id $n NOT NULL, date DATE NOT NULL, timetable_id $n NOT NULL, faculty_id $n NOT NULL, created_by $n NULL, PRIMARY KEY (date, timetable_id))$end");
}

/** Holidays on a date: [class_id => title]; key 0 means the whole school. */
function holidays_on(int $school, string $date): array {
    $out = [];
    foreach (q('SELECT class_id, title FROM holidays WHERE school_id=? AND from_date<=? AND to_date>=?', [$school, $date, $date]) as $h) $out[(int)$h['class_id']] = $h['title'];
    return $out;
}
function holiday_title(array $holidays, int $classId): ?string { return $holidays[0] ?? $holidays[$classId] ?? null; }
/** Teachers who are off on a date: [faculty_id => leave row]. */
function away_on(int $school, string $date): array {
    $out = [];
    foreach (q('SELECT * FROM faculty_leave WHERE school_id=? AND from_date<=? AND to_date>=?', [$school, $date, $date]) as $l) $out[(int)$l['faculty_id']] = $l;
    // teachers marked absent in faculty attendance are off too, so their periods can be covered
    foreach (q("SELECT faculty_id FROM faculty_attendance WHERE school_id=? AND date=? AND status='A'", [$school, $date]) as $a) $out[(int)$a['faculty_id']] ??= ['type' => 'Absent', 'absent' => true];
    return $out;
}

/**
 * Every period of the school on a date, with what happens to it:
 * holiday (title|null), away (owner is on leave), cover_id / cover_name (substitute; only while the owner is away and the substitute is not).
 */
function day_periods(int $school, string $date): array {
    $dow = (int)date('N', (int)strtotime($date)); if ($dow > 6) return [];
    $rows = q('SELECT t.*, c.name cname, c.section, sub.name sname, ou.name owner, s.faculty_id sub_id, su.name sub_name FROM timetable t
        JOIN classes c ON c.id=t.class_id JOIN subjects sub ON sub.id=t.subject_id JOIN users ou ON ou.id=t.faculty_id
        LEFT JOIN substitutions s ON s.timetable_id=t.id AND s.date=? LEFT JOIN users su ON su.id=s.faculty_id
        WHERE t.school_id=? AND t.day=? ORDER BY t.period, (c.name+0), c.name, c.section', [$date, $school, $dow]);
    $hol = holidays_on($school, $date); $away = away_on($school, $date);
    foreach ($rows as &$r) {
        $r['holiday'] = holiday_title($hol, (int)$r['class_id']);
        $r['away'] = isset($away[(int)$r['faculty_id']]); $r['absent'] = !empty($away[(int)$r['faculty_id']]['absent']);
        $valid = $r['away'] && $r['sub_id'] && !isset($away[(int)$r['sub_id']]);
        $r['cover_id'] = $valid ? (int)$r['sub_id'] : 0; $r['cover_name'] = $valid ? $r['sub_name'] : null;
    }
    return $rows;
}
/** The periods that concern one teacher on a date: own ones (possibly blocked) plus those they cover. 'blocked' is '' when attendance may be marked. */
function faculty_slots(int $faculty, int $school, string $date): array {
    $out = [];
    foreach (day_periods($school, $date) as $r) {
        $own = (int)$r['faculty_id'] === $faculty;
        if (!$own && $r['cover_id'] !== $faculty) continue;
        $r['covering'] = $own ? null : $r['owner'];
        $r['blocked'] = $r['holiday'] !== null ? 'Holiday: ' . $r['holiday'] : ($own && $r['away'] ? ($r['absent'] ? 'You are marked absent' : 'You are on leave') . ($r['cover_name'] ? ' - covered by ' . $r['cover_name'] : '') : '');
        $out[] = $r;
    }
    return $out;
}

function date_range_label(string $from, string $to): string { return $from === $to ? fmt_date($from, 'D, d M Y') : fmt_date($from) . ' to ' . fmt_date($to); }
/** Holidays from today onwards; $classId null = all (staff view), otherwise school-wide plus that class. */
function upcoming_holidays_html(int $school, ?int $classId = null, int $limit = 4): string {
    $w = 'h.school_id=? AND h.to_date>=?'; $p = [$school, date('Y-m-d')];
    if ($classId !== null) { $w .= ' AND (h.class_id IS NULL OR h.class_id=?)'; $p[] = $classId; }
    $rows = q("SELECT h.*, c.name cname, c.section FROM holidays h LEFT JOIN classes c ON c.id=h.class_id WHERE $w ORDER BY h.from_date LIMIT " . (int)$limit, $p);
    if (!$rows) return '';
    $o = '<div class="card"><h3>Upcoming holidays</h3>';
    foreach ($rows as $h) $o .= '<div class="slot"><div class="info"><b>' . e($h['title']) . '</b><br><span class="muted small">' . e(date_range_label($h['from_date'], $h['to_date'])) . '</span></div>' .
        ($h['from_date'] <= date('Y-m-d') ? badge('Today', 'green') : '') . ' ' . badge($h['class_id'] ? 'Class ' . cls($h) : 'Whole school', 'blue') . '</div>';
    return $o . '</div>';
}
