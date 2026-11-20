<?php
/**
 * Customer registration - creates a Users record with a hashed password.
 */
require_once __DIR__ . '/config/config.php';

if (is_logged_in() && current_user()) {
    redirect('customer/dashboard.php');
}

$pageTitle = 'Create Account - Register Online';
$noindex   = true;
$active    = 'register';

$errors = [];
$form   = [
    'full_name' => '',
    'email'     => '',
    'phone'     => '',
    'address'   => '',
    'city'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('register.php');

    $form['full_name'] = trim($_POST['full_name'] ?? '');
    $form['email']     = trim($_POST['email'] ?? '');
    $form['phone']     = trim($_POST['phone'] ?? '');
    $form['address']   = trim($_POST['address'] ?? '');
    $form['city']      = trim($_POST['city'] ?? '');
    $password          = (string) ($_POST['password'] ?? '');
    $confirm           = (string) ($_POST['confirm_password'] ?? '');

    if (mb_strlen($form['full_name']) < 3) {
        $errors['full_name'] = 'Please enter your full name (at least 3 characters).';
    }
    if (!valid_email($form['email'])) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (!valid_phone($form['phone'])) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }
    if (mb_strlen($form['address']) < 5) {
        $errors['address'] = 'Please enter your complete address.';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Password must be at least 8 characters and include letters and numbers.';
    }
    if ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (!$errors) {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM Users WHERE email = ?');
        $exists->execute([$form['email']]);
        if ((int) $exists->fetchColumn() > 0) {
            customer_activity_log('Registration Failed', $form['email'], null, $form['full_name'], 'Email already registered');
            $errors['email'] = 'An account with this email already exists. Please login instead.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO Users (full_name, email, phone, password, address, city, status)
             VALUES (?, ?, ?, ?, ?, ?, "Active")'
        );
        $stmt->execute([
            $form['full_name'],
            $form['email'],
            $form['phone'],
            password_hash($password, PASSWORD_DEFAULT),
            $form['address'],
            $form['city'] ?: null,
        ]);
        $userId = (int) $pdo->lastInsertId();
        customer_activity_log('Registered', $form['email'], $userId, $form['full_name'], 'Email registration');

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        flash('success', 'Your account has been created. Welcome to FASTED COURIER!');
        redirect('customer/dashboard.php');
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
    <div class="container">
        <div class="auth-card wide">
            <div class="auth-brand">
                <img class="brand-logo brand-logo-login" src="<?= e(site_logo_url()) ?>" alt="FASTED COURIER">
            </div>
            <h1>Create <span class="text-yellow">Account</span></h1>
            <p class="lead-text">Register once, then book and track parcels anytime.</p>

            <?php if ($errors): ?>
                <div class="alert flash-alert alert-danger mb-4" style="position:static">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                    <div>Please fix the highlighted fields and try again.</div>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= url('register.php') ?>" class="needs-validate" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="full_name">Full Name <span class="req">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                               id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" maxlength="100" required>
                        <div class="invalid-feedback"><?= e($errors['full_name'] ?? 'Enter your full name.') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email Address <span class="req">*</span></label>
                        <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               id="email" name="email" value="<?= e($form['email']) ?>" maxlength="120" required>
                        <div class="invalid-feedback"><?= e($errors['email'] ?? 'Enter a valid email.') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone Number <span class="req">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                               id="phone" name="phone" value="<?= e($form['phone']) ?>" maxlength="25"
                               placeholder="0300-1234567" required>
                        <div class="invalid-feedback"><?= e($errors['phone'] ?? 'Enter a valid phone number.') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="city">City</label>
                        <input type="text" class="form-control" id="city" name="city"
                               value="<?= e($form['city']) ?>" maxlength="80" placeholder="Karachi">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="address">Complete Address <span class="req">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>"
                               id="address" name="address" value="<?= e($form['address']) ?>" maxlength="255" required>
                        <div class="invalid-feedback"><?= e($errors['address'] ?? 'Enter your address.') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password">Password <span class="req">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                   id="password" name="password" minlength="8" required>
                            <button class="btn btn-navy" type="button" data-toggle-password="password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Minimum 8 characters with letters and numbers.</div>
                        <?php if (isset($errors['password'])): ?>
                            <div class="text-danger mt-1" style="font-size:12px"><?= e($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="confirm_password">Confirm Password <span class="req">*</span></label>
                        <input type="password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                               id="confirm_password" name="confirm_password" minlength="8" required>
                        <div class="invalid-feedback"><?= e($errors['confirm_password'] ?? 'Passwords do not match.') ?></div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="agree" required>
                            <label class="form-check-label" for="agree">
                                I agree to the courier terms and confirm that the information provided is correct.
                            </label>
                            <div class="invalid-feedback">Please accept the terms to continue.</div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-yellow w-100 btn-lg mt-4">
                    <i class="bi bi-person-plus"></i> Create Account
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
                Already registered? <a href="<?= url('login.php') ?>">Login here</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
