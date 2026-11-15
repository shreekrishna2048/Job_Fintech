<?php
/* ---------- Config / DB ---------- */
function param(string $k, $d = null) { return $GLOBALS['params'][$k] ?? $d; }
function config(string $key, $default = null) { return $GLOBALS['config'][$key] ?? $default; }

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c = config('db');
        $pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function val(string $sql, array $p = []) { $v = q($sql, $p)->fetchColumn(); return $v === false ? null : $v; }
function insert(string $table, array $data): int {
    $cols = array_keys($data);
    $sql = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
    q($sql, array_values($data));
    return (int) db()->lastInsertId();
}
function update(string $table, array $data, string $where, array $wp = []): int {
    $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($data)));
    return q("UPDATE `$table` SET $set WHERE $where", array_merge(array_values($data), $wp))->rowCount();
}

function setting(string $key, $default = '') {
    static $cache;
    if ($cache === null) {
        $cache = [];
        try { foreach (rows('SELECT `key`,`value` FROM settings') as $r) $cache[$r['key']] = $r['value']; } catch (Throwable $e) {}
    }
    return (isset($cache[$key]) && $cache[$key] !== '') ? $cache[$key] : $default;
}
function save_setting(string $key, $value): void {
    q('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)', [$key, (string) $value]);
}

/* ---------- Output / URLs ---------- */
function e($v): string { return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_url(): string { return rtrim(config('base_url', ''), '/'); }
function url(string $path = ''): string { return base_url() . '/' . ltrim($path, '/'); }
function asset(string $path): string {
    $f = ROOT . '/assets/' . $path;
    return url('assets/' . $path) . (is_file($f) ? '?v=' . filemtime($f) : '');
}
function upload_url(?string $path, string $fallback = ''): string { return $path ? url('uploads/' . $path) : $fallback; }
function current_path(): string { return $GLOBALS['route_path'] ?? '/'; }
function redirect(string $to): void { header('Location: ' . (preg_match('#^https?://#', $to) ? $to : url($to))); exit; }
function back(): void { $r = $_SERVER['HTTP_REFERER'] ?? ''; redirect($r && str_starts_with($r, base_url()) ? $r : '/'); }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function input(string $k, $d = '') { $v = $_POST[$k] ?? $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $v; }
function json_out($data, int $code = 200): void { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }

/* ---------- Flash / old input ---------- */
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function take_flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function keep_old(): void { $_SESSION['old'] = $_POST; unset($_SESSION['old']['password'], $_SESSION['old']['password_confirm'], $_SESSION['old']['_csrf']); }
function old(string $k, $d = '') { return $_SESSION['old'][$k] ?? $d; }
function clear_old(): void { unset($_SESSION['old']); }

/* ---------- CSRF ---------- */
function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        if (str_starts_with(current_path(), '/api/')) json_out(['ok' => false, 'error' => 'Session expired. Refresh the page.'], 419);
        flash('error', 'Your session expired. Please try again.');
        back();
    }
}

