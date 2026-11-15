<?php
/*
 * Site images: every image slot has a relevant free stock photo (Unsplash License - free for
 * commercial use, no attribution required). Admin can replace any image (upload or URL) in
 * Admin › Images, and can download all remote images to this server with one click.
 */
const STOCK = [
    // page slots
    'img_hero' => '1522071820081-009f0129c71c', 'img_hero_2' => '1573497620053-ea5300f94f21', 'img_auth' => '1531973576160-7125cd663d86',
    'img_employers' => '1521791136064-7986c2920216', 'img_how' => '1758691737278-3af15b37af48', 'img_cta' => '1529733905113-027ed85d7e33',
    'img_cover_default' => '1568992687947-868a62a9f521', 'img_og' => '1587186846095-f8c531b4683c', 'img_page_header' => '1518002054494-3a6f94352e9d',
    // categories
    'cat:it-software' => '1498050108023-c5249f4df085', 'cat:banking-finance' => '1526628953301-3e589a6a8b74', 'cat:sales-marketing' => '1542744173-8e7e53415bb0',
    'cat:accounting' => '1554224155-6726b3ff858f', 'cat:ngo-ingo' => '1652971876875-05db98fab376', 'cat:teaching-education' => '1509062522246-3755977927d7',
    'cat:healthcare' => '1584432810601-6c7f27d2362b', 'cat:engineering' => '1581092583537-20d51b4b4f1b', 'cat:hospitality-tourism' => '1551632436-cbf8dd35adfa',
    'cat:design-creative' => '1572044162444-ad60f128bdea', 'cat:customer-service' => '1553775282-20af80779df7', 'cat:human-resources' => '1686771416282-3888ddaf249b',
    'cat:admin-office' => '1606857521015-7f9fcf423740', 'cat:logistics-driving' => '1565793298595-6a879b1d9492', 'cat:construction' => '1541888946425-d81bb19240f5',
    'cat:media-journalism' => '1504711434969-e33886168f5c', 'cat:legal' => '1589829545856-d10d557cf95f', 'cat:manufacturing' => '1647427060118-4911c9821b82',
    // cities
    'loc:kathmandu' => '1587186846095-f8c531b4683c', 'loc:lalitpur' => '1585597800810-07a63ea8e983', 'loc:bhaktapur' => '1618851142562-ff30d09313a9',
    'loc:pokhara' => '1576948187290-457c015b3bff',
];
/** Nepal landscapes used (deterministically) for cities without their own photo */
const STOCK_NEPAL = ['1518002054494-3a6f94352e9d', '1636513988093-126e51dee32d', '1580424917967-a8867a6e676e', '1718179634911-8551f8b0cccf', '1720812128603-0b4b3ddd8804', '1529733905113-027ed85d7e33'];
/** Generic work photos for admin-added categories without an image */
const STOCK_WORK = ['1522071820081-009f0129c71c', '1531973576160-7125cd663d86', '1517048676732-d65bc937f952', '1560264280-88b68371db39', '1542744173-8e7e53415bb0', '1606857521015-7f9fcf423740'];
/** Industry -> category photo for default company covers */
const INDUSTRY_PHOTO = [
    'information-technology' => 'cat:it-software', 'banking' => 'cat:banking-finance', 'insurance' => 'cat:banking-finance', 'microfinance' => 'cat:accounting',
    'ngo-ingo' => 'cat:ngo-ingo', 'education' => 'cat:teaching-education', 'healthcare' => 'cat:healthcare', 'hospitality' => 'cat:hospitality-tourism',
    'travel-tourism' => 'loc:pokhara', 'manufacturing' => 'cat:manufacturing', 'retail-ecommerce' => 'cat:sales-marketing', 'telecommunication' => 'cat:customer-service',
    'construction' => 'cat:construction', 'media' => 'cat:media-journalism', 'government' => 'cat:admin-office', 'energy' => 'cat:engineering',
    'consulting' => 'cat:human-resources', 'food-restaurant' => 'cat:hospitality-tourism', 'logistics' => 'cat:logistics-driving',
];
const SITE_IMAGE_SLOTS = [
    'img_hero' => ['Home - hero photo (left)', 'People at work. Shown beside the search box on desktop.'],
    'img_hero_2' => ['Home - hero photo (right)', 'Second floating photo on the home page.'],
    'img_how' => ['Home - “How it works” photo', 'Behind the application-tracking demo card.'],
    'img_cta' => ['Home - employer banner background', '“Hiring? Reach thousands…” banner.'],
    'img_auth' => ['Sign in / Sign up page', 'Large photo on the left of the login and register pages.'],
    'img_employers' => ['For employers page', 'Hero photo on /employers.'],
    'img_page_header' => ['Page headers', 'Background for About / Privacy / Contact headers.'],
    'img_cover_default' => ['Default company cover', 'Used when a company has no cover and no matching industry photo.'],
    'img_og' => ['Social share image', 'Shown when your site link is shared on Facebook/WhatsApp/LinkedIn.'],
];

