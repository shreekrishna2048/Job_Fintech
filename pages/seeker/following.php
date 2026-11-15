<?php
$u = require_login('seeker');
$list = rows("SELECT c.*, (SELECT COUNT(*) FROM jobs j WHERE j.company_id=c.id AND j.status='active' AND (j.deadline IS NULL OR j.deadline>=CURDATE())) open_jobs FROM company_follows f JOIN companies c ON c.id=f.company_id WHERE f.user_id=? ORDER BY f.created_at DESC", [$u['id']]);
dash_start('Following', 'seeker/following');
?>
<div class="page-head"><div><h1>Companies you follow</h1><p class="muted">You’ll be notified when they post new jobs.</p></div></div>
<?php if ($list): ?><div class="card list"><?php foreach ($list as $c): ?>
  <div class="list-row"><?= avatar_html($c['logo'], $c['name'], 'logo-box logo-sm') ?><a class="grow" href="<?= url('company/' . $c['slug']) ?>"><b><?= e($c['name']) ?></b><small><?= (int) $c['open_jobs'] ?> open jobs</small></a><button class="btn btn-ghost btn-sm" data-follow="<?= $c['id'] ?>" aria-pressed="true"><?= icon('heart', 'i i-sm') ?><span>Following</span></button></div>
<?php endforeach; ?></div>
<?php else: ?><div class="empty card"><?= icon('heart', 'i i-xl') ?><h3>Not following anyone yet</h3><a class="btn btn-brand" href="<?= url('companies') ?>">Explore companies</a></div><?php endif; ?>
<?php dash_end();
