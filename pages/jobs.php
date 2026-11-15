<?php
$f = [
    'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100),
    'location' => param('location', $_GET['location'] ?? ''),
    'category' => param('category', $_GET['category'] ?? ''),
    'type' => $_GET['type'] ?? '', 'mode' => $_GET['mode'] ?? '', 'level' => $_GET['level'] ?? '',
    'exp' => $_GET['exp'] ?? '', 'salary' => (int) ($_GET['salary'] ?? 0), 'posted' => (int) ($_GET['posted'] ?? 0),
    'featured' => !empty($_GET['featured']), 'company' => $_GET['company'] ?? '', 'sort' => $_GET['sort'] ?? '',
];
$where = [JOB_LIVE]; $p = [];
$loc = $f['location'] ? row('SELECT * FROM locations WHERE slug=?', [$f['location']]) : null;
$cat = $f['category'] ? row('SELECT * FROM categories WHERE slug=?', [$f['category']]) : null;
if ((param('location') && !$loc) || (param('category') && !$cat)) abort(404);
if ($loc) { $where[] = 'j.location_id=?'; $p[] = $loc['id']; }
if ($cat) { $where[] = 'j.category_id=?'; $p[] = $cat['id']; }
if (isset(JOB_TYPES[$f['type']])) { $where[] = 'j.job_type=?'; $p[] = $f['type']; }
if (isset(WORK_MODES[$f['mode']])) { $where[] = 'j.work_mode=?'; $p[] = $f['mode']; }
if (isset(JOB_LEVELS[$f['level']])) { $where[] = 'j.job_level=?'; $p[] = $f['level']; }
if ($f['exp'] !== '' && is_numeric($f['exp'])) { $where[] = 'j.experience_min<=?'; $p[] = (int) $f['exp']; }
if ($f['salary'] > 0) { $where[] = '(j.salary_max>=? OR j.salary_min>=?) AND j.hide_salary=0'; $p[] = $f['salary']; $p[] = $f['salary']; }
if (in_array($f['posted'], [1, 3, 7, 30], true)) { $where[] = 'j.published_at >= NOW() - INTERVAL ' . $f['posted'] . ' DAY'; }
if ($f['featured']) $where[] = 'j.featured=1';
$ctry = null;
foreach (COUNTRIES as $cn => $ci) if (strtolower($_GET['country'] ?? '') === strtolower($ci['code']) && in_array($cn, countries_enabled(), true)) $ctry = $cn;
if ($ctry && !$loc) { $where[] = 'l.country=?'; $p[] = $ctry; }
if ($f['company']) { $where[] = 'c.slug=?'; $p[] = $f['company']; }

$relevance = '0'; $rp = [];
if ($f['q'] !== '') {
    $tokens = array_filter(preg_split('/[^\p{L}\p{N}+#.]+/u', mb_strtolower($f['q'])), fn($t) => mb_strlen($t) >= 2);
    $bool = implode(' ', array_map(fn($t) => '+' . preg_replace('/[+\-<>()~*"@]/', '', $t) . '*', $tokens));
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['q']) . '%';
    if ($bool !== '') {
        $where[] = '(MATCH(j.title,j.skills,j.description) AGAINST(? IN BOOLEAN MODE) OR j.title LIKE ? OR c.name LIKE ? OR j.skills LIKE ?)';
        array_push($p, $bool, $like, $like, $like);
        $relevance = '(MATCH(j.title,j.skills,j.description) AGAINST(? IN BOOLEAN MODE) + (j.title LIKE ?)*5)'; $rp = [$bool, $like];
    } else { $where[] = '(j.title LIKE ? OR c.name LIKE ?)'; array_push($p, $like, $like); }
}
$w = implode(' AND ', $where);
$total = (int) val("SELECT COUNT(*) FROM jobs j JOIN companies c ON c.id=j.company_id LEFT JOIN locations l ON l.id=j.location_id WHERE $w", $p);
$pg = paginate($total, 15);
$order = match ($f['sort']) {
    'salary' => 'j.hide_salary ASC, COALESCE(j.salary_max,j.salary_min,0) DESC',
    'deadline' => 'j.deadline IS NULL, j.deadline ASC',
    'new' => 'j.published_at DESC',
    default => ($f['q'] !== '' && $rp ? "rel DESC, " : '') . 'j.featured DESC, j.published_at DESC',
};
$sel = str_replace('SELECT j.*', 'SELECT j.*, ' . $relevance . ' rel', JOB_SELECT);
$jobs = rows("$sel WHERE $w ORDER BY $order LIMIT {$pg['per']} OFFSET {$pg['offset']}", array_merge($rp, $p));

