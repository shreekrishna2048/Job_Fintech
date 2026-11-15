<?php
if (current_user()) redirect(dashboard_path());
$roles = array_values(array_filter(['seeker', 'employer'], 'signup_allowed'));
$errors = [];
$role = in_array($_GET['role'] ?? old('role'), $roles, true) ? ($_GET['role'] ?? old('role')) : ($roles[0] ?? 'seeker');
if (is_post() && $roles) {
    csrf_check();
    $role = in_array(input('role'), $roles, true) ? input('role') : $roles[0];
    $name = mb_substr(preg_replace('/\s+/', ' ', input('name')), 0, 120); $email = mb_strtolower(input('email')); $pass = (string) ($_POST['password'] ?? '');
    $company = mb_substr(input('company'), 0, 160); $phone = mb_substr(preg_replace('/[^\d+ -]/', '', input('phone')), 0, 30);
    if ($name === mb_strtolower($name)) $name = mb_convert_case($name, MB_CASE_TITLE);
    if (mb_strlen($name) < 2) $errors['name'] = 'Enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
    elseif (val('SELECT 1 FROM users WHERE email=?', [$email])) $errors['email'] = 'This email already has an account. <a href="' . url('login') . '">Sign in instead</a>';
    if (!password_login_enabled()) $errors['password'] = 'Email sign-up is turned off. Please use Google or Microsoft above.';
    elseif (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) $errors['password'] = 'Use at least 8 characters with letters and numbers.';
    if ($role === 'employer' && mb_strlen($company) < 2) $errors['company'] = 'Enter your company name.';
    if (empty($_POST['terms'])) $errors['terms'] = 'Please accept the terms to continue.';
    if (input('website') !== '') $errors['name'] = 'Invalid submission.'; // honeypot
    if ((int) val("SELECT COUNT(*) FROM activity_logs WHERE action='register' AND ip=? AND created_at > NOW() - INTERVAL 1 HOUR", [client_ip()]) >= 5) $errors['email'] = 'Too many sign-ups from your network. Please try later.';
    if (!$errors) {
        $user = create_user($role, $name, $email, $pass, ['phone' => $phone ?: null]);
        if ($role === 'employer') insert('companies', ['owner_id' => $user['id'], 'name' => $company, 'slug' => unique_slug('companies', $company), 'email' => $email, 'phone' => $phone ?: null]);
        send_verification($user);
        login_user($user, 'password', true);
        remember_last_login('password', $email);
        flash('success', 'Account created! We sent a verification link to ' . $email . '.');
        redirect($role === 'employer' ? 'employer/company' : 'seeker/profile');
    }
    keep_old();
}
$perks = [
    'seeker' => ['One profile, apply in one tap', 'See every application status live', 'Job alerts & AI cover letters'],
    'employer' => ['Free company page with photos', 'AI writes your job post', 'Applicant pipeline & candidate search'],
];
auth_start('Create account', 'Create your free account', 'Takes less than a minute.');
if (!$roles): ?>
  <div class="alert alert-info">New sign-ups are paused right now. Please check back soon or <a href="<?= url('contact') ?>">contact us</a>.</div>
<?php else: ?>
<?php if (count($roles) > 1): ?>
<div class="role-cards" data-role-switch role="tablist">
  <?php foreach ($roles as $r): ?><button type="button" class="role-card<?= $role === $r ? ' on' : '' ?>" data-role="<?= $r ?>" role="tab" aria-selected="<?= $role === $r ? 'true' : 'false' ?>"><?= icon($r === 'employer' ? 'building' : 'user') ?><b><?= $r === 'employer' ? 'I’m hiring' : 'I want a job' ?></b><ul><?php foreach ($perks[$r] as $pk): ?><li><?= e($pk) ?></li><?php endforeach; ?></ul></button><?php endforeach; ?>
</div>
<?php endif; ?>
<div data-oauth-holder><?= oauth_buttons($role, 'Sign up', password_login_enabled()) ?></div>
<?php if (password_login_enabled()): ?>
<form method="post" class="form" data-loading><?= csrf_field() ?>
  <input type="hidden" name="role" value="<?= e($role) ?>" data-role-input>
  <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
  <div class="field"><label for="name">Full name</label><input id="name" name="name" required autocomplete="name" value="<?= e(old('name')) ?>" placeholder="e.g. Sita Sharma"><?php if (!empty($errors['name'])): ?><p class="err"><?= e($errors['name']) ?></p><?php endif; ?></div>
  <div class="field" data-employer-only <?= $role === 'employer' ? '' : 'hidden' ?>><label for="company">Company name</label><input id="company" name="company" autocomplete="organization" value="<?= e(old('company')) ?>" placeholder="e.g. Himal Tech Pvt. Ltd."><?php if (!empty($errors['company'])): ?><p class="err"><?= e($errors['company']) ?></p><?php endif; ?></div>
  <div class="field"><label for="email"><span data-employer-only <?= $role === 'employer' ? '' : 'hidden' ?>>Work </span>Email</label><input id="email" type="email" name="email" required autocomplete="email" inputmode="email" value="<?= e(old('email')) ?>" data-email-check>
    <p class="err" data-email-msg <?= empty($errors['email']) ? 'hidden' : '' ?>><?= !empty($errors['email']) ? strip_tags($errors['email'], '<a>') : '' ?></p></div>
  <div class="field"><label for="phone">Mobile <span class="muted">(optional - employers may call you)</span></label><input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="98XXXXXXXX" value="<?= e(old('phone')) ?>"></div>
  <div class="field"><label for="pw">Create password</label><div class="pw-wrap"><input id="pw" type="password" name="password" required minlength="8" autocomplete="new-password" data-pw-meter data-capslock><button type="button" class="pw-toggle" data-pw-toggle aria-label="Show password"><?= icon('eye', 'i i-sm') ?></button></div>
    <div class="pw-meter"><span></span></div><p class="hint small muted" data-pw-hint>8+ characters with letters and numbers</p><p class="hint-warn" data-caps hidden>⇪ Caps Lock is on</p>
    <?php if (!empty($errors['password'])): ?><p class="err"><?= e($errors['password']) ?></p><?php endif; ?></div>
  <label class="check"><input type="checkbox" name="terms" value="1" <?= old('terms') ? 'checked' : '' ?> required> I agree to the <a href="<?= url('page/terms') ?>" target="_blank">Terms</a> and <a href="<?= url('page/privacy') ?>" target="_blank">Privacy Policy</a></label>
  <?php if (!empty($errors['terms'])): ?><p class="err"><?= e($errors['terms']) ?></p><?php endif; ?>
  <button class="btn btn-brand btn-block btn-lg" data-submit-label><span data-employer-only <?= $role === 'employer' ? '' : 'hidden' ?>>Create employer account</span><span data-seeker-only <?= $role === 'seeker' ? '' : 'hidden' ?>>Create my profile</span></button>
</form>
<?php endif; ?>
<?php endif; ?>
<p class="auth-alt">Already have an account? <a href="<?= url('login') ?>">Sign in</a></p>
<?php auth_end();
