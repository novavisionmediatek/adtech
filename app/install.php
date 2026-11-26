<?php
declare(strict_types=1);
/* Creates the schema and demo data on first run (when data/vidyasetu.sqlite does not exist). */

function install_database(PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE schools(
  id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, code TEXT UNIQUE NOT NULL, udise TEXT, board TEXT,
  address TEXT, city TEXT, state TEXT, pincode TEXT, phone TEXT, email TEXT, principal TEXT,
  status TEXT NOT NULL DEFAULT 'pending', created_at TEXT DEFAULT (datetime('now','localtime')));
CREATE TABLE users(
  id INTEGER PRIMARY KEY AUTOINCREMENT, school_id INTEGER REFERENCES schools(id) ON DELETE CASCADE,
  role TEXT NOT NULL, name TEXT NOT NULL, email TEXT, username TEXT UNIQUE, phone TEXT,
  password_hash TEXT NOT NULL, active INTEGER DEFAULT 1, must_change INTEGER DEFAULT 0,
  failed INTEGER DEFAULT 0, locked_until INTEGER DEFAULT 0, last_login TEXT,
  created_at TEXT DEFAULT (datetime('now','localtime')));
CREATE UNIQUE INDEX users_email ON users(lower(email)) WHERE email IS NOT NULL AND email<>'';
CREATE TABLE faculty(user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE, school_id INTEGER, employee_id TEXT, designation TEXT, qualification TEXT);
CREATE TABLE classes(id INTEGER PRIMARY KEY AUTOINCREMENT, school_id INTEGER NOT NULL REFERENCES schools(id) ON DELETE CASCADE,
  name TEXT NOT NULL, section TEXT DEFAULT '', class_teacher_id INTEGER, UNIQUE(school_id,name,section));
CREATE TABLE subjects(id INTEGER PRIMARY KEY AUTOINCREMENT, school_id INTEGER NOT NULL REFERENCES schools(id) ON DELETE CASCADE,
  name TEXT NOT NULL, code TEXT DEFAULT '', UNIQUE(school_id,name));
CREATE TABLE students(id INTEGER PRIMARY KEY AUTOINCREMENT, school_id INTEGER NOT NULL REFERENCES schools(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, class_id INTEGER NOT NULL REFERENCES classes(id),
  admission_no TEXT NOT NULL, roll_no INTEGER DEFAULT 0, dob TEXT, gender TEXT, parent_id INTEGER REFERENCES users(id),
  address TEXT, active INTEGER DEFAULT 1, UNIQUE(school_id,admission_no));
CREATE TABLE timetable(id INTEGER PRIMARY KEY AUTOINCREMENT, school_id INTEGER NOT NULL, class_id INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
  day INTEGER NOT NULL, period INTEGER NOT NULL, subject_id INTEGER NOT NULL REFERENCES subjects(id), faculty_id INTEGER NOT NULL REFERENCES users(id),
  UNIQUE(class_id,day,period));
CREATE INDEX tt_fac ON timetable(faculty_id,day,period);
CREATE TABLE attendance(id INTEGER PRIMARY KEY AUTOINCREMENT, school_id INTEGER NOT NULL, class_id INTEGER NOT NULL, subject_id INTEGER NOT NULL,
  faculty_id INTEGER NOT NULL, student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE, date TEXT NOT NULL, period INTEGER NOT NULL,
  status TEXT NOT NULL, marked_at TEXT DEFAULT (datetime('now','localtime')), UNIQUE(student_id,date,period));
CREATE INDEX att_cls ON attendance(class_id,date);
CREATE TABLE absence_alerts(student_id INTEGER, date TEXT, period_key INTEGER, PRIMARY KEY(student_id,date,period_key));
CREATE TABLE homework(id INTEGER PRIMARY KEY AUTOINCREMENT, school_id INTEGER NOT NULL, class_id INTEGER NOT NULL, subject_id INTEGER NOT NULL,
  faculty_id INTEGER NOT NULL, title TEXT NOT NULL, description TEXT, due_date TEXT, file_name TEXT, file_path TEXT,
  created_at TEXT DEFAULT (datetime('now','localtime')));
CREATE TABLE notices(id INTEGER PRIMARY KEY AUTOINCREMENT, school_id INTEGER, title TEXT NOT NULL, body TEXT, audience TEXT DEFAULT 'all',
  created_by INTEGER, created_at TEXT DEFAULT (datetime('now','localtime')));
CREATE TABLE outbox(id INTEGER PRIMARY KEY AUTOINCREMENT, school_id INTEGER, to_email TEXT, to_name TEXT, subject TEXT, body TEXT,
  status TEXT, error TEXT, created_at TEXT DEFAULT (datetime('now','localtime')), sent_at TEXT);
CREATE TABLE posts(id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, title TEXT NOT NULL, excerpt TEXT, body TEXT, category TEXT,
  published INTEGER DEFAULT 1, created_at TEXT DEFAULT (datetime('now','localtime')));
CREATE TABLE jobs(id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, dept TEXT, location TEXT, type TEXT, description TEXT,
  active INTEGER DEFAULT 1, created_at TEXT DEFAULT (datetime('now','localtime')));
CREATE TABLE applications(id INTEGER PRIMARY KEY AUTOINCREMENT, job_id INTEGER REFERENCES jobs(id) ON DELETE CASCADE, name TEXT, email TEXT, phone TEXT,
  message TEXT, file_path TEXT, file_name TEXT, created_at TEXT DEFAULT (datetime('now','localtime')));
CREATE TABLE messages(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, phone TEXT, subject TEXT, message TEXT,
  handled INTEGER DEFAULT 0, created_at TEXT DEFAULT (datetime('now','localtime')));
CREATE TABLE audit(id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, school_id INTEGER, action TEXT, detail TEXT, ip TEXT,
  created_at TEXT DEFAULT (datetime('now','localtime')));
SQL);
    seed_demo($pdo);
}

