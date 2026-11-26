<?php
declare(strict_types=1);
/* Fees: amounts due per student, payments received at the school (recorded by the admin) and receipts. No online payment. */

const PAY_MODES = ['Cash', 'Cheque', 'UPI', 'Bank transfer', 'Card', 'Other'];
const FEE_MAX = 1000000;

function money($v): string { return '&#8377;' . number_format((float)$v, fmod((float)$v, 1.0) == 0.0 ? 0 : 2); }
function fee_badge(float $amount, float $paid, string $due): string {
    if ($paid >= $amount - 0.001) return badge('Paid', 'green');
    if ($due < date('Y-m-d')) return badge($paid > 0 ? 'Part paid, overdue' : 'Overdue', 'red');
    return $paid > 0 ? badge('Part paid', 'amber') : badge('To be paid', 'blue');
}
/** SQL for dues with the amount paid so far; append conditions with AND. */
function fee_sql(): string {
    return 'SELECT d.*, u.name, st.admission_no, st.class_id, st.parent_id, c.name cname, c.section, COALESCE(p.paid,0) paid FROM fee_dues d
        JOIN students st ON st.id=d.student_id JOIN users u ON u.id=st.user_id JOIN classes c ON c.id=st.class_id
        LEFT JOIN (SELECT due_id, SUM(amount) paid FROM fee_payments WHERE cancelled=0 GROUP BY due_id) p ON p.due_id=d.id WHERE d.school_id=?';
}
/** Adds one fee to a class, to every class, or to one student. Students who already have the same fee are skipped. Returns [added, skipped]. */
function add_fees(int $school, array $d): array {
    $title = mb_substr(trim((string)($d['title'] ?? '')), 0, 150); $amount = (string)($d['amount'] ?? ''); $due = (string)($d['due_date'] ?? '');
    $adm = preg_replace('/\s+/', '', (string)($d['admission_no'] ?? '')); $class = (int)($d['class_id'] ?? 0);
    if ($title === '') throw new RuntimeException('Give the fee a name.');
    if (!is_numeric($amount) || (float)$amount <= 0 || (float)$amount > FEE_MAX) throw new RuntimeException('Enter an amount between 1 and ' . number_format(FEE_MAX) . '.');
    if (!valid_date($due)) throw new RuntimeException('Choose the last date for payment.');
    if ($adm !== '') {
        $ids = q('SELECT id FROM students WHERE school_id=? AND lower(admission_no)=? AND active=1', [$school, strtolower($adm)]);
        if (!$ids) throw new RuntimeException("No active student with admission number $adm.");
    } elseif ($class === -1) $ids = q('SELECT id FROM students WHERE school_id=? AND active=1', [$school]);
    elseif ($class > 0) $ids = q('SELECT id FROM students WHERE school_id=? AND class_id=? AND active=1', [$school, $class]);
    else throw new RuntimeException('Choose a class, all students, or type an admission number.');
    $added = 0; $skipped = 0; $pdo = db(); $pdo->beginTransaction();
    foreach ($ids as $s) {
        if (val('SELECT 1 FROM fee_dues WHERE student_id=? AND title=? AND due_date=?', [$s['id'], $title, $due])) { $skipped++; continue; }
        run('INSERT INTO fee_dues(school_id,student_id,title,amount,due_date,created_by,created_at) VALUES(?,?,?,?,?,?,?)', [$school, $s['id'], $title, round((float)$amount, 2), $due, me()['id'], date('Y-m-d H:i:s')]); $added++;
    }
    $pdo->commit(); audit('fee_added', "$title " . round((float)$amount, 2) . " x $added");
    return [$added, $skipped];
}
/** Records money received. Never more than what is still unpaid. Returns the payment id. */
function record_payment(int $school, int $dueId, array $d): int {
    $amount = (string)($d['amount'] ?? ''); $mode = (string)($d['mode'] ?? ''); $on = (string)($d['paid_on'] ?? '');
    if (!is_numeric($amount) || round((float)$amount, 2) <= 0) throw new RuntimeException('Enter the amount received.');
    if (!in_array($mode, PAY_MODES, true)) throw new RuntimeException('Choose how it was paid.');
    if (!valid_date($on) || $on > date('Y-m-d')) throw new RuntimeException('The payment date cannot be in the future.');
    $amount = round((float)$amount, 2); $pdo = db(); $pdo->beginTransaction();
    try {
        run('UPDATE fee_dues SET amount=amount WHERE id=? AND school_id=?', [$dueId, $school]); // locks the fee so two people cannot collect it twice
        $due = row('SELECT * FROM fee_dues WHERE id=? AND school_id=?', [$dueId, $school]);
        if (!$due) throw new RuntimeException('Fee not found.');
        $left = round((float)$due['amount'] - (float)val('SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE due_id=? AND cancelled=0', [$dueId]), 2);
        if ($amount > $left + 0.001) throw new RuntimeException($left > 0 ? 'Only ' . number_format($left, 2) . ' is still unpaid.' : 'This fee is already fully paid.');
        $id = ins('INSERT INTO fee_payments(school_id,due_id,amount,mode,reference,paid_on,recorded_by,created_at) VALUES(?,?,?,?,?,?,?,?)', [$school, $dueId, $amount, $mode, mb_substr(trim((string)($d['reference'] ?? '')), 0, 100), $on, me()['id'], date('Y-m-d H:i:s')]);
        $no = (string)val('SELECT code FROM schools WHERE id=?', [$school]) . '-R' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
        run('UPDATE fee_payments SET receipt_no=? WHERE id=?', [$no, $id]);
        $pdo->commit();
    } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
    audit('fee_paid', "$no $amount");
    $p = row('SELECT pu.name, pu.email, su.name child FROM students st JOIN users su ON su.id=st.user_id JOIN users pu ON pu.id=st.parent_id WHERE st.id=?', [$due['student_id']]);
    if ($p && $p['email']) send_mail($p['email'], $p['name'], 'Fee receipt ' . $no, mail_wrap('Fee received', '<p>Dear ' . e($p['name']) . ',</p><p>We received <b>' . money($amount) . '</b> for <b>' . e($due['title']) . '</b> of ' . e($p['child']) . ' on ' . e(fmt_date($on)) . ' (' . e($mode) . ').</p><p>Receipt number: <b>' . e($no) . '</b>. You can print the receipt after logging in to the portal.</p>'), $school);
    return $id;
}
/** Fee list of one student (student and parent screens). */
function view_fees(array $st): string {
    $dues = q(fee_sql() . ' AND d.student_id=? ORDER BY d.due_date DESC, d.id DESC', [$st['school_id'], $st['id']]);
    $total = array_sum(array_column($dues, 'amount')); $paid = array_sum(array_column($dues, 'paid'));
    $pays = []; foreach (q('SELECT p.* FROM fee_payments p JOIN fee_dues d ON d.id=p.due_id WHERE d.student_id=? AND p.cancelled=0 ORDER BY p.paid_on, p.id', [$st['id']]) as $p) $pays[$p['due_id']][] = $p;
    $o = '<div class="stats">' . kpi('Total fees', html_entity_decode(money($total))) . kpi('Paid', html_entity_decode(money($paid)), '', 'green') . kpi('Still to pay', html_entity_decode(money($total - $paid)), '', $total - $paid > 0 ? 'red' : 'green') . '</div>';
    $o .= '<div class="card"><h3>Fees</h3><p class="muted small">Fees are paid at the school office. Receipts appear here after the office records the payment.</p><div class="table-wrap"><table class="stack"><tr><th>Fee</th><th>Last date</th><th>Amount</th><th>Paid</th><th>Status</th><th>Receipts</th></tr>';
    foreach ($dues as $d) {
        $links = ''; foreach ($pays[$d['id']] ?? [] as $p) $links .= '<a class="btn btn-sm" href="/print/receipt/' . (int)$p['id'] . '">' . e($p['receipt_no']) . '</a> ';
        $o .= '<tr><td class="who-cell"><b>' . e($d['title']) . '</b></td><td data-label="Last date">' . e(fmt_date($d['due_date'])) . '</td><td data-label="Amount">' . money($d['amount']) . '</td><td data-label="Paid">' . money($d['paid']) . '</td><td data-label="Status">' . fee_badge((float)$d['amount'], (float)$d['paid'], $d['due_date']) . '</td><td data-label="Receipts" class="wide">' . ($links ?: '-') . '</td></tr>';
    }
    if (!$dues) $o .= empty_row(6, 'No fees have been added.');
    return $o . '</table></div></div>';
}
