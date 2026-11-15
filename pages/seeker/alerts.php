<?php
$u = require_login('seeker');
if (is_post()) {
    csrf_check();
    if (input('action') === 'delete') q('DELETE FROM job_alerts WHERE id=? AND user_id=?', [(int) input('id'), $u['id']]);
    elseif (input('action') === 'toggle') q('UPDATE job_alerts SET active=1-active WHERE id=? AND user_id=?', [(int) input('id'), $u['id']]);
    else {
        if ((int) val('SELECT COUNT(*) FROM job_alerts WHERE user_id=?', [$u['id']]) >= 10) flash('error', 'You can have up to 10 alerts.');
        else {
            insert('job_alerts', ['user_id' => $u['id'], 'keyword' => mb_substr(input('keyword'), 0, 120) ?: null, 'category_id' => (int) input('category_id') ?: null, 'location_id' => (int) input('location_id') ?: null,
                'job_type' => isset(JOB_TYPES[input('job_type')]) ? input('job_type') : null, 'frequency' => in_array(input('frequency'), ['instant', 'daily', 'weekly'], true) ? input('frequency') : 'daily', 'last_sent_at' => date('Y-m-d H:i:s')]);
            log_activity('alert_create', 'alert', null, input('keyword'));
            flash('success', 'Job alert created.');
        }
    }
    redirect('seeker/alerts');
}
$alerts = rows('SELECT a.*, c.name cat, l.name loc FROM job_alerts a LEFT JOIN categories c ON c.id=a.category_id LEFT JOIN locations l ON l.id=a.location_id WHERE a.user_id=? ORDER BY a.id DESC', [$u['id']]);
dash_start('Job alerts', 'seeker/alerts');
?>
<div class="page-head"><div><h1>Job alerts</h1><p class="muted">Get an email when matching jobs are posted.</p></div></div>
<form method="post" class="card form"><?= csrf_field() ?>
  <h2>New alert</h2>
  <div class="row2"><div class="field"><label>Keyword</label><input name="keyword" placeholder="e.g. accountant, react, driver"></div><div class="field"><label>City</label><select name="location_id"><?= options(locations(), '', 'Anywhere') ?></select></div></div>
  <div class="row3"><div class="field"><label>Category</label><select name="category_id"><?= options(categories(), '', 'Any') ?></select></div><div class="field"><label>Job type</label><select name="job_type"><?= options(JOB_TYPES, '', 'Any') ?></select></div><div class="field"><label>How often</label><select name="frequency"><?= options(['instant' => 'Instantly', 'daily' => 'Daily digest', 'weekly' => 'Weekly digest'], 'daily') ?></select></div></div>
  <button class="btn btn-brand"><?= icon('bell', 'i i-sm') ?>Create alert</button>
</form>
<?php if ($alerts): ?><div class="card list"><?php foreach ($alerts as $a): ?>
  <div class="list-row"><span class="notif-ic"><?= icon('bell', 'i i-sm') ?></span><div class="grow"><b><?= e($a['keyword'] ?: 'All jobs') ?></b><small><?= e(implode(' · ', array_filter([$a['cat'], $a['loc'], $a['job_type'] ? JOB_TYPES[$a['job_type']] : null, ucfirst($a['frequency'])]))) ?></small></div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="badge <?= $a['active'] ? 'b-green' : 'b-gray' ?>"><?= $a['active'] ? 'Active' : 'Paused' ?></button></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="icon-btn" aria-label="Delete"><?= icon('trash', 'i i-sm') ?></button></form></div>
<?php endforeach; ?></div><?php endif; ?>
<?php dash_end();
