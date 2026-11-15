<?php
$k = row("SELECT
  (SELECT COUNT(*) FROM users WHERE role='seeker') seekers, (SELECT COUNT(*) FROM users WHERE role='employer') employers,
  (SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()) today_users, (SELECT COUNT(*) FROM companies) companies,
  (SELECT COUNT(*) FROM jobs WHERE status='active' AND (deadline IS NULL OR deadline>=CURDATE())) live_jobs, (SELECT COUNT(*) FROM jobs WHERE status='pending') pending_jobs,
  (SELECT COUNT(*) FROM applications) apps, (SELECT COUNT(*) FROM applications WHERE created_at >= CURDATE()) today_apps,
  (SELECT COUNT(*) FROM applications WHERE status='hired') hires, (SELECT COUNT(*) FROM email_queue WHERE status='pending') mail_pending,
  (SELECT COUNT(*) FROM email_queue WHERE status='failed') mail_failed, (SELECT COUNT(*) FROM ai_logs WHERE created_at >= CURDATE()) ai_today,
  (SELECT COUNT(*) FROM job_views WHERE created_at >= CURDATE()) views_today");
$series = function (string $table, string $where = '1') {
    $d = array_column(rows("SELECT DATE(created_at) d, COUNT(*) n FROM $table WHERE $where AND created_at >= CURDATE() - INTERVAL 29 DAY GROUP BY d"), 'n', 'd');
    $out = []; for ($i = 29; $i >= 0; $i--) { $day = date('Y-m-d', strtotime("-$i day")); $out[$day] = (int) ($d[$day] ?? 0); } return $out;
};
$su = $series('users'); $ap = $series('applications'); $jv = $series('job_views');
$pending = rows("SELECT j.id, j.title, j.created_at, c.name company FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.status='pending' ORDER BY j.id LIMIT 6");
$top = rows("SELECT keyword, COUNT(*) n, ROUND(AVG(results)) r FROM search_logs WHERE keyword<>'' AND created_at > NOW() - INTERVAL 30 DAY GROUP BY keyword ORDER BY n DESC LIMIT 8");
$activity = rows("SELECT a.*, u.name FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 12");
$chart = function (array $s, string $label) { $max = max(1, ...array_values($s)); ob_start(); ?>
  <div class="card"><div class="label-row"><h2><?= $label ?></h2><b><?= array_sum($s) ?></b></div><div class="bars bars-30"><?php foreach ($s as $d => $n): ?><div class="bar" style="--h:<?= round($n / $max * 100) ?>%" title="<?= date('d M', strtotime($d)) ?>: <?= $n ?>"><small><?= date('j', strtotime($d)) % 5 === 0 ? date('j', strtotime($d)) : '' ?></small></div><?php endforeach; ?></div></div>
<?php return ob_get_clean(); };
admin_start('Dashboard');
?>
<?php if (!setting('smtp_host')): ?><div class="alert alert-warn">Email is using PHP mail(). For reliable delivery set SMTP in <a href="<?= url('admin/settings?tab=email') ?>">Settings › Email</a>.</div><?php endif; ?>
<?php if (!setting('cron_key') || !val("SELECT 1 FROM settings WHERE `key`='cron_last_run' AND `value` > ?", [date('Y-m-d H:i:s', time() - 7200)])): ?><div class="alert alert-info">Cron hasn’t run in the last 2 hours. Add the cron job shown in <a href="<?= url('admin/settings?tab=cron') ?>">Settings › Cron</a> so alerts, expiry and emails run automatically.</div><?php endif; ?>
<div class="kpis kpis-adm">
  <a class="kpi" href="<?= url('admin/users?role=seeker') ?>"><?= icon('user') ?><b><?= number_format($k['seekers']) ?></b><span>Job seekers</span></a>
  <a class="kpi" href="<?= url('admin/companies') ?>"><?= icon('building') ?><b><?= number_format($k['companies']) ?></b><span>Companies · <?= $k['employers'] ?> employers</span></a>
  <a class="kpi" href="<?= url('admin/jobs?status=active') ?>"><?= icon('briefcase') ?><b><?= number_format($k['live_jobs']) ?></b><span>Live jobs</span></a>
  <a class="kpi kpi-hot" href="<?= url('admin/jobs?status=pending') ?>"><?= icon('clock') ?><b><?= $k['pending_jobs'] ?></b><span>Pending approval</span></a>
  <a class="kpi" href="<?= url('admin/applications') ?>"><?= icon('file') ?><b><?= number_format($k['apps']) ?></b><span>Applications · <?= $k['today_apps'] ?> today</span></a>
  <div class="kpi"><?= icon('star') ?><b><?= $k['hires'] ?></b><span>Hires</span></div>
  <a class="kpi" href="<?= url('admin/emails?tab=queue') ?>"><?= icon('mail') ?><b><?= $k['mail_pending'] ?></b><span>Emails queued<?= $k['mail_failed'] ? ' · ' . $k['mail_failed'] . ' failed' : '' ?></span></a>
  <div class="kpi"><?= icon('sparkles') ?><b><?= $k['ai_today'] ?></b><span>AI requests today</span></div>
</div>
<div class="grid-3"><?= $chart($su, 'Sign-ups (30d)') ?><?= $chart($ap, 'Applications (30d)') ?><?= $chart($jv, 'Job views (30d)') ?></div>
<div class="grid-2">
  <div class="card"><div class="label-row"><h2>Waiting for approval</h2><a href="<?= url('admin/jobs?status=pending') ?>">All</a></div>
    <?php if ($pending): foreach ($pending as $p): ?><div class="list-row"><div class="grow"><a href="<?= url('admin/jobs?q=' . $p['id']) ?>"><b><?= e($p['title']) ?></b></a><small><?= e($p['company']) ?> · <?= e(time_ago($p['created_at'])) ?></small></div><?= adm_action_form('approve', (int) $p['id'], 'Approve', 'btn btn-ok btn-xs') ?></div><?php endforeach; else: ?><p class="muted">Nothing pending. 🎉</p><?php endif; ?></div>
  <div class="card"><div class="label-row"><h2>Top searches (30d)</h2><a href="<?= url('admin/logs?tab=search') ?>">Details</a></div>
    <?php if ($top): ?><table class="table table-sm"><thead><tr><th>Keyword</th><th>Searches</th><th>Avg results</th></tr></thead><tbody><?php foreach ($top as $t): ?><tr><td><?= e($t['keyword']) ?></td><td><?= $t['n'] ?></td><td class="<?= $t['r'] == 0 ? 'err' : '' ?>"><?= $t['r'] ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><p class="muted">No searches yet.</p><?php endif; ?></div>
</div>
<div class="card"><div class="label-row"><h2>Live activity</h2><a href="<?= url('admin/logs') ?>">All logs</a></div>
  <div class="table-wrap"><table class="table table-sm"><tbody><?php foreach ($activity as $a): ?><tr><td class="muted nowrap"><?= e(time_ago($a['created_at'])) ?></td><td><?= $a['user_id'] ? '<a href="' . url('admin/user/' . $a['user_id']) . '">' . e($a['name'] ?? '#' . $a['user_id']) . '</a>' : '<span class="muted">Guest</span>' ?></td><td><code><?= e($a['action']) ?></code></td><td><?= e(excerpt($a['description'], 70)) ?></td><td class="muted"><?= e($a['ip']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php admin_end();