function seed_demo(PDO $pdo): void {
    $pdo->beginTransaction();
    $plain = demo_password();
    if ($plain === '') { $plain = temp_password(); file_put_contents(DATA_DIR . '/demo_password.txt', $plain . "\n"); } // never stored in the code
    $pw = password_hash($plain, PASSWORD_DEFAULT);
    $u = function (?int $school, string $role, string $name, ?string $email, string $username, string $phone = '') use ($pdo, $pw): int {
        $pdo->prepare('INSERT INTO users(school_id,role,name,email,username,phone,password_hash) VALUES(?,?,?,?,?,?,?)')
            ->execute([$school, $role, $name, $email, strtolower($username), $phone, $pw]);
        return (int)$pdo->lastInsertId();
    };
    $u(null, 'super_admin', 'Platform Administrator', 'superadmin@vidyasetu.test', 'superadmin@vidyasetu.test');

    $pdo->prepare("INSERT INTO schools(name,code,udise,board,address,city,state,pincode,phone,email,principal,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,'active')")
        ->execute(['Demo Public School', 'DPS', 'DEMO-0000001', 'CBSE', '12, Education Road', 'Lucknow', 'Uttar Pradesh', '226001', '0522-4000000', 'admin@dps.test', 'Dr. Meera Sharma']);
    $sid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO schools(name,code,udise,board,city,state,email,principal,status) VALUES('Sunrise Vidyalaya','SUN','DEMO-0000002','State Board','Patna','Bihar','principal@sunrise.test','Mr. R. K. Singh','pending')")->execute();
    $sun = (int)$pdo->lastInsertId();
    $u($sun, 'school_admin', 'Sunrise Admin', 'principal@sunrise.test', 'principal@sunrise.test');
    $u($sid, 'school_admin', 'Dr. Meera Sharma', 'admin@dps.test', 'admin@dps.test', '9800000001');

    $subjects = ['English', 'Hindi', 'Mathematics', 'Science', 'Social Science', 'Computer'];
    $subId = [];
    foreach ($subjects as $i => $s) {
        $pdo->prepare('INSERT INTO subjects(school_id,name,code) VALUES(?,?,?)')->execute([$sid, $s, strtoupper(substr($s, 0, 3))]);
        $subId[$i] = (int)$pdo->lastInsertId();
    }
    $fac = [['Anita Verma', 'anita.verma'], ['Rakesh Kumar', 'rakesh.kumar'], ['Suman Gupta', 'suman.gupta'], ['Vikram Singh', 'vikram.singh'], ['Pooja Mishra', 'pooja.mishra'], ['Imran Khan', 'imran.khan']];
    $facId = [];
    foreach ($fac as $i => [$n, $e]) {
        $facId[$i] = $u($sid, 'faculty', $n, "$e@dps.test", "$e@dps.test", '98000001' . $i . '0');
        $pdo->prepare('INSERT INTO faculty(user_id,school_id,employee_id,designation,qualification) VALUES(?,?,?,?,?)')
            ->execute([$facId[$i], $sid, 'EMP-10' . ($i + 1), $i < 2 ? 'PGT' : 'TGT', $i % 2 ? 'M.Sc., B.Ed.' : 'M.A., B.Ed.']);
    }
    $classes = [['6', 'A'], ['7', 'A'], ['8', 'A']]; $clsId = [];
    foreach ($classes as $i => [$n, $s]) {
        $pdo->prepare('INSERT INTO classes(school_id,name,section,class_teacher_id) VALUES(?,?,?,?)')->execute([$sid, $n, $s, $facId[$i]]);
        $clsId[$i] = (int)$pdo->lastInsertId();
    }
    // Timetable: Mon-Sat, 6 periods; teacher == subject index (no clashes across classes)
    $tt = $pdo->prepare('INSERT INTO timetable(school_id,class_id,day,period,subject_id,faculty_id) VALUES(?,?,?,?,?,?)');
    foreach ($clsId as $ci => $cid) for ($d = 1; $d <= 6; $d++) for ($p = 1; $p <= 6; $p++) {
        $s = ($d + $p + $ci) % 6; $tt->execute([$sid, $cid, $d, $p, $subId[$s], $facId[$s]]);
    }
    // Students & parents
    $first = ['Aarav', 'Vivaan', 'Aditya', 'Ishaan', 'Kabir', 'Reyansh', 'Arjun', 'Rohan', 'Ananya', 'Diya', 'Saanvi', 'Myra', 'Kavya', 'Riya', 'Isha', 'Priya'];
    $last = ['Sharma', 'Verma', 'Gupta', 'Singh', 'Yadav', 'Mishra', 'Khan', 'Patel'];
    $stuIds = []; $n = 0;
    foreach ($clsId as $ci => $cid) for ($r = 1; $r <= 8; $r++) {
        $n++; $name = $first[($n * 3) % 16] . ' ' . $last[($n * 5) % 8];
        $pid = $u($sid, 'parent', 'Parent of ' . $name, "parent$n@example.test", "parent$n@example.test", '97000000' . str_pad((string)$n, 2, '0', STR_PAD_LEFT));
        $adm = $classes[$ci][0] . $classes[$ci][1] . str_pad((string)$r, 2, '0', STR_PAD_LEFT);
        $suid = $u($sid, 'student', $name, null, "DPS-$adm");
        $pdo->prepare('INSERT INTO students(school_id,user_id,class_id,admission_no,roll_no,dob,gender,parent_id,address) VALUES(?,?,?,?,?,?,?,?,?)')
            ->execute([$sid, $suid, $cid, $adm, $r, (2013 - $ci) . '-0' . (1 + $r % 9) . '-1' . ($r % 9), $n % 2 ? 'M' : 'F', $pid, 'Lucknow, Uttar Pradesh']);
        $stuIds[$cid][] = (int)$pdo->lastInsertId();
    }
    // Attendance for the last 14 days (deterministic randomness)
    mt_srand(42);
    $att = $pdo->prepare('INSERT OR IGNORE INTO attendance(school_id,class_id,subject_id,faculty_id,student_id,date,period,status) VALUES(?,?,?,?,?,?,?,?)');
    $slots = $pdo->query('SELECT * FROM timetable')->fetchAll(PDO::FETCH_ASSOC);
    $weak = [3 => true, 11 => true, 19 => true]; // a few students with poor attendance
    for ($back = 14; $back >= 0; $back--) {
        $ts = strtotime("-$back day"); $dow = (int)date('N', $ts); if ($dow > 6) continue; $date = date('Y-m-d', $ts);
        $dayAbsent = [];
        foreach ($slots as $s) {
            if ((int)$s['day'] !== $dow) continue;
            foreach ($stuIds[(int)$s['class_id']] as $stuId) {
                $key = $stuId . $date;
                if (!isset($dayAbsent[$key])) { $dayAbsent[$key] = mt_rand(1, 100) <= (isset($weak[$stuId]) ? 40 : 6); }
                $st = $dayAbsent[$key] ? 'A' : (mt_rand(1, 100) <= 2 ? 'L' : 'P');
                $att->execute([$sid, $s['class_id'], $s['subject_id'], $s['faculty_id'], $stuId, $date, $s['period'], $st]);
            }
        }
    }
    // Homework
    $hw = $pdo->prepare('INSERT INTO homework(school_id,class_id,subject_id,faculty_id,title,description,due_date) VALUES(?,?,?,?,?,?,?)');
    $topics = [['Chapter 4 exercises', 'Solve Q1-Q10 from exercise 4.2 in your notebook.'], ['Essay writing', 'Write a 150-word essay on "My Favourite Festival".'],
        ['Diagram practice', 'Draw and label the diagram discussed in class. Submit neatly.'], ['Reading task', 'Read the next lesson and note down five new words with meanings.']];
    foreach ($clsId as $ci => $cid) foreach ([0, 2, 3] as $k => $si) {
        $t = $topics[($ci + $k) % 4];
        $hw->execute([$sid, $cid, $subId[$si], $facId[$si], $t[0], $t[1], date('Y-m-d', strtotime('+' . (2 + $k) . ' day'))]);
    }
    // Notices
    $nt = $pdo->prepare('INSERT INTO notices(school_id,title,body,audience) VALUES(?,?,?,?)');
    $nt->execute([$sid, 'Parent-Teacher Meeting', 'PTM for all classes will be held on the first Saturday of next month, 10:00 AM to 1:00 PM. Parents are requested to attend.', 'all']);
    $nt->execute([$sid, 'Minimum attendance rule', 'Students must maintain at least 75% attendance to be eligible for the annual examination.', 'all']);
    $nt->execute([null, 'Platform maintenance', 'Scheduled maintenance on Sunday 2:00-4:00 AM. The portal may be briefly unavailable.', 'all']);

    // CMS: blog + careers
    $po = $pdo->prepare('INSERT INTO posts(slug,title,excerpt,body,category) VALUES(?,?,?,?,?)');
    $po->execute(['why-digital-attendance-matters', 'Why digital attendance matters for every school', 'Paper registers hide patterns. Period-wise digital attendance reveals them early.',
        "Most schools still record attendance once a day on paper. That makes it impossible to see which students attend school but skip specific periods, or to alert parents in time.\n\nPeriod-wise digital attendance fixes this. Every teacher marks attendance for their own period, the data is stored centrally, and parents are informed the same day when their child is absent.\n\nSchools using such a system get instant reports for inspections, fewer disputes about attendance percentage, and a clear record that supports the minimum-attendance rule.", 'Attendance']);
    $po->execute(['involving-parents-in-learning', '5 ways to involve parents in everyday learning', 'Timely communication builds trust between school and home.',
        "Parents are the most important partners of a school. Yet communication is often limited to the yearly meeting.\n\n1. Send same-day absence alerts.\n2. Share homework online so parents can see it.\n3. Publish notices in one place.\n4. Give parents a simple dashboard with attendance percentage.\n5. Keep communication respectful and in the language parents understand.", 'Parents']);
    $po->execute(['data-protection-in-schools', 'Protecting student data: a checklist for school leaders', 'Student records are sensitive. Here is a practical checklist.',
        "Schools hold personal data of minors, which deserves the highest care.\n\nCollect only what you need. Give each user a role with the minimum permissions. Use strong passwords and lock accounts after repeated failures. Keep an audit log of important actions. Take regular backups and do not share data with third parties without a lawful purpose.\n\nThe Digital Personal Data Protection Act, 2023 places special obligations around children's data, so build these habits early.", 'Compliance']);
    foreach (require __DIR__ . '/seed_posts.php' as [$slug, $title, $excerpt, $category, $html]) $po->execute([$slug, $title, $excerpt, $html, $category]);
    $jb = $pdo->prepare('INSERT INTO jobs(title,dept,location,type,description) VALUES(?,?,?,?,?)');
    $jb->execute(['Full-stack Developer (PHP / JavaScript)', 'Engineering', 'Lucknow / Remote', 'Full-time', 'Build and maintain school-management features: attendance, homework, reports and parent communication. 2+ years experience with PHP, SQL and modern JavaScript.']);
    $jb->execute(['School Onboarding Executive', 'Operations', 'Pan-India', 'Full-time', 'Help schools get started: data import, teacher training and support. Good communication in Hindi and English.']);
    $jb->execute(['Customer Support Intern', 'Support', 'Remote', 'Internship', 'Answer school queries over phone and e-mail, document common issues and help improve the product.']);
    $pdo->commit();
}
