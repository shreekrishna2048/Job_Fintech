<?php
$u = require_login('seeker');
$uid = (int) $u['id'];
if (!seeker_profile($uid)) insert('seeker_profiles', ['user_id' => $uid]);
if (is_post()) {
    csrf_check();
    $act = input('action');
    try {
        if ($act === 'basic') {
            $skills = implode(', ', array_slice(array_map(fn($s) => mb_substr($s, 0, 40), skills_arr(input('skills'))), 0, 30));
            update('seeker_profiles', [
                'headline' => mb_substr(input('headline'), 0, 160) ?: null, 'summary' => mb_substr(input('summary'), 0, 3000) ?: null,
                'location_id' => (int) input('location_id') ?: null, 'experience_years' => input('experience_years') === '' ? null : min(50, max(0, (float) input('experience_years'))),
                'current_salary' => (int) preg_replace('/\D/', '', input('current_salary')) ?: null, 'expected_salary' => (int) preg_replace('/\D/', '', input('expected_salary')) ?: null,
                'skills' => $skills ?: null, 'preferred_category_id' => (int) input('preferred_category_id') ?: null,
                'preferred_job_type' => isset(JOB_TYPES[input('preferred_job_type')]) ? input('preferred_job_type') : null,
                'linkedin' => filter_var(input('linkedin'), FILTER_VALIDATE_URL) ?: null, 'portfolio_url' => filter_var(input('portfolio_url'), FILTER_VALIDATE_URL) ?: null,
                'open_to_work' => !empty($_POST['open_to_work']) ? 1 : 0, 'visible_to_employers' => !empty($_POST['visible_to_employers']) ? 1 : 0,
            ], 'user_id=?', [$uid]);
            if ($ph = mb_substr(input('phone'), 0, 30)) update('users', ['phone' => $ph], 'id=?', [$uid]);
            flash('success', 'Profile saved.');
        } elseif ($act === 'resume') {
            if ($r = handle_upload('resume', 'resumes', DOC_TYPES, 5)) {
                $old = val('SELECT resume_path FROM seeker_profiles WHERE user_id=?', [$uid]);
                update('seeker_profiles', ['resume_path' => $r, 'resume_name' => mb_substr(basename($_FILES['resume']['name']), 0, 190)], 'user_id=?', [$uid]);
                if ($old && !val('SELECT 1 FROM applications WHERE resume_path=? LIMIT 1', [$old])) delete_upload($old); // keep files used by applications
                flash('success', 'CV uploaded.');
            }
        } elseif ($act === 'add_exp') {
            if (input('title') && input('company')) insert('seeker_experience', ['user_id' => $uid, 'title' => mb_substr(input('title'), 0, 150), 'company' => mb_substr(input('company'), 0, 150),
                'start_date' => input('start_date') ? input('start_date') . '-01' : null, 'end_date' => !empty($_POST['is_current']) || !input('end_date') ? null : input('end_date') . '-01',
                'is_current' => !empty($_POST['is_current']) ? 1 : 0, 'description' => mb_substr(input('description'), 0, 2000) ?: null]);
        } elseif ($act === 'del_exp') q('DELETE FROM seeker_experience WHERE id=? AND user_id=?', [(int) input('id'), $uid]);
        elseif ($act === 'add_edu') {
            if (input('degree') && input('institution')) insert('seeker_education', ['user_id' => $uid, 'degree' => mb_substr(input('degree'), 0, 150), 'institution' => mb_substr(input('institution'), 0, 190),
                'year_completed' => (int) input('year_completed') ?: null, 'grade' => mb_substr(input('grade'), 0, 40) ?: null]);
        } elseif ($act === 'del_edu') q('DELETE FROM seeker_education WHERE id=? AND user_id=?', [(int) input('id'), $uid]);
        log_activity('profile_update', 'seeker', $uid, $act);
    } catch (RuntimeException $e) { flash('error', $e->getMessage()); }
    redirect('seeker/profile' . (in_array($act, ['add_exp', 'del_exp'], true) ? '#experience' : (in_array($act, ['add_edu', 'del_edu'], true) ? '#education' : '')));
}
$p = seeker_profile($uid);
$exp = rows('SELECT * FROM seeker_experience WHERE user_id=? ORDER BY is_current DESC, start_date DESC', [$uid]);
$edu = rows('SELECT * FROM seeker_education WHERE user_id=? ORDER BY year_completed DESC', [$uid]);
$comp = profile_completeness($uid);
$aiCtx = "Name: {$u['name']}\nHeadline: {$p['headline']}\nYears of experience: {$p['experience_years']}\nSkills: {$p['skills']}\nExperience: " . implode('; ', array_map(fn($e) => "{$e['title']} at {$e['company']}", $exp)) . "\nEducation: " . implode('; ', array_map(fn($e) => "{$e['degree']}, {$e['institution']}", $edu));
dash_start('My profile', 'seeker/profile');
?>
<div class="page-head"><div><h1>My profile</h1><p class="muted"><?= $comp['percent'] ?>% complete · this is what employers see</p></div><div class="progress"><span style="width:<?= $comp['percent'] ?>%"></span></div></div>

