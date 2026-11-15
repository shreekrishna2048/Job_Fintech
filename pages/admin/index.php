<?php
$admin = require_login('admin');
$section = param('section', 'dashboard');
$sections = [
    'dashboard' => ['grid', 'Dashboard'], 'import' => ['zap', 'Bulk import'], 'finder' => ['target', 'Job finder'], 'jobs' => ['briefcase', 'Jobs'], 'companies' => ['building', 'Companies'], 'users' => ['users', 'Users'],
    'applications' => ['file', 'Applications'], 'reports' => ['flag', 'Reports'], 'messages' => ['mail', 'Messages'],
    'broadcast' => ['send', 'Broadcast'], 'emails' => ['mail', 'Email center'], 'taxonomy' => ['layers', 'Categories & cities'],
    'images' => ['image', 'Site images'], 'pages' => ['file', 'Pages'], 'login' => ['lock', 'Login & sign-up'], 'ai' => ['sparkles', 'AI & integrations'], 'logs' => ['log', 'Logs & analytics'], 'settings' => ['settings', 'Settings'],
];
if ($section !== 'user' && !isset($sections[$section])) abort(404);
foreach ($sections as $k => $_) if (!admin_can($k)) unset($sections[$k]);
$need = $section === 'user' ? 'users' : $section;
if (!admin_can($need)) {
    if ($section === 'dashboard' && $sections) redirect('admin/' . array_key_first($sections));
    abort(403, 'Your admin account does not have access to this section.');
}

/** Admin "act as" another user to edit their profile, company, jobs or applicants exactly as they would. */
function admin_impersonate(int $userId, string $to = ''): void {
    $t = row('SELECT * FROM users WHERE id=?', [$userId]);
    if (!$t || !admin_can('impersonate')) { flash('error', 'Not allowed.'); back(); }
    if ($t['role'] === 'admin') { flash('error', 'You can’t act as another admin.'); back(); }
    $adminId = uid();
    log_activity('impersonate_start', 'user', $userId, 'Admin acting as ' . $t['email']);
    session_regenerate_id(true);
    $_SESSION['impersonator'] = $adminId;
    $_SESSION['uid'] = $userId;
    $to = ltrim($to, '/');
    if ($to === '' || str_contains($to, '//') || preg_match('#^(admin|https?:)#', $to)) $to = $t['role'] === 'employer' ? 'employer' : 'seeker';
    redirect($to);
}


function admin_start(string $title): void {
    global $sections, $section;
    $site = setting('site_name', 'KaamKhoj');
    $badges = [
        'jobs' => (int) val("SELECT COUNT(*) FROM jobs WHERE status='pending'"),
        'reports' => (int) val('SELECT COUNT(*) FROM job_reports WHERE resolved=0'),
        'messages' => (int) val('SELECT COUNT(*) FROM contact_messages WHERE is_read=0'),
        'emails' => (int) val("SELECT COUNT(*) FROM email_queue WHERE status='failed'"),
    ];
    $u = current_user();
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><script>document.documentElement.classList.add('js')</script><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> · Admin · <?= e($site) ?></title><meta name="robots" content="noindex"><meta name="csrf-token" content="<?= csrf_token() ?>">
<link rel="icon" href="<?= asset('img/favicon.svg') ?>"><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= asset('css/app.css') ?>"><style>:root{--brand:<?= e(setting('primary_color', '#e11d48')) ?>}</style></head>
<body class="admin-body">
<div class="adm">
  <aside class="adm-side" id="admside">
    <a class="logo logo-light" href="<?= url('admin') ?>"><?= brand_svg() ?><span><?= e($site) ?></span></a><small class="adm-tag">Admin · v<?= APP_VERSION ?></small>
    <nav><?php foreach ($sections as $k => [$ic, $l]): ?><a href="<?= url($k === 'dashboard' ? 'admin' : "admin/$k") ?>" class="<?= $section === $k || ($section === 'user' && $k === 'users') ? 'on' : '' ?>"><?= icon($ic) ?><span><?= $l ?></span><?php if (!empty($badges[$k])): ?><b class="adm-badge"><?= $badges[$k] ?></b><?php endif; ?></a><?php endforeach; ?></nav>
    <div class="adm-side-foot"><a href="<?= url('') ?>" target="_blank"><?= icon('globe') ?><span>View site</span></a><form method="post" action="<?= url('logout') ?>"><?= csrf_field() ?><button><?= icon('logout') ?><span>Sign out</span></button></form></div>
  </aside>
  <div class="adm-main">
    <header class="adm-top"><button class="icon-btn show-md" onclick="document.getElementById('admside').classList.toggle('open')" aria-label="Menu"><?= icon('menu') ?></button><h1><?= e($title) ?></h1><div class="adm-me"><a class="icon-btn" href="<?= url('notifications') ?>"><?= icon('bell') ?></a><?= avatar_html($u['avatar'], $u['name'], 'av av-sm') ?></div></header>
    <div class="toasts" id="toasts"><?php foreach (take_flashes() as [$t, $m]): ?><div class="toast toast-<?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?></div>
    <div class="adm-content">
<?php }
function admin_end(): void { ?>
    </div>
  </div>
</div>
<script>window.KK={base:<?= json_encode(base_url()) ?>,csrf:<?= json_encode(csrf_token()) ?>,logged:true,ai:false};</script>
<script src="<?= asset('js/app.js') ?>" defer></script>
</body></html>
<?php }
function adm_action_form(string $action, int $id, string $label, string $cls = 'btn btn-ghost btn-xs', string $confirm = '', array $extra = []): string {
    $h = '<form method="post" class="inline"' . ($confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '') . '>' . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $id . '">';
    foreach ($extra as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    return $h . '<button class="' . $cls . '">' . $label . '</button></form>';
}

if (is_post()) {
    csrf_check();
    if (input('action') === 'impersonate') admin_impersonate((int) input('user_id'), (string) input('to'));
}
function adm_act_as(int $userId, string $to, string $label = 'Edit as owner', string $cls = 'btn btn-ghost btn-xs'): string {
    if (!admin_can('impersonate')) return '';
    return '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="impersonate"><input type="hidden" name="user_id" value="' . $userId . '"><input type="hidden" name="to" value="' . e($to) . '"><button class="' . $cls . '">' . $label . '</button></form>';
}
require __DIR__ . '/' . $section . '.php';
