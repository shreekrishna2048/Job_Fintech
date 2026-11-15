<?php
@set_time_limit(240);
$batchId = (int) ($_GET['batch'] ?? $_POST['batch'] ?? 0);

/* ---------- AJAX / POST actions ---------- */
if (is_post()) {
    $act = input('action');
    if ($act === 'create') {
        if (!ai_enabled()) { flash('error', 'Add your AI key first in Admin › AI & integrations.'); redirect('admin/ai'); }
        try {
            $chunks = []; $src = 'Pasted text';
            if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['file']['size'] > 15 * 1048576) throw new RuntimeException('File too large (max 15 MB).');
                $chunks = import_file_chunks($_FILES['file']['tmp_name'], $_FILES['file']['name']);
                $src = mb_substr(basename($_FILES['file']['name']), 0, 190);
            }
            if (trim((string) ($_POST['raw'] ?? '')) !== '') $chunks = array_merge($chunks, import_text_chunks((string) $_POST['raw']));
            if (!$chunks) throw new RuntimeException('Paste job text or choose a file.');
            if (count($chunks) > 120) throw new RuntimeException('That is very large (' . count($chunks) . ' parts). Split it into smaller imports of ~300 jobs.');
            $opt = ['country' => in_array(input('country'), countries_enabled(), true) ? input('country') : countries_enabled()[0], 'rewrite' => !empty($_POST['rewrite']), 'featured' => !empty($_POST['featured']), 'logos' => !empty($_POST['logos']),
                'default_location' => (int) input('default_location') ?: null, 'days' => max(3, min(120, (int) input('days', 30)))];
            $id = insert('import_batches', ['created_by' => uid(), 'source_name' => $src, 'chunks' => json_encode($chunks, JSON_UNESCAPED_UNICODE), 'total_chunks' => count($chunks), 'options' => json_encode($opt)]);
            log_activity('import_create', 'import', $id, $src . ' · ' . count($chunks) . ' parts');
            redirect('admin/import?batch=' . $id);
        } catch (RuntimeException $e) { flash('error', $e->getMessage()); keep_old(); redirect('admin/import'); }
    }
    if ($act === 'parse') {
        try { $n = import_parse_chunk($batchId, (int) input('chunk')); json_out(['ok' => true, 'found' => $n, 'stats' => $GLOBALS['finder_last_stats'] ?? null]); }
        catch (AiBudget $e) { json_out(['ok' => false, 'stop' => true, 'error' => $e->getMessage()]); }
        catch (AiRateLimit $e) { json_out(['ok' => false, 'retry' => true, 'wait' => min(300, max(5, $e->wait)), 'error' => $e->getMessage()]); }
        catch (Throwable $e) {
            $msg = $e->getMessage();
            // Free plans (e.g. Groq) limit tokens per minute: tell the browser to wait and retry this same part
            if (preg_match('/rate.?limit|too many requests|HTTP 429|try again in/i', $msg)) {
                $wait = preg_match('/try again in (?:(\d+)m)?([\d.]+)s/i', $msg, $m) ? (int) ceil(((int) ($m[1] ?? 0)) * 60 + (float) $m[2]) + 2 : 30;
                json_out(['ok' => false, 'retry' => true, 'wait' => min(120, max(5, $wait)), 'error' => 'AI rate limit reached']);
            }
            q("UPDATE import_batches SET parsed_chunks=parsed_chunks+1, status=IF(parsed_chunks>=total_chunks,'review',status) WHERE id=?", [$batchId]);
            json_out(['ok' => false, 'error' => $msg]);
        }
    }
    if ($act === 'contacts') {
        try { json_out(['ok' => true] + find_company_contacts(mb_substr(input('company'), 0, 160), (string) input('website'))); }
        catch (Throwable $e) { json_out(['ok' => false, 'error' => $e->getMessage()]); }
    }
    if ($act === 'reparse') { // retry the AI on the whole batch
        q('DELETE FROM import_items WHERE batch_id=? AND status=?', [$batchId, 'pending']);
        q("UPDATE import_batches SET parsed_chunks=0, status='parsing' WHERE id=?", [$batchId]);
        redirect('admin/import?batch=' . $batchId);
    }
    if ($act === 'delete_batch') { q('DELETE FROM import_batches WHERE id=?', [$batchId]); flash('success', 'Import deleted.'); redirect('admin/import'); }
    if ($act === 'save' || $act === 'publish') {
        $b = row('SELECT * FROM import_batches WHERE id=?', [$batchId]);
        if (!$b) abort(404);
        $opt = json_decode((string) $b['options'], true) ?: [];
        $items = json_decode((string) ($_POST['items_json'] ?? ''), true);
        if (!is_array($items)) { flash('error', 'Nothing received - please try again.'); redirect('admin/import?batch=' . $batchId); }
        $pub = 0; $skip = 0; $errs = [];
        foreach ($items as $it) {
            $item = row("SELECT * FROM import_items WHERE id=? AND batch_id=? AND status IN ('pending','error')", [(int) ($it['id'] ?? 0), $batchId]);
            if (!$item) continue;
            $d = array_merge(json_decode($item['data'], true) ?: [], array_map(fn($v) => is_string($v) ? trim($v) : $v, (array) ($it['data'] ?? [])));
            if ($act === 'save' || empty($it['include'])) {
                update('import_items', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE), 'status' => ($act === 'publish' && empty($it['include'])) ? 'skipped' : $item['status']], 'id=?', [$item['id']]);
                if ($act === 'publish') $skip++;
                continue;
            }
            try {
                $jid = import_publish_item($d, $opt);
                update('import_items', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE), 'status' => 'published', 'job_id' => $jid, 'company_id' => val('SELECT company_id FROM jobs WHERE id=?', [$jid]), 'note' => null], 'id=?', [$item['id']]);
                $pub++;
            } catch (Throwable $e) {
                update('import_items', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE), 'status' => 'error', 'note' => mb_substr($e->getMessage(), 0, 255)], 'id=?', [$item['id']]);
                $errs[] = ($d['title'] ?? '?') . ': ' . $e->getMessage();
            }
        }
        if ($act === 'publish') {
            if (!val("SELECT 1 FROM import_items WHERE batch_id=? AND status IN ('pending','error')", [$batchId])) update('import_batches', ['status' => 'done'], 'id=?', [$batchId]);
            log_activity('import_publish', 'import', $batchId, "$pub published, $skip skipped, " . count($errs) . ' errors');
            flash($errs ? 'info' : 'success', "$pub job(s) are live" . ($skip ? ", $skip skipped" : '') . ($errs ? '. ' . count($errs) . ' need fixing: ' . mb_substr(implode('; ', $errs), 0, 300) : '. Search engines are being notified.'));
        } else flash('success', 'Changes saved.');
        redirect('admin/import?batch=' . $batchId);
    }
}

