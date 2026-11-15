<?php
$p = row('SELECT * FROM pages WHERE slug=? AND published=1', [param('slug')]);
if (!$p) abort(404);
layout_start(['title' => $p['title'], 'description' => $p['meta_description'] ?: excerpt($p['content'])]);
?>
<section class="list-hero list-hero-photo" style="<?= bg_img(site_image('img_page_header', 1600), 'linear-gradient(90deg,rgba(250,248,245,.97) 45%,rgba(250,248,245,.7))') ?>"><div class="wrap"><h1><?= e($p['title']) ?></h1></div></section>
<div class="wrap narrow sec"><article class="card prose"><?= clean_html($p['content']) ?></article></div>
<?php layout_end();
