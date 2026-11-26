<?php
// Image upload for the blog editor (super admin only; CSRF is checked by the router for every POST).
header('Content-Type: application/json');
try {
    if (!is_post()) throw new RuntimeException('POST only.');
    $f = save_upload('image', 'blog', ['jpg', 'jpeg', 'png', 'gif', 'webp'], 5 * 1048576);
    if (!$f) throw new RuntimeException('No image received.');
    if (!@getimagesize(UPLOAD_DIR . '/' . $f['path'])) { @unlink(UPLOAD_DIR . '/' . $f['path']); throw new RuntimeException('That file is not a valid image.'); }
    echo json_encode(['url' => '/blogimg/' . basename($f['path'])], JSON_UNESCAPED_SLASHES);
} catch (RuntimeException $x) { http_response_code(400); echo json_encode(['error' => $x->getMessage()]); }