$catOpts = array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], categories());
$locOpts = locations();

/* ---------- views ---------- */
if (!$batchId):
    $recent = rows("SELECT b.*, (SELECT COUNT(*) FROM import_items i WHERE i.batch_id=b.id) items, (SELECT COUNT(*) FROM import_items i WHERE i.batch_id=b.id AND i.status='published') published FROM import_batches b ORDER BY b.id DESC LIMIT 15");
    admin_start('Bulk import jobs');
    if (!ai_enabled()): ?><div class="alert alert-warn">AI is not set up yet. <a href="<?= url('admin/ai') ?>">Add your Groq / Grok API key</a> - the importer needs it to read the text.</div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card form" data-loading><?= csrf_field() ?><input type="hidden" name="action" value="create">
  <div class="label-row"><h2>1. Paste jobs or upload a file</h2><span class="muted small">Many jobs from many companies at once is fine</span></div>
  <textarea name="raw" rows="12" placeholder="Paste anything here: job posts copied from websites, newspapers, Facebook, emails… one after another. The AI separates each job, company, salary, deadline and contact details."><?= e(old('raw')) ?></textarea>
  <div class="upload-zone" data-upload-zone><input type="file" name="file" id="imp-file" accept=".xlsx,.csv,.docx,.txt"><label for="imp-file"><?= icon('upload') ?><b>…or upload Excel / CSV / Word</b><small>.xlsx (first sheet, one job per row, titles in row 1) · .csv · .docx · .txt - max 15 MB</small></label></div>
  <h3>Options</h3>
  <div class="row3">
    <?php if (count(countries_enabled()) > 1): ?><div class="field"><label>Country of these jobs</label><select name="country"><?= options(array_combine(countries_enabled(), countries_enabled()), old('country', 'Nepal')) ?></select></div><?php else: ?><input type="hidden" name="country" value="<?= e(countries_enabled()[0]) ?>"><?php endif; ?>
    <div class="field"><label>City if not mentioned</label><select name="default_location"><?= options($locOpts, old('default_location', ''), 'Leave empty (I’ll choose)') ?></select></div>
    <div class="field"><label>Deadline if not mentioned (days)</label><input type="number" name="days" min="3" max="120" value="<?= e(old('days', setting('job_default_days', '30'))) ?>"></div>
    <div class="field"><label>&nbsp;</label><label class="check"><input type="checkbox" name="featured" value="1"> Mark as featured</label></div>
  </div>
  <label class="switch"><input type="checkbox" name="rewrite" value="1" checked><span></span><span><b>Rewrite descriptions in our own words</b> - unique content ranks better on Google and avoids copying other sites’ text (recommended)</span></label>
  <label class="switch"><input type="checkbox" name="logos" value="1" checked><span></span>Fetch company logos from their websites when a website is known</label>
  <button class="btn btn-brand btn-lg"><?= icon('sparkles', 'i i-sm') ?>Read with AI</button>
  <p class="muted small">Nothing goes live yet - you’ll review and edit every job before publishing.</p>
