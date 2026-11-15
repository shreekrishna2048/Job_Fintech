<?php
require __DIR__ . '/_guard.php';
$cid = (int) $company['id'];
$id = (int) param('id', 0);
$job = $id ? row('SELECT * FROM jobs WHERE id=? AND company_id=?', [$id, $cid]) : null;
if ($id && !$job) abort(404);
$questions = $job ? rows('SELECT * FROM job_questions WHERE job_id=? ORDER BY id', [$id]) : [];
$errors = [];

if (is_post()) {
    csrf_check();
    $publish = input('submit') !== 'draft';
    $d = [
        'title' => mb_substr(input('title'), 0, 190), 'category_id' => (int) input('category_id') ?: null, 'location_id' => (int) input('location_id') ?: null,
        'job_type' => isset(JOB_TYPES[input('job_type')]) ? input('job_type') : 'full-time', 'work_mode' => isset(WORK_MODES[input('work_mode')]) ? input('work_mode') : 'onsite',
        'job_level' => isset(JOB_LEVELS[input('job_level')]) ? input('job_level') : 'mid',
        'experience_min' => min(40, (int) input('experience_min')), 'experience_max' => input('experience_max') === '' ? null : min(50, (int) input('experience_max')),
        'salary_min' => (int) preg_replace('/\D/', '', input('salary_min')) ?: null, 'salary_max' => (int) preg_replace('/\D/', '', input('salary_max')) ?: null,
        'salary_period' => in_array(input('salary_period'), ['month', 'year', 'hour'], true) ? input('salary_period') : 'month',
        'salary_negotiable' => !empty($_POST['salary_negotiable']) ? 1 : 0, 'hide_salary' => !empty($_POST['hide_salary']) ? 1 : 0,
        'vacancies' => max(1, min(999, (int) input('vacancies', 1))), 'education' => mb_substr(input('education'), 0, 120) ?: null,
        'skills' => implode(', ', array_slice(skills_arr(input('skills')), 0, 20)) ?: null,
        'description' => text_to_html(input('description')), 'requirements' => text_to_html(input('requirements')) ?: null, 'benefits' => text_to_html(input('benefits')) ?: null,
        'apply_method' => in_array(input('apply_method'), ['portal', 'email', 'url'], true) ? input('apply_method') : 'portal', 'apply_target' => mb_substr(input('apply_target'), 0, 255) ?: null,
        'currency' => COUNTRIES[country_of_location((int) input('location_id'))]['currency'] ?? null,
        'deadline' => input('deadline') ?: date('Y-m-d', strtotime('+' . (int) setting('job_default_days', '30') . ' days')), 'urgent' => !empty($_POST['urgent']) ? 1 : 0,
    ];
    if (mb_strlen($d['title']) < 3) $errors['title'] = 'Enter a job title.';
    if (mb_strlen(strip_tags($d['description'])) < 50) $errors['description'] = 'Description is too short (min 50 characters). Try “Generate with AI”.';
    if (!$d['location_id']) $errors['location_id'] = 'Choose a location.';
    if ($d['salary_min'] && $d['salary_max'] && $d['salary_min'] > $d['salary_max']) $errors['salary'] = 'Minimum salary is higher than maximum.';
    if ($d['deadline'] < date('Y-m-d')) $errors['deadline'] = 'Deadline must be today or later.';
    if ($d['apply_method'] === 'email' && !filter_var($d['apply_target'], FILTER_VALIDATE_EMAIL)) $errors['apply_target'] = 'Enter a valid email to receive applications.';
    if ($d['apply_method'] === 'url' && !filter_var($d['apply_target'], FILTER_VALIDATE_URL)) $errors['apply_target'] = 'Enter a valid application URL.';
    if ($publish && $company['status'] !== 'active') $errors['title'] = 'Your company is suspended.';
    if ($publish && !impersonator() && setting('employer_require_verify', '0') === '1' && !$company['verified']) $errors['title'] = 'Your company must be verified by admin before publishing. Save as draft for now.';

    if (!$errors) {
        $wasLive = $job && $job['status'] === 'active';
        $firstPublish = false;
        if (!$publish) $d['status'] = 'draft';
        elseif ($wasLive) $d['status'] = 'active';
        else {
            $d['status'] = setting('job_auto_approve', '0') === '1' || $company['verified'] || impersonator() ? 'active' : 'pending';
            if ($d['status'] === 'active' && empty($job['published_at'])) { $d['published_at'] = date('Y-m-d H:i:s'); $firstPublish = true; }
            $d['reject_reason'] = null;
        }
        if ($job) {
            if ($d['title'] !== $job['title']) $d['slug'] = unique_slug('jobs', $d['title'] . '-' . $company['name'], $id);
            update('jobs', $d, 'id=?', [$id]);
        } else {
            $d += ['company_id' => $cid, 'posted_by' => $u['id'], 'slug' => unique_slug('jobs', $d['title'] . '-' . $company['name'])];
            $id = insert('jobs', $d);
        }
        q('DELETE FROM job_questions WHERE job_id=?', [$id]);
        foreach (array_slice((array) ($_POST['q'] ?? []), 0, 5) as $i => $qt) if (trim($qt) !== '') insert('job_questions', ['job_id' => $id, 'question' => mb_substr(trim($qt), 0, 255), 'required' => !empty($_POST['q_req'][$i]) ? 1 : 0]);
        log_activity($job ? 'job_update' : 'job_create', 'job', $id, $d['title'] . ' [' . $d['status'] . ']');
        if ($d['status'] === 'pending') {
            notify_admins('job_pending', 'Job pending approval', $d['title'] . ' · ' . $company['name'], 'admin/jobs?status=pending');
            foreach (rows("SELECT email, name FROM users WHERE role='admin' AND status='active'") as $a) queue_template_email($a['email'], $a['name'], 'admin_new_job', ['job_title' => $d['title'], 'company_name' => $company['name'], 'link' => url('admin/jobs?status=pending')]);
            flash('success', 'Job submitted! It will go live after a quick review.');
        } elseif ($d['status'] === 'active') flash('success', $wasLive ? 'Job updated.' : 'Your job is live!');
        else flash('success', 'Draft saved.');
        if ($firstPublish) on_job_published($id);
        redirect('employer/jobs');
    }
    $job = array_merge($job ?? [], $d, ['description' => input('description'), 'requirements' => input('requirements'), 'benefits' => input('benefits')]);
    $questions = array_map(fn($qt, $i) => ['question' => $qt, 'required' => !empty($_POST['q_req'][$i])], (array) ($_POST['q'] ?? []), array_keys((array) ($_POST['q'] ?? [])));
}
$v = fn($k, $d = '') => $job[$k] ?? $d;
$txt = fn($k) => isset($job[$k]) && preg_match('/<\w/', (string) $job[$k]) ? html_to_text($job[$k]) : ($job[$k] ?? '');
dash_start($id ? 'Edit job' : 'Post a job', $id ? 'employer/jobs' : 'employer/jobs/new');
?>
<div class="page-head"><div><h1><?= $id ? 'Edit job' : 'Post a new job' ?></h1><p class="muted">Clear posts with salary get 2–3× more applicants.</p></div></div>
<?php if ($errors): ?><div class="alert alert-error">Please fix the highlighted fields.</div><?php endif; ?>
<form method="post" class="job-form" data-loading data-job-form><?= csrf_field() ?>
<div class="card form">
  <h2>1. Basics</h2>
  <div class="field"><label for="title">Job title *</label><input id="title" name="title" required maxlength="190" placeholder="e.g. Senior Accountant" value="<?= e($v('title')) ?>"><?php if (!empty($errors['title'])): ?><p class="err"><?= e($errors['title']) ?></p><?php endif; ?></div>
  <div class="row3">
    <div class="field"><label>Category</label><select name="category_id"><?= options(categories(), $v('category_id'), 'Select') ?></select></div>
    <div class="field"><label>Location *</label><select name="location_id" required><?= options(locations(), $v('location_id', $company['location_id']), 'Select') ?></select><?php if (!empty($errors['location_id'])): ?><p class="err"><?= e($errors['location_id']) ?></p><?php endif; ?></div>
    <div class="field"><label>Vacancies</label><input type="number" name="vacancies" min="1" value="<?= e($v('vacancies', 1)) ?>"></div>
  </div>
  <div class="row3">
    <div class="field"><label>Job type</label><select name="job_type" data-ai-type><?= options(JOB_TYPES, $v('job_type', 'full-time')) ?></select></div>
    <div class="field"><label>Work mode</label><select name="work_mode"><?= options(WORK_MODES, $v('work_mode', 'onsite')) ?></select></div>
    <div class="field"><label>Level</label><select name="job_level" data-ai-level><?= options(JOB_LEVELS, $v('job_level', 'mid')) ?></select></div>
  </div>
