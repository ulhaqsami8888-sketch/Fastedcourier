<?php
/**
 * Contact Us - customer query form saved to ContactMessages.
 * Logged in customers are linked to their account automatically.
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Contact Us - 24/7 Courier Support';
$pageDesc  = 'Get in touch with FASTED COURIER for booking help, shipment delays, billing questions or corporate shipping. Our support team is available 24/7.';
$active    = 'contact';
$user      = current_user();

$errors = [];
$form   = [
    'name'    => $user['full_name'] ?? '',
    'email'   => $user['email'] ?? '',
    'phone'   => $user['phone'] ?? '',
    'subject' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('contact.php');

    $form['name']    = trim($_POST['name'] ?? '');
    $form['email']   = trim($_POST['email'] ?? '');
    $form['phone']   = trim($_POST['phone'] ?? '');
    $form['subject'] = trim($_POST['subject'] ?? '');
    $form['message'] = trim($_POST['message'] ?? '');

    if (mb_strlen($form['name']) < 3) {
        $errors['name'] = 'Please enter your full name.';
    }
    if (!valid_email($form['email'])) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($form['phone'] !== '' && !valid_phone($form['phone'])) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }
    if (mb_strlen($form['subject']) < 4) {
        $errors['subject'] = 'Please enter a short subject.';
    }
    if (mb_strlen($form['message']) < 10) {
        $errors['message'] = 'Message must be at least 10 characters long.';
    }

    if (!$errors) {
        // Guests are linked to an existing account when the email matches.
        $userId = $user['user_id'] ?? null;
        if ($userId === null) {
            $lookup = $pdo->prepare('SELECT user_id FROM Users WHERE email = ? LIMIT 1');
            $lookup->execute([$form['email']]);
            $matched = $lookup->fetchColumn();
            if ($matched !== false) {
                $userId = (int) $matched;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO ContactMessages (user_id, name, email, phone, subject, message, status)
             VALUES (?, ?, ?, ?, ?, ?, "New")'
        );
        $stmt->execute([
            $userId,
            $form['name'],
            $form['email'],
            $form['phone'] ?: null,
            $form['subject'],
            $form['message'],
        ]);
        flash('success', $user
            ? 'Thank you! Your message has been sent. You can see our reply on the My Messages page.'
            : 'Thank you! Your message has been sent. Our team will reply soon.');
        redirect('contact.php');
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero"<?= page_hero_style('contact') ?>>
    <div class="container">
        <span class="eyebrow">Contact Us</span>
        <h1>We're Here<br><span>To Help You.</span></h1>
        <p>Questions about a shipment, pricing or a booking? Send us a message and our support team will get back to you.</p>
        <div class="breadcrumb-line">
            <a href="<?= url('index.php') ?>">Home</a><span class="dot"></span><span>Contact Us</span>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="row g-4">
            <div class="col-sm-6 col-lg-3">
                <div class="contact-info-card">
                    <span class="icon"><i class="bi bi-geo-alt"></i></span>
                    <div>
                        <h4>Head Office</h4>
                        <p><?= e(setting('contact_address')) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="contact-info-card">
                    <span class="icon"><i class="bi bi-telephone"></i></span>
                    <div>
                        <h4>Call Us</h4>
                        <p><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="contact-info-card">
                    <span class="icon"><i class="bi bi-envelope"></i></span>
                    <div>
                        <h4>Email Us</h4>
                        <p><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="contact-info-card">
                    <span class="icon"><i class="bi bi-clock"></i></span>
                    <div>
                        <h4>Support Hours</h4>
                        <p><?= e(setting('support_hours')) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-lg-7">
                <div class="form-card h-100">
                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                        <h2><i class="bi bi-chat-dots"></i> Send Us a Message</h2>
                        <?php if ($user): ?>
                            <a href="<?= url('customer/messages.php') ?>" class="btn btn-navy btn-sm">
                                <i class="bi bi-chat-left-text"></i> My Messages
                            </a>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted-soft mb-4" style="font-size:13.5px">
                        Fill in the form below. Fields marked <span class="req">*</span> are required.
                    </p>

                    <form method="post" action="<?= url('contact.php') ?>" class="needs-validate" novalidate>
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Full Name <span class="req">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                       id="name" name="name" value="<?= e($form['name']) ?>" maxlength="100" required>
                                <div class="invalid-feedback"><?= e($errors['name'] ?? 'Please enter your name.') ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email">Email Address <span class="req">*</span></label>
                                <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                       id="email" name="email" value="<?= e($form['email']) ?>" maxlength="120" required>
                                <div class="invalid-feedback"><?= e($errors['email'] ?? 'Please enter a valid email.') ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Phone Number (optional)</label>
                                <input type="text" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                                       id="phone" name="phone" value="<?= e($form['phone']) ?>" maxlength="25" placeholder="0300-1234567">
                                <div class="invalid-feedback"><?= e($errors['phone'] ?? 'Enter a valid phone number.') ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="subject">Subject <span class="req">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['subject']) ? 'is-invalid' : '' ?>"
                                       id="subject" name="subject" value="<?= e($form['subject']) ?>" maxlength="150" required>
                                <div class="invalid-feedback"><?= e($errors['subject'] ?? 'Please enter a subject.') ?></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="message">Your Message <span class="req">*</span></label>
                                <textarea class="form-control <?= isset($errors['message']) ? 'is-invalid' : '' ?>" id="message"
                                          name="message" rows="6" maxlength="2000" required
                                          data-counter="#messageCounter"><?= e($form['message']) ?></textarea>
                                <div class="invalid-feedback"><?= e($errors['message'] ?? 'Please write your message.') ?></div>
                                <div class="textarea-counter" id="messageCounter">0 / 2000</div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-yellow mt-3">
                            <i class="bi bi-send"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="form-card h-100 d-flex flex-column">
                    <h3><i class="bi bi-headset"></i> We're Always On</h3>
                    <p class="text-muted-soft" style="font-size:13.5px">
                        Our customer care team is available 24/7 for urgent shipment issues. For faster help,
                        keep your Tracking ID ready when contacting us.
                    </p>
                    <div class="d-grid gap-3 mt-2">
                        <div class="achievement"><i class="bi bi-box-seam"></i> Shipment delays &amp; missing parcels</div>
                        <div class="achievement"><i class="bi bi-cash-coin"></i> Billing and payment questions</div>
                        <div class="achievement"><i class="bi bi-file-earmark-text"></i> Corporate &amp; bulk shipping</div>
                        <div class="achievement"><i class="bi bi-tools"></i> Website feedback &amp; complaints</div>
                    </div>
                    <div class="mt-auto pt-4">
                        <a href="<?= url('track.php') ?>" class="btn btn-navy w-100">
                            <i class="bi bi-search"></i> Track Your Shipment
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
