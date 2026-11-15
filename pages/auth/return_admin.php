<?php
if (!is_post() || !($adminId = impersonator())) redirect('');
csrf_check();
$back = uid();
log_activity('impersonate_end', 'user', $back);
unset($_SESSION['impersonator']);
session_regenerate_id(true);
$_SESSION['uid'] = $adminId;
flash('success', 'Back in admin.');
redirect($back ? 'admin/user/' . $back : 'admin');
