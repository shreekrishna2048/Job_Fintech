<?php
if (is_post()) {
    if (input('action') === 'delete') q('DELETE FROM contact_messages WHERE id=?', [(int) input('id')]);
    else update('contact_messages', ['is_read' => 1], 'id=?', [(int) input('id')]);
    back();
}
$pg = paginate((int) val('SELECT COUNT(*) FROM contact_messages'), 30);
$list = rows("SELECT * FROM contact_messages ORDER BY is_read, id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
admin_start('Contact messages');
?>
<?php foreach ($list as $m): ?><div class="card msg <?= $m['is_read'] ? '' : 'unread' ?>"><div class="label-row"><div><b><?= e($m['name']) ?></b> · <a href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . $m['subject']) ?>"><?= e($m['email']) ?></a><br><small class="muted"><?= e($m['subject']) ?> · <?= e(time_ago($m['created_at'])) ?> · <?= e($m['ip']) ?></small></div>
<div class="actions"><?php if (!$m['is_read']): ?><?= adm_action_form('read', (int) $m['id'], 'Mark read') ?><?php endif; ?><?= adm_action_form('delete', (int) $m['id'], icon('trash', 'i i-xs'), 'btn btn-danger-ghost btn-xs', 'Delete?') ?></div></div><p><?= nl2br(e($m['message'])) ?></p></div><?php endforeach; ?>
<?php if (!$list): ?><div class="empty card"><p>No messages yet.</p></div><?php endif; ?>
<?= page_links($pg) ?>
<?php admin_end();
