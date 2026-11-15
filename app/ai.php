<?php
/* ---------- AI provider (Groq, xAI Grok, or any OpenAI-compatible API) ---------- */
const AI_PROVIDERS = [
    'groq' => ['Groq', 'https://api.groq.com/openai/v1', 'llama-3.3-70b-versatile'],
    'xai' => ['xAI Grok', 'https://api.x.ai/v1', 'grok-3-mini'],
    'openai' => ['OpenAI', 'https://api.openai.com/v1', 'gpt-4o-mini'],
    'custom' => ['Other (OpenAI-compatible)', '', ''],
];
function ai_provider(): string { $p = $GLOBALS['ai_provider_override'] ?? setting('ai_provider', 'groq'); return isset(AI_PROVIDERS[$p]) ? $p : 'groq'; }
function ai_base(?string $p = null): string { $p = $p ?? ai_provider(); return rtrim($p === 'custom' ? setting('ai_custom_base', '') : (setting($p . '_base_override', '') ?: AI_PROVIDERS[$p][1]), '/'); }
function ai_key(?string $p = null): string { $p = $p ?? ai_provider(); return (string) setting($p === 'groq' ? 'groq_api_key' : $p . '_api_key', ''); }
function ai_model(?string $p = null, bool $import = false): string {
    $p = $p ?? ai_provider();
    if ($import && ($m = setting('ai_import_model', ''))) return $m;
    return (string) setting($p === 'groq' ? 'groq_model' : $p . '_model', AI_PROVIDERS[$p][2] ?? '');
}
function ai_enabled(): bool { return setting('ai_enabled', '1') === '1' && ai_key() !== '' && ai_base() !== ''; }

function ai_remaining(?int $uid): int {
    $limit = (int) setting('ai_daily_limit', '30');
    if (!$uid) return 0;
    if (is_role('admin')) return 9999;
    $used = (int) val('SELECT COUNT(*) FROM ai_logs WHERE user_id=? AND created_at >= CURDATE()', [$uid]);
    return max(0, $limit - $used);
}

class AiRateLimit extends RuntimeException { public int $wait = 30; public bool $daily = false; }
class AiBudget extends RuntimeException {}

function ai_ready(string $p): bool { return isset(AI_PROVIDERS[$p]) && ai_key($p) !== '' && ai_base($p) !== ''; }

function ai_http(string $method, string $path, ?array $payload = null, int $timeout = 60, ?string $p = null): array {
    $ch = curl_init(ai_base($p) . $path);
    $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . ai_key($p)]];
    if ($method === 'POST') { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = json_encode($payload); }
    curl_setopt_array($ch, $opt);
    $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    return [$code, json_decode((string) $res, true) ?: [], $err];
}

/* ---------- cost & budget ---------- */
/** USD per 1M tokens [input, output]. Groq free plan = 0. Unknown paid models use a safe high default so the cap is never under-counted. */
function ai_prices(string $p): array {
    $in = setting($p . '_price_in', ''); $out = setting($p . '_price_out', '');
    if ($in !== '' && $out !== '') return [(float) $in, (float) $out];
    return match ($p) { 'groq' => [0.0, 0.0], 'xai' => [2.0, 6.0], 'openai' => [2.5, 10.0], default => [3.0, 15.0] };
}
function ai_spent(string $period = 'day'): float {
    $since = $period === 'month' ? date('Y-m-01 00:00:00') : date('Y-m-d 00:00:00');
    return (float) val('SELECT COALESCE(SUM(cost_usd),0) FROM ai_logs WHERE created_at >= ?', [$since]);
}
function ai_budget_check(string $p, int $promptChars, int $maxTokens): void {
    [$pi, $po] = ai_prices($p);
    if ($pi <= 0 && $po <= 0) return; // free provider
    $est = (($promptChars / 3.2) * $pi + $maxTokens * $po) / 1e6; // worst case: full max_tokens output
    $day = (float) setting('ai_budget_daily', '0.05'); $mon = (float) setting('ai_budget_monthly', '1');
    $sd = ai_spent('day'); $sm = ai_spent('month');
    if ($sd + $est > $day) throw new AiBudget(sprintf('Daily AI budget reached: $%.4f used of your $%s limit (this request could cost up to $%.4f). Raise the limit in Admin › AI & integrations, or wait until tomorrow.', $sd, rtrim(rtrim(number_format($day, 4), '0'), '.'), $est));
    if ($sm + $est > $mon) throw new AiBudget(sprintf('Monthly AI budget reached: $%.4f used of your $%s limit.', $sm, rtrim(rtrim(number_format($mon, 4), '0'), '.')));
}

