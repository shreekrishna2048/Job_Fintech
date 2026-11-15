<?php
/* Instant indexing: IndexNow (Bing, Yandex, Seznam…) and Google Indexing API (officially supported for JobPosting pages). */

function seo_queue(string $url, string $type = 'URL_UPDATED'): void { $GLOBALS['seo_queue'][$url] = $type; }

function indexnow_key(): string {
    $k = setting('indexnow_key', '');
    if (!preg_match('/^[a-f0-9]{32}$/', $k)) { $k = bin2hex(random_bytes(16)); save_setting('indexnow_key', $k); }
    return $k;
}

function seo_flush(): void {
    $q = $GLOBALS['seo_queue'] ?? [];
    if (!$q) return;
    $GLOBALS['seo_queue'] = [];
    if (preg_match('#^https?://(localhost|127\.|10\.|192\.168\.)#', base_url())) return; // not on a public domain
    $host = parse_url(base_url(), PHP_URL_HOST);
    if (setting('indexnow_enabled', '1') === '1') {
        try {
            $ch = curl_init('https://api.indexnow.org/indexnow');
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
                CURLOPT_POSTFIELDS => json_encode(['host' => $host, 'key' => indexnow_key(), 'keyLocation' => url(indexnow_key() . '.txt'), 'urlList' => array_slice(array_keys($q), 0, 10000)])]);
            curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            save_setting('indexnow_last', date('Y-m-d H:i') . " · HTTP $code · " . count($q) . ' URLs');
        } catch (Throwable $e) {}
    }
    if (setting('google_indexing_json', '') !== '') {
        $used = (int) val("SELECT COUNT(*) FROM activity_logs WHERE action='google_index' AND created_at >= CURDATE()");
        foreach ($q as $url => $type) {
            if ($used++ >= 190) break; // Google default quota: 200/day
            try { $r = google_index_url($url, $type); log_activity('google_index', null, null, "$type $url → $r", 0); }
            catch (Throwable $e) { log_activity('google_index', null, null, "FAILED $url: " . mb_substr($e->getMessage(), 0, 200), 0); }
        }
    }
}

function google_access_token(): string {
    $cache = json_decode(setting('google_indexing_token', ''), true);
    if (!empty($cache['t']) && ($cache['exp'] ?? 0) > time() + 60) return $cache['t'];
    $sa = json_decode(setting('google_indexing_json', ''), true);
    if (empty($sa['client_email']) || empty($sa['private_key'])) throw new RuntimeException('Service account JSON is invalid.');
    $b64 = fn($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $now = time();
    $jwt = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode(['iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/indexing', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600]));
    if (!openssl_sign($jwt, $sig, $sa['private_key'], 'sha256WithRSAEncryption')) throw new RuntimeException('Could not sign with the private key.');
    $jwt .= '.' . $b64($sig);
    $tok = http_json('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]);
    if (empty($tok['access_token'])) throw new RuntimeException('Google token error: ' . ($tok['error_description'] ?? $tok['error'] ?? 'unknown'));
    save_setting('google_indexing_token', json_encode(['t' => $tok['access_token'], 'exp' => $now + (int) ($tok['expires_in'] ?? 3600)]));
    return $tok['access_token'];
}
function google_index_url(string $url, string $type = 'URL_UPDATED'): string {
    $ch = curl_init('https://indexing.googleapis.com/v3/urlNotifications:publish');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . google_access_token()],
        CURLOPT_POSTFIELDS => json_encode(['url' => $url, 'type' => $type])]);
    $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200) { $d = json_decode((string) $res, true); throw new RuntimeException("HTTP $code " . ($d['error']['message'] ?? '')); }
    return 'OK';
}
