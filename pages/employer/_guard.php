<?php
// Shared: require employer + company (creates one if missing)
$u = require_login('employer');
$company = employer_company((int) $u['id']);
if (!$company && $u['role'] === 'employer') {
    $name = $u['name'] . "'s Company";
    insert('companies', ['owner_id' => $u['id'], 'name' => $name, 'slug' => unique_slug('companies', $name), 'email' => $u['email']]);
    $company = employer_company((int) $u['id']);
}
if ($u['role'] === 'admin' && !$company) redirect('admin');
if ($company && $company['status'] === 'suspended' && !in_array(current_path(), ['/employer', '/employer/company'], true)) { flash('error', 'Your company account is suspended. Contact support.'); redirect('employer'); }
