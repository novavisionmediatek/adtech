<?php
$u = require_login();
$seg = array_values(array_filter(explode('/', trim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/')), 'strlen'));
$type = $seg[1] ?? ''; $id = (int)($seg[2] ?? 0);
$path = null; $name = null;

if ($type === 'homework') {
    $h = row('SELECT * FROM homework WHERE id=?', [$id]);
    if ($h && $h['file_path']) {
        $ok = match ($u['role']) {
            'super_admin' => true,
            'school_admin' => (int)$h['school_id'] === sid(),
            'faculty' => (int)$h['faculty_id'] === (int)$u['id'],
            'student' => (bool)val('SELECT 1 FROM students WHERE user_id=? AND class_id=?', [$u['id'], $h['class_id']]),
            'parent' => (bool)val('SELECT 1 FROM students WHERE parent_id=? AND class_id=?', [$u['id'], $h['class_id']]),
            default => false,
        };
        if ($ok) { $path = $h['file_path']; $name = $h['file_name']; }
    }
} elseif ($type === 'lecture') {
    $l = row("SELECT l.*, i.file_path, i.file_name FROM lecture_items i JOIN lectures l ON l.id=i.lecture_id WHERE i.id=? AND i.kind='file'", [$id]);
    if ($l && $l['file_path']) {
        $ok = match ($u['role']) {
            'super_admin' => true,
            'school_admin' => (int)$l['school_id'] === sid(),
            'faculty' => (int)$l['faculty_id'] === (int)$u['id'],
            'student' => (bool)val('SELECT 1 FROM students WHERE user_id=? AND class_id=?', [$u['id'], $l['class_id']]),
            'parent' => (bool)val('SELECT 1 FROM students WHERE parent_id=? AND class_id=?', [$u['id'], $l['class_id']]),
            default => false,
        };
        if ($ok) { $path = $l['file_path']; $name = $l['file_name']; $lecture = true; }
    }
} elseif ($type === 'submission') {
    $f = row('SELECT f.file_path, f.file_name, h.school_id, h.faculty_id, st.user_id, st.parent_id FROM submission_files f JOIN homework_submissions s ON s.id=f.submission_id JOIN homework h ON h.id=s.homework_id JOIN students st ON st.id=s.student_id WHERE f.id=?', [$id]);
    if ($f) {
        $ok = match ($u['role']) { 'super_admin' => true, 'school_admin' => (int)$f['school_id'] === sid(), 'faculty' => (int)$f['faculty_id'] === (int)$u['id'], 'student' => (int)$f['user_id'] === (int)$u['id'], 'parent' => (int)$f['parent_id'] === (int)$u['id'], default => false };
        if ($ok) { $path = $f['file_path']; $name = $f['file_name']; }
    }
} elseif ($type === 'resume' && $u['role'] === 'super_admin') {
    $a = row('SELECT * FROM applications WHERE id=?', [$id]);
    if ($a && $a['file_path']) { $path = $a['file_path']; $name = $a['file_name']; }
}
$full = $path ? realpath(UPLOAD_DIR . '/' . $path) : false;
if (!$full || !str_starts_with($full, realpath(UPLOAD_DIR)) || !is_file($full)) render_error(404, 'File not found', 'The file does not exist or you do not have access.');
$size = filesize($full);
$fname = str_replace('"', '', (string)$name);
if (!empty($lecture)) {
    // Lectures: stream inline (video/audio/PDF) with Range support so seeking works. Chunks are capped so the
    // single-threaded dev server is never blocked for long by one open-ended video request.
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $types = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4', 'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
    $inline = isset($types[$ext]) && empty($_GET['dl']);
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $fname . '"');
    header('Accept-Ranges: bytes'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, max-age=3600');
    $start = 0; $end = $size - 1;
    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m) && ($m[1] !== '' || $m[2] !== '')) {
        if ($m[1] === '') { $start = max(0, $size - (int)$m[2]); } else { $start = (int)$m[1]; if ($m[2] !== '') $end = min($end, (int)$m[2]); else $end = min($end, $start + 4 * 1048576 - 1); }
        if ($start > $end || $start >= $size) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
        http_response_code(206); header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Length: ' . ($end - $start + 1));
    $fh = fopen($full, 'rb'); fseek($fh, $start); $left = $end - $start + 1;
    while ($left > 0 && !feof($fh) && !connection_aborted()) { $chunk = fread($fh, min(262144, $left)); echo $chunk; $left -= strlen($chunk); flush(); }
    fclose($fh); exit;
}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Content-Length: ' . $size);
readfile($full);
exit;
