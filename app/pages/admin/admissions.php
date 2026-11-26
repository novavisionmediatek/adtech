<?php
$s = sid(); $classes = school_classes($s);
$id = (int)inp('id'); $app = $id ? row('SELECT * FROM admissions WHERE id=? AND school_id=?', [$id, $s]) : null;
if ($id && !$app) render_error(404, 'Application not found', 'This application does not exist.');
if (is_post() && $app) {
    $a = post('action');
    try {
        if ($app['status'] !== 'new') throw new RuntimeException('This application was already decided.');
        if ($a === 'accept') {
            [$login, $spw, $ppw, $newParent] = create_student($s, ['name' => $app['student_name'], 'class_id' => post('class_id'), 'admission_no' => post('admission_no'), 'roll_no' => post('roll_no'), 'dob' => (string)$app['dob'], 'gender' => (string)$app['gender'],
                'parent_name' => $app['parent_name'], 'parent_email' => $app['parent_email'], 'parent_phone' => $app['parent_phone'], 'address' => (string)$app['address']]);
            $stId = (int)val('SELECT st.id FROM students st JOIN users u ON u.id=st.user_id WHERE lower(u.username)=?', [strtolower($login)]);
            run("UPDATE admissions SET status='accepted', student_id=?, decided_at=?, decided_by=? WHERE id=?", [$stId, date('Y-m-d H:i:s'), me()['id'], $id]);
            audit('admission_accepted', $app['ref_no'] . " $login");
            flash("Admitted. Student login ID: $login" . ($spw !== null ? ", temporary password: $spw" : '') . '. ' . ($newParent ? ($ppw !== null ? "Parent temporary password: $ppw." : '') : 'Linked to the existing parent account.') . mail_summary());
        } elseif ($a === 'reject') {
            $note = mb_substr(post('note'), 0, 255);
            run("UPDATE admissions SET status='rejected', note=?, decided_at=?, decided_by=? WHERE id=?", [$note, date('Y-m-d H:i:s'), me()['id'], $id]);
            audit('admission_rejected', (string)$app['ref_no']);
            send_mail($app['parent_email'], $app['parent_name'], 'Admission application ' . $app['ref_no'], mail_wrap('Admission application', '<p>Dear ' . e($app['parent_name']) . ',</p><p>We are sorry, the admission application for <b>' . e($app['student_name']) . '</b> could not be accepted.</p>' . ($note !== '' ? '<p>Note from school: ' . e($note) . '</p>' : '')), $s);
            flash('Application marked as not accepted and the parent was informed.' . mail_summary());
        }
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect("admin/admissions?id=$id");
}
if ($app) {
    dash_header('Application ' . $app['ref_no'], '<a class="btn btn-sm" href="/admin/admissions">&larr; All applications</a>');
    $fields = ['Student' => $app['student_name'], 'Date of birth' => $app['dob'] ? fmt_date($app['dob']) : '-', 'Gender' => ['M' => 'Male', 'F' => 'Female', 'O' => 'Other'][$app['gender']] ?? '-', 'Class wanted' => $app['class_sought'], 'Parent' => $app['parent_name'], 'Mobile' => $app['parent_phone'], 'E-mail' => $app['parent_email'], 'Address' => $app['address'] ?: '-', 'Previous school' => $app['previous_school'] ?: '-', 'Applied on' => fmt_date($app['created_at'], 'd M Y, h:i A')];
    echo '<div class="card"><div class="card-head"><h3>' . e($app['student_name']) . '</h3>' . badge(...ADMISSION_STATUS[$app['status']]) . '</div><div class="table-wrap"><table>';
    foreach ($fields as $k => $v) echo '<tr><th style="width:180px">' . e($k) . '</th><td>' . e($v) . '</td></tr>';
    echo '</table></div>' . ($app['note'] ? '<p class="muted">Note: ' . e($app['note']) . '</p>' : '') . '</div>';
    if ($app['status'] === 'new'): ?>
<div class="grid g2">
<div class="card"><h3>Admit this student</h3><?php if (!$classes): ?><p class="muted">Add classes first.</p><?php else: ?><p class="muted small">Creates the student and parent accounts and e-mails the login details to the parent.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="accept"><div class="form-grid">
    <div><label>Class *</label><select name="class_id" required><?php foreach ($classes as $c) echo '<option value="' . (int)$c['id'] . '"' . (strcasecmp($c['name'], trim((string)$app['class_sought'])) === 0 ? ' selected' : '') . '>' . e(class_label($c)) . '</option>'; ?></select></div>
    <div><label>New admission no. *</label><input name="admission_no" required></div><div><label>Roll no.</label><input type="number" name="roll_no" min="0"></div></div>
    <p><button class="btn btn-primary"<?= confirm_attr('Admit this student and create the accounts?') ?>>Admit student</button></p></form><?php endif; ?></div>
<div class="card"><h3>Do not accept</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><p><label>Reason sent to the parent <span class="muted">(optional)</span></label><input name="note" maxlength="255"></p>
  <p><button class="btn btn-danger"<?= confirm_attr('Reject this application and e-mail the parent?') ?>>Do not accept</button></p></form></div></div>
    <?php endif; dash_footer(); return;
}
$status = isset(ADMISSION_STATUS[inp('status')]) ? inp('status') : '';
[$rows, $pager] = paginate('SELECT * FROM admissions WHERE school_id=?' . ($status ? ' AND status=?' : '') . " ORDER BY status='new' DESC, id DESC", $status ? [$s, $status] : [$s], 20);
dash_header('Admissions');
?>
<div class="card"><p class="muted" style="margin:0">Parents apply on the public page: <a href="/admission" target="_blank"><b><?= e(rtrim((string)cfg('base_url'), '/')) ?>/admission</b></a></p></div>
<div class="tabs"><?php foreach (['' => 'All', 'new' => 'New', 'accepted' => 'Admitted', 'rejected' => 'Not accepted'] as $k => $l) echo '<a href="?status=' . $k . '"' . ($status === $k ? ' class="active"' : '') . '>' . $l . '</a>'; ?></div>
<div class="card"><div class="table-wrap"><table class="stack"><tr><th>Student</th><th>Reference</th><th>Class wanted</th><th>Parent</th><th>Applied on</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td class="who-cell"><b><?= e($r['student_name']) ?></b></td><td data-label="Reference"><code><?= e($r['ref_no']) ?></code></td><td data-label="Class wanted"><?= e($r['class_sought']) ?></td><td data-label="Parent"><?= e($r['parent_name']) ?><br><span class="muted small"><?= e($r['parent_phone']) ?></span></td><td data-label="Applied on"><?= e(fmt_date($r['created_at'])) ?></td><td data-label="Status"><?= badge(...ADMISSION_STATUS[$r['status']]) ?></td>
  <td class="right"><a class="btn btn-sm <?= $r['status'] === 'new' ? 'btn-primary' : '' ?>" href="?id=<?= (int)$r['id'] ?>">Open</a></td></tr>
<?php endforeach; if (!$rows) echo empty_row(7, 'No applications.'); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
