<?php
require __DIR__ . '/_guard.php';
$cid = (int) $company['id'];
$k = row("SELECT SUM(status='active' AND (deadline IS NULL OR deadline>=CURDATE())) active, SUM(status='pending') pending, SUM(views) views FROM jobs WHERE company_id=?", [$cid]);
$apps = row("SELECT COUNT(*) total, SUM(a.created_at > NOW() - INTERVAL 7 DAY) week, SUM(a.status='applied') unseen, SUM(a.status='hired') hired FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.company_id=?", [$cid]);
$daily = array_column(rows("SELECT DATE(a.created_at) d, COUNT(*) n FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.company_id=? AND a.created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY d", [$cid]), 'n', 'd');
$max = max(1, ...array_values($daily ?: [0]));
$recent = rows("SELECT a.*, u.name, u.avatar, j.title FROM applications a JOIN users u ON u.id=a.user_id JOIN jobs j ON j.id=a.job_id WHERE j.company_id=? ORDER BY a.id DESC LIMIT 6", [$cid]);
$jobs = rows("SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id=j.id AND a.status='applied') new_apps FROM jobs j WHERE j.company_id=? AND j.status IN ('active','pending','draft') ORDER BY j.created_at DESC LIMIT 6", [$cid]);
$needsSetup = !$company['logo'] || !$company['about'] || !$company['industry_id'];
dash_start('Employer dashboard', 'employer');
?>
<div class="page-head"><div><p class="muted"><?= e($company['name']) ?></p><h1>Hiring overview</h1></div><a class="btn btn-brand" href="<?= url('employer/jobs/new') ?>"><?= icon('plus', 'i i-sm') ?>Post a job</a></div>
<?php if ($company['status'] === 'suspended'): ?><div class="alert alert-error">Your company is suspended. Jobs are hidden. Contact support.</div><?php endif; ?>
<?php if ($needsSetup): ?><div class="card complete-card"><span class="feat-ic"><?= icon('building') ?></span><div><h2>Finish your company profile</h2><p class="muted">Companies with a logo and description get far more applicants. AI can write your “About us”.</p><a class="btn btn-dark btn-sm" href="<?= url('employer/company') ?>">Complete profile</a></div></div><?php endif; ?>
<div class="kpis">
  <a class="kpi" href="<?= url('employer/jobs') ?>"><?= icon('briefcase') ?><b><?= (int) $k['active'] ?></b><span>Live jobs<?= $k['pending'] ? ' · ' . (int) $k['pending'] . ' pending' : '' ?></span></a>
  <a class="kpi" href="<?= url('employer/applicants') ?>"><?= icon('users') ?><b><?= (int) $apps['total'] ?></b><span>Applicants</span></a>
  <a class="kpi kpi-hot" href="<?= url('employer/applicants?status=applied') ?>"><?= icon('zap') ?><b><?= (int) $apps['unseen'] ?></b><span>New, unreviewed</span></a>
  <div class="kpi"><?= icon('eye') ?><b><?= number_format((int) $k['views']) ?></b><span>Job views</span></div>
</div>
<div class="grid-2">
  <div class="card"><h2>Applications · last 14 days</h2>
    <div class="bars"><?php for ($i = 13; $i >= 0; $i--): $d = date('Y-m-d', strtotime("-$i day")); $n = (int) ($daily[$d] ?? 0); ?><div class="bar" style="--h:<?= round($n / $max * 100) ?>%" title="<?= date('d M', strtotime($d)) ?>: <?= $n ?>"><span><?= $n ?: '' ?></span><small><?= date('j', strtotime($d)) ?></small></div><?php endfor; ?></div>
    <p class="muted small"><?= (int) $apps['week'] ?> this week · <?= (int) $apps['hired'] ?> hired overall</p>
  </div>
  <div class="card"><h2>Latest applicants</h2>
    <?php if ($recent): foreach ($recent as $a): ?><a class="list-row" href="<?= url('employer/applicants/' . $a['id']) ?>"><?= avatar_html($a['avatar'], $a['name'], 'av av-sm') ?><div class="grow"><b><?= e($a['name']) ?></b><small><?= e($a['title']) ?> · <?= e(time_ago($a['created_at'])) ?></small></div><?php if ($a['match_score'] !== null): ?><span class="match m-<?= $a['match_score'] >= 75 ? 'hi' : ($a['match_score'] >= 50 ? 'md' : 'lo') ?>"><?= $a['match_score'] ?>%</span><?php endif; ?></a><?php endforeach;
    else: ?><p class="muted">No applicants yet. Share your jobs to get started.</p><?php endif; ?>
  </div>
</div>
<div class="card"><div class="sec-head"><h2>Your jobs</h2><a class="link-arrow" href="<?= url('employer/jobs') ?>">Manage <?= icon('arrow', 'i i-sm') ?></a></div>
  <?php if ($jobs): ?><div class="table-wrap"><table class="table"><thead><tr><th>Job</th><th>Status</th><th>Views</th><th>Applicants</th><th>Conversion</th></tr></thead><tbody>
  <?php foreach ($jobs as $j): ?><tr><td><a href="<?= url('employer/applicants?job=' . $j['id']) ?>"><b><?= e($j['title']) ?></b></a><?php if ($j['new_apps']): ?> <span class="badge b-brand"><?= $j['new_apps'] ?> new</span><?php endif; ?></td><td><?= status_badge($j['status']) ?></td><td><?= (int) $j['views'] ?></td><td><?= (int) $j['applications_count'] ?></td><td><?= $j['views'] ? round($j['applications_count'] / $j['views'] * 100, 1) . '%' : '-' ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php else: ?><div class="empty"><p>No jobs yet.</p><a class="btn btn-brand" href="<?= url('employer/jobs/new') ?>">Post your first job</a></div><?php endif; ?>
</div>
<?php dash_end();
