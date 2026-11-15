<?php
/*
 * Job finder: discovers recent job postings for a country, extracts them accurately and puts them in the
 * normal import review screen. Nothing is published without the admin selecting and confirming.
 *
 * Accuracy order:
 *  1. Google "JobPosting" structured data on the page (exact title, company, dates, salary, location, apply link)
 *  2. AI extraction from the page text (only when no structured data)
 * Pages that block bots in robots.txt are not fetched.
 */

const FINDER_DEFAULT_QUERIES = [
    'Nepal' => ['job vacancy Nepal', 'vacancy announcement Kathmandu', 'we are hiring Nepal', 'career opportunity Nepal apply now', 'job opening Pokhara OR Biratnagar OR Butwal'],
    'United Arab Emirates' => ['job vacancy UAE', 'we are hiring Dubai', 'job opening Abu Dhabi', 'careers Sharjah apply', 'vacancy UAE immediate joining'],
];

function finder_searches_today(): int { return (int) val("SELECT COUNT(*) FROM activity_logs WHERE action='finder_search' AND created_at >= CURDATE()"); }

/** Tavily web search → candidate pages (with page text) */
function finder_tavily(string $query, string $country, int $days, array $exclude = []): array {
    $key = setting('tavily_api_key');
    if (!$key) throw new RuntimeException('Add a Tavily API key in Admin › AI & integrations to search the web.');
    $body = ['query' => $query, 'search_depth' => 'basic', 'max_results' => 20, 'topic' => 'general', 'include_raw_content' => 'text', 'include_published_date' => true,
        'country' => COUNTRIES[$country]['search'] ?? null, 'time_range' => $days <= 1 ? 'day' : ($days <= 7 ? 'week' : ($days <= 31 ? 'month' : 'year'))];
    if ($exclude) $body['exclude_domains'] = array_slice($exclude, 0, 150);
    $ch = curl_init(setting('tavily_endpoint', 'https://api.tavily.com/search'));
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key], CURLOPT_POSTFIELDS => json_encode(array_filter($body, fn($v) => $v !== null))]);
    $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    log_activity('finder_search', null, null, "$query · HTTP $code");
    $d = json_decode((string) $res, true) ?: [];
    if ($code !== 200) throw new RuntimeException('Web search failed: ' . ($d['detail']['error'] ?? $d['error'] ?? "HTTP $code"));
    $out = [];
    foreach ($d['results'] ?? [] as $r) {
        if (empty($r['url'])) continue;
        $out[] = ['url' => $r['url'], 'title' => mb_substr((string) ($r['title'] ?? ''), 0, 200), 'text' => mb_substr((string) ($r['raw_content'] ?? $r['content'] ?? ''), 0, 12000),
            'published' => !empty($r['published_date']) ? date('Y-m-d', strtotime($r['published_date'])) : '', 'via' => 'Web search'];
    }
    return $out;
}

/** Saved sources: company career pages, government notice pages, RSS/Atom feeds */
function finder_source_candidates(string $url): array {
    if (!robots_allowed($url)) return [['url' => $url, 'skip' => 'Blocked by the site’s robots.txt']];
    [$code, $body, $final] = http_get_page($url, 12);
    if ($code !== 200 || $body === '') return [['url' => $url, 'skip' => "Could not load (HTTP $code)"]];
    // RSS / Atom feed → one candidate per item
    if (preg_match('/^\s*(<\?xml[^>]*>\s*)?<(rss|feed)\b/i', $body) && ($x = @simplexml_load_string($body))) {
        $items = [];
        foreach ($x->channel->item ?? [] as $it) $items[] = ['url' => (string) $it->link, 'title' => (string) $it->title, 'text' => strip_tags((string) $it->description), 'published' => (string) $it->pubDate ? date('Y-m-d', strtotime((string) $it->pubDate)) : '', 'via' => 'Feed'];
        foreach ($x->entry ?? [] as $it) $items[] = ['url' => (string) ($it->link['href'] ?? ''), 'title' => (string) $it->title, 'text' => strip_tags((string) ($it->summary ?? $it->content)), 'published' => (string) ($it->updated ?? $it->published) ? date('Y-m-d', strtotime((string) ($it->updated ?? $it->published))) : '', 'via' => 'Feed'];
        return array_slice(array_filter($items, fn($i) => $i['url']), 0, 60);
    }
    return [['url' => $final ?: $url, 'title' => '', 'html' => mb_substr($body, 0, 600000), 'published' => '', 'via' => 'Your source']];
}

