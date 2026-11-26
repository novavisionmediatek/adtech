<?php
$u = me();
if (is_post()) {
    $phone = post('phone'); $sms = !empty($_POST['sms']); $wa = !empty($_POST['whatsapp']);
    if (($sms || $wa) && !phone_number($phone)) flash('Enter a valid 10-digit mobile number to receive SMS or WhatsApp alerts.', 'err');
    else {
        run('UPDATE users SET phone=? WHERE id=?', [mb_substr($phone, 0, 20), $u['id']]);
        save_prefs((int)$u['id'], $sms, $wa, post('lang')); audit('alert_settings', ($sms ? 'sms ' : '') . ($wa ? 'whatsapp ' : '') . post('lang'));
        flash('Your alert settings are saved.');
    }
    redirect('parent/alerts');
}
$p = user_prefs((int)$u['id']);
dash_header('Alert settings');
?>
<div class="card" style="max-width:640px"><h3>How should the school reach you?</h3>
  <p class="muted">E-mail alerts are always sent. SMS and WhatsApp are optional and sent only if you tick them. You can untick them any time.</p>
  <form method="post"><?= csrf_field() ?>
    <p><label>Your mobile number</label><input name="phone" inputmode="tel" value="<?= e($u['phone'] ?? '') ?>" placeholder="10-digit mobile number"></p>
    <div class="pw-opts"><?php foreach (ALERT_CHANNELS as $k => $l): ?><label><input type="checkbox" name="<?= $k ?>" value="1"<?= $p[$k] ? ' checked' : '' ?>> I agree to receive <?= $l ?> alerts about my child</label><?php endforeach; ?></div>
    <p><label>Language of alert messages</label><select name="lang"><option value="en">English</option><option value="hi"<?= $p['lang'] === 'hi' ? ' selected' : '' ?>>हिंदी (Hindi)</option></select></p>
    <button class="btn btn-primary">Save settings</button></form></div>
<?php dash_footer(); ?>
