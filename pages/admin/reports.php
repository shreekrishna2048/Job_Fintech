<?php
if (is_post()) { update('job_reports', ['resolved' => 1], 'id=?', [(int) input('id')]); flash('success', 'Marked resolved.'); back(); }
$list = rows('SELECT r.*, j.title, j.slug, j.status job_status, j.id job_id, c.name company, u.name reporter FROM job_reports r JOIN jobs j ON j.id=r.job_id JOIN companies c ON c.id=j.company_id LEFT JOIN users u ON u.id=r.user_id ORDER BY r.resolved, r.id DESC LIMIT 200');
admin_start('Reported jobs');
?>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Job</th><th>Reason</th><th>By</th><th>When</th><th></th></tr></thead><tbody>
<?php foreach ($list as $r): ?><tr class="<?= $r['resolved'] ? 'muted' : '' ?>"><td><a href="<?= url('job/' . $r['slug']) ?>" target="_blank"><b><?= e($r['title']) ?></b></a><br><small><?= e($r['company']) ?> · <?= status_badge($r['job_status']) ?></small></td><td><?= e($r['reason']) ?></td><td><?= e($r['reporter'] ?? 'Guest ' . $r['ip']) ?></td><td class="nowrap"><?= e(time_ago($r['created_at'])) ?></td>
<td class="actions right"><?php if (!$r['resolved']): ?><?= adm_action_form('resolve', (int) $r['id'], 'Resolve', 'btn btn-ok btn-xs') ?><a class="btn btn-ghost btn-xs" href="<?= url('admin/jobs?q=' . $r['job_id']) ?>">Moderate job</a><?php else: ?>Resolved<?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$list): ?><tr><td colspan="5" class="center muted">No reports.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php admin_end();
