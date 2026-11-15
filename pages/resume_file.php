<?php
$u = require_login();
$id = (int) param('id');
if (param('type') === 'app') {
    $a = row('SELECT a.resume_path, a.resume_name, a.user_id, a.id, a.status, c.owner_id FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.id=?', [$id]);
    if (!$a || !($u['role'] === 'admin' || $u['id'] == $a['owner_id'] || $u['id'] == $a['user_id'])) abort(403);
    $path = $a['resume_path']; $name = $a['resume_name'];
    if ($u['id'] == $a['owner_id']) log_activity('resume_download', 'application', $id);
} else {
    $p = row('SELECT sp.resume_path, sp.resume_name, sp.visible_to_employers, sp.user_id, u.name FROM seeker_profiles sp JOIN users u ON u.id=sp.user_id WHERE sp.user_id=?', [$id]);
    if (!$p) abort(404);
    $applied = $u['role'] === 'employer' && val('SELECT 1 FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND c.owner_id=? LIMIT 1', [$id, $u['id']]);
    if (!($u['role'] === 'admin' || $u['id'] == $id || $applied || ($u['role'] === 'employer' && $p['visible_to_employers']))) abort(403);
    $path = $p['resume_path']; $name = $p['resume_name'] ?: ($p['name'] . ' CV');
    if ($u['role'] === 'employer') log_activity('resume_download', 'seeker', $id);
}
$file = ROOT . '/uploads/' . $path;
if (!$path || str_contains($path, '..') || !is_file($file)) abort(404, 'File not found.');
$ext = pathinfo($file, PATHINFO_EXTENSION);
$mime = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'][$ext] ?? 'application/octet-stream';
$dl = preg_replace('/[^\w .-]/u', '', pathinfo($name ?: 'resume', PATHINFO_FILENAME)) . '.' . $ext;
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: ' . ($ext === 'pdf' && !isset($_GET['dl']) ? 'inline' : 'attachment') . '; filename="' . $dl . '"');
header('Cache-Control: private, no-store');
readfile($file);
