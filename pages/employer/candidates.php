<?php
require __DIR__ . '/_guard.php';
$f = ['q' => mb_substr(trim($_GET['q'] ?? ''), 0, 100), 'location' => (int) ($_GET['location'] ?? 0), 'exp' => $_GET['exp'] ?? '', 'category' => (int) ($_GET['category'] ?? 0)];
$where = ["u.status='active'", "u.role='seeker'", 'sp.visible_to_employers=1', 'sp.open_to_work=1']; $p = [];
if ($f['q'] !== '') {
    $bool = implode(' ', array_map(fn($t) => '+' . preg_replace('/[+\-<>()~*"@]/', '', $t) . '*', array_filter(preg_split('/\s+/', mb_strtolower($f['q'])), fn($t) => mb_strlen($t) >= 2)));
    $where[] = '(MATCH(sp.headline,sp.summary,sp.skills) AGAINST(? IN BOOLEAN MODE) OR u.name LIKE ? OR sp.skills LIKE ?)'; array_push($p, $bool ?: $f['q'], "%{$f['q']}%", "%{$f['q']}%");
}
if ($f['location']) { $where[] = 'sp.location_id=?'; $p[] = $f['location']; }
if ($f['exp'] !== '' && is_numeric($f['exp'])) { $where[] = 'sp.experience_years>=?'; $p[] = (float) $f['exp']; }
if ($f['category']) { $where[] = 'sp.preferred_category_id=?'; $p[] = $f['category']; }
$w = implode(' AND ', $where);
$pg = paginate((int) val("SELECT COUNT(*) FROM users u JOIN seeker_profiles sp ON sp.user_id=u.id WHERE $w", $p), 20);
$list = rows("SELECT u.id, u.name, u.avatar, sp.headline, sp.skills, sp.experience_years, sp.expected_salary, sp.resume_path, l.name location, sp.updated_at FROM users u JOIN seeker_profiles sp ON sp.user_id=u.id LEFT JOIN locations l ON l.id=sp.location_id WHERE $w ORDER BY (sp.resume_path IS NOT NULL) DESC, COALESCE(sp.updated_at,u.created_at) DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
dash_start('Find candidates', 'employer/candidates');
?>
<div class="page-head"><div><h1>Find candidates</h1><p class="muted"><?= number_format($pg['total']) ?> job seekers open to work</p></div></div>
<form class="toolbar" method="get">
  <input name="q" value="<?= e($f['q']) ?>" placeholder="Skill, title or name (e.g. Tally, nurse, PHP)">
  <select name="location"><?= options(locations(), $f['location'] ?: '', 'Any city') ?></select>
  <select name="exp"><?= options(['0' => 'Any', '1' => '1+ yrs', '3' => '3+ yrs', '5' => '5+ yrs', '10' => '10+ yrs'], $f['exp'], 'Experience') ?></select>
  <select name="category"><?= options(categories(), $f['category'] ?: '', 'Any category') ?></select>
  <button class="btn btn-dark btn-sm"><?= icon('search', 'i i-sm') ?>Search</button>
</form>
<?php if ($list): ?><div class="cand-grid">
<?php foreach ($list as $c): ?>
  <a class="card cand-card reveal" href="<?= url('employer/candidates/' . $c['id']) ?>">
    <div class="cand-card-top"><?= avatar_html($c['avatar'], $c['name'], 'av av-md') ?><div><b><?= e($c['name']) ?></b><small><?= e($c['headline'] ?: 'Job seeker') ?></small></div></div>
    <div class="chips"><?php if ($c['location']): ?><span><?= icon('pin', 'i i-xs') ?><?= e($c['location']) ?></span><?php endif; ?><?php if ($c['experience_years'] !== null): ?><span><?= (float) $c['experience_years'] ?> yrs</span><?php endif; ?><?php if ($c['resume_path']): ?><span class="chip-accent">CV</span><?php endif; ?></div>
    <div class="skill-tags skill-tags-sm"><?php foreach (array_slice(skills_arr($c['skills']), 0, 6) as $s): ?><span class="stag"><?= e($s) ?></span><?php endforeach; ?></div>
  </a>
<?php endforeach; ?></div><?= page_links($pg) ?>
<?php else: ?><div class="empty card"><?= icon('users', 'i i-xl') ?><h3>No candidates match</h3><p>Try a broader skill or remove filters.</p></div><?php endif; ?>
<?php dash_end();
