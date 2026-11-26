<?php
$s = sid(); $today = date('Y-m-d');
if (is_post()) {
    try {
        $a = post('action');
        if ($a === 'book') {
            $title = post('title'); $copies = (int)post('copies');
            if ($title === '' || $copies < 1 || $copies > 1000) throw new RuntimeException('Enter the title and the number of copies (1 to 1000).');
            run('INSERT INTO library_books(school_id,title,author,code,copies,created_at) VALUES(?,?,?,?,?,?)', [$s, mb_substr($title, 0, 200), mb_substr(post('author'), 0, 150), mb_substr(post('code'), 0, 40), $copies, date('Y-m-d H:i:s')]);
            audit('book_added', $title); flash('Book added.');
        } elseif ($a === 'copies') {
            $b = row(books_sql() . ' AND b.id=?', [$s, (int)post('id')]); $n = (int)post('copies');
            if (!$b) throw new RuntimeException('Book not found.');
            if ($n < (int)$b['out_now'] || $n > 1000) throw new RuntimeException('Copies cannot be fewer than the books now with students (' . (int)$b['out_now'] . ').');
            run('UPDATE library_books SET copies=? WHERE id=?', [$n, $b['id']]); flash('Number of copies saved.');
        } elseif ($a === 'delete') {
            if (val('SELECT 1 FROM library_loans WHERE book_id=?', [(int)post('id')])) throw new RuntimeException('A book that was ever issued cannot be deleted. Set its copies to what you still have.');
            if (run('DELETE FROM library_books WHERE id=? AND school_id=?', [(int)post('id'), $s])) flash('Book removed.');
        } elseif ($a === 'issue') { issue_book($s, (int)post('book_id'), post('admission_no'), post('due_on')); flash('Book issued.'); }
        elseif ($a === 'return') {
            if (run('UPDATE library_loans SET returned_on=? WHERE id=? AND school_id=? AND returned_on IS NULL', [$today, (int)post('id'), $s])) { audit('book_returned', post('id')); flash('Book returned.'); }
        }
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('admin/library');
}
$qs = inp('q'); $w = ''; $p = [$s];
if ($qs !== '') { $w = ' AND (b.title LIKE ? OR b.author LIKE ? OR b.code LIKE ?)'; array_push($p, "%$qs%", "%$qs%", "%$qs%"); }
[$books, $bookPager] = paginate(books_sql() . "$w ORDER BY b.title, b.id", $p, 10);
[$loans, $loanPager] = paginate('SELECT l.*, b.title, u.name, st.admission_no FROM library_loans l JOIN library_books b ON b.id=l.book_id JOIN students st ON st.id=l.student_id JOIN users u ON u.id=st.user_id WHERE l.school_id=? ORDER BY l.returned_on IS NOT NULL, l.due_on, l.id DESC', [$s], 10, 'pg2');
$all = q(books_sql() . ' ORDER BY b.title', [$s]); $students = q('SELECT st.admission_no, u.name FROM students st JOIN users u ON u.id=st.user_id WHERE st.school_id=? AND st.active=1 ORDER BY u.name LIMIT 1000', [$s]);
dash_header('Library');
?>
<div class="stats"><?= kpi('Books', count($all)) ?><?= kpi('Copies', array_sum(array_column($all, 'copies'))) ?><?= kpi('With students', array_sum(array_column($all, 'out_now')), '', 'saffron') ?><?= kpi('Overdue', (int)val('SELECT COUNT(*) FROM library_loans WHERE school_id=? AND returned_on IS NULL AND due_on<?', [$s, $today]), '', 'red') ?></div>
<div class="grid g2">
<div class="card"><h3>Issue a book</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="issue"><div class="form-grid">
  <div class="full"><label>Book *</label><select name="book_id" required><option value="">Choose...</option><?php foreach ($all as $b) if ($b['copies'] > $b['out_now']) echo '<option value="' . (int)$b['id'] . '">' . e($b['title'] . ($b['code'] ? ' (' . $b['code'] . ')' : '')) . ' - ' . ((int)$b['copies'] - (int)$b['out_now']) . ' available</option>'; ?></select></div>
  <div><label>Student admission no. *</label><input name="admission_no" list="students" required autocomplete="off"><datalist id="students"><?php foreach ($students as $x) echo '<option value="' . e($x['admission_no']) . '">' . e($x['name']) . '</option>'; ?></datalist></div>
  <div><label>Return by *</label><input type="date" name="due_on" required min="<?= $today ?>" value="<?= date('Y-m-d', strtotime('+' . LOAN_DAYS . ' day')) ?>"></div></div>
  <p><button class="btn btn-primary">Issue book</button></p></form></div>
<div class="card"><h3>Add a book</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="book"><div class="form-grid">
  <div class="full"><label>Title *</label><input name="title" required maxlength="200"></div><div><label>Author</label><input name="author" maxlength="150"></div>
  <div><label>Book code</label><input name="code" maxlength="40"></div><div><label>Copies *</label><input type="number" name="copies" value="1" min="1" max="1000" required></div></div>
  <p><button class="btn">Add book</button></p></form></div></div>
<div class="card"><h3>Issued books</h3><div class="table-wrap"><table class="stack"><tr><th>Student</th><th>Book</th><th>Issued</th><th>Return by</th><th>Status</th><th></th></tr>
<?php foreach ($loans as $l): ?><tr><td class="who-cell"><b><?= e($l['name']) ?></b> <span class="muted small"><?= e($l['admission_no']) ?></span></td><td data-label="Book"><?= e($l['title']) ?></td><td data-label="Issued"><?= e(fmt_date($l['issued_on'])) ?></td><td data-label="Return by"><?= e(fmt_date($l['due_on'])) ?></td><td data-label="Status"><?= loan_badge($l['due_on'], $l['returned_on']) ?></td>
  <td class="right"><?php if (!$l['returned_on']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="return"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-primary">Mark returned</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$loans) echo empty_row(6, 'No books issued.'); ?></table></div><?= $loanPager ?></div>
<div class="card"><div class="card-head"><h3>Books</h3><form method="get" class="inline-form"><input name="q" value="<?= e($qs) ?>" placeholder="Search title / author / code"><button class="btn btn-sm">Search</button></form></div>
<div class="table-wrap"><table class="stack"><tr><th>Title</th><th>Author</th><th>Code</th><th>Available</th><th>Copies</th><th></th></tr>
<?php foreach ($books as $b): ?><tr><td class="who-cell"><b><?= e($b['title']) ?></b></td><td data-label="Author"><?= e($b['author'] ?: '-') ?></td><td data-label="Code"><?= e($b['code'] ?: '-') ?></td><td data-label="Available"><?= badge((string)((int)$b['copies'] - (int)$b['out_now']), $b['copies'] > $b['out_now'] ? 'green' : 'red') ?></td>
  <td data-label="Copies"><form method="post" class="row-form"><?= csrf_field() ?><input type="hidden" name="action" value="copies"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="number" class="mark-in" name="copies" value="<?= (int)$b['copies'] ?>" min="0" max="1000" aria-label="Copies"><button class="btn btn-sm">Save</button></form></td>
  <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn btn-sm btn-danger"<?= confirm_attr('Remove this book?') ?>>Remove</button></form></td></tr>
<?php endforeach; if (!$books) echo empty_row(6, 'No books yet.'); ?></table></div><?= $bookPager ?></div>
<?php dash_footer(); ?>
