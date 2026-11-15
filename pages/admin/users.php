<?php
if (is_post()) {
    $act = input('action');
    if ($act === 'create') {
        $role = input('role'); $name = mb_substr(input('name'), 0, 120); $email = mb_strtolower(input('email')); $pass = (string) ($_POST['password'] ?? '');
        if (!in_array($role, ['seeker', 'employer', 'admin'], true) || ($role === 'admin' && !is_super_admin())) { flash('error', 'Only the main admin can create admin accounts.'); back(); }
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('error', 'Enter a name and valid email.'); keep_old(); back(); }
        if (val('SELECT 1 FROM users WHERE email=?', [$email])) { flash('error', 'That email already has an account.'); keep_old(); back(); }
        if ($pass !== '' && strlen($pass) < 8) { flash('error', 'Password must be 8+ characters (or leave blank to email a set-password link).'); keep_old(); back(); }
        if ($role === 'admin') {
            $id = insert('users', ['role' => 'admin', 'name' => $name, 'email' => $email, 'password_hash' => $pass ? password_hash($pass, PASSWORD_DEFAULT) : null, 'phone' => input('phone') ?: null,
                'email_verified_at' => date('Y-m-d H:i:s'), 'admin_perms' => implode(',', array_intersect(array_keys(ADMIN_PERMS), (array) ($_POST['perms'] ?? ['dashboard'])))]);
            $nu = row('SELECT * FROM users WHERE id=?', [$id]);
        } else {
            $nu = create_user($role, $name, $email, $pass ?: null, ['phone' => input('phone') ?: null, 'email_verified_at' => date('Y-m-d H:i:s')]);
            if ($role === 'employer') {
                $co = mb_substr(input('company') ?: $name, 0, 160);
                insert('companies', ['owner_id' => $nu['id'], 'name' => $co, 'slug' => unique_slug('companies', $co), 'email' => $email, 'phone' => input('phone') ?: null, 'verified' => !empty($_POST['verified']) ? 1 : 0]);
            }
        }
        if (!$pass || !empty($_POST['send_link'])) {
            $tok = create_token((int) $nu['id'], 'reset', 72 * 3600);
            queue_email($email, $name, 'Your ' . setting('site_name', 'KaamKhoj') . ' account is ready', email_layout('<p>Hi ' . e($name) . ',</p><p>An account was created for you on <b>' . e(setting('site_name', 'KaamKhoj')) . '</b>' . ($role === 'employer' ? ' for <b>' . e(input('company') ?: $name) . '</b>' : '') . '.</p><p><a class="btn" href="' . e(url('reset-password?token=' . $tok)) . '">Set your password</a></p><p>This link is valid for 72 hours. You can also sign in with Google or Microsoft using this email.</p>'), 'account_created');
        }
        log_activity('admin_user_create', 'user', (int) $nu['id'], "$role: $email");
        flash('success', ucfirst($role) . ' account created.' . (!$pass ? ' A set-password link was emailed.' : ''));
        redirect('admin/user/' . $nu['id']);
    }
    $t = row('SELECT * FROM users WHERE id=?', [(int) input('id')]);
    if ($t && $t['role'] === 'admin' && !is_super_admin()) { flash('error', 'Only the main admin can change other admin accounts.'); back(); }
    if ($t && $t['id'] != $admin['id']) {
        switch ($act) {
            case 'edit':
                $email = mb_strtolower(input('email'));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || val('SELECT 1 FROM users WHERE email=? AND id<>?', [$email, $t['id']])) { flash('error', 'Email is invalid or already used.'); break; }
                $d = ['name' => mb_substr(input('name'), 0, 120) ?: $t['name'], 'email' => $email, 'phone' => mb_substr(input('phone'), 0, 30) ?: null, 'email_notifications' => !empty($_POST['email_notifications']) ? 1 : 0];
                if (($pw = (string) ($_POST['new_password'] ?? '')) !== '') {
                    if (strlen($pw) < 8) { flash('error', 'Password must be 8+ characters.'); break; }
                    $d['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
                    q("DELETE FROM user_tokens WHERE user_id=? AND type='remember'", [$t['id']]);
                }
                try { if ($a = handle_upload('avatar', 'avatars', IMG_TYPES, 3)) { delete_upload($t['avatar']); $d['avatar'] = $a; } } catch (RuntimeException $e) { flash('error', $e->getMessage()); }
                update('users', $d, 'id=?', [$t['id']]);
                flash('success', 'User updated.'); break;
            case 'perms':
                if (!is_super_admin() || $t['role'] !== 'admin') break;
                $perms = !empty($_POST['super']) ? null : implode(',', array_intersect(array_keys(ADMIN_PERMS), (array) ($_POST['perms'] ?? [])));
                update('users', ['admin_perms' => $perms], 'id=?', [$t['id']]);
                flash('success', $perms === null ? 'Now a main (super) admin with full access.' : 'Permissions saved.'); break;
            case 'suspend': $s = $t['status'] === 'active' ? 'suspended' : 'active'; update('users', ['status' => $s], 'id=?', [$t['id']]); if ($s === 'suspended') q("DELETE FROM user_tokens WHERE user_id=? AND type='remember'", [$t['id']]); flash('success', "User $s."); break;
            case 'verify': update('users', ['email_verified_at' => date('Y-m-d H:i:s')], 'id=?', [$t['id']]); flash('success', 'Email marked verified.'); break;
            case 'role':
                $r = input('role');
                if ($r === 'admin' && !is_super_admin()) { flash('error', 'Only the main admin can make admins.'); break; }
                if (in_array($r, ['seeker', 'employer', 'admin'], true)) {
                    update('users', ['role' => $r, 'admin_perms' => $r === 'admin' ? 'dashboard' : null], 'id=?', [$t['id']]);
                    if ($r === 'seeker' && !seeker_profile((int) $t['id'])) insert('seeker_profiles', ['user_id' => $t['id']]);
                    if ($r === 'employer' && !employer_company((int) $t['id'])) insert('companies', ['owner_id' => $t['id'], 'name' => $t['name'], 'slug' => unique_slug('companies', $t['name']), 'email' => $t['email']]);
                    flash('success', "Role changed to $r." . ($r === 'admin' ? ' Set their permissions below.' : ''));
                } break;
            case 'reset': $tok = create_token((int) $t['id'], 'reset', 3600); queue_template_email($t['email'], $t['name'], 'password_reset', ['name' => $t['name'], 'link' => url('reset-password?token=' . $tok)], true); flash('success', 'Password reset email sent.'); break;
            case 'delete': delete_upload($t['avatar']); q('DELETE FROM users WHERE id=?', [$t['id']]); flash('success', 'User deleted.'); log_activity('admin_user_delete', 'user', (int) $t['id'], $t['email']); redirect('admin/users');
        }
        log_activity('admin_user_' . $act, 'user', (int) $t['id'], $t['email']);
    }
    back();
}
$role = $_GET['role'] ?? ''; $qtxt = trim($_GET['q'] ?? ''); $st = $_GET['status'] ?? '';
$where = '1'; $p = [];
if (in_array($role, ['seeker', 'employer', 'admin'], true)) { $where .= ' AND role=?'; $p[] = $role; }
if (empty($_GET['show_imported'])) $where .= " AND email NOT LIKE '%@imported.invalid'";
if (in_array($st, ['active', 'suspended'], true)) { $where .= ' AND status=?'; $p[] = $st; }
if ($st === 'unverified') $where .= ' AND email_verified_at IS NULL';
if ($qtxt !== '') { $where .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)'; array_push($p, "%$qtxt%", "%$qtxt%", "%$qtxt%"); }
$pg = paginate((int) val("SELECT COUNT(*) FROM users WHERE $where", $p), 30);
$list = rows("SELECT * FROM users WHERE $where ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
if (!empty($_GET['export'])) {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="users-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w'); fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Role', 'Status', 'Verified', 'Joined', 'Last login']);
    foreach (rows("SELECT * FROM users WHERE $where ORDER BY id DESC", $p) as $r) fputcsv($out, [$r['id'], $r['name'], $r['email'], $r['phone'], $r['role'], $r['status'], $r['email_verified_at'] ? 'yes' : 'no', $r['created_at'], $r['last_login_at']]);
    log_activity('admin_export_users'); exit;
}
admin_start('Users');
?>
<form class="toolbar" method="get"><input name="q" value="<?= e($qtxt) ?>" placeholder="Name, email or phone"><select name="role"><?= options(['seeker' => 'Job seekers', 'employer' => 'Employers', 'admin' => 'Admins'], $role, 'All roles') ?></select><select name="status"><?= options(['active' => 'Active', 'suspended' => 'Suspended', 'unverified' => 'Email unverified'], $st, 'Any status') ?></select><button class="btn btn-dark btn-sm">Filter</button>
<a class="btn btn-ghost btn-sm" href="<?= url('admin/users?' . http_build_query($_GET + ['export' => 1])) ?>"><?= icon('download', 'i i-sm') ?>CSV</a></form>
<details class="card add-block" <?= old('email') ? 'open' : '' ?>><summary><?= icon('plus', 'i i-sm') ?>Create a new account</summary>
<form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="create">
  <div class="row3"><div class="field"><label>Account type</label><select name="role" data-create-role><?= options(array_filter(['seeker' => 'Job seeker', 'employer' => 'Employer / company', 'admin' => is_super_admin() ? 'Admin staff' : null]), old('role', 'employer')) ?></select></div>
  <div class="field"><label>Full name</label><input name="name" required value="<?= e(old('name')) ?>"></div><div class="field"><label>Email</label><input type="email" name="email" required value="<?= e(old('email')) ?>"></div></div>
  <div class="row3"><div class="field"><label>Phone</label><input name="phone" value="<?= e(old('phone')) ?>"></div><div class="field" data-for-role="employer"><label>Company name</label><input name="company" value="<?= e(old('company')) ?>"></div>
  <div class="field"><label>Password <span class="muted">(blank = email a set-password link)</span></label><input type="password" name="password" autocomplete="new-password"></div></div>
  <div class="inline-checks"><label class="check" data-for-role="employer"><input type="checkbox" name="verified" value="1" checked> Mark company verified</label><label class="check"><input type="checkbox" name="send_link" value="1"> Also email login link</label></div>
  <?php if (is_super_admin()): ?><div class="field" data-for-role="admin" hidden><label>Admin permissions</label><div class="perm-grid"><?php foreach (ADMIN_PERMS as $k => $l): ?><label class="check"><input type="checkbox" name="perms[]" value="<?= $k ?>" <?= in_array($k, ['dashboard', 'jobs', 'companies'], true) ? 'checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?></div></div><?php endif; ?>
  <button class="btn btn-brand">Create account</button>
</form></details>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>User</th><th>Role</th><th>Status</th><th>Sign-in</th><th>Last login</th><th>Joined</th><th class="right">Actions</th></tr></thead><tbody>
<?php foreach ($list as $r): ?><tr>
  <td><div class="cell-user"><?= avatar_html($r['avatar'], $r['name'], 'av av-sm') ?><div><a href="<?= url('admin/user/' . $r['id']) ?>"><b><?= e($r['name']) ?></b></a><br><small class="muted"><?= e($r['email']) ?><?= $r['email_verified_at'] ? ' ✓' : '' ?></small></div></div></td>
  <td><span class="badge b-<?= $r['role'] === 'admin' ? 'violet' : ($r['role'] === 'employer' ? 'blue' : 'gray') ?>"><?= $r['role'] === 'admin' ? (($r['admin_perms'] ?? null) === null ? 'Main admin' : 'Admin staff') : e(ucfirst($r['role'])) ?></span></td><td><?= status_badge($r['status']) ?></td>
  <td class="small"><?= $r['google_id'] ? 'Google ' : '' ?><?= $r['microsoft_id'] ? 'Microsoft ' : '' ?><?= $r['password_hash'] ? 'Password' : '' ?></td>
  <td class="small nowrap"><?= $r['last_login_at'] ? e(time_ago($r['last_login_at'])) . '<br><span class="muted">' . e($r['last_login_ip']) . '</span>' : '-' ?></td><td class="nowrap"><?= date('d M y', strtotime($r['created_at'])) ?></td>
  <td class="right actions"><a class="btn btn-ghost btn-xs" href="<?= url('admin/user/' . $r['id']) ?>">View</a><?php if ($r['role'] !== 'admin'): ?><?= adm_act_as((int) $r['id'], '', 'Act as') ?><?php endif; ?><?php if ($r['id'] != $admin['id']): ?><?= adm_action_form('suspend', (int) $r['id'], $r['status'] === 'active' ? 'Suspend' : 'Activate') ?><?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table></div></div>
<?= page_links($pg) ?>
<?php admin_end();
