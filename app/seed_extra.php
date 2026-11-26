<?php
declare(strict_types=1);
/* Sample records for the new modules, added once and only to the built-in demo school (demo_mode), so the pages are not empty. */
function seed_extra(PDO $pdo): void {
    $sid = (int)$pdo->query("SELECT id FROM schools WHERE code='DPS' AND udise='DEMO-0000001'")->fetchColumn();
    if (!$sid || $pdo->query("SELECT 1 FROM exams WHERE school_id=$sid UNION SELECT 1 FROM fee_dues WHERE school_id=$sid")->fetchColumn()) return;
    $ins = function (string $sql, array $p) use ($pdo): int { $pdo->prepare($sql)->execute($p); return (int)$pdo->lastInsertId(); };
    $now = date('Y-m-d H:i:s'); $day = fn(int $d) => date('Y-m-d', strtotime(($d >= 0 ? '+' : '') . "$d day"));
    $students = $pdo->query("SELECT id, class_id, roll_no FROM students WHERE school_id=$sid ORDER BY class_id, roll_no")->fetchAll(PDO::FETCH_ASSOC);
    if (!$students) return;
    $pairs = $pdo->query("SELECT DISTINCT class_id, subject_id FROM timetable WHERE school_id=$sid ORDER BY class_id, subject_id")->fetchAll(PDO::FETCH_ASSOC);
    $pdo->beginTransaction();
    // one published exam with marks, one upcoming exam
    $done = $ins('INSERT INTO exams(school_id,name,from_date,to_date,pass_pct,published,created_at) VALUES(?,?,?,?,33,1,?)', [$sid, 'Unit Test 1', $day(-40), $day(-35), $now]);
    $next = $ins('INSERT INTO exams(school_id,name,from_date,to_date,pass_pct,published,created_at) VALUES(?,?,?,?,33,0,?)', [$sid, 'Half Yearly Examination', $day(14), $day(20), $now]);
    foreach ($pairs as $i => $p) {
        $paper = $ins('INSERT INTO exam_papers(exam_id,class_id,subject_id,max_marks,exam_date) VALUES(?,?,?,40,?)', [$done, $p['class_id'], $p['subject_id'], $day(-40 + $i % 6)]);
        $ins('INSERT INTO exam_papers(exam_id,class_id,subject_id,max_marks,exam_date) VALUES(?,?,?,80,?)', [$next, $p['class_id'], $p['subject_id'], $day(14 + $i % 6)]);
        foreach ($students as $s) if ($s['class_id'] == $p['class_id'])
            $pdo->prepare('INSERT INTO exam_marks(paper_id,student_id,marks,absent,updated_at) VALUES(?,?,?,0,?)')->execute([$paper, $s['id'], 16 + (($s['id'] * 7 + $i * 5) % 24), $now]);
    }
    foreach ($students as $n => $s) {
        $due = $ins('INSERT INTO fee_dues(school_id,student_id,title,amount,due_date,created_at) VALUES(?,?,?,?,?,?)', [$sid, $s['id'], 'Tuition fee - Term 2', 4500, $day(-10), $now]);
        if ($n % 3 !== 0) { $pay = $ins('INSERT INTO fee_payments(school_id,due_id,amount,mode,paid_on,created_at) VALUES(?,?,?,?,?,?)', [$sid, $due, $n % 3 === 1 ? 4500 : 2000, $n % 2 ? 'UPI' : 'Cash', $day(-15), $now]);
            $pdo->prepare('UPDATE fee_payments SET receipt_no=? WHERE id=?')->execute(['DPS-R' . str_pad((string)$pay, 5, '0', STR_PAD_LEFT), $pay]); }
    }
    $books = [['Panchatantra Stories', 'Vishnu Sharma', 'LIB-001', 4], ['Wings of Fire', 'A. P. J. Abdul Kalam', 'LIB-002', 3], ['The Jungle Book', 'Rudyard Kipling', 'LIB-003', 2], ['Malgudi Days', 'R. K. Narayan', 'LIB-004', 3]];
    foreach ($books as $i => $b) {
        $bid = $ins('INSERT INTO library_books(school_id,title,author,code,copies,created_at) VALUES(?,?,?,?,?,?)', [$sid, $b[0], $b[1], $b[2], $b[3], $now]);
        if (isset($students[$i])) $ins('INSERT INTO library_loans(school_id,book_id,student_id,issued_on,due_on) VALUES(?,?,?,?,?)', [$sid, $bid, $students[$i]['id'], $day(-12 + $i * 3), $day(-2 + $i * 3)]);
    }
    $route = $ins('INSERT INTO transport_routes(school_id,name,vehicle_no,driver_name,driver_phone,stops,created_at) VALUES(?,?,?,?,?,?,?)', [$sid, 'Route 1 - Gomti Nagar', 'UP32 AB 1234', 'Ramesh Yadav', '9800000099', 'Gomti Nagar, Hazratganj, Education Road', $now]);
    foreach (array_slice($students, 0, 6) as $i => $s) $pdo->prepare('INSERT INTO transport_students(student_id,school_id,route_id,stop,pickup_time) VALUES(?,?,?,?,?)')->execute([$s['id'], $sid, $route, $i < 3 ? 'Gomti Nagar' : 'Hazratganj', $i < 3 ? '07:10' : '07:25']);
    $ins('INSERT INTO admissions(school_id,ref_no,student_name,dob,gender,class_sought,parent_name,parent_email,parent_phone,address,previous_school,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
        [$sid, 'DPS-ADM-SAMPLE', 'Neha Tiwari', '2014-03-12', 'F', '6', 'Sunil Tiwari', 'sunil.tiwari@example.test', '9812345678', 'Indira Nagar, Lucknow', 'City Primary School', $now]);
    $pdo->commit();
}
