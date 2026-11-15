<?php
$done = false;
if (is_post()) {
    csrf_check();
    $email = mb_strtolower(input('email'));
    $recent = (int) val("SELECT COUNT(*) FROM activity_logs WHERE action='password_reset_request' AND ip=? AND created_at > NOW() - INTERVAL 1 HOUR", [client_ip()]);
    if ($recent < 5 && ($user = row("SELECT * FROM users WHERE email=? AND status='active'", [$email]))) {
        $tok = create_token((int) $user['id'], 'reset', 3600);
        queue_template_email($user['email'], $user['name'], 'password_reset', ['name' => $user['name'], 'link' => url('reset-password?token=' . $tok)], true);
    }
    log_activity('password_reset_request', 'user', $user['id'] ?? null, $email);
    $done = true; // same response either way (no account enumeration)
}
auth_start('Forgot password', 'Reset your password', 'We’ll email you a secure link.');
if ($done): ?>
  <div class="alert alert-success">If an account exists for that email, a reset link is on its way. Check spam too.</div>
  <a class="btn btn-ghost btn-block" href="<?= url('login') ?>">Back to sign in</a>
<?php else: ?>
<form method="post" class="form" data-loading><?= csrf_field() ?>
  <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autofocus></div>
  <button class="btn btn-dark btn-block btn-lg">Send reset link</button>
</form>
<p class="auth-alt"><a href="<?= url('login') ?>">← Back to sign in</a></p>
<?php endif; auth_end();
