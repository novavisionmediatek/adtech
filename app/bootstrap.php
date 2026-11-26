<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);
define('DATA_DIR', ROOT . '/data');
define('UPLOAD_DIR', DATA_DIR . '/uploads');
define('DB_FILE', DATA_DIR . '/vidyasetu.sqlite');

$GLOBALS['config'] = require ROOT . '/config.php';
require APP . '/lib.php';
require APP . '/mail.php';
require APP . '/layout.php';
require APP . '/views.php';
require APP . '/icons.php';
require APP . '/homework_track.php';
require APP . '/leave.php';
require APP . '/staff_attendance.php';
require APP . '/pager.php';
require APP . '/class_log.php';
foreach (['schema_extra', 'security', 'backup', 'alerts', 'exams', 'requests', 'submissions', 'fees', 'office', 'campus', 'i18n'] as $m) require APP . "/$m.php";
date_default_timezone_set(cfg('timezone', 'Asia/Kolkata'));

if (!is_dir(DATA_DIR)) { mkdir(DATA_DIR, 0775, true); }
foreach (['homework', 'resumes', 'lectures', 'blog', 'submissions'] as $d) { if (!is_dir(UPLOAD_DIR . '/' . $d)) { mkdir(UPLOAD_DIR . '/' . $d, 0775, true); } }

session_name('vsid');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
session_start();

auto_backup();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'; frame-ancestors 'none'");