/** One request to one provider (budget-checked, logged with real token cost). */
function ai_call(string $p, string $system, string $user, string $feature, bool $json, int $maxTokens, bool $import): string {
    ai_budget_check($p, mb_strlen($system) + mb_strlen($user), $maxTokens);
    $model = ai_model($p, $import && $p === ai_provider_primary());
    $payload = ['model' => $model, 'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
        'temperature' => $json ? 0.1 : 0.6, 'max_tokens' => $maxTokens];
    if ($json) $payload['response_format'] = ['type' => 'json_object'];
    [$code, $data, $err] = ai_http('POST', '/chat/completions', $payload, $import ? 150 : 50, $p);
    if ($json && $code === 400 && stripos(json_encode($data), 'response_format') !== false) { unset($payload['response_format']); [$code, $data, $err] = ai_http('POST', '/chat/completions', $payload, $import ? 150 : 50, $p); }
    $ok = $code === 200 && isset($data['choices'][0]['message']['content']);
    $pt = (int) ($data['usage']['prompt_tokens'] ?? 0); $ct = (int) ($data['usage']['completion_tokens'] ?? 0);
    [$pi, $po] = ai_prices($p);
    $cost = ($pt * $pi + $ct * $po) / 1e6;
    if ($ok && $pt + $ct === 0 && $pi + $po > 0) $cost = ((mb_strlen($system) + mb_strlen($user)) / 3.2 * $pi + $maxTokens * $po) / 1e6; // no usage reported: count worst case
    insert('ai_logs', ['user_id' => uid(), 'feature' => $feature, 'tokens' => $data['usage']['total_tokens'] ?? null, 'success' => $ok ? 1 : 0,
        'provider' => $p, 'model' => mb_substr($model, 0, 80), 'prompt_tokens' => $pt, 'completion_tokens' => $ct, 'cost_usd' => round($cost, 6)]);
    if ($ok) return trim($data['choices'][0]['message']['content']);
    $msg = $data['error']['message'] ?? (is_string($data['error'] ?? null) ? $data['error'] : '') ?: $err ?: "HTTP $code";
    if ($code === 429 || preg_match('/rate.?limit|too many requests|try again in/i', (string) $msg)) {
        $e = new AiRateLimit(AI_PROVIDERS[$p][0] . ' limit reached: ' . $msg);
        if (preg_match('/try again in (?:(\d+)h)?(?:(\d+)m)?([\d.]+)s/i', (string) $msg, $m)) $e->wait = (int) ceil(((int) ($m[1] ?? 0)) * 3600 + ((int) ($m[2] ?? 0)) * 60 + (float) $m[3]) + 2;
        $e->daily = (bool) preg_match('/per day|\(TPD\)|\(RPD\)|daily/i', (string) $msg);
        throw $e;
    }
    throw new RuntimeException('AI error (' . AI_PROVIDERS[$p][0] . '): ' . $msg);
}
function ai_provider_primary(): string { $p = setting('ai_provider', 'groq'); return isset(AI_PROVIDERS[$p]) ? $p : 'groq'; }

/**
 * Smart switching: use the main provider (e.g. free Groq). If it hits its limit and a backup provider is set,
 * switch to the backup only when the wait would be too long (bulk import waits up to N seconds first) or the daily quota is used up.
 * The backup is always inside the budget cap.
 */
function ai_chat(string $system, string $user, string $feature, bool $json = false, int $maxTokens = 1200, bool $import = false): string {
    $primary = ai_provider();
    try { return ai_call($primary, $system, $user, $feature, $json, $maxTokens, $import); }
    catch (AiRateLimit $e) {
        $fb = setting('ai_fallback_provider', '');
        $threshold = $import ? (int) setting('ai_fallback_after', '180') : 0;
        if ($fb && $fb !== $primary && ai_ready($fb) && ($e->daily || $e->wait >= $threshold)) {
            return ai_call($fb, $system, $user, $feature . ':backup', $json, $maxTokens, $import);
        }
        throw $e;
    }
}
/** Parse JSON even if the model wrapped it in text or ``` fences */
function ai_json(string $txt): array {
    $d = json_decode($txt, true);
    if (is_array($d)) return $d;
    if (preg_match('/\{.*\}/s', $txt, $m) && is_array($d = json_decode($m[0], true))) return $d;
    throw new RuntimeException('AI returned an unreadable answer. Try again or use a stronger model.');
}
/** List models from the provider (for the admin dropdown) */
function ai_list_models(): array {
    [$code, $data, $err] = ai_http('GET', '/models', null, 20);
    if ($code !== 200) throw new RuntimeException($data['error']['message'] ?? ($err ?: "HTTP $code - check the API key"));
    $ids = array_column($data['data'] ?? [], 'id'); sort($ids);
    return $ids;
}

