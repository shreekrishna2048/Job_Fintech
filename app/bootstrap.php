<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP_START', microtime(true));
define('APP_VERSION', '7');

if (!is_file(ROOT . '/config.php')) {
    if (PHP_SAPI !== 'cli') { header('Location: install.php'); exit; }
    exit("Not installed. Run install.php\n");
}
$GLOBALS['config'] = require ROOT . '/config.php';

date_default_timezone_set($GLOBALS['config']['timezone'] ?? 'Asia/Kathmandu');
error_reporting(E_ALL);
ini_set('display_errors', !empty($GLOBALS['config']['debug']) ? '1' : '0');
ini_set('log_errors', '1');

require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/ai.php';
require __DIR__ . '/oauth.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/jobs.php';
require __DIR__ . '/images.php';
require __DIR__ . '/seo.php';
require __DIR__ . '/importer.php';
require __DIR__ . '/finder.php';
run_migrations();

if (PHP_SAPI !== 'cli') {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('kksess');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    remember_me_restore();
}

// Send queued emails after the response is flushed so users never wait on SMTP
register_shutdown_function(function () {
    if (empty($GLOBALS['mail_pending']) && empty($GLOBALS['seo_queue'])) return;
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    @set_time_limit(120);
    try { seo_flush(); } catch (Throwable $e) { error_log('seo: ' . $e->getMessage()); }
    if (!empty($GLOBALS['mail_pending'])) try { process_email_queue(10); } catch (Throwable $e) { error_log('mail queue: ' . $e->getMessage()); }
});
