<?php
$u = require_login('seeker');
$j = row(JOB_SELECT . ' WHERE j.slug=?', [param('slug')]);
if (!$j) abort(404);
if ($j['status'] !== 'active' || ($j['deadline'] && $j['deadline'] < date('Y-m-d'))) { flash('error', 'This job is no longer accepting applications.'); redirect('job/' . $j['slug']); }
if ($j['apply_method'] !== 'portal') redirect('job/' . $j['slug']);
if ($ex = row('SELECT id FROM applications WHERE job_id=? AND user_id=?', [$j['id'], $u['id']])) { flash('info', 'You already applied for this job.'); redirect('seeker/applications/' . $ex['id']); }
if (setting('require_email_verify', '1') === '1' && !$u['email_verified_at']) { flash('error', 'Please verify your email before applying. Check your inbox or resend from Account settings.'); redirect('account'); }

$profile = seeker_profile((int) $u['id']) ?? [];
$questions = rows('SELECT * FROM job_questions WHERE job_id=? ORDER BY id', [$j['id']]);
$errors = [];

if (is_post()) {
    csrf_check();
    $cover = mb_substr(trim($_POST['cover_letter'] ?? ''), 0, 5000);
    $salary = (int) preg_replace('/\D/', '', $_POST['expected_salary'] ?? '') ?: null;
    $useProfile = ($_POST['resume_choice'] ?? 'profile') === 'profile' && !empty($profile['resume_path']);
    $resume = $useProfile ? $profile['resume_path'] : null; $resumeName = $useProfile ? $profile['resume_name'] : null;
    if (!$useProfile) {
        try {
            $resume = handle_upload('resume', 'resumes', DOC_TYPES, 5);
            $resumeName = $resume ? mb_substr(basename($_FILES['resume']['name']), 0, 190) : null;
        } catch (RuntimeException $e) { $errors['resume'] = $e->getMessage(); }
        if (!$resume && empty($errors['resume'])) $errors['resume'] = 'Please upload your CV (PDF or Word).';
    }
    $answers = [];
    foreach ($questions as $qq) {
        $a = mb_substr(trim($_POST['answer'][$qq['id']] ?? ''), 0, 1000);
        if ($qq['required'] && $a === '') $errors['q' . $qq['id']] = 'This answer is required.';
        $answers[] = ['q' => $qq['question'], 'a' => $a];
    }
    if (!$errors) {
        $match = match_score($profile ?: null, $j);
        db()->beginTransaction();
        try {
            $appId = insert('applications', ['job_id' => $j['id'], 'user_id' => $u['id'], 'cover_letter' => $cover ?: null, 'resume_path' => $resume, 'resume_name' => $resumeName,
                'answers' => $answers ? json_encode($answers, JSON_UNESCAPED_UNICODE) : null, 'expected_salary' => $salary, 'match_score' => $match]);
            insert('application_events', ['application_id' => $appId, 'status' => 'applied', 'note' => 'Application submitted', 'actor_id' => $u['id']]);
            q('UPDATE jobs SET applications_count=applications_count+1 WHERE id=?', [$j['id']]);
            if (!$useProfile && !empty($_POST['save_resume']) && $resume) update('seeker_profiles', ['resume_path' => $resume, 'resume_name' => $resumeName], 'user_id=?', [$u['id']]);
            db()->commit();
        } catch (PDOException $e) {
            db()->rollBack();
            if ($e->getCode() === '23000') { flash('info', 'You already applied for this job.'); redirect('seeker/applications'); }
            throw $e;
        }
        log_activity('apply', 'application', $appId, "Applied to job #{$j['id']} {$j['title']}");
        $owner = row('SELECT u.* FROM users u JOIN companies c ON c.owner_id=u.id WHERE c.id=?', [$j['company_id']]);
        if ($owner && is_placeholder_email($owner['email'])) {
            // Imported company (not yet claimed): forward the application to the company's real email, with the CV attached
            $jx = row('SELECT j.contact_email, j.apply_target, j.apply_method, c.email co_email, c.name co_name, c.slug co_slug FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.id=?', [$j['id']]);
            $fwd = $jx['contact_email'] ?: (!is_placeholder_email($jx['co_email']) ? $jx['co_email'] : null);
            $cvPath = $resume ? ROOT . '/uploads/' . $resume : null;
            if ($fwd) {
                queue_template_email($fwd, $jx['co_name'], 'application_forward', [
                    'company_name' => $jx['co_name'], 'job_title' => $j['title'], 'applicant_name' => $u['name'], 'applicant_email' => $u['email'], 'applicant_phone' => $u['phone'] ?: '',
                    'cover' => $cover ? mb_substr($cover, 0, 1500) : '', 'claim_link' => url('contact?subject=' . rawurlencode('Claim company page: ' . $jx['co_name'])),
                ], true, $cvPath ? [['path' => $cvPath, 'name' => preg_replace('/[^\w .-]/u', '', $u['name']) . ' - CV.' . pathinfo($cvPath, PATHINFO_EXTENSION)]] : []);
                insert('application_events', ['application_id' => $appId, 'status' => 'applied', 'note' => 'Forwarded to the employer by email', 'actor_id' => null]);
            }
            notify_admins('new_applicant', 'Application to imported job: ' . $j['title'], $u['name'] . ' · ' . $jx['co_name'] . ($fwd ? ' · forwarded to ' . $fwd : ' · NO company email - handle manually'), 'admin/applications?job=' . $j['id']);
        } elseif ($owner) {
            notify((int) $owner['id'], 'new_applicant', "New applicant: {$u['name']}", "{$j['title']} · " . ($match !== null ? "$match% match" : ''), 'employer/applicants/' . $appId);
            queue_template_email($owner['email'], $owner['name'], 'application_received_employer', ['name' => $owner['name'], 'applicant_name' => $u['name'], 'job_title' => $j['title'], 'match' => $match ?? '-', 'link' => url('employer/applicants/' . $appId)]);
        }
        notify((int) $u['id'], 'applied', "Applied: {$j['title']}", "Your application to {$j['company_name']} was sent.", 'seeker/applications/' . $appId);
        queue_template_email($u['email'], $u['name'], 'application_received_seeker', ['name' => $u['name'], 'job_title' => $j['title'], 'company_name' => $j['company_name'], 'link' => url('seeker/applications/' . $appId)]);
        flash('success', 'Application sent! We’ll email you when the employer updates it.');
        redirect('seeker/applications/' . $appId);
    }
}

