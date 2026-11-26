<?php
if (is_post() && post('action') === 'test') {
    $to = post('to');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) flash('Enter a valid e-mail address.', 'err');
    else {
        $id = send_mail($to, 'Test', 'Test e-mail from ' . cfg('app_name'), mail_wrap('Test e-mail', '<p>If you can read this, e-mail delivery is working.</p>'));
        $m = row('SELECT status, error FROM outbox WHERE id=?', [$id]);
        if ($m['status'] === 'sent') flash('Test e-mail sent to ' . $to . '. Check the inbox and spam folder.');
        else flash(($m['status'] === 'failed' ? 'Sending failed: ' : 'Not sent: ') . $m['error'], 'err');
    }
    redirect('super/outbox');
}
$smtpOn = !empty(cfg('smtp', [])['host']);
[$rows, $pager] = paginate('SELECT * FROM outbox ORDER BY id DESC', [], 20);
dash_header('Email log (all schools)');
?>
<div class="card"><h3>E-mail delivery <?= $smtpOn ? badge('SMTP set up', 'green') : badge('Not set up', 'red') ?></h3>
  <p class="muted small"><?= $smtpOn ? 'Mails are sent through your SMTP server. Passwords are hidden in this log.' : 'Nothing is delivered yet: mails are only saved below. Fill in the <code>smtp</code> block in <code>config.php</code> to send real e-mails.' ?></p>
  <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="test"><div><label>Send a test e-mail to</label><input type="email" name="to" required placeholder="you@example.com"></div><div style="flex:0"><button class="btn btn-primary">Send test</button></div></form></div>
<div class="card"><?= outbox_table($rows) ?><?= $pager ?></div>
<?php dash_footer(); ?>
