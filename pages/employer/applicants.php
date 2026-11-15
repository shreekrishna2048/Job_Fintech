<?php
require __DIR__ . '/_guard.php';
$cid = (int) $company['id'];
$jobId = (int) ($_GET['job'] ?? 0);
$job = $jobId ? row('SELECT * FROM jobs WHERE id=? AND company_id=?', [$jobId, $cid]) : null;
if ($jobId && !$job) abort(404);
$view = $job && ($_GET['view'] ?? '') === 'board' ? 'board' : 'list';
$status = $_GET['status'] ?? ''; $minMatch = (int) ($_GET['match'] ?? 0); $qtxt = mb_substr(trim($_GET['q'] ?? ''), 0, 80);
$sort = $_GET['sort'] ?? 'new';

$p = [$cid];
$where = "j.company_id=? AND a.status<>'withdrawn'";
if ($job) { $where .= ' AND a.job_id=?'; $p[] = $jobId; }
if ($view === 'list' && isset(APP_STATUSES[$status])) { $where .= ' AND a.status=?'; $p[] = $status; }
if ($minMatch) { $where .= ' AND a.match_score>=?'; $p[] = $minMatch; }
if ($qtxt !== '') { $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR sp.skills LIKE ?)'; array_push($p, "%$qtxt%", "%$qtxt%", "%$qtxt%"); }
$order = $sort === 'match' ? 'a.match_score DESC' : ($sort === 'rating' ? 'a.rating DESC' : 'a.id DESC');
$base = "FROM applications a JOIN jobs j ON j.id=a.job_id JOIN users u ON u.id=a.user_id LEFT JOIN seeker_profiles sp ON sp.user_id=u.id LEFT JOIN locations l ON l.id=sp.location_id WHERE $where";
$counts = array_column(rows("SELECT a.status, COUNT(*) n FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.company_id=?" . ($job ? ' AND a.job_id=' . $jobId : '') . " GROUP BY a.status", [$cid]), 'n', 'status');
if ($view === 'board') { $list = rows("SELECT a.*, u.name, u.avatar, sp.headline, sp.experience_years $base ORDER BY $order LIMIT 500", $p); $pg = null; }
else { $pg = paginate((int) val("SELECT COUNT(*) $base", $p), 25); $list = rows("SELECT a.*, u.name, u.email, u.avatar, u.phone, sp.headline, sp.experience_years, sp.skills, l.name location, j.title job_title $base ORDER BY $order LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p); }
$myJobs = rows("SELECT id, title, applications_count FROM jobs WHERE company_id=? ORDER BY created_at DESC LIMIT 100", [$cid]);
dash_start('Applicants', 'employer/applicants');
?>
<div class="page-head"><div><h1><?= $job ? e($job['title']) : 'All applicants' ?></h1><?php if ($job): ?><p class="muted"><?= (int) $job['applications_count'] ?> applicants · <a href="<?= url('job/' . $job['slug']) ?>">view job</a></p><?php endif; ?></div>
  <?php if ($job): ?><div class="seg"><a href="?job=<?= $jobId ?>" class="<?= $view === 'list' ? 'on' : '' ?>"><?= icon('menu', 'i i-sm') ?>List</a><a href="?job=<?= $jobId ?>&view=board" class="<?= $view === 'board' ? 'on' : '' ?>"><?= icon('layers', 'i i-sm') ?>Pipeline</a></div><?php endif; ?></div>
<form class="toolbar" method="get">
  <select name="job" onchange="this.form.submit()"><option value="">All jobs</option><?php foreach ($myJobs as $mj): ?><option value="<?= $mj['id'] ?>" <?= $mj['id'] == $jobId ? 'selected' : '' ?>><?= e($mj['title']) ?> (<?= (int) $mj['applications_count'] ?>)</option><?php endforeach; ?></select>
  <?php if ($view === 'board'): ?><input type="hidden" name="view" value="board"><?php endif; ?>
  <input name="q" value="<?= e($qtxt) ?>" placeholder="Search name, email, skill">
  <select name="match"><?= options(['50' => '50%+ match', '75' => '75%+ match'], $minMatch ?: '', 'Any match') ?></select>
  <select name="sort"><?= options(['new' => 'Newest', 'match' => 'Best match', 'rating' => 'Top rated'], $sort) ?></select>
  <button class="btn btn-dark btn-sm">Filter</button>
</form>

<?php if ($view === 'board'): $cols = ['applied', 'viewed', 'shortlisted', 'interview', 'offered', 'hired', 'rejected']; ?>
<p class="muted small show-md">Swipe sideways to see all stages. Drag cards between columns (desktop) or open a card to change status.</p>
<div class="board" data-board>
  <?php foreach ($cols as $col): $items = array_filter($list, fn($a) => $a['status'] === $col); ?>
  <div class="board-col" data-status="<?= $col ?>"><div class="board-head"><?= status_badge($col) ?><span class="count"><?= count($items) ?></span></div>
    <div class="board-list" data-dropzone>
    <?php foreach ($items as $a): ?><a class="bcard" draggable="true" data-app="<?= $a['id'] ?>" href="<?= url('employer/applicants/' . $a['id']) ?>"><div class="bcard-top"><?= avatar_html($a['avatar'], $a['name'], 'av av-sm') ?><b><?= e($a['name']) ?></b></div><small><?= e($a['headline'] ?: (($a['experience_years'] ?? '?') . ' yrs exp')) ?></small><div class="bcard-foot"><?php if ($a['match_score'] !== null): ?><span class="match m-<?= $a['match_score'] >= 75 ? 'hi' : ($a['match_score'] >= 50 ? 'md' : 'lo') ?>"><?= $a['match_score'] ?>%</span><?php endif; ?><?php if ($a['rating']): ?><span class="stars-sm"><?= str_repeat('★', (int) $a['rating']) ?></span><?php endif; ?><time><?= e(time_ago($a['created_at'])) ?></time></div></a><?php endforeach; ?>
    </div></div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<nav class="tabs tabs-scroll"><?php $qs = $_GET; unset($qs['status'], $qs['page']); ?><a href="?<?= e(http_build_query($qs)) ?>" class="<?= $status === '' ? 'on' : '' ?>">All <span class="count"><?= array_sum($counts) - (int) ($counts['withdrawn'] ?? 0) ?></span></a><?php foreach (['applied', 'shortlisted', 'interview', 'offered', 'hired', 'rejected'] as $s): ?><a href="?<?= e(http_build_query($qs + ['status' => $s])) ?>" class="<?= $status === $s ? 'on' : '' ?>"><?= $s === 'applied' ? 'New' : APP_STATUSES[$s] ?> <span class="count"><?= (int) ($counts[$s] ?? 0) ?></span></a><?php endforeach; ?></nav>
<?php if ($list): ?><div class="card list applicant-list">
<?php foreach ($list as $a): ?>
  <a class="list-row app-cand" href="<?= url('employer/applicants/' . $a['id']) ?>">
    <?= avatar_html($a['avatar'], $a['name'], 'av av-md') ?>
    <div class="grow"><b><?= e($a['name']) ?><?php if ($a['status'] === 'applied'): ?> <span class="dot-new"></span><?php endif; ?></b><small><?= e($a['headline'] ?: '') ?><?= $a['experience_years'] !== null ? ' · ' . (float) $a['experience_years'] . ' yrs' : '' ?><?= $a['location'] ? ' · ' . e($a['location']) : '' ?></small><?php if (!$job): ?><small class="muted">For: <?= e($a['job_title']) ?></small><?php endif; ?></div>
    <div class="app-cand-meta"><?php if ($a['match_score'] !== null): ?><span class="match m-<?= $a['match_score'] >= 75 ? 'hi' : ($a['match_score'] >= 50 ? 'md' : 'lo') ?>"><?= $a['match_score'] ?>%</span><?php endif; ?><?= status_badge($a['status']) ?><time class="muted small"><?= e(time_ago($a['created_at'])) ?></time></div>
  </a>
<?php endforeach; ?></div><?= page_links($pg) ?>
<?php else: ?><div class="empty card"><?= icon('users', 'i i-xl') ?><h3>No applicants found</h3><p>Share your job link on social media to reach more people.</p></div><?php endif; ?>
<?php endif; ?>
<?php dash_end();
