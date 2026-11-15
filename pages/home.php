<?php
$stats = [
    'jobs' => (int) val("SELECT COUNT(*) FROM jobs j JOIN companies c ON c.id=j.company_id WHERE " . JOB_LIVE),
    'companies' => (int) val("SELECT COUNT(*) FROM companies WHERE status='active'"),
    'seekers' => (int) val("SELECT COUNT(*) FROM users WHERE role='seeker'"),
    'week' => (int) val("SELECT COUNT(*) FROM jobs j JOIN companies c ON c.id=j.company_id WHERE " . JOB_LIVE . " AND j.published_at > NOW() - INTERVAL 7 DAY"),
];
$featured = rows(JOB_SELECT . " WHERE " . JOB_LIVE . " AND j.featured=1 ORDER BY j.published_at DESC LIMIT 6");
$latest = rows(JOB_SELECT . " WHERE " . JOB_LIVE . " ORDER BY j.published_at DESC LIMIT 9");
$catCounts = rows("SELECT cat.*, COUNT(j.id) n FROM categories cat LEFT JOIN jobs j ON j.category_id=cat.id AND j.status='active' AND (j.deadline IS NULL OR j.deadline>=CURDATE()) GROUP BY cat.id ORDER BY n DESC, cat.sort LIMIT 12");
$locCounts = rows("SELECT l.*, COUNT(j.id) n FROM locations l LEFT JOIN jobs j ON j.location_id=l.id AND j.status='active' AND (j.deadline IS NULL OR j.deadline>=CURDATE()) GROUP BY l.id ORDER BY n DESC, l.sort LIMIT 8");
$topCompanies = rows("SELECT c.*, (SELECT COUNT(*) FROM jobs j WHERE j.company_id=c.id AND j.status='active' AND (j.deadline IS NULL OR j.deadline>=CURDATE())) n FROM companies c WHERE c.status='active' ORDER BY n DESC, c.verified DESC, c.views DESC LIMIT 8");
$popular = array_column(rows("SELECT keyword, COUNT(*) n FROM search_logs WHERE CHAR_LENGTH(keyword)>=3 AND created_at > NOW() - INTERVAL 30 DAY GROUP BY keyword ORDER BY n DESC LIMIT 6"), 'keyword') ?: ['Accountant', 'Web developer', 'Sales officer', 'Teacher', 'Remote', 'Internship'];

