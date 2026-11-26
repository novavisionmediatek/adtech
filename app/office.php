<?php
declare(strict_types=1);
/* School office: online admission applications and student certificates. */

const ADMISSION_STATUS = ['new' => ['New', 'amber'], 'accepted' => ['Admitted', 'green'], 'rejected' => ['Not accepted', 'red']];
const CERT_TYPES = ['bonafide' => ['Bonafide certificate', 'BC'], 'character' => ['Character certificate', 'CC'], 'transfer' => ['Transfer certificate', 'TC']];

/** Public application form. Returns the reference number. */
function submit_admission(array $d): string {
    $get = fn(string $k, int $max = 150) => mb_substr(trim((string)(is_string($d[$k] ?? null) ? $d[$k] : '')), 0, $max);
    $school = row("SELECT id, code, name FROM schools WHERE id=? AND status='active'", [(int)($d['school_id'] ?? 0)]);
    if (!$school) throw new RuntimeException('Choose a school.');
    foreach (['student_name' => 'student name', 'class_sought' => 'class', 'parent_name' => 'parent name', 'parent_phone' => 'mobile number'] as $k => $label) if ($get($k) === '') throw new RuntimeException("Please fill the $label.");
    if (!filter_var($get('parent_email'), FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid e-mail address.');
    if (!phone_number($get('parent_phone', 20))) throw new RuntimeException('Enter a valid mobile number.');
    $dob = $get('dob', 10); if (!valid_date($dob) || $dob >= date('Y-m-d')) throw new RuntimeException('Enter the date of birth.');
    if (empty($d['agree'])) throw new RuntimeException('Please agree to the use of these details for admission.');
    $ip = client_ip(); if (throttle_count("admission:$ip", 3600) >= 5) throw new RuntimeException('Too many applications from this connection. Please try again later.');
    $email = strtolower($get('parent_email'));
    if (val("SELECT 1 FROM admissions WHERE school_id=? AND status='new' AND lower(student_name)=? AND dob=? AND lower(parent_email)=?", [$school['id'], mb_strtolower($get('student_name')), $dob, $email])) throw new RuntimeException('An application for this child is already waiting.');
    throttle_hit("admission:$ip");
    $id = ins('INSERT INTO admissions(school_id,student_name,dob,gender,class_sought,parent_name,parent_email,parent_phone,address,previous_school,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
        [$school['id'], $get('student_name'), $dob, in_array($d['gender'] ?? '', ['M', 'F', 'O'], true) ? $d['gender'] : '', $get('class_sought', 40), $get('parent_name'), $email, $get('parent_phone', 20), $get('address', 255), $get('previous_school', 200), date('Y-m-d H:i:s')]);
    $ref = $school['code'] . '-ADM-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
    run('UPDATE admissions SET ref_no=? WHERE id=?', [$ref, $id]);
    send_mail($email, $get('parent_name'), 'Admission application received: ' . $ref, mail_wrap('Application received', '<p>Dear ' . e($get('parent_name')) . ',</p><p><b>' . e($school['name']) . '</b> has received the admission application for <b>' . e($get('student_name')) . '</b>.</p><p>Reference number: <b>' . e($ref) . '</b></p><p>The school will contact you.</p>'), (int)$school['id']);
    return $ref;
}

/** Issues a certificate and keeps a copy of the details as they are today. */
function issue_certificate(int $school, array $d): int {
    $type = (string)($d['type'] ?? ''); $adm = preg_replace('/\s+/', '', (string)($d['admission_no'] ?? ''));
    if (!isset(CERT_TYPES[$type])) throw new RuntimeException('Choose the certificate.');
    $st = row('SELECT st.*, u.name, u.created_at joined, c.name cname, c.section, p.name pname FROM students st JOIN users u ON u.id=st.user_id JOIN classes c ON c.id=st.class_id LEFT JOIN users p ON p.id=st.parent_id WHERE st.school_id=? AND lower(st.admission_no)=?', [$school, strtolower($adm)]);
    if (!$st) throw new RuntimeException("No student with admission number $adm.");
    $txt = fn(string $k) => mb_substr(trim((string)($d[$k] ?? '')), 0, 200);
    $details = ['name' => $st['name'], 'parent' => (string)$st['pname'], 'class' => cls($st), 'admission_no' => $st['admission_no'], 'dob' => (string)$st['dob'], 'gender' => (string)$st['gender'], 'purpose' => $txt('purpose'), 'conduct' => $txt('conduct') ?: 'Good', 'remarks' => $txt('remarks')];
    if ($type === 'transfer') {
        $leave = (string)($d['leaving_date'] ?? ''); if (!valid_date($leave)) throw new RuntimeException('A transfer certificate needs the date of leaving.');
        if ($txt('reason') === '') throw new RuntimeException('A transfer certificate needs the reason for leaving.');
        $details += ['leaving_date' => $leave, 'reason' => $txt('reason')];
        if (val("SELECT 1 FROM certificates WHERE student_id=? AND type='transfer' AND revoked=0", [$st['id']])) throw new RuntimeException('This student already has a transfer certificate. Cancel it before issuing a new one.');
    }
    $id = ins('INSERT INTO certificates(school_id,student_id,type,issued_on,details,issued_by,created_at) VALUES(?,?,?,?,?,?,?)', [$school, $st['id'], $type, date('Y-m-d'), json_encode($details, JSON_UNESCAPED_UNICODE), me()['id'], date('Y-m-d H:i:s')]);
    $serial = (string)val('SELECT code FROM schools WHERE id=?', [$school]) . '/' . CERT_TYPES[$type][1] . '/' . date('Y') . '/' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
    run('UPDATE certificates SET serial_no=? WHERE id=?', [$serial, $id]);
    if ($type === 'transfer' && !empty($d['deactivate'])) run('UPDATE students SET active=0 WHERE id=?', [$st['id']]);
    audit('certificate_issued', $serial . ' ' . $st['name']);
    return $id;
}
/** Certificates of one student (student and parent screens). */
function view_certificates(array $st): string {
    $rows = q('SELECT * FROM certificates WHERE student_id=? AND revoked=0 ORDER BY id DESC', [$st['id']]);
    $o = '<div class="card"><h3>Certificates</h3><p class="muted small">Certificates issued by the school. Ask the school office if you need one.</p><div class="table-wrap"><table><tr><th>Certificate</th><th>Number</th><th>Issued on</th><th></th></tr>';
    foreach ($rows as $r) $o .= '<tr><td><b>' . e(CERT_TYPES[$r['type']][0] ?? $r['type']) . '</b></td><td><code>' . e($r['serial_no']) . '</code></td><td>' . e(fmt_date($r['issued_on'])) . '</td><td class="right"><a class="btn btn-sm" href="/print/certificate/' . (int)$r['id'] . '">View / print</a></td></tr>';
    if (!$rows) $o .= empty_row(4, 'No certificates yet.');
    return $o . '</table></div></div>';
}
