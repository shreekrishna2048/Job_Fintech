<?php
if (is_post()) {
    $aud = input('audience'); $subject = mb_substr(input('subject'), 0, 200); $body = mb_substr(input('body'), 0, 10000); $link = input('link');
    $channels = (array) ($_POST['channels'] ?? []);
    if (!$subject || !$body) { flash('error', 'Subject and message are required.'); keep_old(); back(); }
    $where = match ($aud) { 'seekers' => "role='seeker'", 'employers' => "role='employer'", 'open' => "role='seeker' AND id IN (SELECT user_id FROM seeker_profiles WHERE open_to_work=1)", default => "role IN ('seeker','employer')" };
    $users = rows("SELECT id, name, email, email_notifications FROM users WHERE status='active' AND $where");
    $html = email_layout(text_to_html($body) . ($link ? '<p><a class="btn" href="' . e($link) . '">Open</a></p>' : ''));
    $n = 0;
    foreach ($users as $u) {
        if (in_array('notify', $channels, true)) notify((int) $u['id'], 'broadcast', $subject, excerpt($body, 200), $link ? preg_replace('#^' . preg_quote(base_url(), '#') . '/?#', '', $link) : '');
        if (in_array('email', $channels, true) && $u['email_notifications']) { insert('email_queue', ['to_email' => $u['email'], 'to_name' => $u['name'], 'subject' => $subject, 'body_html' => str_replace('Hi {name}', 'Hi ' . e($u['name']), $html), 'template' => 'broadcast']); $n++; }
    }
    log_activity('admin_broadcast', null, null, "$aud: $subject (" . count($users) . ' users)');
    flash('success', 'Broadcast sent to ' . count($users) . " users ($n emails queued - they go out via cron in batches).");
    redirect('admin/broadcast');
}
$recent = rows("SELECT * FROM activity_logs WHERE action='admin_broadcast' ORDER BY id DESC LIMIT 10");
admin_start('Broadcast');
?>
<form method="post" class="card form"><?= csrf_field() ?>
  <p class="muted">Send an announcement to your users by email and/or in-app notification. Emails respect each user’s email preference.</p>
  <div class="row2"><div class="field"><label>Audience</label><select name="audience"><?= options(['all' => 'All users', 'seekers' => 'All job seekers', 'open' => 'Job seekers open to work', 'employers' => 'All employers'], old('audience', 'all')) ?></select></div>
  <div class="field"><label>Channels</label><div class="inline-checks"><label class="check"><input type="checkbox" name="channels[]" value="email" checked> Email</label><label class="check"><input type="checkbox" name="channels[]" value="notify" checked> In-app notification</label></div></div></div>
  <div class="field"><label>Subject</label><input name="subject" required value="<?= e(old('subject')) ?>"></div>
  <div class="field"><label>Message</label><textarea name="body" rows="8" required placeholder="Plain text. Lines starting with - become bullets."><?= e(old('body')) ?></textarea></div>
  <div class="field"><label>Button link (optional)</label><input name="link" type="url" placeholder="<?= e(url('jobs')) ?>" value="<?= e(old('link')) ?>"></div>
  <button class="btn btn-brand" onclick="return confirm('Send to the selected audience?')"><?= icon('send', 'i i-sm') ?>Send broadcast</button>
</form>
<?php if ($recent): ?><div class="card"><h3>Recent broadcasts</h3><?php foreach ($recent as $r): ?><div class="list-row"><span class="grow"><?= e($r['description']) ?></span><small class="muted"><?= e(time_ago($r['created_at'])) ?></small></div><?php endforeach; ?></div><?php endif; ?>
<?php admin_end();
