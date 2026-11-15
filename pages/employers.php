<?php
$stats = ['jobs' => (int) val("SELECT COUNT(*) FROM jobs"), 'apps' => (int) val("SELECT COUNT(*) FROM applications"), 'seekers' => (int) val("SELECT COUNT(*) FROM users WHERE role='seeker'")];
$u = current_user();
layout_start(['title' => 'Post jobs & hire in ' . setting('country', 'Nepal'), 'description' => 'Post jobs free, build your employer brand and manage applicants in one pipeline. AI writes your job posts in seconds.']);
$cta = $u ? ($u['role'] === 'employer' ? 'employer/jobs/new' : 'account') : 'register?role=employer';
?>
<section class="hero hero-emp">
  <div class="emp-photo" aria-hidden="true" style="<?= bg_img(site_image('img_employers', 1600)) ?>"></div>
  <div class="hero-bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="grid-lines"></span></div>
  <div class="wrap hero-in">
    <p class="eyebrow reveal"><span class="pulse"></span>For employers</p>
    <h1 class="reveal">Hire the right people,<br><em>faster.</em></h1>
    <p class="hero-sub reveal">Post a job in two minutes, get matched applicants, and move them through a clear pipeline - candidates are updated automatically.</p>
    <div class="hero-cta reveal"><a class="btn btn-brand btn-lg" href="<?= url($cta) ?>">Post a job free</a><a class="btn btn-ghost btn-lg" href="<?= url('employer/candidates') ?>">Search candidates</a></div>
    <dl class="stats reveal"><div><dt data-count="<?= $stats['seekers'] ?>">0</dt><dd>Candidates</dd></div><div><dt data-count="<?= $stats['jobs'] ?>">0</dt><dd>Jobs posted</dd></div><div><dt data-count="<?= $stats['apps'] ?>">0</dt><dd>Applications</dd></div></dl>
  </div>
</section>
<section class="sec wrap">
  <div class="feature-grid">
    <?php foreach ([
      ['sparkles', 'AI job writer', 'Type a title and a few notes - get a complete description, requirements, benefits and skills.'],
      ['target', 'Match scores', 'Every applicant is scored against your skills, experience and location requirements.'],
      ['layers', 'Hiring pipeline', 'Move candidates from Applied → Shortlisted → Interview → Hired. They are emailed at every step.'],
      ['building', 'Employer brand', 'Company page with logo, cover, culture, perks and a photo portfolio - fully SEO-indexed.'],
      ['search', 'Candidate search', 'Find job seekers who are open to work by skill, city and experience.'],
      ['chart', 'Analytics', 'See views, applications and conversion for every job you post.'],
    ] as $i => [$ic, $t, $d]): ?>
      <div class="feature reveal" style="--d:<?= $i * 50 ?>ms"><span class="feat-ic"><?= icon($ic) ?></span><h3><?= $t ?></h3><p><?= $d ?></p></div>
    <?php endforeach; ?>
  </div>
</section>
<section class="sec wrap"><div class="cta-band reveal"><div><h2>Ready to post your first job?</h2><p>Free to start. Your company profile goes live instantly.</p></div><div class="cta-actions"><a class="btn btn-light" href="<?= url($cta) ?>">Get started</a></div></div></section>
<?php layout_end();
