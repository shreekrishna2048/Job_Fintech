<?php
$u = require_login('seeker');
$profile = seeker_profile((int) $u['id']);
$pg = paginate((int) val('SELECT COUNT(*) FROM saved_jobs WHERE user_id=?', [$u['id']]), 20);
$jobs = rows(JOB_SELECT . " JOIN saved_jobs s ON s.job_id=j.id AND s.user_id=? ORDER BY s.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", [$u['id']]);
dash_start('Saved jobs', 'seeker/saved');
?>
<div class="page-head"><h1>Saved jobs</h1></div>
<?php if ($jobs): ?><div class="job-list"><?php foreach ($jobs as $j) echo job_card($j, ['saved' => true, 'match' => match_score($profile, $j)]); ?></div><?= page_links($pg) ?>
<?php else: ?><div class="empty card"><?= icon('bookmark', 'i i-xl') ?><h3>No saved jobs yet</h3><p>Tap the bookmark on any job to save it for later.</p><a class="btn btn-brand" href="<?= url('jobs') ?>">Browse jobs</a></div><?php endif; ?>
<?php dash_end();
