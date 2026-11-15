<?php
// Web cron fallback: https://yoursite/cron?key=CRON_KEY (prefer CLI cron: php cron.php)
if (!hash_equals((string) setting('cron_key', bin2hex(random_bytes(8))), (string) ($_GET['key'] ?? ''))) abort(403);
require ROOT . '/app/cron_tasks.php';
header('Content-Type: text/plain');
echo run_cron_tasks();
