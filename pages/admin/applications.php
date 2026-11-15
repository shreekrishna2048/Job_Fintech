<?php
$jobId = (int) ($_GET['job'] ?? 0); $st = $_GET['status'] ?? ''; $qtxt = trim($_GET['q'] ?? '');
$where = '1'; $p = [];
if ($jobId) { $where .= ' AND a.job_id=?'; $p[] = $jobId; }
if (isset(APP_STATUSES[$st])) { $where .= ' AND a.status=?'; $p[] = $st; }
if ($qtxt !== '') { $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR j.title LIKE ? OR c.name LIKE ?)'; array_push($p, "%$qtxt%", "%$qtxt%", "%$qtxt%", "%$qtxt%"); }
$base = "FROM applications a JOIN users u ON u.id=a.user_id JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE $where";
$pg = paginate((int) val("SELECT COUNT(*) $base", $p), 40);
$list = rows("SELECT a.*, u.name, u.email, j.title, j.slug, c.name company, c.owner_id $base ORDER BY a.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
$funnel = array_column(rows('SELECT status, COUNT(*) n FROM applications GROUP BY status'), 'n', 'status');
admin_start('Applications');
?>
<div class="funnel"><?php foreach (['applied', 'viewed', 'shortlisted', 'interview', 'offered', 'hired', 'rejected', 'withdrawn'] as $s): ?><a href="?status=<?= $s ?>" class="<?= $st === $s ? 'on' : '' ?>"><b><?= (int) ($funnel[$s] ?? 0) ?></b><span><?= APP_STATUSES[$s] ?></span></a><?php endforeach; ?></div>
<form class="toolbar" method="get"><input name="q" value="<?= e($qtxt) ?>" placeholder="Candidate, job or company"><select name="status"><?= options(APP_STATUSES, $st, 'Any status') ?></select><?php if ($jobId): ?><input type="hidden" name="job" value="<?= $jobId ?>"><?php endif; ?><button class="btn btn-dark btn-sm">Filter</button></form>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Candidate</th><th>Job</th><th>Company</th><th>Match</th><th>Status</th><th>Applied</th><th></th></tr></thead><tbody>
<?php foreach ($list as $a): ?><tr><td><a href="<?= url('admin/user/' . $a['user_id']) ?>"><b><?= e($a['name']) ?></b></a><br><small class="muted"><?= e($a['email']) ?></small></td><td><a href="<?= url('job/' . $a['slug']) ?>" target="_blank"><?= e($a['title']) ?></a></td><td><?= e($a['company']) ?></td><td><?= $a['match_score'] !== null ? $a['match_score'] . '%' : '-' ?></td><td><?= status_badge($a['status']) ?></td><td class="nowrap"><?= date('d M y', strtotime($a['created_at'])) ?></td><td class="actions"><?= adm_act_as((int) $a['owner_id'], 'employer/applicants/' . $a['id'], 'Manage') ?><?php if ($a['resume_path']): ?><a class="btn btn-ghost btn-xs" href="<?= url('file/resume/app/' . $a['id']) ?>" target="_blank">CV</a><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?= page_links($pg) ?>
<?php admin_end();
