<?php
$secretKeys = ['groq_api_key', 'xai_api_key', 'openai_api_key', 'custom_api_key', 'hunter_api_key', 'google_indexing_json', 'tavily_api_key'];
$raw = fn($k) => (string) val('SELECT `value` FROM settings WHERE `key`=?', [$k]);
if (is_post()) {
    $act = input('action');
    if ($act === 'models') {
        if (isset(AI_PROVIDERS[input('provider')])) $GLOBALS['ai_provider_override'] = input('provider');
        try { json_out(['ok' => true, 'models' => ai_list_models()]); } catch (Throwable $e) { json_out(['ok' => false, 'error' => $e->getMessage()]); }
    }
    if ($act === 'test_ai') {
        try { $t = microtime(true); $r = ai_chat('Reply with exactly: OK', 'Test', 'admin_test', false, 10); json_out(['ok' => true, 'message' => 'Connected to ' . AI_PROVIDERS[ai_provider()][0] . ' · model ' . ai_model() . ' · replied “' . mb_substr($r, 0, 20) . '” in ' . round(microtime(true) - $t, 1) . 's']); }
        catch (Throwable $e) { json_out(['ok' => false, 'error' => $e->getMessage()]); }
    }
    if ($act === 'test_google') {
        try { save_setting('google_indexing_token', ''); google_access_token(); json_out(['ok' => true, 'message' => 'Google accepted the service account. Make sure its email is an Owner in Search Console.']); }
        catch (Throwable $e) { json_out(['ok' => false, 'error' => $e->getMessage()]); }
    }
    if ($act === 'save') {
        $plain = ['ai_provider', 'groq_model', 'xai_model', 'openai_model', 'custom_model', 'ai_custom_base', 'ai_import_model', 'ai_daily_limit', 'ai_fallback_provider', 'ai_fallback_after', 'finder_daily_searches'];
        foreach (array_keys(AI_PROVIDERS) as $pp) $plain = array_merge($plain, [$pp . '_price_in', $pp . '_price_out']);
        foreach (['ai_budget_daily', 'ai_budget_monthly'] as $bk) if (isset($_POST[$bk])) save_setting($bk, (string) max(0, round((float) $_POST[$bk], 4)));
        if (($_POST['ai_fallback_provider'] ?? '') === ($_POST['ai_provider'] ?? '')) $_POST['ai_fallback_provider'] = '';
        foreach ($plain as $k) if (array_key_exists($k, $_POST)) save_setting($k, mb_substr(trim((string) $_POST[$k]), 0, 300));
        foreach ($secretKeys as $k) {
            if (!array_key_exists($k, $_POST)) continue;
            $v = trim((string) $_POST[$k]);
            if ($v === '••••••••') continue;
            if ($k === 'google_indexing_json' && $v !== '' && empty(json_decode($v, true)['private_key'])) { flash('error', 'That is not a Google service-account JSON key file.'); redirect('admin/ai'); }
            save_setting($k, $v);
            if ($k === 'google_indexing_json') save_setting('google_indexing_token', '');
        }
        save_setting('ai_enabled', !empty($_POST['ai_enabled']) ? '1' : '0');
        save_setting('indexnow_enabled', !empty($_POST['indexnow_enabled']) ? '1' : '0');
        log_activity('admin_ai_settings');
        flash('success', 'Saved.');
        redirect('admin/ai');
    }
}
$prov = ai_provider();
$mask = fn($k) => $raw($k) !== '' ? '••••••••' : '';
$secretInput = fn($k, $ph = '') => '<input type="password" name="' . $k . '" value="' . $mask($k) . '" placeholder="' . e($ph) . '" autocomplete="new-password" spellcheck="false" onfocus="if(this.value===\'••••••••\')this.value=\'\'" data-had="' . ($raw($k) !== '' ? '1' : '') . '" onblur="if(this.value===\'\'&&this.dataset.had)this.value=\'••••••••\'">';
$gi = $raw('google_indexing_json') ? (json_decode($raw('google_indexing_json'), true)['client_email'] ?? '') : '';
admin_start('AI & integrations');
?>
<form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="save">
<div class="card form">
  <div class="label-row"><h2><?= icon('sparkles') ?> AI provider</h2><span class="badge <?= ai_enabled() ? 'b-green' : 'b-gray' ?>"><?= ai_enabled() ? 'Active: ' . e(AI_PROVIDERS[$prov][0]) : 'Not set up' ?></span></div>
  <p class="muted small" style="margin:0">Used for the bulk job importer, AI job writer, cover letters, profile summaries and smart search.</p>
  <label class="switch"><input type="checkbox" name="ai_enabled" value="1" <?= setting('ai_enabled', '1') === '1' ? 'checked' : '' ?>><span></span>AI features on</label>
  <div class="field"><label>Provider</label><div class="seg seg-wrap" data-prov-seg><?php foreach (AI_PROVIDERS as $k => [$n]): ?><label><input type="radio" name="ai_provider" value="<?= $k ?>" <?= $prov === $k ? 'checked' : '' ?> hidden><span><?= e($n) ?></span></label><?php endforeach; ?></div></div>
  <div data-prov="groq" class="prov-box"><div class="row2"><div class="field"><label>Groq API key <a class="small" href="https://console.groq.com/keys" target="_blank" rel="noopener">get key ↗</a></label><?= $secretInput('groq_api_key', 'gsk_...') ?></div>
    <div class="field"><label>Model</label><div class="copy-row"><input name="groq_model" value="<?= e($raw('groq_model') ?: 'llama-3.3-70b-versatile') ?>" list="models-groq"><button type="button" class="btn btn-ghost btn-sm" data-load-models="groq">Load list</button></div><datalist id="models-groq"></datalist></div></div></div>
  <div data-prov="xai" class="prov-box"><div class="row2"><div class="field"><label>xAI (Grok) API key <a class="small" href="https://console.x.ai" target="_blank" rel="noopener">get key ↗</a></label><?= $secretInput('xai_api_key', 'xai-...') ?></div>
    <div class="field"><label>Model</label><div class="copy-row"><input name="xai_model" value="<?= e($raw('xai_model') ?: 'grok-3-mini') ?>" list="models-xai"><button type="button" class="btn btn-ghost btn-sm" data-load-models="xai">Load list</button></div><datalist id="models-xai"></datalist></div></div></div>
  <div data-prov="openai" class="prov-box"><div class="row2"><div class="field"><label>OpenAI API key</label><?= $secretInput('openai_api_key', 'sk-...') ?></div>
    <div class="field"><label>Model</label><div class="copy-row"><input name="openai_model" value="<?= e($raw('openai_model') ?: 'gpt-4o-mini') ?>" list="models-openai"><button type="button" class="btn btn-ghost btn-sm" data-load-models="openai">Load list</button></div><datalist id="models-openai"></datalist></div></div></div>
  <div data-prov="custom" class="prov-box"><div class="row3"><div class="field"><label>API base URL</label><input name="ai_custom_base" value="<?= e($raw('ai_custom_base')) ?>" placeholder="https://api.example.com/v1"></div><div class="field"><label>API key</label><?= $secretInput('custom_api_key') ?></div>
    <div class="field"><label>Model</label><div class="copy-row"><input name="custom_model" value="<?= e($raw('custom_model')) ?>" list="models-custom"><button type="button" class="btn btn-ghost btn-sm" data-load-models="custom">Load list</button></div><datalist id="models-custom"></datalist></div></div></div>
  <div class="row2">
    <div class="field"><label>Model for bulk import <span class="muted">(optional - a bigger model reads messy text better)</span></label><input name="ai_import_model" value="<?= e($raw('ai_import_model')) ?>" placeholder="Same as above"></div>
    <div class="field"><label>AI uses per user per day (job seekers / employers)</label><input type="number" name="ai_daily_limit" min="0" value="<?= e(setting('ai_daily_limit', '30')) ?>"></div>
  </div>
  <div class="btn-row"><button class="btn btn-dark btn-sm">Save</button><button type="button" class="btn btn-brand btn-sm" data-ai-test="test_ai"><?= icon('zap', 'i i-sm') ?>Test connection</button><span class="small" data-test-out="test_ai"></span></div>
  <p class="muted small" style="margin:0">Save first, then test. “Load list” shows the models your key can use (save the key first).</p>
