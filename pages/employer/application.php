<?php
require __DIR__ . '/_guard.php';
$a = employer_application((int) param('id'));
if (!$a) abort(404);
if (is_post()) {
    csrf_check();
    $act = input('action');
    if ($act === 'status') {
        $st = input('status');
        if ($st !== $a['status'] && isset(APP_STATUSES[$st]) && $a['status'] !== 'withdrawn') {
            if ($st === 'interview' && !input('interview_at')) { flash('error', 'Pick an interview date & time.'); redirect('employer/applicants/' . $a['id']); }
            change_application_status($a, $st, mb_substr(input('message'), 0, 1000), input('interview_at') ?: null, !empty($_POST['notify']));
            flash('success', 'Status updated to ' . APP_STATUSES[$st] . (!empty($_POST['notify']) ? ' · candidate emailed.' : '.'));
        }
    } elseif ($act === 'note') {
        update('applications', ['employer_note' => mb_substr(input('note'), 0, 3000) ?: null, 'rating' => min(5, max(0, (int) input('rating'))) ?: null], 'id=?', [$a['id']]);
        flash('success', 'Saved.');
    }
    redirect('employer/applicants/' . $a['id']);
}
// First open by the employer marks the application as viewed
if ($a['status'] === 'applied' && current_user()['role'] === 'employer') { change_application_status($a, 'viewed', '', null, false); $a['status'] = 'viewed'; }

