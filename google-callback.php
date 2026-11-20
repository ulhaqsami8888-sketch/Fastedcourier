<?php
/**
 * Google OAuth 2.0 callback - verifies the Google account and signs the
 * customer in, creating a local Users record on first sign-in.
 */
require_once __DIR__ . '/config/config.php';

if (is_logged_in() && current_user()) {
    redirect('customer/dashboard.php');
}

if (!google_login_enabled()) {
    flash('danger', 'Google sign-in is not available right now. Please use your email and password.');
    redirect('login.php');
}

$expectedState = (string) ($_SESSION['google_oauth_state'] ?? '');
unset($_SESSION['google_oauth_state']);

$returnedState = (string) ($_GET['state'] ?? '');
if ($expectedState === '' || !hash_equals($expectedState, $returnedState)) {
    flash('danger', 'Your Google sign-in session expired. Please try again.');
    redirect('login.php');
}

if (!empty($_GET['error'])) {
    flash('danger', 'Google sign-in was cancelled. Please try again or use your email and password.');
    redirect('login.php');
}

$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '' || !function_exists('curl_init')) {
    flash('danger', 'Google did not return a valid sign-in code. Please try again.');
    redirect('login.php');
}

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $code,
        'client_id'     => google_client_id(),
        'client_secret' => google_client_secret(),
        'redirect_uri'  => google_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
]);
$tokenBody   = curl_exec($ch);
$tokenStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

$token = is_string($tokenBody) ? json_decode($tokenBody, true) : null;
if ($tokenStatus !== 200 || !is_array($token) || empty($token['id_token'])) {
    flash('danger', 'Google could not confirm the sign-in. Please try again.');
    redirect('login.php');
}

$ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token['id_token']));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
]);
$infoBody   = curl_exec($ch);
$infoStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

$info = is_string($infoBody) ? json_decode($infoBody, true) : null;
if ($infoStatus !== 200 || !is_array($info) || ($info['aud'] ?? '') !== google_client_id()) {
    flash('danger', 'Google could not verify this account. Please try again.');
    redirect('login.php');
}

$verified = ($info['email_verified'] ?? false) === true || ($info['email_verified'] ?? '') === 'true';
$email    = strtolower(trim((string) ($info['email'] ?? '')));
$googleSub = trim((string) ($info['sub'] ?? ''));
$issuer    = (string) ($info['iss'] ?? '');
$issuerOk  = in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true);

if (!valid_email($email) || !$verified || (int) ($info['exp'] ?? 0) < time()
    || $googleSub === '' || !preg_match('/^[0-9]{5,64}$/', $googleSub) || !$issuerOk) {
    flash('danger', 'Please use a verified Google account with a valid email address.');
    redirect('login.php');
}

$profile = [
    'sub'            => $googleSub,
    'email'          => $email,
    'name'           => (string) ($info['name'] ?? ''),
    'given_name'     => (string) ($info['given_name'] ?? ''),
    'family_name'    => (string) ($info['family_name'] ?? ''),
    'picture'        => (string) ($info['picture'] ?? ''),
    'locale'         => (string) ($info['locale'] ?? ''),
    'hd'             => (string) ($info['hd'] ?? ''),
    'email_verified' => $verified,
];

// A Google subject ID is the stable identity. If it is already linked, use
// that customer even if they changed their Gmail address later.
$googleRow = google_account_find($pdo, $googleSub);
$account = null;
if ($googleRow !== null) {
    $stmt = $pdo->prepare('SELECT * FROM Users WHERE user_id = ? LIMIT 1');
    $stmt->execute([(int) $googleRow['user_id']]);
    $account = $stmt->fetch() ?: null;
}
if (!$account) {
    $stmt = $pdo->prepare('SELECT * FROM Users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $account = $stmt->fetch() ?: null;
}

if ($account && $account['status'] === 'Blocked') {
    customer_activity_log('Blocked Login', $email, (int) $account['user_id'], (string) $account['full_name'], 'Google sign-in on blocked account');
    flash('danger', 'This account has been blocked. Please contact support.');
    redirect('login.php');
}

if ($account) {
    $userId = (int) $account['user_id'];
    customer_activity_log('Google Login', $email, $userId, (string) $account['full_name'], 'Verified Google login');
    $firstName = explode(' ', $account['full_name'])[0];
    flash('success', 'Welcome back, ' . $firstName . '!');
} else {
    $name = trim((string) ($info['name'] ?? ''));
    if (mb_strlen($name) < 3) {
        $name = ucfirst(explode('@', $email)[0]);
    }
    $name = mb_substr($name, 0, 100);
    if (mb_strlen($name) < 3) {
        $name = 'Google Customer';
    }

    $stmt = $pdo->prepare(
        "INSERT INTO Users (full_name, email, phone, password, address, city, status)
         VALUES (?, ?, '', ?, '', NULL, 'Active')"
    );
    $stmt->execute([$name, $email, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    customer_activity_log('Registered', $email, $userId, $name, 'Google sign-up (verified)');

    flash('success', 'Your account was created with Google. Add your phone and address in My Profile to complete it.');
}

try {
    google_account_save($pdo, $userId, $profile);
} catch (Throwable $error) {
    // Sign-in should still work if the GoogleAccounts table was not imported yet.
}

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
redirect('customer/dashboard.php');
