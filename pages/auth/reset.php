<?php
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$valid = $token && row("SELECT 1 FROM user_tokens WHERE token_hash=? AND type='reset' AND expires_at > NOW()", [hash('sha256', $token)]);
$error = '';
if (is_post() && $valid) {
    csrf_check();
    $pass = (string) ($_POST['password'] ?? '');
    if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) $error = 'Use at least 8 characters with letters and numbers.';
    elseif ($pass !== ($_POST['password_confirm'] ?? '')) $error = 'Passwords don’t match.';
    elseif ($uid = consume_token($token, 'reset')) {
        update('users', ['email_verified_at' => date('Y-m-d H:i:s')], 'id=? AND email_verified_at IS NULL', [$uid]);
        update('users', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id=?', [$uid]);
        q("DELETE FROM user_tokens WHERE user_id=? AND type='remember'", [$uid]);
        log_activity('password_reset', 'user', $uid, null, $uid);
        flash('success', 'Password updated. Please sign in.');
        redirect('login');
    }
}
auth_start('Set new password', 'Choose a new password');
if (!$valid): ?>
  <div class="alert alert-error">This reset link is invalid or expired.</div><a class="btn btn-dark btn-block" href="<?= url('forgot-password') ?>">Request a new link</a>
<?php else: ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
  <div class="field"><label>New password</label><div class="pw-wrap"><input type="password" name="password" required minlength="8" data-pw-meter autocomplete="new-password"><button type="button" class="pw-toggle" data-pw-toggle><?= icon('eye', 'i i-sm') ?></button></div><div class="pw-meter"><span></span></div></div>
  <div class="field"><label>Confirm password</label><input type="password" name="password_confirm" required autocomplete="new-password"></div>
  <button class="btn btn-dark btn-block btn-lg">Update password</button>
</form>
<?php endif; auth_end();
