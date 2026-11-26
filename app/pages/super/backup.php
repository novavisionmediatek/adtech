<?php
$send = function (string $name): never {
    $f = BACKUP_DIR . '/' . $name;
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/gzip'); header('Content-Disposition: attachment; filename="' . $name . '"'); header('Content-Length: ' . filesize($f)); header('Cache-Control: no-store');
    readfile($f); exit;
};
if (is_post()) {
    $a = post('action');
    try {
        if ($a === 'db') { $n = backup_database('manual'); audit('backup_downloaded', $n); $send($n); }
        if ($a === 'get') {
            $n = post('name');
            if (!preg_match(BACKUP_NAME, $n) || !is_file(BACKUP_DIR . '/' . $n)) throw new RuntimeException('That backup no longer exists.');
            audit('backup_downloaded', $n); $send($n);
        }
        if ($a === 'files') { audit('backup_files_downloaded'); stream_zip(UPLOAD_DIR, 'vidyasetu-files-' . date('Ymd') . '.zip'); }
    } catch (Throwable $x) { flash('Backup failed: ' . $x->getMessage(), 'err'); }
    redirect('super/backup');
}
$list = backup_list(); [$page, $pager] = paginate_array($list, 10);
$error = is_file(BACKUP_DIR . '/.error') ? (string)file_get_contents(BACKUP_DIR . '/.error') : '';
dash_header('Backup');
?>
<?php if ($error): ?><div class="alert alert-err">The last automatic backup failed: <?= e($error) ?></div><?php endif; ?>
<div class="grid g2">
<div class="card"><h3>Download a backup now</h3><p class="muted">Keep a copy on your own computer or pen drive at least once a week.</p>
  <form method="post" style="display:inline"><?= csrf_field() ?><button class="btn btn-primary" name="action" value="db">&#8681; Database (all records)</button></form>
  <form method="post" style="display:inline"><?= csrf_field() ?><button class="btn" name="action" value="files">&#8681; Uploaded files (ZIP)</button></form>
  <p class="small muted" style="margin:14px 0 0">A backup contains personal data of students and staff. Store it safely and share it with nobody.</p></div>
<div class="card"><h3>Automatic backup <?= cfg('auto_backup', true) ? badge('On', 'green') : badge('Off', 'red') ?></h3>
  <p class="muted">The database is saved once a day, on the first visit of the day. The newest <?= BACKUP_KEEP['auto'] ?> daily copies are kept on the server.</p>
  <p class="small muted" style="margin:0">To restore: <?= is_mysql() ? 'unzip the <code>.sql.gz</code> file and import it with phpMyAdmin.' : 'unzip the <code>.sqlite.gz</code> file, stop the portal, and save it as <code>data/vidyasetu.sqlite</code> (delete the files ending in <code>-wal</code> and <code>-shm</code>).' ?> Unzip the files backup into <code>data/uploads</code>.</p></div>
</div>
<div class="card"><h3>Saved on the server</h3><div class="table-wrap"><table class="stack"><tr><th>Date and time</th><th>Kind</th><th>Size</th><th></th></tr>
<?php foreach ($page as $b): ?><tr><td class="who-cell"><b><?= e(date('d M Y, h:i A', $b['time'])) ?></b></td><td data-label="Kind"><?= badge($b['kind'] === 'auto' ? 'Automatic' : 'Manual', $b['kind'] === 'auto' ? 'blue' : 'gray') ?></td><td data-label="Size"><?= e(size_label((int)$b['size'])) ?></td>
  <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="name" value="<?= e($b['name']) ?>"><button class="btn btn-sm" name="action" value="get">&#8681; Download</button></form></td></tr>
<?php endforeach; if (!$list) echo empty_row(4, 'No backups yet.'); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
