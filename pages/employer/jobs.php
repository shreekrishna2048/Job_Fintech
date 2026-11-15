<?php
require __DIR__ . '/_guard.php';
$cid = (int) $company['id'];
if (is_post()) {
    csrf_check();
    $j = row('SELECT * FROM jobs WHERE id=? AND company_id=?', [(int) input('id'), $cid]);
    if ($j) {
        switch (input('action')) {
            case 'close': update('jobs', ['status' => 'closed'], 'id=?', [$j['id']]); flash('success', 'Job closed.'); break;
            case 'reopen':
                $st = setting('job_auto_approve', '0') === '1' || $j['published_at'] ? 'active' : 'pending';
                $dl = $j['deadline'] && $j['deadline'] < date('Y-m-d') ? date('Y-m-d', strtotime('+' . (int) setting('job_default_days', '30') . ' days')) : $j['deadline'];
                update('jobs', ['status' => $st, 'deadline' => $dl], 'id=?', [$j['id']]); flash('success', $st === 'active' ? 'Job reopened.' : 'Job sent for approval.'); break;
            case 'extend':
                $base = max(time(), strtotime((string) $j['deadline']));
                update('jobs', ['deadline' => date('Y-m-d', $base + 14 * 86400)], 'id=?', [$j['id']]); flash('success', 'Deadline extended by 14 days.'); break;
            case 'duplicate':
                $copy = array_diff_key($j, array_flip(['id', 'slug', 'views', 'applications_count', 'published_at', 'created_at', 'updated_at', 'featured', 'reject_reason']));
                $copy['title'] = $j['title'] . ' (copy)'; $copy['slug'] = unique_slug('jobs', $copy['title'] . '-' . $company['name']); $copy['status'] = 'draft'; $copy['posted_by'] = $u['id'];
                $nid = insert('jobs', $copy);
                foreach (rows('SELECT question, required FROM job_questions WHERE job_id=?', [$j['id']]) as $qq) insert('job_questions', $qq + ['job_id' => $nid]);
                flash('success', 'Draft copy created.'); redirect("employer/jobs/$nid/edit");
            case 'delete':
                q('DELETE FROM jobs WHERE id=?', [$j['id']]); flash('success', 'Job deleted.'); break;
        }
        log_activity('job_' . input('action'), 'job', (int) $j['id'], $j['title']);
    }
    redirect('employer/jobs' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}
$status = $_GET['status'] ?? '';
$where = 'j.company_id=?'; $p = [$cid];
if ($status === 'expired') $where .= " AND j.status='active' AND j.deadline < CURDATE()";
elseif (in_array($status, ['active', 'pending', 'draft', 'closed', 'rejected'], true)) { $where .= ' AND j.status=?'; $p[] = $status; }
$counts = array_column(rows('SELECT status, COUNT(*) n FROM jobs WHERE company_id=? GROUP BY status', [$cid]), 'n', 'status');
$pg = paginate((int) val("SELECT COUNT(*) FROM jobs j WHERE $where", $p), 20);
$jobs = rows("SELECT j.*, l.name location_name, (SELECT COUNT(*) FROM applications a WHERE a.job_id=j.id AND a.status='applied') new_apps FROM jobs j LEFT JOIN locations l ON l.id=j.location_id WHERE $where ORDER BY j.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
dash_start('My jobs', 'employer/jobs');
?>
<div class="page-head"><h1>My jobs</h1><a class="btn btn-brand" href="<?= url('employer/jobs/new') ?>"><?= icon('plus', 'i i-sm') ?>Post a job</a></div>
<nav class="tabs tabs-scroll"><a href="?" class="<?= $status === '' ? 'on' : '' ?>">All <span class="count"><?= array_sum($counts) ?></span></a><?php foreach (['active' => 'Live', 'pending' => 'Pending', 'draft' => 'Drafts', 'closed' => 'Closed', 'rejected' => 'Rejected'] as $s => $l): ?><a href="?status=<?= $s ?>" class="<?= $status === $s ? 'on' : '' ?>"><?= $l ?> <span class="count"><?= (int) ($counts[$s] ?? 0) ?></span></a><?php endforeach; ?><a href="?status=expired" class="<?= $status === 'expired' ? 'on' : '' ?>">Expired</a></nav>
<?php if ($jobs): ?><div class="emp-jobs">
<?php foreach ($jobs as $j): $expired = $j['status'] === 'active' && $j['deadline'] && $j['deadline'] < date('Y-m-d'); ?>
  <div class="card ej reveal">
    <div class="ej-main">
      <div class="grow"><a href="<?= url('job/' . $j['slug']) ?>" class="ej-title"><?= e($j['title']) ?></a>
        <p class="muted small"><?= e($j['location_name'] ?? '') ?> · <?= e(JOB_TYPES[$j['job_type']]) ?> · Posted <?= e(time_ago($j['created_at'])) ?><?= $j['deadline'] ? ' · Deadline ' . date('d M', strtotime($j['deadline'])) : '' ?></p>
        <?php if ($j['status'] === 'rejected' && $j['reject_reason']): ?><p class="err small">Rejected: <?= e($j['reject_reason']) ?></p><?php endif; ?></div>
      <div><?= $expired ? '<span class="badge b-amber">Expired</span>' : status_badge($j['status']) ?><?php if ($j['featured']): ?> <span class="tag tag-feat">Featured</span><?php endif; ?></div>
    </div>
    <div class="ej-stats"><a href="<?= url('employer/applicants?job=' . $j['id']) ?>"><b><?= (int) $j['applications_count'] ?></b> applicants<?php if ($j['new_apps']): ?> <span class="badge b-brand"><?= $j['new_apps'] ?> new</span><?php endif; ?></a><span><b><?= (int) $j['views'] ?></b> views</span><span><b><?= $j['views'] ? round($j['applications_count'] / $j['views'] * 100, 1) : 0 ?>%</b> apply rate</span></div>
    <div class="ej-actions">
      <a class="btn btn-ghost btn-sm" href="<?= url('employer/applicants?job=' . $j['id'] . '&view=board') ?>"><?= icon('layers', 'i i-sm') ?>Pipeline</a>
      <a class="btn btn-ghost btn-sm" href="<?= url('employer/jobs/' . $j['id'] . '/edit') ?>"><?= icon('edit', 'i i-sm') ?>Edit</a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $j['id'] ?>">
        <?php if ($j['status'] === 'active' && !$expired): ?><button name="action" value="close" class="btn btn-ghost btn-sm">Close</button><button name="action" value="extend" class="btn btn-ghost btn-sm">+14 days</button>
        <?php elseif (in_array($j['status'], ['closed', 'active'], true)): ?><button name="action" value="reopen" class="btn btn-ghost btn-sm">Reopen</button><?php endif; ?>
        <button name="action" value="duplicate" class="btn btn-ghost btn-sm">Duplicate</button>
        <button name="action" value="delete" class="btn btn-danger-ghost btn-sm" onclick="return confirm('Delete this job and all its applications?')"><?= icon('trash', 'i i-sm') ?></button>
      </form>
    </div>
  </div>
<?php endforeach; ?></div><?= page_links($pg) ?>
<?php else: ?><div class="empty card"><?= icon('briefcase', 'i i-xl') ?><h3>No jobs here</h3><a class="btn btn-brand" href="<?= url('employer/jobs/new') ?>">Post a job</a></div><?php endif; ?>
<?php dash_end();
