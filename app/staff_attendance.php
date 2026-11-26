<?php
declare(strict_types=1);
/* Faculty (staff) attendance: one record per teacher per day. Leave and school holidays come from the leave module. */

const STAFF_STATUS = ['P' => ['Present', 'green', 3], 'L' => ['Late', 'amber', 5], 'H' => ['Half day', 'amber', 5], 'A' => ['Absent', 'red', 4], 'LV' => ['On leave', 'blue', 7], 'HO' => ['Holiday', 'gray', 7]];
const STAFF_MARKABLE = ['P', 'L', 'H', 'A'];

function ensure_staff_attendance(PDO $pdo, bool $mysql): void {
    $n = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    $pdo->exec("CREATE TABLE IF NOT EXISTS faculty_attendance(school_id $n NOT NULL, faculty_id $n NOT NULL, date DATE NOT NULL, status VARCHAR(2) NOT NULL, check_in VARCHAR(5) NULL,
        note VARCHAR(255) NULL, marked_by $n NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (faculty_id, date))" . ($mysql ? ' ENGINE=InnoDB' : ''));
}
function staff_badge(string $code): string { return isset(STAFF_STATUS[$code]) ? badge(STAFF_STATUS[$code][0], STAFF_STATUS[$code][1]) : badge('Not marked'); }

/**
 * A month for the given teachers: working days (Mon-Sat, not in the future) and, per teacher and day,
 * [code, check_in, note]. Order of precedence: leave, saved record, school holiday, nothing.
 */
function staff_month(int $school, string $month, array $facultyIds): array {
    $first = $month . '-01'; $last = date('Y-m-t', (int)strtotime($first)); $today = date('Y-m-d');
    $days = [];
    for ($d = $first; $d <= min($last, $today); $d = date('Y-m-d', (int)strtotime("$d +1 day"))) if (date('N', (int)strtotime($d)) <= 6) $days[] = $d;
    $holidays = q('SELECT title, from_date, to_date FROM holidays WHERE school_id=? AND class_id IS NULL AND from_date<=? AND to_date>=?', [$school, $last, $first]);
    $leaves = q('SELECT faculty_id, type, from_date, to_date FROM faculty_leave WHERE school_id=? AND from_date<=? AND to_date>=?', [$school, $last, $first]);
    $saved = []; foreach (q('SELECT * FROM faculty_attendance WHERE school_id=? AND date BETWEEN ? AND ?', [$school, $first, $last]) as $r) $saved[$r['faculty_id']][$r['date']] = $r;
    $grid = [];
    foreach ($facultyIds as $f) foreach ($days as $d) {
        $cell = null;
        foreach ($leaves as $l) if ((int)$l['faculty_id'] === (int)$f && $l['from_date'] <= $d && $l['to_date'] >= $d) $cell = ['LV', '', $l['type']];
        if (!$cell && isset($saved[$f][$d])) $cell = [$saved[$f][$d]['status'], (string)$saved[$f][$d]['check_in'], (string)$saved[$f][$d]['note']];
        if (!$cell) foreach ($holidays as $h) if ($h['from_date'] <= $d && $h['to_date'] >= $d) $cell = ['HO', '', $h['title']];
        $grid[$f][$d] = $cell ?? ['', '', ''];
    }
    return [$days, $grid];
}
/** Totals and attendance % for one teacher's days. Half day counts as half; leave, holidays and unmarked days are not counted. */
function staff_totals(array $cells): array {
    $t = ['P' => 0, 'L' => 0, 'H' => 0, 'A' => 0, 'LV' => 0, 'HO' => 0, '' => 0];
    foreach ($cells as $c) $t[$c[0]] = ($t[$c[0]] ?? 0) + 1;
    $counted = $t['P'] + $t['L'] + $t['H'] + $t['A'];
    $t['pct'] = $counted ? pct((float)($t['P'] + $t['L'] + 0.5 * $t['H']), (float)$counted) : null;
    return $t;
}