</form>
<?php if ($recent): ?><div class="card"><h2>Recent imports</h2><div class="table-wrap"><table class="table"><thead><tr><th>Source</th><th>Jobs found</th><th>Published</th><th>Status</th><th>When</th><th></th></tr></thead><tbody>
<?php foreach ($recent as $r): ?><tr><td><?= e($r['source_name']) ?></td><td><?= (int) $r['items'] ?></td><td><?= (int) $r['published'] ?></td><td><?= status_badge($r['status'] === 'done' ? 'sent' : ($r['status'] === 'review' ? 'pending' : 'draft')) ?> <small class="muted"><?= e($r['status']) ?></small></td><td class="nowrap"><?= e(time_ago($r['created_at'])) ?></td><td class="right"><a class="btn btn-ghost btn-xs" href="?batch=<?= $r['id'] ?>">Open</a></td></tr><?php endforeach; ?>
</tbody></table></div></div><?php endif; ?>
<div class="card"><h3>Tips</h3><ul class="small muted" style="margin:0;padding-left:18px">
  <li>Excel: one job per row with column titles like <i>Title, Company, Location, Salary, Deadline, Email, Website, Description</i>. Any column names work.</li>
  <li>Emails, phones and websites are only taken from your text or from the company’s own website - the AI never invents them.</li>
  <li>Applications to imported jobs are tracked here and forwarded automatically (with CV) to the company email.</li>
</ul></div>
<?php admin_end(); return; endif;

$b = row('SELECT * FROM import_batches WHERE id=?', [$batchId]);
if (!$b) abort(404);
$opt = json_decode((string) $b['options'], true) ?: [];

$isFinder = ($opt['mode'] ?? '') === 'finder';
if ($b['status'] === 'parsing' && (int) $b['parsed_chunks'] < (int) $b['total_chunks'] && empty($_GET['review'])):
    admin_start('Reading jobs…'); ?>
<div class="card import-progress" data-import-parse data-batch="<?= $batchId ?>" data-total="<?= (int) $b['total_chunks'] ?>" data-start="<?= (int) $b['parsed_chunks'] ?>" <?= $isFinder ? 'data-finder="1"' : '' ?>>
  <h2><?= icon('sparkles') ?> <?= $isFinder ? 'Checking ' . (int) $b['total_chunks'] . ' pages found for' : 'AI is reading' ?> “<?= e($b['source_name']) ?>”</h2>
  <?php if ($isFinder && !empty($opt['notes'])): ?><div class="alert alert-info"><?= e(implode(' ', $opt['notes'])) ?></div><?php endif; ?>
  <div class="progress progress-lg"><span style="width:0%"></span></div>
  <p><b data-ip-status>Starting…</b></p>
  <p class="muted small">Part <span data-ip-done>0</span> of <?= (int) $b['total_chunks'] ?> · <span data-ip-found>0</span> jobs found so far. Keep this page open.</p>
  <ul class="ip-log small" data-ip-log></ul>
