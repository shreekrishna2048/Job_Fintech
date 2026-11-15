<?php
/** Side effects when a job becomes publicly visible for the first time */
function on_job_published(int $jobId): void {
    $j = row(JOB_SELECT . ' WHERE j.id=?', [$jobId]);
    if (!$j || $j['status'] !== 'active') return;
    $link = url('job/' . $j['slug']);
    seo_queue($link);
    // 1) followers of the company
    foreach (rows("SELECT u.id, u.name, u.email FROM company_follows f JOIN users u ON u.id=f.user_id WHERE f.company_id=? AND u.status='active'", [$j['company_id']]) as $f) {
        notify((int) $f['id'], 'new_job', "{$j['company_name']} is hiring", $j['title'], 'job/' . $j['slug']);
        queue_template_email($f['email'], $f['name'], 'new_job_followed', ['name' => $f['name'], 'company_name' => $j['company_name'], 'job_title' => $j['title'], 'link' => $link]);
    }
    // 2) instant job alerts
    foreach (rows("SELECT a.*, u.name, u.email FROM job_alerts a JOIN users u ON u.id=a.user_id WHERE a.active=1 AND a.frequency='instant' AND u.status='active'") as $a) {
        if (!alert_matches($a, $j)) continue;
        notify((int) $a['user_id'], 'job_alert', 'New job: ' . $j['title'], $j['company_name'], 'job/' . $j['slug']);
        queue_template_email($a['email'], $a['name'], 'job_alert', ['name' => $a['name'], 'count' => 1, 'alert' => $a['keyword'] ?: 'your alert', 'jobs' => alert_jobs_html([$j]), 'link' => $link]);
        update('job_alerts', ['last_sent_at' => date('Y-m-d H:i:s')], 'id=?', [$a['id']]);
    }
}

function alert_matches(array $a, array $j): bool {
    if ($a['category_id'] && $a['category_id'] != $j['category_id']) return false;
    if ($a['location_id'] && $a['location_id'] != $j['location_id'] && $j['work_mode'] !== 'remote') return false;
    if ($a['job_type'] && $a['job_type'] !== $j['job_type']) return false;
    if ($a['keyword']) {
        $hay = mb_strtolower($j['title'] . ' ' . $j['skills'] . ' ' . $j['company_name']);
        foreach (preg_split('/\s+/', mb_strtolower($a['keyword'])) as $w) if ($w !== '' && !str_contains($hay, $w)) return false;
    }
    return true;
}

function alert_jobs_html(array $jobs): string {
    $h = '';
    foreach ($jobs as $j) $h .= '<div style="padding:12px 0;border-bottom:1px solid #eee"><a href="' . e(url('job/' . $j['slug'])) . '" style="font-weight:700;color:#1c1b22;text-decoration:none">' . e($j['title']) . '</a><br><span style="color:#666;font-size:13px">' . e($j['company_name']) . ' · ' . e($j['location_name'] ?? '') . ' · ' . e(salary_text($j)) . '</span></div>';
    return $h;
}

const STATUS_MESSAGES = [
    'viewed' => 'The employer has viewed your application.',
    'shortlisted' => 'Great news - you have been shortlisted! The employer may contact you soon.',
    'interview' => 'You are invited for an interview.',
    'offered' => 'Congratulations! The employer would like to make you an offer.',
    'hired' => 'Congratulations on your new job! 🎉',
    'rejected' => 'Thank you for your interest. The employer has decided to move forward with other candidates this time. Keep going - new jobs are posted every day.',
];

function change_application_status(array $a, string $status, string $note = '', ?string $interviewAt = null, bool $email = true): void {
    if (!isset(APP_STATUSES[$status]) || $status === 'withdrawn') return;
    $upd = ['status' => $status];
    if ($status === 'interview' && $interviewAt) $upd['interview_at'] = date('Y-m-d H:i:s', strtotime($interviewAt));
    update('applications', $upd, 'id=?', [$a['id']]);
    $msg = STATUS_MESSAGES[$status] ?? '';
    if ($status === 'interview' && !empty($upd['interview_at'])) $msg .= ' When: ' . date('l, d M Y \a\t h:i A', strtotime($upd['interview_at'])) . '.';
    if ($note !== '') $msg .= "\n\nMessage from the employer: " . $note;
    insert('application_events', ['application_id' => $a['id'], 'status' => $status, 'note' => mb_substr($note ?: $msg, 0, 500), 'actor_id' => uid()]);
    $info = row('SELECT u.id, u.name, u.email, j.title, c.name company_name FROM applications a JOIN users u ON u.id=a.user_id JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.id=?', [$a['id']]);
    if (!$info) return;
    notify((int) $info['id'], 'status', APP_STATUSES[$status] . ': ' . $info['title'], $info['company_name'], 'seeker/applications/' . $a['id']);
    if ($email) queue_template_email($info['email'], $info['name'], 'application_status', ['name' => $info['name'], 'job_title' => $info['title'], 'company_name' => $info['company_name'], 'status' => APP_STATUSES[$status], 'message' => $msg, 'link' => url('seeker/applications/' . $a['id'])]);
    log_activity('application_status', 'application', (int) $a['id'], "→ $status");
}

/** Load an application only if the current employer owns the job (admins can see all) */
function employer_application(int $id): ?array {
    $u = current_user();
    return row("SELECT a.*, j.title job_title, j.slug job_slug, j.skills job_skills, j.experience_min, j.experience_max, j.location_id job_location_id, j.work_mode, j.id job_id, c.owner_id, c.name company_name
        FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.id=?" . ($u['role'] === 'admin' ? '' : ' AND c.owner_id=' . (int) $u['id']), [$id]);
}
