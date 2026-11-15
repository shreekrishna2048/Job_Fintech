<?php
function icon(string $n, string $cls = 'i'): string {
    static $p = [
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'pin' => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'bookmark' => '<path d="M6 3h12v18l-6-4-6 4z"/>',
        'building' => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1M10 21v-3h4v3"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0M16 4.5a3.5 3.5 0 0 1 0 7M22 20a7 7 0 0 0-4-6.3"/>',
        'bell' => '<path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4z"/><path d="M10 21h4"/>',
        'logout' => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 17l5-5-5-5M15 12H3"/>',
        'home' => '<path d="M3 11 12 3l9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'file' => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'sparkles' => '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
        'check' => '<path d="m5 12 5 5L20 7"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'filter' => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m14 6 4 4"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'phone' => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5M4 16v4h16v-4"/>',
        'download' => '<path d="M12 4v12M7 11l5 5 5-5M4 20h16"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'heart' => '<path d="M12 20s-8-5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 9c0 6-8 11-8 11z"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'money' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 10v4M18 10v4"/>',
        'zap' => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        'chev' => '<path d="m6 9 6 6 6-6"/>',
        'log' => '<path d="M4 4h16v16H4zM8 9h8M8 13h8M8 17h5"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
        'flag' => '<path d="M5 21V4h11l-2 4 2 4H5"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
    ];
    return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$n] ?? '') . '</svg>';
}

function brand_svg(): string {
    return '<svg class="logo-mark" viewBox="0 0 32 32" aria-hidden="true"><path d="M6 4h9l-3 10 10-10h6L15 17l13 11h-7l-9-8-2 8H4z" fill="currentColor"/></svg>';
}

