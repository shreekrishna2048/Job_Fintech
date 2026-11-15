<?php
$code = http_response_code() ?: 404;
$msg = $GLOBALS['abort_msg'] ?? '';
$titles = [403 => 'Access denied', 404 => 'Page not found', 419 => 'Session expired', 500 => 'Something went wrong'];
if (str_starts_with($GLOBALS['route_path'] ?? '', '/api/')) json_out(['ok' => false, 'error' => $msg ?: ($titles[$code] ?? 'Error')], $code);
layout_start(['title' => $titles[$code] ?? 'Error', 'noindex' => true]);
?>
<div class="wrap narrow sec"><div class="empty err-page">
  <b class="err-code"><?= $code ?></b>
  <h1><?= e($titles[$code] ?? 'Error') ?></h1>
  <p><?= e($msg ?: ($code === 404 ? 'The page you’re looking for moved or never existed.' : 'Please try again.')) ?></p>
  <div class="btn-row"><a class="btn btn-brand" href="<?= url('jobs') ?>">Browse jobs</a><a class="btn btn-ghost" href="<?= url('') ?>">Home</a></div>
</div></div>
<?php layout_end();
