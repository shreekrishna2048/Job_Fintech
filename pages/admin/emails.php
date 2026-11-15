<?php
$tab = $_GET['tab'] ?? 'templates';
if (is_post()) {
    switch (input('action')) {
        case 'template':
            update('email_templates', ['subject' => mb_substr(input('subject'), 0, 255), 'body' => input('body'), 'enabled' => !empty($_POST['enabled']) ? 1 : 0], '`key`=?', [input('key')]);
            flash('success', 'Template saved.'); break;
        case 'test':
            $to = input('to') ?: $admin['email'];
            try { send_mail_now($to, 'Test', 'Test email from ' . setting('site_name'), email_layout('<p>✅ Your email settings work. Sent ' . date('d M Y H:i') . '.</p>')); flash('success', "Test email sent to $to."); }
            catch (Throwable $e) { flash('error', 'Failed: ' . $e->getMessage()); }
            break;
        case 'process': $r = process_email_queue(50); flash('success', "Processed: {$r['sent']} sent, {$r['failed']} failed."); break;
        case 'retry': q("UPDATE email_queue SET status='pending', attempts=0 WHERE " . (input('id') ? 'id=' . (int) input('id') : "status='failed'")); flash('success', 'Queued for retry.'); break;
        case 'purge': q("DELETE FROM email_queue WHERE status='sent' AND sent_at < NOW() - INTERVAL 30 DAY"); flash('success', 'Old sent emails purged.'); break;
    }
    log_activity('admin_email_' . input('action'));
    back();
}
$templates = rows('SELECT * FROM email_templates ORDER BY name');
$edit = isset($_GET['edit']) ? row('SELECT * FROM email_templates WHERE `key`=?', [$_GET['edit']]) : null;
$qs = $_GET['qs'] ?? '';
$queue = rows('SELECT * FROM email_queue' . (in_array($qs, ['pending', 'sent', 'failed'], true) ? " WHERE status='$qs'" : '') . ' ORDER BY id DESC LIMIT 100');
$qc = array_column(rows('SELECT status, COUNT(*) n FROM email_queue GROUP BY status'), 'n', 'status');
admin_start('Email center');
?>
<nav class="tabs"><a href="?tab=templates" class="<?= $tab === 'templates' ? 'on' : '' ?>">Templates</a><a href="?tab=queue" class="<?= $tab === 'queue' ? 'on' : '' ?>">Queue & log <span class="count"><?= (int) ($qc['pending'] ?? 0) ?></span></a><a href="?tab=test" class="<?= $tab === 'test' ? 'on' : '' ?>">Test</a></nav>
<?php if ($tab === 'templates'): ?>
  <?php if ($edit): ?>
  <form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="action" value="template"><input type="hidden" name="key" value="<?= e($edit['key']) ?>">
    <div class="label-row"><h2><?= e($edit['name']) ?></h2><label class="switch"><input type="checkbox" name="enabled" value="1" <?= $edit['enabled'] ? 'checked' : '' ?>><span></span>Enabled</label></div>
    <div class="field"><label>Subject</label><input name="subject" value="<?= e($edit['subject']) ?>"></div>
    <div class="field"><label>Body (HTML)</label><textarea name="body" rows="10" class="mono"><?= e($edit['body']) ?></textarea></div>
    <p class="muted small">Placeholders like <code>{name}</code>, <code>{job_title}</code>, <code>{link}</code>, <code>{site_name}</code> are replaced automatically. Use <code>class="btn"</code> on links for a button.</p>
    <div class="btn-row"><button class="btn btn-dark">Save template</button><a class="btn btn-ghost" href="?tab=templates">Back</a></div>
  </form>
  <div class="card"><h3>Preview</h3><iframe class="mail-preview" srcdoc="<?= e(email_layout($edit['body'])) ?>"></iframe></div>
  <?php else: ?>
  <div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Email</th><th>Subject</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($templates as $t): ?><tr><td><b><?= e($t['name']) ?></b><br><code class="small"><?= e($t['key']) ?></code></td><td><?= e($t['subject']) ?></td><td><?= $t['enabled'] ? '<span class="badge b-green">On</span>' : '<span class="badge b-gray">Off</span>' ?></td><td class="right"><a class="btn btn-ghost btn-xs" href="?tab=templates&edit=<?= e($t['key']) ?>">Edit</a></td></tr><?php endforeach; ?></tbody></table></div></div>
  <?php endif; ?>
<?php elseif ($tab === 'queue'): ?>
  <div class="toolbar"><?php foreach (['' => 'All', 'pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Failed'] as $k => $l): ?><a class="btn btn-<?= $qs === $k ? 'dark' : 'ghost' ?> btn-sm" href="?tab=queue&qs=<?= $k ?>"><?= $l ?> <?= $k ? (int) ($qc[$k] ?? 0) : '' ?></a><?php endforeach; ?>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="process"><button class="btn btn-brand btn-sm">Send pending now</button></form>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="retry"><button class="btn btn-ghost btn-sm">Retry all failed</button></form>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="purge"><button class="btn btn-ghost btn-sm">Purge sent &gt;30d</button></form></div>
  <div class="card"><div class="table-wrap"><table class="table table-sm"><thead><tr><th>To</th><th>Subject</th><th>Template</th><th>Status</th><th>Tries</th><th>Created</th><th></th></tr></thead><tbody>
  <?php foreach ($queue as $m): ?><tr><td><?= e($m['to_email']) ?></td><td><?= e($m['subject']) ?><?php if ($m['error']): ?><br><small class="err"><?= e($m['error']) ?></small><?php endif; ?></td><td><code class="small"><?= e($m['template']) ?></code></td><td><?= status_badge($m['status']) ?></td><td><?= $m['attempts'] ?></td><td class="nowrap"><?= date('d M H:i', strtotime($m['created_at'])) ?></td><td><?php if ($m['status'] === 'failed'): ?><?= adm_action_form('retry', (int) $m['id'], 'Retry') ?><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
<?php else: ?>
  <form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="action" value="test"><h2>Send a test email</h2><p class="muted">Uses the current SMTP settings (<?= setting('smtp_host') ? e(setting('smtp_host')) . ':' . e(setting('smtp_port')) : 'PHP mail()' ?>).</p>
    <div class="row2"><input type="email" name="to" value="<?= e($admin['email']) ?>"><button class="btn btn-brand">Send test</button></div></form>
<?php endif; ?>
<?php admin_end();
