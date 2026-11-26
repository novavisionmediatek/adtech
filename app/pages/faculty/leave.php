<?php
$u = me();
if (is_post()) {
    try {
        if (post('action') === 'cancel') cancel_request((int)post('id'), (int)$u['id']);
        else { request_leave($u, 'faculty', null, $_POST); flash('Your leave request was sent to the school admin.'); }
    } catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
    redirect('faculty/leave');
}
dash_header('My leave');
echo leave_panel((int)$u['id'], null);
dash_footer();
