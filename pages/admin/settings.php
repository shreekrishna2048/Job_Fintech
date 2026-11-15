<?php
$tab = $_GET['tab'] ?? 'general';
$groups = [
    'general' => ['General', ['site_name' => 'Site name', 'site_tagline' => 'Tagline', 'contact_email' => 'Contact email (receives contact form)', 'contact_phone' => 'Contact phone', 'contact_address' => 'Address', 'country' => 'Country', 'currency' => 'Currency code', 'primary_color' => 'Brand color (hex)']],
    'seo' => ['SEO', ['site_description' => 'Default meta description', 'site_keywords' => 'Keywords', 'google_site_verification' => 'Google Search Console verification code', 'google_analytics_id' => 'Google Analytics ID (G-XXXX)', 'facebook_url' => 'Facebook URL', 'linkedin_url' => 'LinkedIn URL', 'instagram_url' => 'Instagram URL']],
    'moderation' => ['Jobs & moderation', ['job_auto_approve' => 'Auto-approve new jobs (1 = yes, 0 = admin review)', 'employer_require_verify' => 'Employers must be verified before publishing (1/0)', 'job_default_days' => 'Default job duration (days)']],
    'email' => ['Email / SMTP', ['mail_from_email' => 'From email (e.g. no-reply@yourdomain.com)', 'mail_from_name' => 'From name', 'smtp_host' => 'SMTP host (blank = PHP mail())', 'smtp_port' => 'SMTP port (587 TLS / 465 SSL)', 'smtp_secure' => 'Encryption: tls, ssl or none', 'smtp_user' => 'SMTP username', 'smtp_pass' => 'SMTP password']],
    'cron' => ['Cron', []],
];
if (!isset($groups[$tab])) $tab = 'general';
$secret = ['smtp_pass', 'google_client_secret', 'microsoft_client_secret', 'groq_api_key'];
$long = ['site_description', 'site_keywords'];
if (is_post()) {
    if (input('action') === 'new_cron_key') save_setting('cron_key', bin2hex(random_bytes(16)));
    else foreach ($groups[$tab][1] as $k => $l) {
        if (!array_key_exists($k, $_POST)) continue;
        $v = trim((string) $_POST[$k]);
        if (in_array($k, $secret, true) && $v === '••••••••') continue; // unchanged secret
        save_setting($k, $v);
    }
    log_activity('admin_settings', 'settings', null, $tab);
    flash('success', 'Settings saved.');
    redirect("admin/settings?tab=$tab");
}
if (!setting('cron_key')) save_setting('cron_key', bin2hex(random_bytes(16)));
admin_start('Settings');
?>
<nav class="tabs tabs-scroll"><?php foreach ($groups as $k => [$l]): ?><a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= $l ?></a><?php endforeach; ?><a href="<?= url('admin/ai') ?>">AI & integrations ›</a><a href="<?= url('admin/login') ?>">Google & Microsoft login ›</a><a href="<?= url('admin/images') ?>">Images ›</a></nav>
<?php if ($tab === 'cron'): $key = val("SELECT `value` FROM settings WHERE `key`='cron_key'"); $last = val("SELECT `value` FROM settings WHERE `key`='cron_last_run'"); ?>
<div class="card form">
  <h2>Scheduled tasks</h2>
  <p>Cron sends queued emails, job-alert digests, deadline reminders, closes expired jobs and cleans tokens. Last run: <b><?= $last ? e(time_ago($last)) . ' (' . e($last) . ')' : 'never' ?></b></p>
  <h3>cPanel › Cron Jobs → “Every 5 minutes”</h3>
  <pre class="code">/usr/local/bin/php <?= e(ROOT) ?>/cron.php >/dev/null 2>&1</pre>
  <p class="muted small">If your host uses a different PHP path, run <code>which php</code> in cPanel Terminal. Alternative (web cron):</p>
  <pre class="code">curl -s "<?= e(url('cron?key=' . $key)) ?>" >/dev/null</pre>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="new_cron_key"><button class="btn btn-ghost btn-sm">Regenerate web-cron key</button></form>
</div>
<?php else: ?>
<form method="post" class="card form"><?= csrf_field() ?>
  <h2><?= $groups[$tab][0] ?></h2>
  <?php if ($tab === 'email'): ?>
    <div class="alert alert-info">On cPanel create an email account (e.g. no-reply@yourdomain) and use host <code>mail.yourdomain.com</code>, port 465 + ssl or 587 + tls. After saving, send a test from <a href="<?= url('admin/emails?tab=test') ?>">Email center › Test</a>.</div>
  <?php endif; ?>
  <?php foreach ($groups[$tab][1] as $k => $l): $v = (string) val('SELECT `value` FROM settings WHERE `key`=?', [$k]); ?>
    <div class="field"><label for="s_<?= $k ?>"><?= e($l) ?></label>
    <?php if (in_array($k, $long, true)): ?><textarea id="s_<?= $k ?>" name="<?= $k ?>" rows="3"><?= e($v) ?></textarea>
    <?php elseif ($k === 'primary_color'): ?><div class="row2"><input id="s_<?= $k ?>" name="<?= $k ?>" value="<?= e($v) ?>"><input type="color" value="<?= e($v ?: '#e11d48') ?>" oninput="document.getElementById('s_<?= $k ?>').value=this.value"></div>
    <?php else: ?><input id="s_<?= $k ?>" name="<?= $k ?>" value="<?= in_array($k, $secret, true) && $v !== '' ? '••••••••' : e($v) ?>" <?= in_array($k, $secret, true) ? 'type="password" autocomplete="new-password" onfocus="if(this.value===\'••••••••\')this.value=\'\'" onblur="if(this.value===\'\')this.value=\'••••••••\'"' : '' ?>><?php endif; ?></div>
  <?php endforeach; ?>
  <button class="btn btn-dark">Save settings</button>
</form>
<?php endif; ?>
<?php admin_end();
