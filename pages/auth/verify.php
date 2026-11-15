<?php
if (isset($_GET['resend'])) {
    $u = require_login();
    if (!$u['email_verified_at']) { send_verification($u); flash('success', 'Verification email sent to ' . $u['email']); }
    redirect('account');
}
$uid = consume_token((string) ($_GET['token'] ?? ''), 'verify');
if ($uid) {
    update('users', ['email_verified_at' => date('Y-m-d H:i:s')], 'id=?', [$uid]);
    log_activity('email_verified', 'user', $uid, null, $uid);
    flash('success', 'Email verified. You’re all set!');
} else flash('error', 'Verification link is invalid or expired. Sign in and resend it from Account settings.');
redirect(current_user() ? dashboard_path() : 'login');
