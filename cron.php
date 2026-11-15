<?php
// Run every 5 minutes from cPanel: /usr/local/bin/php /home/USER/public_html/cron.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only. Use /cron?key=... for web cron.'); }
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/cron_tasks.php';
echo date('[Y-m-d H:i:s] ') . run_cron_tasks();
