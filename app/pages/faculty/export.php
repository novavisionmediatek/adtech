<?php
// Teachers download student attendance of the periods they marked, only for classes they teach or covered.
require_once APP . '/xlsx.php';
set_time_limit(0);
$u = me(); $cid = (int)inp('class');
$classes = q('SELECT DISTINCT c.id, c.name, c.section FROM classes c WHERE c.school_id=? AND (c.id IN (SELECT class_id FROM timetable WHERE faculty_id=?) OR c.id IN (SELECT class_id FROM attendance WHERE faculty_id=?))
    ORDER BY (c.name+0), c.name, c.section', [$u['school_id'], $u['id'], $u['id']]);
if ($cid) {
    $classes = array_values(array_filter($classes, fn($c) => (int)$c['id'] === $cid));
    if (!$classes) render_error(404, 'Not found', 'This is not one of your classes.');
}
$file = 'my_student_attendance_' . ($cid ? class_label($classes[0]) : 'all-classes');
export_or_back(attendance_report_sheets((int)$u['school_id'], $classes, (int)$u['id']), $file, 'faculty/reports', $file);
