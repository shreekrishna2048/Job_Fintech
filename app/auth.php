<?php
function current_user(): ?array {
    static $u = false;
    if ($u === false || !empty($GLOBALS['_reload_user'])) {
        unset($GLOBALS['_reload_user']);
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $u = row('SELECT * FROM users WHERE id=?', [$_SESSION['uid']]);
            if (!$u || $u['status'] !== 'active') { $u = null; unset($_SESSION['uid']); }
        }
    }
    return $u;
}
function uid(): ?int { return isset(current_user()['id']) ? (int) current_user()['id'] : null; }
function is_role(string $r): bool { return (current_user()['role'] ?? '') === $r; }

function require_login(?string $role = null): array {
    $u = current_user();
    if (!$u) {
        if (str_starts_with(current_path(), '/api/')) json_out(['ok' => false, 'error' => 'Please sign in first.', 'login' => true], 401);
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '/';
        flash('info', 'Please sign in to continue.');
        redirect('login');
    }
    if ($role && $u['role'] !== $role && $u['role'] !== 'admin') {
        if (str_starts_with(current_path(), '/api/')) json_out(['ok' => false, 'error' => 'Not allowed for your account type.'], 403);
        abort(403, 'This area is for ' . ($role === 'employer' ? 'employers' : ($role === 'seeker' ? 'job seekers' : 'admins')) . ' only.');
    }
    if ($role === 'admin' && $u['role'] !== 'admin') abort(403);
    return $u;
}

function dashboard_path(?array $u = null): string {
    $u = $u ?? current_user();
    return match ($u['role'] ?? '') { 'admin' => 'admin', 'employer' => 'employer', default => 'seeker' };
}

/** Rate limit: max 8 failed logins per IP per 15 minutes */
function login_throttled(): bool {
    return (int) val("SELECT COUNT(*) FROM login_logs WHERE ip=? AND success=0 AND created_at > NOW() - INTERVAL 15 MINUTE", [client_ip()]) >= 8;
}

function log_login(?int $userId, ?string $email, string $method, bool $ok): void {
    insert('login_logs', ['user_id' => $userId, 'email' => $email, 'method' => $method, 'success' => $ok ? 1 : 0, 'ip' => client_ip(), 'user_agent' => user_agent(), 'device' => device_name()]);
}

function login_user(array $user, string $method = 'password', bool $remember = false): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    $GLOBALS['_reload_user'] = true;
    // New-device alert: first successful login from this IP in 60 days (skip very first login)
    $seenIp = val("SELECT 1 FROM login_logs WHERE user_id=? AND ip=? AND success=1 AND created_at > NOW() - INTERVAL 60 DAY LIMIT 1", [$user['id'], client_ip()]);
    $hasHistory = val("SELECT 1 FROM login_logs WHERE user_id=? AND success=1 LIMIT 1", [$user['id']]);
    log_login((int) $user['id'], $user['email'], $method, true);
    update('users', ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => client_ip()], 'id=?', [$user['id']]);
    log_activity('login', 'user', (int) $user['id'], "Signed in via $method", (int) $user['id']);
    if ($hasHistory && !$seenIp) {
        queue_template_email($user['email'], $user['name'], 'login_alert', [
            'name' => $user['name'], 'method' => ucfirst($method), 'device' => device_name(), 'ip' => client_ip(), 'time' => date('d M Y, h:i A'),
        ], true);
    }
    if ($remember) remember_me_set((int) $user['id']);
}

function logout_user(): void {
    if ($id = uid()) { log_activity('logout', 'user', $id); q("DELETE FROM user_tokens WHERE user_id=? AND type='remember'", [$id]); }
    setcookie('kkrem', '', time() - 3600, '/', '', false, true);
    $_SESSION = [];
    session_regenerate_id(true);
}