$u = current_user(); $savedIds = []; $profile = null;
if ($u && $u['role'] === 'seeker') {
    $savedIds = array_column(rows('SELECT job_id FROM saved_jobs WHERE user_id=?', [$u['id']]), 'job_id');
    $profile = seeker_profile((int) $u['id']);
}
$site = setting('site_name', 'KaamKhoj');
layout_start([
    'description' => setting('site_description'), 'keywords' => setting('site_keywords'), 'body_class' => 'home',
    'jsonld' => [
        ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $site, 'url' => url(''),
            'potentialAction' => ['@type' => 'SearchAction', 'target' => url('jobs') . '?q={search_term_string}', 'query-input' => 'required name=search_term_string']],
        ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $site, 'url' => url(''), 'logo' => asset('img/favicon.svg')],
    ],
]);
?>
<section class="hero">
  <div class="hero-bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="grid-lines"></span></div>
  <div class="hero-photos" aria-hidden="true">
    <figure class="hp hp-1"><img src="<?= e(site_image('img_hero', 700)) ?>" alt="" loading="eager" onerror="this.parentNode.remove()"><figcaption><?= icon('check', 'i i-xs') ?> Shortlisted</figcaption></figure>
    <figure class="hp hp-2"><img src="<?= e(site_image('img_hero_2', 600)) ?>" alt="" loading="eager" onerror="this.parentNode.remove()"><figcaption><?= icon('calendar', 'i i-xs') ?> Interview Thu</figcaption></figure>
  </div>
  <div class="wrap hero-in">
    <p class="eyebrow reveal"><span class="pulse"></span><?= number_format($stats['week']) ?> new jobs this week across <?= e(setting('country', 'Nepal')) ?></p>
    <h1 class="reveal">Find work that <em>fits</em> you,<br>not just any job.</h1>
    <p class="hero-sub reveal">Search verified employers, apply in one tap, and follow every application from “sent” to “hired”.</p>

    <div class="searchbox reveal" data-searchbox>
      <div class="sb-tabs" role="tablist">
        <button type="button" class="on" data-sb-tab="classic" role="tab">Search</button>
        <?php if (ai_enabled()): ?><button type="button" data-sb-tab="ai" role="tab"><?= icon('sparkles', 'i i-sm') ?>Ask AI</button><?php endif; ?>
      </div>
      <form class="sb-form" data-sb-panel="classic" action="<?= url('jobs') ?>" method="get" role="search">
        <label class="sb-field sb-kw"><?= icon('search') ?><input name="q" placeholder="Job title, skill or company" autocomplete="off" data-suggest aria-label="Keyword"><div class="suggest" hidden></div></label>
        <label class="sb-field sb-loc"><?= icon('pin') ?><select name="location" aria-label="Location"><?= options(array_map(fn($l) => ['id' => $l['slug'], 'name' => $l['name']], locations()), '', 'All of ' . setting('country', 'Nepal')) ?></select></label>
        <button class="btn btn-brand sb-go"><?= icon('search', 'i show-sm') ?><span class="hide-sm">Search jobs</span></button>
      </form>
      <?php if (ai_enabled()): ?>
      <form class="sb-form sb-ai" data-sb-panel="ai" hidden data-ai-search>
        <label class="sb-field sb-kw"><?= icon('sparkles') ?><input name="text" placeholder="e.g. remote React job in Kathmandu above 60k" aria-label="Describe the job"></label>
        <button class="btn btn-brand sb-go"><span>Find</span></button>
      </form>
      <?php endif; ?>
      <div class="popular"><span>Popular:</span><?php foreach ($popular as $p): ?><a href="<?= url('jobs?q=' . urlencode($p)) ?>"><?= e($p) ?></a><?php endforeach; ?></div>
    </div>

    <dl class="stats reveal">
      <div><dt data-count="<?= $stats['jobs'] ?>">0</dt><dd>Live jobs</dd></div>
      <div><dt data-count="<?= $stats['companies'] ?>">0</dt><dd>Companies</dd></div>
      <div><dt data-count="<?= $stats['seekers'] ?>">0</dt><dd>Job seekers</dd></div>
    </dl>
  </div>
</section>

<?php if ($topCompanies): ?>
<section class="marquee-sec" aria-label="Companies hiring">
  <div class="marquee"><div class="marquee-track">
    <?php for ($r = 0; $r < 2; $r++) foreach ($topCompanies as $c): ?>
      <a class="mq-item" href="<?= url('company/' . $c['slug']) ?>" <?= $r ? 'aria-hidden="true" tabindex="-1"' : '' ?>><?= avatar_html($c['logo'], $c['name'], 'logo-box logo-sm') ?><span><?= e($c['name']) ?></span><?php if ($c['n']): ?><small><?= $c['n'] ?> open</small><?php endif; ?></a>
    <?php endforeach; ?>
  </div></div>
</section>
<?php endif; ?>

<section class="sec wrap">
  <div class="sec-head reveal"><div><p class="kicker">Explore</p><h2>Browse by category</h2></div><a class="link-arrow" href="<?= url('jobs') ?>">All jobs <?= icon('arrow', 'i i-sm') ?></a></div>
  <div class="cat-grid">
    <?php foreach ($catCounts as $i => $c): ?>
      <a class="cat-card reveal" style="--d:<?= $i * 40 ?>ms" href="<?= url('jobs/category/' . $c['slug']) ?>"><span class="cat-photo"><img src="<?= e(cat_image($c, 480)) ?>" alt="<?= e($c['name']) ?> jobs" loading="lazy" onerror="this.remove()"></span><span class="cat-ic"><?= e($c['icon']) ?></span><b><?= e($c['name']) ?></b><small><?= (int) $c['n'] ?> job<?= $c['n'] == 1 ? '' : 's' ?></small></a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($featured): ?>
