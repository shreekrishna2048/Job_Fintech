<?php
if (is_post()) {
    $act = input('action');
    try {
        if ($act === 'localize') {
            @set_time_limit(300);
            $r = localize_all_images();
            flash($r['fail'] ? 'info' : 'success', "{$r['ok']} images saved to your server" . ($r['fail'] ? ", {$r['fail']} could not be downloaded (your host may block outgoing requests - they still load from the web)." : '.'));
        } elseif ($act === 'slot' && isset(SITE_IMAGE_SLOTS[input('key')])) {
            $k = input('key'); $old = setting($k, '');
            if (!empty($_POST['reset'])) $new = '';
            elseif ($up = handle_upload('file', 'site', IMG_TYPES, 8)) $new = $up;
            elseif (($u = input('url')) !== '') { if (!filter_var($u, FILTER_VALIDATE_URL) || !preg_match('#^https://#', $u)) throw new RuntimeException('Enter a valid https:// image link.'); $new = !empty($_POST['local']) ? (localize_remote_image($u) ?? $u) : $u; }
            else throw new RuntimeException('Upload a file or paste an image link.');
            if ($old && !preg_match('#^https?://#', $old) && $old !== $new) delete_upload($old);
            save_setting($k, $new);
            flash('success', SITE_IMAGE_SLOTS[$k][0] . ($new === '' ? ' reset to the default photo.' : ' updated.'));
        } elseif ($act === 'row' && in_array(input('table'), ['categories', 'locations'], true)) {
            $t = input('table'); $r = row("SELECT * FROM $t WHERE id=?", [(int) input('id')]);
            if (!$r) throw new RuntimeException('Not found.');
            if (!empty($_POST['reset'])) $new = null;
            elseif ($up = handle_upload('file', 'site', IMG_TYPES, 6)) $new = $up;
            elseif (($u = input('url')) !== '') { if (!filter_var($u, FILTER_VALIDATE_URL) || !preg_match('#^https://#', $u)) throw new RuntimeException('Enter a valid https:// image link.'); $new = $u; }
            else throw new RuntimeException('Upload a file or paste an image link.');
            if ($r['image'] && !preg_match('#^https?://#', $r['image']) && $r['image'] !== $new) delete_upload($r['image']);
            q("UPDATE $t SET image=? WHERE id=?", [$new, $r['id']]);
            flash('success', $r['name'] . ' image ' . ($new ? 'updated.' : 'reset to default.'));
        }
        log_activity('admin_images_' . $act, null, null, input('key') ?: input('table') . '#' . input('id'));
    } catch (RuntimeException $e) { flash('error', $e->getMessage()); }
    redirect('admin/images' . (input('tab') ? '?tab=' . urlencode(input('tab')) : ''));
}
$tab = $_GET['tab'] ?? 'site';
$src = fn($v) => !$v ? '<span class="badge b-gray">Default photo</span>' : (preg_match('#^https?://#', $v) ? '<span class="badge b-blue">Web link</span>' : '<span class="badge b-green">On your server</span>');
$imgForm = function (array $hidden, string $preview, ?string $cur, string $tab) use ($src) {
    ob_start(); ?>
  <div class="img-card card">
    <a class="img-prev" href="<?= e($preview) ?>" target="_blank"><img src="<?= e($preview) ?>" alt="" loading="lazy" onerror="this.closest('.img-prev').classList.add('broken')"></a>
    <form method="post" enctype="multipart/form-data" class="img-form"><?= csrf_field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>">
      <?php foreach ($hidden as $k => $v): if ($k === '__title') continue; ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
      <div class="img-meta"><?= $hidden['__title'] ?? '' ?><?= $src($cur) ?></div>
      <label class="btn btn-ghost btn-xs"><?= icon('upload', 'i i-xs') ?>Upload<input type="file" name="file" accept="image/*" hidden onchange="this.form.submit()"></label>
      <div class="img-url"><input name="url" type="url" placeholder="…or paste https:// image link"><button class="btn btn-dark btn-xs">Use</button></div>
      <?php if ($cur): ?><button name="reset" value="1" class="linklike small" onclick="return confirm('Reset to default photo?')">Reset to default</button><?php endif; ?>
    </form>
  </div>
<?php return ob_get_clean(); };
admin_start('Site images');
?>
<div class="card toolbar" style="justify-content:space-between">
  <p class="muted small" style="margin:0;flex:1 1 300px">Every empty image uses a matching free photo (Unsplash License - free for commercial use). Replace any image by uploading or pasting a link. Company logos and user photos are never auto-filled - they show neat initials until the company/user uploads their own.</p>
  <form method="post" onsubmit="this.querySelector('button').classList.add('is-loading')"><?= csrf_field() ?><input type="hidden" name="action" value="localize"><button class="btn btn-brand btn-sm"><?= icon('download', 'i i-sm') ?>Save all photos to my server</button></form>
</div>
<?php if ($t = setting('images_localized')): ?><p class="muted small">Last saved to server: <?= e($t) ?></p><?php endif; ?>
<nav class="tabs"><a href="?tab=site" class="<?= $tab === 'site' ? 'on' : '' ?>">Page images</a><a href="?tab=categories" class="<?= $tab === 'categories' ? 'on' : '' ?>">Category images</a><a href="?tab=locations" class="<?= $tab === 'locations' ? 'on' : '' ?>">City images</a></nav>
<div class="img-grid">
<?php if ($tab === 'site'):
    foreach (SITE_IMAGE_SLOTS as $k => [$label, $help]) echo $imgForm(['action' => 'slot', 'key' => $k, '__title' => '<b>' . e($label) . '</b><small>' . e($help) . '</small>'], site_image($k, 600), setting($k, '') ?: null, 'site');
elseif ($tab === 'categories'):
    foreach (rows('SELECT * FROM categories ORDER BY sort, name') as $c) echo $imgForm(['action' => 'row', 'table' => 'categories', 'id' => $c['id'], '__title' => '<b>' . e(($c['icon'] ? $c['icon'] . ' ' : '') . $c['name']) . '</b>'], cat_image($c, 500), $c['image'] ?? null, 'categories');
else:
    foreach (rows('SELECT * FROM locations ORDER BY sort, name') as $l) echo $imgForm(['action' => 'row', 'table' => 'locations', 'id' => $l['id'], '__title' => '<b>' . e($l['name']) . '</b><small>' . e($l['province'] ?? '') . '</small>'], loc_image($l, 500), $l['image'] ?? null, 'locations');
endif; ?>
</div>
<?php admin_end();
