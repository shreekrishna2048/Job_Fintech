<?php
/* KaamKhoj installer - run once, then delete this file. */
declare(strict_types=1);
session_start();
$root = __DIR__;
if (is_file("$root/config.php")) { http_response_code(403); exit('Already installed. Delete config.php to reinstall (this wipes nothing in the database). For safety, delete install.php.'); }

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$guess = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$checks = [
    'PHP 8.0+' => version_compare(PHP_VERSION, '8.0.0', '>='), 'PDO MySQL' => extension_loaded('pdo_mysql'), 'cURL' => extension_loaded('curl'),
    'mbstring' => extension_loaded('mbstring'), 'GD (image resize)' => extension_loaded('gd'),
    'OpenSSL' => extension_loaded('openssl'), 'Writable root (for config.php)' => is_writable($root), 'Writable uploads/' => is_writable("$root/uploads"),
];
$err = ''; $done = false;

// Test defaults - the cPanel username is detected from the folder path (/home/USERNAME/...)
$cpUser = preg_match('#^/home\d?/([^/]+)/#', $root, $mm) ? $mm[1] : 'cpaneluser';
$defaults = [
    'db_host' => 'localhost', 'db_name' => $cpUser . '_kaamkhoj', 'db_user' => $cpUser . '_kkuser', 'db_pass' => 'Kk1fd21a66e1#Np26',
    'site_name' => 'KaamKhoj', 'admin_name' => 'Shree', 'admin_email' => 'shree@meetshree.com', 'admin_pass' => 'Admin@6c9646',
];
$v = fn($k, $d = '') => htmlspecialchars((string) ($_POST[$k] ?? ($defaults[$k] ?? $d)), ENT_QUOTES);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = ['host' => trim($_POST['db_host'] ?? 'localhost'), 'name' => trim($_POST['db_name'] ?? ''), 'user' => trim($_POST['db_user'] ?? ''), 'pass' => (string) ($_POST['db_pass'] ?? '')];
    $base = rtrim(trim($_POST['base_url'] ?? $guess), '/');
    $aName = trim($_POST['admin_name'] ?? ''); $aEmail = strtolower(trim($_POST['admin_email'] ?? '')); $aPass = (string) ($_POST['admin_pass'] ?? '');
    $site = trim($_POST['site_name'] ?? 'KaamKhoj');
    try {
        if (in_array(false, $checks, true)) throw new RuntimeException('Fix the failed requirements first.');
        if (!filter_var($aEmail, FILTER_VALIDATE_EMAIL) || strlen($aPass) < 8 || !$aName) throw new RuntimeException('Enter admin name, a valid email and a password of 8+ characters.');
        if (!filter_var($base, FILTER_VALIDATE_URL)) throw new RuntimeException('Site URL is invalid.');
        $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach (['schema.sql', 'seed.sql'] as $f) {
            $sql = file_get_contents("$root/sql/$f");
            $sql = preg_replace('/^--.*$/m', '', $sql);
            foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql))) as $stmt) $pdo->exec($stmt);
        }
        $st = $pdo->prepare("INSERT INTO users (role,name,email,password_hash,email_verified_at) VALUES ('admin',?,?,?,NOW()) ON DUPLICATE KEY UPDATE role='admin', password_hash=VALUES(password_hash)");
        $st->execute([$aName, $aEmail, password_hash($aPass, PASSWORD_DEFAULT)]);
        $set = $pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
        foreach (['site_name' => $site, 'mail_from_name' => $site, 'contact_email' => $aEmail, 'cron_key' => bin2hex(random_bytes(16))] as $k => $val) $set->execute([$k, $val]);
        $config = "<?php\nreturn " . var_export([
            'db' => $db, 'base_url' => $base, 'timezone' => 'Asia/Kathmandu', 'debug' => false, 'app_key' => bin2hex(random_bytes(32)),
        ], true) . ";\n";
        if (file_put_contents("$root/config.php", $config) === false) throw new RuntimeException('Could not write config.php - check folder permissions.');
        @chmod("$root/config.php", 0640);
        $done = true;
    } catch (Throwable $e) { $err = $e->getMessage(); }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Install KaamKhoj</title>
