<?php
// School admins can see and correct tracking for any homework of their own school.
$h = row('SELECT h.*, c.name cname, c.section, sub.name sname FROM homework h JOIN classes c ON c.id=h.class_id JOIN subjects sub ON sub.id=h.subject_id WHERE h.id=? AND h.school_id=?', [(int)inp('id'), sid()]);
if (!$h) render_error(404, 'Homework not found', 'This homework does not exist.');
hw_check_page($h, 'admin');
