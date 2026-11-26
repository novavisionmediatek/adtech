<?php
declare(strict_types=1);
/*
 * Tables for: password reset, exams, leave requests, homework submissions, alerts, fees, admissions,
 * certificates, library and transport. Created automatically (SQLite and MySQL) - no manual upgrade needed.
 */
const SCHEMA_VERSION = '2';

/** SQL statements that create the extra tables in the chosen database dialect. */
function extra_schema(bool $mysql): array {
    $id = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $n = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    // table => [columns, [index name => columns]]
    $t = [
        'app_meta' => ["k VARCHAR(40) NOT NULL PRIMARY KEY, v VARCHAR(100) NOT NULL", []],
        'throttle' => ["id $id, bucket VARCHAR(190) NOT NULL, at_time $n NOT NULL", ['throttle_b' => 'bucket, at_time']],
        'password_resets' => ["id $id, user_id $n NOT NULL, token_hash CHAR(64) NOT NULL, expires_at $n NOT NULL, used INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL", ['reset_token' => 'token_hash']],
        'user_prefs' => ["user_id $n NOT NULL PRIMARY KEY, sms INT NOT NULL DEFAULT 0, whatsapp INT NOT NULL DEFAULT 0, lang VARCHAR(5) NOT NULL DEFAULT 'en'", []],
        'msg_log' => ["id $id, school_id $n NULL, channel VARCHAR(10) NOT NULL, phone VARCHAR(20) NULL, message VARCHAR(700) NOT NULL, status VARCHAR(10) NOT NULL, error VARCHAR(255) NULL, created_at DATETIME NOT NULL", ['msg_school' => 'school_id, id']],
        'exams' => ["id $id, school_id $n NOT NULL, name VARCHAR(150) NOT NULL, from_date DATE NOT NULL, to_date DATE NOT NULL, pass_pct INT NOT NULL DEFAULT 33, published INT NOT NULL DEFAULT 0, created_by $n NULL, created_at DATETIME NOT NULL", ['exam_school' => 'school_id']],
        'exam_papers' => ["id $id, exam_id $n NOT NULL, class_id $n NOT NULL, subject_id $n NOT NULL, max_marks INT NOT NULL, exam_date DATE NULL, UNIQUE (exam_id, class_id, subject_id)", []],
        'exam_marks' => ["paper_id $n NOT NULL, student_id $n NOT NULL, marks DECIMAL(6,2) NULL, absent INT NOT NULL DEFAULT 0, updated_by $n NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (paper_id, student_id)", ['marks_student' => 'student_id']],
        'leave_requests' => ["id $id, school_id $n NOT NULL, user_id $n NOT NULL, kind VARCHAR(10) NOT NULL, student_id $n NULL, from_date DATE NOT NULL, to_date DATE NOT NULL, type VARCHAR(40) NOT NULL, reason VARCHAR(500) NOT NULL, status VARCHAR(10) NOT NULL DEFAULT 'pending', decided_by $n NULL, decided_note VARCHAR(255) NULL, decided_at DATETIME NULL, leave_id $n NULL, created_at DATETIME NOT NULL", ['req_school' => 'school_id, status']],
        'homework_submissions' => ["id $id, homework_id $n NOT NULL, student_id $n NOT NULL, note VARCHAR(1000) NULL, submitted_at DATETIME NOT NULL, UNIQUE (homework_id, student_id)", []],
        'submission_files' => ["id $id, submission_id $n NOT NULL, file_name VARCHAR(255) NOT NULL, file_path VARCHAR(255) NOT NULL", ['subfile_sub' => 'submission_id']],
        'fee_dues' => ["id $id, school_id $n NOT NULL, student_id $n NOT NULL, title VARCHAR(150) NOT NULL, amount DECIMAL(10,2) NOT NULL, due_date DATE NOT NULL, created_by $n NULL, created_at DATETIME NOT NULL", ['due_school' => 'school_id', 'due_student' => 'student_id']],
        'fee_payments' => ["id $id, school_id $n NOT NULL, due_id $n NOT NULL, amount DECIMAL(10,2) NOT NULL, mode VARCHAR(20) NOT NULL, reference VARCHAR(100) NULL, paid_on DATE NOT NULL, receipt_no VARCHAR(40) NULL, recorded_by $n NULL, created_at DATETIME NOT NULL, cancelled INT NOT NULL DEFAULT 0, cancel_reason VARCHAR(255) NULL", ['pay_due' => 'due_id']],
        'admissions' => ["id $id, school_id $n NOT NULL, ref_no VARCHAR(40) NULL, student_name VARCHAR(150) NOT NULL, dob DATE NULL, gender VARCHAR(1) NULL, class_sought VARCHAR(40) NOT NULL, parent_name VARCHAR(150) NOT NULL, parent_email VARCHAR(150) NOT NULL, parent_phone VARCHAR(20) NOT NULL, address VARCHAR(255) NULL, previous_school VARCHAR(200) NULL, status VARCHAR(10) NOT NULL DEFAULT 'new', note VARCHAR(255) NULL, student_id $n NULL, created_at DATETIME NOT NULL, decided_at DATETIME NULL, decided_by $n NULL", ['adm_school' => 'school_id, status']],
        'certificates' => ["id $id, school_id $n NOT NULL, student_id $n NOT NULL, type VARCHAR(20) NOT NULL, serial_no VARCHAR(60) NULL, issued_on DATE NOT NULL, details TEXT NOT NULL, issued_by $n NULL, revoked INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL", ['cert_school' => 'school_id', 'cert_student' => 'student_id']],
        'library_books' => ["id $id, school_id $n NOT NULL, title VARCHAR(200) NOT NULL, author VARCHAR(150) NULL, code VARCHAR(40) NULL, copies INT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL", ['book_school' => 'school_id']],
        'library_loans' => ["id $id, school_id $n NOT NULL, book_id $n NOT NULL, student_id $n NOT NULL, issued_on DATE NOT NULL, due_on DATE NOT NULL, returned_on DATE NULL, created_by $n NULL", ['loan_school' => 'school_id', 'loan_student' => 'student_id']],
        'transport_routes' => ["id $id, school_id $n NOT NULL, name VARCHAR(120) NOT NULL, vehicle_no VARCHAR(30) NULL, driver_name VARCHAR(120) NULL, driver_phone VARCHAR(20) NULL, stops VARCHAR(700) NULL, created_at DATETIME NOT NULL", ['route_school' => 'school_id']],
        'transport_students' => ["student_id $n NOT NULL PRIMARY KEY, school_id $n NOT NULL, route_id $n NOT NULL, stop VARCHAR(120) NULL, pickup_time VARCHAR(10) NULL", ['tstu_route' => 'route_id']],
    ];
    $out = [];
    foreach ($t as $name => [$cols, $indexes]) {
        if ($mysql) {
            foreach ($indexes as $ix => $on) $cols .= ", KEY $ix ($on)";
            $out[] = "CREATE TABLE IF NOT EXISTS $name($cols) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        } else {
            $out[] = "CREATE TABLE IF NOT EXISTS $name($cols)";
            foreach ($indexes as $ix => $on) $out[] = "CREATE INDEX IF NOT EXISTS $ix ON $name($on)";
        }
    }
    return $out;
}
function extra_tables(): array { return array_values(array_filter(array_map(fn($s) => preg_match('/^CREATE TABLE IF NOT EXISTS (\w+)/', $s, $m) ? $m[1] : null, extra_schema(false)))); }

/** Runs once per schema version. */
function ensure_extra_tables(PDO $pdo, bool $mysql): void {
    try { $v = $pdo->query("SELECT v FROM app_meta WHERE k='schema'")->fetchColumn(); } catch (PDOException) { $v = false; }
    if ($v === SCHEMA_VERSION) return;
    foreach (extra_schema($mysql) as $sql) $pdo->exec($sql);
    $pdo->exec("DELETE FROM app_meta WHERE k='schema'");
    $pdo->prepare("INSERT INTO app_meta(k,v) VALUES('schema',?)")->execute([SCHEMA_VERSION]);
    if (cfg('demo_mode')) { require_once APP . '/seed_extra.php'; seed_extra($pdo); }
}
