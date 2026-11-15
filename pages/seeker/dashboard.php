<?php
$u = require_login('seeker');
if ($u['role'] === 'admin') redirect('admin');
$profile = seeker_profile((int) $u['id']) ?? [];
$comp = profile_completeness((int) $u['id']);
$counts = row("SELECT COUNT(*) total, SUM(status IN ('shortlisted','interview','offered')) progress, SUM(status='interview') interviews, SUM(status='hired') hired FROM applications WHERE user_id=?", [$u['id']]);
$saved = (int) val('SELECT COUNT(*) FROM saved_jobs WHERE user_id=?', [$u['id']]);
$views = (int) val("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE action='candidate_view' AND entity_id=? AND created_at > NOW() - INTERVAL 30 DAY", [$u['id']]);
$recent = rows("SELECT a.*, j.title, j.slug, c.name company_name, c.logo company_logo FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? ORDER BY COALESCE(a.updated_at,a.created_at) DESC LIMIT 5", [$u['id']]);
$interviews = rows("SELECT a.id, a.interview_at, j.title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.status='interview' AND a.interview_at >= NOW() ORDER BY a.interview_at LIMIT 3", [$u['id']]);

// Recommendations: candidate pool by skills/category, then rank by match score
$skills = skills_arr($profile['skills'] ?? '');
$pool = [];
if ($skills) {
    $bool = implode(' ', array_map(fn($s) => preg_replace('/[+\-<>()~*"@]/', '', $s) . '*', array_slice($skills, 0, 12)));
    $pool = rows(JOB_SELECT . ' WHERE ' . JOB_LIVE . ' AND MATCH(j.title,j.skills,j.description) AGAINST(? IN BOOLEAN MODE) AND j.id NOT IN (SELECT job_id FROM applications WHERE user_id=?) ORDER BY j.published_at DESC LIMIT 60', [$bool, $u['id']]);
}
if (count($pool) < 6) $pool = array_merge($pool, rows(JOB_SELECT . ' WHERE ' . JOB_LIVE . ' AND (j.category_id<=>? OR j.location_id<=>?) AND j.id NOT IN (SELECT job_id FROM applications WHERE user_id=?) ORDER BY j.published_at DESC LIMIT 30', [$profile['preferred_category_id'] ?? null, $profile['location_id'] ?? null, $u['id']]));
$seen = []; $recs = [];
foreach ($pool as $j) { if (isset($seen[$j['id']])) continue; $seen[$j['id']] = 1; $j['_m'] = match_score($profile, $j) ?? 0; $recs[] = $j; }
usort($recs, fn($a, $b) => $b['_m'] <=> $a['_m']);
$recs = array_slice($recs, 0, 6);
$savedIds = array_column(rows('SELECT job_id FROM saved_jobs WHERE user_id=?', [$u['id']]), 'job_id');

dash_start('Dashboard', 'seeker');
?>
<div class="page-head"><div><p class="muted">Namaste,</p><h1><?= e(explode(' ', $u['name'])[0]) ?> 👋</h1></div><a class="btn btn-brand" href="<?= url('jobs') ?>"><?= icon('search', 'i i-sm') ?>Find jobs</a></div>

<?php if ($comp['percent'] < 100): ?>
<div class="card complete-card reveal">
  <div class="ring" style="--p:<?= $comp['percent'] ?>"><span><?= $comp['percent'] ?>%</span></div>
  <div><h2>Complete your profile</h2><p class="muted">Complete profiles get up to 3× more employer views and better match scores.</p>
    <div class="todo-chips"><?php foreach ($comp['missing'] as $m): ?><a href="<?= url('seeker/profile') ?>"><?= icon('plus', 'i i-xs') ?><?= e($m) ?></a><?php endforeach; ?></div></div>
</div>
<?php endif; ?>

<div class="kpis">
  <a class="kpi" href="<?= url('seeker/applications') ?>"><?= icon('send') ?><b><?= (int) $counts['total'] ?></b><span>Applications</span></a>
  <a class="kpi" href="<?= url('seeker/applications?status=shortlisted') ?>"><?= icon('star') ?><b><?= (int) $counts['progress'] ?></b><span>In progress</span></a>
  <a class="kpi" href="<?= url('seeker/saved') ?>"><?= icon('bookmark') ?><b><?= $saved ?></b><span>Saved jobs</span></a>
  <div class="kpi"><?= icon('eye') ?><b><?= $views ?></b><span>Employer views (30d)</span></div>
</div>

<?php if ($interviews): ?>
<div class="card"><h2><?= icon('calendar') ?> Upcoming interviews</h2>
  <?php foreach ($interviews as $i): ?><a class="list-row" href="<?= url('seeker/applications/' . $i['id']) ?>"><div><b><?= e($i['title']) ?></b><small><?= e($i['company_name']) ?></small></div><span class="badge b-blue"><?= date('D, d M · h:i A', strtotime($i['interview_at'])) ?></span></a><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="sec-head"><h2>Recommended for you</h2><a class="link-arrow" href="<?= url('jobs') ?>">More jobs <?= icon('arrow', 'i i-sm') ?></a></div>
<?php if ($recs): ?><div class="job-grid job-grid-2"><?php foreach ($recs as $j) echo job_card($j, ['match' => $j['_m'], 'saved' => in_array($j['id'], $savedIds)]); ?></div>
<?php else: ?><div class="empty card"><?= icon('target', 'i i-xl') ?><h3>Add skills to get recommendations</h3><a class="btn btn-brand" href="<?= url('seeker/profile') ?>">Add skills</a></div><?php endif; ?>

<div class="sec-head"><h2>Recent applications</h2><a class="link-arrow" href="<?= url('seeker/applications') ?>">All <?= icon('arrow', 'i i-sm') ?></a></div>
<?php if ($recent): ?><div class="card list">
  <?php foreach ($recent as $a): ?><a class="list-row" href="<?= url('seeker/applications/' . $a['id']) ?>"><?= avatar_html($a['company_logo'], $a['company_name'], 'logo-box logo-sm') ?><div class="grow"><b><?= e($a['title']) ?></b><small><?= e($a['company_name']) ?> · <?= e(time_ago($a['created_at'])) ?></small></div><?= status_badge($a['status']) ?></a><?php endforeach; ?>
</div><?php else: ?><div class="empty card"><p>You haven’t applied to any jobs yet.</p></div><?php endif; ?>
<?php dash_end();
