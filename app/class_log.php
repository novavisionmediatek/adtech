<?php
declare(strict_types=1);
/*
 * Date-wise class record: on which date, in which period, which teacher took which class.
 * "Taken" means attendance was marked for that class and period - the attendance rows are the evidence of who
 * actually took it. Periods without attendance come from the timetable, holidays, leave and substitutes.
 */

const LOG_STATUS = ['taken' => ['Taken', 'green'], 'not_taken' => ['Not taken', 'red'], 'no_teacher' => ['No teacher', 'red'], 'upcoming' => ['Upcoming', 'blue'], 'holiday' => ['Holiday', 'gray']];
const LOG_MAX_DAYS = 62;

function class_log(int $school, string $from, string $to, int $classId = 0, int $facultyId = 0): array {
    $today = date('Y-m-d'); $now = date('H:i');
    $recorded = []; // "date|class|period" => attendance summary
    foreach (q("SELECT a.date, a.class_id, a.period, a.subject_id, a.faculty_id, SUM(a.status='P') p, SUM(a.status='L') l, SUM(a.status='A') ab, MAX(a.marked_at) marked_at,
            s.name sname, u.name fname, c.name cname, c.section
        FROM attendance a JOIN subjects s ON s.id=a.subject_id JOIN classes c ON c.id=a.class_id LEFT JOIN users u ON u.id=a.faculty_id
        WHERE a.school_id=? AND a.date BETWEEN ? AND ? GROUP BY a.date, a.class_id, a.period, a.subject_id, a.faculty_id ORDER BY marked_at", [$school, $from, $to]) as $r) {
        $k = $r['date'] . '|' . $r['class_id'] . '|' . $r['period'];
        if (isset($recorded[$k])) foreach (['p', 'l', 'ab'] as $c) $r[$c] += $recorded[$k][$c]; // latest teacher/subject wins, counts add up
        $recorded[$k] = $r;
    }
    $staff = [];
    foreach (q('SELECT faculty_id, date, status, check_in FROM faculty_attendance WHERE school_id=? AND date BETWEEN ? AND ?', [$school, $from, $to]) as $r) $staff[$r['faculty_id']][$r['date']] = $r;

    $rows = [];
    for ($d = $from; $d <= $to; $d = date('Y-m-d', (int)strtotime("$d +1 day"))) {
        $away = away_on($school, $d); $planned = day_periods($school, $d); $seen = [];
        $facultyDay = function (?int $fid) use ($staff, $away, $d): array {
            if (!$fid) return ['', ''];
            if (isset($away[$fid]) && empty($away[$fid]['absent'])) return ['LV', ''];
            return isset($staff[$fid][$d]) ? [$staff[$fid][$d]['status'], (string)$staff[$fid][$d]['check_in']] : ['', ''];
        };
        foreach ($planned as $x) {
            $k = $d . '|' . $x['class_id'] . '|' . $x['period']; $seen[$k] = true; $a = $recorded[$k] ?? null;
            $owner = (int)$x['faculty_id']; $remark = '';
            if ($a) {
                $by = (int)$a['faculty_id']; $byName = (string)$a['fname']; $status = 'taken'; $subject = $a['sname'];
                if ($by !== $owner) $remark = 'Substitute / changed: scheduled ' . $x['owner'];
                if ($x['holiday'] !== null) $remark = trim($remark . ' (marked on a holiday: ' . $x['holiday'] . ')');
            } else {
                $by = 0; $byName = ''; $subject = $x['sname'];
                if ($x['holiday'] !== null) { $status = 'holiday'; $remark = $x['holiday']; }
                else {
                    if ($x['cover_id']) { $by = (int)$x['cover_id']; $byName = (string)$x['cover_name']; $remark = 'Substitute for ' . $x['owner']; }
                    elseif ($x['away']) { $remark = $x['owner'] . ($x['absent'] ? ' is absent' : ' is on leave') . ', no substitute'; }
                    else { $by = $owner; $byName = (string)$x['owner']; }
                    $start = cfg('periods')[(int)$x['period']][0] ?? '00:00';
                    $status = $d > $today || ($d === $today && $now < $start) ? 'upcoming' : ($by ? 'not_taken' : 'no_teacher');
                    if ($status === 'upcoming' && !$by) $status = 'no_teacher';
                }
            }
            [$fs, $in] = $facultyDay($by ?: $owner);
            $rows[] = ['date' => $d, 'period' => (int)$x['period'], 'class_id' => (int)$x['class_id'], 'class' => cls($x), 'subject' => $subject, 'owner_id' => $owner, 'owner' => $x['owner'],
                'by_id' => $by, 'by' => $byName, 'fstatus' => $fs, 'check_in' => $in, 'status' => $status, 'present' => $a ? (int)$a['p'] : null, 'late' => $a ? (int)$a['l'] : null, 'absent' => $a ? (int)$a['ab'] : null,
                'marked_at' => $a['marked_at'] ?? null, 'remark' => $remark];
        }
        // attendance marked for a period that is not in the present timetable (the timetable was changed later)
        foreach ($recorded as $k => $a) {
            if ($a['date'] !== $d || isset($seen[$k])) continue;
            [$fs, $in] = $facultyDay((int)$a['faculty_id']);
            $rows[] = ['date' => $d, 'period' => (int)$a['period'], 'class_id' => (int)$a['class_id'], 'class' => cls($a), 'subject' => $a['sname'], 'owner_id' => 0, 'owner' => '',
                'by_id' => (int)$a['faculty_id'], 'by' => (string)$a['fname'], 'fstatus' => $fs, 'check_in' => $in, 'status' => 'taken', 'present' => (int)$a['p'], 'late' => (int)$a['l'], 'absent' => (int)$a['ab'],
                'marked_at' => $a['marked_at'], 'remark' => 'Not in the current timetable'];
        }
    }
    if ($classId) $rows = array_filter($rows, fn($r) => $r['class_id'] === $classId);
    if ($facultyId) $rows = array_filter($rows, fn($r) => $r['by_id'] === $facultyId || $r['owner_id'] === $facultyId);
    usort($rows, fn($a, $b) => [$a['date'], $a['period'], strnatcasecmp($a['class'], $b['class'])] <=> [$b['date'], $b['period'], 0]);
    return $rows;
}

/** Per teacher: periods planned for them, how many they took, missed, and took in place of someone else. */
function class_log_by_faculty(array $rows): array {
    $out = [];
    $blank = fn(string $n) => ['name' => $n, 'planned' => 0, 'taken' => 0, 'missed' => 0, 'upcoming' => 0, 'extra' => 0];
    foreach ($rows as $r) {
        if ($r['status'] === 'holiday') continue;
        if ($r['owner_id']) {
            $o = &$out[$r['owner_id']]; $o ??= $blank($r['owner']); $o['planned']++;
            if ($r['by_id'] === $r['owner_id'] && $r['status'] === 'taken') $o['taken']++;
            elseif ($r['status'] === 'upcoming') { if ($r['by_id'] === $r['owner_id']) $o['upcoming']++; else $o['missed']++; }
            else $o['missed']++;
            unset($o);
        }
        if ($r['status'] === 'taken' && $r['by_id'] && $r['by_id'] !== $r['owner_id']) { $o = &$out[$r['by_id']]; $o ??= $blank($r['by']); $o['extra']++; unset($o); }
    }
    uasort($out, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    return $out;
}

function class_log_sheets(int $school, array $rows, string $from, string $to): ?array {
    if (!$rows) return null;
    $name = (string)val('SELECT name FROM schools WHERE id=?', [$school]);
    $head = fn(array $cols) => array_map(fn($c) => [$c, 1], $cols);
    $title = fn(string $t) => [[[$name . ' - ' . $t, 2]], [[date('d M Y', (int)strtotime($from)) . ' to ' . date('d M Y', (int)strtotime($to)) . ' | Generated ' . date('d M Y, h:i A') . ' | "Taken" = student attendance was marked', 6]], []];
    $style = ['taken' => 3, 'not_taken' => 4, 'no_teacher' => 4, 'upcoming' => 7, 'holiday' => 7];
    $sheet = $title('Date-wise class record');
    $sheet[] = $head(['Date', 'Day', 'Period', 'Time', 'Class', 'Subject', 'Scheduled faculty', 'Taken by', 'Faculty attendance', 'Check-in', 'Class status', 'Present', 'Late', 'Absent', 'Attendance marked at', 'Remark']);
    foreach ($rows as $r) {
        $ts = (int)strtotime($r['date']); $num = fn($v) => $v === null ? ['-', 7] : [$v, 7];
        $sheet[] = [date('d-m-Y', $ts), date('l', $ts), [$r['period'], 7], period_time($r['period']), $r['class'], $r['subject'], $r['owner'] ?: '-', $r['status'] === 'taken' ? $r['by'] : ($r['by'] && $r['status'] === 'upcoming' ? $r['by'] . ' (planned)' : '-'),
            STAFF_STATUS[$r['fstatus']][0] ?? 'Not marked', $r['check_in'], [LOG_STATUS[$r['status']][0], $style[$r['status']]], $num($r['present']), $num($r['late']), $num($r['absent']),
            $r['marked_at'] ? date('d-m-Y h:i A', (int)strtotime((string)$r['marked_at'])) : '', $r['remark']];
    }
    $sum = $title('Faculty summary');
    $sum[] = $head(['Faculty', 'Periods scheduled', 'Taken', 'Missed', 'Upcoming', 'Taken for others', 'Taken % (of due periods)']);
    foreach (class_log_by_faculty($rows) as $f) { $due = $f['taken'] + $f['missed']; $p = $due ? pct((float)$f['taken'], (float)$due) : null;
        $sum[] = [$f['name'], [$f['planned'], 7], [$f['taken'], 7], [$f['missed'], 7], [$f['upcoming'], 7], [$f['extra'], 7], $p === null ? ['-', 7] : [(float)$p, $p >= 90 ? 3 : ($p >= 75 ? 5 : 4)]]; }
    return [['name' => 'Class record', 'rows' => $sheet, 'freeze' => [4, 0], 'widths' => [12, 11, 7, 13, 8, 17, 22, 22, 17, 9, 13, 8, 7, 8, 20, 44]],
            ['name' => 'Faculty summary', 'rows' => $sum, 'freeze' => [4, 1], 'widths' => [26, 18, 9, 9, 10, 17, 24]]];
}