<style>
*{box-sizing:border-box}body{margin:0;font:15px/1.55 system-ui,-apple-system,Segoe UI,sans-serif;background:#f6f4f1;color:#16141d}
.w{max-width:640px;margin:40px auto;padding:0 16px}.c{background:#fff;border-radius:16px;padding:28px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
h1{margin:0 0 4px;font-size:26px}h2{font-size:16px;margin:24px 0 10px}label{display:block;font-weight:600;margin:12px 0 4px;font-size:14px}
input{width:100%;padding:11px 12px;border:1px solid #ddd;border-radius:10px;font:inherit}input:focus{outline:2px solid #e11d48;border-color:transparent}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:560px){.row{grid-template-columns:1fr}}
button{margin-top:22px;width:100%;padding:13px;border:0;border-radius:10px;background:#e11d48;color:#fff;font:600 16px system-ui;cursor:pointer}
.ok{color:#15803d}.no{color:#b91c1c}.chk{display:grid;grid-template-columns:1fr 1fr;gap:4px 16px;font-size:14px}.err{background:#fee2e2;color:#991b1b;padding:12px;border-radius:10px;margin:14px 0}
.succ{background:#dcfce7;color:#14532d;padding:16px;border-radius:10px}code{background:#f1f0ee;padding:2px 6px;border-radius:5px;font-size:13px}a.b{display:inline-block;margin-top:12px;background:#16141d;color:#fff;padding:11px 18px;border-radius:10px;text-decoration:none}
</style></head><body><div class="w"><div class="c">
<h1>Install KaamKhoj</h1><p style="color:#666;margin:0">Job portal · PHP + MySQL</p>
<?php if ($done): ?>
  <div class="succ" style="margin-top:20px"><b>✅ Installed!</b><br>Admin login: <code><?= htmlspecialchars($aEmail) ?></code> / <code><?= htmlspecialchars($aPass) ?></code><br><br>1. <b>Delete <code>install.php</code></b> from the server now.<br>2. Sign in as admin and open Settings to add SMTP, Google/Microsoft login and your Groq AI key.<br>3. Add the cron job shown in Admin › Settings › Cron.</div>
  <a class="b" href="<?= htmlspecialchars($base) ?>/login">Go to sign in →</a>
<?php else: ?>
  <h2>Requirements</h2><div class="chk"><?php foreach ($checks as $k => $ok): ?><span class="<?= $ok ? 'ok' : 'no' ?>"><?= $ok ? '✓' : '✗' ?> <?= $k ?></span><?php endforeach; ?></div>
  <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px 14px;margin-top:16px;font-size:14px">
    <b>Before clicking Install</b>, create this in cPanel › <b>MySQL® Databases</b>:<br>
    1. New database: <code><?= $v('db_name') ?></code><br>
    2. New user: <code><?= $v('db_user') ?></code> · password <code><?= $v('db_pass') ?></code><br>
    3. “Add user to database” → tick <b>ALL PRIVILEGES</b>.<br>
    Everything below is already filled - just click <b>Install now</b>. Change the passwords after testing.
  </div>
  <form method="post">
    <h2>Database (create it in cPanel › MySQL Databases first)</h2>
    <div class="row"><div><label>DB host</label><input name="db_host" value="<?= $v('db_host', 'localhost') ?>" required></div><div><label>DB name</label><input name="db_name" value="<?= $v('db_name') ?>" placeholder="cpaneluser_jobs" required></div></div>
    <div class="row"><div><label>DB user</label><input name="db_user" value="<?= $v('db_user') ?>" required></div><div><label>DB password</label><input name="db_pass" value="<?= $v('db_pass') ?>"></div></div>
    <h2>Site</h2>
    <label>Site URL (no trailing slash)</label><input name="base_url" value="<?= $v('base_url', $guess) ?>" required>
    <label>Site name</label><input name="site_name" value="<?= $v('site_name', 'KaamKhoj') ?>" required>
    <h2>Admin account</h2>
    <div class="row"><div><label>Your name</label><input name="admin_name" value="<?= $v('admin_name') ?>" required></div><div><label>Admin email</label><input name="admin_email" type="email" value="<?= $v('admin_email') ?>" required></div></div>
    <label>Admin password (8+ characters)</label><input name="admin_pass" minlength="8" required value="<?= $v('admin_pass') ?>">
    <button>Install now</button>
  </form>
<?php endif; ?>
</div></div></body></html>