$c = row('SELECT u.*, sp.* FROM users u LEFT JOIN seeker_profiles sp ON sp.user_id=u.id WHERE u.id=?', [$a['user_id']]);
$exp = rows('SELECT * FROM seeker_experience WHERE user_id=? ORDER BY is_current DESC, start_date DESC', [$a['user_id']]);
$edu = rows('SELECT * FROM seeker_education WHERE user_id=? ORDER BY year_completed DESC', [$a['user_id']]);
$events = rows('SELECT e.*, u.name actor FROM application_events e LEFT JOIN users u ON u.id=e.actor_id WHERE application_id=? ORDER BY e.id DESC', [$a['id']]);
$answers = json_decode((string) $a['answers'], true) ?: [];
$jobSkills = skills_arr($a['job_skills']); $mine = skills_arr($c['skills'] ?? '');
$has = fn($s) => (bool) array_filter($mine, fn($p) => $p === $s || str_contains($p, $s) || str_contains($s, $p));
$other = rows("SELECT a2.id, j.title, a2.status FROM applications a2 JOIN jobs j ON j.id=a2.job_id WHERE a2.user_id=? AND j.company_id=? AND a2.id<>?", [$a['user_id'], $company['id'] ?? 0, $a['id']]);
$isPdf = $a['resume_path'] && str_ends_with($a['resume_path'], '.pdf');
// prev/next navigation within the same job
$prev = val('SELECT id FROM applications WHERE job_id=? AND id>? ORDER BY id ASC LIMIT 1', [$a['job_id'], $a['id']]);
$next = val('SELECT id FROM applications WHERE job_id=? AND id<? ORDER BY id DESC LIMIT 1', [$a['job_id'], $a['id']]);
dash_start('Applicant: ' . $c['name'], 'employer/applicants');
?>
<div class="page-head"><a class="back-link" href="<?= url('employer/applicants?job=' . $a['job_id']) ?>">← <?= e($a['job_title']) ?></a><div class="btn-row"><?php if ($prev): ?><a class="btn btn-ghost btn-sm" href="<?= url('employer/applicants/' . $prev) ?>">‹ Prev</a><?php endif; ?><?php if ($next): ?><a class="btn btn-ghost btn-sm" href="<?= url('employer/applicants/' . $next) ?>">Next ›</a><?php endif; ?></div></div>
<div class="cand-layout">
  <div class="cand-main">
    <div class="card cand-head">
      <?= avatar_html($c['avatar'], $c['name'], 'av av-xl') ?>
      <div class="grow"><h1><?= e($c['name']) ?></h1><p><?= e($c['headline'] ?? '') ?></p>
        <div class="chips"><?php if ($c['experience_years'] !== null): ?><span><?= (float) $c['experience_years'] ?> yrs experience</span><?php endif; ?><?php if ($c['location_id']): ?><span><?= icon('pin', 'i i-xs') ?><?= e(val('SELECT name FROM locations WHERE id=?', [$c['location_id']])) ?></span><?php endif; ?><?php if ($a['expected_salary']): ?><span>Expects <?= e(setting('currency', 'NPR')) ?> <?= number_format((int) $a['expected_salary']) ?></span><?php endif; ?></div>
        <div class="contact-row"><a href="mailto:<?= e($c['email']) ?>"><?= icon('mail', 'i i-sm') ?><?= e($c['email']) ?></a><?php if ($c['phone']): ?><a href="tel:<?= e($c['phone']) ?>"><?= icon('phone', 'i i-sm') ?><?= e($c['phone']) ?></a><a href="https://wa.me/<?= e(preg_replace('/\D/', '', (str_starts_with($c['phone'], '+') ? '' : '977') . $c['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?><?php if ($c['linkedin']): ?><a href="<?= e($c['linkedin']) ?>" target="_blank" rel="noopener">LinkedIn</a><?php endif; ?><?php if ($c['portfolio_url']): ?><a href="<?= e($c['portfolio_url']) ?>" target="_blank" rel="noopener">Portfolio</a><?php endif; ?></div>
      </div>
      <?php if ($a['match_score'] !== null): ?><div class="ring ring-sm" style="--p:<?= $a['match_score'] ?>"><span><?= $a['match_score'] ?>%</span></div><?php endif; ?>
    </div>
    <?php if ($jobSkills): ?><div class="card"><h2>Skill match</h2><div class="skill-tags"><?php foreach ($jobSkills as $s): ?><span class="stag <?= $has($s) ? 'yes' : 'no' ?>"><?= icon($has($s) ? 'check' : 'x', 'i i-xs') ?><?= e($s) ?></span><?php endforeach; ?></div>
      <?php $extra = array_diff($mine, $jobSkills); if ($extra): ?><p class="muted small">Also: <?= e(implode(', ', array_slice($extra, 0, 12))) ?></p><?php endif; ?></div><?php endif; ?>
    <?php if ($a['resume_path']): ?><div class="card"><div class="label-row"><h2>CV</h2><a class="btn btn-ghost btn-sm" href="<?= url('file/resume/app/' . $a['id'] . '?dl=1') ?>"><?= icon('download', 'i i-sm') ?>Download</a></div>
      <?php if ($isPdf): ?><iframe class="cv-frame" src="<?= url('file/resume/app/' . $a['id']) ?>" title="CV preview" loading="lazy"></iframe><?php else: ?><p class="muted">Word document - download to view.</p><?php endif; ?></div><?php endif; ?>
    <?php if ($a['cover_letter']): ?><div class="card prose"><h2>Cover letter</h2><?= nl2br(e($a['cover_letter'])) ?></div><?php endif; ?>
    <?php if ($answers): ?><div class="card"><h2>Screening answers</h2><?php foreach ($answers as $x): ?><p><b><?= e($x['q']) ?></b><br><?= e($x['a'] ?: '-') ?></p><?php endforeach; ?></div><?php endif; ?>
    <?php if ($c['summary']): ?><div class="card prose"><h2>Summary</h2><?= nl2br(e($c['summary'])) ?></div><?php endif; ?>
    <?php if ($exp): ?><div class="card"><h2>Experience</h2><?php foreach ($exp as $x): ?><div class="tl-item"><div><b><?= e($x['title']) ?></b><small><?= e($x['company']) ?> · <?= $x['start_date'] ? date('M Y', strtotime($x['start_date'])) : '' ?> – <?= $x['is_current'] ? 'Present' : ($x['end_date'] ? date('M Y', strtotime($x['end_date'])) : '') ?></small><?php if ($x['description']): ?><p><?= nl2br(e($x['description'])) ?></p><?php endif; ?></div></div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($edu): ?><div class="card"><h2>Education</h2><?php foreach ($edu as $x): ?><div class="tl-item"><div><b><?= e($x['degree']) ?></b><small><?= e($x['institution']) ?><?= $x['year_completed'] ? ' · ' . (int) $x['year_completed'] : '' ?><?= $x['grade'] ? ' · ' . e($x['grade']) : '' ?></small></div></div><?php endforeach; ?></div><?php endif; ?>
  </div>
  <aside class="cand-side">
    <form method="post" class="card form status-form" data-status-form><?= csrf_field() ?><input type="hidden" name="action" value="status">
      <h3>Stage</h3>
      <div class="stage-pills"><?php foreach (['viewed', 'shortlisted', 'interview', 'offered', 'hired', 'rejected'] as $s): ?><label class="pill pill-<?= $s ?>"><input type="radio" name="status" value="<?= $s ?>" <?= $a['status'] === $s ? 'checked' : '' ?> <?= $a['status'] === 'withdrawn' ? 'disabled' : '' ?>><span><?= APP_STATUSES[$s] ?></span></label><?php endforeach; ?></div>
      <div class="field" data-interview-field <?= $a['status'] === 'interview' ? '' : 'hidden' ?>><label>Interview date & time</label><input type="datetime-local" name="interview_at" value="<?= $a['interview_at'] ? date('Y-m-d\TH:i', strtotime($a['interview_at'])) : '' ?>"></div>
      <div class="field"><label>Message to candidate <span class="muted">(optional)</span></label><textarea name="message" rows="3" placeholder="e.g. Please bring your original documents."></textarea></div>
      <label class="check"><input type="checkbox" name="notify" value="1" checked> Email the candidate</label>
      <button class="btn btn-dark btn-block" <?= $a['status'] === 'withdrawn' ? 'disabled' : '' ?>>Update stage</button>
      <?php if ($a['status'] === 'withdrawn'): ?><p class="muted small">Candidate withdrew this application.</p><?php endif; ?>
    </form>
    <form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="action" value="note">
      <h3>Private notes</h3>
      <div class="stars" data-stars><?php for ($i = 5; $i >= 1; $i--): ?><label><input type="radio" name="rating" value="<?= $i ?>" <?= (int) $a['rating'] === $i ? 'checked' : '' ?>><span>★</span></label><?php endfor; ?></div>
      <textarea name="note" rows="4" placeholder="Only your team can see this"><?= e($a['employer_note']) ?></textarea>
      <button class="btn btn-ghost btn-block">Save note</button>
    </form>
    <div class="card"><h3>Activity</h3><ul class="timeline timeline-sm"><?php foreach ($events as $ev): ?><li><b><?= e(APP_STATUSES[$ev['status']] ?? label($ev['status'])) ?></b><?php if ($ev['note'] && $ev['status'] !== 'viewed'): ?><p><?= e(excerpt($ev['note'], 120)) ?></p><?php endif; ?><time><?= date('d M, h:i A', strtotime($ev['created_at'])) ?><?= $ev['actor'] ? ' · ' . e($ev['actor']) : '' ?></time></li><?php endforeach; ?></ul></div>
    <?php if ($other): ?><div class="card"><h3>Also applied to</h3><?php foreach ($other as $o): ?><a class="list-row" href="<?= url('employer/applicants/' . $o['id']) ?>"><span class="grow"><?= e($o['title']) ?></span><?= status_badge($o['status']) ?></a><?php endforeach; ?></div><?php endif; ?>
  </aside>
</div>
<?php dash_end();
