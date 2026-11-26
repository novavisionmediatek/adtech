<?php
// School admin downloads, always limited to the admin's own school.
require_once APP . '/xlsx.php';
set_time_limit(0);
$s = sid(); $what = inp('what');
if ($what === 'faculty') {
    $fid = (int)inp('faculty'); $name = 'all';
    if ($fid) { $name = val("SELECT name FROM users WHERE id=? AND school_id=? AND role='faculty'", [$fid, $s]); if (!$name) render_error(404, 'Not found', 'This faculty member does not exist.'); }
    export_or_back(staff_report_sheets($s, $fid ?: null), 'faculty_attendance_' . slugify((string)$name), 'admin/faculty', "faculty: $name");
}
$classes = school_classes($s); $sid2 = (int)inp('student'); $cid = (int)inp('class'); $file = 'student_attendance_all-classes';
if ($sid2) {
    $st = row('SELECT st.id, st.class_id, st.admission_no FROM students st WHERE st.id=? AND st.school_id=?', [$sid2, $s]);
    if (!$st) render_error(404, 'Not found', 'This student does not exist.');
    $file = 'attendance_' . $st['admission_no'];
    // include every class the student has records in (they may have changed class)
    $in = array_column(q('SELECT DISTINCT class_id FROM attendance WHERE student_id=? AND school_id=?', [$sid2, $s]), 'class_id'); $in[] = $st['class_id'];
    $classes = array_values(array_filter($classes, fn($c) => in_array($c['id'], $in)));
} elseif ($cid) {
    $classes = array_values(array_filter($classes, fn($c) => (int)$c['id'] === $cid));
    if (!$classes) render_error(404, 'Not found', 'This class does not exist.');
    $file = 'student_attendance_' . class_label($classes[0]);
}
export_or_back(attendance_report_sheets($s, $classes, null, $sid2 ?: null), $file, 'admin/students', $file);
