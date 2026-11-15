<?php
$u = require_login();
if (is_post()) { csrf_check(); q('UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL', [$u['id']]); redirect('notifications'); }
$pg = paginate((int) val('SELECT COUNT(*) FROM notifications WHERE user_id=?', [$u['id']]), 30);
$list = rows("SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", [$u['id']]);
$icons = ['new_applicant' => 'users', 'applied' => 'send', 'status' => 'layers', 'job_alert' => 'bell', 'welcome' => 'sparkles', 'job_approved' => 'check', 'job_rejected' => 'x', 'new_job' => 'briefcase', 'contact' => 'mail', 'new_user' => 'user', 'job_pending' => 'clock'];
$u['role'] === 'admin' ? layout_start(['title' => 'Notifications', 'noindex' => true]) : dash_start('Notifications', 'notifications');
if ($u['role'] === 'admin') echo '<div class="wrap narrow sec">';
?>
<div class="page-head"><h1>Notifications</h1><?php if ($list): ?><form method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= icon('check', 'i i-sm') ?>Mark all read</button></form><?php endif; ?></div>
<?php if ($list): ?>
<div class="card notif-list">
  <?php foreach ($list as $n): ?>
    <a class="notif <?= $n['read_at'] ? '' : 'unread' ?>" href="<?= $n['link'] ? url('api/notification-open?id=' . $n['id']) : '#' ?>">
      <span class="notif-ic"><?= icon($icons[$n['type']] ?? 'bell', 'i i-sm') ?></span>
      <span class="notif-txt"><b><?= e($n['title']) ?></b><?php if ($n['body']): ?><small><?= e($n['body']) ?></small><?php endif; ?></span>
      <time><?= e(time_ago($n['created_at'])) ?></time>
    </a>
  <?php endforeach; ?>
</div>
<?= page_links($pg) ?>
<?php else: ?><div class="empty card"><?= icon('bell', 'i i-xl') ?><h3>You’re all caught up</h3><p>Updates about your jobs and applications will appear here.</p></div><?php endif; ?>
<?php if ($u['role'] === 'admin') { echo '</div>'; layout_end(); } else dash_end();