function layout_start(array $m = []): void {
    $site = setting('site_name', 'KaamKhoj');
    $title = !empty($m['title']) ? $m['title'] . ' | ' . $site : $site . ' - ' . setting('site_tagline', 'Find your next job in Nepal');
    $desc = $m['description'] ?? setting('site_description');
    $canonical = $m['canonical'] ?? (base_url() . strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
    $og = $m['image'] ?? site_image('img_og', 1200);
    $u = current_user();
    $unread = $u ? (int) val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL', [$u['id']]) : 0;
    $GLOBALS['layout_meta'] = $m;
    $color = setting('primary_color', '#e11d48');
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><script>document.documentElement.classList.add('js')</script>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e(excerpt($desc, 160)) ?>">
<?php if (!empty($m['keywords'])): ?><meta name="keywords" content="<?= e($m['keywords']) ?>"><?php endif; ?>
<?php if (!empty($m['noindex'])): ?><meta name="robots" content="noindex,nofollow"><?php else: ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
<meta property="og:type" content="<?= e($m['og_type'] ?? 'website') ?>">
<meta property="og:site_name" content="<?= e($site) ?>">
<meta property="og:title" content="<?= e($m['title'] ?? $site) ?>">
<meta property="og:description" content="<?= e(excerpt($desc, 200)) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($og) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#16141d">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<?php if ($v = setting('google_site_verification')): ?><meta name="google-site-verification" content="<?= e($v) ?>"><?php endif; ?>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>:root{--brand:<?= e($color) ?>}</style>
<?php foreach (($m['jsonld'] ?? []) as $ld): ?><script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endforeach; ?>
<?php if ($ga = setting('google_analytics_id')): ?><script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= e($ga) ?>');</script><?php endif; ?>
</head>
<body class="<?= e($m['body_class'] ?? '') ?><?= $u ? ' logged-in' : '' ?>">
<a class="skip" href="#main">Skip to content</a>
<header class="topbar" id="topbar">
  <div class="wrap topbar-in">
    <a class="logo" href="<?= url('') ?>" aria-label="<?= e($site) ?> home"><?= brand_svg() ?><span><?= e($site) ?></span></a>
    <nav class="mainnav" aria-label="Main">
      <a href="<?= url('jobs') ?>" class="<?= str_starts_with(current_path(), '/jobs') || str_starts_with(current_path(), '/job/') ? 'on' : '' ?>">Find jobs</a>
      <a href="<?= url('companies') ?>" class="<?= str_starts_with(current_path(), '/compan') ? 'on' : '' ?>">Companies</a>
      <?php if (!$u || $u['role'] === 'employer'): ?><a href="<?= url($u ? 'employer/jobs/new' : 'employers') ?>">For employers</a><?php endif; ?>
    </nav>
    <div class="top-actions">
      <?php if ($u): ?>
        <a class="icon-btn" href="<?= url('notifications') ?>" aria-label="Notifications"><?= icon('bell') ?><?php if ($unread): ?><b class="dot-count"><?= $unread > 9 ? '9+' : $unread ?></b><?php endif; ?></a>
        <div class="dd" data-dd>
          <button class="me-btn" data-dd-toggle aria-haspopup="true"><?= avatar_html($u['avatar'], $u['name'], 'av av-sm') ?><span class="hide-sm"><?= e(explode(' ', $u['name'])[0]) ?></span><?= icon('chev', 'i i-sm') ?></button>
          <div class="dd-menu">
            <div class="dd-head"><b><?= e($u['name']) ?></b><small><?= e($u['email']) ?></small></div>
            <a href="<?= url(dashboard_path($u)) ?>"><?= icon('grid') ?>Dashboard</a>
            <?php if ($u['role'] === 'seeker'): ?>
              <a href="<?= url('seeker/profile') ?>"><?= icon('user') ?>My profile</a>
              <a href="<?= url('seeker/applications') ?>"><?= icon('file') ?>Applications</a>
              <a href="<?= url('seeker/saved') ?>"><?= icon('bookmark') ?>Saved jobs</a>
            <?php elseif ($u['role'] === 'employer'): ?>
              <a href="<?= url('employer/company') ?>"><?= icon('building') ?>Company profile</a>
              <a href="<?= url('employer/jobs') ?>"><?= icon('briefcase') ?>My jobs</a>
            <?php endif; ?>
            <a href="<?= url('account') ?>"><?= icon('settings') ?>Account settings</a>
            <form method="post" action="<?= url('logout') ?>"><?= csrf_field() ?><button><?= icon('logout') ?>Sign out</button></form>
          </div>
        </div>
      <?php else: ?>
        <a class="btn btn-ghost hide-sm" href="<?= url('login') ?>">Sign in</a>
        <a class="btn btn-dark" href="<?= url('register') ?>">Get started</a>
      <?php endif; ?>
      <button class="icon-btn show-sm" data-drawer-open="mobnav" aria-label="Menu"><?= icon('menu') ?></button>
    </div>
  </div>
</header>
<aside class="drawer" id="mobnav" aria-hidden="true">
  <div class="drawer-panel">
    <div class="drawer-head"><a class="logo" href="<?= url('') ?>"><?= brand_svg() ?><span><?= e($site) ?></span></a><button class="icon-btn" data-drawer-close aria-label="Close"><?= icon('x') ?></button></div>
    <nav class="drawer-nav">
      <a href="<?= url('jobs') ?>"><?= icon('search') ?>Find jobs</a>
      <a href="<?= url('companies') ?>"><?= icon('building') ?>Companies</a>
      <a href="<?= url('employers') ?>"><?= icon('briefcase') ?>For employers</a>
      <?php if ($u): ?><a href="<?= url(dashboard_path($u)) ?>"><?= icon('grid') ?>Dashboard</a><a href="<?= url('account') ?>"><?= icon('settings') ?>Account</a>
      <?php else: ?><a href="<?= url('login') ?>"><?= icon('user') ?>Sign in</a><a class="btn btn-brand btn-block" href="<?= url('register') ?>">Create free account</a><?php endif; ?>
    </nav>
  </div>
</aside>
<?php if (impersonator() && $u): ?><div class="imp-bar"><span><?= icon('eye', 'i i-sm') ?>Admin mode - you are acting as <b><?= e($u['name']) ?></b> (<?= e(ucfirst($u['role'])) ?>). Everything you change is saved to their account and logged.</span><form method="post" action="<?= url('auth/return-admin') ?>"><?= csrf_field() ?><button class="btn btn-light btn-sm">Back to admin</button></form></div><?php endif; ?>
<div class="toasts" id="toasts"><?php foreach (take_flashes() as [$t, $msg]): ?><div class="toast toast-<?= e($t) ?>"><?= e($msg) ?></div><?php endforeach; ?></div>
<main id="main">
<?php
}

function layout_end(): void {
    $site = setting('site_name', 'KaamKhoj'); $u = current_user(); $m = $GLOBALS['layout_meta'] ?? [];
    clear_old();
    ?>
</main>
<?php if (empty($m['no_footer'])): ?>
<footer class="footer">
  <div class="wrap footer-grid">
    <div class="footer-brand">
      <a class="logo logo-light" href="<?= url('') ?>"><?= brand_svg() ?><span><?= e($site) ?></span></a>
      <p><?= e(setting('site_tagline', 'Find your next job in Nepal')) ?>. Every application tracked, every employer with a real profile.</p>
      <div class="socials">
        <?php foreach (['facebook_url' => 'Facebook', 'linkedin_url' => 'LinkedIn', 'instagram_url' => 'Instagram'] as $k => $n) if ($l = setting($k)): ?><a href="<?= e($l) ?>" rel="noopener" target="_blank"><?= $n ?></a><?php endif; ?>
      </div>
    </div>
    <div><h4>Job seekers</h4><a href="<?= url('jobs') ?>">Browse jobs</a><a href="<?= url('jobs?mode=remote') ?>">Remote jobs</a><a href="<?= url('jobs?type=internship') ?>">Internships</a><a href="<?= url('register') ?>">Create profile</a></div>
    <div><h4>Employers</h4><a href="<?= url('employers') ?>">Why hire here</a><a href="<?= url('register?role=employer') ?>">Post a job</a><a href="<?= url('companies') ?>">Company directory</a></div>
    <div><h4>Jobs by city</h4><?php foreach (array_slice(locations(), 0, 6) as $l): ?><a href="<?= url('jobs/in/' . $l['slug']) ?>">Jobs in <?= e($l['name']) ?></a><?php endforeach; ?></div>
    <div><h4>Company</h4><a href="<?= url('page/about') ?>">About</a><a href="<?= url('contact') ?>">Contact</a><a href="<?= url('page/privacy') ?>">Privacy</a><a href="<?= url('page/terms') ?>">Terms</a></div>
  </div>
  <div class="wrap footer-bottom"><span>© <?= date('Y') ?> <?= e($site) ?>. All rights reserved.</span><span>Made in Nepal 🇳🇵</span></div>
</footer>
<?php endif; ?>
<?php if ($u && $u['role'] !== 'admin'): $dp = dashboard_path($u); ?>
<nav class="bottomnav" aria-label="Quick">
  <a href="<?= url('') ?>" class="<?= current_path() === '/' ? 'on' : '' ?>"><?= icon('home') ?><span>Home</span></a>
  <a href="<?= url('jobs') ?>" class="<?= str_starts_with(current_path(), '/job') ? 'on' : '' ?>"><?= icon('search') ?><span>Jobs</span></a>
  <?php if ($u['role'] === 'employer'): ?>
    <a href="<?= url('employer/jobs/new') ?>" class="bn-cta"><?= icon('plus') ?><span>Post</span></a>
    <a href="<?= url('employer/applicants') ?>" class="<?= str_starts_with(current_path(), '/employer/applicants') ? 'on' : '' ?>"><?= icon('users') ?><span>Applicants</span></a>
  <?php else: ?>
    <a href="<?= url('seeker/saved') ?>" class="<?= current_path() === '/seeker/saved' ? 'on' : '' ?>"><?= icon('bookmark') ?><span>Saved</span></a>
    <a href="<?= url('seeker/applications') ?>" class="<?= str_starts_with(current_path(), '/seeker/applications') ? 'on' : '' ?>"><?= icon('file') ?><span>Applied</span></a>
  <?php endif; ?>
  <a href="<?= url($dp) ?>" class="<?= current_path() === '/' . $dp ? 'on' : '' ?>"><?= icon('grid') ?><span>Me</span></a>
</nav>
<?php endif; ?>
<script>window.KK={base:<?= json_encode(base_url()) ?>,csrf:<?= json_encode(csrf_token()) ?>,logged:<?= $u ? 'true' : 'false' ?>,ai:<?= ai_enabled() && $u ? 'true' : 'false' ?>};</script>
<script src="<?= asset('js/app.js') ?>" defer></script>
</body></html>
<?php
}

/** Dashboard shell for seekers & employers (sidebar + content) */
function dash_start(string $title, string $active): void {
    $u = current_user();
    layout_start(['title' => $title, 'noindex' => true, 'body_class' => 'dash-body', 'no_footer' => true]);
    $items = $u['role'] === 'employer' ? [
        'employer' => ['grid', 'Overview'], 'employer/jobs' => ['briefcase', 'My jobs'], 'employer/jobs/new' => ['plus', 'Post a job'],
        'employer/applicants' => ['users', 'Applicants'], 'employer/candidates' => ['search', 'Find candidates'],
        'employer/company' => ['building', 'Company profile'], 'notifications' => ['bell', 'Notifications'], 'account' => ['settings', 'Account'],
    ] : [
        'seeker' => ['grid', 'Overview'], 'seeker/profile' => ['user', 'My profile'], 'seeker/applications' => ['file', 'Applications'],
        'seeker/saved' => ['bookmark', 'Saved jobs'], 'seeker/alerts' => ['bell', 'Job alerts'], 'seeker/following' => ['heart', 'Following'],
        'notifications' => ['mail', 'Notifications'], 'account' => ['settings', 'Account'],
    ];
    if ($u['role'] === 'admin') $items = ['admin' => ['shield', 'Admin panel'], 'account' => ['settings', 'Account']];
    echo '<div class="dash wrap"><aside class="dash-side"><div class="dash-user">' . avatar_html($u['avatar'], $u['name'], 'av av-md') . '<div><b>' . e($u['name']) . '</b><small>' . ($u['role'] === 'employer' ? 'Employer' : 'Job seeker') . '</small></div></div><nav class="dash-nav">';
    foreach ($items as $path => [$ic, $label]) echo '<a href="' . url($path) . '" class="' . ($active === $path ? 'on' : '') . '">' . icon($ic) . '<span>' . $label . '</span></a>';
    echo '</nav></aside><section class="dash-main">';
}
function dash_end(): void { echo '</section></div>'; layout_end(); }

function status_badge(string $s): string {
    $map = ['active' => 'green', 'hired' => 'green', 'offered' => 'green', 'pending' => 'amber', 'interview' => 'blue', 'shortlisted' => 'violet',
        'viewed' => 'blue', 'applied' => 'gray', 'draft' => 'gray', 'closed' => 'gray', 'rejected' => 'red', 'suspended' => 'red', 'withdrawn' => 'gray', 'sent' => 'green', 'failed' => 'red'];
    return '<span class="badge b-' . ($map[$s] ?? 'gray') . '">' . e(APP_STATUSES[$s] ?? label($s)) . '</span>';
}

/** Job card used across listing pages */
function job_card(array $j, array $opt = []): string {
    $saved = $opt['saved'] ?? false; $match = $opt['match'] ?? null;
    $dl = days_left($j['deadline']);
    ob_start(); ?>
<article class="jcard reveal<?= $j['featured'] ? ' is-featured' : '' ?>">
  <a class="jcard-link" href="<?= url('job/' . $j['slug']) ?>" aria-label="<?= e($j['title']) ?> at <?= e($j['company_name']) ?>"></a>
  <div class="jcard-top">
    <?= avatar_html($j['company_logo'], $j['company_name'], 'logo-box') ?>
    <div class="jcard-head">
      <h3><?= e($j['title']) ?></h3>
      <p class="co"><?= e($j['company_name']) ?><?php if ($j['company_verified']): ?><span class="verified" title="Verified employer"><?= icon('check', 'i i-xs') ?></span><?php endif; ?></p>
    </div>
    <button class="save-btn<?= $saved ? ' on' : '' ?>" data-save="<?= (int) $j['id'] ?>" aria-label="Save job" aria-pressed="<?= $saved ? 'true' : 'false' ?>"><?= icon('bookmark') ?></button>
  </div>
  <div class="chips">
    <span><?= icon('pin', 'i i-xs') ?><?= e($j['location_name'] ?? 'Nepal') ?></span>
    <span><?= e(JOB_TYPES[$j['job_type']] ?? $j['job_type']) ?></span>
    <?php if ($j['work_mode'] !== 'onsite'): ?><span class="chip-accent"><?= e(WORK_MODES[$j['work_mode']]) ?></span><?php endif; ?>
    <span><?= icon('money', 'i i-xs') ?><?= e(salary_text($j)) ?></span>
  </div>
  <div class="jcard-foot">
    <span class="muted"><?= e(time_ago($j['published_at'] ?? $j['created_at'])) ?></span>
    <span class="jcard-tags">
      <?php if ($j['featured']): ?><span class="tag tag-feat">Featured</span><?php endif; ?>
      <?php if ($j['urgent']): ?><span class="tag tag-urgent">Urgent</span><?php endif; ?>
      <?php if ($dl !== null && $dl <= 3 && $dl >= 0): ?><span class="tag tag-warn"><?= $dl === 0 ? 'Closes today' : "Closes in {$dl}d" ?></span><?php endif; ?>
      <?php if ($match !== null): ?><span class="match m-<?= $match >= 75 ? 'hi' : ($match >= 50 ? 'md' : 'lo') ?>"><?= $match ?>% match</span><?php endif; ?>
    </span>
  </div>
</article>
<?php return ob_get_clean();
}

function ai_button(string $feature, string $target, string $label = 'Write with AI', array $data = []): string {
    if (!ai_enabled()) return '';
    $attrs = '';
    foreach ($data as $k => $v) $attrs .= ' data-' . e($k) . '="' . e($v) . '"';
    return '<button type="button" class="ai-btn" data-ai="' . e($feature) . '" data-target="' . e($target) . '"' . $attrs . '>' . icon('sparkles', 'i i-sm') . e($label) . '</button>';
}

function auth_start(string $title, string $heading, string $sub = ''): void {
    layout_start(['title' => $title, 'noindex' => true, 'body_class' => 'auth-body', 'no_footer' => true]);
    echo '<div class="auth"><aside class="auth-art" aria-hidden="true" style="' . bg_img(site_image('img_auth', 1400), 'linear-gradient(160deg,rgba(18,16,25,.55),rgba(18,16,25,.92) 70%)') . '"><div class="auth-art-in"><a class="logo logo-light" href="' . url('') . '">' . brand_svg() . '<span>' . e(setting('site_name', 'KaamKhoj')) . '</span></a>'
        . '<blockquote>“Applied on Monday, shortlisted on Wednesday, hired the next week. I could see every step.”<cite>- A job seeker in Itahari</cite></blockquote>'
        . '<ul class="auth-points"><li>' . icon('check', 'i i-sm') . 'One profile, one-tap apply</li><li>' . icon('check', 'i i-sm') . 'Live application tracking</li><li>' . icon('check', 'i i-sm') . 'AI help for CVs & job posts</li></ul></div></aside>'
        . '<section class="auth-main"><div class="auth-card"><h1>' . e($heading) . '</h1>' . ($sub ? '<p class="muted">' . $sub . '</p>' : '');
}
function auth_end(): void { echo '</div></section></div>'; layout_end(); }

function oauth_buttons(string $role = '', string $verb = 'Continue', bool $divider = true): string {
    $p = oauth_providers();
    if (!$p) return '';
    $last = $_COOKIE['kk_last'] ?? '';
    $h = '<div class="oauth">';
    $logos = [
        'google' => '<svg viewBox="0 0 24 24" class="i"><path fill="#4285F4" d="M22.5 12.3c0-.8-.1-1.5-.2-2.3H12v4.3h5.9a5 5 0 0 1-2.2 3.3v2.7h3.6c2-1.9 3.2-4.7 3.2-8z"/><path fill="#34A853" d="M12 23c3 0 5.5-1 7.3-2.7l-3.6-2.7c-1 .7-2.3 1.1-3.7 1.1-2.9 0-5.3-1.9-6.2-4.5H2.1v2.8A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.8 14.2a6.6 6.6 0 0 1 0-4.3V7.1H2.1a11 11 0 0 0 0 9.9z"/><path fill="#EA4335" d="M12 5.4c1.6 0 3.1.6 4.2 1.7l3.2-3.2A11 11 0 0 0 2.1 7.1l3.7 2.8C6.7 7.3 9.1 5.4 12 5.4z"/></svg>',
        'microsoft' => '<svg viewBox="0 0 24 24" class="i"><path fill="#F25022" d="M2 2h9.5v9.5H2z"/><path fill="#7FBA00" d="M12.5 2H22v9.5h-9.5z"/><path fill="#00A4EF" d="M2 12.5h9.5V22H2z"/><path fill="#FFB900" d="M12.5 12.5H22V22h-9.5z"/></svg>',
    ];
    $sub = ['google' => 'Gmail or Google Workspace', 'microsoft' => 'Outlook, Hotmail, Office 365'];
    // last-used provider first
    uksort($p, fn($a, $b) => ($b === $last) <=> ($a === $last));
    foreach ($p as $k => $n) $h .= '<a class="btn btn-oauth' . ($k === $last ? ' is-last' : '') . '" data-oauth href="' . url("auth/$k" . ($role ? "?role=$role" : '')) . '">' . $logos[$k] . '<span class="oa-txt"><b>' . e($verb) . ' with ' . $n . '</b><small>' . $sub[$k] . '</small></span>' . ($k === $last ? '<em class="last-pill">Last used</em>' : '') . '</a>';
    return $h . '</div>' . ($divider ? '<div class="or"><span>or with email</span></div>' : '');
}
