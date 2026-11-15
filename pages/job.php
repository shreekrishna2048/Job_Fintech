<?php
$j = row(str_replace('SELECT j.*', 'SELECT j.*, c.tagline company_tagline, c.about company_about, c.company_size, c.website company_website, c.owner_id, ind.name industry_name', JOB_SELECT) . ' LEFT JOIN industries ind ON ind.id=c.industry_id WHERE j.slug=?', [param('slug')]);
if (!$j) abort(404, 'This job does not exist or was removed.');
$u = current_user();
$isOwner = $u && ((int) $j['owner_id'] === (int) $u['id'] || $u['role'] === 'admin');
$live = $j['status'] === 'active' && (!$j['deadline'] || $j['deadline'] >= date('Y-m-d'));
if ($j['status'] !== 'active' && $j['status'] !== 'closed' && !$isOwner) abort(404, 'This job is not available.');

// Count a view once per session per job (ignore bots and the owner)
if (!is_bot() && !$isOwner && empty($_SESSION['viewed'][$j['id']])) {
    $_SESSION['viewed'][$j['id']] = 1;
    q('UPDATE jobs SET views=views+1 WHERE id=?', [$j['id']]);
    insert('job_views', ['job_id' => $j['id'], 'user_id' => uid(), 'ip' => client_ip(), 'referrer' => mb_substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255)]);
}

$app = null; $saved = false; $profile = null; $match = null;
if ($u && $u['role'] === 'seeker') {
    $app = row('SELECT * FROM applications WHERE job_id=? AND user_id=?', [$j['id'], $u['id']]);
    $saved = (bool) val('SELECT 1 FROM saved_jobs WHERE user_id=? AND job_id=?', [$u['id'], $j['id']]);
    $profile = seeker_profile((int) $u['id']);
    $match = match_score($profile, $j);
}
$jobSkills = skills_arr($j['skills']);
$mySkills = $profile ? skills_arr($profile['skills']) : [];
$hasSkill = fn($s) => (bool) array_filter($mySkills, fn($p) => $p === $s || str_contains($p, $s) || str_contains($s, $p));

$similar = rows(JOB_SELECT . ' WHERE ' . JOB_LIVE . ' AND j.id<>? AND (j.category_id<=>? OR j.company_id=?) ORDER BY j.company_id=? DESC, j.published_at DESC LIMIT 4', [$j['id'], $j['category_id'], $j['company_id'], $j['company_id']]);
$dl = days_left($j['deadline']);
$jobUrl = url('job/' . $j['slug']);

$empType = ['full-time' => 'FULL_TIME', 'part-time' => 'PART_TIME', 'contract' => 'CONTRACTOR', 'internship' => 'INTERN', 'freelance' => 'CONTRACTOR', 'temporary' => 'TEMPORARY'][$j['job_type']];
$ld = [
    '@context' => 'https://schema.org/', '@type' => 'JobPosting', 'title' => $j['title'],
    'description' => $j['description'] . ($j['requirements'] ? '<h3>Requirements</h3>' . $j['requirements'] : '') . ($j['benefits'] ? '<h3>Benefits</h3>' . $j['benefits'] : ''),
    'identifier' => ['@type' => 'PropertyValue', 'name' => $j['company_name'], 'value' => (string) $j['id']],
    'datePosted' => date('c', strtotime($j['published_at'] ?? $j['created_at'])),
    'employmentType' => $empType, 'directApply' => $j['apply_method'] === 'portal',
    'hiringOrganization' => array_filter(['@type' => 'Organization', 'name' => $j['company_name'], 'sameAs' => $j['company_website'] ?: url('company/' . $j['company_slug']), 'logo' => $j['company_logo'] ? upload_url($j['company_logo']) : null]),
    'jobLocation' => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $j['location_name'] ?? 'Kathmandu', 'addressCountry' => COUNTRIES[country_of_location($j['location_id'])]['code'] ?? 'NP']],
    'experienceRequirements' => ['@type' => 'OccupationalExperienceRequirements', 'monthsOfExperience' => (int) $j['experience_min'] * 12],
];
if ($j['deadline']) $ld['validThrough'] = date('c', strtotime($j['deadline'] . ' 23:59:59'));
if ($j['work_mode'] === 'remote') { $ld['jobLocationType'] = 'TELECOMMUTE'; $ld['applicantLocationRequirements'] = ['@type' => 'Country', 'name' => country_of_location($j['location_id'])]; }
if (!$j['hide_salary'] && ($j['salary_min'] || $j['salary_max'])) $ld['baseSalary'] = ['@type' => 'MonetaryAmount', 'currency' => $j['currency'] ?: (COUNTRIES[country_of_location($j['location_id'])]['currency'] ?? setting('currency', 'NPR')), 'value' => array_filter(['@type' => 'QuantitativeValue', 'minValue' => $j['salary_min'] ? (int) $j['salary_min'] : null, 'maxValue' => $j['salary_max'] ? (int) $j['salary_max'] : null, 'unitText' => strtoupper($j['salary_period'])])];

