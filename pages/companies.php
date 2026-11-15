<?php
$qtxt = mb_substr(trim($_GET['q'] ?? ''), 0, 80); $ind = (int) ($_GET['industry'] ?? 0); $hiring = !empty($_GET['hiring']);
$where = ["c.status='active'"]; $p = [];
if ($qtxt !== '') { $where[] = '(c.name LIKE ? OR c.tagline LIKE ?)'; $p[] = "%$qtxt%"; $p[] = "%$qtxt%"; }
if ($ind) { $where[] = 'c.industry_id=?'; $p[] = $ind; }
$open = "(SELECT COUNT(*) FROM jobs j WHERE j.company_id=c.id AND j.status='active' AND (j.deadline IS NULL OR j.deadline>=CURDATE()))";
if ($hiring) $where[] = "$open > 0";
$w = implode(' AND ', $where);
$pg = paginate((int) val("SELECT COUNT(*) FROM companies c WHERE $w", $p), 24);
$list = rows("SELECT c.*, i.name industry, i.slug industry_slug, l.name location, $open open_jobs, (SELECT COUNT(*) FROM company_follows f WHERE f.company_id=c.id) followers
  FROM companies c LEFT JOIN industries i ON i.id=c.industry_id LEFT JOIN locations l ON l.id=c.location_id WHERE $w ORDER BY open_jobs DESC, c.verified DESC, c.name LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
layout_start(['title' => 'Companies hiring in ' . setting('country', 'Nepal'), 'description' => 'Explore company profiles, culture, photos and open jobs from top employers in ' . setting('country', 'Nepal') . '.']);
?>
<section class="list-hero">
  <div class="wrap">
    <h1>Companies hiring now</h1>
    <form class="searchbar" method="get">
      <label class="sb-field sb-kw"><?= icon('search') ?><input name="q" value="<?= e($qtxt) ?>" placeholder="Company name"></label>
      <label class="sb-field sb-loc"><?= icon('layers') ?><select name="industry"><?= options(industries(), $ind ?: '', 'All industries') ?></select></label>
      <button class="btn btn-brand">Search</button>
    </form>
    <label class="check check-light"><input type="checkbox" <?= $hiring ? 'checked' : '' ?> onchange="const u=new URL(location);this.checked?u.searchParams.set('hiring',1):u.searchParams.delete('hiring');location=u"> Only companies with open jobs</label>
  </div>
</section>
<div class="wrap sec">
  <?php if ($list): ?>
  <div class="co-grid">
    <?php foreach ($list as $i => $c): ?>
      <a class="co-tile reveal" style="--d:<?= ($i % 8) * 40 ?>ms" href="<?= url('company/' . $c['slug']) ?>">
        <div class="co-tile-cover" style="<?= bg_img(company_cover($c, 700)) ?>"></div>
        <?= avatar_html($c['logo'], $c['name'], 'logo-box logo-md') ?>
        <h3><?= e($c['name']) ?><?php if ($c['verified']): ?><span class="verified"><?= icon('check', 'i i-xs') ?></span><?php endif; ?></h3>
        <p class="muted"><?= e($c['industry'] ?? '') ?><?= $c['location'] ? ' · ' . e($c['location']) : '' ?></p>
        <div class="co-tile-foot"><span class="<?= $c['open_jobs'] ? 'hiring' : 'muted' ?>"><?= (int) $c['open_jobs'] ?> open job<?= $c['open_jobs'] == 1 ? '' : 's' ?></span><span class="muted"><?= (int) $c['followers'] ?> followers</span></div>
      </a>
    <?php endforeach; ?>
  </div>
  <?= page_links($pg) ?>
  <?php else: ?><div class="empty"><?= icon('building', 'i i-xl') ?><h3>No companies found</h3></div><?php endif; ?>
</div>
<?php layout_end();
