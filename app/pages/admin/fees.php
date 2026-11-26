<?php
$s = sid(); $classes = school_classes($s); $today = date('Y-m-d');
$dueId = (int)inp('due');
if (is_post()) {
    $a = post('action'); $back = 'admin/fees' . ($dueId ? "?due=$dueId" : '');
    try {
        if ($a === 'add') { [$added, $skipped] = add_fees($s, $_POST); flash("Fee added for $added student(s)." . ($skipped ? " $skipped already had it." : ''), $added ? 'ok' : 'warn'); }
        elseif ($a === 'pay') { $pid = record_payment($s, $dueId, $_POST); flash('Payment recorded.' . mail_summary()); redirect("print/receipt/$pid"); }
        elseif ($a === 'cancel') {
            $reason = post('reason'); if ($reason === '') throw new RuntimeException('Write why the payment is cancelled.');
            if (run('UPDATE fee_payments SET cancelled=1, cancel_reason=? WHERE id=? AND due_id=? AND school_id=? AND cancelled=0', [mb_substr($reason, 0, 255), (int)post('payment'), $dueId, $s])) { audit('fee_payment_cancelled', post('payment') . ' ' . $reason); flash('Payment cancelled. It stays in the history.'); }
        } elseif ($a === 'delete') {
            if (val('SELECT 1 FROM fee_payments WHERE due_id=?', [$dueId])) throw new RuntimeException('A fee with payments (even cancelled ones) cannot be deleted.');
            if (run('DELETE FROM fee_dues WHERE id=? AND school_id=?', [$dueId, $s])) { audit('fee_deleted', (string)$dueId); flash('Fee removed.'); }
            $back = 'admin/fees';
        }
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect($back);
}

if ($dueId) {
    $d = row(fee_sql() . ' AND d.id=?', [$s, $dueId]);
    if (!$d) render_error(404, 'Fee not found', 'This fee does not exist.');
    $left = round((float)$d['amount'] - (float)$d['paid'], 2);
    $pays = q('SELECT p.*, u.name rname FROM fee_payments p LEFT JOIN users u ON u.id=p.recorded_by WHERE p.due_id=? ORDER BY p.id', [$dueId]);
    dash_header('Collect fee', '<a class="btn btn-sm" href="/admin/fees">&larr; All fees</a>');
    ?>
<div class="card"><div class="card-head"><h3><?= e($d['name']) ?> &middot; Class <?= e(cls($d)) ?></h3><?= fee_badge((float)$d['amount'], (float)$d['paid'], $d['due_date']) ?></div>
  <p class="muted" style="margin:0"><?= e($d['title']) ?> &middot; admission no. <?= e($d['admission_no']) ?> &middot; last date <?= e(fmt_date($d['due_date'])) ?></p></div>
<div class="stats"><?= kpi('Amount', html_entity_decode(money($d['amount']))) ?><?= kpi('Paid', html_entity_decode(money($d['paid'])), '', 'green') ?><?= kpi('Still to pay', html_entity_decode(money($left)), '', $left > 0 ? 'red' : 'green') ?></div>
<?php if ($left > 0): ?><div class="card"><h3>Record a payment</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pay"><div class="form-grid">
  <div><label>Amount received *</label><input type="number" name="amount" step="0.01" min="0.01" max="<?= $left ?>" value="<?= $left ?>" required></div>
  <div><label>Paid by *</label><select name="mode"><?php foreach (PAY_MODES as $m) echo '<option>' . $m . '</option>'; ?></select></div>
  <div><label>Date *</label><input type="date" name="paid_on" value="<?= $today ?>" max="<?= $today ?>" required></div>
  <div><label>Reference <span class="muted">(cheque / UPI no.)</span></label><input name="reference" maxlength="100"></div></div>
  <p><button class="btn btn-primary"<?= confirm_attr('Record this payment and make a receipt?') ?>>Save and make receipt</button></p></form></div><?php endif; ?>
<div class="card"><h3>Payments</h3><div class="table-wrap"><table class="stack"><tr><th>Receipt</th><th>Date</th><th>Amount</th><th>Paid by</th><th>Recorded by</th><th></th></tr>
<?php foreach ($pays as $p): ?><tr><td class="who-cell"><b><?= e($p['receipt_no']) ?></b> <?= $p['cancelled'] ? badge('Cancelled', 'red') : '' ?></td><td data-label="Date"><?= e(fmt_date($p['paid_on'])) ?></td><td data-label="Amount"><?= money($p['amount']) ?></td><td data-label="Paid by"><?= e($p['mode']) ?><?= $p['reference'] ? '<br><span class="muted small">' . e($p['reference']) . '</span>' : '' ?></td><td data-label="Recorded by"><?= e($p['rname'] ?? '-') ?></td>
  <td class="right"><?php if (!$p['cancelled']): ?><a class="btn btn-sm" href="/print/receipt/<?= (int)$p['id'] ?>">Receipt</a>
    <form method="post" class="row-form"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="payment" value="<?= (int)$p['id'] ?>"><input name="reason" placeholder="Reason to cancel" maxlength="255" required aria-label="Reason"><button class="btn btn-sm btn-danger"<?= confirm_attr('Cancel this payment?') ?>>Cancel</button></form>
    <?php else: ?><span class="muted small"><?= e($p['cancel_reason']) ?></span><?php endif; ?></td></tr>
<?php endforeach; if (!$pays) echo empty_row(6, 'No payments yet.'); ?></table></div></div>
<?php if (!$pays): ?><form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-danger" name="action" value="delete"<?= confirm_attr('Remove this fee?') ?>>Remove this fee</button></form><?php endif; ?>
    <?php dash_footer(); return;
}

$cf = (int)inp('class'); $status = in_array(inp('status'), ['open', 'overdue', 'paid'], true) ? inp('status') : ''; $qs = inp('q');
$w = ''; $p = [$s];
if ($cf) { $w .= ' AND st.class_id=?'; $p[] = $cf; }
if ($status === 'paid') $w .= ' AND COALESCE(p.paid,0)>=d.amount-0.001';
if ($status === 'open' || $status === 'overdue') $w .= ' AND COALESCE(p.paid,0)<d.amount-0.001';
if ($status === 'overdue') { $w .= ' AND d.due_date<?'; $p[] = $today; }
if ($qs !== '') { $w .= ' AND (u.name LIKE ? OR st.admission_no LIKE ? OR d.title LIKE ?)'; array_push($p, "%$qs%", "%$qs%", "%$qs%"); }
$sql = fee_sql() . $w . ' ORDER BY d.due_date DESC, (c.name+0), c.section, st.roll_no, d.id';
if (inp('export')) {
    require_once APP . '/xlsx.php';
    $head = fn(array $cols) => array_map(fn($c) => [$c, 1], $cols);
    $rows = [[[(string)val('SELECT name FROM schools WHERE id=?', [$s]) . ' - Fees', 2]], [['Generated ' . date('d M Y, h:i A'), 6]], []];
    $rows[] = $head(['Class', 'Admission No', 'Student', 'Fee', 'Last date', 'Amount', 'Paid', 'Balance', 'Status']);
    foreach (q($sql, $p) as $r) { $bal = round((float)$r['amount'] - (float)$r['paid'], 2);
        $rows[] = [cls($r), $r['admission_no'], $r['name'], $r['title'], date('d-m-Y', (int)strtotime($r['due_date'])), [(float)$r['amount'], 7], [(float)$r['paid'], 7], [$bal, 7], $bal <= 0 ? ['Paid', 3] : ($r['due_date'] < $today ? ['Overdue', 4] : ['To be paid', 5])]; }
    $pays = [$head(['Receipt', 'Paid on', 'Student', 'Admission No', 'Fee', 'Amount', 'Paid by', 'Reference', 'Cancelled'])];
    foreach (q('SELECT p.*, d.title, u.name, st.admission_no FROM fee_payments p JOIN fee_dues d ON d.id=p.due_id JOIN students st ON st.id=d.student_id JOIN users u ON u.id=st.user_id WHERE p.school_id=? ORDER BY p.id', [$s]) as $r)
        $pays[] = [$r['receipt_no'], date('d-m-Y', (int)strtotime($r['paid_on'])), $r['name'], $r['admission_no'], $r['title'], [(float)$r['amount'], 7], $r['mode'], (string)$r['reference'], $r['cancelled'] ? ['Yes: ' . $r['cancel_reason'], 4] : ''];
    audit('fee_export');
    xlsx_download("fees_$today.xlsx", [['name' => 'Fees', 'rows' => $rows, 'freeze' => [4, 3], 'widths' => [9, 15, 26, 30, 12, 11, 11, 11, 12]], ['name' => 'Payments', 'rows' => $pays, 'freeze' => [1, 0], 'widths' => [16, 12, 26, 15, 30, 11, 14, 20, 24]]]);
}
$tot = row('SELECT COALESCE(SUM(amount),0) billed FROM fee_dues WHERE school_id=?', [$s]);
$got = (float)val('SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE school_id=? AND cancelled=0', [$s]);
$over = (float)val('SELECT COALESCE(SUM(d.amount-COALESCE(p.paid,0)),0) FROM fee_dues d LEFT JOIN (SELECT due_id, SUM(amount) paid FROM fee_payments WHERE cancelled=0 GROUP BY due_id) p ON p.due_id=d.id WHERE d.school_id=? AND d.due_date<? AND COALESCE(p.paid,0)<d.amount', [$s, $today]);
[$rows, $pager] = paginate($sql, $p, 20);
dash_header('Fees');
?>
<div class="stats"><?= kpi('Fees added', html_entity_decode(money($tot['billed']))) ?><?= kpi('Collected', html_entity_decode(money($got)), '', 'green') ?><?= kpi('Still to collect', html_entity_decode(money((float)$tot['billed'] - $got)), '', 'saffron') ?><?= kpi('Overdue', html_entity_decode(money($over)), '', 'red') ?></div>
<div class="card"><h3>Add a fee</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add"><div class="form-grid">
  <div><label>Fee name *</label><input name="title" required maxlength="150" placeholder="e.g. Tuition fee - Term 2"></div>
  <div><label>Amount (&#8377;) *</label><input type="number" name="amount" min="1" step="0.01" required></div>
  <div><label>Last date to pay *</label><input type="date" name="due_date" required></div>
  <div><label>For class</label><select name="class_id"><option value="0">Choose...</option><option value="-1">All students</option><?php foreach ($classes as $c) echo '<option value="' . (int)$c['id'] . '">Class ' . e(class_label($c)) . '</option>'; ?></select></div>
  <div><label>Or only one student <span class="muted">(admission no.)</span></label><input name="admission_no" placeholder="leave empty for a class"></div></div>
  <p><button class="btn btn-primary"<?= confirm_attr('Add this fee?') ?>>Add fee</button></p></form></div>
<div class="card"><h3>Student fees</h3><form method="get" class="inline-form"><div><label>Class</label><select name="class" data-autosubmit><option value="0">All classes</option><?php foreach ($classes as $c) echo '<option value="' . (int)$c['id'] . '"' . ($cf === (int)$c['id'] ? ' selected' : '') . '>' . e(class_label($c)) . '</option>'; ?></select></div>
  <div><label>Status</label><select name="status" data-autosubmit><?php foreach (['' => 'All', 'open' => 'Not fully paid', 'overdue' => 'Overdue', 'paid' => 'Paid'] as $k => $l) echo '<option value="' . $k . '"' . ($status === $k ? ' selected' : '') . '>' . $l . '</option>'; ?></select></div>
  <div><label>Search</label><input name="q" value="<?= e($qs) ?>" placeholder="name, adm. no. or fee"></div><div style="flex:0"><button class="btn btn-primary">Search</button></div>
  <div class="push"><a class="btn" href="?<?= e(http_build_query(['class' => $cf, 'status' => $status, 'q' => $qs, 'export' => 1])) ?>">&#8681; Download Excel</a></div></form>
<div class="table-wrap" style="margin-top:16px"><table class="stack"><tr><th>Student</th><th>Fee</th><th>Last date</th><th>Amount</th><th>Paid</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td class="who-cell"><b><?= e($r['name']) ?></b> <span class="muted small"><?= e(cls($r)) ?> &middot; <?= e($r['admission_no']) ?></span></td><td data-label="Fee"><?= e($r['title']) ?></td><td data-label="Last date"><?= e(fmt_date($r['due_date'])) ?></td><td data-label="Amount"><?= money($r['amount']) ?></td><td data-label="Paid"><?= money($r['paid']) ?></td><td data-label="Status"><?= fee_badge((float)$r['amount'], (float)$r['paid'], $r['due_date']) ?></td>
  <td class="right"><a class="btn btn-sm <?= $r['paid'] >= $r['amount'] ? '' : 'btn-primary' ?>" href="?due=<?= (int)$r['id'] ?>"><?= $r['paid'] >= $r['amount'] ? 'View' : 'Collect' ?></a></td></tr>
<?php endforeach; if (!$rows) echo empty_row(7, 'No fees found.'); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
