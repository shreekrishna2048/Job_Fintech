<?php
require __DIR__ . '/app/bootstrap.php';

$basePath = rtrim(parse_url(base_url(), PHP_URL_PATH) ?? '', '/');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($basePath && str_starts_with($path, $basePath)) $path = substr($path, strlen($basePath));
$path = '/' . trim(rawurldecode($path), '/');
if ($path !== '/' && str_ends_with($_SERVER['REQUEST_URI'] ?? '', '/') && !str_contains($_SERVER['REQUEST_URI'], '?')) redirect(ltrim($path, '/')); // canonical: no trailing slash
$GLOBALS['route_path'] = $path;

$routes = [
    '#^/$#' => 'home',
    '#^/jobs$#' => 'jobs',
    '#^/jobs/in/(?<location>[\w-]+)$#' => 'jobs',
    '#^/jobs/category/(?<category>[\w-]+)$#' => 'jobs',
    '#^/job/(?<slug>[\w-]+)$#' => 'job',
    '#^/job/(?<slug>[\w-]+)/apply$#' => 'apply',
    '#^/companies$#' => 'companies',
    '#^/company/(?<slug>[\w-]+)$#' => 'company',
    '#^/employers$#' => 'employers',
    '#^/contact$#' => 'contact',
    '#^/page/(?<slug>[\w-]+)$#' => 'page',
    '#^/login$#' => 'auth/login',
    '#^/register$#' => 'auth/register',
    '#^/logout$#' => 'auth/logout',
    '#^/forgot-password$#' => 'auth/forgot',
    '#^/reset-password$#' => 'auth/reset',
    '#^/verify-email$#' => 'auth/verify',
    '#^/auth/choose-role$#' => 'auth/choose_role',
    '#^/auth/return-admin$#' => 'auth/return_admin',
    '#^/auth/(?<provider>google|microsoft)$#' => 'auth/oauth',
    '#^/auth/(?<provider>google|microsoft)/callback$#' => 'auth/oauth',
    '#^/notifications$#' => 'notifications',
    '#^/account$#' => 'account',
    '#^/seeker$#' => 'seeker/dashboard',
    '#^/seeker/profile$#' => 'seeker/profile',
    '#^/seeker/applications$#' => 'seeker/applications',
    '#^/seeker/applications/(?<id>\d+)$#' => 'seeker/application',
    '#^/seeker/saved$#' => 'seeker/saved',
    '#^/seeker/alerts$#' => 'seeker/alerts',
    '#^/seeker/following$#' => 'seeker/following',
    '#^/employer$#' => 'employer/dashboard',
    '#^/employer/company$#' => 'employer/company',
    '#^/employer/jobs$#' => 'employer/jobs',
    '#^/employer/jobs/new$#' => 'employer/job_form',
    '#^/employer/jobs/(?<id>\d+)/edit$#' => 'employer/job_form',
    '#^/employer/applicants$#' => 'employer/applicants',
    '#^/employer/applicants/(?<id>\d+)$#' => 'employer/application',
    '#^/employer/candidates$#' => 'employer/candidates',
    '#^/employer/candidates/(?<id>\d+)$#' => 'employer/candidate',
    '#^/file/resume/(?<type>app|profile)/(?<id>\d+)$#' => 'resume_file',
    '#^/api/(?<action>[\w-]+)$#' => 'api',
    '#^/sitemap\.xml$#' => 'sitemap',
    '#^/(?<key>[a-f0-9]{32})\.txt$#' => 'indexnow_key',
    '#^/robots\.txt$#' => 'robots',
    '#^/cron$#' => 'cron_web',
];

$GLOBALS['params'] = [];
try {
    if (preg_match('#^/admin(?:/(?<section>[\w-]+))?(?:/(?<id>\d+|[\w-]+))?$#', $path, $m)) {
        $GLOBALS['params'] = ['section' => $m['section'] ?? 'dashboard', 'id' => $m['id'] ?? null];
        require ROOT . '/pages/admin/index.php';
        exit;
    }
    foreach ($routes as $re => $page) {
        if (preg_match($re, $path, $m)) {
            $GLOBALS['params'] = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            require ROOT . '/pages/' . $page . '.php';
            exit;
        }
    }
    abort(404);
} catch (PDOException $e) {
    error_log('DB: ' . $e->getMessage());
    if (config('debug')) throw $e;
    abort(500, 'A database error occurred. Please try again.');
}

