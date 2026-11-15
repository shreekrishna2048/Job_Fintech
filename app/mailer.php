<?php
/** Render an email template from DB and queue it. $priority sends right after the response. */
function queue_template_email(string $to, string $name, string $key, array $vars, bool $priority = false, array $attachments = []): bool {
    try {
        $t = row('SELECT * FROM email_templates WHERE `key`=? AND enabled=1', [$key]);
        if (!$t) return false;
        $u = row('SELECT email_notifications FROM users WHERE email=?', [$to]);
        $critical = in_array($key, ['verify_email', 'password_reset', 'login_alert', 'welcome'], true);
        if ($u && !$u['email_notifications'] && !$critical) return false;
        $vars += ['site_name' => setting('site_name', 'KaamKhoj'), 'site_url' => url('')];
        $rep = function (string $s, bool $html) use ($vars) {
            return preg_replace_callback('/\{(\w+)\}/', function ($m) use ($vars, $html) {
                if (!array_key_exists($m[1], $vars)) return $m[0];
                $v = (string) $vars[$m[1]];
                return ($html && !in_array($m[1], ['jobs', 'link', 'dashboard_url', 'site_url'], true)) ? nl2br(e($v)) : $v;
            }, $s);
        };
        queue_email($to, $name, $rep($t['subject'], false), email_layout($rep($t['body'], true)), $key, $attachments);
        return true;
    } catch (Throwable $e) { error_log('queue email: ' . $e->getMessage()); return false; }
}

/** Placeholder owners of imported companies use @imported.invalid - never mail them */
function is_placeholder_email(?string $e): bool { return $e !== null && str_ends_with(strtolower($e), '@imported.invalid'); }
/** $attachments: list of ['path' => absolute path, 'name' => file name] */
function queue_email(string $to, string $name, string $subject, string $html, ?string $template = null, array $attachments = []): void {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || is_placeholder_email($to)) return;
    insert('email_queue', ['to_email' => $to, 'to_name' => $name, 'subject' => mb_substr($subject, 0, 255), 'body_html' => $html, 'template' => $template, 'attachments' => $attachments ? json_encode($attachments, JSON_UNESCAPED_UNICODE) : null]);
    $GLOBALS['mail_pending'] = true;
}