</div>

<div class="card form ai-gen">
  <div class="label-row"><h2>2. Description</h2>
  <?php if (ai_enabled()): ?><button type="button" class="ai-btn ai-btn-lg" data-ai-job data-company="<?= e($company['name']) ?>"><?= icon('sparkles', 'i i-sm') ?>Generate with AI</button><?php endif; ?></div>
  <?php if (ai_enabled()): ?><div class="field"><label>Quick notes for AI <span class="muted">(optional)</span></label><input data-ai-notes placeholder="e.g. Tally & VAT, 3+ yrs, festival bonus, Saturday off"></div><?php endif; ?>
  <div class="field"><div class="label-row"><label for="description">About the role *</label><?= ai_button('improve', '#description', 'Improve') ?></div><textarea id="description" name="description" rows="7" required><?= e($txt('description')) ?></textarea><?php if (!empty($errors['description'])): ?><p class="err"><?= e($errors['description']) ?></p><?php endif; ?></div>
  <div class="field"><div class="label-row"><label for="requirements">Requirements <span class="muted">(one per line)</span></label><?= ai_button('improve', '#requirements', 'Improve') ?></div><textarea id="requirements" name="requirements" rows="6" placeholder="- Bachelor’s in Accounting&#10;- 3+ years experience"><?= e($txt('requirements')) ?></textarea></div>
  <div class="field"><label for="benefits">Benefits <span class="muted">(one per line)</span></label><textarea id="benefits" name="benefits" rows="4" placeholder="- Festival bonus&#10;- Lunch provided"><?= e($txt('benefits')) ?></textarea></div>
  <div class="field"><label>Skills</label><div class="tag-input" data-tags><input type="hidden" name="skills" id="skills" value="<?= e($v('skills')) ?>"><input type="text" placeholder="Type a skill and press Enter" data-tag-entry></div></div>
