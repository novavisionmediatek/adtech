<?php
$s = sid();
[$rows, $pager] = paginate('SELECT * FROM msg_log WHERE school_id=? ORDER BY id DESC', [$s], 20);
$on = fn(string $col) => (int)val("SELECT COUNT(*) FROM user_prefs p JOIN users u ON u.id=p.user_id WHERE u.school_id=? AND u.role='parent' AND p.$col=1", [$s]);
$parents = (int)val("SELECT COUNT(*) FROM users WHERE school_id=? AND role='parent' AND active=1", [$s]);
dash_header('SMS & WhatsApp');
?>
<div class="stats"><?= kpi('Parents', $parents) ?><?= kpi('Agreed to SMS', $on('sms'), '', 'green') ?><?= kpi('Agreed to WhatsApp', $on('whatsapp'), '', 'green') ?></div>
<?php foreach (ALERT_CHANNELS as $k => $l) if (!gateway_ready($k)) { echo '<div class="alert alert-warn">' . $l . ' provider is not set up, so ' . $l . ' messages are only recorded here and <b>not delivered</b>.</div>'; } ?>
<div class="card"><h3>Message log</h3><p class="muted small">Parents switch these alerts on themselves under "Alert settings" in their login. Absence alerts are sent automatically.</p>
<div class="table-wrap"><table class="stack"><tr><th>When</th><th>Channel</th><th>Mobile</th><th>Message</th><th>Status</th></tr>
<?php foreach ($rows as $r): ?><tr><td class="who-cell"><b><?= e(fmt_date($r['created_at'], 'd M, h:i A')) ?></b></td><td data-label="Channel"><?= e(ALERT_CHANNELS[$r['channel']] ?? $r['channel']) ?></td><td data-label="Mobile"><?= e($r['phone'] ?? '-') ?></td><td data-label="Message" class="wide"><?= e($r['message']) ?></td><td data-label="Status"><?= status_badge($r['status']) ?><?= $r['error'] ? '<br><span class="muted small">' . e($r['error']) . '</span>' : '' ?></td></tr>
<?php endforeach; if (!$rows) echo empty_row(5, 'No messages yet.'); ?></table></div><?= $pager ?></div>
<?php dash_footer(); ?>