/** Very small robots.txt check (User-agent: * and our bot) */
function robots_allowed(string $url): bool {
    static $cache = [];
    $p = parse_url($url);
    if (empty($p['host'])) return false;
    $origin = ($p['scheme'] ?? 'https') . '://' . $p['host'];
    if (!isset($cache[$origin])) {
        [$code, $txt] = http_get_page($origin . '/robots.txt', 6);
        $rules = []; $applies = false;
        if ($code === 200) foreach (preg_split('/\R/', $txt) as $line) {
            $line = trim(preg_replace('/#.*/', '', $line));
            if (preg_match('/^user-agent:\s*(.+)$/i', $line, $m)) { $ua = strtolower(trim($m[1])); $applies = $ua === '*' || str_contains($ua, 'kaamkhoj'); continue; }
            if ($applies && preg_match('/^disallow:\s*(.*)$/i', $line, $m) && trim($m[1]) !== '') $rules[] = trim($m[1]);
        }
        $cache[$origin] = $rules;
    }
    $path = ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
    foreach ($cache[$origin] as $r) {
        $re = '#^' . str_replace(['\*', '\$'], ['.*', '$'], preg_quote($r, '#')) . '#';
        if (preg_match($re, $path)) return false;
    }
    return true;
}

/** All JobPosting objects in a page's JSON-LD */
function jsonld_jobpostings(string $html): array {
    $out = [];
    if (!preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $m)) return [];
    $walk = function ($n) use (&$walk, &$out) {
        if (!is_array($n)) return;
        $t = $n['@type'] ?? null;
        if ($t === 'JobPosting' || (is_array($t) && in_array('JobPosting', $t, true))) { $out[] = $n; return; }
        foreach (['@graph', 'itemListElement', 'item'] as $k) if (isset($n[$k])) $walk($n[$k]);
        if (array_is_list($n)) foreach ($n as $c) $walk($c);
    };
    foreach ($m[1] as $json) $walk(json_decode(html_entity_decode(trim($json), ENT_QUOTES), true));
    return $out;
}

