<?php
$u = me();
$pairs = q('SELECT DISTINCT t.class_id, t.subject_id, c.name cname, c.section, sub.name sname FROM timetable t JOIN classes c ON c.id=t.class_id JOIN subjects sub ON sub.id=t.subject_id
   WHERE t.faculty_id=? ORDER BY (c.name+0), c.section, sub.name', [$u['id']]);
if (is_post()) {
    try {
        if (post('action') === 'delete') {
            $l = row('SELECT * FROM lectures WHERE id=? AND faculty_id=?', [(int)post('id'), $u['id']]);
            if ($l) { delete_lecture($l); flash('Lecture deleted.'); }
        } else {
            if (!empty($_SERVER['CONTENT_LENGTH']) && empty($_POST) && empty($_FILES)) throw new RuntimeException('Upload too large for the server limit (max 300 MB).');
            [$cid, $sid2] = array_map('intval', explode('-', post('pair') . '-0'));
            $ok = false; foreach ($pairs as $p) if ((int)$p['class_id'] === $cid && (int)$p['subject_id'] === $sid2) $ok = true;
            if (!$ok) throw new RuntimeException('Choose one of your classes/subjects.');
            if (post('title') === '') throw new RuntimeException('Title is required.');
            $date = post('date') ?: date('Y-m-d'); if (!valid_date($date)) throw new RuntimeException('Invalid lecture date.');
            $links = [];
            foreach ((array)($_POST['links'] ?? []) as $lk) {
                $lk = trim((string)$lk); if ($lk === '') continue;
                if (!preg_match('#^https?://[^\s]+$#i', $lk)) throw new RuntimeException('Link "' . mb_strimwidth($lk, 0, 40, '...') . '" must start with http:// or https://');
                $links[] = $lk;
            }
            $files = save_uploads('files', 'lectures', ['mp4', 'webm', 'ogg', 'mp3', 'wav', 'm4a', 'pdf', 'ppt', 'pptx', 'doc', 'docx', 'jpg', 'jpeg', 'png'], 300 * 1048576);
            if (!$files && !$links) throw new RuntimeException('Add at least one file or link (YouTube, Google Drive, etc.).');
            $lid = ins('INSERT INTO lectures(school_id,class_id,subject_id,faculty_id,title,description,lecture_date) VALUES(?,?,?,?,?,?,?)',
                [$u['school_id'], $cid, $sid2, $u['id'], post('title'), post('description'), $date]);
            foreach ($files as $f) run("INSERT INTO lecture_items(lecture_id,kind,file_name,file_path) VALUES(?,'file',?,?)", [$lid, $f['name'], $f['path']]);
            foreach (array_unique($links) as $lk) run("INSERT INTO lecture_items(lecture_id,kind,url) VALUES(?,'link',?)", [$lid, $lk]);
            audit('lecture_added', post('title')); flash('Lecture published with ' . count($files) . ' file(s) and ' . count($links) . ' link(s). Students of the class can see it now.');
        }
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('faculty/lectures');
}
[$rows, $pager] = paginate('SELECT l.*, c.name cname, c.section, sub.name sname FROM lectures l JOIN classes c ON c.id=l.class_id JOIN subjects sub ON sub.id=l.subject_id WHERE l.faculty_id=? ORDER BY l.created_at DESC, l.id DESC', [$u['id']], 10);
$items = lecture_items(array_column($rows, 'id'));
dash_header('Lectures');
?>
<div class="card"><h3>Upload a lecture</h3>
<?php if (!$pairs): ?><p class="muted">You have no classes in the timetable yet. Ask the school admin to assign periods.</p><?php else: ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><div class="form-grid">
  <div><label>Class &amp; subject *</label><select name="pair" required><?php foreach ($pairs as $p) echo '<option value="' . (int)$p['class_id'] . '-' . (int)$p['subject_id'] . '">Class ' . e(cls($p)) . ' - ' . e($p['sname']) . '</option>'; ?></select></div>
  <div><label>Lecture date</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></div>
  <div class="full"><label>Title *</label><input name="title" required maxlength="150" placeholder="e.g. Fractions - Chapter 3"></div>
  <div class="full"><label>Description</label><textarea name="description" placeholder="What is covered in this lecture?"></textarea></div>
  <div class="full"><label>Upload files <span class="muted">(select several at once &mdash; video, audio, PDF, slides)</span></label>
    <div id="file-box"><input type="file" name="files[]" multiple accept=".mp4,.webm,.ogg,.mp3,.wav,.m4a,.pdf,.ppt,.pptx,.doc,.docx,.jpg,.jpeg,.png"></div>
    <button type="button" class="btn btn-sm" id="add-file" style="margin-top:8px">+ Add another file</button><div class="small muted" id="file-list"></div></div>
  <div class="full"><label>Links <span class="muted">(YouTube, Google Drive, etc.)</span></label>
    <div id="link-list"><input type="url" name="links[]" placeholder="https://youtube.com/..."></div>
    <button type="button" class="btn btn-sm" id="add-link" style="margin-top:8px">+ Add another link</button></div></div>
  <p class="small muted">Each file up to 300 MB. Tip: MP4 (H.264) plays directly in every browser. For very long videos, upload to YouTube (unlisted) and paste the link.</p>
  <p><button class="btn btn-primary">Publish lecture</button></p></form><?php endif; ?></div>
<div class="card"><h3>My lectures</h3><div class="table-wrap"><table><tr><th>Date</th><th>Class</th><th>Subject</th><th>Title</th><th>Content</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e(fmt_date($r['lecture_date'] ?: $r['created_at'])) ?></td><td><?= e(cls($r)) ?></td><td><?= e($r['sname']) ?></td><td><b><?= e($r['title']) ?></b></td>
  <td><?= lecture_items_links($items[$r['id']] ?? []) ?></td>
  <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger"<?= confirm_attr('Delete this lecture?') ?>>Delete</button></form></td></tr>
<?php endforeach; if (!$rows) echo empty_row(6); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