function stock_url(string $id, int $w = 1200): string { return "https://images.unsplash.com/photo-$id?auto=format&fit=crop&w=$w&q=70"; }

/** Resolve a stored value (full URL, uploads path or empty) */
function img_value_url(?string $v, int $w = 1200): ?string {
    if (!$v) return null;
    if (preg_match('#^https?://#', $v)) return str_contains($v, 'images.unsplash.com') ? preg_replace('/([?&])w=\d+/', '$1w=' . $w, $v) : $v;
    return upload_url($v);
}
function site_image(string $key, int $w = 1600): string {
    return img_value_url(setting($key, ''), $w) ?? stock_url(STOCK[$key] ?? STOCK['img_hero'], $w);
}
function cat_image(array $cat, int $w = 600): string {
    if ($u = img_value_url($cat['image'] ?? null, $w)) return $u;
    return stock_url(STOCK['cat:' . ($cat['slug'] ?? '')] ?? STOCK_WORK[crc32((string) ($cat['slug'] ?? 'x')) % count(STOCK_WORK)], $w);
}
function loc_image(array $loc, int $w = 600): string {
    if ($u = img_value_url($loc['image'] ?? null, $w)) return $u;
    return stock_url(STOCK['loc:' . ($loc['slug'] ?? '')] ?? STOCK_NEPAL[crc32((string) ($loc['slug'] ?? 'x')) % count(STOCK_NEPAL)], $w);
}
/** Company cover: uploaded → industry photo → default */
function company_cover(array $c, int $w = 1600): string {
    if (!empty($c['cover'])) return upload_url($c['cover']);
    $ind = $c['industry_slug'] ?? (!empty($c['industry_id']) ? val('SELECT slug FROM industries WHERE id=?', [$c['industry_id']]) : null);
    if ($ind && isset(INDUSTRY_PHOTO[$ind])) return stock_url(STOCK[INDUSTRY_PHOTO[$ind]], $w);
    return site_image('img_cover_default', $w);
}
/** CSS background with gradient fallback if the photo can't load */
function bg_img(string $url, string $overlay = ''): string {
    return "background-image:" . ($overlay ? "$overlay," : '') . "url('" . e($url) . "'),linear-gradient(120deg,#2a1d3a,#16141d)";
}

