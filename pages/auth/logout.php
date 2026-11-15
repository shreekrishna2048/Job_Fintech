<?php
if (is_post() && impersonator()) { require __DIR__ . '/return_admin.php'; exit; }
if (is_post()) { csrf_check(); logout_user(); flash('success', 'You’re signed out.'); }
redirect('');
