<?php
declare(strict_types=1);
/* Library loans and school transport. */

const LOAN_DAYS = 14;

function books_sql(): string { return 'SELECT b.*, COALESCE(o.n,0) out_now FROM library_books b LEFT JOIN (SELECT book_id, COUNT(*) n FROM library_loans WHERE returned_on IS NULL GROUP BY book_id) o ON o.book_id=b.id WHERE b.school_id=?'; }
function issue_book(int $school, int $bookId, string $admissionNo, string $due): void {
    if (!valid_date($due) || $due < date('Y-m-d')) throw new RuntimeException('The return date cannot be in the past.');
    $st = row('SELECT st.id, u.name FROM students st JOIN users u ON u.id=st.user_id WHERE st.school_id=? AND lower(st.admission_no)=? AND st.active=1', [$school, strtolower(preg_replace('/\s+/', '', $admissionNo))]);
    if (!$st) throw new RuntimeException('No active student with that admission number.');
    $pdo = db(); $pdo->beginTransaction();
    try {
        run('UPDATE library_books SET copies=copies WHERE id=? AND school_id=?', [$bookId, $school]); // lock so the last copy is not issued twice
        $b = row(books_sql() . ' AND b.id=?', [$school, $bookId]);
        if (!$b) throw new RuntimeException('Choose a book.');
        if ((int)$b['copies'] <= (int)val('SELECT COUNT(*) FROM library_loans WHERE book_id=? AND returned_on IS NULL', [$bookId])) throw new RuntimeException('No copy of "' . $b['title'] . '" is available.');
        if (val('SELECT 1 FROM library_loans WHERE book_id=? AND student_id=? AND returned_on IS NULL', [$bookId, $st['id']])) throw new RuntimeException($st['name'] . ' already has this book.');
        run('INSERT INTO library_loans(school_id,book_id,student_id,issued_on,due_on,created_by) VALUES(?,?,?,?,?,?)', [$school, $bookId, $st['id'], date('Y-m-d'), $due, me()['id']]);
        $pdo->commit();
    } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
    audit('book_issued', $b['title'] . ' to ' . $st['name']);
}
function loan_badge(string $due, ?string $returned): string {
    if ($returned) return badge('Returned ' . fmt_date($returned, 'd M'), $returned > $due ? 'amber' : 'green');
    return $due < date('Y-m-d') ? badge('Overdue', 'red') : badge('With student', 'blue');
}
/** Library books and bus details of one student (student and parent screens). */
function view_campus(array $st): string {
    $loans = q('SELECT l.*, b.title, b.author FROM library_loans l JOIN library_books b ON b.id=l.book_id WHERE l.student_id=? ORDER BY l.returned_on IS NOT NULL, l.id DESC LIMIT 30', [$st['id']]);
    $bus = row('SELECT t.*, r.name rname, r.vehicle_no, r.driver_name, r.driver_phone, r.stops FROM transport_students t JOIN transport_routes r ON r.id=t.route_id WHERE t.student_id=?', [$st['id']]);
    $o = '<div class="card"><h3>School bus</h3>';
    if (!$bus) $o .= '<p class="muted">No school transport is assigned.</p>';
    else { $o .= '<div class="table-wrap"><table>'; foreach (['Route' => $bus['rname'], 'Your stop' => $bus['stop'], 'Pick-up time' => $bus['pickup_time'], 'Vehicle number' => $bus['vehicle_no'], 'Driver' => $bus['driver_name'], 'Driver phone' => $bus['driver_phone'], 'All stops' => $bus['stops']] as $k => $v) $o .= '<tr><th style="width:170px">' . $k . '</th><td>' . e((string)$v ?: '-') . '</td></tr>'; $o .= '</table></div>'; }
    $o .= '</div><div class="card"><h3>Library books</h3><div class="table-wrap"><table><tr><th>Book</th><th>Issued</th><th>Return by</th><th>Status</th></tr>';
    foreach ($loans as $l) $o .= '<tr><td><b>' . e($l['title']) . '</b><br><span class="muted small">' . e($l['author']) . '</span></td><td>' . e(fmt_date($l['issued_on'])) . '</td><td>' . e(fmt_date($l['due_on'])) . '</td><td>' . loan_badge($l['due_on'], $l['returned_on']) . '</td></tr>';
    if (!$loans) $o .= empty_row(4, 'No books borrowed.');
    return $o . '</table></div></div>';
}