/** Map a schema.org JobPosting to our item fields (exact values, no AI) */
function jsonld_to_job(array $jp, string $pageUrl): array {
    $str = fn($v) => is_scalar($v) ? trim(html_entity_decode(strip_tags((string) $v), ENT_QUOTES)) : '';
    $org = $jp['hiringOrganization'] ?? []; if (isset($org[0])) $org = $org[0];
    $loc = $jp['jobLocation'] ?? []; if (isset($loc[0])) $loc = $loc[0];
    $addr = $loc['address'] ?? []; if (is_string($addr)) $addr = ['addressLocality' => $addr];
    $sal = $jp['baseSalary'] ?? []; $sv = $sal['value'] ?? [];
    $unit = strtoupper($str($sv['unitText'] ?? $sal['unitText'] ?? 'MONTH'));
    $mult = ['YEAR' => 1 / 12, 'WEEK' => 4.33, 'DAY' => 26, 'HOUR' => 208][$unit] ?? 1;
    $num = fn($x) => is_numeric($x) ? (int) round($x * $mult) : null;
    $type = strtoupper(is_array($jp['employmentType'] ?? null) ? ($jp['employmentType'][0] ?? '') : $str($jp['employmentType'] ?? ''));
    $desc = (string) ($jp['description'] ?? '');
    return [
        'title' => $str($jp['title'] ?? ''), 'company' => $str($org['name'] ?? ''), 'company_website' => $str($org['sameAs'] ?? $org['url'] ?? ''),
        'location' => trim($str($addr['addressLocality'] ?? '') . ' ' . $str($addr['addressRegion'] ?? '')), 'location_country' => $str(is_array($addr['addressCountry'] ?? null) ? ($addr['addressCountry']['name'] ?? '') : ($addr['addressCountry'] ?? '')),
        'job_type' => ['FULL_TIME' => 'full-time', 'PART_TIME' => 'part-time', 'CONTRACTOR' => 'contract', 'TEMPORARY' => 'temporary', 'INTERN' => 'internship'][$type] ?? 'full-time',
        'work_mode' => ($jp['jobLocationType'] ?? '') === 'TELECOMMUTE' ? 'remote' : 'onsite',
        'salary_min' => $num($sv['minValue'] ?? $sv['value'] ?? null), 'salary_max' => $num($sv['maxValue'] ?? null), 'salary_currency' => $str($sal['currency'] ?? ''),
        'date_posted' => !empty($jp['datePosted']) ? date('Y-m-d', strtotime($str($jp['datePosted']))) : null,
        'deadline' => !empty($jp['validThrough']) ? date('Y-m-d', strtotime($str($jp['validThrough']))) : null,
        'apply_url' => $str($jp['url'] ?? '') ?: $pageUrl, 'education' => $str(is_array($jp['educationRequirements'] ?? null) ? ($jp['educationRequirements']['credentialCategory'] ?? '') : ($jp['educationRequirements'] ?? '')),
        'skills' => is_array($jp['skills'] ?? null) ? $jp['skills'] : array_filter(array_map('trim', explode(',', $str($jp['skills'] ?? '')))),
        'description_html' => $desc, 'source_url' => $pageUrl,
    ];
}

