<?php
header('Content-Type: application/xml; charset=utf-8');
$u = fn($loc, $mod = null, $freq = 'daily', $pri = '0.6') => "<url><loc>" . e($loc) . "</loc>" . ($mod ? "<lastmod>" . date('Y-m-d', strtotime($mod)) . "</lastmod>" : '') . "<changefreq>$freq</changefreq><priority>$pri</priority></url>\n";
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
echo $u(url(''), null, 'hourly', '1.0'), $u(url('jobs'), null, 'hourly', '0.9'), $u(url('companies'), null, 'daily', '0.7'), $u(url('employers'), null, 'monthly', '0.5'), $u(url('contact'), null, 'yearly', '0.3');
foreach (rows("SELECT j.slug, COALESCE(j.updated_at,j.published_at) m FROM jobs j JOIN companies c ON c.id=j.company_id WHERE " . JOB_LIVE . " ORDER BY j.published_at DESC LIMIT 20000") as $r) echo $u(url('job/' . $r['slug']), $r['m'], 'daily', '0.8');
foreach (rows("SELECT slug, COALESCE(updated_at,created_at) m FROM companies WHERE status='active'") as $r) echo $u(url('company/' . $r['slug']), $r['m'], 'weekly', '0.6');
foreach (rows("SELECT DISTINCT l.slug FROM locations l JOIN jobs j ON j.location_id=l.id WHERE j.status='active'") as $r) echo $u(url('jobs/in/' . $r['slug']), null, 'daily', '0.7');
foreach (rows("SELECT DISTINCT cat.slug FROM categories cat JOIN jobs j ON j.category_id=cat.id WHERE j.status='active'") as $r) echo $u(url('jobs/category/' . $r['slug']), null, 'daily', '0.7');
foreach (rows("SELECT slug, updated_at FROM pages WHERE published=1") as $r) echo $u(url('page/' . $r['slug']), $r['updated_at'], 'monthly', '0.3');
echo '</urlset>';