function email_layout(string $content): string {
    $site = e(setting('site_name', 'KaamKhoj')); $color = e(setting('primary_color', '#e11d48')); $u = e(url(''));
    $year = date('Y');
    return <<<HTML
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;background:#f4f4f6;font-family:Segoe UI,Helvetica,Arial,sans-serif;color:#1c1b22">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f6;padding:28px 12px"><tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:14px;overflow:hidden">
<tr><td style="padding:22px 28px;border-bottom:1px solid #eee"><a href="$u" style="font-size:20px;font-weight:800;color:#1c1b22;text-decoration:none">$site<span style="color:$color">.</span></a></td></tr>
<tr><td style="padding:26px 28px;font-size:15px;line-height:1.6">
<style>.btn{display:inline-block;background:$color;color:#fff!important;padding:11px 22px;border-radius:9px;text-decoration:none;font-weight:600}</style>
$content
</td></tr>
<tr><td style="padding:18px 28px;background:#fafafa;font-size:12px;color:#888">© $year $site · You get this email because you have an account. Manage email preferences in your account settings.</td></tr>
</table></td></tr></table></body></html>
HTML;
}

function process_email_queue(int $limit = 20): array {
    $sent = 0; $failed = 0;
    // Claim rows atomically-ish to avoid double sending between cron and shutdown handler
    $items = rows("SELECT * FROM email_queue WHERE status='pending' AND attempts < 3 ORDER BY id LIMIT " . (int) $limit);
    foreach ($items as $m) {
        if (!q("UPDATE email_queue SET attempts=attempts+1 WHERE id=? AND attempts=?", [$m['id'], $m['attempts']])->rowCount()) continue;
        try {
            send_mail_now($m['to_email'], $m['to_name'] ?? '', $m['subject'], $m['body_html'], json_decode((string) ($m['attachments'] ?? ''), true) ?: []);
            update('email_queue', ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'), 'error' => null], 'id=?', [$m['id']]);
            $sent++;
        } catch (Throwable $e) {
            update('email_queue', ['status' => $m['attempts'] + 1 >= 3 ? 'failed' : 'pending', 'error' => mb_substr($e->getMessage(), 0, 500)], 'id=?', [$m['id']]);
            $failed++;
        }
    }
    return ['sent' => $sent, 'failed' => $failed];
}

function send_mail_now(string $to, string $toName, string $subject, string $html, array $attachments = []): void {
    $fromEmail = setting('mail_from_email', setting('smtp_user', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $fromName = setting('mail_from_name', setting('site_name', 'KaamKhoj'));
    $text = html_to_text(preg_replace('#<style.*?</style>#s', '', $html));
    $boundary = 'b' . bin2hex(random_bytes(8));
    $encName = fn($n) => '=?UTF-8?B?' . base64_encode($n) . '?=';
    $headers = [
        'From: ' . $encName($fromName) . " <$fromEmail>",
        'To: ' . ($toName ? $encName($toName) . ' ' : '') . "<$to>",
        'Subject: ' . $encName($subject),
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . (explode('@', $fromEmail)[1] ?? 'localhost') . '>',
        'MIME-Version: 1.0',
    ];
    $alt = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
        . "--$boundary--\r\n";
    $files = array_filter($attachments, fn($a) => !empty($a['path']) && is_file($a['path']) && filesize($a['path']) < 10485760);
    if ($files) {
        $mix = 'm' . bin2hex(random_bytes(8));
        $headers[] = "Content-Type: multipart/mixed; boundary=\"$mix\"";
        $body = "--$mix\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n" . $alt;
        foreach ($files as $a) {
            $fn = preg_replace('/[^\w .()-]/u', '_', $a['name'] ?? basename($a['path']));
            $mime = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'][strtolower(pathinfo($a['path'], PATHINFO_EXTENSION))] ?? 'application/octet-stream';
            $body .= "--$mix\r\nContent-Type: $mime; name=\"" . $encName($fn) . "\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"" . $encName($fn) . "\"\r\n\r\n" . chunk_split(base64_encode(file_get_contents($a['path'])));
        }
        $body .= "--$mix--\r\n";
    } else {
        $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";
        $body = $alt;
    }

    $host = setting('smtp_host');
    if (!$host) { // PHP mail() fallback (cPanel sendmail)
        $h = array_filter($headers, fn($x) => !str_starts_with($x, 'To:') && !str_starts_with($x, 'Subject:'));
        if (!@mail($to, $encName($subject), $body, implode("\r\n", $h), '-f' . $fromEmail)) throw new RuntimeException('mail() failed - configure SMTP in Admin › Settings.');
        return;
    }
    smtp_send($host, (int) setting('smtp_port', '587'), setting('smtp_secure', 'tls'), setting('smtp_user'), setting('smtp_pass'), $fromEmail, $to, implode("\r\n", $headers) . "\r\n\r\n" . $body);
}

function smtp_send(string $host, int $port, string $secure, string $user, string $pass, string $from, string $to, string $data): void {
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $s = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$s) throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
    stream_set_timeout($s, 20);
    $read = function () use ($s) { $r = ''; while (($l = fgets($s, 515)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $r; };
    $cmd = function (string $c, array $ok) use ($s, $read) {
        if ($c !== '') fwrite($s, $c . "\r\n");
        $r = $read();
        if (!in_array((int) substr($r, 0, 3), $ok, true)) throw new RuntimeException('SMTP error: ' . trim($r ?: 'no response') . ' (after ' . (str_starts_with($c, 'AUTH') || strlen($c) > 60 ? 'AUTH' : $c) . ')');
        return $r;
    };
    $ehloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $cmd('', [220]);
    $cmd("EHLO $ehloHost", [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('STARTTLS failed');
        $cmd("EHLO $ehloHost", [250]);
    }
    if ($user !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($user), [334]);
        $cmd(base64_encode($pass), [235]);
    }
    $cmd("MAIL FROM:<$from>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);
    $data = preg_replace('/^\./m', '..', $data);
    $cmd($data . "\r\n.", [250]);
    @fwrite($s, "QUIT\r\n");
    fclose($s);
}
