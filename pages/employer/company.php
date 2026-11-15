<?php
require __DIR__ . '/_guard.php';
$cid = (int) $company['id'];
if (is_post()) {
    csrf_check();
    $act = input('action', 'save');
    try {
        if ($act === 'save') {
            $name = mb_substr(input('name'), 0, 160) ?: $company['name'];
            $url = fn($k) => ($v = input($k)) && filter_var(preg_match('#^https?://#', $v) ? $v : "https://$v", FILTER_VALIDATE_URL) ? (preg_match('#^https?://#', $v) ? $v : "https://$v") : null;
            $d = ['name' => $name, 'tagline' => mb_substr(input('tagline'), 0, 200) ?: null, 'industry_id' => (int) input('industry_id') ?: null,
                'company_size' => isset(COMPANY_SIZES[input('company_size')]) ? input('company_size') : null, 'founded_year' => (int) input('founded_year') ?: null,
                'website' => $url('website'), 'email' => filter_var(input('email'), FILTER_VALIDATE_EMAIL) ?: null, 'phone' => mb_substr(input('phone'), 0, 40) ?: null,
                'location_id' => (int) input('location_id') ?: null, 'address' => mb_substr(input('address'), 0, 255) ?: null,
                'about' => mb_substr(input('about'), 0, 8000) ?: null, 'culture' => mb_substr(input('culture'), 0, 5000) ?: null, 'benefits' => mb_substr(input('benefits'), 0, 5000) ?: null,
                'facebook' => $url('facebook'), 'linkedin' => $url('linkedin'), 'instagram' => $url('instagram')];
            if ($name !== $company['name']) $d['slug'] = unique_slug('companies', $name, $cid);
            if ($l = handle_upload('logo', 'logos', IMG_TYPES, 3)) { delete_upload($company['logo']); $d['logo'] = $l; }
            if ($c = handle_upload('cover', 'covers', IMG_TYPES, 6)) { delete_upload($company['cover']); $d['cover'] = $c; }
            update('companies', $d, 'id=?', [$cid]);
            log_activity('company_update', 'company', $cid);
            flash('success', 'Company profile saved.');
        } elseif ($act === 'gallery') {
            $n = 0; $existing = (int) val('SELECT COUNT(*) FROM company_gallery WHERE company_id=?', [$cid]);
            foreach (array_keys($_FILES['images']['name'] ?? []) as $i) {
                if ($existing + $n >= 30) { flash('info', 'Gallery limit is 30 images.'); break; }
                if ($p = handle_upload('images', 'gallery', IMG_TYPES, 6, $i)) { insert('company_gallery', ['company_id' => $cid, 'image' => $p, 'caption' => mb_substr(input('caption'), 0, 190) ?: null]); $n++; }
            }
            if ($n) { log_activity('gallery_upload', 'company', $cid, "$n images"); flash('success', "$n image(s) added to your portfolio."); }
        } elseif ($act === 'del_image') {
            $g = row('SELECT * FROM company_gallery WHERE id=? AND company_id=?', [(int) input('id'), $cid]);
            if ($g) { delete_upload($g['image']); q('DELETE FROM company_gallery WHERE id=?', [$g['id']]); }
        }
    } catch (RuntimeException $e) { flash('error', $e->getMessage()); }
    redirect('employer/company' . ($act === 'save' ? '' : '#gallery'));
}
$company = employer_company((int) $company['owner_id']);
$gallery = rows('SELECT * FROM company_gallery WHERE company_id=? ORDER BY id DESC', [$cid]);
$aiCtx = "Company: {$company['name']}\nTagline: {$company['tagline']}\nIndustry: " . (val('SELECT name FROM industries WHERE id=?', [$company['industry_id']]) ?? '') . "\nSize: {$company['company_size']}\nFounded: {$company['founded_year']}\nLocation: " . (val('SELECT name FROM locations WHERE id=?', [$company['location_id']]) ?? '') . "\nNotes: {$company['about']}";
dash_start('Company profile', 'employer/company');
?>
<div class="page-head"><div><h1>Company profile</h1><p class="muted">Your public employer page</p></div><a class="btn btn-ghost" href="<?= url('company/' . $company['slug']) ?>" target="_blank"><?= icon('eye', 'i i-sm') ?>View public page</a></div>
<form method="post" enctype="multipart/form-data" class="card form" data-loading><?= csrf_field() ?><input type="hidden" name="action" value="save">
  <div class="brand-edit">
    <label class="cover-edit" style="<?= bg_img(company_cover($company, 1400)) ?>"><input type="file" name="cover" accept="image/*" hidden data-preview-bg=".cover-edit"><span><?= icon('image', 'i i-sm') ?><?= $company['cover'] ? 'Change cover' : 'Auto photo by industry - click to upload your own (1600×400)' ?></span></label>
    <label class="logo-edit"><?= avatar_html($company['logo'], $company['name'], 'logo-box logo-xl') ?><input type="file" name="logo" accept="image/*" hidden data-preview=".logo-edit .logo-box"><span><?= icon('upload', 'i i-xs') ?></span></label>
  </div>
  <div class="row2"><div class="field"><label>Company name</label><input name="name" required value="<?= e($company['name']) ?>"></div><div class="field"><label>Tagline</label><input name="tagline" maxlength="200" placeholder="One line about what you do" value="<?= e($company['tagline']) ?>"></div></div>
  <div class="row3"><div class="field"><label>Industry</label><select name="industry_id"><?= options(industries(), $company['industry_id'], 'Select') ?></select></div><div class="field"><label>Company size</label><select name="company_size"><?= options(COMPANY_SIZES, $company['company_size'], 'Select') ?></select></div><div class="field"><label>Founded</label><input type="number" name="founded_year" min="1800" max="<?= date('Y') ?>" value="<?= e($company['founded_year']) ?>"></div></div>
  <div class="field"><div class="label-row"><label for="about">About the company</label><?= ai_button('company_about', '#about', 'Write with AI', ['context' => $aiCtx]) ?></div><textarea id="about" name="about" rows="6"><?= e($company['about']) ?></textarea></div>
  <div class="row2"><div class="field"><div class="label-row"><label for="culture">Culture</label><?= ai_button('improve', '#culture', 'Improve') ?></div><textarea id="culture" name="culture" rows="4" placeholder="How you work, values, team"><?= e($company['culture']) ?></textarea></div>
  <div class="field"><div class="label-row"><label for="benefits">Perks & benefits</label><?= ai_button('improve', '#benefits', 'Improve') ?></div><textarea id="benefits" name="benefits" rows="4" placeholder="- Festival bonus&#10;- Health insurance"><?= e($company['benefits']) ?></textarea></div></div>
  <h3>Contact & location</h3>
  <div class="row3"><div class="field"><label>Website</label><input name="website" value="<?= e($company['website']) ?>" placeholder="example.com.np"></div><div class="field"><label>HR email</label><input type="email" name="email" value="<?= e($company['email']) ?>"></div><div class="field"><label>Phone</label><input name="phone" value="<?= e($company['phone']) ?>"></div></div>
  <div class="row2"><div class="field"><label>City</label><select name="location_id"><?= options(locations(), $company['location_id'], 'Select') ?></select></div><div class="field"><label>Address</label><input name="address" value="<?= e($company['address']) ?>"></div></div>
  <h3>Social links</h3>
  <div class="row3"><div class="field"><label>Facebook</label><input name="facebook" value="<?= e($company['facebook']) ?>"></div><div class="field"><label>LinkedIn</label><input name="linkedin" value="<?= e($company['linkedin']) ?>"></div><div class="field"><label>Instagram</label><input name="instagram" value="<?= e($company['instagram']) ?>"></div></div>
  <button class="btn btn-dark btn-lg">Save company profile</button>