</div>

<div class="card form">
  <h2>3. Requirements & salary</h2>
  <div class="row3">
    <div class="field"><label>Min. experience (yrs)</label><input type="number" name="experience_min" min="0" max="40" value="<?= e($v('experience_min', 0)) ?>"></div>
    <div class="field"><label>Max. experience (yrs)</label><input type="number" name="experience_max" min="0" max="50" value="<?= e($v('experience_max')) ?>"></div>
    <div class="field"><label>Education</label><input name="education" placeholder="e.g. Bachelor’s degree" value="<?= e($v('education')) ?>"></div>
  </div>
  <div class="row3">
    <div class="field"><label>Salary from (<?= e(count(countries_enabled()) > 1 ? 'local currency' : setting('currency', 'NPR')) ?>)</label><input name="salary_min" inputmode="numeric" value="<?= e($v('salary_min')) ?>"></div>
    <div class="field"><label>Salary to</label><input name="salary_max" inputmode="numeric" value="<?= e($v('salary_max')) ?>"></div>
    <div class="field"><label>Per</label><select name="salary_period"><?= options(['month' => 'Month', 'year' => 'Year', 'hour' => 'Hour'], $v('salary_period', 'month')) ?></select></div>
  </div>
  <?php if (!empty($errors['salary'])): ?><p class="err"><?= e($errors['salary']) ?></p><?php endif; ?>
  <div class="inline-checks"><label class="check"><input type="checkbox" name="salary_negotiable" value="1" <?= $v('salary_negotiable') ? 'checked' : '' ?>> Negotiable</label><label class="check"><input type="checkbox" name="hide_salary" value="1" <?= $v('hide_salary') ? 'checked' : '' ?>> Hide salary (show “Confidential”)</label></div>
</div>

<div class="card form">
  <h2>4. Applications</h2>
  <div class="row2">
    <div class="field"><label>How should people apply?</label><select name="apply_method" data-apply-method><?= options(['portal' => 'Through this portal (recommended - tracked)', 'email' => 'By email', 'url' => 'On our website'], $v('apply_method', 'portal')) ?></select></div>
    <div class="field" data-apply-target <?= $v('apply_method', 'portal') === 'portal' ? 'hidden' : '' ?>><label>Email / URL</label><input name="apply_target" value="<?= e($v('apply_target')) ?>"><?php if (!empty($errors['apply_target'])): ?><p class="err"><?= e($errors['apply_target']) ?></p><?php endif; ?></div>
  </div>
  <div class="row2"><div class="field"><label>Application deadline</label><input type="date" name="deadline" min="<?= date('Y-m-d') ?>" value="<?= e($v('deadline', date('Y-m-d', strtotime('+' . (int) setting('job_default_days', '30') . ' days')))) ?>"><?php if (!empty($errors['deadline'])): ?><p class="err"><?= e($errors['deadline']) ?></p><?php endif; ?></div>
  <label class="check"><input type="checkbox" name="urgent" value="1" <?= $v('urgent') ? 'checked' : '' ?>> Mark as urgent hiring</label></div>
  <h3>Screening questions <span class="muted">(optional, up to 5)</span></h3>
  <div class="questions" data-questions>
    <?php foreach ($questions ?: [['question' => '', 'required' => 0]] as $i => $qq): ?><div class="q-row"><input name="q[]" placeholder="e.g. Do you have a two-wheeler license?" value="<?= e($qq['question']) ?>"><label class="check"><input type="checkbox" name="q_req[<?= $i ?>]" value="1" <?= $qq['required'] ? 'checked' : '' ?>> Required</label></div><?php endforeach; ?>
  </div>
  <button type="button" class="btn btn-ghost btn-sm" data-add-question><?= icon('plus', 'i i-sm') ?>Add question</button>
</div>

<div class="form-actions sticky-actions">
  <button name="submit" value="draft" class="btn btn-ghost">Save draft</button>
  <button name="submit" value="publish" class="btn btn-brand btn-lg"><?= $job && ($job['status'] ?? '') === 'active' ? 'Update job' : 'Publish job' ?></button>
</div>
</form>
<?php dash_end();
