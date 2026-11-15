<?php
$action = param('action');
$postOnly = ['save', 'follow', 'alert', 'ai', 'ai-search', 'app-status', 'report'];
if (in_array($action, $postOnly, true)) { if (!is_post()) json_out(['ok' => false, 'error' => 'POST required'], 405); csrf_check(); }

switch ($action) {
    case 'suggest':
        $q = mb_substr(trim($_GET['q'] ?? ''), 0, 60);
        if (mb_strlen($q) < 2) json_out(['items' => []]);
        $like = str_replace(['%', '_'], ['\%', '\_'], $q) . '%'; $any = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $titles = rows("SELECT j.title label, COUNT(*) n FROM jobs j JOIN companies c ON c.id=j.company_id WHERE " . JOB_LIVE . " AND j.title LIKE ? GROUP BY j.title ORDER BY n DESC LIMIT 5", [$any]);
        $cos = rows("SELECT name label, slug FROM companies WHERE status='active' AND name LIKE ? LIMIT 3", [$any]);
        $skills = [];
        foreach (rows("SELECT skills FROM jobs WHERE status='active' AND skills LIKE ? LIMIT 50", [$any]) as $r)
            foreach (skills_arr($r['skills']) as $s) if (str_starts_with($s, mb_strtolower($q))) $skills[$s] = ($skills[$s] ?? 0) + 1;
        arsort($skills);
        $items = [];
        foreach ($titles as $t) $items[] = ['type' => 'Job title', 'label' => $t['label'], 'url' => url('jobs?q=' . urlencode($t['label']))];
        foreach (array_slice(array_keys($skills), 0, 3) as $s) $items[] = ['type' => 'Skill', 'label' => $s, 'url' => url('jobs?q=' . urlencode($s))];
        foreach ($cos as $c) $items[] = ['type' => 'Company', 'label' => $c['label'], 'url' => url('company/' . $c['slug'])];
        header('Cache-Control: public, max-age=120');
        json_out(['items' => $items]);

    case 'email-check':
        $_SESSION['ec'] = array_filter($_SESSION['ec'] ?? [], fn($t) => $t > time() - 3600);
        if (count($_SESSION['ec']) > 40) json_out(['ok' => true]);
        $_SESSION['ec'][] = time();
        $em = mb_strtolower(trim($_GET['email'] ?? ''));
        if (!filter_var($em, FILTER_VALIDATE_EMAIL)) json_out(['ok' => true, 'valid' => false]);
        $u = row('SELECT google_id, microsoft_id, password_hash FROM users WHERE email=?', [$em]);
        $domain = substr(strrchr($em, '@'), 1);
        $typo = ['gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gamil.com' => 'gmail.com', 'gmail.co' => 'gmail.com', 'hotmial.com' => 'hotmail.com', 'yahooo.com' => 'yahoo.com', 'outlok.com' => 'outlook.com'][$domain] ?? null;
        json_out(['ok' => true, 'valid' => true, 'exists' => (bool) $u, 'via' => $u ? array_keys(array_filter(['Google' => $u['google_id'], 'Microsoft' => $u['microsoft_id'], 'password' => $u['password_hash']])) : [], 'suggest' => $typo ? strstr($em, '@', true) . '@' . $typo : null]);

    case 'save':
        $u = require_login('seeker'); $jid = (int) input('job_id');
        if (!val('SELECT 1 FROM jobs WHERE id=?', [$jid])) json_out(['ok' => false, 'error' => 'Job not found'], 404);
        if (val('SELECT 1 FROM saved_jobs WHERE user_id=? AND job_id=?', [$u['id'], $jid])) { q('DELETE FROM saved_jobs WHERE user_id=? AND job_id=?', [$u['id'], $jid]); json_out(['ok' => true, 'saved' => false, 'message' => 'Removed from saved']); }
        insert('saved_jobs', ['user_id' => $u['id'], 'job_id' => $jid]);
        log_activity('job_save', 'job', $jid);
        json_out(['ok' => true, 'saved' => true, 'message' => 'Job saved']);

    case 'follow':
        $u = require_login('seeker'); $cid = (int) input('company_id');
        if (!val('SELECT 1 FROM companies WHERE id=?', [$cid])) json_out(['ok' => false, 'error' => 'Not found'], 404);
        if (val('SELECT 1 FROM company_follows WHERE user_id=? AND company_id=?', [$u['id'], $cid])) { q('DELETE FROM company_follows WHERE user_id=? AND company_id=?', [$u['id'], $cid]); $on = false; }
        else { insert('company_follows', ['user_id' => $u['id'], 'company_id' => $cid]); $on = true; log_activity('company_follow', 'company', $cid); }
        json_out(['ok' => true, 'following' => $on, 'count' => (int) val('SELECT COUNT(*) FROM company_follows WHERE company_id=?', [$cid]), 'message' => $on ? 'You’ll be notified about new jobs' : 'Unfollowed']);

    case 'alert':
        $u = require_login('seeker');
        if ((int) val('SELECT COUNT(*) FROM job_alerts WHERE user_id=?', [$u['id']]) >= 10) json_out(['ok' => false, 'error' => 'You can have up to 10 alerts.']);
        insert('job_alerts', ['user_id' => $u['id'], 'keyword' => mb_substr(input('keyword'), 0, 120) ?: null, 'category_id' => (int) input('category_id') ?: null,
            'location_id' => (int) input('location_id') ?: null, 'job_type' => isset(JOB_TYPES[input('job_type')]) ? input('job_type') : null, 'frequency' => 'daily', 'last_sent_at' => date('Y-m-d H:i:s')]);
        log_activity('alert_create', 'alert', null, input('keyword'));
        json_out(['ok' => true, 'message' => 'Alert created - we’ll email you daily matches.']);

    case 'ai':
        $u = require_login();
        if (!ai_enabled()) json_out(['ok' => false, 'error' => 'AI is not enabled.']);
        if (ai_remaining((int) $u['id']) <= 0) json_out(['ok' => false, 'error' => 'Daily AI limit reached. Try again tomorrow.']);
        $feature = input('feature');
        $allowed = ['job_description' => 'employer', 'company_about' => 'employer', 'improve' => null, 'summary' => 'seeker', 'cover_letter' => 'seeker', 'interview_tips' => 'seeker'];
        if (!array_key_exists($feature, $allowed)) json_out(['ok' => false, 'error' => 'Unknown feature'], 400);
        if ($allowed[$feature] && $u['role'] !== $allowed[$feature] && $u['role'] !== 'admin') json_out(['ok' => false, 'error' => 'Not available for your account.'], 403);
        $in = ['text' => input('text'), 'title' => input('title'), 'company' => input('company'), 'level' => input('level'), 'type' => input('type'), 'notes' => input('notes')];
        if ($feature === 'improve' && mb_strlen(trim($in['text'])) < 10) json_out(['ok' => false, 'error' => 'Write a few words first, then AI can improve it.']);
        if ($feature === 'job_description' && mb_strlen(trim($in['title'])) < 3) json_out(['ok' => false, 'error' => 'Enter the job title first.']);
        try { $r = ai_feature($feature, $in); } catch (Throwable $e) { json_out(['ok' => false, 'error' => $e->getMessage()]); }
        json_out(['ok' => true, 'data' => $r, 'remaining' => ai_remaining((int) $u['id'])]);

    case 'ai-search':
        if (!ai_enabled()) json_out(['ok' => false, 'error' => 'AI search unavailable']);
        $key = 'ais_' . client_ip();
        if ((int) val("SELECT COUNT(*) FROM ai_logs WHERE feature='smart_search' AND created_at > NOW() - INTERVAL 1 HOUR AND (user_id<=>?)", [uid()]) > 40) json_out(['ok' => false, 'error' => 'Too many AI searches. Use normal search.']);
        try { $f = ai_feature('smart_search', ['text' => input('text')]); } catch (Throwable $e) { json_out(['ok' => true, 'url' => url('jobs?q=' . urlencode(input('text')))]); }
        $qs = array_filter(['q' => $f['q'] ?? '', 'location' => $f['location'] ?? '', 'category' => $f['category'] ?? '', 'type' => $f['type'] ?? '', 'mode' => $f['mode'] ?? '', 'level' => $f['level'] ?? '', 'salary' => (int) ($f['salary'] ?? 0) ?: '']);
        json_out(['ok' => true, 'filters' => $qs, 'url' => url('jobs' . ($qs ? '?' . http_build_query($qs) : ''))]);

    case 'app-status':
        $u = require_login('employer');
        $a = employer_application((int) input('app_id'));
        if (!$a) json_out(['ok' => false, 'error' => 'Not found'], 404);
        $st = input('status');
        if ($st === 'interview') json_out(['ok' => false, 'error' => 'Open the applicant to set an interview time.', 'open' => url('employer/applicants/' . $a['id'])]);
        if (!isset(APP_STATUSES[$st]) || $st === 'withdrawn' || $a['status'] === 'withdrawn') json_out(['ok' => false, 'error' => 'Invalid status'], 400);
        if ($st !== $a['status']) change_application_status($a, $st, '', null, $st !== 'viewed');
        json_out(['ok' => true, 'message' => 'Moved to ' . APP_STATUSES[$st]]);

    case 'notification-open':
        $u = require_login();
        $n = row('SELECT * FROM notifications WHERE id=? AND user_id=?', [(int) ($_GET['id'] ?? 0), $u['id']]);
        if (!$n) redirect('notifications');
        if (!$n['read_at']) update('notifications', ['read_at' => date('Y-m-d H:i:s')], 'id=?', [$n['id']]);
        redirect($n['link'] ?: 'notifications');

    case 'notif-count':
        $u = current_user();
        json_out(['count' => $u ? (int) val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL', [$u['id']]) : 0]);

    case 'report':
        $jid = (int) input('job_id'); $reason = mb_substr(input('reason'), 0, 500);
        if (!$reason) json_out(['ok' => false, 'error' => 'Tell us what’s wrong.']);
        if ((int) val('SELECT COUNT(*) FROM job_reports WHERE ip=? AND created_at > NOW() - INTERVAL 1 DAY', [client_ip()]) >= 10) json_out(['ok' => false, 'error' => 'Too many reports today.']);
        if (!val('SELECT 1 FROM jobs WHERE id=?', [$jid])) json_out(['ok' => false, 'error' => 'Not found'], 404);
        insert('job_reports', ['job_id' => $jid, 'user_id' => uid(), 'reason' => $reason, 'ip' => client_ip()]);
        notify_admins('report', 'Job reported', $reason, 'admin/reports');
        log_activity('job_report', 'job', $jid, $reason);
        json_out(['ok' => true, 'message' => 'Thanks - our team will review this job.']);

    case 'track-apply':
        $jid = (int) ($_GET['job'] ?? 0);
        if ($jid) log_activity('external_apply_click', 'job', $jid);
        json_out(['ok' => true]);
}
json_out(['ok' => false, 'error' => 'Unknown endpoint'], 404);