</div>

<div class="card form">
  <div class="label-row"><h2><?= icon('shield') ?> Spending limit & smart switching</h2><span class="badge b-blue">Spent today $<?= number_format(ai_spent('day'), 4) ?> · month $<?= number_format(ai_spent('month'), 4) ?></span></div>
  <p class="muted small" style="margin:0">Free providers (Groq free plan) are never counted. For paid providers the system checks the <b>worst-case cost before every request</b> and refuses it if it would go over your cap, so you can't be surprised.</p>
  <div class="row2">
    <div class="field"><label>Max AI spend per day (US$)</label><input type="number" step="0.01" min="0" name="ai_budget_daily" value="<?= e(setting('ai_budget_daily', '0.05')) ?>"></div>
    <div class="field"><label>Max AI spend per month (US$)</label><input type="number" step="0.01" min="0" name="ai_budget_monthly" value="<?= e(setting('ai_budget_monthly', '1')) ?>"></div>
  </div>
  <div class="row2">
    <div class="field"><label>Backup provider when the main one hits its free limit</label><select name="ai_fallback_provider"><?= options(['' => 'None - just wait for the free limit to reset'] + array_map(fn($x) => $x[0], AI_PROVIDERS), $raw('ai_fallback_provider')) ?></select><small class="muted">Needs its key above. Uses the spending limit.</small></div>
    <div class="field"><label>For bulk import & job finder: wait up to (seconds) before switching</label><input type="number" min="0" max="3600" name="ai_fallback_after" value="<?= e(setting('ai_fallback_after', '180')) ?>"><small class="muted">180 = wait up to 3 minutes for the free limit; switch only if longer (or the daily quota is used up).</small></div>
  </div>
  <details class="add-block"><summary><?= icon('money', 'i i-sm') ?>Prices used for the limit (US$ per 1 million tokens)</summary>
    <div class="price-grid"><?php foreach (AI_PROVIDERS as $pp => [$pn]): [$di, $do] = ai_prices($pp); ?>
      <div class="field"><label><?= e($pn) ?> input</label><input type="number" step="0.001" min="0" name="<?= $pp ?>_price_in" value="<?= e($raw($pp . '_price_in') !== '' ? $raw($pp . '_price_in') : $di) ?>"></div>
      <div class="field"><label><?= e($pn) ?> output</label><input type="number" step="0.001" min="0" name="<?= $pp ?>_price_out" value="<?= e($raw($pp . '_price_out') !== '' ? $raw($pp . '_price_out') : $do) ?>"></div>
    <?php endforeach; ?></div>
    <p class="muted small">Defaults are set high on purpose so the limit is never under-counted. Groq = 0 because its free plan costs nothing; set real prices if you upgrade Groq. Copy exact prices from your provider's pricing page for your model.</p>
  </details>
  <div class="alert alert-info" style="margin:0"><b>Extra safety at xAI:</b> in console.x.ai › Billing, buy a small amount of prepaid credit (e.g. $5) and keep <b>auto top-up OFF</b>. When the credit is used up, xAI simply stops answering. It cannot charge your card more.</div>
  <?php $logs = rows("SELECT feature, provider, COUNT(*) n, SUM(COALESCE(prompt_tokens,0)+COALESCE(completion_tokens,0)) tok, SUM(cost_usd) cost FROM ai_logs WHERE created_at >= ? GROUP BY feature, provider ORDER BY cost DESC, n DESC LIMIT 12", [date('Y-m-01')]); if ($logs): ?>
  <div class="table-wrap"><table class="table table-sm"><thead><tr><th>This month</th><th>Provider</th><th>Requests</th><th>Tokens</th><th>Cost</th></tr></thead><tbody><?php foreach ($logs as $l): ?><tr><td><code><?= e($l['feature']) ?></code></td><td><?= e($l['provider'] ?: '-') ?></td><td><?= (int) $l['n'] ?></td><td><?= number_format((int) $l['tok']) ?></td><td>$<?= number_format((float) $l['cost'], 4) ?></td></tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>