/** Process one candidate page → import items. Returns jobs added. */
function finder_process_candidate(int $batchId, $cand, array $opt): int {
    $country = $opt['country'] ?? 'Nepal';
    $stats = ['found' => 0, 'old' => 0, 'dup' => 0, 'abroad' => 0, 'skipped' => ''];
    $finish = function () use ($batchId, &$stats) {
        $b = row('SELECT options FROM import_batches WHERE id=?', [$batchId]);
        $o = json_decode((string) $b['options'], true) ?: [];
        foreach (['found', 'old', 'dup', 'abroad'] as $k) $o['stats'][$k] = ($o['stats'][$k] ?? 0) + $stats[$k];
        if ($stats['skipped']) $o['stats']['skipped_pages'] = ($o['stats']['skipped_pages'] ?? 0) + 1;
        q("UPDATE import_batches SET options=?, parsed_chunks=parsed_chunks+1, status=IF(parsed_chunks>=total_chunks,'review',status) WHERE id=?", [json_encode($o, JSON_UNESCAPED_UNICODE), $batchId]);
        $GLOBALS['finder_last_stats'] = $stats;
        return $stats['found'];
    };
    if (!is_array($cand) || empty($cand['url']) || !empty($cand['skip'])) { $stats['skipped'] = $cand['skip'] ?? 'Invalid'; return $finish(); }
    $url = $cand['url'];
    $minDate = date('Y-m-d', strtotime('-' . max(1, (int) ($opt['days'] ?? 7)) . ' days'));

    // Get page HTML if allowed (for exact structured data); fall back to search text
    if (!robots_allowed($url)) { $stats['skipped'] = 'Site does not allow bots (robots.txt)'; return $finish(); }
    $html = $cand['html'] ?? '';
    if ($html === '') { [$code, $body] = http_get_page($url, 10); if ($code === 200) $html = mb_substr($body, 0, 600000); }
    $jobs = [];
    foreach (jsonld_jobpostings($html) as $jp) {
        $x = jsonld_to_job($jp, $url);
        if ($x['title'] === '') continue;
        $item = import_normalize(['title' => $x['title'], 'company' => $x['company'], 'company_website' => $x['company_website'], 'location' => $x['location'], 'job_type' => $x['job_type'], 'work_mode' => $x['work_mode'],
            'salary_min' => $x['salary_min'], 'salary_max' => $x['salary_max'], 'date_posted' => $x['date_posted'], 'deadline' => $x['deadline'], 'apply_url' => $x['apply_url'],
            'education' => $x['education'], 'skills' => $x['skills'], 'source_url' => $url, 'description' => html_to_text($x['description_html'])], $country);
        $item['data_source'] = 'Structured data (exact)';
        $pat = $country === 'United Arab Emirates' ? '/\b(AE|UAE)\b|emirates/i' : '/\bNP\b|nepal/i';
        if ($x['location_country'] && !preg_match($pat, $x['location_country'])) $item['abroad'] = $x['location_country'];
        // AI only rewrites the description/requirements and picks the category - hard facts stay exact
        if (!empty($opt['rewrite']) && ai_enabled() && mb_strlen($item['description']) > 80) {
            try {
                $r = ai_json(ai_chat('Rewrite this job description IN YOUR OWN WORDS. Return JSON {"description": "2 short paragraphs", "requirements": ["..."], "benefits": ["..."], "category": "one of: ' . implode(', ', array_column(categories(), 'slug')) . '", "experience_min": int or null}. Use only facts in the text.', mb_substr($item['description'], 0, 6000), 'finder_rewrite', true, 1500, true));
                $n = import_normalize(['title' => $item['title'], 'description' => $r['description'] ?? '', 'requirements' => $r['requirements'] ?? [], 'benefits' => $r['benefits'] ?? [], 'category' => $r['category'] ?? '', 'experience_min' => $r['experience_min'] ?? null], $country);
                foreach (['description', 'requirements', 'benefits', 'category_id'] as $k) if (!empty($n[$k])) $item[$k] = $n[$k];
                if (is_numeric($r['experience_min'] ?? null)) $item['experience_min'] = (int) $r['experience_min'];
            } catch (AiBudget $e) { throw $e; } catch (Throwable $e) {}
        }
        $jobs[] = $item;
    }
    if (!$jobs) {
        $text = trim($cand['text'] ?? '');
        if (mb_strlen($text) < 200 && $html !== '') $text = page_text($html);
        if (mb_strlen($text) < 40) { $stats['skipped'] = 'Page had no readable text'; return $finish(); }
        $ctx = "Source page: $url" . (!empty($cand['title']) ? "\nPage title: {$cand['title']}" : '') . (!empty($cand['published']) ? "\nPage published: {$cand['published']}" : '');
        foreach (import_ai_parse(mb_substr($text, 0, 9000), !empty($opt['rewrite']), $country, $ctx) as $item) {
            if (!$item['posted_date'] && !empty($cand['published'])) $item['posted_date'] = $cand['published'];
            $item['source_url'] = $url;
            $item['data_source'] = 'AI read the page';
            $jobs[] = $item;
        }
    }
    // dedupe & date rules
    $seen = [];
    foreach (rows('SELECT data FROM import_items WHERE batch_id=?', [$batchId]) as $r) { $d = json_decode($r['data'], true); $seen[company_norm($d['company'] ?? '') . '|' . title_norm($d['title'] ?? '')] = 1; }
    foreach ($jobs as $j) {
        if (!$j['apply_target'] && !$j['contact_email']) { $j['apply_method'] = 'url'; $j['apply_target'] = $url; } // send candidates to the original posting
        $key = company_norm($j['company']) . '|' . title_norm($j['title']);
        if (isset($seen[$key]) || val('SELECT 1 FROM jobs WHERE source_url=? AND title=? LIMIT 1', [$url, $j['title']])) { $stats['dup']++; continue; }
        if (($j['posted_date'] && $j['posted_date'] < $minDate) || ($j['deadline'] && $j['deadline'] < date('Y-m-d'))) { $stats['old']++; continue; }
        if (!empty($opt['require_date']) && !$j['posted_date']) { $stats['old']++; continue; }
        if (!empty($j['abroad'])) { $stats['abroad']++; continue; }
        $seen[$key] = 1;
        if (!$j['location_id'] && !empty($opt['default_location'])) $j['location_id'] = $opt['default_location'];
        $j = import_flags($j);
        if ($j['duplicate_of']) { $stats['dup']++; continue; }
        insert('import_items', ['batch_id' => $batchId, 'data' => json_encode($j, JSON_UNESCAPED_UNICODE)]);
        $stats['found']++;
    }
    return $finish();
}

