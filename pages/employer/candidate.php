<?php
require __DIR__ . '/_guard.php';
$id = (int) param('id');
$c = row("SELECT u.*, sp.*, l.name location FROM users u JOIN seeker_profiles sp ON sp.user_id=u.id LEFT JOIN locations l ON l.id=sp.location_id WHERE u.id=? AND u.role='seeker' AND u.status='active'", [$id]);
$appliedHere = $company ? val('SELECT 1 FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.user_id=? AND j.company_id=? LIMIT 1', [$id, $company['id']]) : false;
if (!$c || (!$c['visible_to_employers'] && !$appliedHere && $u['role'] !== 'admin')) abort(404, 'This candidate profile is private.');
if (is_post() && input('action') === 'invite') {
    csrf_check();
    $job = row("SELECT * FROM jobs WHERE id=? AND company_id=? AND status='active'", [(int) input('job_id'), $company['id']]);
    $recent = (int) val("SELECT COUNT(*) FROM activity_logs WHERE action='candidate_invite' AND user_id=? AND created_at > NOW() - INTERVAL 1 DAY", [$u['id']]);
    if (!$job) flash('error', 'Choose one of your live jobs.');
    elseif ($recent >= 50) flash('error', 'Daily invite limit reached.');
    elseif (val("SELECT 1 FROM activity_logs WHERE action='candidate_invite' AND entity_id=? AND description=?", [$id, 'job:' . $job['id']])) flash('info', 'You already invited this candidate to that job.');
    else {
        $msg = mb_substr(input('message'), 0, 1000);
        notify($id, 'invite', "{$company['name']} invited you to apply", $job['title'], 'job/' . $job['slug']);
        queue_email($c['email'], $c['name'], "{$company['name']} invited you to apply: {$job['title']}", email_layout('<p>Hi ' . e($c['name']) . ',</p><p><b>' . e($company['name']) . '</b> viewed your profile and invites you to apply for <b>' . e($job['title']) . '</b>.</p>' . ($msg ? '<p>“' . nl2br(e($msg)) . '”</p>' : '') . '<p><a class="btn" href="' . e(url('job/' . $job['slug'])) . '">View job & apply</a></p>'), 'invite');
        log_activity('candidate_invite', 'seeker', $id, 'job:' . $job['id']);
        flash('success', 'Invitation sent to ' . $c['name'] . '.');
    }
    redirect('employer/candidates/' . $id);
}
// Track employer profile views (shown to the seeker as "employer views")
if ($u['role'] === 'employer' && !val("SELECT 1 FROM activity_logs WHERE action='candidate_view' AND user_id=? AND entity_id=? AND created_at > NOW() - INTERVAL 1 DAY", [$u['id'], $id])) {
    log_activity('candidate_view', 'seeker', $id);
    notify($id, 'profile_view', 'An employer viewed your profile', $company['name'] ?? '', 'seeker');
}
$exp = rows('SELECT * FROM seeker_experience WHERE user_id=? ORDER BY is_current DESC, start_date DESC', [$id]);
$edu = rows('SELECT * FROM seeker_education WHERE user_id=? ORDER BY year_completed DESC', [$id]);
$myJobs = $company ? rows("SELECT id, title FROM jobs WHERE company_id=? AND status='active' AND (deadline IS NULL OR deadline>=CURDATE())", [$company['id']]) : [];
dash_start($c['name'], 'employer/candidates');
?>
<a class="back-link" href="<?= url('employer/candidates') ?>">← Candidates</a>
<div class="cand-layout">
  <div class="cand-main">
    <div class="card cand-head"><?= avatar_html($c['avatar'], $c['name'], 'av av-xl') ?><div class="grow"><h1><?= e($c['name']) ?></h1><p><?= e($c['headline']) ?></p>
      <div class="chips"><?php if ($c['location']): ?><span><?= icon('pin', 'i i-xs') ?><?= e($c['location']) ?></span><?php endif; ?><?php if ($c['experience_years'] !== null): ?><span><?= (float) $c['experience_years'] ?> yrs</span><?php endif; ?><?php if ($c['expected_salary']): ?><span>Expects <?= e(setting('currency', 'NPR')) ?> <?= number_format((int) $c['expected_salary']) ?></span><?php endif; ?><?php if ($c['preferred_job_type']): ?><span><?= e(JOB_TYPES[$c['preferred_job_type']] ?? '') ?></span><?php endif; ?></div>
      <?php if ($appliedHere): ?><div class="contact-row"><a href="mailto:<?= e($c['email']) ?>"><?= icon('mail', 'i i-sm') ?><?= e($c['email']) ?></a><?php if ($c['phone']): ?><a href="tel:<?= e($c['phone']) ?>"><?= icon('phone', 'i i-sm') ?><?= e($c['phone']) ?></a><?php endif; ?></div><?php else: ?><p class="muted small"><?= icon('lock', 'i i-xs') ?> Contact details are shared once the candidate applies to one of your jobs.</p><?php endif; ?>
    </div></div>
    <?php if ($c['skills']): ?><div class="card"><h2>Skills</h2><div class="skill-tags"><?php foreach (skills_arr($c['skills']) as $s): ?><span class="stag"><?= e($s) ?></span><?php endforeach; ?></div></div><?php endif; ?>
    <?php if ($c['summary']): ?><div class="card prose"><h2>Summary</h2><?= nl2br(e($c['summary'])) ?></div><?php endif; ?>
    <?php if ($exp): ?><div class="card"><h2>Experience</h2><?php foreach ($exp as $x): ?><div class="tl-item"><div><b><?= e($x['title']) ?></b><small><?= e($x['company']) ?> · <?= $x['start_date'] ? date('M Y', strtotime($x['start_date'])) : '' ?> – <?= $x['is_current'] ? 'Present' : ($x['end_date'] ? date('M Y', strtotime($x['end_date'])) : '') ?></small><?php if ($x['description']): ?><p><?= nl2br(e($x['description'])) ?></p><?php endif; ?></div></div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($edu): ?><div class="card"><h2>Education</h2><?php foreach ($edu as $x): ?><div class="tl-item"><div><b><?= e($x['degree']) ?></b><small><?= e($x['institution']) ?><?= $x['year_completed'] ? ' · ' . (int) $x['year_completed'] : '' ?></small></div></div><?php endforeach; ?></div><?php endif; ?>
  </div>
  <aside class="cand-side">
    <?php if ($c['resume_path']): ?><div class="card"><h3>CV</h3><a class="btn btn-dark btn-block" href="<?= url('file/resume/profile/' . $id) ?>" target="_blank"><?= icon('file', 'i i-sm') ?>View CV</a></div><?php endif; ?>
    <form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="action" value="invite"><h3>Invite to apply</h3>
      <?php if ($myJobs): ?><select name="job_id" required><?= options($myJobs ? array_map(fn($j) => ['id' => $j['id'], 'name' => $j['title']], $myJobs) : [], '', 'Choose a job') ?></select><textarea name="message" rows="3" placeholder="Short personal message (optional)"></textarea><button class="btn btn-brand btn-block"><?= icon('send', 'i i-sm') ?>Send invite</button>
      <?php else: ?><p class="muted small">Post a job first to invite candidates.</p><a class="btn btn-ghost btn-block" href="<?= url('employer/jobs/new') ?>">Post a job</a><?php endif; ?>
    </form>
  </aside>
</div>
<?php dash_end();