/** Feature prompts. Input is user-controlled text; it is passed as data, never as instructions. */
function ai_feature(string $feature, array $in): array {
    $country = setting('country', 'Nepal'); $cur = setting('currency', 'NPR');
    switch ($feature) {
        case 'job_description':
            $sys = "You write clear, attractive job posts for employers in $country. Return JSON with keys: description (2 short paragraphs about the role and team, plain text), requirements (6-8 bullet lines each starting with '- '), benefits (4-6 bullet lines starting with '- '), skills (comma-separated, max 10, short skill names). No placeholders like [Company]. Plain text only, no markdown headings.";
            $usr = "Job title: " . mb_substr($in['title'] ?? '', 0, 150) . "\nCompany: " . mb_substr($in['company'] ?? '', 0, 150) . "\nLevel: " . ($in['level'] ?? '') . "\nType: " . ($in['type'] ?? '') . "\nEmployer notes: " . mb_substr($in['notes'] ?? '', 0, 1500);
            $r = ai_json(ai_chat($sys, $usr, $feature, true));
            foreach (['requirements', 'benefits'] as $k) if (isset($r[$k]) && is_array($r[$k])) $r[$k] = implode("\n", array_map(fn($x) => '- ' . ltrim($x, '- '), $r[$k]));
            if (isset($r['skills']) && is_array($r['skills'])) $r['skills'] = implode(', ', $r['skills']);
            return array_intersect_key($r, array_flip(['description', 'requirements', 'benefits', 'skills']));
        case 'improve':
            $sys = "Improve the user's text for a professional job portal in $country: fix grammar, make it clear and concise, keep the meaning and facts, keep bullet lines if present. Return only the improved text.";
            return ['text' => ai_chat($sys, mb_substr($in['text'] ?? '', 0, 4000), $feature)];
        case 'summary':
            $sys = "Write a first-person professional profile summary (60-90 words) for a job seeker in $country based on the details given. Confident, specific, no clichés, no emojis. Return only the summary.";
            return ['text' => ai_chat($sys, mb_substr($in['text'] ?? '', 0, 2500), $feature)];
        case 'cover_letter':
            $sys = "Write a short, specific cover letter (120-170 words) from the candidate to the employer. Use the candidate's real skills and experience; do not invent degrees or employers. Start with 'Dear Hiring Manager,'. End with the candidate's name. Return only the letter.";
            return ['text' => ai_chat($sys, mb_substr($in['text'] ?? '', 0, 4000), $feature)];
        case 'company_about':
            $sys = "Write an engaging 'About us' (90-130 words) for a company profile on a job portal in $country, aimed at attracting candidates. Use only facts given. Return only the text.";
            return ['text' => ai_chat($sys, mb_substr($in['text'] ?? '', 0, 2000), $feature)];
        case 'smart_search':
            $cats = implode(', ', array_column(categories(), 'slug')); $locs = implode(', ', array_column(locations(), 'slug'));
            $sys = "Convert a job search query into filters. Return JSON: {\"q\": keywords string (role/skills only), \"category\": one of [$cats] or \"\", \"location\": one of [$locs] or \"\", \"type\": one of [full-time,part-time,contract,internship,freelance,temporary] or \"\", \"mode\": one of [onsite,remote,hybrid] or \"\", \"level\": one of [entry,mid,senior,lead,executive] or \"\", \"salary\": minimum monthly salary in $cur as integer or 0}. Understand Nepali/Romanized Nepali words too (e.g. 'kaam' = job).";
            $r = ai_json(ai_chat($sys, mb_substr($in['text'] ?? '', 0, 300), $feature, true, 300));
            return array_filter(array_intersect_key($r, array_flip(['q', 'category', 'location', 'type', 'mode', 'level', 'salary'])));
        case 'interview_tips':
            $sys = "You are a career coach in $country. Given a job post, give 5 likely interview questions with one-line tips each, then 3 quick preparation tips. Plain text, bullet lines starting with '- '. Max 220 words.";
            return ['text' => ai_chat($sys, mb_substr($in['text'] ?? '', 0, 4000), $feature)];
    }
    throw new RuntimeException('Unknown AI feature.');
}
