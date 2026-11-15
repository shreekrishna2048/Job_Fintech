<?php
$u = require_login('seeker');
$status = $_GET['status'] ?? '';
$where = 'a.user_id=?'; $p = [$u['id']];
if (isset(APP_STATUSES[$status])) { $where .= ' AND a.status=?'; $p[] = $status; }
$counts = array_column(rows('SELECT status, COUNT(*) n FROM applications WHERE user_id=? GROUP BY status', [$u['id']]), 'n', 'status');
$pg = paginate((int) val("SELECT COUNT(*) FROM applications a WHERE $where", $p), 20);
$list = rows("SELECT a.*, j.title, j.slug, j.status job_status, c.name company_name, c.logo company_logo, l.name location_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id LEFT JOIN locations l ON l.id=j.location_id WHERE $where ORDER BY a.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
$steps = ['applied', 'viewed', 'shortlisted', 'interview', 'offered', 'hired'];
dash_start('My applications', 'seeker/applications');
?>
<div class="page-head"><h1>My applications</h1></div>
<nav class="tabs tabs-scroll"><a href="?" class="<?= $status === '' ? 'on' : '' ?>">All <span class="count"><?= array_sum($counts) ?></span></a>
<?php foreach (['applied', 'viewed', 'shortlisted', 'interview', 'offered', 'hired', 'rejected'] as $s): ?><a href="?status=<?= $s ?>" class="<?= $status === $s ? 'on' : '' ?>"><?= APP_STATUSES[$s] ?> <span class="count"><?= (int) ($counts[$s] ?? 0) ?></span></a><?php endforeach; ?></nav>
<?php if ($list): ?>
<div class="app-list">
<?php foreach ($list as $a): $idx = array_search($a['status'], $steps, true); ?>
  <a class="card app-row reveal" href="<?= url('seeker/applications/' . $a['id']) ?>">
    <div class="app-row-top"><?= avatar_html($a['company_logo'], $a['company_name'], 'logo-box') ?><div class="grow"><b><?= e($a['title']) ?></b><small><?= e($a['company_name']) ?> · <?= e($a['location_name'] ?? '') ?> · Applied <?= e(time_ago($a['created_at'])) ?></small></div><?= status_badge($a['status']) ?></div>
    <?php if ($idx !== false): ?><div class="mini-steps"><?php foreach ($steps as $i => $s): ?><span class="<?= $i <= $idx ? 'done' : '' ?>" title="<?= APP_STATUSES[$s] ?>"></span><?php endforeach; ?></div><?php endif; ?>
  </a>
<?php endforeach; ?>
</div>
<?= page_links($pg) ?>
<?php else: ?><div class="empty card"><?= icon('file', 'i i-xl') ?><h3>No applications here</h3><a class="btn btn-brand" href="<?= url('jobs') ?>">Browse jobs</a></div><?php endif; ?>
<?php dash_end();