$aiContext = "Candidate: {$u['name']}\nHeadline: " . ($profile['headline'] ?? '') . "\nExperience: " . ($profile['experience_years'] ?? '?') . " years\nSkills: " . ($profile['skills'] ?? '') . "\nSummary: " . ($profile['summary'] ?? '')
    . "\n\nJob: {$j['title']} at {$j['company_name']}\nJob skills: {$j['skills']}\nJob description: " . excerpt($j['description'], 900);
$match = match_score($profile ?: null, $j);
layout_start(['title' => 'Apply: ' . $j['title'], 'noindex' => true]);
?>
<div class="wrap narrow apply-page">
  <a class="back-link" href="<?= url('job/' . $j['slug']) ?>">← Back to job</a>
  <div class="card apply-head">
    <?= avatar_html($j['company_logo'], $j['company_name'], 'logo-box') ?>
    <div><h1><?= e($j['title']) ?></h1><p class="muted"><?= e($j['company_name']) ?> · <?= e($j['location_name'] ?? '') ?> · <?= e(salary_text($j)) ?></p></div>
    <?php if ($match !== null): ?><span class="match m-<?= $match >= 75 ? 'hi' : ($match >= 50 ? 'md' : 'lo') ?>"><?= $match ?>% match</span><?php endif; ?>
  </div>

  <form method="post" enctype="multipart/form-data" class="card form" data-loading>
    <?= csrf_field() ?>
    <div class="applicant-mini"><?= avatar_html($u['avatar'], $u['name'], 'av av-md') ?><div><b><?= e($u['name']) ?></b><small><?= e($u['email']) ?><?= $u['phone'] ? ' · ' . e($u['phone']) : '' ?></small></div><a class="btn btn-ghost btn-sm" href="<?= url('seeker/profile') ?>">Edit profile</a></div>

    <fieldset class="field">
      <legend>CV / Resume</legend>
      <?php if (!empty($profile['resume_path'])): ?>
        <label class="radio-card"><input type="radio" name="resume_choice" value="profile" checked><span><?= icon('file') ?><b><?= e($profile['resume_name'] ?: 'My CV') ?></b><small>From your profile</small></span></label>
        <label class="radio-card"><input type="radio" name="resume_choice" value="upload"><span><?= icon('upload') ?><b>Upload a different CV</b><small>PDF, DOC, DOCX · max 5 MB</small></span></label>
      <?php else: ?><input type="hidden" name="resume_choice" value="upload"><?php endif; ?>
      <div class="upload-zone" data-upload-zone <?= !empty($profile['resume_path']) ? 'data-show-when="upload"' : '' ?>>
        <input type="file" name="resume" accept=".pdf,.doc,.docx" id="resume-in">
        <label for="resume-in"><?= icon('upload') ?><b>Choose file</b> or drag it here<small>PDF, DOC, DOCX · max 5 MB</small></label>
        <?php if (empty($profile['resume_path'])): ?><label class="check"><input type="checkbox" name="save_resume" value="1" checked> Also save this CV to my profile</label><?php endif; ?>
      </div>
      <?php if (!empty($errors['resume'])): ?><p class="err"><?= e($errors['resume']) ?></p><?php endif; ?>
    </fieldset>

    <div class="field">
      <div class="label-row"><label for="cl">Cover letter <span class="muted">(optional, recommended)</span></label><?= ai_button('cover_letter', '#cl', 'Draft with AI', ['context' => $aiContext]) ?></div>
      <textarea id="cl" name="cover_letter" rows="8" maxlength="5000" placeholder="Tell the employer why you’re a great fit…"><?= e($_POST['cover_letter'] ?? '') ?></textarea>
    </div>
    <div class="field"><label for="es">Expected salary (<?= e(setting('currency', 'NPR')) ?>/month)</label><input id="es" name="expected_salary" inputmode="numeric" value="<?= e($_POST['expected_salary'] ?? ($profile['expected_salary'] ?? '')) ?>"></div>
    <?php foreach ($questions as $qq): ?>
      <div class="field"><label><?= e($qq['question']) ?><?= $qq['required'] ? ' *' : '' ?></label><textarea name="answer[<?= $qq['id'] ?>]" rows="2" <?= $qq['required'] ? 'required' : '' ?>><?= e($_POST['answer'][$qq['id']] ?? '') ?></textarea><?php if (!empty($errors['q' . $qq['id']])): ?><p class="err"><?= e($errors['q' . $qq['id']]) ?></p><?php endif; ?></div>
    <?php endforeach; ?>
    <button class="btn btn-brand btn-lg btn-block"><?= icon('send', 'i i-sm') ?>Submit application</button>
    <p class="muted small center">Your profile and CV will be shared with <?= e($j['company_name']) ?> only.</p>
  </form>
</div>
<?php layout_end();
