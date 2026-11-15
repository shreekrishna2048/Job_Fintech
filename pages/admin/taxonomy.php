<?php
$tables = ['categories' => ['Categories', true], 'locations' => ['Cities / locations', false], 'industries' => ['Industries', false]];
$t = $_GET['t'] ?? 'categories'; if (!isset($tables[$t])) $t = 'categories';
if (is_post()) {
    $tb = input('table'); if (!isset($tables[$tb])) back();
    $act = input('action'); $id = (int) input('id'); $name = mb_substr(input('name'), 0, 100);
    if ($act === 'save' && $name) {
        $d = ['name' => $name];
        if ($tb === 'categories') $d['icon'] = mb_substr(input('icon'), 0, 10) ?: null;
        if ($tb === 'locations') $d['province'] = mb_substr(input('province'), 0, 60) ?: null;
        if ($tb !== 'industries') $d['sort'] = (int) input('sort');
        if ($id) update($tb, $d, 'id=?', [$id]); else insert($tb, $d + ['slug' => unique_slug($tb, $name)]);
        flash('success', 'Saved.');
    } elseif ($act === 'delete' && $id) {
        $col = ['categories' => 'category_id', 'locations' => 'location_id', 'industries' => null][$tb];
        if ($col && val("SELECT 1 FROM jobs WHERE $col=? LIMIT 1", [$id])) flash('error', 'In use by jobs - can’t delete. Rename it instead.');
        else { q("DELETE FROM $tb WHERE id=?", [$id]); flash('success', 'Deleted.'); }
    }
    log_activity('admin_taxonomy_' . $act, $tb, $id ?: null, $name);
    redirect("admin/taxonomy?t=$tb");
}
$col = ['categories' => 'category_id', 'locations' => 'location_id', 'industries' => 'industry_id'][$t];
$from = $t === 'industries' ? 'companies' : 'jobs';
$list = rows("SELECT x.*, (SELECT COUNT(*) FROM $from WHERE $col=x.id) used FROM $t x ORDER BY " . ($t === 'industries' ? 'name' : 'sort, name'));
admin_start('Categories & cities');
?>
<nav class="tabs"><?php foreach ($tables as $k => [$l]): ?><a href="?t=<?= $k ?>" class="<?= $t === $k ? 'on' : '' ?>"><?= $l ?></a><?php endforeach; ?></nav>
<form method="post" class="card form toolbar"><?= csrf_field() ?><input type="hidden" name="table" value="<?= $t ?>"><input type="hidden" name="action" value="save">
  <input name="name" placeholder="New <?= rtrim(strtolower($tables[$t][0]), 's') ?> name" required><?php if ($t === 'categories'): ?><input name="icon" placeholder="Emoji" style="max-width:90px"><?php endif; ?><?php if ($t === 'locations'): ?><input name="province" placeholder="Province"><?php endif; ?><?php if ($t !== 'industries'): ?><input name="sort" type="number" placeholder="Order" style="max-width:90px"><?php endif; ?><button class="btn btn-brand btn-sm">Add</button></form>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Slug</th><th>Used</th><th class="right">Edit</th></tr></thead><tbody>
<?php foreach ($list as $r): ?><tr><td colspan="4"><form method="post" class="tax-row"><?= csrf_field() ?><input type="hidden" name="table" value="<?= $t ?>"><input type="hidden" name="id" value="<?= $r['id'] ?>">
  <?php if ($t === 'categories'): ?><input name="icon" value="<?= e($r['icon']) ?>" class="w-icon"><?php endif; ?><input name="name" value="<?= e($r['name']) ?>"><?php if ($t === 'locations'): ?><input name="province" value="<?= e($r['province']) ?>" placeholder="Province"><?php endif; ?><?php if ($t !== 'industries'): ?><input name="sort" type="number" value="<?= (int) $r['sort'] ?>" class="w-num"><?php endif; ?>
  <code class="small muted"><?= e($r['slug']) ?></code><span class="muted small"><?= $r['used'] ?> used</span>
  <button name="action" value="save" class="btn btn-ghost btn-xs">Save</button><button name="action" value="delete" class="btn btn-danger-ghost btn-xs" onclick="return confirm('Delete?')"><?= icon('trash', 'i i-xs') ?></button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php admin_end();
