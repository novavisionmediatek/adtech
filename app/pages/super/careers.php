<?php
if (is_post()) {
    $a = post('action'); $id = (int)post('id');
    if ($a === 'add') {
        if (post('title') === '') flash('Job title is required.', 'err');
        else { run('INSERT INTO jobs(title,dept,location,type,description) VALUES(?,?,?,?,?)', [post('title'), post('dept'), post('location'), post('type'), post('description')]); audit('job_added', post('title')); flash('Opening published.'); }
    } elseif ($a === 'toggle') { run('UPDATE jobs SET active=1-active WHERE id=?', [$id]); flash('Updated.'); }
    elseif ($a === 'delete') { run('DELETE FROM jobs WHERE id=?', [$id]); flash('Opening deleted.'); }
    redirect('super/careers');
}
[$jobs, $jobPager] = paginate('SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id=j.id) napps FROM jobs j ORDER BY j.created_at DESC, j.id DESC', [], 10);
[$apps, $appPager] = paginate('SELECT a.*, j.title FROM applications a JOIN jobs j ON j.id=a.job_id ORDER BY a.id DESC', [], 10, 'pg2');
dash_header('Careers manager');
?>
<div class="card"><h3>Post an opening</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add"><div class="form-grid">
  <div class="full"><label>Title *</label><input name="title" required></div><div><label>Department</label><input name="dept"></div><div><label>Location</label><input name="location"></div>
  <div><label>Type</label><select name="type"><option>Full-time</option><option>Part-time</option><option>Internship</option><option>Contract</option></select></div>
  <div class="full"><label>Description</label><textarea name="description"></textarea></div></div><p><button class="btn btn-primary">Publish</button></p></form></div>
<div class="card"><h3>Openings</h3><div class="table-wrap"><table><tr><th>Title</th><th>Dept</th><th>Applications</th><th>Status</th><th></th></tr>
<?php foreach ($jobs as $j): ?><tr><td><?= e($j['title']) ?></td><td><?= e($j['dept']) ?></td><td><?= (int)$j['napps'] ?></td><td><?= $j['active'] ? badge('Open', 'green') : badge('Closed') ?></td>
  <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$j['id'] ?>"><button name="action" value="toggle" class="btn btn-sm"><?= $j['active'] ? 'Close' : 'Reopen' ?></button> <button name="action" value="delete" class="btn btn-sm btn-danger"<?= confirm_attr('Delete opening and its applications?') ?>>Delete</button></form></td></tr>
<?php endforeach; ?></table></div><?= $jobPager ?></div>
<div class="card"><h3>Applications</h3><div class="table-wrap"><table><tr><th>Date</th><th>Applicant</th><th>Position</th><th>Message</th><th>Resume</th></tr>
<?php foreach ($apps as $a): ?><tr><td><?= e(fmt_date($a['created_at'])) ?></td><td><?= e($a['name']) ?><br><span class="muted small"><?= e($a['email']) ?> <?= e($a['phone']) ?></span></td><td><?= e($a['title']) ?></td><td><?= e(mb_strimwidth((string)$a['message'], 0, 100, '...')) ?></td>
  <td><?= $a['file_path'] ? '<a href="/file/resume/' . (int)$a['id'] . '">Download</a>' : '-' ?></td></tr><?php endforeach; if (!$apps) echo empty_row(5, 'No applications yet.'); ?></table></div><?= $appPager ?></div>
<?php dash_footer(); ?>
