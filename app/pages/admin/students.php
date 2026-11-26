<?php
$s = sid();
if (is_post()) {
    try {
        $a = post('action');
        if ($a === 'add') {
            [$login, $spw, $ppw, $newParent] = create_student($s, $_POST); audit('student_added', $login);
            flash("Student added. Login ID: $login, " . ($spw === null ? 'password: the one you set' : "temporary password: $spw") . '. ' .
                (!$newParent ? 'Linked to the existing parent account (its password is unchanged).'
                    : 'Parent account created, ' . ($ppw === null ? 'password: the one you set' : "temporary password: $ppw") . '.') .
                ($spw !== null || $ppw !== null ? ' Temporary passwords are shown only once.' : '') . mail_summary());
        } elseif ($a === 'import') {
            if (empty($_FILES['csv']['tmp_name'])) throw new RuntimeException('Choose a CSV file.');
            $h = fopen($_FILES['csv']['tmp_name'], 'r'); $hdr = array_map(fn($x) => strtolower(trim($x, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($h) ?: []);
            $need = ['name', 'class', 'section', 'admission_no', 'parent_name', 'parent_email'];
            foreach ($need as $c) if (!in_array($c, $hdr, true)) throw new RuntimeException("CSV column missing: $c");
            $ok = 0; $bad = []; set_time_limit(0); // one e-mail per student can take a while
            $classes = []; foreach (school_classes($s) as $c) $classes[strtolower($c['name'] . '|' . $c['section'])] = (int)$c['id'];
            $line = 1;
            while (($r = fgetcsv($h)) !== false) {
                $line++; if (count($r) < count($hdr)) { if (count(array_filter($r)) === 0) continue; $r = array_pad($r, count($hdr), ''); }
                $d = array_combine($hdr, array_slice($r, 0, count($hdr)));
                $key = strtolower(trim($d['class']) . '|' . strtoupper(trim($d['section'])));
                $d['class_id'] = $classes[$key] ?? 0;
                try { create_student($s, $d); $ok++; } catch (Throwable $x) { $bad[] = "Line $line: " . $x->getMessage(); }
            }
            fclose($h); audit('students_imported', "$ok ok, " . count($bad) . ' failed');
            flash("Imported $ok students." . ($bad ? ' Skipped ' . count($bad) . ': ' . implode(' | ', array_slice($bad, 0, 5)) : '') . mail_summary(), $bad ? 'warn' : 'ok');
        } elseif ($a === 'toggle') {
            run('UPDATE students SET active=1-active WHERE id=? AND school_id=?', [(int)post('id'), $s]); flash('Status changed.');
        } elseif ($a === 'reset') {
            $st = row('SELECT st.user_id, u.name FROM students st JOIN users u ON u.id=st.user_id WHERE st.id=? AND st.school_id=?', [(int)post('id'), $s]);
            if ($st) { $pw = temp_password(); run('UPDATE users SET password_hash=?, must_change=1, failed=0, locked_until=0 WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $st['user_id']]); reset_mail(row('SELECT * FROM users WHERE id=?', [$st['user_id']]), $pw); audit('password_reset', (string)$st['user_id']); flash('New temporary password for ' . $st['name'] . ': ' . $pw . '.' . mail_summary()); }
        }
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('admin/students' . (inp('class') ? '?class=' . (int)inp('class') : ''));
}
$classes = school_classes($s); $cf = (int)inp('class'); $qs = inp('q');
$w = 'st.school_id=?'; $p = [$s];
if ($cf) { $w .= ' AND st.class_id=?'; $p[] = $cf; }
if ($qs !== '') { $w .= ' AND (u.name LIKE ? OR st.admission_no LIKE ?)'; $p[] = "%$qs%"; $p[] = "%$qs%"; }
[$rows, $pager, $total] = paginate("SELECT st.*, u.name, u.username, c.name cname, c.section, pu.name pname, pu.email pemail, pu.phone pphone,
  (SELECT COUNT(*) FROM attendance a WHERE a.student_id=st.id) tot, (SELECT COUNT(*) FROM attendance a WHERE a.student_id=st.id AND a.status IN ('P','L')) pres
  FROM students st JOIN users u ON u.id=st.user_id JOIN classes c ON c.id=st.class_id LEFT JOIN users pu ON pu.id=st.parent_id WHERE $w ORDER BY (c.name+0), c.section, st.roll_no, st.id", $p, 20);
$copts = ''; foreach ($classes as $c) $copts .= '<option value="' . (int)$c['id'] . '">' . e(class_label($c)) . '</option>';
dash_header('Students');
?>
<div class="grid g2">
<div class="card"><h3>Add student</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div class="form-grid"><div><label>Student name *</label><input name="name" required></div><div><label>Class *</label><select name="class_id" required><?= $copts ?></select></div>
  <div><label>Admission no. *</label><input name="admission_no" required></div><div><label>Roll no.</label><input name="roll_no" type="number" min="0"></div>
  <div><label>Date of birth</label><input name="dob" type="date"></div><div><label>Gender</label><select name="gender"><option value="">-</option><option value="M">Male</option><option value="F">Female</option><option value="O">Other</option></select></div>
  <div><label>Parent name *</label><input name="parent_name" required></div><div><label>Parent e-mail * (alerts go here)</label><input type="email" name="parent_email" required></div>
  <div><label>Parent phone</label><input name="parent_phone"></div><div><label>Address</label><input name="address"></div>
  <?= password_fields('password', 'Student password') ?><?= password_fields('parent_password', 'Parent password') ?><?= password_options() ?></div>
  <p><button class="btn btn-primary">Add student</button></p></form></div>
<div class="card"><h3>Bulk import (CSV)</h3><p class="muted small">Columns: <code>name, class, section, admission_no, roll_no, dob, gender, parent_name, parent_email, parent_phone, address, password, parent_password</code> (name, class, section, admission_no, parent_name and parent_email are required). Leave the password columns empty to auto-generate them. Classes must already exist.</p>
  <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="import"><p><input type="file" name="csv" accept=".csv" required></p><button class="btn">Import students</button></form>
  <p class="small"><a href="data:text/csv;charset=utf-8,name%2Cclass%2Csection%2Cadmission_no%2Croll_no%2Cdob%2Cgender%2Cparent_name%2Cparent_email%2Cparent_phone%2Caddress%2Cpassword%2Cparent_password%0ASample%20Student%2C6%2CA%2C6A99%2C9%2C2013-05-01%2CM%2CSample%20Parent%2Cparent99%40example.test%2C9999999999%2CLucknow%2C%2C" download="students_template.csv">Download template</a></p></div>
</div>
<div class="card"><div class="card-head"><h3>Students (<?= $total ?>)</h3>
  <a class="btn btn-primary btn-sm" href="/admin/export?what=students<?= $cf ? '&amp;class=' . $cf : '' ?>">&#8681; Download attendance (<?= $cf ? 'this class' : 'all classes' ?>)</a>
  <form method="get" class="inline-form"><select name="class" data-autosubmit><option value="">All classes</option><?php foreach ($classes as $c) echo '<option value="' . (int)$c['id'] . '"' . ($cf === (int)$c['id'] ? ' selected' : '') . '>' . e(class_label($c)) . '</option>'; ?></select>
  <input name="q" placeholder="Search name / adm. no." value="<?= e($qs) ?>"><button class="btn btn-sm">Search</button></form></div>
<div class="table-wrap"><table><tr><th>Roll</th><th>Name</th><th>Class</th><th>Login ID</th><th>Parent</th><th>Attendance</th><th></th></tr>
<?php foreach ($rows as $r): $pc = pct((float)$r['pres'], (float)$r['tot']); ?><tr><td><?= (int)$r['roll_no'] ?></td><td><b><?= e($r['name']) ?></b><?= $r['active'] ? '' : ' ' . badge('Inactive', 'red') ?></td><td><?= e(cls($r)) ?></td><td><code><?= e(strtoupper($r['username'])) ?></code></td>
  <td><?= e($r['pname']) ?><br><span class="muted small"><?= e($r['pemail']) ?></span></td><td><?= pct_badge($pc, (int)$r['tot']) ?></td>
  <td class="right" style="white-space:nowrap"><a class="btn btn-sm" href="/admin/export?what=students&amp;student=<?= (int)$r['id'] ?>" title="Download this student's attendance">&#8681; Attendance</a> <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button name="action" value="reset" class="btn btn-sm"<?= confirm_attr('Reset this student\'s password?') ?>>Reset pwd</button>
  <button name="action" value="toggle" class="btn btn-sm"><?= $r['active'] ? 'Deactivate' : 'Activate' ?></button></form></td></tr>
<?php endforeach; if (!$rows) echo empty_row(7, 'No students found.'); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
