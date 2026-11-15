<?php
function run_cron_tasks(): string {
    $log = [];
    // Lock so overlapping runs don't double-send
    if (!val("SELECT GET_LOCK('kk_cron', 1)")) return "Another cron run is in progress.\n";

    // 1) Close jobs past their deadline
    foreach (rows("SELECT slug FROM jobs WHERE status='active' AND deadline < CURDATE() LIMIT 500") as $x) seo_queue(url('job/' . $x['slug']), 'URL_DELETED');
    $n = q("UPDATE jobs SET status='closed' WHERE status='active' AND deadline < CURDATE()")->rowCount();
    $log[] = "Closed $n expired jobs";

    // 2) Remind employers 3 days before deadline (once)
    $c = 0;
    foreach (rows(JOB_SELECT . " WHERE j.status='active' AND j.deadline = CURDATE() + INTERVAL 3 DAY") as $j) {
        if (val("SELECT 1 FROM activity_logs WHERE action='job_expiring_mail' AND entity_id=?", [$j['id']])) continue;
        $o = row('SELECT u.* FROM users u JOIN companies c ON c.owner_id=u.id WHERE c.id=?', [$j['company_id']]);
        if ($o) {
            queue_template_email($o['email'], $o['name'], 'job_expiring', ['name' => $o['name'], 'job_title' => $j['title'], 'days' => 3, 'applications' => $j['applications_count'], 'link' => url('employer/jobs')]);
            notify((int) $o['id'], 'job_expiring', 'Job closes in 3 days', $j['title'], 'employer/jobs');
        }
        log_activity('job_expiring_mail', 'job', (int) $j['id'], null, 0); $c++;
    }
    $log[] = "Deadline reminders: $c";

    // 3) Interview reminders (day before, once)
    $c = 0;
    foreach (rows("SELECT a.id, a.user_id, a.interview_at, j.title, co.name company FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies co ON co.id=j.company_id WHERE a.status='interview' AND a.interview_at BETWEEN NOW() + INTERVAL 20 HOUR AND NOW() + INTERVAL 28 HOUR") as $a) {
        if (val("SELECT 1 FROM activity_logs WHERE action='interview_reminder' AND entity_id=?", [$a['id']])) continue;
        notify((int) $a['user_id'], 'status', 'Interview tomorrow: ' . $a['title'], $a['company'] . ' · ' . date('h:i A', strtotime($a['interview_at'])), 'seeker/applications/' . $a['id']);
        log_activity('interview_reminder', 'application', (int) $a['id'], null, 0); $c++;
    }
    $log[] = "Interview reminders: $c";

    // 4) Job alert digests
    $c = 0;
    $due = rows("SELECT a.*, u.name, u.email FROM job_alerts a JOIN users u ON u.id=a.user_id WHERE a.active=1 AND u.status='active' AND u.email_notifications=1 AND (
        (a.frequency='daily' AND (a.last_sent_at IS NULL OR a.last_sent_at < NOW() - INTERVAL 23 HOUR)) OR
        (a.frequency='weekly' AND (a.last_sent_at IS NULL OR a.last_sent_at < NOW() - INTERVAL 7 DAY))) LIMIT 300");
    foreach ($due as $a) {
        $since = $a['last_sent_at'] ?: date('Y-m-d H:i:s', strtotime('-1 day'));
        $jobs = array_values(array_filter(rows(JOB_SELECT . ' WHERE ' . JOB_LIVE . ' AND j.published_at > ? ORDER BY j.published_at DESC LIMIT 100', [$since]), fn($j) => alert_matches($a, $j)));
        update('job_alerts', ['last_sent_at' => date('Y-m-d H:i:s')], 'id=?', [$a['id']]);
        if (!$jobs) continue;
        $qs = array_filter(['q' => $a['keyword'], 'category' => $a['category_id'] ? val('SELECT slug FROM categories WHERE id=?', [$a['category_id']]) : null, 'location' => $a['location_id'] ? val('SELECT slug FROM locations WHERE id=?', [$a['location_id']]) : null]);
        queue_template_email($a['email'], $a['name'], 'job_alert', ['name' => $a['name'], 'count' => count($jobs), 'alert' => $a['keyword'] ?: 'your alert', 'jobs' => alert_jobs_html(array_slice($jobs, 0, 10)), 'link' => url('jobs?' . http_build_query($qs))]);
        notify((int) $a['user_id'], 'job_alert', count($jobs) . ' new jobs for “' . ($a['keyword'] ?: 'your alert') . '”', '', 'jobs?' . http_build_query($qs));
        $c++;
    }
    $log[] = "Alert digests sent: $c";

    // 5) Housekeeping
    q('DELETE FROM user_tokens WHERE expires_at < NOW()');
    q("DELETE FROM email_queue WHERE status='sent' AND sent_at < NOW() - INTERVAL 90 DAY");

    // 5b) One-time: store stock photos on this server
    if (!setting('images_localized')) { $r = localize_all_images(); $log[] = "Images saved locally: {$r['ok']}"; }

    // 6) Send emails
    $r = process_email_queue(150);
    $log[] = "Emails: {$r['sent']} sent, {$r['failed']} failed";

    seo_flush();
    save_setting('cron_last_run', date('Y-m-d H:i:s'));
    val("SELECT RELEASE_LOCK('kk_cron')");
    return implode("\n", $log) . "\n";
}
