<?php
$secret = ['google_client_secret', 'microsoft_client_secret'];
$fields = ['google_client_id', 'google_client_secret', 'microsoft_client_id', 'microsoft_client_secret', 'microsoft_tenant'];
$toggles = ['google_enabled', 'microsoft_enabled', 'allow_seeker_signup', 'allow_employer_signup', 'password_login', 'require_email_verify'];
if (is_post()) {
    $grp = input('group');
    if ($grp === 'google' || $grp === 'microsoft') {
        foreach ($fields as $k) {
            if (!str_starts_with($k, $grp) || !array_key_exists($k, $_POST)) continue;
            $v = trim((string) $_POST[$k]);
            if (in_array($k, $secret, true) && $v === '••••••••') continue;
            if ($k === 'microsoft_tenant' && !preg_match('/^[\w.-]{1,80}$/', $v)) $v = 'common';
            save_setting($k, $v);
        }
        save_setting($grp . '_enabled', !empty($_POST[$grp . '_enabled']) ? '1' : '0');
        if (!empty($_POST['clear'])) { foreach ($fields as $k) if (str_starts_with($k, $grp)) save_setting($k, $k === 'microsoft_tenant' ? 'common' : ''); save_setting($grp . '_last_test', ''); }
        flash('success', ucfirst($grp) . ' sign-in settings saved.' . (!empty($_POST['then_test']) ? '' : ' Click “Test sign-in” to confirm it works.'));
        log_activity('admin_login_settings', 'settings', null, $grp);
        if (!empty($_POST['then_test'])) redirect('auth/' . $grp . '?test=1');
    } elseif ($grp === 'general') {
        foreach (['allow_seeker_signup', 'allow_employer_signup', 'password_login', 'require_email_verify'] as $k) save_setting($k, !empty($_POST[$k]) ? '1' : '0');
        flash('success', 'Sign-up rules saved.');
        log_activity('admin_login_settings', 'settings', null, 'general');
    }
    redirect('admin/login');
}
$on = fn($k) => setting($k, '1') === '1';
$status = function (string $p) use ($on) {
    if (!oauth_configured($p)) return ['gray', 'Not set up'];
    if (!$on($p . '_enabled')) return ['amber', 'Turned off'];
    $t = explode('|', (string) val("SELECT `value` FROM settings WHERE `key`=?", [$p . '_last_test']) ?: '');
    if (($t[1] ?? '') === 'ok') return ['green', 'Live ✓ tested ' . $t[0]];
    if (($t[1] ?? '') === 'fail') return ['red', 'Last test failed'];
    return ['blue', 'Live - not tested yet'];
};
$raw = fn($k) => (string) val("SELECT `value` FROM settings WHERE `key`=?", [$k]);
$providers = [
    'google' => ['Google', 'https://console.cloud.google.com/apis/credentials', [
        'Open Google Cloud Console › APIs & Services › Credentials (link below).',
        'If asked, set up the OAuth consent screen: External, app name = your site name, support email = yours. Publish it (“In production”).',
        'Create credentials › OAuth client ID › Web application.',
        'Authorised JavaScript origin = your site address. Authorised redirect URI = the Redirect URI shown here (copy button).',
        'Paste the Client ID and Client secret here, save, then click “Test sign-in”.',
    ]],
    'microsoft' => ['Microsoft', 'https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade', [
        'Open Microsoft Entra admin center › App registrations › New registration (link below).',
        'Supported account types: “Any organizational directory and personal Microsoft accounts” (tenant = common), so Outlook/Hotmail and work accounts can sign in.',
        'Redirect URI: platform “Web” = the Redirect URI shown here (copy button).',
        'Copy “Application (client) ID”. Then Certificates & secrets › New client secret › copy the secret VALUE (not the ID). Note the expiry date.',
        'Paste both here, save, then click “Test sign-in”.',
    ]],
];
admin_start('Login & sign-up');
?>
<p class="muted">Turn on “Continue with Google” and “Continue with Microsoft” and change their keys at any time. Changes apply to the login and sign-up pages immediately.</p>
<div class="grid-2">
<?php foreach ($providers as $p => [$name, $console, $steps]): [$col, $label] = $status($p); $last = explode('|', $raw($p . '_last_test')); ?>
  <form method="post" class="card form login-prov"><?= csrf_field() ?><input type="hidden" name="group" value="<?= $p ?>">
    <div class="label-row"><h2><?= $name ?> sign-in</h2><span class="badge b-<?= $col ?>"><?= e($label) ?></span></div>
    <label class="switch"><input type="checkbox" name="<?= $p ?>_enabled" value="1" <?= $on($p . '_enabled') ? 'checked' : '' ?>><span></span>Show “Continue with <?= $name ?>” on login & sign-up</label>
    <div class="field"><label>Redirect URI <span class="muted">(paste into <?= $name ?>)</span></label><div class="copy-row"><input readonly value="<?= e(oauth_redirect_uri($p)) ?>" onclick="this.select()"><button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e(oauth_redirect_uri($p)) ?>">Copy</button></div></div>
    <?php if ($p === 'google'): ?><div class="field"><label>Authorised JavaScript origin</label><div class="copy-row"><input readonly value="<?= e(preg_replace('#(https?://[^/]+).*#', '$1', base_url())) ?>"><button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e(preg_replace('#(https?://[^/]+).*#', '$1', base_url())) ?>">Copy</button></div></div><?php endif; ?>
    <div class="field"><label><?= $p === 'google' ? 'Client ID' : 'Application (client) ID' ?></label><input name="<?= $p ?>_client_id" value="<?= e($raw($p . '_client_id')) ?>" autocomplete="off" spellcheck="false"></div>
    <div class="field"><label><?= $p === 'google' ? 'Client secret' : 'Client secret (Value)' ?></label><input type="password" name="<?= $p ?>_client_secret" value="<?= $raw($p . '_client_secret') !== '' ? '••••••••' : '' ?>" autocomplete="new-password" onfocus="if(this.value==='••••••••')this.value=''" onblur="if(this.value===''&&this.dataset.had)this.value='••••••••'" data-had="<?= $raw($p . '_client_secret') !== '' ? '1' : '' ?>"></div>
    <?php if ($p === 'microsoft'): ?><div class="field"><label>Who can sign in (tenant)</label><select name="microsoft_tenant"><?php $tn = $raw('microsoft_tenant') ?: 'common'; ?><?= options(['common' => 'Everyone - work/school + personal accounts (recommended)', 'organizations' => 'Work/school accounts only', 'consumers' => 'Personal accounts only (Outlook, Hotmail)'] + (in_array($tn, ['common', 'organizations', 'consumers'], true) ? [] : [$tn => "Only tenant $tn"]), $tn) ?></select></div><?php endif; ?>
    <?php if (($last[1] ?? '') !== ''): ?><p class="small <?= $last[1] === 'ok' ? 'ok-txt' : 'err' ?>">Last test <?= e($last[0]) ?>: <?= $last[1] === 'ok' ? 'worked - returned ' . e($last[2] ?? '') : e($last[2] ?? 'failed') ?></p><?php endif; ?>
    <div class="btn-row"><button class="btn btn-dark btn-sm">Save</button><button name="then_test" value="1" class="btn btn-brand btn-sm"><?= icon('zap', 'i i-sm') ?>Save & test sign-in</button>
      <?php if (oauth_configured($p)): ?><button name="clear" value="1" class="btn btn-danger-ghost btn-sm" onclick="return confirm('Remove <?= $name ?> keys?')">Remove keys</button><?php endif; ?></div>
    <details class="add-block"><summary><?= icon('settings', 'i i-sm') ?>How to get these keys (5 steps)</summary><ol class="small steps-sm"><?php foreach ($steps as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ol><a class="btn btn-ghost btn-sm" href="<?= e($console) ?>" target="_blank" rel="noopener">Open <?= $name ?> console ↗</a></details>
  </form>
<?php endforeach; ?>
</div>
<form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="group" value="general">
  <h2>Sign-up rules</h2>
  <label class="switch"><input type="checkbox" name="allow_seeker_signup" value="1" <?= $on('allow_seeker_signup') ? 'checked' : '' ?>><span></span>Job seekers can create accounts</label>
  <label class="switch"><input type="checkbox" name="allow_employer_signup" value="1" <?= $on('allow_employer_signup') ? 'checked' : '' ?>><span></span>Employers can create accounts (off = only admin creates companies)</label>
  <label class="switch"><input type="checkbox" name="password_login" value="1" <?= $on('password_login') ? 'checked' : '' ?>><span></span>Allow email + password login (if off, only Google/Microsoft - kept on automatically while no provider is live)</label>
  <label class="switch"><input type="checkbox" name="require_email_verify" value="1" <?= $on('require_email_verify') ? 'checked' : '' ?>><span></span>Job seekers must verify email before applying (Google/Microsoft accounts are verified automatically)</label>
  <button class="btn btn-dark btn-sm">Save rules</button>
</form>
<div class="card"><h3>Preview</h3><div class="btn-row"><a class="btn btn-ghost btn-sm" href="<?= url('login') ?>" target="_blank">Open login page ↗</a><a class="btn btn-ghost btn-sm" href="<?= url('register') ?>" target="_blank">Open sign-up page ↗</a></div><p class="muted small">Tip: open them in a private window to see them as a visitor.</p></div>
<?php admin_end();
