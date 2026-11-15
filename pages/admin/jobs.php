<?php
if (is_post()) {
    $j = row(JOB_SELECT . ' WHERE j.id=?', [(int) input('id')]);
    if ($j) {
        $owner = row('SELECT u.* FROM users u JOIN companies c ON c.owner_id=u.id WHERE c.id=?', [$j['company_id']]);
        switch (input('action')) {
            case 'approve':
                $first = !$j['published_at'];
                update('jobs', ['status' => 'active', 'reject_reason' => null] + ($first ? ['published_at' => date('Y-m-d H:i:s')] : []), 'id=?', [$j['id']]);
                if ($owner) { notify((int) $owner['id'], 'job_approved', 'Job approved: ' . $j['title'], 'It is now live.', 'job/' . $j['slug']); queue_template_email($owner['email'], $owner['name'], 'job_approved', ['name' => $owner['name'], 'job_title' => $j['title'], 'link' => url('job/' . $j['slug'])]); }
                if ($first) on_job_published((int) $j['id']);
                flash('success', 'Job approved and live.'); break;
            case 'reject':
                $reason = mb_substr(input('reason'), 0, 255) ?: 'Does not meet posting guidelines';
                update('jobs', ['status' => 'rejected', 'reject_reason' => $reason], 'id=?', [$j['id']]);
                if ($owner) { notify((int) $owner['id'], 'job_rejected', 'Job needs changes: ' . $j['title'], $reason, 'employer/jobs/' . $j['id'] . '/edit'); queue_template_email($owner['email'], $owner['name'], 'job_rejected', ['name' => $owner['name'], 'job_title' => $j['title'], 'reason' => $reason, 'link' => url('employer/jobs/' . $j['id'] . '/edit')]); }
                flash('success', 'Job rejected.'); break;
            case 'feature': update('jobs', ['featured' => 1 - (int) $j['featured']], 'id=?', [$j['id']]); flash('success', $j['featured'] ? 'Unfeatured.' : 'Featured.'); break;
            case 'close': update('jobs', ['status' => 'closed'], 'id=?', [$j['id']]); flash('success', 'Closed.'); break;
            case 'delete': q('DELETE FROM jobs WHERE id=?', [$j['id']]); flash('success', 'Deleted.'); break;
        }
        log_activity('admin_job_' . input('action'), 'job', (int) $j['id'], $j['title']);
    }
    back();
}
$status = $_GET['status'] ?? ''; $qtxt = trim($_GET['q'] ?? '');
$where = '1'; $p = [];
if (in_array($status, ['active', 'pending', 'draft', 'closed', 'rejected'], true)) { $where .= ' AND j.status=?'; $p[] = $status; }
if ($status === 'featured') $where .= ' AND j.featured=1';
if ($qtxt !== '') { if (ctype_digit($qtxt)) { $where .= ' AND j.id=?'; $p[] = (int) $qtxt; } else { $where .= ' AND (j.title LIKE ? OR c.name LIKE ?)'; $p[] = "%$qtxt%"; $p[] = "%$qtxt%"; } }
$counts = array_column(rows('SELECT status, COUNT(*) n FROM jobs GROUP BY status'), 'n', 'status');
$pg = paginate((int) val("SELECT COUNT(*) FROM jobs j JOIN companies c ON c.id=j.company_id WHERE $where", $p), 30);
$list = rows(str_replace('SELECT j.*', 'SELECT j.*, c.owner_id', JOB_SELECT) . " WHERE $where ORDER BY j.status='pending' DESC, j.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
admin_start('Jobs');
?>
<nav class="tabs tabs-scroll"><a href="?" class="<?= $status === '' ? 'on' : '' ?>">All <span class="count"><?= array_sum($counts) ?></span></a><?php foreach (['pending' => 'Pending', 'active' => 'Live', 'featured' => 'Featured', 'draft' => 'Draft', 'closed' => 'Closed', 'rejected' => 'Rejected'] as $s => $l): ?><a href="?status=<?= $s ?>" class="<?= $status === $s ? 'on' : '' ?>"><?= $l ?><?php if (isset($counts[$s])): ?> <span class="count"><?= $counts[$s] ?></span><?php endif; ?></a><?php endforeach; ?></nav>
<form class="toolbar" method="get"><input type="hidden" name="status" value="<?= e($status) ?>"><input name="q" value="<?= e($qtxt) ?>" placeholder="Title, company or job ID"><button class="btn btn-dark btn-sm">Search</button></form>
<div class="card"><div class="table-wrap"><table class="table">
<thead><tr><th>Job</th><th>Company</th><th>Status</th><th>Views</th><th>Apps</th><th>Posted</th><th class="right">Actions</th></tr></thead><tbody>
<?php foreach ($list as $j): ?><tr>
  <td><a href="<?= url('job/' . $j['slug']) ?>" target="_blank"><b><?= e($j['title']) ?></b></a><?= $j['featured'] ? ' <span class="tag tag-feat">Featured</span>' : '' ?><br><small class="muted">#<?= $j['id'] ?> · <?= e($j['location_name'] ?? '') ?> · <?= e(salary_text($j)) ?></small><?php if ($j['reject_reason']): ?><br><small class="err"><?= e($j['reject_reason']) ?></small><?php endif; ?></td>
  <td><a href="<?= url('admin/companies?q=' . urlencode($j['company_name'])) ?>"><?= e($j['company_name']) ?></a><?= $j['company_verified'] ? ' ✓' : '' ?></td>
  <td><?= status_badge($j['status']) ?></td><td><?= (int) $j['views'] ?></td><td><a href="<?= url('admin/applications?job=' . $j['id']) ?>"><?= (int) $j['applications_count'] ?></a></td><td class="nowrap"><?= date('d M y', strtotime($j['created_at'])) ?></td>
  <td class="right actions">
    <?php if ($j['status'] !== 'active'): ?><?= adm_action_form('approve', (int) $j['id'], 'Approve', 'btn btn-ok btn-xs') ?><?php endif; ?>
    <?php if (in_array($j['status'], ['pending', 'active'], true)): ?><details class="pop"><summary class="btn btn-ghost btn-xs">Reject</summary><form method="post" class="pop-body"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= $j['id'] ?>"><input name="reason" placeholder="Reason shown to employer" required><button class="btn btn-danger btn-xs">Reject job</button></form></details><?php endif; ?>
    <?= adm_act_as((int) $j['owner_id'], 'employer/jobs/' . $j['id'] . '/edit', 'Edit') ?>
    <?= adm_act_as((int) $j['owner_id'], 'employer/applicants?job=' . $j['id'] . '&view=board', 'Applicants') ?>
    <?= adm_action_form('feature', (int) $j['id'], $j['featured'] ? 'Unfeature' : 'Feature') ?>
    <?php if ($j['status'] === 'active'): ?><?= adm_action_form('close', (int) $j['id'], 'Close') ?><?php endif; ?>
    <?= adm_action_form('delete', (int) $j['id'], icon('trash', 'i i-xs'), 'btn btn-danger-ghost btn-xs', 'Delete job and its applications?') ?>
  </td></tr><?php endforeach; ?>
<?php if (!$list): ?><tr><td colspan="7" class="center muted">No jobs.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?= page_links($pg) ?>
<?php admin_end();
