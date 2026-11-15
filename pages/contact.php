<?php
$sent = false;
if (is_post()) {
    csrf_check();
    $d = ['name' => mb_substr(input('name'), 0, 120), 'email' => mb_substr(input('email'), 0, 190), 'subject' => mb_substr(input('subject'), 0, 190), 'message' => mb_substr(input('message'), 0, 5000)];
    $recent = (int) val('SELECT COUNT(*) FROM contact_messages WHERE ip=? AND created_at > NOW() - INTERVAL 1 HOUR', [client_ip()]);
    if (input('website') !== '') { $sent = true; } // honeypot
    elseif ($recent >= 5) flash('error', 'Too many messages. Please try later.');
    elseif (!$d['name'] || !filter_var($d['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($d['message']) < 10) { flash('error', 'Please fill in your name, a valid email and a message.'); keep_old(); }
    else {
        $id = insert('contact_messages', $d + ['ip' => client_ip()]);
        log_activity('contact', 'contact', $id, $d['subject']);
        notify_admins('contact', 'New contact message', $d['name'] . ': ' . $d['subject'], 'admin/messages');
        if ($to = setting('contact_email')) queue_template_email($to, 'Admin', 'contact_admin', $d);
        $sent = true;
    }
}
layout_start(['title' => 'Contact us', 'description' => 'Get in touch with the ' . setting('site_name') . ' team.']);
$u = current_user();
?>
<section class="list-hero list-hero-photo" style="<?= bg_img(site_image('img_page_header', 1600), 'linear-gradient(90deg,rgba(250,248,245,.97) 45%,rgba(250,248,245,.7))') ?>"><div class="wrap"><h1>Contact us</h1><p class="hero-sub">Questions, feedback or partnership - we reply within one working day.</p></div></section>
<div class="wrap sec contact-grid">
  <div class="card">
    <?php if ($sent): ?><div class="empty"><?= icon('check', 'i i-xl') ?><h3>Message sent</h3><p>Thanks! We’ll get back to you soon.</p></div>
    <?php else: ?>
    <form method="post" class="form" data-loading><?= csrf_field() ?>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
      <div class="row2"><div class="field"><label>Name</label><input name="name" required value="<?= e(old('name', $u['name'] ?? '')) ?>"></div><div class="field"><label>Email</label><input type="email" name="email" required value="<?= e(old('email', $u['email'] ?? '')) ?>"></div></div>
      <div class="field"><label>Subject</label><input name="subject" value="<?= e(old('subject', mb_substr((string) ($_GET['subject'] ?? ''), 0, 190))) ?>"></div>
      <div class="field"><label>Message</label><textarea name="message" rows="6" required><?= e(old('message')) ?></textarea></div>
      <button class="btn btn-brand">Send message</button>
    </form>
    <?php endif; ?>
  </div>
  <div class="card contact-info">
    <?php if ($v = setting('contact_email')): ?><p><?= icon('mail') ?><a href="mailto:<?= e($v) ?>"><?= e($v) ?></a></p><?php endif; ?>
    <?php if ($v = setting('contact_phone')): ?><p><?= icon('phone') ?><a href="tel:<?= e(preg_replace('/\s/', '', $v)) ?>"><?= e($v) ?></a></p><?php endif; ?>
    <p><?= icon('pin') ?><?= e(setting('contact_address', 'Nepal')) ?></p>
  </div>
</div>
<?php layout_end();
