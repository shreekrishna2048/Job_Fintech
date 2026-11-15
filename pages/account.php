<?php
$u = require_login();
if (is_post()) {
    csrf_check();
    $act = input('action');
    if ($act === 'profile') {
        $d = ['name' => mb_substr(input('name'), 0, 120) ?: $u['name'], 'phone' => mb_substr(input('phone'), 0, 30) ?: null, 'email_notifications' => !empty($_POST['email_notifications']) ? 1 : 0];
        try {
            if ($a = handle_upload('avatar', 'avatars', IMG_TYPES, 3)) { delete_upload($u['avatar']); $d['avatar'] = $a; }
            update('users', $d, 'id=?', [$u['id']]);
            log_activity('account_update', 'user', (int) $u['id']);
            flash('success', 'Account updated.');
        } catch (RuntimeException $e) { flash('error', $e->getMessage()); }
    } elseif ($act === 'password') {
        $cur = (string) ($_POST['current'] ?? ''); $new = (string) ($_POST['new'] ?? '');
        if ($u['password_hash'] && !password_verify($cur, $u['password_hash'])) flash('error', 'Current password is incorrect.');
        elseif (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) flash('error', 'New password: at least 8 characters with letters and numbers.');
        else {
            update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id=?', [$u['id']]);
            q("DELETE FROM user_tokens WHERE user_id=? AND type='remember'", [$u['id']]);
            log_activity('password_change', 'user', (int) $u['id']);
            flash('success', 'Password updated.');
        }
    } elseif ($act === 'delete') {
        if ($u['role'] === 'admin') flash('error', 'Admin accounts can’t be deleted here.');
        elseif (input('confirm') !== 'DELETE') flash('error', 'Type DELETE to confirm.');
        else {
            log_activity('account_delete', 'user', (int) $u['id'], $u['email']);
            delete_upload($u['avatar']);
            q('DELETE FROM users WHERE id=?', [$u['id']]);
            logout_user();
            flash('success', 'Your account and data were deleted.');
            redirect('');
        }
    }
    redirect('account');
}
$logins = rows('SELECT * FROM login_logs WHERE user_id=? ORDER BY id DESC LIMIT 8', [$u['id']]);
dash_start('Account settings', 'account');
?>
<div class="page-head"><h1>Account settings</h1></div>
<?php if (!$u['email_verified_at']): ?><div class="alert alert-warn">Your email <b><?= e($u['email']) ?></b> isn’t verified yet. <a href="<?= url('verify-email?resend=1') ?>">Resend verification email</a></div><?php endif; ?>
<div class="grid-2">
  <form method="post" enctype="multipart/form-data" class="card form" data-loading><?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <h2>Personal details</h2>
    <div class="avatar-edit"><?= avatar_html($u['avatar'], $u['name'], 'av av-lg') ?><label class="btn btn-ghost btn-sm"><?= icon('upload', 'i i-sm') ?>Change photo<input type="file" name="avatar" accept="image/*" hidden data-preview=".avatar-edit .av"></label></div>
    <div class="field"><label>Full name</label><input name="name" value="<?= e($u['name']) ?>" required></div>
    <div class="field"><label>Email</label><input value="<?= e($u['email']) ?>" disabled><small class="muted"><?= $u['email_verified_at'] ? '✓ Verified' : 'Not verified' ?></small></div>
    <div class="field"><label>Mobile</label><input name="phone" value="<?= e($u['phone']) ?>" inputmode="tel"></div>
    <label class="switch"><input type="checkbox" name="email_notifications" value="1" <?= $u['email_notifications'] ? 'checked' : '' ?>><span></span>Email me updates (applications, alerts, new jobs)</label>
    <button class="btn btn-dark">Save changes</button>
  </form>
  <div>
    <form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <h2><?= $u['password_hash'] ? 'Change password' : 'Set a password' ?></h2>
      <?php if ($u['password_hash']): ?><div class="field"><label>Current password</label><input type="password" name="current" required autocomplete="current-password"></div><?php else: ?><p class="muted small">You sign in with Google/Microsoft. Set a password to also sign in with email.</p><?php endif; ?>
      <div class="field"><label>New password</label><input type="password" name="new" required minlength="8" autocomplete="new-password"></div>
      <button class="btn btn-dark">Update password</button>
    </form>
    <div class="card"><h2>Connected sign-in</h2>
      <ul class="kv"><li><span>Google</span><b><?= $u['google_id'] ? '✓ Connected' : '-' ?></b></li><li><span>Microsoft</span><b><?= $u['microsoft_id'] ? '✓ Connected' : '-' ?></b></li></ul>
      <p class="muted small">Sign in once with Google or Microsoft using <?= e($u['email']) ?> to connect it.</p>
    </div>
  </div>
</div>
<div class="card"><h2>Recent sign-ins</h2>
  <div class="table-wrap"><table class="table"><thead><tr><th>When</th><th>Method</th><th>Device</th><th>IP</th><th>Result</th></tr></thead><tbody>
  <?php foreach ($logins as $l): ?><tr><td><?= e(time_ago($l['created_at'])) ?></td><td><?= e(ucfirst($l['method'])) ?></td><td><?= e($l['device']) ?></td><td><?= e($l['ip']) ?></td><td><?= $l['success'] ? '<span class="badge b-green">Success</span>' : '<span class="badge b-red">Failed</span>' ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php if ($u['role'] !== 'admin'): ?>
<form method="post" class="card form danger-zone" onsubmit="return confirm('Delete your account permanently?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete">
  <h2>Delete account</h2><p class="muted">This permanently removes your profile, applications<?= $u['role'] === 'employer' ? ', company and jobs' : '' ?>. This can’t be undone.</p>
  <div class="row2"><input name="confirm" placeholder="Type DELETE"><button class="btn btn-danger">Delete my account</button></div>
</form>
<?php endif; ?>
<?php dash_end();
