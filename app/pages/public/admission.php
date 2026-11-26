<?php
$err = null; $f = $_POST;
if (is_post()) {
    if (post('website') !== '') redirect('admission'); // hidden field only robots fill in
    try { $ref = submit_admission($_POST); flash("Application sent. Your reference number is $ref. Please note it down. The school will contact you."); redirect('admission'); }
    catch (RuntimeException $x) { $err = $x->getMessage(); }
}
$schools = q("SELECT id, name, city FROM schools WHERE status='active' ORDER BY name");
$v = fn(string $k) => e(is_string($f[$k] ?? null) ? $f[$k] : '');
public_header('Apply for admission', 'Online admission application');
?>
<section class="page-hero"><div class="container"><h1>Apply for admission</h1><p>Send the child's details to the school online.</p></div></section>
<section class="auth wide"><div class="card">
  <?php if ($err): ?><div class="alert alert-err" style="width:auto"><?= e($err) ?></div><?php endif; ?>
  <?php if (!$schools): ?><p class="muted">No school is accepting applications online right now.</p><?php else: ?>
  <form method="post"><?= csrf_field() ?><div class="hp"><label>Leave this empty<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="form-grid">
      <div class="full"><label>School *</label><select name="school_id" required><?php foreach ($schools as $s) echo '<option value="' . (int)$s['id'] . '"' . ((int)($f['school_id'] ?? 0) === (int)$s['id'] ? ' selected' : '') . '>' . e($s['name'] . ($s['city'] ? ', ' . $s['city'] : '')) . '</option>'; ?></select></div>
      <div><label>Student name *</label><input name="student_name" required maxlength="150" value="<?= $v('student_name') ?>"></div>
      <div><label>Date of birth *</label><input type="date" name="dob" required max="<?= date('Y-m-d') ?>" value="<?= $v('dob') ?>"></div>
      <div><label>Gender</label><select name="gender"><?php foreach (['' => '-', 'M' => 'Male', 'F' => 'Female', 'O' => 'Other'] as $k => $l) echo '<option value="' . $k . '"' . (($f['gender'] ?? '') === $k ? ' selected' : '') . '>' . $l . '</option>'; ?></select></div>
      <div><label>Class wanted *</label><input name="class_sought" required maxlength="40" placeholder="e.g. 6" value="<?= $v('class_sought') ?>"></div>
      <div><label>Parent name *</label><input name="parent_name" required maxlength="150" value="<?= $v('parent_name') ?>"></div>
      <div><label>Parent mobile *</label><input name="parent_phone" required inputmode="tel" maxlength="20" value="<?= $v('parent_phone') ?>"></div>
      <div><label>Parent e-mail *</label><input type="email" name="parent_email" required maxlength="150" value="<?= $v('parent_email') ?>"></div>
      <div><label>Previous school</label><input name="previous_school" maxlength="200" value="<?= $v('previous_school') ?>"></div>
      <div class="full"><label>Address</label><input name="address" maxlength="255" value="<?= $v('address') ?>"></div>
      <div class="full pw-opts"><label><input type="checkbox" name="agree" value="1" required><span>I am the parent or guardian and agree that the school may use these details to process the admission (<a href="/privacy">privacy notice</a>).</span></label></div>
    </div>
    <p><button class="btn btn-primary btn-lg">Send application</button></p>
  </form><?php endif; ?>
</div></section>
<?php public_footer(); ?>
