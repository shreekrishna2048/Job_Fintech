<?php
$provider = param('provider');
$name = ucfirst($provider);
$testing = fn() => !empty($_SESSION['oauth_test']);
$u0 = current_user();

if (!str_ends_with(current_path(), '/callback')) {
    // Admin "Test sign-in": works even while the provider is switched off
    if (isset($_GET['test']) && $u0 && admin_can('login', $u0)) {
        if (!oauth_configured($provider)) { flash('error', "Enter the $name Client ID and Secret first."); redirect('admin/login'); }
        $_SESSION['oauth_test'] = $provider;
    } elseif (!isset(oauth_providers()[$provider])) { flash('error', "$name sign-in is not available right now. Please use email."); redirect('login'); }
    else unset($_SESSION['oauth_test']);
    if (in_array($_GET['role'] ?? '', ['seeker', 'employer'], true)) $_SESSION['oauth_role'] = $_GET['role'];
    redirect(oauth_auth_url($provider));
}

$test = $_SESSION['oauth_test'] ?? null; unset($_SESSION['oauth_test']);
try {
    if (!empty($_GET['error'])) throw new RuntimeException(oauth_error_text((string) $_GET['error'], (string) ($_GET['error_description'] ?? '')));
    if (empty($_GET['state']) || !hash_equals($_SESSION['oauth_state'] ?? '', (string) $_GET['state'])) throw new RuntimeException('Sign-in link expired. Please try again.');
    unset($_SESSION['oauth_state']);
    $info = oauth_fetch_user($provider, (string) ($_GET['code'] ?? ''));
} catch (Throwable $e) {
    if ($test) { save_setting($provider . '_last_test', date('Y-m-d H:i') . '|fail|' . $e->getMessage()); flash('error', "$name test failed: " . $e->getMessage()); redirect('admin/login'); }
    log_login(null, null, $provider, false);
    flash('error', $e->getMessage());
    redirect('login');
}
if ($test) {
    save_setting($provider . '_last_test', date('Y-m-d H:i') . '|ok|' . $info['email']);
    log_activity('oauth_test', null, null, "$provider OK: {$info['email']}");
    flash('success', "✅ $name sign-in works! It returned {$info['name']} <{$info['email']}>.");
    redirect('admin/login');
}

$col = $provider . '_id';
$user = row("SELECT * FROM users WHERE $col=?", [$info['id']]) ?? row('SELECT * FROM users WHERE email=?', [mb_strtolower($info['email'])]);
if ($user) {
    if ($user['status'] !== 'active') { log_login((int) $user['id'], $user['email'], $provider, false); flash('error', 'Your account is suspended. Contact support.'); redirect('login'); }
    $upd = [$col => $info['id']];
    $linked = empty($user[$col]);
    if (!$user['email_verified_at']) $upd['email_verified_at'] = date('Y-m-d H:i:s');
    update('users', $upd, 'id=?', [$user['id']]);
    login_user($user, $provider, true);
    remember_last_login($provider, $user['email']);
    flash('success', $linked ? "$name is now connected to your account. Welcome back!" : 'Welcome back, ' . explode(' ', $user['name'])[0] . '!');
    $to = $_SESSION['intended'] ?? null; unset($_SESSION['intended']);
    if ($to && str_starts_with($to, '/') && !str_starts_with($to, '//')) { header('Location: ' . $to); exit; }
    redirect(dashboard_path($user));
}

// New user
$role = $_SESSION['oauth_role'] ?? null; unset($_SESSION['oauth_role']);
if ($role && !signup_allowed($role)) $role = null;
if (!$role) { $_SESSION['oauth_pending'] = $info + ['provider' => $provider]; redirect('auth/choose-role'); }
$user = create_user($role, $info['name'], $info['email'], null, [$col => $info['id'], 'email_verified_at' => date('Y-m-d H:i:s')]);
login_user($user, $provider, true);
remember_last_login($provider, $user['email']);
flash('success', 'Welcome! Your account is ready.');
redirect($role === 'employer' ? 'employer/company' : 'seeker/profile');