/** Excel sheets for every month that has data: Summary, one register per month, and all saved records. Null when empty. $only = one teacher. */
function staff_report_sheets(int $school, ?int $only = null): ?array {
    $faculty = school_faculty($school);
    if ($only) $faculty = array_values(array_filter($faculty, fn($f) => (int)$f['id'] === $only));
    $months = [];
    $and = $only ? ' AND faculty_id=' . (int)$only : '';
    foreach (q('SELECT date d FROM faculty_attendance WHERE school_id=?' . $and, [$school]) as $r) $months[substr((string)$r['d'], 0, 7)] = true;
    foreach (q('SELECT from_date d FROM faculty_leave WHERE school_id=? AND from_date<=?' . $and, [$school, date('Y-m-d')]) as $r) $months[substr((string)$r['d'], 0, 7)] = true;
    if (!$months || !$faculty) return null;
    ksort($months); $months = array_keys($months); $ids = array_map(fn($f) => (int)$f['id'], $faculty);
    $name = (string)val('SELECT name FROM schools WHERE id=?', [$school]);
    $head = fn(array $cols) => array_map(fn($c) => [$c, 1], $cols);
    $pc = fn(array $t) => $t['pct'] === null ? ['-', 7] : [(float)$t['pct'], $t['pct'] >= 90 ? 3 : ($t['pct'] >= 75 ? 5 : 4)];
    $title = fn(string $what) => [[[$name . ' - Faculty attendance', 2]], [[$what . ' | Generated ' . date('d M Y, h:i A') . ' | % = (Present + Late + half of Half days) / marked days', 6]], []];
    $label = fn(string $m) => date('M Y', (int)strtotime("$m-01"));
    $all = []; $registers = [];
    foreach ($months as $m) {
        [$days, $grid] = staff_month($school, $m, $ids);
        $rows = $title($label($m) . ' register | P Present, L Late, H Half day, A Absent, LV Leave, HO Holiday, - Not marked');
        $rows[] = $head(array_merge(['Employee ID', 'Name', 'Designation'], array_map(fn($d) => date('d D', (int)strtotime($d)), $days), ['Present', 'Late', 'Half day', 'Absent', 'Leave', '%']));
        foreach ($faculty as $f) {
            $cells = $grid[$f['id']] ?? []; $line = [(string)$f['employee_id'], $f['name'], (string)$f['designation']];
            foreach ($days as $d) { $c = $cells[$d][0]; $line[] = $c === '' ? ['-', 7] : [$c, STAFF_STATUS[$c][2]]; $all[$f['id']][] = $cells[$d]; }
            $t = staff_totals($cells);
            array_push($line, [$t['P'], 7], [$t['L'], 7], [$t['H'], 7], [$t['A'], 7], [$t['LV'], 7], $pc($t));
            $rows[] = $line;
        }
        $registers[] = ['name' => $label($m), 'rows' => $rows, 'freeze' => [4, 3], 'widths' => array_merge([13, 24, 14], array_fill(0, count($days), 7), [9, 7, 9, 8, 7, 8])];
    }
    $rows = $title($label($months[0]) . ' to ' . $label(end($months)));
    $rows[] = $head(['Employee ID', 'Name', 'Designation', 'Present', 'Late', 'Half day', 'Absent', 'Leave', 'Holidays', 'Not marked', '%']);
    foreach ($faculty as $f) { $t = staff_totals($all[$f['id']] ?? []); $rows[] = [(string)$f['employee_id'], $f['name'], (string)$f['designation'], [$t['P'], 7], [$t['L'], 7], [$t['H'], 7], [$t['A'], 7], [$t['LV'], 7], [$t['HO'], 7], [$t[''], 7], $pc($t)]; }
    $detail = [$head(['Date', 'Day', 'Employee ID', 'Name', 'Status', 'Check-in', 'Note', 'Marked by', 'Saved on'])];
    foreach (q('SELECT a.*, u.name, f.employee_id, m.name marker FROM faculty_attendance a JOIN users u ON u.id=a.faculty_id LEFT JOIN faculty f ON f.user_id=a.faculty_id LEFT JOIN users m ON m.id=a.marked_by WHERE a.school_id=?' . ($only ? ' AND a.faculty_id=' . (int)$only : '') . ' ORDER BY a.date, u.name', [$school]) as $r) {
        $ts = (int)strtotime((string)$r['date']); $s = STAFF_STATUS[$r['status']] ?? [$r['status'], '', 7];
        $detail[] = [date('d-m-Y', $ts), date('D', $ts), (string)$r['employee_id'], $r['name'], [$s[0], $s[2]], (string)$r['check_in'], (string)$r['note'], (string)$r['marker'], date('d-m-Y H:i', (int)strtotime((string)$r['updated_at']))];
    }
    return array_merge([['name' => 'Summary', 'rows' => $rows, 'freeze' => [4, 2], 'widths' => [13, 24, 14, 9, 7, 9, 8, 7, 9, 11, 8]]], $registers,
        [['name' => 'All records', 'rows' => $detail, 'freeze' => [1, 0], 'widths' => [12, 7, 13, 24, 11, 9, 30, 22, 17]]]);
}