<form method="post" class="card form" data-loading><?= csrf_field() ?><input type="hidden" name="action" value="basic">
  <h2>About you</h2>
  <div class="field"><label>Professional headline</label><input name="headline" maxlength="160" placeholder="e.g. Accountant with 4 years in banking" value="<?= e($p['headline']) ?>"></div>
  <div class="field"><div class="label-row"><label for="summary">Summary</label><?= ai_button('summary', '#summary', 'Write with AI', ['context' => $aiCtx]) ?></div><textarea id="summary" name="summary" rows="5" maxlength="3000" placeholder="A few lines about your experience and what you’re looking for"><?= e($p['summary']) ?></textarea></div>
  <div class="field"><label>Skills <span class="muted">(press Enter or comma after each)</span></label><div class="tag-input" data-tags><input type="hidden" name="skills" value="<?= e($p['skills']) ?>"><input type="text" placeholder="e.g. Tally, Excel, Communication" data-tag-entry></div></div>
  <div class="row3">
    <div class="field"><label>City</label><select name="location_id"><?= options(locations(), $p['location_id'], 'Select') ?></select></div>
    <div class="field"><label>Experience (years)</label><input name="experience_years" type="number" step="0.5" min="0" max="50" value="<?= e($p['experience_years']) ?>"></div>
    <div class="field"><label>Mobile</label><input name="phone" value="<?= e($u['phone']) ?>" inputmode="tel"></div>
  </div>
  <div class="row3">
    <div class="field"><label>Current salary /month</label><input name="current_salary" inputmode="numeric" value="<?= e($p['current_salary']) ?>"></div>
    <div class="field"><label>Expected salary /month</label><input name="expected_salary" inputmode="numeric" value="<?= e($p['expected_salary']) ?>"></div>
    <div class="field"><label>Preferred job type</label><select name="preferred_job_type"><?= options(JOB_TYPES, $p['preferred_job_type'], 'Any') ?></select></div>
  </div>
  <div class="row2">
    <div class="field"><label>Preferred category</label><select name="preferred_category_id"><?= options(categories(), $p['preferred_category_id'], 'Any') ?></select></div>
    <div class="field"><label>LinkedIn URL</label><input name="linkedin" type="url" value="<?= e($p['linkedin']) ?>"></div>
  </div>
  <div class="field"><label>Portfolio / website</label><input name="portfolio_url" type="url" value="<?= e($p['portfolio_url']) ?>"></div>
  <label class="switch"><input type="checkbox" name="open_to_work" value="1" <?= $p['open_to_work'] ? 'checked' : '' ?>><span></span>Open to work</label>
  <label class="switch"><input type="checkbox" name="visible_to_employers" value="1" <?= $p['visible_to_employers'] ? 'checked' : '' ?>><span></span>Let employers find me in candidate search</label>
  <button class="btn btn-dark">Save profile</button>
