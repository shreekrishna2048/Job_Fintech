<?php
$c = row("SELECT c.*, i.name industry, i.slug industry_slug, l.name location FROM companies c LEFT JOIN industries i ON i.id=c.industry_id LEFT JOIN locations l ON l.id=c.location_id WHERE c.slug=?", [param('slug')]);
$u = current_user();
if (!$c || ($c['status'] !== 'active' && !($u && ($u['role'] === 'admin' || $u['id'] == $c['owner_id'])))) abort(404, 'Company not found.');
if (!is_bot() && empty($_SESSION['cviewed'][$c['id']])) { $_SESSION['cviewed'][$c['id']] = 1; q('UPDATE companies SET views=views+1 WHERE id=?', [$c['id']]); }

$jobs = rows(JOB_SELECT . ' WHERE ' . JOB_LIVE . ' AND j.company_id=? ORDER BY j.featured DESC, j.published_at DESC', [$c['id']]);
$gallery = rows('SELECT * FROM company_gallery WHERE company_id=? ORDER BY id DESC', [$c['id']]);
$followers = (int) val('SELECT COUNT(*) FROM company_follows WHERE company_id=?', [$c['id']]);
$following = $u ? (bool) val('SELECT 1 FROM company_follows WHERE user_id=? AND company_id=?', [$u['id'], $c['id']]) : false;
$hired = (int) val("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.company_id=? AND a.status='hired'", [$c['id']]);
$profile = $u && $u['role'] === 'seeker' ? seeker_profile((int) $u['id']) : null;
$isOwner = $u && ($u['id'] == $c['owner_id'] || $u['role'] === 'admin');

layout_start([
    'title' => $c['name'] . ' – Jobs, Culture & Company Profile',
    'description' => $c['name'] . ($c['tagline'] ? ': ' . $c['tagline'] . '. ' : '. ') . count($jobs) . ' open jobs. ' . excerpt($c['about'], 110),
    'image' => $c['logo'] ? upload_url($c['logo']) : null,
    'jsonld' => [array_filter(['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $c['name'], 'url' => $c['website'] ?: url('company/' . $c['slug']),
        'logo' => $c['logo'] ? upload_url($c['logo']) : null, 'description' => excerpt($c['about'], 300) ?: null, 'foundingDate' => $c['founded_year'] ? (string) $c['founded_year'] : null,
        'address' => $c['location'] ? ['@type' => 'PostalAddress', 'addressLocality' => $c['location'], 'streetAddress' => $c['address'], 'addressCountry' => COUNTRIES[country_of_location($c['location_id'])]['code'] ?? 'NP'] : null,
        'sameAs' => array_values(array_filter([$c['facebook'], $c['linkedin'], $c['instagram']])) ?: null])],
]);
?>
<section class="co-hero">
  <div class="co-cover" style="<?= bg_img(company_cover($c, 1800), $c['cover'] ? '' : 'linear-gradient(180deg,rgba(18,16,25,.1),rgba(18,16,25,.45))') ?>"></div>
  <div class="wrap co-head">
    <?= avatar_html($c['logo'], $c['name'], 'logo-box logo-xl') ?>
    <div class="co-title">
      <h1><?= e($c['name']) ?><?php if ($c['verified']): ?><span class="verified verified-lg" title="Verified employer"><?= icon('check', 'i i-sm') ?></span><?php endif; ?></h1>
      <?php if ($c['tagline']): ?><p class="co-tagline"><?= e($c['tagline']) ?></p><?php endif; ?>
      <div class="chips"><?php if ($c['industry']): ?><span><?= icon('layers', 'i i-xs') ?><?= e($c['industry']) ?></span><?php endif; ?><?php if ($c['location']): ?><span><?= icon('pin', 'i i-xs') ?><?= e($c['location']) ?></span><?php endif; ?><?php if ($c['company_size']): ?><span><?= icon('users', 'i i-xs') ?><?= e($c['company_size']) ?> employees</span><?php endif; ?></div>
    </div>
    <div class="co-actions">
      <?php if ($isOwner): ?><a class="btn btn-dark" href="<?= url('employer/company') ?>"><?= icon('edit', 'i i-sm') ?>Edit profile</a>
      <?php elseif (!$u || $u['role'] === 'seeker'): ?><button class="btn <?= $following ? 'btn-ghost' : 'btn-brand' ?>" data-follow="<?= $c['id'] ?>" aria-pressed="<?= $following ? 'true' : 'false' ?>"><?= icon('heart', 'i i-sm') ?><span><?= $following ? 'Following' : 'Follow' ?></span></button><?php endif; ?>
      <?php if (!empty($c['imported']) && !$isOwner): ?><a class="btn btn-ghost" href="<?= url('contact?subject=' . rawurlencode('Claim company page: ' . $c['name'])) ?>" title="Manage this page, post jobs and see applicants for free"><?= icon('shield', 'i i-sm') ?><span class="hide-sm">Is this your company?</span></a><?php endif; ?>
      <button class="btn btn-ghost" data-share="<?= e(url('company/' . $c['slug'])) ?>" data-title="<?= e($c['name']) ?>"><?= icon('send', 'i i-sm') ?></button>
    </div>
  </div>
  <div class="wrap">
    <div class="co-stats"><div><b><?= count($jobs) ?></b><span>Open jobs</span></div><div><b data-follow-count><?= $followers ?></b><span>Followers</span></div><div><b><?= number_format((int) $c['views']) ?></b><span>Profile views</span></div><?php if ($hired): ?><div><b><?= $hired ?></b><span>Hired here</span></div><?php endif; ?></div>
    <nav class="tabs" data-tabs><a href="#about" class="on">About</a><a href="#jobs">Jobs <span class="count"><?= count($jobs) ?></span></a><?php if ($gallery): ?><a href="#gallery">Life & portfolio</a><?php endif; ?></nav>
  </div>