<div class="card form">
  <h2><?= icon('search') ?> Web search for the Job finder</h2>
  <p class="muted small" style="margin:0">The Job finder uses Tavily to find recent job pages on the web (free plan includes a monthly allowance; 1 credit per search). Without it, the finder only checks your saved sources.</p>
  <div class="row2">
    <div class="field"><label>Tavily API key <a class="small" href="https://app.tavily.com" target="_blank" rel="noopener">get free key ↗</a></label><?= $secretInput('tavily_api_key', 'tvly-...') ?></div>
    <div class="field"><label>Max web searches per day</label><input type="number" min="0" max="500" name="finder_daily_searches" value="<?= e(setting('finder_daily_searches', '20')) ?>"><small class="muted">Searches today: <?= finder_searches_today() ?></small></div>
  </div>
</div>

<div class="card form">
  <h2><?= icon('mail') ?> Company email finder</h2>
  <p class="muted small" style="margin:0">When importing, “Find contacts” reads the company’s own website (home, contact, about, careers pages) for real emails. Optionally add a Hunter.io key for companies whose websites don’t show an email (free plan: 25 searches/month).</p>
  <div class="field"><label>Hunter.io API key (optional) <a class="small" href="https://hunter.io/api-keys" target="_blank" rel="noopener">get key ↗</a></label><?= $secretInput('hunter_api_key') ?></div>
