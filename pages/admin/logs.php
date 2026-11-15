<?php
$tab = $_GET['tab'] ?? 'activity';
if (is_post() && input('action') === 'prune') {
    $days = max(30, (int) input('days', 180));
    foreach (['activity_logs', 'login_logs', 'search_logs', 'job_views', 'ai_logs'] as $tb) q("DELETE FROM $tb WHERE created_at < NOW() - INTERVAL $days DAY");
    log_activity('admin_logs_prune', null, null, "$days days");
    flash('success', "Logs older than $days days removed."); back();
}
$qa = trim($_GET['action'] ?? ''); $qip = trim($_GET['ip'] ?? ''); $qu = (int) ($_GET['user'] ?? 0);
admin_start('Logs & analytics');
?>
<nav class="tabs tabs-scroll"><?php foreach (['activity' => 'Activity', 'logins' => 'Sign-ins', 'search' => 'Search analytics', 'views' => 'Job traffic', 'ai' => 'AI usage'] as $k => $l): ?><a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= $l ?></a><?php endforeach; ?></nav>
<?php if ($tab === 'activity'):
    $where = '1'; $p = [];
    if ($qa !== '') { $where .= ' AND a.action LIKE ?'; $p[] = "%$qa%"; }
    if ($qip !== '') { $where .= ' AND a.ip=?'; $p[] = $qip; }
    if ($qu) { $where .= ' AND a.user_id=?'; $p[] = $qu; }
    $pg = paginate((int) val("SELECT COUNT(*) FROM activity_logs a WHERE $where", $p), 50);
    $list = rows("SELECT a.*, u.name FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id WHERE $where ORDER BY a.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
    $actions = array_column(rows('SELECT action, COUNT(*) n FROM activity_logs GROUP BY action ORDER BY n DESC LIMIT 40'), 'action'); ?>
  <form class="toolbar" method="get"><input type="hidden" name="tab" value="activity"><select name="action"><?= options(array_combine($actions, $actions) ?: [], $qa, 'All actions') ?></select><input name="ip" value="<?= e($qip) ?>" placeholder="IP address"><input name="user" value="<?= $qu ?: '' ?>" placeholder="User ID" style="max-width:110px"><button class="btn btn-dark btn-sm">Filter</button></form>
  <div class="card"><div class="table-wrap"><table class="table table-sm"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP / device</th></tr></thead><tbody>
  <?php foreach ($list as $a): ?><tr><td class="nowrap"><?= date('d M H:i:s', strtotime($a['created_at'])) ?></td><td><?= $a['user_id'] ? '<a href="' . url('admin/user/' . $a['user_id']) . '">' . e($a['name'] ?? '#' . $a['user_id']) . '</a>' : '<span class="muted">Guest</span>' ?></td><td><code><?= e($a['action']) ?></code></td><td class="small"><?= e($a['entity']) ?><?= $a['entity_id'] ? ' #' . $a['entity_id'] : '' ?></td><td class="small"><?= e(excerpt($a['description'], 80)) ?></td><td class="small"><a href="?tab=activity&ip=<?= e($a['ip']) ?>"><?= e($a['ip']) ?></a><br><span class="muted"><?= e(device_name($a['user_agent'])) ?></span></td></tr><?php endforeach; ?>
  </tbody></table></div></div><?= page_links($pg) ?>
<?php elseif ($tab === 'logins'):
    $fail = $_GET['fail'] ?? '';
    $where = $fail ? 'l.success=0' : '1';
    $pg = paginate((int) val("SELECT COUNT(*) FROM login_logs l WHERE $where"), 50);
    $list = rows("SELECT l.*, u.name FROM login_logs l LEFT JOIN users u ON u.id=l.user_id WHERE $where ORDER BY l.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
    $suspicious = rows("SELECT ip, COUNT(*) n, MAX(created_at) last FROM login_logs WHERE success=0 AND created_at > NOW() - INTERVAL 1 DAY GROUP BY ip HAVING n >= 5 ORDER BY n DESC LIMIT 10");
    $methods = rows("SELECT method, COUNT(*) n FROM login_logs WHERE success=1 AND created_at > NOW() - INTERVAL 30 DAY GROUP BY method"); ?>
  <div class="grid-2">
    <div class="card"><h3>Sign-in methods (30d)</h3><?php foreach ($methods as $m): ?><div class="list-row"><span class="grow"><?= e(ucfirst($m['method'])) ?></span><b><?= $m['n'] ?></b></div><?php endforeach; ?></div>
    <div class="card"><h3>Suspicious IPs (24h, 5+ failures)</h3><?php if ($suspicious): foreach ($suspicious as $s): ?><div class="list-row"><span class="grow"><code><?= e($s['ip']) ?></code></span><span class="badge b-red"><?= $s['n'] ?> fails</span><small class="muted"><?= e(time_ago($s['last'])) ?></small></div><?php endforeach; else: ?><p class="muted">None 👍</p><?php endif; ?></div>
  </div>
  <div class="toolbar"><a class="btn btn-<?= $fail ? 'ghost' : 'dark' ?> btn-sm" href="?tab=logins">All</a><a class="btn btn-<?= $fail ? 'dark' : 'ghost' ?> btn-sm" href="?tab=logins&fail=1">Failed only</a></div>
  <div class="card"><div class="table-wrap"><table class="table table-sm"><thead><tr><th>Time</th><th>User / email</th><th>Method</th><th>Result</th><th>Device</th><th>IP</th></tr></thead><tbody>
  <?php foreach ($list as $l): ?><tr><td class="nowrap"><?= date('d M H:i:s', strtotime($l['created_at'])) ?></td><td><?= $l['user_id'] ? '<a href="' . url('admin/user/' . $l['user_id']) . '">' . e($l['name'] ?? $l['email']) . '</a>' : e($l['email'] ?? '-') ?></td><td><?= e($l['method']) ?></td><td><?= $l['success'] ? '<span class="badge b-green">OK</span>' : '<span class="badge b-red">Failed</span>' ?></td><td class="small"><?= e($l['device']) ?></td><td><a href="?tab=activity&ip=<?= e($l['ip']) ?>"><?= e($l['ip']) ?></a></td></tr><?php endforeach; ?>
  </tbody></table></div></div><?= page_links($pg) ?>
<?php elseif ($tab === 'search'):
    $top = rows("SELECT keyword, COUNT(*) n, ROUND(AVG(results)) r FROM search_logs WHERE keyword<>'' AND created_at > NOW() - INTERVAL 30 DAY GROUP BY keyword ORDER BY n DESC LIMIT 30");
    $zero = rows("SELECT keyword, COUNT(*) n FROM search_logs WHERE keyword<>'' AND results=0 AND created_at > NOW() - INTERVAL 30 DAY GROUP BY keyword ORDER BY n DESC LIMIT 30");
    $locs = rows("SELECT l.name, COUNT(*) n FROM search_logs s JOIN locations l ON l.id=s.location_id WHERE s.created_at > NOW() - INTERVAL 30 DAY GROUP BY l.id ORDER BY n DESC LIMIT 10"); ?>
  <div class="grid-3">
    <div class="card"><h3>Top keywords (30d)</h3><table class="table table-sm"><tbody><?php foreach ($top as $t): ?><tr><td><?= e($t['keyword']) ?></td><td class="right"><?= $t['n'] ?></td><td class="right muted"><?= $t['r'] ?> res</td></tr><?php endforeach; ?></tbody></table></div>
    <div class="card"><h3>Searches with 0 results</h3><p class="muted small">Demand you’re not serving - great for employer outreach.</p><table class="table table-sm"><tbody><?php foreach ($zero as $t): ?><tr><td><?= e($t['keyword']) ?></td><td class="right"><?= $t['n'] ?></td></tr><?php endforeach; ?></tbody></table></div>
    <div class="card"><h3>Top searched cities</h3><table class="table table-sm"><tbody><?php foreach ($locs as $t): ?><tr><td><?= e($t['name']) ?></td><td class="right"><?= $t['n'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
<?php elseif ($tab === 'views'):
    $topJobs = rows("SELECT j.id, j.title, j.slug, c.name company, COUNT(v.id) n FROM job_views v JOIN jobs j ON j.id=v.job_id JOIN companies c ON c.id=j.company_id WHERE v.created_at > NOW() - INTERVAL 7 DAY GROUP BY j.id ORDER BY n DESC LIMIT 20");
    $refs = rows("SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(referrer,'https://',''),'http://',''),'/',1),'?',1) host, COUNT(*) n FROM job_views WHERE referrer<>'' AND created_at > NOW() - INTERVAL 30 DAY GROUP BY host ORDER BY n DESC LIMIT 15"); ?>
  <div class="grid-2">
    <div class="card"><h3>Most viewed jobs (7d)</h3><table class="table table-sm"><tbody><?php foreach ($topJobs as $t): ?><tr><td><a href="<?= url('job/' . $t['slug']) ?>" target="_blank"><?= e($t['title']) ?></a><br><small class="muted"><?= e($t['company']) ?></small></td><td class="right"><?= $t['n'] ?></td></tr><?php endforeach; ?></tbody></table></div>
    <div class="card"><h3>Traffic sources (30d)</h3><table class="table table-sm"><tbody><?php foreach ($refs as $t): ?><tr><td><?= e($t['host'] ?: 'direct') ?></td><td class="right"><?= $t['n'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
<?php else:
    $byF = rows("SELECT feature, COUNT(*) n, SUM(success=0) fails, SUM(tokens) tokens FROM ai_logs WHERE created_at > NOW() - INTERVAL 30 DAY GROUP BY feature ORDER BY n DESC");
    $byU = rows("SELECT u.id, u.name, COUNT(*) n FROM ai_logs a JOIN users u ON u.id=a.user_id WHERE a.created_at > NOW() - INTERVAL 30 DAY GROUP BY u.id ORDER BY n DESC LIMIT 15"); ?>
  <div class="grid-2">
    <div class="card"><h3>AI by feature (30d)</h3><table class="table table-sm"><thead><tr><th>Feature</th><th>Calls</th><th>Failed</th><th>Tokens</th></tr></thead><tbody><?php foreach ($byF as $t): ?><tr><td><code><?= e($t['feature']) ?></code></td><td><?= $t['n'] ?></td><td><?= $t['fails'] ?></td><td><?= number_format((int) $t['tokens']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <div class="card"><h3>Top AI users (30d)</h3><table class="table table-sm"><tbody><?php foreach ($byU as $t): ?><tr><td><a href="<?= url('admin/user/' . $t['id']) ?>"><?= e($t['name']) ?></a></td><td class="right"><?= $t['n'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
<?php endif; ?>
<form method="post" class="card form toolbar"><?= csrf_field() ?><input type="hidden" name="action" value="prune"><span class="muted">Housekeeping: delete logs older than</span><select name="days"><?= options(['90' => '90 days', '180' => '180 days', '365' => '1 year'], '180') ?></select><button class="btn btn-ghost btn-sm" onclick="return confirm('Delete old logs?')">Prune</button></form>
<?php admin_end();