layout_start([
    'title' => $j['title'] . ' at ' . $j['company_name'] . ($j['location_name'] ? ', ' . $j['location_name'] : ''),
    'description' => $j['title'] . ' job at ' . $j['company_name'] . ' in ' . ($j['location_name'] ?? setting('country')) . '. ' . excerpt($j['description'], 120),
    'image' => $j['company_logo'] ? upload_url($j['company_logo']) : null, 'og_type' => 'article',
    'noindex' => !$live, 'jsonld' => $live ? [$ld] : [],
]);
?>
<div class="job-page">
  <section class="job-hero">
    <div class="jh-photo" aria-hidden="true" style="<?= bg_img($j['category_slug'] ? cat_image(['slug' => $j['category_slug'], 'image' => val('SELECT image FROM categories WHERE id=?', [$j['category_id']])], 1200) : site_image('img_hero', 1200)) ?>"></div>
    <div class="wrap">
      <nav class="crumbs"><a href="<?= url('') ?>">Home</a><span>/</span><a href="<?= url('jobs') ?>">Jobs</a><?php if ($j['category_name']): ?><span>/</span><a href="<?= url('jobs/category/' . $j['category_slug']) ?>"><?= e($j['category_name']) ?></a><?php endif; ?></nav>
      <?php if ($isOwner && $j['status'] !== 'active'): ?><div class="alert alert-warn">This job is <b><?= e($j['status']) ?></b><?= $j['reject_reason'] ? ' - ' . e($j['reject_reason']) : '' ?>. Only you can see it.</div><?php endif; ?>
      <?php if (!$live && $j['status'] === 'closed' || ($j['deadline'] && $dl < 0)): ?><div class="alert alert-info">This job is no longer accepting applications.</div><?php endif; ?>
      <div class="jh-main">
        <?= avatar_html($j['company_logo'], $j['company_name'], 'logo-box logo-lg') ?>
        <div class="jh-text">
          <h1><?= e($j['title']) ?></h1>
          <p class="jh-co"><a href="<?= url('company/' . $j['company_slug']) ?>"><?= e($j['company_name']) ?></a><?php if ($j['company_verified']): ?><span class="verified" title="Verified employer"><?= icon('check', 'i i-xs') ?></span><?php endif; ?></p>
          <div class="chips chips-lg">
            <span><?= icon('pin', 'i i-xs') ?><?= e($j['location_name'] ?? 'Nepal') ?></span>
            <span><?= icon('briefcase', 'i i-xs') ?><?= e(JOB_TYPES[$j['job_type']]) ?></span>
            <span><?= e(WORK_MODES[$j['work_mode']]) ?></span>
            <span><?= icon('money', 'i i-xs') ?><?= e(salary_text($j)) ?></span>
            <span><?= icon('clock', 'i i-xs') ?>Posted <?= e(time_ago($j['published_at'] ?? $j['created_at'])) ?></span>
          </div>
        </div>
      </div>
      <div class="jh-actions" id="apply-bar">
        <?php if ($isOwner): ?>
          <a class="btn btn-dark" href="<?= url('employer/jobs/' . $j['id'] . '/edit') ?>"><?= icon('edit', 'i i-sm') ?>Edit job</a>
          <a class="btn btn-ghost" href="<?= url('employer/applicants?job=' . $j['id']) ?>"><?= icon('users', 'i i-sm') ?><?= (int) $j['applications_count'] ?> applicants</a>
        <?php elseif ($app): ?>
          <a class="btn btn-ok" href="<?= url('seeker/applications/' . $app['id']) ?>"><?= icon('check', 'i i-sm') ?>Applied · <?= e(APP_STATUSES[$app['status']]) ?></a>
        <?php elseif ($live): ?>
          <?php if ($j['apply_method'] === 'url' && $j['apply_target']): ?>
            <a class="btn btn-brand btn-lg" href="<?= e($j['apply_target']) ?>" target="_blank" rel="nofollow noopener" data-track-apply="<?= $j['id'] ?>">Apply on company site <?= icon('arrow', 'i i-sm') ?></a>
          <?php elseif ($j['apply_method'] === 'email' && $j['apply_target']): ?>
            <a class="btn btn-brand btn-lg" href="mailto:<?= e($j['apply_target']) ?>?subject=<?= rawurlencode('Application: ' . $j['title']) ?>">Apply by email</a>
          <?php elseif (!$u || $u['role'] === 'seeker'): ?>
            <a class="btn btn-brand btn-lg" href="<?= url('job/' . $j['slug'] . '/apply') ?>">Apply now <?= icon('arrow', 'i i-sm') ?></a>
          <?php endif; ?>
        <?php endif; ?>
        <?php if (!$u || $u['role'] === 'seeker'): ?><button class="btn btn-ghost save-btn-lg<?= $saved ? ' on' : '' ?>" data-save="<?= $j['id'] ?>" aria-pressed="<?= $saved ? 'true' : 'false' ?>"><?= icon('bookmark', 'i i-sm') ?><span><?= $saved ? 'Saved' : 'Save' ?></span></button><?php endif; ?>
        <button class="btn btn-ghost" data-share="<?= e($jobUrl) ?>" data-title="<?= e($j['title'] . ' at ' . $j['company_name']) ?>"><?= icon('send', 'i i-sm') ?>Share</button>
      </div>
    </div>
  </section>

  <div class="wrap job-layout">
    <article class="job-body">
      <?php if ($match !== null): ?>
      <section class="card match-card">
        <div class="ring" style="--p:<?= $match ?>"><span><?= $match ?>%</span></div>
        <div>
          <h2>Your profile match</h2>
          <?php if ($jobSkills): ?><div class="skill-tags">
            <?php foreach ($jobSkills as $s): ?><span class="stag <?= $hasSkill($s) ? 'yes' : 'no' ?>"><?= icon($hasSkill($s) ? 'check' : 'x', 'i i-xs') ?><?= e($s) ?></span><?php endforeach; ?>
          </div><?php endif; ?>
          <p class="muted small"><?= $match >= 75 ? 'Strong fit - apply soon.' : ($match >= 50 ? 'Good fit. Highlight related experience in your cover letter.' : 'Add missing skills to your profile if you have them.') ?> <a href="<?= url('seeker/profile') ?>">Update skills</a></p>
        </div>
      </section>
      <?php endif; ?>

      <section class="prose card"><h2>About the role</h2><?= clean_html($j['description']) ?></section>
      <?php if ($j['requirements']): ?><section class="prose card"><h2>Requirements</h2><?= clean_html($j['requirements']) ?></section><?php endif; ?>
      <?php if ($j['benefits']): ?><section class="prose card"><h2>What you get</h2><?= clean_html($j['benefits']) ?></section><?php endif; ?>
      <?php if ($jobSkills): ?><section class="card"><h2>Skills</h2><div class="skill-tags"><?php foreach ($jobSkills as $s): ?><a class="stag" href="<?= url('jobs?q=' . urlencode($s)) ?>"><?= e($s) ?></a><?php endforeach; ?></div></section><?php endif; ?>

      <?php if ($u && $u['role'] === 'seeker' && ai_enabled()): ?>
      <section class="card ai-card">
        <div class="ai-card-head"><h2><?= icon('sparkles') ?>Interview prep</h2><?= ai_button('interview_tips', '#tips-out', 'Get likely questions', ['context' => $j['title'] . ' at ' . $j['company_name'] . "\n" . html_to_text($j['description'] . "\n" . $j['requirements'])]) ?></div>
        <div id="tips-out" class="prose ai-out" hidden></div>
      </section>
      <?php endif; ?>

      <p class="report-line"><button class="linklike" data-report="<?= $j['id'] ?>"><?= icon('flag', 'i i-xs') ?>Report this job</button> · Never pay anyone to get a job.</p>
    </article>

    <aside class="job-side">
      <div class="card overview">
        <h3>Job overview</h3>
        <?php if ($j['deadline']): ?><div class="deadline <?= $dl !== null && $dl <= 3 ? 'soon' : '' ?>"><?= icon('calendar') ?><div><small>Apply before</small><b><?= date('d M Y', strtotime($j['deadline'])) ?></b><?php if ($dl !== null && $dl >= 0): ?><em><?= $dl === 0 ? 'Last day!' : $dl . ' days left' ?></em><?php endif; ?></div></div><?php endif; ?>
        <dl class="ov-list">
          <div><dt>Level</dt><dd><?= e(JOB_LEVELS[$j['job_level']]) ?></dd></div>
          <div><dt>Experience</dt><dd><?= $j['experience_min'] ? (int) $j['experience_min'] . ($j['experience_max'] ? '–' . (int) $j['experience_max'] : '+') . ' years' : 'Freshers welcome' ?></dd></div>
          <div><dt>Vacancies</dt><dd><?= (int) $j['vacancies'] ?></dd></div>
          <?php if ($j['education']): ?><div><dt>Education</dt><dd><?= e($j['education']) ?></dd></div><?php endif; ?>
          <div><dt>Salary</dt><dd><?= e(salary_text($j)) ?></dd></div>
          <?php if ($j['category_name']): ?><div><dt>Category</dt><dd><a href="<?= url('jobs/category/' . $j['category_slug']) ?>"><?= e($j['category_name']) ?></a></dd></div><?php endif; ?>
          <div><dt>Views</dt><dd><?= number_format((int) $j['views']) ?></dd></div>
          <div><dt>Applicants</dt><dd><?= (int) $j['applications_count'] ?></dd></div>
        </dl>
      </div>
      <a class="card co-card" href="<?= url('company/' . $j['company_slug']) ?>">
        <div class="co-card-top"><?= avatar_html($j['company_logo'], $j['company_name'], 'logo-box') ?><div><b><?= e($j['company_name']) ?></b><small><?= e($j['industry_name'] ?? '') ?><?= $j['company_size'] ? ' · ' . e($j['company_size']) . ' employees' : '' ?></small></div></div>
        <?php if ($j['company_about']): ?><p><?= e(excerpt($j['company_about'], 150)) ?></p><?php endif; ?>
        <span class="link-arrow">View company <?= icon('arrow', 'i i-sm') ?></span>
      </a>
    </aside>
  </div>

  <?php if ($similar): ?>
  <section class="sec wrap"><div class="sec-head"><h2>Similar jobs</h2></div><div class="job-grid"><?php foreach ($similar as $s) echo job_card($s, ['match' => match_score($profile, $s)]); ?></div></section>
  <?php endif; ?>
</div>
<?php if ($live && !$app && !$isOwner && (!$u || $u['role'] === 'seeker') && $j['apply_method'] === 'portal'): ?>
<div class="sticky-apply"><div><b><?= e($j['title']) ?></b><small><?= e(salary_text($j)) ?></small></div><a class="btn btn-brand" href="<?= url('job/' . $j['slug'] . '/apply') ?>">Apply now</a></div>
<?php endif; ?>
<?php layout_end();