</div>
<?php admin_end(); return; endif;

$items = rows('SELECT * FROM import_items WHERE batch_id=? ORDER BY id', [$batchId]);
if ($isFinder) usort($items, fn($a, $b) => strcmp(json_decode($b['data'], true)['posted_date'] ?? '', json_decode($a['data'], true)['posted_date'] ?? ''));
$dates = array_filter(array_map(fn($it) => json_decode($it['data'], true)['posted_date'] ?? '', $items));
$withEmail = count(array_filter($items, fn($it) => !empty(json_decode($it['data'], true)['contact_email'])));
$counts = ['pending' => 0, 'published' => 0, 'skipped' => 0, 'error' => 0];
foreach ($items as $it) $counts[$it['status']]++;
admin_start('Review imported jobs');
?>
<div class="card toolbar import-head">
  <div class="grow"><b><?= e($b['source_name']) ?></b> · <?= count($items) ?> jobs found · <span class="badge b-amber"><?= $counts['pending'] + $counts['error'] ?> to review</span> <span class="badge b-green"><?= $counts['published'] ?> live</span> <?php if ($counts['skipped']): ?><span class="badge b-gray"><?= $counts['skipped'] ?> skipped</span><?php endif; ?></div>
  <a class="btn btn-ghost btn-sm" href="<?= url('admin/import') ?>">New import</a>
  <form method="post" class="inline" onsubmit="return confirm('Run the AI again on the original text? Unpublished edits will be lost.')"><?= csrf_field() ?><input type="hidden" name="action" value="reparse"><input type="hidden" name="batch" value="<?= $batchId ?>"><button class="btn btn-ghost btn-sm">Re-read with AI</button></form>
  <form method="post" class="inline" onsubmit="return confirm('Delete this import? Published jobs stay live.')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_batch"><input type="hidden" name="batch" value="<?= $batchId ?>"><button class="btn btn-danger-ghost btn-sm"><?= icon('trash', 'i i-xs') ?></button></form>
</div>
<?php if ($isFinder): $st = $opt['stats'] ?? []; ?>
<div class="card finder-summary">
  <h2><?= icon('target') ?> Found <?= count($items) ?> new job(s) <?= e($opt['country'] === 'United Arab Emirates' ? 'in the UAE' : 'in ' . $opt['country']) ?><?= !empty($opt['keywords']) ? ' for “' . e(implode(', ', $opt['keywords'])) . '”' : '' ?></h2>
  <div class="fs-stats">
    <div><b><?= count($items) ?></b><span>ready to review</span></div>
    <div><b><?= $dates ? (min($dates) === max($dates) ? e(date('d M', strtotime(min($dates)))) : e(date('d M', strtotime(min($dates)))) . ' to ' . e(date('d M', strtotime(max($dates))))) : 'n/a' ?></b><span>posting dates</span></div>
    <div><b><?= count($items) - count($dates) ?></b><span>date not shown</span></div>
    <div><b><?= $withEmail ?></b><span>with company email</span></div>
    <div><b><?= (int) ($st['old'] ?? 0) ?></b><span>older / closed (hidden)</span></div>
    <div><b><?= (int) ($st['dup'] ?? 0) ?></b><span>already on your site (hidden)</span></div>
  </div>
  <p class="muted small" style="margin:0">Nothing is selected. Tick the jobs you want, check the details, then publish. Each job links to its original page so you can verify it.</p>
</div>
<?php endif; ?>
<?php if (!$items): ?><div class="empty card"><?= icon('search', 'i i-xl') ?><h3>No jobs were found in that text</h3><p>Try pasting the full job posts, or click “Re-read with AI”. A stronger model (Admin › AI & integrations) also helps.</p></div><?php admin_end(); return; endif; ?>

