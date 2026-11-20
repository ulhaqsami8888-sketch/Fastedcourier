<?php
/**
 * Customer login - email + password authentication.
 */
require_once __DIR__ . '/config/config.php';

if (is_logged_in() && current_user()) {
    redirect('customer/dashboard.php');
}

$pageTitle = 'Customer Login';
$noindex   = true;
$active    = 'login';
$email     = '';
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('login.php');

    $email    = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (!valid_email($email) || $password === '') {
        $error = 'Please enter a valid email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM Users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        if (!$account || !password_verify($password, $account['password'])) {
            customer_activity_log(
                'Failed Login',
                $email,
                $account ? (int) $account['user_id'] : null,
                $account ? (string) $account['full_name'] : '',
                $account ? 'Incorrect password' : 'Email not registered'
            );
            $error = 'Incorrect email or password. Please try again.';
        } elseif ($account['status'] === 'Blocked') {
            customer_activity_log('Blocked Login', $email, (int) $account['user_id'], (string) $account['full_name'], 'Account is blocked');
            $error = 'This account has been blocked. Please contact support.';
        } else {
            customer_activity_log('Login', $email, (int) $account['user_id'], (string) $account['full_name'], 'Password login');
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $account['user_id'];
            flash('success', 'Welcome back, ' . explode(' ', $account['full_name'])[0] . '!');
            redirect('customer/dashboard.php');
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
    <div class="container">
        <div class="auth-card">
            <div class="auth-brand">
                <img class="brand-logo brand-logo-login" src="<?= e(site_logo_url()) ?>" alt="FASTED COURIER">
            </div>
            <h1>Customer <span class="text-yellow">Login</span></h1>
            <p class="lead-text">Login to book parcels and track your shipments.</p>

            <?php if ($error): ?>
                <div class="alert flash-alert alert-danger mb-4" style="position:static">
                    <i class="bi bi-exclamation-octagon-fill"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= url('login.php') ?>" class="needs-validate" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>"
                               placeholder="you@example.com" required autofocus>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Your password" required>
                        <button class="btn btn-navy" type="button" data-toggle-password="password" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-yellow w-100 btn-lg mt-2">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>

            <div class="auth-divider">or</div>
            <a href="<?= url('google-login.php') ?>" class="btn btn-google w-100">
                <svg viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                </svg>
                Continue with Google
            </a>

            <div class="auth-footer">
                New to FASTED COURIER? <a href="<?= url('register.php') ?>">Create an account</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
