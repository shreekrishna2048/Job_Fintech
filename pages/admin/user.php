<?php
$t = row('SELECT * FROM users WHERE id=?', [(int) param('id')]);
if (!$t) abort(404);
if (is_post()) { require __DIR__ . '/users.php'; exit; }
$p = $t['role'] === 'seeker' ? seeker_profile((int) $t['id']) : null;
$co = $t['role'] === 'employer' ? employer_company((int) $t['id']) : null;
$apps = rows('SELECT a.*, j.title, c.name company FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? ORDER BY a.id DESC LIMIT 20', [$t['id']]);
$logins = rows('SELECT * FROM login_logs WHERE user_id=? OR email=? ORDER BY id DESC LIMIT 15', [$t['id'], $t['email']]);
$acts = rows('SELECT * FROM activity_logs WHERE user_id=? ORDER BY id DESC LIMIT 30', [$t['id']]);
$mails = rows('SELECT * FROM email_queue WHERE to_email=? ORDER BY id DESC LIMIT 10', [$t['email']]);
admin_start($t['name']);
?>
<a class="back-link" href="<?= url('admin/users') ?>">← Users</a>
<div class="card cand-head"><?= avatar_html($t['avatar'], $t['name'], 'av av-xl') ?><div class="grow"><h2><?= e($t['name']) ?></h2><p class="muted"><?= e($t['email']) ?> · <?= e($t['phone'] ?? '') ?> · <?= ucfirst($t['role']) ?> · joined <?= date('d M Y', strtotime($t['created_at'])) ?></p>
  <div class="chips"><?= status_badge($t['status']) ?><span><?= $t['email_verified_at'] ? 'Email verified' : 'Email not verified' ?></span><?php if ($t['google_id']): ?><span>Google linked</span><?php endif; ?><?php if ($t['microsoft_id']): ?><span>Microsoft linked</span><?php endif; ?></div></div></div>
<?php if ($t['role'] !== 'admin' && admin_can('impersonate')): ?>
<div class="card"><h3>Manage as this user</h3><p class="muted small">Opens their own dashboard so you can edit anything they can. A purple bar shows you're in admin mode; click “Back to admin” when done. All changes are logged.</p><div class="btn-row">
<?php if ($t['role'] === 'employer'): ?>
  <?= adm_act_as((int) $t['id'], 'employer/company', icon('building', 'i i-sm') . 'Edit company profile', 'btn btn-dark btn-sm') ?>
  <?= adm_act_as((int) $t['id'], 'employer/jobs', icon('briefcase', 'i i-sm') . 'Edit jobs', 'btn btn-ghost btn-sm') ?>
  <?= adm_act_as((int) $t['id'], 'employer/jobs/new', icon('plus', 'i i-sm') . 'Post a job for them', 'btn btn-ghost btn-sm') ?>
  <?= adm_act_as((int) $t['id'], 'employer/applicants', icon('users', 'i i-sm') . 'Manage applicants', 'btn btn-ghost btn-sm') ?>
<?php else: ?>
  <?= adm_act_as((int) $t['id'], 'seeker/profile', icon('user', 'i i-sm') . 'Edit profile & CV', 'btn btn-dark btn-sm') ?>
  <?= adm_act_as((int) $t['id'], 'seeker/applications', icon('file', 'i i-sm') . 'Their applications', 'btn btn-ghost btn-sm') ?>
  <?= adm_act_as((int) $t['id'], 'seeker/alerts', icon('bell', 'i i-sm') . 'Job alerts', 'btn btn-ghost btn-sm') ?>
<?php endif; ?>
</div></div>
<?php endif; ?>
<?php if ($t['id'] != $admin['id'] && ($t['role'] !== 'admin' || is_super_admin())): ?>
<form method="post" enctype="multipart/form-data" class="card form"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= $t['id'] ?>">
  <h3>Edit account</h3>
  <div class="row3"><div class="field"><label>Name</label><input name="name" value="<?= e($t['name']) ?>" required></div><div class="field"><label>Email</label><input type="email" name="email" value="<?= e($t['email']) ?>" required></div><div class="field"><label>Phone</label><input name="phone" value="<?= e($t['phone']) ?>"></div></div>
  <div class="row3"><div class="field"><label>New password <span class="muted">(leave blank to keep)</span></label><input type="password" name="new_password" autocomplete="new-password"></div><div class="field"><label>Photo</label><input type="file" name="avatar" accept="image/*"></div><label class="switch"><input type="checkbox" name="email_notifications" value="1" <?= $t['email_notifications'] ? 'checked' : '' ?>><span></span>Email notifications</label></div>
  <button class="btn btn-dark btn-sm">Save account</button>