/** Readable text from HTML, keeping line breaks between blocks */
function page_text(string $html): string {
    $h = preg_replace('#<(script|style|nav|footer|header|noscript|svg)\b.*?</\1>#is', ' ', $html);
    $h = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr|/section|/article)\b[^>]*>#i', "\n", $h);
    $t = html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace(["/[ \t\x{00A0}]+/u", "/ *\n */", "/\n{3,}/"], [' ', "\n", "\n\n"], $t);
    return trim($t);
}

/** Build the candidate list for a finder run (search + saved sources). Returns [candidates, notes]. */
function finder_collect(string $country, array $keywords, int $days, int $limit, bool $useWeb, bool $useSources): array {
    $cands = []; $notes = [];
    $exclude = array_values(array_filter(array_map(fn($l) => strtolower(trim($l)), preg_split('/[\s,]+/', setting('finder_exclude_domains', '')))));
    if ($useSources) {
        $src = array_filter(array_map('trim', preg_split('/\R/', setting('finder_sources_' . strtolower(COUNTRIES[$country]['code']), ''))), fn($u) => filter_var($u, FILTER_VALIDATE_URL));
        foreach ($src as $u) foreach (finder_source_candidates($u) as $c) $cands[] = $c;
        if (!$src) $notes[] = 'No saved sources for ' . $country . ' yet.';
    }
    if ($useWeb) {
        $cap = (int) setting('finder_daily_searches', '20'); $left = max(0, $cap - finder_searches_today());
        $queries = $keywords ? array_map(fn($k) => '"' . trim($k, '" ') . '" vacancy ' . ($country === 'United Arab Emirates' ? 'UAE' : $country), $keywords) : FINDER_DEFAULT_QUERIES[$country];
        $queries = array_slice($queries, 0, min($left, max(1, (int) ceil($limit / 8)), 6));
        if (!$queries) $notes[] = "Daily web-search limit reached ($cap). Raise it in Admin › AI & integrations or try tomorrow.";
        foreach ($queries as $q) {
            try { foreach (finder_tavily($q, $country, $days, $exclude) as $c) $cands[] = $c; }
            catch (Throwable $e) { $notes[] = $e->getMessage(); break; }
        }
    }
    // unique by URL, drop pages we already imported, newest first, cap
    $uniq = [];
    foreach ($cands as $c) {
        $u = preg_replace('/#.*$/', '', $c['url'] ?? '');
        if ($u === '' || isset($uniq[$u])) continue;
        if (in_array(domain_of($u), $exclude, true)) continue;
        if (empty($c['skip']) && val('SELECT 1 FROM jobs WHERE source_url=? LIMIT 1', [$u])) continue;
        if (!empty($c['published']) && $c['published'] < date('Y-m-d', strtotime("-$days days"))) continue;
        $uniq[$u] = $c;
    }
    $list = array_values($uniq);
    usort($list, fn($a, $b) => strcmp($b['published'] ?? '', $a['published'] ?? ''));
    return [array_slice($list, 0, $limit), $notes];
}