</form>

<form method="post" enctype="multipart/form-data" class="card form" id="resume"><?= csrf_field() ?><input type="hidden" name="action" value="resume">
  <h2>CV / Resume</h2>
  <?php if ($p['resume_path']): ?><div class="file-row"><?= icon('file') ?><div class="grow"><b><?= e($p['resume_name'] ?: 'My CV') ?></b><small>Used for one-tap apply</small></div><a class="btn btn-ghost btn-sm" href="<?= url('file/resume/profile/' . $uid) ?>" target="_blank">View</a></div><?php endif; ?>
  <div class="upload-zone" data-upload-zone><input type="file" name="resume" accept=".pdf,.doc,.docx" id="rin" data-autosubmit-file><label for="rin"><?= icon('upload') ?><b><?= $p['resume_path'] ? 'Replace CV' : 'Upload CV' ?></b><small>PDF, DOC, DOCX · max 5 MB</small></label></div>
</form>

<div class="card" id="experience"><h2>Experience</h2>
  <?php foreach ($exp as $x): ?><div class="tl-item"><div class="grow"><b><?= e($x['title']) ?></b><small><?= e($x['company']) ?> · <?= $x['start_date'] ? date('M Y', strtotime($x['start_date'])) : '' ?> – <?= $x['is_current'] ? 'Present' : ($x['end_date'] ? date('M Y', strtotime($x['end_date'])) : '') ?></small><?php if ($x['description']): ?><p><?= nl2br(e($x['description'])) ?></p><?php endif; ?></div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="del_exp"><input type="hidden" name="id" value="<?= $x['id'] ?>"><button class="icon-btn" aria-label="Delete" onclick="return confirm('Remove?')"><?= icon('trash', 'i i-sm') ?></button></form></div><?php endforeach; ?>
  <details class="add-block"><summary><?= icon('plus', 'i i-sm') ?>Add experience</summary>
    <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="add_exp">
      <div class="row2"><div class="field"><label>Job title</label><input name="title" required></div><div class="field"><label>Company</label><input name="company" required></div></div>
      <div class="row3"><div class="field"><label>From</label><input type="month" name="start_date"></div><div class="field"><label>To</label><input type="month" name="end_date"></div><label class="check"><input type="checkbox" name="is_current" value="1"> I work here now</label></div>
      <div class="field"><label>What did you do?</label><textarea name="description" rows="3"></textarea></div>
      <button class="btn btn-dark btn-sm">Add</button>
    </form></details>
</div>

<div class="card" id="education"><h2>Education</h2>
  <?php foreach ($edu as $x): ?><div class="tl-item"><div class="grow"><b><?= e($x['degree']) ?></b><small><?= e($x['institution']) ?><?= $x['year_completed'] ? ' · ' . (int) $x['year_completed'] : '' ?><?= $x['grade'] ? ' · ' . e($x['grade']) : '' ?></small></div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="del_edu"><input type="hidden" name="id" value="<?= $x['id'] ?>"><button class="icon-btn" aria-label="Delete" onclick="return confirm('Remove?')"><?= icon('trash', 'i i-sm') ?></button></form></div><?php endforeach; ?>
  <details class="add-block"><summary><?= icon('plus', 'i i-sm') ?>Add education</summary>
    <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="add_edu">
      <div class="row2"><div class="field"><label>Degree / level</label><input name="degree" placeholder="e.g. BBS, +2 Management, BE Computer" required></div><div class="field"><label>Institution</label><input name="institution" required></div></div>
      <div class="row2"><div class="field"><label>Year completed</label><input type="number" name="year_completed" min="1960" max="2040"></div><div class="field"><label>Grade / GPA</label><input name="grade"></div></div>
      <button class="btn btn-dark btn-sm">Add</button>
    </form></details>
</div>
<?php dash_end();