</form>
<?php if ($t['role'] === 'admin' && is_super_admin()): $perms = ($t['admin_perms'] ?? null) === null ? null : explode(',', $t['admin_perms']); ?>
<form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="action" value="perms"><input type="hidden" name="id" value="<?= $t['id'] ?>">
  <h3>Admin permissions</h3>
  <label class="switch"><input type="checkbox" name="super" value="1" <?= $perms === null ? 'checked' : '' ?>><span></span><b>Main admin</b> - full access to everything, including other admins</label>
  <div class="perm-grid"><?php foreach (ADMIN_PERMS as $k => $l): ?><label class="check"><input type="checkbox" name="perms[]" value="<?= $k ?>" <?= $perms === null || in_array($k, $perms, true) ? 'checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?></div>
  <button class="btn btn-dark btn-sm">Save permissions</button>
</form>
<?php endif; ?>
<div class="card btn-row">
  <?= adm_action_form('suspend', (int) $t['id'], $t['status'] === 'active' ? 'Suspend user' : 'Activate user', 'btn btn-ghost btn-sm') ?>
  <?php if (!$t['email_verified_at']): ?><?= adm_action_form('verify', (int) $t['id'], 'Mark email verified', 'btn btn-ghost btn-sm') ?><?php endif; ?>
  <?= adm_action_form('reset', (int) $t['id'], 'Send password reset', 'btn btn-ghost btn-sm') ?>
  <?php if ($t['role'] !== 'admin' || is_super_admin()): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= $t['id'] ?>"><select name="role" onchange="if(confirm('Change role?'))this.form.submit()"><?= options(array_filter(['seeker' => 'Seeker', 'employer' => 'Employer', 'admin' => is_super_admin() ? 'Admin' : null]), $t['role']) ?></select></form><?php endif; ?>
  <?= adm_action_form('delete', (int) $t['id'], 'Delete user', 'btn btn-danger-ghost btn-sm', 'Permanently delete this user and all their data?') ?>
</div>
<?php endif; ?>
<div class="grid-2">
  <?php if ($p): ?><div class="card"><h3>Profile</h3><ul class="kv"><li><span>Headline</span><b><?= e($p['headline'] ?? '-') ?></b></li><li><span>Experience</span><b><?= e($p['experience_years'] ?? '-') ?> yrs</b></li><li><span>Skills</span><b><?= e($p['skills'] ?? '-') ?></b></li><li><span>CV</span><b><?= $p['resume_path'] ? '<a href="' . url('file/resume/profile/' . $t['id']) . '" target="_blank">View</a>' : '-' ?></b></li><li><span>Visible</span><b><?= $p['visible_to_employers'] ? 'Yes' : 'No' ?></b></li></ul></div><?php endif; ?>
  <?php if ($co): ?><div class="card"><h3>Company</h3><p><a href="<?= url('company/' . $co['slug']) ?>" target="_blank"><b><?= e($co['name']) ?></b></a> <?= $co['verified'] ? '✓ verified' : '' ?></p><a class="btn btn-ghost btn-sm" href="<?= url('admin/companies?q=' . urlencode($co['name'])) ?>">Manage company</a></div><?php endif; ?>
  <?php if ($apps): ?><div class="card"><h3>Applications</h3><?php foreach ($apps as $a): ?><div class="list-row"><span class="grow"><?= e($a['title']) ?> <small class="muted">· <?= e($a['company']) ?></small></span><?= status_badge($a['status']) ?></div><?php endforeach; ?></div><?php endif; ?>
</div>
<div class="card"><h3>Sign-in history</h3><div class="table-wrap"><table class="table table-sm"><thead><tr><th>When</th><th>Method</th><th>Result</th><th>Device</th><th>IP</th></tr></thead><tbody><?php foreach ($logins as $l): ?><tr><td><?= date('d M y H:i', strtotime($l['created_at'])) ?></td><td><?= e($l['method']) ?></td><td><?= $l['success'] ? '<span class="badge b-green">OK</span>' : '<span class="badge b-red">Fail</span>' ?></td><td><?= e($l['device']) ?></td><td><?= e($l['ip']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="grid-2">
  <div class="card"><h3>Activity</h3><div class="table-wrap"><table class="table table-sm"><tbody><?php foreach ($acts as $a): ?><tr><td class="nowrap muted"><?= date('d M H:i', strtotime($a['created_at'])) ?></td><td><code><?= e($a['action']) ?></code></td><td><?= e(excerpt($a['description'], 50)) ?></td><td class="muted"><?= e($a['ip']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
  <div class="card"><h3>Emails sent</h3><div class="table-wrap"><table class="table table-sm"><tbody><?php foreach ($mails as $m): ?><tr><td class="nowrap muted"><?= date('d M H:i', strtotime($m['created_at'])) ?></td><td><?= e($m['subject']) ?></td><td><?= status_badge($m['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
<?php admin_end();