<section class="sec sec-tint">
  <div class="wrap">
    <div class="sec-head reveal"><div><p class="kicker">Hand-picked</p><h2>Featured jobs</h2></div><a class="link-arrow" href="<?= url('jobs?featured=1') ?>">See all <?= icon('arrow', 'i i-sm') ?></a></div>
    <div class="job-grid"><?php foreach ($featured as $j) echo job_card($j, ['saved' => in_array($j['id'], $savedIds), 'match' => match_score($profile, $j)]); ?></div>
  </div>
</section>
<?php endif; ?>

<section class="sec wrap">
  <div class="sec-head reveal"><div><p class="kicker">Fresh</p><h2>Latest openings</h2></div><a class="link-arrow" href="<?= url('jobs?sort=new') ?>">View all <?= icon('arrow', 'i i-sm') ?></a></div>
  <?php if ($latest): ?>
    <div class="job-grid"><?php foreach ($latest as $j) echo job_card($j, ['saved' => in_array($j['id'], $savedIds), 'match' => match_score($profile, $j)]); ?></div>
  <?php else: ?>
    <div class="empty"><?= icon('briefcase', 'i i-xl') ?><h3>No jobs posted yet</h3><p>Be the first employer to post a job.</p><a class="btn btn-brand" href="<?= url('register?role=employer') ?>">Post a job</a></div>
  <?php endif; ?>
</section>

<section class="sec sec-dark">
  <div class="wrap split">
    <div class="reveal">
      <p class="kicker kicker-light">How it works</p>
      <h2>From search to offer -<br>without the guesswork.</h2>
      <ol class="steps">
        <li><b>Build your profile once</b><span>Upload your CV, add skills. AI can write your summary for you.</span></li>
        <li><b>Get matched</b><span>Every job shows how well it fits your skills, experience and city.</span></li>
        <li><b>Apply in one tap</b><span>Use your saved CV. AI drafts a tailored cover letter if you want one.</span></li>
        <li><b>Track every step</b><span>See when you’re viewed, shortlisted or invited - by email and on your dashboard.</span></li>
      </ol>
      <a class="btn btn-brand" href="<?= url('register') ?>">Create free profile</a>
    </div>
    <div class="track-demo reveal" aria-hidden="true">
      <img class="td-photo" src="<?= e(site_image('img_how', 900)) ?>" alt="" loading="lazy" onerror="this.remove()">
      <div class="td-card"><div class="td-head"><span class="logo-box logo-sm av-txt" style="--h:210">NB</span><div><b>Senior Accountant</b><small>Nabil Group · Kathmandu</small></div></div>
        <ul class="td-steps"><li class="done">Applied</li><li class="done">Viewed</li><li class="done">Shortlisted</li><li class="cur">Interview · Thu 11 AM</li><li>Offer</li></ul></div>
      <div class="td-pop"><?= icon('bell', 'i i-sm') ?> You’ve been shortlisted!</div>
    </div>
  </div>
</section>

<section class="sec wrap">
  <div class="sec-head reveal"><div><p class="kicker">Near you</p><h2>Jobs by city</h2></div></div>
  <div class="city-grid">
    <?php foreach ($locCounts as $i => $l): ?>
      <a class="city reveal" style="--d:<?= $i * 40 ?>ms;<?= bg_img(loc_image($l, 600), 'linear-gradient(180deg,rgba(18,16,25,.05) 30%,rgba(18,16,25,.82))') ?>" href="<?= url('jobs/in/' . $l['slug']) ?>"><b><?= e($l['name']) ?></b><small><?= icon('pin', 'i i-xs') ?><?= (int) $l['n'] ?> open jobs</small></a>
    <?php endforeach; ?>
  </div>
</section>

<section class="sec wrap">
  <div class="cta-band reveal" style="<?= bg_img(site_image('img_cta', 1600), 'linear-gradient(110deg,rgba(22,20,29,.96) 35%,rgba(42,29,58,.75) 65%,' . 'rgba(225,29,72,.35))') ?>">
    <div><h2>Hiring? Reach thousands of candidates.</h2><p>Free company profile, AI-written job posts, and an applicant pipeline that keeps everyone informed.</p></div>
    <div class="cta-actions"><a class="btn btn-light" href="<?= url('register?role=employer') ?>">Post a job free</a><a class="btn btn-outline-light" href="<?= url('employers') ?>">Learn more</a></div>
  </div>
</section>
<?php layout_end();