</form>
<div class="card" id="gallery"><h2>Portfolio & office photos</h2><p class="muted small">Show your team, office, events and work. Up to 30 images.</p>
  <form method="post" enctype="multipart/form-data" class="form gallery-form" data-loading><?= csrf_field() ?><input type="hidden" name="action" value="gallery">
    <div class="upload-zone" data-upload-zone><input type="file" name="images[]" accept="image/*" multiple id="gin"><label for="gin"><?= icon('image') ?><b>Choose photos</b> (multiple allowed)<small>JPG, PNG, WEBP · max 6 MB each</small></label></div>
    <div class="row2"><input name="caption" placeholder="Caption (optional)"><button class="btn btn-dark">Upload</button></div>
  </form>
  <?php if ($gallery): ?><div class="gallery gallery-edit"><?php foreach ($gallery as $g): ?><figure><img src="<?= e(upload_url($g['image'])) ?>" alt="" loading="lazy"><?php if ($g['caption']): ?><figcaption><?= e($g['caption']) ?></figcaption><?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="del_image"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="icon-btn" aria-label="Delete" onclick="return confirm('Delete image?')"><?= icon('trash', 'i i-sm') ?></button></form></figure><?php endforeach; ?></div><?php endif; ?>
</div>
<?php dash_end();