function remember_me_set(int $uid): void {
    $tok = bin2hex(random_bytes(32));
    insert('user_tokens', ['user_id' => $uid, 'type' => 'remember', 'token_hash' => hash('sha256', $tok), 'expires_at' => date('Y-m-d H:i:s', time() + 30 * 86400)]);
    setcookie('kkrem', $uid . ':' . $tok, ['expires' => time() + 30 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
}
function remember_me_restore(): void {
    if (!empty($_SESSION['uid']) || empty($_COOKIE['kkrem']) || !str_contains($_COOKIE['kkrem'], ':')) return;
    [$id, $tok] = explode(':', $_COOKIE['kkrem'], 2);
    try {
        $t = row("SELECT * FROM user_tokens WHERE user_id=? AND type='remember' AND token_hash=? AND expires_at > NOW()", [(int) $id, hash('sha256', $tok)]);
        if ($t && ($u = row("SELECT * FROM users WHERE id=? AND status='active'", [(int) $id]))) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $u['id'];
        }
    } catch (Throwable $e) {}
}

function create_token(int $uid, string $type, int $ttlSeconds): string {
    q('DELETE FROM user_tokens WHERE user_id=? AND type=?', [$uid, $type]);
    $tok = bin2hex(random_bytes(32));
    insert('user_tokens', ['user_id' => $uid, 'type' => $type, 'token_hash' => hash('sha256', $tok), 'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds)]);
    return $tok;
}
function consume_token(string $tok, string $type): ?int {
    $t = row('SELECT * FROM user_tokens WHERE token_hash=? AND type=? AND expires_at > NOW()', [hash('sha256', $tok), $type]);
    if (!$t) return null;
    q('DELETE FROM user_tokens WHERE id=?', [$t['id']]);
    return (int) $t['user_id'];
}

function send_verification(array $user): void {
    $tok = create_token((int) $user['id'], 'verify', 48 * 3600);
    queue_template_email($user['email'], $user['name'], 'verify_email', ['name' => $user['name'], 'link' => url('verify-email?token=' . $tok)], true);
}

/** Create account (password or social). Returns user row. */
function create_user(string $role, string $name, string $email, ?string $password, array $extra = []): array {
    $role = in_array($role, ['seeker', 'employer'], true) ? $role : 'seeker';
    $id = insert('users', array_merge([
        'role' => $role, 'name' => mb_substr($name, 0, 120), 'email' => mb_strtolower($email),
        'password_hash' => $password ? password_hash($password, PASSWORD_DEFAULT) : null,
    ], $extra));
    if ($role === 'seeker') insert('seeker_profiles', ['user_id' => $id]);
    $user = row('SELECT * FROM users WHERE id=?', [$id]);
    log_activity('register', 'user', $id, "New $role account", $id);
    notify_admins('new_user', "New $role registered", "$name ($email)", 'admin/users?q=' . urlencode($email));
    queue_template_email($user['email'], $user['name'], 'welcome', [
        'name' => $name,
        'role_message' => $role === 'employer' ? 'Set up your company profile and post your first job - it only takes a few minutes.' : 'Complete your profile and upload your CV to get matched with the right jobs.',
        'dashboard_url' => url($role === 'employer' ? 'employer' : 'seeker'),
    ]);
    notify($id, 'welcome', 'Welcome to ' . setting('site_name', 'KaamKhoj') . '!', $role === 'employer' ? 'Create your company profile to start posting jobs.' : 'Complete your profile to get better job matches.', $role === 'employer' ? 'employer/company' : 'seeker/profile');
    return $user;
}

function signup_allowed(string $role): bool { return setting($role === 'employer' ? 'allow_employer_signup' : 'allow_seeker_signup', '1') === '1'; }
function password_login_enabled(): bool { return setting('password_login', '1') === '1' || !oauth_providers(); }
/** Remember how this device last signed in, to highlight that button next time */
function remember_last_login(string $method, string $email): void {
    $opt = ['expires' => time() + 180 * 86400, 'path' => '/', 'httponly' => false, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])];
    setcookie('kk_last', $method, $opt);
    setcookie('kk_email', $email, $opt + ['httponly' => true]);
}
