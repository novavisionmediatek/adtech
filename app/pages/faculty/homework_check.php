<?php
// Teachers can track only the homework they posted.
$h = row('SELECT h.*, c.name cname, c.section, sub.name sname FROM homework h JOIN classes c ON c.id=h.class_id JOIN subjects sub ON sub.id=h.subject_id WHERE h.id=? AND h.faculty_id=?', [(int)inp('id'), me()['id']]);
if (!$h) render_error(404, 'Homework not found', 'This homework does not exist or is not yours.');
hw_check_page($h, 'faculty');
