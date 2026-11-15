<?php
function oauth_providers(): array {
    $p = [];
    if (setting('google_enabled', '1') === '1' && setting('google_client_id') && setting('google_client_secret')) $p['google'] = 'Google';
    if (setting('microsoft_enabled', '1') === '1' && setting('microsoft_client_id') && setting('microsoft_client_secret')) $p['microsoft'] = 'Microsoft';
    return $p;
}
function oauth_configured(string $p): bool { return setting($p . '_client_id') !== '' && setting($p . '_client_secret') !== ''; }
/** Friendly text for provider error codes */
function oauth_error_text(string $code, string $desc = ''): string {
    return match ($code) {
        'access_denied' => 'Sign-in was cancelled.',
        'invalid_client', 'unauthorized_client' => 'Login keys are wrong. Admin: check the Client ID/Secret in Admin › Login & sign-up.',
        'redirect_uri_mismatch' => 'Redirect URI is not registered with the provider. Admin: copy it from Admin › Login & sign-up.',
        'invalid_grant' => 'The sign-in code expired. Please try again.',
        default => 'Sign-in failed' . ($desc ? ': ' . mb_substr($desc, 0, 160) : '.'),
    };
}
function oauth_redirect_uri(string $provider): string { return url("auth/$provider/callback"); }

function oauth_auth_url(string $provider): string {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    if ($provider === 'google') {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => setting('google_client_id'), 'redirect_uri' => oauth_redirect_uri('google'), 'response_type' => 'code',
            'scope' => 'openid email profile', 'state' => $state, 'prompt' => 'select_account', 'access_type' => 'online',
        ]);
    }
    $tenant = setting('microsoft_tenant', 'common');
    return "https://login.microsoftonline.com/$tenant/oauth2/v2.0/authorize?" . http_build_query([
        'client_id' => setting('microsoft_client_id'), 'redirect_uri' => oauth_redirect_uri('microsoft'), 'response_type' => 'code',
        'scope' => 'openid email profile User.Read', 'state' => $state, 'response_mode' => 'query', 'prompt' => 'select_account',
    ]);
}

function http_json(string $url, ?array $post = null, array $headers = []): array {
    $ch = curl_init($url);
    $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers)];
    if ($post !== null) { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = http_build_query($post); }
    curl_setopt_array($ch, $opt);
    $res = curl_exec($ch); curl_close($ch);
    return json_decode((string) $res, true) ?: [];
}

/** Exchange code -> ['id','email','name','avatar_url'] */
function oauth_fetch_user(string $provider, string $code): array {
    if ($provider === 'google') {
        $tok = http_json('https://oauth2.googleapis.com/token', [
            'code' => $code, 'client_id' => setting('google_client_id'), 'client_secret' => setting('google_client_secret'),
            'redirect_uri' => oauth_redirect_uri('google'), 'grant_type' => 'authorization_code',
        ]);
        if (empty($tok['access_token'])) throw new RuntimeException(oauth_error_text($tok['error'] ?? '', $tok['error_description'] ?? 'Could not reach Google'));
        $u = http_json('https://openidconnect.googleapis.com/v1/userinfo', null, ['Authorization: Bearer ' . $tok['access_token']]);
        if (empty($u['email']) || empty($u['email_verified'])) throw new RuntimeException('Google account email is not verified.');
        return ['id' => $u['sub'], 'email' => $u['email'], 'name' => $u['name'] ?? $u['email'], 'verified' => true];
    }
    $tenant = setting('microsoft_tenant', 'common');
    $tok = http_json("https://login.microsoftonline.com/$tenant/oauth2/v2.0/token", [
        'code' => $code, 'client_id' => setting('microsoft_client_id'), 'client_secret' => setting('microsoft_client_secret'),
        'redirect_uri' => oauth_redirect_uri('microsoft'), 'grant_type' => 'authorization_code', 'scope' => 'openid email profile User.Read',
    ]);
    if (empty($tok['access_token'])) throw new RuntimeException(oauth_error_text($tok['error'] ?? '', $tok['error_description'] ?? 'Could not reach Microsoft'));
    $u = http_json('https://graph.microsoft.com/v1.0/me', null, ['Authorization: Bearer ' . $tok['access_token']]);
    $email = $u['mail'] ?? $u['userPrincipalName'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Could not read an email address from your Microsoft account.');
    return ['id' => $u['id'], 'email' => $email, 'name' => $u['displayName'] ?? $email, 'verified' => true];
}