/* ---------- Strings / format ---------- */
function slugify(string $s): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s), '-'));
    return $s ?: 'item';
}
function unique_slug(string $table, string $base, ?int $ignoreId = null): string {
    $slug = slugify($base); $try = $slug; $i = 2;
    while (val("SELECT id FROM `$table` WHERE slug=?" . ($ignoreId ? ' AND id<>' . (int) $ignoreId : ''), [$try])) $try = $slug . '-' . $i++;
    return $try;
}
function excerpt(?string $html, int $len = 160): string {
    $t = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)));
    return mb_strlen($t) > $len ? rtrim(mb_substr($t, 0, $len)) . '…' : $t;
}
function time_ago(?string $dt): string {
    if (!$dt) return '';
    $d = time() - strtotime($dt);
    if ($d < 60) return 'just now';
    foreach ([31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'] as $s => $n)
        if ($d >= $s) { $c = intdiv($d, $s); return "$c $n" . ($c > 1 ? 's' : '') . ' ago'; }
    return '';
}
function days_left(?string $date): ?int { return $date ? (int) floor((strtotime($date . ' 23:59:59') - time()) / 86400) : null; }
function money(?int $n): string {
    if (!$n) return '';
    if ($n >= 100000) return rtrim(rtrim(number_format($n / 100000, 1), '0'), '.') . ' L';
    if ($n >= 1000) return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
    return (string) $n;
}
function salary_text(array $j): string {
    if (!empty($j['hide_salary'])) return 'Confidential';
    $c = !empty($j['currency']) ? $j['currency'] : setting('currency', 'NPR'); $p = ['month' => '/mo', 'year' => '/yr', 'hour' => '/hr'][$j['salary_period'] ?? 'month'] ?? '';
    $min = (int) ($j['salary_min'] ?? 0); $max = (int) ($j['salary_max'] ?? 0);
    if ($min && $max) $s = "$c " . money($min) . '–' . money($max) . $p;
    elseif ($min) $s = "$c " . money($min) . "+$p";
    elseif ($max) $s = "Up to $c " . money($max) . $p;
    else return 'Negotiable';
    return $s . (!empty($j['salary_negotiable']) ? ' · Neg.' : '');
}
function label(string $v): string { return ucwords(str_replace(['-', '_'], ' ', $v)); }
function skills_arr(?string $s): array {
    return array_values(array_unique(array_filter(array_map(fn($x) => trim(mb_strtolower($x)), preg_split('/[,;\n]+/', (string) $s)))));
}
function initials(string $name): string {
    $p = preg_split('/\s+/', trim($name)); $i = mb_substr($p[0] ?? '', 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : '');
    return mb_strtoupper($i ?: '?');
}
function avatar_html(?string $img, string $name, string $cls = 'av'): string {
    if ($img) return '<img class="' . $cls . '" src="' . e(upload_url($img)) . '" alt="' . e($name) . '" loading="lazy">';
    $hue = crc32($name) % 360;
    return '<span class="' . $cls . ' av-txt" style="--h:' . $hue . '">' . e(initials($name)) . '</span>';
}
/** Allow a safe subset of HTML from rich text inputs */
function clean_html(?string $html): string {
    $html = strip_tags((string) $html, '<p><br><b><strong><i><em><u><ul><ol><li><h2><h3><h4><blockquote><a>');
    $html = preg_replace('/\s(on\w+|style|class|id)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/href\s*=\s*(["\']?)\s*(javascript|data|vbscript):[^"\'>\s]*/i', 'href=$1#', $html);
    return trim($html);
}
/** Plain text (from textarea or AI) -> paragraphs / bullet lists */
function text_to_html(?string $t): string {
    $t = trim((string) $t);
    if ($t === '') return '';
    if (preg_match('/<(p|ul|ol|li|br|h[2-4])\b/i', $t)) return clean_html($t);
    $out = ''; $inList = false;
    foreach (preg_split('/\R/', $t) as $line) {
        $line = trim($line);
        if (preg_match('/^([-*•]|\d+[.)])\s+(.*)$/u', $line, $m)) {
            if (!$inList) { $out .= '<ul>'; $inList = true; }
            $out .= '<li>' . e($m[2]) . '</li>'; continue;
        }
        if ($inList) { $out .= '</ul>'; $inList = false; }
        if ($line === '') continue;
        if (preg_match('/^#{1,3}\s*(.+)$/', $line, $m) || preg_match('/^\*\*(.+)\*\*:?$/', $line, $m)) $out .= '<h3>' . e($m[1]) . '</h3>';
        else $out .= '<p>' . preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', e($line)) . '</p>';
    }
    if ($inList) $out .= '</ul>';
    return $out;
}
function html_to_text(?string $h): string {
    $h = preg_replace(['#<li[^>]*>#i', '#</(p|h[2-4]|li|ul|ol)>#i', '#<br\s*/?>#i'], ["- ", "\n", "\n"], (string) $h);
    return trim(html_entity_decode(strip_tags(preg_replace("/\n{3,}/", "\n\n", $h)), ENT_QUOTES, 'UTF-8'));
}

/* ---------- Request info ---------- */
function client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k)
        if (!empty($_SERVER[$k]) && filter_var($_SERVER[$k], FILTER_VALIDATE_IP)) return $_SERVER[$k];
    return '0.0.0.0';
}
function user_agent(): string { return mb_substr($_SERVER['HTTP_USER_AGENT'] ?? 'cli', 0, 255); }
function device_name(?string $ua = null): string {
    $ua = $ua ?? user_agent();
    $os = preg_match('/iPhone|iPad/i', $ua) ? 'iOS' : (preg_match('/Android/i', $ua) ? 'Android' : (preg_match('/Windows/i', $ua) ? 'Windows' : (preg_match('/Mac OS/i', $ua) ? 'macOS' : (preg_match('/Linux/i', $ua) ? 'Linux' : 'Other'))));
    $br = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/OPR|Opera/', $ua) ? 'Opera' : (preg_match('/Chrome/', $ua) ? 'Chrome' : (preg_match('/Firefox/', $ua) ? 'Firefox' : (preg_match('/Safari/', $ua) ? 'Safari' : 'Browser'))));
    return "$br on $os";
}
function is_bot(): bool { return (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview/i', user_agent()); }

/* ---------- Admin permissions ---------- */
const ADMIN_PERMS = [
    'dashboard' => 'Dashboard', 'import' => 'Bulk import jobs', 'finder' => 'Job finder', 'jobs' => 'Jobs', 'companies' => 'Companies', 'users' => 'Users', 'applications' => 'Applications',
    'reports' => 'Reports', 'messages' => 'Messages', 'broadcast' => 'Broadcast', 'emails' => 'Email center', 'taxonomy' => 'Categories & cities',
    'images' => 'Site images', 'pages' => 'Pages', 'login' => 'Login & sign-up (Google/Microsoft)', 'ai' => 'AI & integrations', 'logs' => 'Logs & analytics', 'settings' => 'Settings', 'impersonate' => 'Act as users (edit their profiles/jobs)',
];
/** Super admin = admin with admin_perms NULL. Staff admins have a comma list of sections. */
function is_super_admin(?array $u = null): bool { $u = $u ?? current_user(); return ($u['role'] ?? '') === 'admin' && ($u['admin_perms'] ?? null) === null; }
function admin_can(string $perm, ?array $u = null): bool {
    $u = $u ?? current_user();
    if (($u['role'] ?? '') !== 'admin') return false;
    if (($u['admin_perms'] ?? null) === null) return true;
    return in_array($perm, explode(',', $u['admin_perms']), true);
}
function impersonator(): ?int { return isset($_SESSION['impersonator']) ? (int) $_SESSION['impersonator'] : null; }

/* ---------- Logs & notifications ---------- */
function log_activity(string $action, ?string $entity = null, ?int $entityId = null, ?string $desc = null, ?int $userId = null): void {
    if ($imp = impersonator()) $desc = trim('[by admin #' . $imp . '] ' . (string) $desc);
    try {
        insert('activity_logs', [
            'user_id' => $userId ?? (current_user()['id'] ?? null), 'action' => $action, 'entity' => $entity, 'entity_id' => $entityId,
            'description' => $desc ? mb_substr($desc, 0, 500) : null, 'ip' => client_ip(), 'user_agent' => user_agent(),
            'url' => mb_substr($_SERVER['REQUEST_URI'] ?? 'cli', 0, 255),
        ]);
    } catch (Throwable $e) { error_log($e->getMessage()); }
}
function notify(int $userId, string $type, string $title, string $body = '', string $link = ''): void {
    insert('notifications', ['user_id' => $userId, 'type' => $type, 'title' => mb_substr($title, 0, 190), 'body' => mb_substr($body, 0, 500), 'link' => $link]);
}
function notify_admins(string $type, string $title, string $body = '', string $link = ''): void {
    foreach (rows("SELECT id FROM users WHERE role='admin' AND status='active'") as $a) notify((int) $a['id'], $type, $title, $body, $link);
}

/* ---------- Uploads ---------- */
const IMG_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
const DOC_TYPES = ['application/pdf' => 'pdf', 'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'];

/** Detect real file type from its content (uses fileinfo when available, otherwise file signatures). */
function detect_mime(string $head, string $name = ''): string {
    if (class_exists('finfo')) { $m = (new finfo(FILEINFO_MIME_TYPE))->buffer($head); if ($m && $m !== 'application/octet-stream') return $m; }
    if (str_starts_with($head, "\xFF\xD8\xFF")) return 'image/jpeg';
    if (str_starts_with($head, "\x89PNG\r\n\x1A\n")) return 'image/png';
    if (str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a')) return 'image/gif';
    if (str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP') return 'image/webp';
    if (str_starts_with($head, '%PDF-')) return 'application/pdf';
    if (str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) return 'application/msword';
    if (str_starts_with($head, "PK\x03\x04") && (str_contains($head, 'word/') || strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'docx')) return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    return 'application/octet-stream';
}

/** @return string|null relative path under uploads/, throws RuntimeException on invalid file */
function handle_upload(string $field, string $dir, array $types, int $maxMb = 5, ?int $idx = null): ?string {
    $f = $_FILES[$field] ?? null;
    if (!$f) return null;
    if ($idx !== null) $f = ['name' => $f['name'][$idx], 'tmp_name' => $f['tmp_name'][$idx], 'error' => $f['error'][$idx], 'size' => $f['size'][$idx]];
    if ($f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed (code ' . $f['error'] . ').');
    if ($f['size'] > $maxMb * 1048576) throw new RuntimeException("File too large. Max {$maxMb} MB.");
    $mime = detect_mime(file_get_contents($f['tmp_name'], false, null, 0, 4096) ?: '', $f['name'] ?? '');
    if (!isset($types[$mime])) throw new RuntimeException('File type not allowed.');
    $name = date('Ym') . '/' . bin2hex(random_bytes(12)) . '.' . $types[$mime];
    $target = ROOT . "/uploads/$dir/$name";
    if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
    if (isset(IMG_TYPES[$mime]) && $mime !== 'image/gif') resize_image($f['tmp_name'], $target, $mime, $dir === 'covers' ? 1800 : 1200);
    elseif (!move_uploaded_file($f['tmp_name'], $target)) throw new RuntimeException('Could not save file.');
    return "$dir/$name";
}
function resize_image(string $src, string $dst, string $mime, int $max): void {
    if (!function_exists('imagecreatefromstring')) { move_uploaded_file($src, $dst); return; }
    $img = @imagecreatefromstring(file_get_contents($src));
    if (!$img) throw new RuntimeException('Invalid image.');
    [$w, $h] = [imagesx($img), imagesy($img)];
    if ($w > $max) { $nh = (int) round($h * $max / $w); $n = imagecreatetruecolor($max, $nh); imagealphablending($n, false); imagesavealpha($n, true); imagecopyresampled($n, $img, 0, 0, 0, 0, $max, $nh, $w, $h); imagedestroy($img); $img = $n; }
    match ($mime) { 'image/png' => imagepng($img, $dst, 7), 'image/webp' => imagewebp($img, $dst, 82), default => imagejpeg($img, $dst, 85) };
    imagedestroy($img);
}
function delete_upload(?string $path): void { if ($path && !str_contains($path, '..') && is_file(ROOT . '/uploads/' . $path)) @unlink(ROOT . '/uploads/' . $path); }

/* ---------- Pagination ---------- */
function paginate(int $total, int $perPage = 20): array {
    $page = max(1, (int) ($_GET['page'] ?? 1)); $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    return ['page' => $page, 'pages' => $pages, 'total' => $total, 'per' => $perPage, 'offset' => ($page - 1) * $perPage];
}
function page_links(array $p): string {
    if ($p['pages'] <= 1) return '';
    $qs = $_GET; $h = '<nav class="pager" aria-label="Pagination">';
    $link = function ($n, $txt, $cls = '') use ($qs) { $qs['page'] = $n; return '<a class="' . $cls . '" href="?' . e(http_build_query($qs)) . '">' . $txt . '</a>'; };
    if ($p['page'] > 1) $h .= $link($p['page'] - 1, '‹ Prev', 'pg-nav');
    for ($i = max(1, $p['page'] - 2); $i <= min($p['pages'], $p['page'] + 2); $i++) $h .= $i === $p['page'] ? '<span class="cur">' . $i . '</span>' : $link($i, (string) $i);
    if ($p['page'] < $p['pages']) $h .= $link($p['page'] + 1, 'Next ›', 'pg-nav');
    return $h . '</nav>';
}

/* ---------- Lookups ---------- */
function categories(): array { static $c; return $c ??= rows('SELECT * FROM categories ORDER BY sort, name'); }
const COUNTRIES = [
    'Nepal' => ['code' => 'NP', 'currency' => 'NPR', 'search' => 'nepal', 'flag' => '🇳🇵'],
    'United Arab Emirates' => ['code' => 'AE', 'currency' => 'AED', 'search' => 'united arab emirates', 'flag' => '🇦🇪'],
];
/** Countries switched on in Admin › Settings (default: Nepal) */
function countries_enabled(): array { return array_values(array_intersect(array_keys(COUNTRIES), array_map('trim', explode(',', setting('countries', 'Nepal'))))) ?: ['Nepal']; }
function locations(?string $country = null): array {
    static $all;
    $all ??= rows('SELECT * FROM locations ORDER BY sort, name');
    $on = $country ? [$country] : countries_enabled();
    return array_values(array_filter($all, fn($l) => in_array($l['country'] ?? 'Nepal', $on, true)));
}
function country_of_location($locId): string { foreach (locations(null) as $l) if ((string) $l['id'] === (string) $locId) return $l['country']; $c = $locId ? val('SELECT country FROM locations WHERE id=?', [$locId]) : null; return $c ?: 'Nepal'; }
function industries(): array { static $i; return $i ??= rows('SELECT * FROM industries ORDER BY name'); }
function options(array $items, $selected, string $placeholder = ''): string {
    $h = $placeholder !== '' ? '<option value="">' . e($placeholder) . '</option>' : '';
    foreach ($items as $k => $v) {
        [$id, $name] = is_array($v) ? [$v['id'] ?? $v['slug'], $v['name']] : [$k, $v];
        $h .= '<option value="' . e($id) . '"' . ((string) $id === (string) $selected ? ' selected' : '') . '>' . e($name) . '</option>';
    }
    return $h;
}
const JOB_TYPES = ['full-time' => 'Full-time', 'part-time' => 'Part-time', 'contract' => 'Contract', 'internship' => 'Internship', 'freelance' => 'Freelance', 'temporary' => 'Temporary'];
const WORK_MODES = ['onsite' => 'On-site', 'remote' => 'Remote', 'hybrid' => 'Hybrid'];
const JOB_LEVELS = ['entry' => 'Entry level', 'mid' => 'Mid level', 'senior' => 'Senior level', 'lead' => 'Lead / Manager', 'executive' => 'Executive'];
const COMPANY_SIZES = ['1-10' => '1–10', '11-50' => '11–50', '51-200' => '51–200', '201-500' => '201–500', '501-1000' => '501–1000', '1000+' => '1000+'];
const APP_STATUSES = ['applied' => 'Applied', 'viewed' => 'Viewed', 'shortlisted' => 'Shortlisted', 'interview' => 'Interview', 'offered' => 'Offered', 'hired' => 'Hired', 'rejected' => 'Not selected', 'withdrawn' => 'Withdrawn'];

/* ---------- Jobs ---------- */
const JOB_SELECT = "SELECT j.*, c.name company_name, c.slug company_slug, c.logo company_logo, c.verified company_verified,
    l.name location_name, l.slug location_slug, cat.name category_name, cat.slug category_slug
    FROM jobs j JOIN companies c ON c.id=j.company_id
    LEFT JOIN locations l ON l.id=j.location_id LEFT JOIN categories cat ON cat.id=j.category_id";
const JOB_LIVE = "j.status='active' AND c.status='active' AND (j.deadline IS NULL OR j.deadline >= CURDATE())";

/** Profile ↔ job match 0-100: skills 60%, experience 25%, location 15% */
function match_score(?array $profile, array $job): ?int {
    if (!$profile) return null;
    $ps = skills_arr($profile['skills'] ?? ''); $js = skills_arr($job['skills'] ?? '');
    if (!$ps && !$js) return null;
    $skill = 0.5;
    if ($js) {
        $hit = 0;
        foreach ($js as $s) foreach ($ps as $p) if ($p === $s || str_contains($p, $s) || str_contains($s, $p)) { $hit++; break; }
        $skill = $hit / count($js);
    }
    $exp = (float) ($profile['experience_years'] ?? 0); $min = (int) $job['experience_min']; $max = $job['experience_max'] !== null ? (int) $job['experience_max'] : null;
    $e = $exp >= $min ? (($max === null || $exp <= $max + 2) ? 1 : 0.7) : max(0, 1 - ($min - $exp) / max(1, $min));
    $loc = (!empty($profile['location_id']) && $profile['location_id'] == $job['location_id']) || $job['work_mode'] === 'remote' ? 1 : 0.4;
    return (int) round(($skill * 0.6 + $e * 0.25 + $loc * 0.15) * 100);
}

function seeker_profile(int $uid): ?array { return row('SELECT * FROM seeker_profiles WHERE user_id=?', [$uid]); }
function employer_company(int $uid): ?array { return row('SELECT * FROM companies WHERE owner_id=? ORDER BY id LIMIT 1', [$uid]); }

function profile_completeness(int $uid): array {
    $p = seeker_profile($uid) ?? []; $u = row('SELECT * FROM users WHERE id=?', [$uid]);
    $checks = [
        'Profile photo' => !empty($u['avatar']), 'Phone number' => !empty($u['phone']), 'Headline' => !empty($p['headline']),
        'Summary' => !empty($p['summary']), 'Skills' => count(skills_arr($p['skills'] ?? '')) >= 3, 'CV / Resume' => !empty($p['resume_path']),
        'Location' => !empty($p['location_id']), 'Experience' => (bool) val('SELECT 1 FROM seeker_experience WHERE user_id=? LIMIT 1', [$uid]) || ($p['experience_years'] ?? null) === '0.0',
        'Education' => (bool) val('SELECT 1 FROM seeker_education WHERE user_id=? LIMIT 1', [$uid]),
    ];
    $done = count(array_filter($checks));
    return ['percent' => (int) round($done / count($checks) * 100), 'missing' => array_keys(array_filter($checks, fn($v) => !$v))];
}

function abort(int $code = 404, string $msg = ''): void {
    http_response_code($code);
    $GLOBALS['abort_msg'] = $msg;
    require ROOT . '/pages/error.php';
    exit;
}