$u = current_user(); $savedIds = []; $profile = null;
if ($u && $u['role'] === 'seeker') { $savedIds = array_column(rows('SELECT job_id FROM saved_jobs WHERE user_id=?', [$u['id']]), 'job_id'); $profile = seeker_profile((int) $u['id']); }

// Track searches (first page, real users) for analytics + "popular searches"
if ($pg['page'] === 1 && !is_bot() && ($f['q'] !== '' || $loc || $cat))
    insert('search_logs', ['user_id' => uid(), 'keyword' => mb_strtolower($f['q']), 'location_id' => $loc['id'] ?? null, 'category_id' => $cat['id'] ?? null, 'results' => $total, 'ip' => client_ip()]);

// SEO title
$what = $f['q'] !== '' ? ucwords($f['q']) . ' jobs' : ($cat ? $cat['name'] . ' jobs' : (isset(JOB_TYPES[$f['type']]) ? JOB_TYPES[$f['type']] . ' jobs' : (isset(WORK_MODES[$f['mode']]) ? WORK_MODES[$f['mode']] . ' jobs' : 'Jobs')));
$where_txt = $loc ? ' in ' . $loc['name'] : ' in ' . setting('country', 'Nepal');
$h1 = $what . $where_txt;
$canonical = $loc && !$cat && $f['q'] === '' ? url('jobs/in/' . $loc['slug']) : ($cat && !$loc && $f['q'] === '' ? url('jobs/category/' . $cat['slug']) : null);
$activeCount = count(array_filter([$f['type'], $f['mode'], $f['level'], $f['exp'], $f['salary'], $f['posted'], $f['featured'], (!param('location') ? $f['location'] : ''), (!param('category') ? $f['category'] : '')]));
$formAction = url(ltrim(current_path(), '/'));

layout_start([
    'title' => $h1 . ($pg['page'] > 1 ? ' – Page ' . $pg['page'] : '') . ' (' . date('M Y') . ')',
    'description' => "Browse $total latest " . mb_strtolower($what) . "$where_txt. Apply online, track applications and get alerts for new vacancies.",
    'canonical' => $canonical, 'noindex' => $total === 0 || count($_GET) > 3,
    'jsonld' => [['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_values(array_filter([
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Jobs', 'item' => url('jobs')],
        $loc ? ['@type' => 'ListItem', 'position' => 3, 'name' => 'Jobs in ' . $loc['name'], 'item' => url('jobs/in/' . $loc['slug'])] : null,
        $cat ? ['@type' => 'ListItem', 'position' => 3, 'name' => $cat['name'], 'item' => url('jobs/category/' . $cat['slug'])] : null,
    ]))]],
]);
?>
<section class="list-hero<?= $cat || $loc ? ' list-hero-photo' : '' ?>"<?= $cat || $loc ? ' style="' . bg_img($cat ? cat_image($cat, 1600) : loc_image($loc, 1600), 'linear-gradient(90deg,rgba(250,248,245,.97) 40%,rgba(250,248,245,.75))') . '"' : '' ?>>
  <div class="wrap">
    <nav class="crumbs"><a href="<?= url('') ?>">Home</a><span>/</span><a href="<?= url('jobs') ?>">Jobs</a><?php if ($loc): ?><span>/</span><span><?= e($loc['name']) ?></span><?php endif; ?><?php if ($cat): ?><span>/</span><span><?= e($cat['name']) ?></span><?php endif; ?></nav>
    <h1><?= e($h1) ?></h1>
    <form class="searchbar" action="<?= url('jobs') ?>" method="get" role="search">
      <label class="sb-field sb-kw"><?= icon('search') ?><input name="q" value="<?= e($f['q']) ?>" placeholder="Job title, skill or company" data-suggest autocomplete="off"><div class="suggest" hidden></div></label>
      <label class="sb-field sb-loc"><?= icon('pin') ?><select name="location"><?= options(array_map(fn($l) => ['id' => $l['slug'], 'name' => $l['name']], locations()), $f['location'], 'Anywhere') ?></select></label>
      <?php if ($cat): ?><input type="hidden" name="category" value="<?= e($cat['slug']) ?>"><?php endif; ?>
      <button class="btn btn-brand">Search</button>
    </form>
  </div>
