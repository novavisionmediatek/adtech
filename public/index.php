<?php
declare(strict_types=1);

$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (PHP_SAPI === 'cli-server') {
    $f = realpath(__DIR__ . $path);
    if ($path !== '/' && $f && str_starts_with($f, __DIR__) && is_file($f) && !str_ends_with($f, '.php')) return false; // static assets
}
require __DIR__ . '/../app/bootstrap.php';
verify_csrf();

$seg = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
$first = $seg[0] ?? '';
$roleMap = ['super' => 'super_admin', 'admin' => 'school_admin', 'faculty' => 'faculty', 'student' => 'student', 'parent' => 'parent'];

if (isset($roleMap[$first])) {
    require_role($roleMap[$first]);
    $page = $seg[1] ?? 'dashboard';
    $file = APP . "/pages/$first/$page.php";
    if (!preg_match('/^[a-z_]+$/', $page) || !is_file($file)) render_error(404, 'Page not found', 'The page you are looking for does not exist.');
    require $file; exit;
}
if ($first === 'account') {
    require_login();
    $page = $seg[1] ?? 'password';
    $file = APP . "/pages/account/$page.php";
    if (!preg_match('/^[a-z_]+$/', $page) || !is_file($file)) render_error(404, 'Page not found', 'The page you are looking for does not exist.');
    require $file; exit;
}
if ($first === 'file') { require APP . '/pages/file.php'; exit; }
if ($first === 'print') { require APP . '/pages/print.php'; exit; }
if ($first === 'lang') { // interface language: English or Hindi
    setcookie('vs_lang', inp('to') === 'hi' ? 'hi' : 'en', ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true]);
    $back = inp('back', '/'); if (!preg_match('#^/(?![/\\\\])[^\\r\\n]*$#', $back)) $back = '/';
    header('Location: ' . $back); exit;
}
if ($first === 'blogimg') { // public blog images (stored outside the web root, name must be our random hex)
    $n = $seg[1] ?? ''; $f = UPLOAD_DIR . '/blog/' . $n;
    if (!preg_match('/^[a-f0-9]{24}\.(jpe?g|png|gif|webp)$/', $n) || !is_file($f)) render_error(404, 'Image not found', 'This image does not exist.');
    $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    header('Content-Type: ' . $types[pathinfo($n, PATHINFO_EXTENSION)]); header('Cache-Control: public, max-age=604800'); header('Content-Length: ' . filesize($f));
    readfile($f); exit;
}
if ($first === 'logout') { $_SESSION = []; session_destroy(); session_start(); flash('You have been logged out.'); redirect('login'); }

$public = ['' => 'home', 'about' => 'about', 'features' => 'features', 'compliance' => 'compliance', 'contact' => 'contact', 'blog' => 'blog',
    'careers' => 'careers', 'login' => 'login', 'signup' => 'signup', 'privacy' => 'privacy', 'forgot' => 'forgot', 'reset' => 'reset', 'admission' => 'admission'];
if (isset($public[$first])) { require APP . '/pages/public/' . $public[$first] . '.php'; exit; }
render_error(404, 'Page not found', 'The page you are looking for does not exist.');
