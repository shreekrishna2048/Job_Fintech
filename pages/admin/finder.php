<?php
@set_time_limit(180);
if (is_post()) {
    $act = input('action');
    if ($act === 'settings') {
        $cs = array_values(array_intersect(array_keys(COUNTRIES), (array) ($_POST['countries'] ?? [])));
        save_setting('countries', implode(',', $cs ?: ['Nepal']));
        foreach (COUNTRIES as $cn => $ci) {
            $k = 'finder_sources_' . strtolower($ci['code']);
            if (array_key_exists($k, $_POST)) save_setting($k, implode("\n", array_slice(array_filter(array_map('trim', preg_split('/\R/', (string) $_POST[$k])), fn($u) => filter_var($u, FILTER_VALIDATE_URL)), 0, 50)));
        }
        save_setting('finder_exclude_domains', mb_substr(trim((string) ($_POST['finder_exclude_domains'] ?? '')), 0, 4000));
        flash('success', 'Job finder settings saved.');
        redirect('admin/finder');
    }
    if ($act === 'start') {
        $country = in_array(input('country'), array_keys(COUNTRIES), true) ? input('country') : 'Nepal';
        if (!in_array($country, countries_enabled(), true)) save_setting('countries', implode(',', array_merge(countries_enabled(), [$country])));
        if (!ai_enabled()) { flash('error', 'Set up AI first (Admin › AI & integrations).'); redirect('admin/ai'); }
        $kw = array_slice(array_filter(array_map('trim', explode(',', (string) input('keywords')))), 0, 6);
        $days = in_array((int) input('days'), [1, 3, 7, 14, 30], true) ? (int) input('days') : 7;
        $limit = in_array((int) input('limit'), [5, 10, 20, 30, 50], true) ? (int) input('limit') : 10;
        $web = !empty($_POST['use_web']); $src = !empty($_POST['use_sources']);
        if (!$web && !$src) { flash('error', 'Choose at least one place to look.'); redirect('admin/finder'); }
        [$cands, $notes] = finder_collect($country, $kw, $days, $limit, $web, $src);
        if (!$cands) { flash('error', 'No new pages found. ' . implode(' ', $notes) . ' Try more days, other keywords, or add sources.'); keep_old(); redirect('admin/finder'); }
        $name = 'Job finder: ' . ($country === 'United Arab Emirates' ? 'UAE' : $country) . ($kw ? ' · ' . implode(', ', $kw) : '') . " · last $days day" . ($days > 1 ? 's' : '');
        $opt = ['mode' => 'finder', 'country' => $country, 'keywords' => $kw, 'days' => $days, 'rewrite' => !empty($_POST['rewrite']), 'require_date' => !empty($_POST['require_date']),
            'logos' => true, 'default_location' => null, 'notes' => $notes, 'stats' => []];
        $id = insert('import_batches', ['created_by' => uid(), 'source_name' => $name, 'chunks' => json_encode($cands, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 'total_chunks' => count($cands), 'options' => json_encode($opt, JSON_UNESCAPED_UNICODE)]);
        log_activity('finder_run', 'import', $id, $name . ' · ' . count($cands) . ' pages');
        redirect('admin/import?batch=' . $id);
    }
}
$hasTavily = setting('tavily_api_key') !== '';
$cap = (int) setting('finder_daily_searches', '20'); $used = finder_searches_today();
[$pi, $po] = ai_prices(ai_provider());
$recent = rows("SELECT b.*, (SELECT COUNT(*) FROM import_items i WHERE i.batch_id=b.id) items, (SELECT COUNT(*) FROM import_items i WHERE i.batch_id=b.id AND i.status='published') published FROM import_batches b WHERE b.source_name LIKE 'Job finder:%' ORDER BY b.id DESC LIMIT 10");
admin_start('Job finder');
?>
<div class="card finder-intro">
  <p style="margin:0">Finds <b>recent job posts</b> for a country, reads each one, removes old, closed and duplicate jobs, and shows you exactly what it found. <b>Nothing is selected or published until you choose.</b></p>
  <div class="chips" style="margin-top:10px">
    <span>AI: <?= ai_enabled() ? e(AI_PROVIDERS[ai_provider()][0]) . ($pi + $po > 0 ? ' (paid, cap $' . e(setting('ai_budget_daily', '0.05')) . '/day)' : ' (free)') : '<b class="err">not set up</b>' ?></span>
    <span>Web searches today: <?= $used ?> / <?= $cap ?></span>
    <span>AI spend today: $<?= number_format(ai_spent('day'), 4) ?> · this month: $<?= number_format(ai_spent('month'), 4) ?></span>
  </div>
</div>
<form method="post" class="card form" data-loading><?= csrf_field() ?><input type="hidden" name="action" value="start">
  <h2><?= icon('target') ?> Find jobs</h2>
  <div class="row3">
    <div class="field"><label>Country</label><select name="country"><?php foreach (COUNTRIES as $cn => $ci): ?><option value="<?= e($cn) ?>" <?= old('country', 'Nepal') === $cn ? 'selected' : '' ?>><?= $ci['flag'] ?> <?= e($cn) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Posted within</label><select name="days"><?= options(['1' => 'Last 24 hours', '3' => 'Last 3 days', '7' => 'Last 7 days', '14' => 'Last 14 days', '30' => 'Last 30 days'], old('days', '7')) ?></select></div>
    <div class="field"><label>How many pages to check</label><select name="limit"><?= options(['5' => '5 (quick test)', '10' => '10', '20' => '20', '30' => '30', '50' => '50'], old('limit', '10')) ?></select><small class="muted">One AI request per page. A page can contain several jobs.</small></div>
  </div>
  <div class="field"><label>Keywords <span class="muted">(optional, comma separated)</span></label><input name="keywords" value="<?= e(old('keywords')) ?>" placeholder="e.g. accountant, nurse, driver, IT  (empty = all kinds of jobs)"></div>
  <div class="inline-checks">
    <label class="check"><input type="checkbox" name="use_web" value="1" <?= $hasTavily ? 'checked' : 'disabled' ?>> Search the web <?= $hasTavily ? '' : '<a href="' . url('admin/ai') . '">(add Tavily key)</a>' ?></label>
    <label class="check"><input type="checkbox" name="use_sources" value="1" checked> My saved sources (below)</label>
  </div>
  <label class="switch"><input type="checkbox" name="rewrite" value="1" checked><span></span>Rewrite descriptions in our own words (better for Google)</label>
  <label class="switch"><input type="checkbox" name="require_date" value="1"><span></span>Only keep jobs that show a posting date</label>
  <button class="btn btn-brand btn-lg"><?= icon('search', 'i i-sm') ?>Find jobs</button>
  <p class="muted small" style="margin:0">Searching takes 10 to 40 seconds, then each page is read with a progress bar. If the free AI limit is reached it waits and continues by itself.</p>
</form>

<form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
  <h2><?= icon('settings') ?> Sources & countries</h2>
  <div class="field"><label>Countries on your site</label><div class="inline-checks"><?php foreach (COUNTRIES as $cn => $ci): ?><label class="check"><input type="checkbox" name="countries[]" value="<?= e($cn) ?>" <?= in_array($cn, countries_enabled(), true) ? 'checked' : '' ?>> <?= $ci['flag'] ?> <?= e($cn) ?> (<?= $ci['currency'] ?>)</label><?php endforeach; ?></div><small class="muted">Turning on the UAE adds Dubai, Abu Dhabi, Sharjah and other emirates to city lists, and a Nepal / UAE switch on the jobs page.</small></div>
  <div class="row2">
    <?php foreach (COUNTRIES as $cn => $ci): $k = 'finder_sources_' . strtolower($ci['code']); ?>
    <div class="field"><label><?= $ci['flag'] ?> Saved sources for <?= e($cn) ?> <span class="muted">(one link per line, max 50)</span></label><textarea name="<?= $k ?>" rows="6" placeholder="https://company.com/careers&#10;https://example.gov.np/notices&#10;https://site.com/jobs/feed (RSS)"><?= e(setting($k, '')) ?></textarea></div>
    <?php endforeach; ?>
  </div>
  <p class="muted small" style="margin:0">Best sources: company career pages, bank/NGO/hospital/hotel “vacancy” pages, government notice pages and RSS feeds. Pages that block bots (robots.txt) are skipped automatically.</p>
  <div class="field"><label>Never use these websites <span class="muted">(domains, comma or new line)</span></label><textarea name="finder_exclude_domains" rows="2" placeholder="example.com, anothersite.com"><?= e(setting('finder_exclude_domains', '')) ?></textarea></div>
  <button class="btn btn-dark btn-sm">Save</button>
</form>

<?php if ($recent): ?><div class="card"><h2>Recent finder runs</h2><div class="table-wrap"><table class="table"><thead><tr><th>Search</th><th>Jobs found</th><th>Published</th><th>When</th><th></th></tr></thead><tbody>
<?php foreach ($recent as $r): ?><tr><td><?= e(substr($r['source_name'], 12)) ?></td><td><?= (int) $r['items'] ?></td><td><?= (int) $r['published'] ?></td><td class="nowrap"><?= e(time_ago($r['created_at'])) ?></td><td class="right"><a class="btn btn-ghost btn-xs" href="<?= url('admin/import?batch=' . $r['id']) ?>">Open</a></td></tr><?php endforeach; ?>
</tbody></table></div></div><?php endif; ?>
<?php admin_end();