</section>

<div class="wrap list-layout">
  <aside class="filters" id="filters" aria-label="Filters">
    <form method="get" action="<?= url('jobs') ?>" data-autosubmit>
      <div class="filters-head"><b>Filters</b><a href="<?= url('jobs') ?>" class="muted">Clear all</a><button type="button" class="icon-btn show-md" data-drawer-close aria-label="Close"><?= icon('x') ?></button></div>
      <input type="hidden" name="q" value="<?= e($f['q']) ?>">
      <div class="fgroup"><label>Location</label><select name="location"><?= options(array_map(fn($l) => ['id' => $l['slug'], 'name' => $l['name']], locations()), $f['location'], 'Anywhere') ?></select></div>
      <div class="fgroup"><label>Category</label><select name="category"><?= options(array_map(fn($c) => ['id' => $c['slug'], 'name' => $c['name']], categories()), $f['category'], 'All categories') ?></select></div>
      <div class="fgroup"><label>Job type</label><div class="pills"><?php foreach (JOB_TYPES as $k => $v): ?><label class="pill"><input type="radio" name="type" value="<?= $k ?>" <?= $f['type'] === $k ? 'checked' : '' ?>><span><?= $v ?></span></label><?php endforeach; ?><label class="pill"><input type="radio" name="type" value="" <?= $f['type'] === '' ? 'checked' : '' ?>><span>Any</span></label></div></div>
      <div class="fgroup"><label>Work mode</label><div class="pills"><?php foreach (WORK_MODES as $k => $v): ?><label class="pill"><input type="radio" name="mode" value="<?= $k ?>" <?= $f['mode'] === $k ? 'checked' : '' ?>><span><?= $v ?></span></label><?php endforeach; ?><label class="pill"><input type="radio" name="mode" value="" <?= $f['mode'] === '' ? 'checked' : '' ?>><span>Any</span></label></div></div>
      <div class="fgroup"><label>Level</label><select name="level"><?= options(JOB_LEVELS, $f['level'], 'Any level') ?></select></div>
      <div class="fgroup"><label>My experience</label><select name="exp"><?= options(['0' => 'Fresher', '1' => '1 year', '2' => '2 years', '3' => '3 years', '5' => '5 years', '8' => '8+ years'], $f['exp'], 'Any') ?></select></div>
      <div class="fgroup"><label>Min. salary (<?= e($ctry ? COUNTRIES[$ctry]['currency'] : setting('currency', 'NPR')) ?>/month)</label><select name="salary"><?= options(['20000' => '20K+', '30000' => '30K+', '50000' => '50K+', '75000' => '75K+', '100000' => '1 Lakh+', '150000' => '1.5 Lakh+'], $f['salary'] ?: '', 'Any') ?></select></div>
      <div class="fgroup"><label>Posted</label><select name="posted"><?= options(['1' => 'Last 24 hours', '3' => 'Last 3 days', '7' => 'Last 7 days', '30' => 'Last 30 days'], $f['posted'] ?: '', 'Any time') ?></select></div>
      <label class="check"><input type="checkbox" name="featured" value="1" <?= $f['featured'] ? 'checked' : '' ?>> Featured only</label>
      <input type="hidden" name="sort" value="<?= e($f['sort']) ?>">
      <button class="btn btn-dark btn-block nojs-only">Apply filters</button>
    </form>
  </aside>
  <div class="drawer-backdrop" data-drawer-close></div>

  <section class="results">
    <?php if (count(countries_enabled()) > 1): $qsC = $_GET; unset($qsC['page'], $qsC['location']); ?>
    <nav class="country-pills"><a href="?<?= e(http_build_query(array_diff_key($qsC, ['country' => 1]))) ?>" class="<?= !$ctry ? 'on' : '' ?>">🌏 All countries</a><?php foreach (countries_enabled() as $cn): ?><a href="?<?= e(http_build_query(['country' => strtolower(COUNTRIES[$cn]['code'])] + $qsC)) ?>" class="<?= $ctry === $cn ? 'on' : '' ?>"><?= COUNTRIES[$cn]['flag'] ?> <?= e($cn === 'United Arab Emirates' ? 'UAE' : $cn) ?></a><?php endforeach; ?></nav>
    <?php endif; ?>
    <div class="results-bar">
      <p><b><?= number_format($total) ?></b> job<?= $total === 1 ? '' : 's' ?> found</p>
      <div class="rb-actions">
        <button class="btn btn-ghost btn-sm show-md" data-drawer-open="filters"><?= icon('filter', 'i i-sm') ?>Filters<?= $activeCount ? " ($activeCount)" : '' ?></button>
        <?php if ($u && $u['role'] === 'seeker' && ($f['q'] !== '' || $loc || $cat)): ?>
          <button class="btn btn-ghost btn-sm" data-create-alert='<?= e(json_encode(['keyword' => $f['q'], 'category_id' => $cat['id'] ?? null, 'location_id' => $loc['id'] ?? null, 'job_type' => $f['type']])) ?>'><?= icon('bell', 'i i-sm') ?>Alert me</button>
        <?php endif; ?>
        <select class="sort-sel" onchange="const u=new URL(location);u.searchParams.set('sort',this.value);u.searchParams.delete('page');location=u" aria-label="Sort">
          <?= options(['' => 'Most relevant', 'new' => 'Newest', 'salary' => 'Highest salary', 'deadline' => 'Closing soon'], $f['sort']) ?>
        </select>
      </div>
    </div>
    <?php if ($jobs): ?>
      <div class="job-list"><?php foreach ($jobs as $j) echo job_card($j, ['saved' => in_array($j['id'], $savedIds), 'match' => match_score($profile, $j)]); ?></div>
      <?= page_links($pg) ?>
    <?php else: ?>
      <div class="empty"><?= icon('search', 'i i-xl') ?><h3>No jobs match these filters</h3><p>Try fewer filters or a broader keyword.</p>
        <?php if ($u && $u['role'] === 'seeker'): ?><button class="btn btn-brand" data-create-alert='<?= e(json_encode(['keyword' => $f['q'], 'category_id' => $cat['id'] ?? null, 'location_id' => $loc['id'] ?? null])) ?>'>Notify me when one is posted</button><?php else: ?><a class="btn btn-ghost" href="<?= url('jobs') ?>">See all jobs</a><?php endif; ?></div>
    <?php endif; ?>

    <?php if ($loc || $cat): ?>
    <div class="seo-links">
      <h2><?= $loc ? 'Popular categories in ' . e($loc['name']) : 'Find ' . e($cat['name']) . ' jobs by city' ?></h2>
      <div class="pill-links">
        <?php if ($loc) foreach (array_slice(categories(), 0, 12) as $c): ?><a href="<?= url('jobs?location=' . $loc['slug'] . '&category=' . $c['slug']) ?>"><?= e($c['name']) ?> jobs in <?= e($loc['name']) ?></a><?php endforeach;
        else foreach (array_slice(locations(), 0, 12) as $l): ?><a href="<?= url('jobs?category=' . $cat['slug'] . '&location=' . $l['slug']) ?>"><?= e($cat['name']) ?> jobs in <?= e($l['name']) ?></a><?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>
</div>
<?php layout_end();
