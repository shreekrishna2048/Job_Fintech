<?php
if (is_post()) {
    $c = row('SELECT * FROM companies WHERE id=?', [(int) input('id')]);
    if ($c) {
        switch (input('action')) {
            case 'handover':
                $email = mb_strtolower(trim(input('email')));
                $owner = row('SELECT * FROM users WHERE id=?', [$c['owner_id']]);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('error', 'Enter a valid email.'); break; }
                if (!$owner || !is_placeholder_email($owner['email'])) { flash('error', 'This company already has a real owner.'); break; }
                if (val('SELECT 1 FROM users WHERE email=?', [$email])) { flash('error', 'That email already has an account. Ask the company to use another email (e.g. hr@…), or delete the unused account first.'); break; }
                update('users', ['email' => $email, 'name' => mb_substr(input('name') ?: $c['name'], 0, 120), 'email_notifications' => 1], 'id=?', [$owner['id']]);
                update('companies', ['imported' => 0, 'verified' => !empty($_POST['verify']) ? 1 : $c['verified'], 'email' => $c['email'] ?: $email], 'id=?', [$c['id']]);
                $tok = create_token((int) $owner['id'], 'reset', 7 * 86400);
                $apps = (int) val('SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.company_id=?', [$c['id']]);
                queue_email($email, $c['name'], 'Your company page on ' . setting('site_name', 'KaamKhoj') . ' is ready', email_layout('<p>Hello ' . e($c['name']) . ' team,</p><p>Your company page on <b>' . e(setting('site_name', 'KaamKhoj')) . '</b> is now yours to manage - free.</p><p>You can edit your profile, post new jobs and review ' . ($apps ? "<b>$apps applicant(s)</b> already waiting" : 'applicants') . ' in one place.</p><p><a class="btn" href="' . e(url('reset-password?token=' . $tok)) . '">Set your password & open your dashboard</a></p><p>This link is valid for 7 days. You can also sign in with Google or Microsoft using this email.</p>'), 'company_handover');
                flash('success', "Company handed over to $email - they got a set-password email.");
                break;
            case 'verify': update('companies', ['verified' => 1 - (int) $c['verified']], 'id=?', [$c['id']]);
                if (!$c['verified']) notify((int) $c['owner_id'], 'verified', 'Your company is verified ✓', 'Your jobs now go live instantly and show a verified badge.', 'company/' . $c['slug']);
                flash('success', $c['verified'] ? 'Verification removed.' : 'Company verified.'); break;
            case 'suspend': $s = $c['status'] === 'active' ? 'suspended' : 'active'; update('companies', ['status' => $s], 'id=?', [$c['id']]); flash('success', "Company $s."); break;
            case 'delete': foreach (rows('SELECT image FROM company_gallery WHERE company_id=?', [$c['id']]) as $g) delete_upload($g['image']); delete_upload($c['logo']); delete_upload($c['cover']);
                q('DELETE FROM companies WHERE id=?', [$c['id']]); flash('success', 'Company deleted.'); break;
        }
        log_activity('admin_company_' . input('action'), 'company', (int) $c['id'], $c['name']);
    }
    back();
}
$qtxt = trim($_GET['q'] ?? ''); $f = $_GET['f'] ?? '';
$where = '1'; $p = [];
if ($qtxt !== '') { $where .= ' AND (c.name LIKE ? OR u.email LIKE ?)'; $p[] = "%$qtxt%"; $p[] = "%$qtxt%"; }
if ($f === 'unverified') $where .= ' AND c.verified=0'; elseif ($f === 'imported') $where .= ' AND c.imported=1'; elseif ($f === 'suspended') $where .= " AND c.status='suspended'";
$pg = paginate((int) val("SELECT COUNT(*) FROM companies c JOIN users u ON u.id=c.owner_id WHERE $where", $p), 30);
$list = rows("SELECT c.*, u.name owner, u.email owner_email, (SELECT COUNT(*) FROM jobs j WHERE j.company_id=c.id) jobs, (SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.company_id=c.id) apps FROM companies c JOIN users u ON u.id=c.owner_id WHERE $where ORDER BY c.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
admin_start('Companies');
?>
<p class="muted small">To add a new company, use Users › Create a new account › Employer. “Edit profile” and “Post job” open that company’s own dashboard in admin mode.</p>
<form class="toolbar" method="get"><input name="q" value="<?= e($qtxt) ?>" placeholder="Company or owner email"><select name="f"><?= options(['imported' => 'Imported (not claimed)', 'unverified' => 'Unverified', 'suspended' => 'Suspended'], $f, 'All') ?></select><button class="btn btn-dark btn-sm">Filter</button></form>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Company</th><th>Owner</th><th>Jobs</th><th>Apps</th><th>Status</th><th>Joined</th><th class="right">Actions</th></tr></thead><tbody>
<?php foreach ($list as $c): ?><tr>
  <td><div class="cell-user"><?= avatar_html($c['logo'], $c['name'], 'logo-box logo-sm') ?><div><a href="<?= url('company/' . $c['slug']) ?>" target="_blank"><b><?= e($c['name']) ?></b></a><?= $c['verified'] ? ' <span class="verified">' . icon('check', 'i i-xs') . '</span>' : '' ?><?= $c['imported'] ? ' <span class="badge b-violet">Imported</span>' : '' ?><br><small class="muted"><?= e($c['tagline'] ?? '') ?></small></div></div></td>
  <td><?php if (is_placeholder_email($c['owner_email'])): ?><span class="muted small">Not claimed yet</span><?php if ($c['email']): ?><br><small><?= e($c['email']) ?></small><?php endif; ?><?php else: ?><a href="<?= url('admin/user/' . $c['owner_id']) ?>"><?= e($c['owner']) ?></a><br><small class="muted"><?= e($c['owner_email']) ?></small><?php endif; ?></td>
  <td><a href="<?= url('admin/jobs?q=' . urlencode($c['name'])) ?>"><?= $c['jobs'] ?></a></td><td><?= $c['apps'] ?></td><td><?= status_badge($c['status']) ?></td><td class="nowrap"><?= date('d M y', strtotime($c['created_at'])) ?></td>
  <td class="right actions"><?php if ($c['imported']): ?><details class="pop"><summary class="btn btn-ok btn-xs">Hand over</summary><form method="post" class="pop-body"><?= csrf_field() ?><input type="hidden" name="action" value="handover"><input type="hidden" name="id" value="<?= $c['id'] ?>"><small class="muted">Give this page to the real company. They get a set-password link.</small><input type="email" name="email" placeholder="Company’s login email" required><input name="name" placeholder="Contact person (optional)"><label class="check"><input type="checkbox" name="verify" value="1" checked> Mark verified</label><button class="btn btn-dark btn-xs">Send access</button></form></details><?php endif; ?><?= adm_act_as((int) $c['owner_id'], 'employer/company', 'Edit profile') ?><?= adm_act_as((int) $c['owner_id'], 'employer/jobs/new', 'Post job') ?><?= adm_action_form('verify', (int) $c['id'], $c['verified'] ? 'Unverify' : 'Verify', $c['verified'] ? 'btn btn-ghost btn-xs' : 'btn btn-ok btn-xs') ?><?= adm_action_form('suspend', (int) $c['id'], $c['status'] === 'active' ? 'Suspend' : 'Activate') ?><?= adm_action_form('delete', (int) $c['id'], icon('trash', 'i i-xs'), 'btn btn-danger-ghost btn-xs', 'Delete company, all its jobs and applications?') ?></td>
</tr><?php endforeach; ?></tbody></table></div></div>
<?= page_links($pg) ?>
<?php admin_end();
