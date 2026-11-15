<?php
if (is_post()) {
    $id = (int) input('id'); $title = mb_substr(input('title'), 0, 190);
    if (input('action') === 'delete') { q('DELETE FROM pages WHERE id=?', [$id]); flash('success', 'Deleted.'); redirect('admin/pages'); }
    $d = ['title' => $title, 'content' => clean_html(input('content')) ?: text_to_html(input('content')), 'meta_description' => mb_substr(input('meta_description'), 0, 255), 'published' => !empty($_POST['published']) ? 1 : 0];
    if ($id) update('pages', $d, 'id=?', [$id]); else $id = insert('pages', $d + ['slug' => unique_slug('pages', input('slug') ?: $title)]);
    log_activity('admin_page_save', 'page', $id, $title);
    flash('success', 'Page saved.'); redirect('admin/pages?edit=' . $id);
}
$edit = isset($_GET['edit']) ? ($_GET['edit'] === 'new' ? ['id' => 0, 'title' => '', 'slug' => '', 'content' => '', 'meta_description' => '', 'published' => 1] : row('SELECT * FROM pages WHERE id=?', [(int) $_GET['edit']])) : null;
$list = rows('SELECT * FROM pages ORDER BY title');
admin_start('Pages');
if ($edit): ?>
<form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
  <div class="row2"><div class="field"><label>Title</label><input name="title" required value="<?= e($edit['title']) ?>"></div><div class="field"><label>URL slug</label><input name="slug" value="<?= e($edit['slug']) ?>" <?= $edit['id'] ? 'disabled' : '' ?> placeholder="auto"></div></div>
  <div class="field"><label>Content (HTML allowed: p, h2, h3, ul, li, b, a)</label><textarea name="content" rows="16" class="mono"><?= e($edit['content']) ?></textarea></div>
  <div class="field"><label>Meta description (SEO)</label><input name="meta_description" maxlength="255" value="<?= e($edit['meta_description']) ?>"></div>
  <label class="switch"><input type="checkbox" name="published" value="1" <?= $edit['published'] ? 'checked' : '' ?>><span></span>Published</label>
  <div class="btn-row"><button class="btn btn-dark">Save page</button><a class="btn btn-ghost" href="<?= url('admin/pages') ?>">Back</a><?php if ($edit['id']): ?><a class="btn btn-ghost" target="_blank" href="<?= url('page/' . $edit['slug']) ?>">View</a><?php endif; ?></div>
</form>
<?php else: ?>
<div class="toolbar"><a class="btn btn-brand btn-sm" href="?edit=new"><?= icon('plus', 'i i-sm') ?>New page</a></div>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Title</th><th>URL</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($list as $p): ?><tr><td><b><?= e($p['title']) ?></b></td><td><a href="<?= url('page/' . $p['slug']) ?>" target="_blank">/page/<?= e($p['slug']) ?></a></td><td><?= $p['published'] ? '<span class="badge b-green">Published</span>' : '<span class="badge b-gray">Hidden</span>' ?></td><td class="right actions"><a class="btn btn-ghost btn-xs" href="?edit=<?= $p['id'] ?>">Edit</a><?= adm_action_form('delete', (int) $p['id'], icon('trash', 'i i-xs'), 'btn btn-danger-ghost btn-xs', 'Delete page?') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php endif; admin_end();
