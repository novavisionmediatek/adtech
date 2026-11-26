<?php
$s = sid();
if (is_post()) {
    try {
        if (post('decision') === 'withdraw') { withdraw_leave((int)post('id'), $s); flash('The approved leave was cancelled.'); redirect('admin/requests'); }
        $approve = post('decision') === 'approve';
        $r = decide_leave((int)post('id'), $s, $approve, post('note'));
        if ($approve && $r['kind'] === 'faculty') { flash('Approved. Now choose substitute teachers for this leave.'); redirect('admin/leave?day=' . max($r['from_date'], date('Y-m-d')) . '#substitutes'); }
        flash($approve ? 'Leave approved. The class teacher sees it while marking attendance.' : 'Request marked as not approved.');
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('admin/requests');
}
$sql = 'SELECT r.*, u.name uname, su.name sname, c.name cname, c.section FROM leave_requests r JOIN users u ON u.id=r.user_id LEFT JOIN students st ON st.id=r.student_id LEFT JOIN users su ON su.id=st.user_id LEFT JOIN classes c ON c.id=st.class_id WHERE r.school_id=?';
$pending = q("$sql AND r.status='pending' ORDER BY r.from_date, r.id LIMIT 100", [$s]);
[$rows, $pager] = paginate("$sql AND r.status<>'pending' ORDER BY r.id DESC", [$s], 10);
$who = fn(array $r) => $r['kind'] === 'student' ? '<b>' . e($r['sname']) . '</b> ' . badge('Student ' . cls($r), 'blue') . '<br><span class="muted small">applied by ' . e($r['uname']) . '</span>' : '<b>' . e($r['uname']) . '</b> ' . badge('Teacher');
dash_header('Leave requests');
?>
<div class="card"><h3>Waiting for your decision (<?= count($pending) ?>)</h3>
<?php foreach ($pending as $r): ?><div class="request"><div class="info"><?= $who($r) ?>
    <p><b><?= e(date_range_label($r['from_date'], $r['to_date'])) ?></b> &middot; <?= e($r['type']) ?><br><?= nl2br(e($r['reason'])) ?></p></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input name="note" maxlength="255" placeholder="Note to applicant (optional)" aria-label="Note">
    <span><button class="btn btn-primary" name="decision" value="approve">Approve</button> <button class="btn btn-danger" name="decision" value="reject">Do not approve</button></span></form></div>
<?php endforeach; if (!$pending) echo '<p class="muted">No requests are waiting.</p>'; ?></div>
<div class="card"><h3>Earlier requests</h3><div class="table-wrap"><table class="stack"><tr><th>Who</th><th>Dates</th><th>Type</th><th>Reason</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td class="who-cell"><?= $who($r) ?></td><td data-label="Dates"><?= e(date_range_label($r['from_date'], $r['to_date'])) ?></td><td data-label="Type"><?= e($r['type']) ?></td><td data-label="Reason" class="wide"><?= e($r['reason']) ?></td><td data-label="Status"><?= badge(...REQUEST_STATUS[$r['status']]) ?><?= $r['decided_note'] ? '<br><span class="muted small">' . e($r['decided_note']) . '</span>' : '' ?></td>
  <td class="right"><?php if ($r['status'] === 'approved' && $r['to_date'] >= date('Y-m-d')): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger" name="decision" value="withdraw"<?= confirm_attr('Cancel this approved leave?') ?>>Cancel leave</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows) echo empty_row(6, 'Nothing yet.'); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