</div>

<div class="card form">
  <h2><?= icon('globe') ?> Instant indexing (SEO)</h2>
  <label class="switch"><input type="checkbox" name="indexnow_enabled" value="1" <?= setting('indexnow_enabled', '1') === '1' ? 'checked' : '' ?>><span></span><span><b>IndexNow</b> - tells Bing, Yahoo, Yandex and others about every new or closed job instantly. No setup needed.<?php if ($l = setting('indexnow_last')): ?><br><small class="muted">Last ping: <?= e($l) ?></small><?php endif; ?></span></label>
  <div class="field"><label><b>Google Indexing API</b> - service account JSON key <span class="muted">(optional; Google officially supports this for job pages, usually indexed within hours instead of days)</span></label>
    <?php if ($gi): ?><p class="small ok-txt" style="margin:0">Connected service account: <code><?= e($gi) ?></code></p><?php endif; ?>
    <textarea name="google_indexing_json" rows="3" class="mono" placeholder='Paste the whole JSON key file: { "type": "service_account", ... }'><?= $raw('google_indexing_json') ? '••••••••' : '' ?></textarea></div>
  <details class="add-block"><summary><?= icon('settings', 'i i-sm') ?>How to get the Google key (5 steps)</summary><ol class="small steps-sm">
    <li>Google Cloud Console › create a project › enable <b>Web Search Indexing API</b>.</li>
    <li>IAM › Service accounts › Create › then Keys › Add key › JSON (downloads a file).</li>
    <li>Paste the file’s full content above and Save.</li>
    <li>Google Search Console › your property (<?= e(parse_url(base_url(), PHP_URL_HOST)) ?>) › Settings › Users and permissions › Add user: the service account email as <b>Owner</b>.</li>
    <li>Click “Test Google key”. New jobs are then sent to Google automatically (up to 200 a day).</li>
  </ol></details>
  <div class="btn-row"><button class="btn btn-dark btn-sm">Save</button><?php if ($gi): ?><button type="button" class="btn btn-ghost btn-sm" data-ai-test="test_google">Test Google key</button><span class="small" data-test-out="test_google"></span><?php endif; ?></div>
</div>
</form>
<?php admin_end();
