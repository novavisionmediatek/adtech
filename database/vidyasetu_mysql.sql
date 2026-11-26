-- =====================================================================
--  VidyaSetu - School Management Portal
--  MySQL 8.0+ / MariaDB 10.5+ schema  (InnoDB, utf8mb4)
--  Import: phpMyAdmin > select YOUR database (left side) > Import. Works on shared hosting
--          (no CREATE DATABASE / USE needed). CLI: mysql -u user -p dbname < vidyasetu_mysql.sql
--
--  NOTE: the app uses SQLite by default; set 'db' => driver mysql in config.php
--  to use this schema (see README, section "Using MySQL").
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------- schools
CREATE TABLE schools (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(200) NOT NULL,
  code        VARCHAR(20)  NOT NULL UNIQUE,              -- used in student login id, e.g. DPS
  udise       VARCHAR(20)  NULL,
  board       VARCHAR(30)  NULL,
  address     VARCHAR(255) NULL,
  city        VARCHAR(100) NULL,
  state       VARCHAR(100) NULL,
  pincode     VARCHAR(10)  NULL,
  phone       VARCHAR(20)  NULL,
  email       VARCHAR(150) NULL,
  principal   VARCHAR(150) NULL,
  status      ENUM('pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------------ users
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id     INT UNSIGNED NULL,                        -- NULL for super admin
  role          ENUM('super_admin','school_admin','faculty','student','parent') NOT NULL,
  name          VARCHAR(150) NOT NULL,
  email         VARCHAR(150) NULL,
  username      VARCHAR(60)  NULL,                        -- students log in with SCHOOLCODE-ADMNO
  phone         VARCHAR(20)  NULL,
  password_hash VARCHAR(255) NOT NULL,                    -- bcrypt
  active        TINYINT(1) NOT NULL DEFAULT 1,
  must_change   TINYINT(1) NOT NULL DEFAULT 0,            -- force password change on first login
  failed        SMALLINT UNSIGNED NOT NULL DEFAULT 0,     -- failed login counter
  locked_until  INT UNSIGNED NOT NULL DEFAULT 0,          -- unix time, lockout after 5 failures
  last_login    DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_username (username),
  KEY ix_users_school_role (school_id, role),
  CONSTRAINT fk_users_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE faculty (
  user_id       INT UNSIGNED PRIMARY KEY,
  school_id     INT UNSIGNED NOT NULL,
  employee_id   VARCHAR(40)  NULL,
  designation   VARCHAR(100) NULL,
  qualification VARCHAR(150) NULL,
  CONSTRAINT fk_faculty_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------- classes & subjects
CREATE TABLE classes (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id        INT UNSIGNED NOT NULL,
  name             VARCHAR(30) NOT NULL,                  -- "6", "10", "Nursery"
  section          VARCHAR(10) NOT NULL DEFAULT '',       -- "A", "B"
  class_teacher_id INT UNSIGNED NULL,
  UNIQUE KEY uq_class (school_id, name, section),
  CONSTRAINT fk_classes_school  FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_classes_teacher FOREIGN KEY (class_teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE subjects (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  name      VARCHAR(100) NOT NULL,
  code      VARCHAR(20)  NOT NULL DEFAULT '',
  UNIQUE KEY uq_subject (school_id, name),
  CONSTRAINT fk_subjects_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------------- students
CREATE TABLE students (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id    INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  class_id     INT UNSIGNED NOT NULL,
  admission_no VARCHAR(40) NOT NULL,
  roll_no      INT NOT NULL DEFAULT 0,
  dob          DATE NULL,
  gender       VARCHAR(10) NULL,
  parent_id    INT UNSIGNED NULL,                         -- parent login (one parent, many children)
  address      VARCHAR(255) NULL,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_admission (school_id, admission_no),
  KEY ix_students_class (class_id),
  KEY ix_students_parent (parent_id),
  CONSTRAINT fk_students_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_students_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  CONSTRAINT fk_students_class  FOREIGN KEY (class_id)  REFERENCES classes(id),
  CONSTRAINT fk_students_parent FOREIGN KEY (parent_id) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------------- timetable
CREATE TABLE timetable (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id  INT UNSIGNED NOT NULL,
  class_id   INT UNSIGNED NOT NULL,
  day        TINYINT UNSIGNED NOT NULL,                   -- 1=Mon ... 6=Sat
  period     TINYINT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  faculty_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_slot (class_id, day, period),
  KEY ix_tt_faculty (faculty_id, day, period),            -- fast "my periods today" + clash check
  CONSTRAINT fk_tt_class   FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
  CONSTRAINT fk_tt_subject FOREIGN KEY (subject_id) REFERENCES subjects(id),
  CONSTRAINT fk_tt_faculty FOREIGN KEY (faculty_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------- attendance
CREATE TABLE attendance (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id  INT UNSIGNED NOT NULL,
  class_id   INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  faculty_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  date       DATE NOT NULL,
  period     TINYINT UNSIGNED NOT NULL,
  status     ENUM('P','A','L') NOT NULL,                  -- Present / Absent / Late
  marked_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_att (student_id, date, period),
  KEY ix_att_class_date (class_id, date),
  KEY ix_att_school_date (school_id, date),
  CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- prevents sending the same absence e-mail twice (per student/date/period or day)
CREATE TABLE absence_alerts (
  student_id INT UNSIGNED NOT NULL,
  date       DATE NOT NULL,
  period_key INT NOT NULL,                                -- period number, or 0 for "daily" mode
  PRIMARY KEY (student_id, date, period_key),
  CONSTRAINT fk_alert_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------------- homework
CREATE TABLE homework (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id   INT UNSIGNED NOT NULL,
  class_id    INT UNSIGNED NOT NULL,
  subject_id  INT UNSIGNED NOT NULL,
  faculty_id  INT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  description TEXT NULL,
  due_date    DATE NULL,
  file_name   VARCHAR(255) NULL,
  file_path   VARCHAR(255) NULL,                          -- relative to data/uploads (kept outside web root)
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_hw_class (class_id, due_date),
  KEY ix_hw_faculty (faculty_id),
  CONSTRAINT fk_hw_class   FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
  CONSTRAINT fk_hw_subject FOREIGN KEY (subject_id) REFERENCES subjects(id),
  CONSTRAINT fk_hw_faculty FOREIGN KEY (faculty_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- who has completed which homework (no row = not checked yet)
CREATE TABLE homework_status (
  homework_id INT UNSIGNED NOT NULL,
  student_id  INT UNSIGNED NOT NULL,
  status      VARCHAR(10) NOT NULL,                       -- done / late / missing / pending (note only)
  remark      VARCHAR(255) NULL,
  checked_by  INT UNSIGNED NULL,
  updated_at  DATETIME NOT NULL,
  PRIMARY KEY (homework_id, student_id),
  KEY ix_hws_student (student_id)
) ENGINE=InnoDB;

-- holidays, faculty leave and substitute teachers
CREATE TABLE holidays (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, school_id INT UNSIGNED NOT NULL, class_id INT UNSIGNED NULL,
  title VARCHAR(150) NOT NULL, from_date DATE NOT NULL, to_date DATE NOT NULL, created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL
) ENGINE=InnoDB;
CREATE TABLE faculty_leave (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, school_id INT UNSIGNED NOT NULL, faculty_id INT UNSIGNED NOT NULL,
  from_date DATE NOT NULL, to_date DATE NOT NULL, type VARCHAR(40) NOT NULL, note VARCHAR(255) NULL, created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL
) ENGINE=InnoDB;
CREATE TABLE substitutions (
  school_id INT UNSIGNED NOT NULL, date DATE NOT NULL, timetable_id INT UNSIGNED NOT NULL, faculty_id INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NULL, PRIMARY KEY (date, timetable_id)
) ENGINE=InnoDB;

-- faculty (staff) attendance
CREATE TABLE faculty_attendance (
  school_id INT UNSIGNED NOT NULL, faculty_id INT UNSIGNED NOT NULL, date DATE NOT NULL, status VARCHAR(2) NOT NULL,
  check_in VARCHAR(5) NULL, note VARCHAR(255) NULL, marked_by INT UNSIGNED NULL, updated_at DATETIME NOT NULL,
  PRIMARY KEY (faculty_id, date)
) ENGINE=InnoDB;

-- --------------------------------------------------------------- lectures
CREATE TABLE lectures (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id    INT UNSIGNED NOT NULL,
  class_id     INT UNSIGNED NOT NULL,
  subject_id   INT UNSIGNED NOT NULL,
  faculty_id   INT UNSIGNED NOT NULL,
  title        VARCHAR(150) NOT NULL,
  description  TEXT NULL,
  lecture_date DATE NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_lec_class (class_id, created_at),
  KEY ix_lec_faculty (faculty_id),
  CONSTRAINT fk_lec_class   FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
  CONSTRAINT fk_lec_subject FOREIGN KEY (subject_id) REFERENCES subjects(id),
  CONSTRAINT fk_lec_faculty FOREIGN KEY (faculty_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- one lecture -> many files and many links
CREATE TABLE lecture_items (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lecture_id INT UNSIGNED NOT NULL,
  kind       ENUM('file','link') NOT NULL,
  file_name  VARCHAR(255) NULL,                           -- original name (kind = file)
  file_path  VARCHAR(255) NULL,                           -- stored name under data/uploads/lectures
  url        VARCHAR(1000) NULL,                          -- kind = link (YouTube, Drive, ...)
  KEY ix_items_lecture (lecture_id),
  CONSTRAINT fk_items_lecture FOREIGN KEY (lecture_id) REFERENCES lectures(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------- notices & e-mail log
CREATE TABLE notices (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id  INT UNSIGNED NULL,                           -- NULL = platform-wide notice
  title      VARCHAR(200) NOT NULL,
  body       TEXT NULL,
  audience   VARCHAR(20) NOT NULL DEFAULT 'all',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_notices_school (school_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE outbox (                                     -- every e-mail the system sends or logs
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id  INT UNSIGNED NULL,
  to_email   VARCHAR(150) NOT NULL,
  to_name    VARCHAR(150) NULL,
  subject    VARCHAR(255) NOT NULL,
  body       MEDIUMTEXT NULL,
  status     VARCHAR(20) NOT NULL DEFAULT 'queued',       -- logged / sent / failed
  error      VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at    DATETIME NULL,
  KEY ix_outbox_school (school_id, created_at),
  KEY ix_outbox_to (to_email)
) ENGINE=InnoDB;

-- ------------------------------------------------ public website content
CREATE TABLE posts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug       VARCHAR(160) NOT NULL UNIQUE,
  title      VARCHAR(200) NOT NULL,
  excerpt    VARCHAR(1000) NULL,
  body       MEDIUMTEXT NULL,
  category   VARCHAR(60) NULL,
  published  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE jobs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(200) NOT NULL,
  dept        VARCHAR(100) NULL,
  location    VARCHAR(100) NULL,
  type        VARCHAR(40)  NULL,
  description TEXT NULL,
  active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE applications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id     INT UNSIGNED NOT NULL,
  name       VARCHAR(150) NOT NULL,
  email      VARCHAR(150) NOT NULL,
  phone      VARCHAR(20)  NULL,
  message    TEXT NULL,
  file_name  VARCHAR(255) NULL,
  file_path  VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_app_job (job_id),
  CONSTRAINT fk_app_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE messages (                                   -- contact-form submissions
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(150) NOT NULL,
  email      VARCHAR(150) NOT NULL,
  phone      VARCHAR(20)  NULL,
  subject    VARCHAR(200) NULL,
  message    TEXT NOT NULL,
  handled    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------------- audit log
CREATE TABLE audit (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  school_id  INT UNSIGNED NULL,
  action     VARCHAR(60) NOT NULL,
  detail     VARCHAR(500) NULL,
  ip         VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_audit_school (school_id, created_at),
  KEY ix_audit_user (user_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  Password reset, exams, leave requests, homework submissions, alerts,
--  fees, admissions, certificates, library and transport.
--  (The portal also creates these by itself if they are missing.)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_meta(k VARCHAR(40) NOT NULL PRIMARY KEY, v VARCHAR(100) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS throttle(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, bucket VARCHAR(190) NOT NULL, at_time INT UNSIGNED NOT NULL, KEY throttle_b (bucket, at_time)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS password_resets(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL, expires_at INT UNSIGNED NOT NULL, used INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, KEY reset_token (token_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS user_prefs(user_id INT UNSIGNED NOT NULL PRIMARY KEY, sms INT NOT NULL DEFAULT 0, whatsapp INT NOT NULL DEFAULT 0, lang VARCHAR(5) NOT NULL DEFAULT 'en') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS msg_log(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, school_id INT UNSIGNED NULL, channel VARCHAR(10) NOT NULL, phone VARCHAR(20) NULL, message VARCHAR(700) NOT NULL, status VARCHAR(10) NOT NULL, error VARCHAR(255) NULL, created_at DATETIME NOT NULL, KEY msg_school (school_id, id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS exams(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, school_id INT UNSIGNED NOT NULL, name VARCHAR(150) NOT NULL, from_date DATE NOT NULL, to_date DATE NOT NULL, pass_pct INT NOT NULL DEFAULT 33, published INT NOT NULL DEFAULT 0, created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL, KEY exam_school (school_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS exam_papers(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, exam_id INT UNSIGNED NOT NULL, class_id INT UNSIGNED NOT NULL, subject_id INT UNSIGNED NOT NULL, max_marks INT NOT NULL, exam_date DATE NULL, UNIQUE (exam_id, class_id, subject_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS exam_marks(paper_id INT UNSIGNED NOT NULL, student_id INT UNSIGNED NOT NULL, marks DECIMAL(6,2) NULL, absent INT NOT NULL DEFAULT 0, updated_by INT UNSIGNED NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (paper_id, student_id), KEY marks_student (student_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS leave_requests(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, school_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, kind VARCHAR(10) NOT NULL, student_id INT UNSIGNED NULL, from_date DATE NOT NULL, to_date DATE NOT NULL, type VARCHAR(40) NOT NULL, reason VARCHAR(500) NOT NULL, status VARCHAR(10) NOT NULL DEFAULT 'pending', decided_by INT UNSIGNED NULL, decided_note VARCHAR(255) NULL, decided_at DATETIME NULL, leave_id INT UNSIGNED NULL, created_at DATETIME NOT NULL, KEY req_school (school_id, status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS homework_submissions(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, homework_id INT UNSIGNED NOT NULL, student_id INT UNSIGNED NOT NULL, note VARCHAR(1000) NULL, submitted_at DATETIME NOT NULL, UNIQUE (homework_id, student_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS submission_files(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, submission_id INT UNSIGNED NOT NULL, file_name VARCHAR(255) NOT NULL, file_path VARCHAR(255) NOT NULL, KEY subfile_sub (submission_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS fee_dues(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, school_id INT UNSIGNED NOT NULL, student_id INT UNSIGNED NOT NULL, title VARCHAR(150) NOT NULL, amount DECIMAL(10,2) NOT NULL, due_date DATE NOT NULL, created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL, KEY due_school (school_id), KEY due_student (student_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS fee_payments(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, school_id INT UNSIGNED NOT NULL, due_id INT UNSIGNED NOT NULL, amount DECIMAL(10,2) NOT NULL, mode VARCHAR(20) NOT NULL, reference VARCHAR(100) NULL, paid_on DATE NOT NULL, receipt_no VARCHAR(40) NULL, recorded_by INT UNSIGNED NULL, created_at DATETIME NOT NULL, cancelled INT NOT NULL DEFAULT 0, cancel_reason VARCHAR(255) NULL, KEY pay_due (due_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS admissions(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, school_id INT UNSIGNED NOT NULL, ref_no VARCHAR(40) NULL, student_name VARCHAR(150) NOT NULL, dob DATE NULL, gender VARCHAR(1) NULL, class_sought VARCHAR(40) NOT NULL, parent_name VARCHAR(150) NOT NULL, parent_email VARCHAR(150) NOT NULL, parent_phone VARCHAR(20) NOT NULL, address VARCHAR(255) NULL, previous_school VARCHAR(200) NULL, status VARCHAR(10) NOT NULL DEFAULT 'new', note VARCHAR(255) NULL, student_id INT UNSIGNED NULL, created_at DATETIME NOT NULL, decided_at DATETIME NULL, decided_by INT UNSIGNED NULL, KEY adm_school (school_id, status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS certificates(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, school_id INT UNSIGNED NOT NULL, student_id INT UNSIGNED NOT NULL, type VARCHAR(20) NOT NULL, serial_no VARCHAR(60) NULL, issued_on DATE NOT NULL, details TEXT NOT NULL, issued_by INT UNSIGNED NULL, revoked INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, KEY cert_school (school_id), KEY cert_student (student_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS library_books(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, school_id INT UNSIGNED NOT NULL, title VARCHAR(200) NOT NULL, author VARCHAR(150) NULL, code VARCHAR(40) NULL, copies INT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, KEY book_school (school_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS library_loans(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, school_id INT UNSIGNED NOT NULL, book_id INT UNSIGNED NOT NULL, student_id INT UNSIGNED NOT NULL, issued_on DATE NOT NULL, due_on DATE NOT NULL, returned_on DATE NULL, created_by INT UNSIGNED NULL, KEY loan_school (school_id), KEY loan_student (student_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS transport_routes(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, school_id INT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, vehicle_no VARCHAR(30) NULL, driver_name VARCHAR(120) NULL, driver_phone VARCHAR(20) NULL, stops VARCHAR(700) NULL, created_at DATETIME NOT NULL, KEY route_school (school_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS transport_students(student_id INT UNSIGNED NOT NULL PRIMARY KEY, school_id INT UNSIGNED NOT NULL, route_id INT UNSIGNED NOT NULL, stop VARCHAR(120) NULL, pickup_time VARCHAR(10) NULL, KEY tstu_route (route_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
--  Starter data: one super admin so you can log in.
--  Before importing, replace PUT_PASSWORD_HASH_HERE with the hash of YOUR
--  password. Make one with:
--      php -r "echo password_hash('your-own-password', PASSWORD_DEFAULT);"
--  Then log in as superadmin@vidyasetu.test. Unchanged, nobody can log in.
--  Never commit a real hash to Git.
-- ---------------------------------------------------------------------
INSERT INTO users (school_id, role, name, email, username, password_hash, must_change)
VALUES (NULL, 'super_admin', 'Super Admin', 'superadmin@vidyasetu.test', 'superadmin',
        'PUT_PASSWORD_HASH_HERE', 1);

-- ---------------------------------------------------------------------
--  Handy report queries
-- ---------------------------------------------------------------------
-- Attendance % per student for a class (replace 1 with the class id):
--   SELECT u.name, ROUND(100*SUM(a.status IN ('P','L'))/COUNT(*),1) AS pct
--   FROM attendance a JOIN students s ON s.id=a.student_id JOIN users u ON u.id=s.user_id
--   WHERE a.class_id=1 GROUP BY s.id ORDER BY pct;
-- Students absent on a date (to e-mail parents):
--   SELECT u.name, p.email FROM attendance a JOIN students s ON s.id=a.student_id
--   JOIN users u ON u.id=s.user_id JOIN users p ON p.id=s.parent_id
--   WHERE a.date='2026-11-16' AND a.status='A' GROUP BY s.id;