/** Add image columns to existing installs */
function run_migrations(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        $ver = (int) setting('db_version', '1');
        if ($ver >= 5) return;
        if (!val("SHOW COLUMNS FROM users LIKE 'admin_perms'")) db()->exec('ALTER TABLE users ADD admin_perms VARCHAR(500) NULL AFTER status');
        foreach (['categories', 'locations'] as $t) if (!val("SHOW COLUMNS FROM $t LIKE 'image'")) db()->exec("ALTER TABLE $t ADD image VARCHAR(255) NULL");
        $col = fn($t, $c, $def) => val("SHOW COLUMNS FROM $t LIKE '$c'") ?: db()->exec("ALTER TABLE $t ADD $c $def");
        $col('companies', 'imported', 'TINYINT(1) NOT NULL DEFAULT 0');
        $col('jobs', 'contact_email', 'VARCHAR(190) NULL');
        $col('jobs', 'contact_phone', 'VARCHAR(60) NULL');
        $col('jobs', 'source_url', 'VARCHAR(500) NULL');
        $col('jobs', 'imported', 'TINYINT(1) NOT NULL DEFAULT 0');
        $col('email_queue', 'attachments', 'TEXT NULL');
        $sql = file_get_contents(ROOT . '/sql/schema.sql');
        foreach (['import_batches', 'import_items'] as $t) if (preg_match('/CREATE TABLE IF NOT EXISTS ' . $t . ' \(.*?\) ENGINE=InnoDB[^;]*;/s', $sql, $m)) db()->exec($m[0]);
        if (preg_match("/\('application_forward'.*?'\),/s", file_get_contents(ROOT . '/sql/seed.sql'), $m)) db()->exec('INSERT IGNORE INTO email_templates (`key`, name, subject, body) VALUES ' . rtrim($m[0], ','));
        // v5: AI cost tracking, job currency, UAE cities
        foreach (['provider' => 'VARCHAR(20) NULL', 'model' => 'VARCHAR(80) NULL', 'prompt_tokens' => 'INT UNSIGNED NULL', 'completion_tokens' => 'INT UNSIGNED NULL', 'cost_usd' => 'DECIMAL(10,6) NOT NULL DEFAULT 0'] as $c => $def) $col('ai_logs', $c, $def);
        $col('jobs', 'currency', 'VARCHAR(5) NULL');
        if (preg_match("/INSERT IGNORE INTO locations \\(name, slug, province, country, sort\\) VALUES.*?;/s", file_get_contents(ROOT . '/sql/seed.sql'), $m)) db()->exec($m[0]);
        save_setting('db_version', '5');
    } catch (Throwable $e) { error_log('migration: ' . $e->getMessage()); }
}

/** Download a remote image into uploads/site/ - returns relative path */
function localize_remote_image(string $url): ?string {
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 25, CURLOPT_USERAGENT => 'KaamKhoj/1.0']);
    $data = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200 || !$data || strlen($data) < 2000) return null;
    $mime = detect_mime($data);
    if (!isset(IMG_TYPES[$mime])) return null;
    $rel = 'site/' . substr(sha1($url), 0, 20) . '.' . IMG_TYPES[$mime];
    if (!is_dir(ROOT . '/uploads/site')) mkdir(ROOT . '/uploads/site', 0755, true);
    file_put_contents(ROOT . '/uploads/' . $rel, $data);
    return $rel;
}
/** Download every stock image currently in use to this server (makes the site independent of Unsplash). */
function localize_all_images(): array {
    $ok = 0; $fail = 0;
    foreach (array_keys(SITE_IMAGE_SLOTS) as $k) {
        $cur = setting($k, '');
        if ($cur && !preg_match('#^https?://#', $cur)) continue;
        $p = localize_remote_image($cur ?: stock_url(STOCK[$k], 1800));
        if ($p) { save_setting($k, $p); $ok++; } else $fail++;
    }
    foreach (['categories' => 'cat_image', 'locations' => 'loc_image'] as $t => $fn) {
        foreach (rows("SELECT * FROM $t") as $r) {
            if (!empty($r['image']) && !preg_match('#^https?://#', $r['image'])) continue;
            $p = localize_remote_image($fn($r, 800));
            if ($p) { q("UPDATE $t SET image=? WHERE id=?", [$p, $r['id']]); $ok++; } else $fail++;
        }
    }
    save_setting('images_localized', date('Y-m-d H:i:s'));
    return ['ok' => $ok, 'fail' => $fail];
}