</section>

<div class="wrap co-layout">
  <div class="co-main">
    <section id="about" class="tabpane on">
      <div class="card prose"><h2>About <?= e($c['name']) ?></h2><?= $c['about'] ? text_to_html($c['about']) : '<p class="muted">No description yet.</p>' ?></div>
      <?php if ($c['culture']): ?><div class="card prose"><h2>Culture</h2><?= text_to_html($c['culture']) ?></div><?php endif; ?>
      <?php if ($c['benefits']): ?><div class="card prose"><h2>Perks & benefits</h2><?= text_to_html($c['benefits']) ?></div><?php endif; ?>
      <?php if ($jobs): ?><div class="sec-head"><h2>Latest openings</h2><a href="#jobs" class="link-arrow" data-tab-link="jobs">See all</a></div><div class="job-list"><?php foreach (array_slice($jobs, 0, 3) as $j) echo job_card($j, ['match' => match_score($profile, $j)]); ?></div><?php endif; ?>
    </section>
    <section id="jobs" class="tabpane">
      <?php if ($jobs): ?><div class="job-list"><?php foreach ($jobs as $j) echo job_card($j, ['match' => match_score($profile, $j)]); ?></div>
      <?php else: ?><div class="empty"><?= icon('briefcase', 'i i-xl') ?><h3>No open jobs right now</h3><p>Follow <?= e($c['name']) ?> to get notified when they post.</p></div><?php endif; ?>
    </section>
    <?php if ($gallery): ?>
    <section id="gallery" class="tabpane">
      <div class="gallery" data-lightbox><?php foreach ($gallery as $g): ?><figure><a href="<?= e(upload_url($g['image'])) ?>"><img src="<?= e(upload_url($g['image'])) ?>" alt="<?= e($g['caption'] ?: $c['name']) ?>" loading="lazy"></a><?php if ($g['caption']): ?><figcaption><?= e($g['caption']) ?></figcaption><?php endif; ?></figure><?php endforeach; ?></div>
    </section>
    <?php endif; ?>
  </div>
  <aside class="co-side">
    <div class="card overview"><h3>Company info</h3><dl class="ov-list">
      <?php if ($c['industry']): ?><div><dt>Industry</dt><dd><?= e($c['industry']) ?></dd></div><?php endif; ?>
      <?php if ($c['company_size']): ?><div><dt>Size</dt><dd><?= e($c['company_size']) ?></dd></div><?php endif; ?>
      <?php if ($c['founded_year']): ?><div><dt>Founded</dt><dd><?= (int) $c['founded_year'] ?></dd></div><?php endif; ?>
      <?php if ($c['address'] || $c['location']): ?><div><dt>Address</dt><dd><?= e(trim(($c['address'] ?? '') . ', ' . ($c['location'] ?? ''), ', ')) ?></dd></div><?php endif; ?>
      <?php if ($c['website']): ?><div><dt>Website</dt><dd><a href="<?= e($c['website']) ?>" target="_blank" rel="nofollow noopener"><?= e(preg_replace('#^https?://(www\.)?#', '', rtrim($c['website'], '/'))) ?></a></dd></div><?php endif; ?>
    </dl>
    <div class="social-row"><?php foreach (['facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'instagram' => 'Instagram'] as $k => $n) if ($c[$k]): ?><a href="<?= e($c[$k]) ?>" target="_blank" rel="nofollow noopener"><?= $n ?></a><?php endif; ?></div>
    </div>
  </aside>
</div>
<?php layout_end();