<form method="post" data-import-form><?= csrf_field() ?><input type="hidden" name="batch" value="<?= $batchId ?>"><input type="hidden" name="items_json"><input type="hidden" name="action" value="publish">
<?php if ($counts['pending'] + $counts['error']): ?>
<div class="toolbar import-bulk">
  <label class="check"><input type="checkbox" data-check-all <?= $isFinder ? '' : 'checked' ?>> Select all</label>
  <button type="button" class="btn btn-ghost btn-sm" data-find-all><?= icon('search', 'i i-sm') ?>Find missing company emails</button>
  <span class="muted small grow">Untick jobs you don’t want. Duplicates are unticked automatically.</span>
</div>
<?php endif; ?>
<div class="imp-list">
<?php foreach ($items as $it): $d = json_decode($it['data'], true) ?: []; $done = $it['status'] === 'published'; $fid = 'it' . $it['id']; ?>
  <div class="card imp-item imp-<?= $it['status'] ?>" data-item="<?= $it['id'] ?>">
    <div class="imp-top">
      <?php if (!$done && $it['status'] !== 'skipped'): ?><label class="imp-check"><input type="checkbox" data-include <?= empty($d['duplicate_of']) && !$isFinder ? 'checked' : '' ?>></label><?php endif; ?>
      <div class="grow">
        <input class="imp-title" data-f="title" value="<?= e($d['title'] ?? '') ?>" <?= $done ? 'disabled' : '' ?> aria-label="Job title">
        <div class="imp-badges">
          <?php if ($done): ?><a class="badge b-green" href="<?= url('job/' . val('SELECT slug FROM jobs WHERE id=?', [$it['job_id']])) ?>" target="_blank">✓ Live - view job</a><?php endif; ?>
          <?php if ($it['status'] === 'skipped'): ?><span class="badge b-gray">Skipped</span><?php endif; ?>
          <?php if ($it['status'] === 'error'): ?><span class="badge b-red">Error: <?= e($it['note']) ?></span><?php endif; ?>
          <?php if (!empty($d['duplicate_of'])): ?><a class="badge b-amber" target="_blank" href="<?= url('job/' . val('SELECT slug FROM jobs WHERE id=?', [$d['duplicate_of']])) ?>">Possible duplicate of an existing job</a><?php endif; ?>
          <?php if (!empty($d['company_match'])): ?><span class="badge b-blue" title="Will be added to this existing company">Existing company: <?= e($d['company_match']) ?></span><?php elseif (!$done): ?><span class="badge b-violet">New company</span><?php endif; ?>
          <?php if (!empty($d['posted_date'])): ?><span class="badge b-green">Posted <?= e(date('d M Y', strtotime($d['posted_date']))) ?></span><?php elseif ($isFinder): ?><span class="badge b-gray">Posting date not shown</span><?php endif; ?>
          <?php if (!empty($d['source_url'])): ?><a class="badge b-gray" href="<?= e($d['source_url']) ?>" target="_blank" rel="noopener nofollow">Source: <?= e(domain_of($d['source_url'])) ?> ↗</a><?php endif; ?>
          <?php if (!empty($d['data_source'])): ?><span class="badge <?= str_contains($d['data_source'], 'exact') ? 'b-blue' : 'b-violet' ?>"><?= e($d['data_source']) ?></span><?php endif; ?>
          <?php if (!empty($d['deadline_note'])): ?><span class="badge b-amber"><?= e($d['deadline_note']) ?></span><?php endif; ?>
          <?php if (!$done && empty($d['contact_email']) && ($d['apply_method'] ?? 'portal') === 'portal'): ?><span class="badge b-gray" data-noemail>No company email - applications come to you</span><?php endif; ?>
        </div>
      </div>
    </div>
    <?php if (!$done): ?>
    <div class="imp-grid">
      <div class="field"><label>Company</label><input data-f="company" value="<?= e($d['company'] ?? '') ?>"><input type="hidden" data-f="company_id" value="<?= e($d['company_id'] ?? '') ?>"></div>
      <div class="field"><label>City</label><select data-f="location_id"><?= options($locOpts, $d['location_id'] ?? '', 'Choose city' . (!empty($d['location_text']) ? ' (“' . $d['location_text'] . '”)' : '')) ?></select></div>
      <div class="field"><label>Category</label><select data-f="category_id"><?= options($catOpts, $d['category_id'] ?? '', 'Choose') ?></select></div>
      <div class="field"><label>Type</label><select data-f="job_type"><?= options(JOB_TYPES, $d['job_type'] ?? 'full-time') ?></select></div>
      <div class="field"><label>Level</label><select data-f="job_level"><?= options(JOB_LEVELS, $d['job_level'] ?? 'mid') ?></select></div>
      <div class="field"><label>Work mode</label><select data-f="work_mode"><?= options(WORK_MODES, $d['work_mode'] ?? 'onsite') ?></select></div>
      <div class="field"><label>Salary from</label><input data-f="salary_min" inputmode="numeric" value="<?= e($d['salary_min'] ?? '') ?>"></div>
      <div class="field"><label>Salary to</label><input data-f="salary_max" inputmode="numeric" value="<?= e($d['salary_max'] ?? '') ?>"></div>
      <div class="field"><label>Deadline</label><input type="date" data-f="deadline" value="<?= e($d['deadline'] ?? '') ?>"><small class="muted">Empty = <?= (int) ($opt['days'] ?? 30) ?> days</small></div>
      <div class="field"><label>Vacancies</label><input type="number" min="1" data-f="vacancies" value="<?= e($d['vacancies'] ?? 1) ?>"></div>
      <div class="field"><label>Experience (min yrs)</label><input type="number" min="0" data-f="experience_min" value="<?= e($d['experience_min'] ?? 0) ?>"></div>
      <div class="field"><label>Education</label><input data-f="education" value="<?= e($d['education'] ?? '') ?>"></div>
    </div>
    <div class="imp-contact">
      <div class="field"><label>Company email (applications are forwarded here)</label><input type="email" data-f="contact_email" value="<?= e($d['contact_email'] ?? '') ?>" placeholder="hr@company.com"></div>
      <div class="field"><label>Company website</label><input data-f="company_website" value="<?= e($d['company_website'] ?? '') ?>" placeholder="https://"></div>
      <div class="field"><label>Phone</label><input data-f="contact_phone" value="<?= e($d['contact_phone'] ?? '') ?>"></div>
      <button type="button" class="btn btn-ghost btn-sm" data-find-contact><?= icon('search', 'i i-sm') ?>Find contacts</button>
      <div class="imp-found small" data-found hidden></div>
    </div>
    <details class="imp-more"><summary>Description, requirements, skills & how to apply</summary>
      <div class="field"><label>Description</label><textarea rows="5" data-f="description"><?= e($d['description'] ?? '') ?></textarea></div>
      <div class="row2"><div class="field"><label>Requirements</label><textarea rows="5" data-f="requirements"><?= e($d['requirements'] ?? '') ?></textarea></div><div class="field"><label>Benefits</label><textarea rows="5" data-f="benefits"><?= e($d['benefits'] ?? '') ?></textarea></div></div>
      <div class="row2"><div class="field"><label>Skills (comma separated)</label><input data-f="skills" value="<?= e($d['skills'] ?? '') ?>"></div><div class="field"><label>Original source link (private, for your records)</label><input data-f="source_url" value="<?= e($d['source_url'] ?? '') ?>"></div></div>
      <div class="row2"><div class="field"><label>How candidates apply</label><select data-f="apply_method"><?= options(['portal' => 'On our site (tracked + forwarded to company email) - recommended', 'url' => 'Send them to the company’s own link', 'email' => 'Show company email to candidates'], $d['apply_method'] ?? 'portal') ?></select></div><div class="field"><label>Company apply link / email</label><input data-f="apply_target" value="<?= e($d['apply_target'] ?? '') ?>"></div></div>
    </details>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php if ($counts['pending'] + $counts['error']): ?>
<div class="form-actions sticky-actions imp-actions">
  <span class="muted small grow" data-sel-count></span>
  <button type="submit" class="btn btn-ghost" data-act="save">Save changes</button>
  <button type="submit" class="btn btn-brand btn-lg" data-act="publish"><?= icon('send', 'i i-sm') ?>Review & publish</button>
</div>
<?php endif; ?>
</form>
<?php admin_end();
