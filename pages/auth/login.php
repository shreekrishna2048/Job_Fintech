<?php
if (current_user()) redirect(dashboard_path());
$error = '';
$showPw = password_login_enabled() || isset($_GET['password']);
if (is_post()) {
    csrf_check();
    $email = mb_strtolower(input('email')); $pass = (string) ($_POST['password'] ?? '');
    if (login_throttled()) $error = 'Too many failed attempts. Please wait 15 minutes or reset your password.';
    else {
        $user = row('SELECT * FROM users WHERE email=?', [$email]);
        if ($user && $user['password_hash'] && password_verify($pass, $user['password_hash'])) {
            if ($user['status'] !== 'active') { log_login((int) $user['id'], $email, 'password', false); $error = 'Your account is suspended. Please contact support.'; }
            elseif (!password_login_enabled() && $user['role'] !== 'admin') { $error = 'Please sign in with Google or Microsoft.'; }
            else {
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) update('users', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id=?', [$user['id']]);
                login_user($user, 'password', !empty($_POST['remember']));
                remember_last_login('password', $user['email']);
                $to = $_SESSION['intended'] ?? null; unset($_SESSION['intended']);
                flash('success', 'Welcome back, ' . explode(' ', $user['name'])[0] . '!');
                if ($to && str_starts_with($to, '/') && !str_starts_with($to, '//')) { header('Location: ' . $to); exit; }
                redirect(dashboard_path($user));
            }
        } else {
            log_login($user['id'] ?? null, $email, 'password', false);
            if ($user && !$user['password_hash']) {
                $via = array_keys(array_filter(['Google' => $user['google_id'], 'Microsoft' => $user['microsoft_id']]));
                $error = 'This account signs in with ' . ($via ? implode(' or ', $via) : 'Google/Microsoft') . '. Use that button above - or reset your password to also use email.';
            } else $error = 'That email and password don’t match. Check for typos, or reset your password.';
        }
    }
    keep_old();
}
$remembered = $_COOKIE['kk_email'] ?? '';
auth_start('Sign in', 'Welcome back', 'Sign in to apply, track applications and manage your jobs.');
?>
<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?= oauth_buttons('', 'Continue', $showPw) ?>
<?php if ($showPw): ?>
<form method="post" class="form" data-loading novalidate data-validate><?= csrf_field() ?>
  <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autocomplete="username" inputmode="email" value="<?= e(old('email', $remembered)) ?>" <?= old('email', $remembered) ? '' : 'autofocus' ?>><p class="err" data-err hidden></p></div>
  <div class="field"><div class="label-row"><label for="pw">Password</label><a href="<?= url('forgot-password') ?>" class="small">Forgot password?</a></div>
    <div class="pw-wrap"><input id="pw" type="password" name="password" required autocomplete="current-password" data-capslock <?= old('email', $remembered) ? 'autofocus' : '' ?>><button type="button" class="pw-toggle" data-pw-toggle aria-label="Show password"><?= icon('eye', 'i i-sm') ?></button></div>
    <p class="hint-warn" data-caps hidden>⇪ Caps Lock is on</p></div>
  <label class="check"><input type="checkbox" name="remember" value="1" checked> Keep me signed in on this device</label>
  <button class="btn btn-dark btn-block btn-lg">Sign in</button>
</form>
<?php else: ?>
<p class="muted small center"><a href="?password=1">Admin? Sign in with password</a></p>
<?php endif; ?>
<?php if (signup_allowed('seeker') || signup_allowed('employer')): ?><p class="auth-alt">New here? <a href="<?= url('register') ?>">Create a free account</a></p><?php endif; ?>
<?php auth_end();
