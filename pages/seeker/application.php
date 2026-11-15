<?php
$u = require_login('seeker');
$a = row("SELECT a.*, j.title, j.slug, j.deadline, c.name company_name, c.slug company_slug, c.logo company_logo, l.name location_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id LEFT JOIN locations l ON l.id=j.location_id WHERE a.id=? AND a.user_id=?", [(int) param('id'), $u['id']]);
if (!$a) abort(404);
if (is_post() && input('action') === 'withdraw') {
    csrf_check();
    if (!in_array($a['status'], ['hired', 'rejected', 'withdrawn'], true)) {
        update('applications', ['status' => 'withdrawn'], 'id=?', [$a['id']]);
        insert('application_events', ['application_id' => $a['id'], 'status' => 'withdrawn', 'note' => 'Withdrawn by candidate', 'actor_id' => $u['id']]);
        $owner = val('SELECT c.owner_id FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.id=?', [$a['job_id']]);
        if ($owner) notify((int) $owner, 'status', "{$u['name']} withdrew their application", $a['title'], 'employer/applicants/' . $a['id']);
        log_activity('application_withdraw', 'application', (int) $a['id']);
        flash('success', 'Application withdrawn.');
    }
    redirect('seeker/applications/' . $a['id']);
}
$events = rows('SELECT * FROM application_events WHERE application_id=? ORDER BY id', [$a['id']]);
$steps = ['applied', 'viewed', 'shortlisted', 'interview', 'offered', 'hired'];
$idx = array_search($a['status'], $steps, true);
$answers = json_decode((string) $a['answers'], true) ?: [];
dash_start('Application: ' . $a['title'], 'seeker/applications');
?>
<a class="back-link" href="<?= url('seeker/applications') ?>">← All applications</a>
<div class="card apply-head"><?= avatar_html($a['company_logo'], $a['company_name'], 'logo-box') ?><div class="grow"><h1><?= e($a['title']) ?></h1><p class="muted"><a href="<?= url('company/' . $a['company_slug']) ?>"><?= e($a['company_name']) ?></a> · <?= e($a['location_name'] ?? '') ?></p></div><?= status_badge($a['status']) ?></div>

<div class="card">
  <h2>Progress</h2>
  <?php if (in_array($a['status'], ['rejected', 'withdrawn'], true)): ?><div class="alert alert-info"><?= $a['status'] === 'rejected' ? 'The employer has moved forward with other candidates this time. Don’t give up - new jobs are posted every day.' : 'You withdrew this application.' ?></div><?php endif; ?>
  <ol class="stepper"><?php foreach ($steps as $i => $s): ?><li class="<?= $idx !== false && $i < $idx ? 'done' : ($idx === $i ? 'cur' : '') ?>"><span></span><b><?= APP_STATUSES[$s] ?></b></li><?php endforeach; ?></ol>
  <?php if ($a['status'] === 'interview' && $a['interview_at']): ?><div class="alert alert-success"><?= icon('calendar', 'i i-sm') ?> Interview: <b><?= date('l, d M Y · h:i A', strtotime($a['interview_at'])) ?></b></div><?php endif; ?>
</div>

<div class="grid-2">
  <div class="card"><h2>Timeline</h2><ul class="timeline"><?php foreach (array_reverse($events) as $ev): ?><li><b><?= e(APP_STATUSES[$ev['status']] ?? label($ev['status'])) ?></b><?php if ($ev['note']): ?><p><?= e($ev['note']) ?></p><?php endif; ?><time><?= date('d M Y, h:i A', strtotime($ev['created_at'])) ?></time></li><?php endforeach; ?></ul></div>
  <div class="card"><h2>What you sent</h2>
    <?php if ($a['resume_path']): ?><div class="file-row"><?= icon('file') ?><div class="grow"><b><?= e($a['resume_name'] ?: 'CV') ?></b></div><a class="btn btn-ghost btn-sm" href="<?= url('file/resume/app/' . $a['id']) ?>" target="_blank">View</a></div><?php endif; ?>
    <?php if ($a['expected_salary']): ?><p><span class="muted">Expected salary:</span> <?= e(setting('currency', 'NPR')) ?> <?= number_format((int) $a['expected_salary']) ?></p><?php endif; ?>
    <?php if ($a['cover_letter']): ?><h3>Cover letter</h3><div class="prose"><?= nl2br(e($a['cover_letter'])) ?></div><?php endif; ?>
    <?php foreach ($answers as $x): ?><p><b><?= e($x['q']) ?></b><br><?= e($x['a']) ?></p><?php endforeach; ?>
  </div>
</div>
<div class="btn-row"><a class="btn btn-ghost" href="<?= url('job/' . $a['slug']) ?>">View job post</a>
<?php if (!in_array($a['status'], ['hired', 'rejected', 'withdrawn'], true)): ?><form method="post" onsubmit="return confirm('Withdraw this application?')"><?= csrf_field() ?><input type="hidden" name="action" value="withdraw"><button class="btn btn-danger-ghost">Withdraw application</button></form><?php endif; ?></div>
<?php dash_end();
