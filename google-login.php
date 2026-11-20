<?php
/**
 * Starts the Google OAuth 2.0 sign-in flow for customers.
 */
require_once __DIR__ . '/config/config.php';

if (is_logged_in() && current_user()) {
    redirect('customer/dashboard.php');
}

if (!google_login_enabled()) {
    flash('danger', 'Google sign-in is not available right now. Please use your email and password.');
    redirect('login.php');
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$query = http_build_query([
    'client_id'     => google_client_id(),
    'redirect_uri'  => google_redirect_uri(),
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'prompt'        => 'select_account',
]);

redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
