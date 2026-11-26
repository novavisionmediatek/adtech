<?php
declare(strict_types=1);
/*
 * Tiny .xlsx writer (no Composer, no PHP zip extension needed).
 * Sheet: ['name' => string, 'rows' => [[cell, ...], ...], 'widths' => [col widths], 'freeze' => [rows, cols]]
 * Cell : scalar, or [value, style]. Styles: 1 header, 2 title, 3 green, 4 red, 5 amber, 6 muted, 7 centered.
 * Text is written as inline strings, so a value starting with "=" is never run as a formula.
 */
function xlsx_download(string $filename, array $sheets): never {
    $bin = xlsx_build($sheets);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^\w.\-]+/', '_', $filename) . '"');
    header('Content-Length: ' . strlen($bin)); header('Cache-Control: private, no-store');
    echo $bin; exit;
}
function xlsx_esc(string $s): string { return htmlspecialchars((string)preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function xlsx_col(int $i): string { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; }

function xlsx_sheet_xml(array $sh): string {
    [$fr, $fc] = $sh['freeze'] ?? [0, 0];
    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $x .= '<sheetViews><sheetView workbookViewId="0">';
    if ($fr || $fc) $x .= '<pane' . ($fc ? ' xSplit="' . $fc . '"' : '') . ($fr ? ' ySplit="' . $fr . '"' : '') . ' topLeftCell="' . xlsx_col($fc) . ($fr + 1) . '" activePane="' . ($fr && $fc ? 'bottomRight' : ($fr ? 'bottomLeft' : 'topRight')) . '" state="frozen"/>';
    $x .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/>';
    if (!empty($sh['widths'])) { $x .= '<cols>'; foreach ($sh['widths'] as $i => $w) $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float)$w . '" customWidth="1"/>'; $x .= '</cols>'; }
    $x .= '<sheetData>';
    foreach (array_values($sh['rows']) as $r => $row) {
        $x .= '<row r="' . ($r + 1) . '">';
        foreach (array_values($row) as $c => $cell) {
            [$v, $s] = is_array($cell) ? [$cell[0], (int)$cell[1]] : [$cell, 0];
            if ($v === null || ($v === '' && !$s)) continue;
            $ref = ' r="' . xlsx_col($c) . ($r + 1) . '"' . ($s ? ' s="' . $s . '"' : '');
            $x .= is_int($v) || is_float($v) ? "<c$ref><v>$v</v></c>" : "<c$ref t=\"inlineStr\"><is><t xml:space=\"preserve\">" . xlsx_esc((string)$v) . '</t></is></c>';
        }
        $x .= '</row>';
    }
    return $x . '</sheetData><pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/><pageSetup paperSize="9" orientation="landscape"/></worksheet>';
}

function xlsx_build(array $sheets): string {
    $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $ct = $head . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $wb = ''; $rels = ''; $files = []; $used = [];
    foreach (array_values($sheets) as $i => $sh) {
        $n = $i + 1;
        $name = mb_substr(trim((string)preg_replace('/[\[\]:*?\/\\\\]/', ' ', $sh['name'])), 0, 31) ?: "Sheet$n";
        if (isset($used[mb_strtolower($name)])) $name = mb_substr($name, 0, 27) . " $n"; $used[mb_strtolower($name)] = true;
        $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $wb .= '<sheet name="' . xlsx_esc($name) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        $files["xl/worksheets/sheet$n.xml"] = xlsx_sheet_xml($sh);
    }
    $files['[Content_Types].xml'] = $ct . '</Types>';
    $files['_rels/.rels'] = $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $files['xl/workbook.xml'] = $head . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wb . '</sheets></workbook>';
    $files['xl/_rels/workbook.xml.rels'] = $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels .
        '<Relationship Id="rId' . (count($sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $fill = fn(string $rgb) => '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $rgb . '"/><bgColor indexed="64"/></patternFill></fill>';
    $xf = fn(int $font, int $fl, bool $center) => '<xf numFmtId="0" fontId="' . $font . '" fillId="' . $fl . '" borderId="0" xfId="0" applyFont="1" applyFill="1"' . ($center ? ' applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' : '/>');
    $files['xl/styles.xml'] = $head . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts count="7"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="15"/><color rgb="FF130D2E"/><name val="Calibri"/></font>' .
        '<font><sz val="11"/><color rgb="FF0A7048"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFB31B40"/><name val="Calibri"/></font><font><sz val="11"/><color rgb="FF895D00"/><name val="Calibri"/></font><font><sz val="10"/><color rgb="FF636780"/><name val="Calibri"/></font></fonts>' .
        '<fills count="6"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>' . $fill('130D2E') . $fill('DCF4E8') . $fill('FDE3E9') . $fill('FFF0C2') . '</fills>' .
        '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="8">' . $xf(0, 0, false) . $xf(1, 2, true) . $xf(2, 0, false) . $xf(3, 3, true) . $xf(4, 4, true) . $xf(5, 5, true) . $xf(6, 0, false) . $xf(0, 0, true) . '</cellXfs>' .
        '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    return zip_pack($files);
}

/** Builds a zip archive in memory from [path => contents]. */
function zip_pack(array $files): string {
    $t = getdate(); $time = ($t['hours'] << 11) | ($t['minutes'] << 5) | ($t['seconds'] >> 1); $date = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
    $body = ''; $dir = '';
    foreach ($files as $name => $data) {
        $crc = crc32($data); $size = strlen($data);
        $deflate = function_exists('gzdeflate'); $packed = $deflate ? gzdeflate($data, 6) : $data; $method = $deflate ? 8 : 0; $csize = strlen($packed);
        $offset = strlen($body);
        $body .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $time, $date, $crc, $csize, $size, strlen($name), 0) . $name . $packed;
        $dir .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $method, $time, $date, $crc, $csize, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
    }
    return $body . $dir . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($dir), strlen($body), 0);
}

/* ------------------------------ month-wise student attendance report ------------------------------ */
/**
 * Student attendance as Excel sheets: Summary, one register per month, and every single record. Null when empty.
 * $classes  : the classes to include (each ['id','name','section']), already checked by the caller.
 * $faculty  : only periods marked by this teacher.  $student : only this student.
 */
function attendance_report_sheets(int $school, array $classes, ?int $faculty = null, ?int $student = null): ?array {
    if (!$classes) return null;
    $ids = implode(',', array_map(fn($c) => (int)$c['id'], $classes));
    $labelOf = []; foreach ($classes as $c) $labelOf[(int)$c['id']] = class_label($c);
    $w = "a.school_id=? AND a.class_id IN ($ids)"; $p = [$school];
    if ($faculty) { $w .= ' AND a.faculty_id=?'; $p[] = $faculty; }
    if ($student) { $w .= ' AND a.student_id=?'; $p[] = $student; }
    $records = q("SELECT a.student_id, a.class_id, a.date, a.period, a.status, sub.name sname, u.name fname FROM attendance a JOIN subjects sub ON sub.id=a.subject_id
                  LEFT JOIN users u ON u.id=a.faculty_id WHERE $w ORDER BY a.date, a.class_id, a.period", $p);
    if (!$records) return null;
    $have = implode(',', array_map('intval', array_keys(array_flip(array_column($records, 'student_id')))));
    $sw = "st.school_id=? AND ((st.class_id IN ($ids) AND st.active=1) OR st.id IN ($have))"; $sp = [$school];
    if ($student) { $sw = 'st.school_id=? AND st.id=?'; $sp[] = $student; }
    $students = q("SELECT st.id, st.class_id, st.roll_no, st.admission_no, u.name, c.name cname, c.section FROM students st JOIN users u ON u.id=st.user_id JOIN classes c ON c.id=st.class_id
                   WHERE $sw ORDER BY (c.name+0), c.name, c.section, st.roll_no, u.name", $sp);
    $byId = []; foreach ($students as $s) $byId[$s['id']] = $s;
    $grid = []; $months = []; // [month][student][date] => counts
    foreach ($records as $r) {
        $m = substr((string)$r['date'], 0, 7); $months[$m][$r['date']] = true;
        $grid[$m][$r['student_id']][$r['date']][$r['status']] = ($grid[$m][$r['student_id']][$r['date']][$r['status']] ?? 0) + 1;
    }
    ksort($months);
    $count = fn(array $days) => array_reduce($days, fn($c, $d) => ['P' => $c['P'] + ($d['P'] ?? 0), 'L' => $c['L'] + ($d['L'] ?? 0), 'A' => $c['A'] + ($d['A'] ?? 0)], ['P' => 0, 'L' => 0, 'A' => 0]);
    $min = (int)cfg('min_attendance');
    $pctCell = function (array $c) use ($min) { $t = $c['P'] + $c['L'] + $c['A']; if (!$t) return ['-', 7]; $p = pct((float)($c['P'] + $c['L']), (float)$t); return [(float)$p, $p >= $min ? 3 : 4]; };
    $head = fn(array $cols) => array_map(fn($c) => [$c, 1], $cols);
    $label = fn(string $m) => date('M Y', (int)strtotime("$m-01"));
    $dates = array_merge(...array_map('array_keys', array_values($months)));
    $what = $student ? ($students[0]['name'] ?? 'Student') . ' (Class ' . ($students ? cls($students[0]) : '') . ')' : (count($classes) === 1 ? 'Class ' . class_label($classes[0]) : 'All classes');
    $by = $faculty ? ' | Periods of ' . (string)val('SELECT name FROM users WHERE id=?', [$faculty]) . ' only' : '';
    $title = fn(string $sub) => [[[(string)val('SELECT name FROM schools WHERE id=?', [$school]) . ' - ' . $what, 2]], [[$sub . ' | Generated ' . date('d M Y, h:i A') . ' | Minimum required ' . $min . '%' . $by, 6]], []];
    $who = fn(array $s) => [cls($s), [(int)$s['roll_no'], 7], $s['admission_no'], $s['name']];
    $whoHead = ['Class', 'Roll', 'Admission No', 'Student']; $whoWidth = [9, 7, 15, 26];

    // Summary: one percentage column per month + overall totals
    $rows = $title('Month-wise attendance, ' . date('d M Y', (int)strtotime(min($dates))) . ' to ' . date('d M Y', (int)strtotime(max($dates))));
    $rows[] = $head(array_merge($whoHead, array_map(fn($m) => $label($m) . ' %', array_keys($months)), ['Periods', 'Present', 'Late', 'Absent', 'Overall %']));
    foreach ($students as $s) {
        $line = $who($s); $all = ['P' => 0, 'L' => 0, 'A' => 0];
        foreach (array_keys($months) as $m) { $c = $count($grid[$m][$s['id']] ?? []); $line[] = $pctCell($c); foreach ($all as $k => $v) $all[$k] = $v + $c[$k]; }
        array_push($line, [array_sum($all), 7], [$all['P'], 7], [$all['L'], 7], [$all['A'], 7], $pctCell($all));
        $rows[] = $line;
    }
    $sheets = [['name' => 'Summary', 'rows' => $rows, 'freeze' => [4, 4], 'widths' => array_merge($whoWidth, array_fill(0, count($months), 12), [10, 10, 8, 9, 12])]];

    // One day-by-day register per month
    foreach ($months as $m => $set) {
        $days = array_keys($set); sort($days);
        $rows = $title($label($m) . ' register');
        $rows[2] = [['P = attended every period | 5/6 = attended 5 of 6 | A = absent all day | L = includes a late mark', 6]];
        $rows[] = $head(array_merge($whoHead, array_map(fn($d) => date('d D', (int)strtotime($d)), $days), ['Periods', 'Present', 'Late', 'Absent', '%']));
        foreach ($students as $s) {
            $line = $who($s);
            foreach ($days as $d) {
                $c = $grid[$m][$s['id']][$d] ?? null;
                if (!$c) { $line[] = ['-', 7]; continue; }
                $p = $c['P'] ?? 0; $l = $c['L'] ?? 0; $a = $c['A'] ?? 0; $t = $p + $l + $a;
                if ($a === $t) $line[] = ['A', 4];
                elseif ($a) $line[] = [($p + $l) . '/' . $t . ($l ? ' L' : ''), 5];
                else $line[] = [$l ? 'P L' : 'P', $l ? 5 : 3];
            }
            $c = $count($grid[$m][$s['id']] ?? []);
            array_push($line, [array_sum($c), 7], [$c['P'], 7], [$c['L'], 7], [$c['A'], 7], $pctCell($c));
            $rows[] = $line;
        }
        $sheets[] = ['name' => $label($m), 'rows' => $rows, 'freeze' => [4, 4], 'widths' => array_merge($whoWidth, array_fill(0, count($days), 8), [9, 9, 7, 8, 8])];
    }

    // Every record: date, period, subject, teacher, student, status
    $rows = [$head(['Month', 'Date', 'Day', 'Class', 'Period', 'Subject', 'Teacher', 'Roll', 'Admission No', 'Student', 'Status'])];
    $word = ['P' => ['Present', 3], 'L' => ['Late', 5], 'A' => ['Absent', 4]];
    foreach ($records as $r) {
        $s = $byId[$r['student_id']] ?? null; if (!$s) continue; $ts = (int)strtotime((string)$r['date']);
        $rows[] = [$label(substr((string)$r['date'], 0, 7)), date('d-m-Y', $ts), date('D', $ts), $labelOf[(int)$r['class_id']] ?? '', [(int)$r['period'], 7], $r['sname'], (string)$r['fname'], [(int)$s['roll_no'], 7], $s['admission_no'], $s['name'], $word[$r['status']] ?? [$r['status'], 7]];
    }
    $sheets[] = ['name' => 'All records', 'rows' => $rows, 'freeze' => [1, 0], 'widths' => [11, 12, 7, 9, 8, 18, 22, 7, 15, 26, 10]];
    return $sheets;
}

/** One overview workbook across every school (super admin): student totals and faculty totals. */
function platform_attendance_sheets(): ?array {
    $head = fn(array $cols) => array_map(fn($c) => [$c, 1], $cols);
    $min = (int)cfg('min_attendance');
    $title = fn(string $t) => [[[$t, 2]], [['All schools | Generated ' . date('d M Y, h:i A'), 6]], []];
    $rows = $title('Student attendance - all records to date');
    $rows[] = $head(['School', 'Class', 'Roll', 'Admission No', 'Student', 'Periods', 'Present', 'Late', 'Absent', '%']);
    $data = q("SELECT sc.name school, c.name cname, c.section, st.roll_no, st.admission_no, u.name, COUNT(a.id) total, COALESCE(SUM(a.status='P'),0) p, COALESCE(SUM(a.status='L'),0) l, COALESCE(SUM(a.status='A'),0) ab
        FROM students st JOIN users u ON u.id=st.user_id JOIN classes c ON c.id=st.class_id JOIN schools sc ON sc.id=st.school_id LEFT JOIN attendance a ON a.student_id=st.id
        WHERE st.active=1 GROUP BY st.id ORDER BY sc.name, (c.name+0), c.name, c.section, st.roll_no");
    foreach ($data as $r) {
        $t = (int)$r['total']; $pc = $t ? pct((float)($r['p'] + $r['l']), (float)$t) : null;
        $rows[] = [$r['school'], cls($r), [(int)$r['roll_no'], 7], $r['admission_no'], $r['name'], [$t, 7], [(int)$r['p'], 7], [(int)$r['l'], 7], [(int)$r['ab'], 7], $pc === null ? ['-', 7] : [(float)$pc, $pc >= $min ? 3 : 4]];
    }
    $staff = $title('Faculty attendance - all saved records');
    $staff[] = $head(['School', 'Faculty', 'Employee ID', 'Marked days', 'Present', 'Late', 'Half day', 'Absent', '%']);
    $sd = q("SELECT sc.name school, u.name, f.employee_id, COUNT(a.date) total, COALESCE(SUM(a.status='P'),0) p, COALESCE(SUM(a.status='L'),0) l, COALESCE(SUM(a.status='H'),0) h, COALESCE(SUM(a.status='A'),0) ab
        FROM users u JOIN schools sc ON sc.id=u.school_id LEFT JOIN faculty f ON f.user_id=u.id LEFT JOIN faculty_attendance a ON a.faculty_id=u.id
        WHERE u.role='faculty' AND u.active=1 GROUP BY u.id ORDER BY sc.name, u.name");
    foreach ($sd as $r) {
        $t = (int)$r['total']; $pc = $t ? pct((float)($r['p'] + $r['l'] + 0.5 * $r['h']), (float)$t) : null;
        $staff[] = [$r['school'], $r['name'], (string)$r['employee_id'], [$t, 7], [(int)$r['p'], 7], [(int)$r['l'], 7], [(int)$r['h'], 7], [(int)$r['ab'], 7], $pc === null ? ['-', 7] : [(float)$pc, $pc >= 75 ? 3 : 4]];
    }
    if (!$data && !$sd) return null;
    return [['name' => 'Students', 'rows' => $rows, 'freeze' => [4, 0], 'widths' => [28, 9, 7, 15, 26, 9, 9, 7, 8, 8]],
            ['name' => 'Faculty', 'rows' => $staff, 'freeze' => [4, 0], 'widths' => [28, 26, 14, 12, 9, 7, 9, 8, 8]]];
}

/** Send a workbook, or go back with a message when there is nothing to export. */
function export_or_back(?array $sheets, string $file, string $back, string $auditDetail): never {
    if (!$sheets) { flash('There is no attendance recorded for this selection yet.', 'warn'); redirect($back); }
    audit('attendance_export', $auditDetail);
    xlsx_download($file . '_' . date('Y-m-d') . '.xlsx', $sheets);
}
