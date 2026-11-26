<?php
$s = sid(); $today = date('Y-m-d');
if (is_post()) {
    try {
        if (post('action') === 'issue') { $id = issue_certificate($s, $_POST); flash('Certificate issued. Print it and get it signed.'); redirect("print/certificate/$id"); }
        if (post('action') === 'revoke' && run('UPDATE certificates SET revoked=1 WHERE id=? AND school_id=?', [(int)post('id'), $s])) { audit('certificate_cancelled', post('id')); flash('Certificate cancelled. It stays in the list as cancelled.'); }
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('admin/certificates');
}
$qs = inp('q'); $w = ''; $p = [$s];
if ($qs !== '') { $w = ' AND (u.name LIKE ? OR st.admission_no LIKE ? OR ct.serial_no LIKE ?)'; array_push($p, "%$qs%", "%$qs%", "%$qs%"); }
[$rows, $pager] = paginate("SELECT ct.*, u.name, st.admission_no FROM certificates ct JOIN students st ON st.id=ct.student_id JOIN users u ON u.id=st.user_id WHERE ct.school_id=?$w ORDER BY ct.id DESC", $p, 20);
$students = q('SELECT st.admission_no, u.name FROM students st JOIN users u ON u.id=st.user_id WHERE st.school_id=? ORDER BY st.active DESC, u.name LIMIT 1000', [$s]);
dash_header('Certificates');
?>
<div class="card"><h3>Issue a certificate</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="issue"><div class="form-grid">
  <div><label>Certificate *</label><select name="type"><?php foreach (CERT_TYPES as $k => [$l]) echo '<option value="' . $k . '">' . $l . '</option>'; ?></select></div>
  <div><label>Student admission no. *</label><input name="admission_no" list="students" required placeholder="type number or name" autocomplete="off"><datalist id="students"><?php foreach ($students as $x) echo '<option value="' . e($x['admission_no']) . '">' . e($x['name']) . '</option>'; ?></datalist></div>
  <div><label>Purpose</label><input name="purpose" maxlength="200" placeholder="e.g. bank account"></div><div><label>Conduct</label><input name="conduct" maxlength="200" placeholder="Good"></div>
  <div><label>Date of leaving <span class="muted">(transfer only)</span></label><input type="date" name="leaving_date" value="<?= $today ?>"></div><div><label>Reason for leaving <span class="muted">(transfer only)</span></label><input name="reason" maxlength="200"></div>
  <div class="full"><label>Remarks</label><input name="remarks" maxlength="200"></div>
  <div class="full pw-opts"><label><input type="checkbox" name="deactivate" value="1"> Transfer certificate: also mark the student as left (inactive)</label></div></div>
  <p><button class="btn btn-primary"<?= confirm_attr('Issue this certificate?') ?>>Issue certificate</button></p></form></div>
<div class="card"><div class="card-head"><h3>Issued certificates</h3><form method="get" class="inline-form"><input name="q" value="<?= e($qs) ?>" placeholder="Search name / number"><button class="btn btn-sm">Search</button></form></div>
<div class="table-wrap"><table class="stack"><tr><th>Student</th><th>Certificate</th><th>Number</th><th>Issued on</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td class="who-cell"><b><?= e($r['name']) ?></b> <span class="muted small"><?= e($r['admission_no']) ?></span> <?= $r['revoked'] ? badge('Cancelled', 'red') : '' ?></td><td data-label="Certificate"><?= e(CERT_TYPES[$r['type']][0] ?? $r['type']) ?></td><td data-label="Number"><code><?= e($r['serial_no']) ?></code></td><td data-label="Issued on"><?= e(fmt_date($r['issued_on'])) ?></td>
  <td class="right"><a class="btn btn-sm" href="/print/certificate/<?= (int)$r['id'] ?>">View / print</a><?php if (!$r['revoked']): ?> <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger"<?= confirm_attr('Cancel this certificate?') ?>>Cancel</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows) echo empty_row(5, 'No certificates issued yet.'); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
