<?php
$info = $_SESSION['oauth_pending'] ?? null;
if (!$info) redirect('login');
if (is_post()) {
    csrf_check();
    $role = input('role') === 'employer' ? 'employer' : 'seeker';
    if (!signup_allowed($role)) { flash('error', 'That account type can’t be created right now.'); redirect('auth/choose-role'); }
    if (val('SELECT 1 FROM users WHERE email=?', [mb_strtolower($info['email'])])) { unset($_SESSION['oauth_pending']); flash('error', 'Account already exists. Please sign in.'); redirect('login'); }
    $user = create_user($role, $info['name'], $info['email'], null, [$info['provider'] . '_id' => $info['id'], 'email_verified_at' => date('Y-m-d H:i:s')]);
    if ($role === 'employer' && ($co = input('company'))) insert('companies', ['owner_id' => $user['id'], 'name' => mb_substr($co, 0, 160), 'slug' => unique_slug('companies', $co), 'email' => $user['email']]);
    unset($_SESSION['oauth_pending']);
    login_user($user, $info['provider'], true);
    remember_last_login($info['provider'], $user['email']);
    flash('success', 'Welcome! Your account is ready.');
    redirect($role === 'employer' ? 'employer/company' : 'seeker/profile');
}
auth_start('Choose account type', 'One last step', 'Hi ' . e(explode(' ', $info['name'])[0]) . ' - how will you use ' . e(setting('site_name')) . '?');
?>
<form method="post" class="form"><?= csrf_field() ?>
  <?php if (signup_allowed('seeker')): ?><label class="radio-card"><input type="radio" name="role" value="seeker" checked data-role-radio><span><?= icon('user') ?><b>I’m looking for a job</b><small>Build a profile, apply and track applications</small></span></label><?php endif; ?>
  <?php if (signup_allowed('employer')): ?><label class="radio-card"><input type="radio" name="role" value="employer" data-role-radio <?= signup_allowed('seeker') ? '' : 'checked' ?>><span><?= icon('building') ?><b>I’m hiring</b><small>Post jobs and manage applicants</small></span></label><?php endif; ?>
  <div class="field" data-employer-only hidden><label>Company name</label><input name="company"></div>
  <button class="btn btn-brand btn-block btn-lg">Continue</button>
</form>
<?php auth_end();
