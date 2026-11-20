<?php
/**
 * Book a Parcel - customer booking form.
 * The delivery charge is recalculated on the server from the Pricing table;
 * the value shown by JavaScript is for display only.
 */
require_once __DIR__ . '/config/config.php';
require_customer();

$pageTitle = 'Book a Parcel - Online Courier Booking';
$pageDesc  = 'Book a parcel online with FASTED COURIER in minutes. Enter sender and receiver details, get an instant delivery quote and receive a Tracking ID after approval.';
$active    = 'book';
$user      = current_user();

// Active services that have a price entry
$services = $pdo->query(
    "SELECT s.service_id, s.service_name, p.base_price, p.per_kg, p.discount_percent, p.estimated_days
     FROM Services s
     JOIN Pricing p ON p.service_id = s.service_id
     WHERE s.status = 'Active'
     ORDER BY p.base_price"
)->fetchAll();

$packageTypes = ['Documents', 'Electronics', 'Clothing', 'Books', 'Cosmetics', 'Gift Items', 'Spare Parts', 'Home Appliances', 'Food Items', 'Other'];
$payMethods   = ['Cash on Delivery', 'Online Payment'];
$payAccounts  = payment_accounts();

$errors = [];
$form   = [
    'sender_name'      => $user['full_name'],
    'sender_phone'     => $user['phone'],
    'sender_address'   => $user['address'],
    'receiver_name'    => '',
    'receiver_phone'   => '',
    'receiver_email'   => '',
    'receiver_address' => '',
    'destination'      => '',
    'package_type'     => 'Documents',
    'weight'           => '',
    'service_id'       => (int) ($_GET['service'] ?? ($services[0]['service_id'] ?? 0)),
    'payment_method'   => 'Cash on Delivery',
    'payment_reference'=> '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('book.php');

    foreach (array_keys($form) as $key) {
        if (isset($_POST[$key])) {
            $form[$key] = trim((string) $_POST[$key]);
        }
    }
    $form['service_id'] = (int) ($_POST['service_id'] ?? 0);

    // ---- Validation ----
    if (mb_strlen($form['sender_name']) < 3) {
        $errors['sender_name'] = 'Enter the sender full name.';
    }
    if (!valid_phone($form['sender_phone'])) {
        $errors['sender_phone'] = 'Enter a valid sender phone number.';
    }
    if (mb_strlen($form['sender_address']) < 5) {
        $errors['sender_address'] = 'Enter the complete sender address.';
    }
    if (mb_strlen($form['receiver_name']) < 3) {
        $errors['receiver_name'] = 'Enter the receiver full name.';
    }
    if (!valid_phone($form['receiver_phone'])) {
        $errors['receiver_phone'] = 'Enter a valid receiver phone number.';
    }
    if (!valid_email($form['receiver_email'])) {
        $errors['receiver_email'] = 'Enter a valid receiver email address.';
    }
    if (mb_strlen($form['receiver_address']) < 5) {
        $errors['receiver_address'] = 'Enter the complete receiver address.';
    }
    if (mb_strlen($form['destination']) < 2) {
        $errors['destination'] = 'Enter the delivery destination city.';
    }
    if (!in_array($form['package_type'], $packageTypes, true)) {
        $errors['package_type'] = 'Please choose a valid package type.';
    }
    if (!is_numeric($form['weight']) || (float) $form['weight'] < 0.1 || (float) $form['weight'] > 100) {
        $errors['weight'] = 'Weight must be between 0.1 and 100 kg.';
    }
    if (!in_array($form['payment_method'], $payMethods, true)) {
        $errors['payment_method'] = 'Please choose a payment method.';
    }
    if ($form['payment_reference'] !== ''
        && (mb_strlen($form['payment_reference']) < 4 || mb_strlen($form['payment_reference']) > 40)) {
        $errors['payment_reference'] = 'Enter the Transaction ID from your receipt (4-40 characters).';
    }
    if (empty($_POST['confirm'])) {
        $errors['confirm'] = 'Please confirm that the parcel details are correct.';
    }

    // ---- Fetch the selected rate (server-side, never trust the browser) ----
    $rateStmt = $pdo->prepare(
        "SELECT s.service_id, s.service_name, p.base_price, p.per_kg, p.discount_percent
         FROM Services s JOIN Pricing p ON p.service_id = s.service_id
         WHERE s.service_id = ? AND s.status = 'Active' LIMIT 1"
    );
    $rateStmt->execute([$form['service_id']]);
    $rate = $rateStmt->fetch();

    if (!$rate) {
        $errors['service_id'] = 'Please choose a valid delivery service.';
    }

    if (!$errors) {
        $weight  = round((float) $form['weight'], 2);
        $charge  = calculate_charge((float) $rate['base_price'], (float) $rate['per_kg'], $weight, (float) $rate['discount_percent']);

        try {
            $pdo->beginTransaction();

            $insert = $pdo->prepare(
                'INSERT INTO Bookings
                 (user_id, sender_name, sender_phone, sender_address, receiver_name, receiver_phone,
                  receiver_email, receiver_address, destination, package_type, weight, service_id,
                  delivery_charge, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Pending")'
            );
            $insert->execute([
                $user['user_id'],
                $form['sender_name'],
                $form['sender_phone'],
                $form['sender_address'],
                $form['receiver_name'],
                $form['receiver_phone'],
                $form['receiver_email'],
                $form['receiver_address'],
                $form['destination'],
                $form['package_type'],
                $weight,
                $rate['service_id'],
                $charge,
            ]);
            $bookingId = (int) $pdo->lastInsertId();

            // Pending payment record - stores the chosen method and the optional
            // transfer reference entered by the customer (never card/bank details).
            $payment = $pdo->prepare(
                'INSERT INTO Payments (booking_id, amount, method, transaction_ref, payment_status)
                 VALUES (?, ?, ?, ?, "Pending")'
            );
            $payment->execute([
                $bookingId,
                $charge,
                $form['payment_method'],
                $form['payment_reference'] !== '' ? $form['payment_reference'] : null,
            ]);

            $pdo->commit();
            flash('success', 'Booking #' . $bookingId . ' submitted successfully. Your parcel barcode is shown below.');
            redirect('customer/booking-view.php?id=' . $bookingId);
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = 'Booking could not be saved. Please try again.';
        }
    }
}

// Rates for the live summary calculator
$rateMap = [];
foreach ($services as $service) {
    $rateMap[$service['service_id']] = [
        'name'     => $service['service_name'],
        'base'     => (float) $service['base_price'],
        'per_kg'   => (float) $service['per_kg'],
        'discount' => (float) $service['discount_percent'],
        'days'     => $service['estimated_days'],
    ];
}
// Make sure the preselected service exists
if (!isset($rateMap[$form['service_id']]) && $rateMap) {
    $form['service_id'] = (int) array_key_first($rateMap);
}

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero compact"<?= page_hero_style('book') ?>>
    <div class="container">
        <span class="eyebrow">Book a Parcel</span>
        <h1>Send Your Parcel<br><span>The Easy Way.</span></h1>
        <p>Fill in the sender, receiver and parcel details. You will get a Tracking ID once our team approves the booking.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="booking-steps">
            <span class="bs"><i class="bi bi-1-circle"></i> Fill parcel details</span>
            <span class="bs"><i class="bi bi-2-circle"></i> Admin approval</span>
            <span class="bs"><i class="bi bi-3-circle"></i> Tracking ID issued</span>
            <span class="bs"><i class="bi bi-4-circle"></i> Track till delivery</span>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert flash-alert alert-danger mb-4" style="position:static">
                <i class="bi bi-exclamation-octagon-fill"></i> <?= e($errors['general']) ?>
            </div>
        <?php elseif ($errors): ?>
            <div class="alert flash-alert alert-danger mb-4" style="position:static">
                <i class="bi bi-exclamation-octagon-fill"></i> Please fix the highlighted fields below.
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('book.php') ?>" id="bookingForm" class="needs-validate" novalidate>
            <?= csrf_field() ?>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="form-card">
                        <!-- Sender -->
                        <div class="form-section-title"><span class="step">1</span> Sender Information</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="sender_name">Sender Name <span class="req">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['sender_name']) ? 'is-invalid' : '' ?>"
                                       id="sender_name" name="sender_name" value="<?= e($form['sender_name']) ?>" maxlength="100" required>
                                <div class="invalid-feedback"><?= e($errors['sender_name'] ?? 'Enter sender name.') ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="sender_phone">Sender Phone <span class="req">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['sender_phone']) ? 'is-invalid' : '' ?>"
                                       id="sender_phone" name="sender_phone" value="<?= e($form['sender_phone']) ?>" maxlength="25" required>
                                <div class="invalid-feedback"><?= e($errors['sender_phone'] ?? 'Enter a valid phone number.') ?></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="sender_address">Sender Address <span class="req">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['sender_address']) ? 'is-invalid' : '' ?>"
                                       id="sender_address" name="sender_address" value="<?= e($form['sender_address']) ?>" maxlength="255" required>
                                <div class="invalid-feedback"><?= e($errors['sender_address'] ?? 'Enter sender address.') ?></div>
                            </div>
                        </div>

                        <!-- Receiver -->
                        <div class="form-section-title mt-4"><span class="step">2</span> Receiver Information</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="receiver_name">Receiver Name <span class="req">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['receiver_name']) ? 'is-invalid' : '' ?>"
                                       id="receiver_name" name="receiver_name" value="<?= e($form['receiver_name']) ?>" maxlength="100" required>
                                <div class="invalid-feedback"><?= e($errors['receiver_name'] ?? 'Enter receiver name.') ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="receiver_phone">Receiver Phone <span class="req">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['receiver_phone']) ? 'is-invalid' : '' ?>"
                                       id="receiver_phone" name="receiver_phone" value="<?= e($form['receiver_phone']) ?>" maxlength="25" required>
                                <div class="invalid-feedback"><?= e($errors['receiver_phone'] ?? 'Enter a valid phone number.') ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="receiver_email">Receiver Email <span class="req">*</span></label>
                                <input type="email" class="form-control <?= isset($errors['receiver_email']) ? 'is-invalid' : '' ?>"
                                       id="receiver_email" name="receiver_email" value="<?= e($form['receiver_email']) ?>" maxlength="120" required>
                                <div class="invalid-feedback"><?= e($errors['receiver_email'] ?? 'Enter a valid email.') ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="destination">Destination City <span class="req">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['destination']) ? 'is-invalid' : '' ?>"
                                       id="destination" name="destination" value="<?= e($form['destination']) ?>" maxlength="120"
                                       placeholder="e.g. Islamabad or Lahore" required>
                                <div class="invalid-feedback"><?= e($errors['destination'] ?? 'Enter the destination.') ?></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="receiver_address">Receiver Address <span class="req">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['receiver_address']) ? 'is-invalid' : '' ?>"
                                       id="receiver_address" name="receiver_address" value="<?= e($form['receiver_address']) ?>" maxlength="255" required>
                                <div class="invalid-feedback"><?= e($errors['receiver_address'] ?? 'Enter receiver address.') ?></div>
                            </div>
                        </div>

                        <!-- Parcel -->
                        <div class="form-section-title mt-4"><span class="step">3</span> Parcel &amp; Service</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="package_type">Package Type <span class="req">*</span></label>
                                <select class="form-select <?= isset($errors['package_type']) ? 'is-invalid' : '' ?>"
                                        id="package_type" name="package_type" required>
                                    <?php foreach ($packageTypes as $type): ?>
                                        <option value="<?= e($type) ?>" <?= $form['package_type'] === $type ? 'selected' : '' ?>>
                                            <?= e($type) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback"><?= e($errors['package_type'] ?? 'Choose a package type.') ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="weightInput">Parcel Weight (KG) <span class="req">*</span></label>
                                <input type="number" class="form-control <?= isset($errors['weight']) ? 'is-invalid' : '' ?>"
                                       id="weightInput" name="weight" value="<?= e($form['weight']) ?>"
                                       min="0.1" max="100" step="0.1" placeholder="e.g. 2.5" required>
                                <div class="invalid-feedback"><?= e($errors['weight'] ?? 'Enter weight between 0.1 and 100 kg.') ?></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="serviceSelect">Delivery Service <span class="req">*</span></label>
                                <select class="form-select <?= isset($errors['service_id']) ? 'is-invalid' : '' ?>"
                                        id="serviceSelect" name="service_id" required>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?= (int) $service['service_id'] ?>"
                                            <?= (int) $form['service_id'] === (int) $service['service_id'] ? 'selected' : '' ?>>
                                            <?= e($service['service_name']) ?> — <?= money($service['base_price']) ?> base + <?= money($service['per_kg']) ?>/kg (<?= e($service['estimated_days']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback"><?= e($errors['service_id'] ?? 'Choose a delivery service.') ?></div>
                            </div>
                        </div>

                        <!-- Payment -->
                        <div class="form-section-title mt-4"><span class="step">4</span> Payment Method</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="pay-option">
                                    <input type="radio" name="payment_method" value="Cash on Delivery"
                                        <?= $form['payment_method'] === 'Cash on Delivery' ? 'checked' : '' ?>>
                                    <span class="pay-icon"><i class="bi bi-cash-coin"></i></span>
                                    <span class="pay-body"><strong>Cash on Delivery</strong><small>Pay on arrival</small></span>
                                    <i class="bi bi-check-circle-fill pay-check"></i>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="pay-option">
                                    <input type="radio" name="payment_method" value="Online Payment"
                                        <?= $form['payment_method'] === 'Online Payment' ? 'checked' : '' ?>>
                                    <span class="pay-icon"><i class="bi bi-credit-card"></i></span>
                                    <span class="pay-body"><strong>Online Payment</strong><small>Easypaisa · JazzCash · Bank</small></span>
                                    <i class="bi bi-check-circle-fill pay-check"></i>
                                </label>
                            </div>
                        </div>

                        <?php if ($payAccounts): ?>
                            <button type="button" class="pay-accounts-link" data-open-pay-accounts>
                                <i class="bi bi-wallet2"></i> View Easypaisa / JazzCash / Bank account numbers
                            </button>
                        <?php endif; ?>

                        <div class="transfer-ref" id="transferRefWrap"<?= $form['payment_method'] === 'Online Payment' ? '' : ' hidden' ?>>
                            <label class="form-label" for="payment_reference">
                                Transaction ID / Reference
                                <small class="text-muted-soft">(for online payment)</small>
                            </label>
                            <input type="text" class="form-control <?= isset($errors['payment_reference']) ? 'is-invalid' : '' ?>"
                                   id="payment_reference" name="payment_reference" maxlength="40"
                                   value="<?= e($form['payment_reference']) ?>" placeholder="e.g. EP-123456789 or bank reference">
                            <div class="invalid-feedback"><?= e($errors['payment_reference'] ?? 'Enter the Transaction ID from your receipt.') ?></div>
                            <div class="form-text">You can also send the payment screenshot on WhatsApp for faster verification.</div>
                        </div>

                        <div class="pricing-note mt-3">
                            <i class="bi bi-shield-lock"></i>
                            <div>
                                We never ask for your card number, PIN, CVV or OTP. Online payments are verified
                                manually by our team. Prefer cash? Choose <strong>Cash on Delivery</strong> and pay
                                the rider when your parcel arrives.
                            </div>
                        </div>

                        <div class="form-check mt-4">
                            <input class="form-check-input <?= isset($errors['confirm']) ? 'is-invalid' : '' ?>"
                                   type="checkbox" id="confirm" name="confirm" value="1" required>
                            <label class="form-check-label" for="confirm">
                                I confirm the sender, receiver and parcel details are correct.
                            </label>
                            <div class="invalid-feedback"><?= e($errors['confirm'] ?? 'Please confirm the details.') ?></div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <button type="submit" class="btn btn-yellow btn-lg">
                                <i class="bi bi-send"></i> Submit Booking
                            </button>
                            <a href="<?= url('customer/dashboard.php') ?>" class="btn btn-navy btn-lg">Cancel</a>
                        </div>
                    </div>
                </div>

                <!-- Live summary -->
                <div class="col-lg-4">
                    <aside class="summary-card">
                        <h3><i class="bi bi-receipt"></i> Booking Summary</h3>
                        <div class="summary-row"><span>Service</span><strong id="sumService">-</strong></div>
                        <div class="summary-row"><span>Base charge</span><strong id="sumBase">Rs 0</strong></div>
                        <div class="summary-row"><span>Per KG rate</span><strong id="sumPerKg">-</strong></div>
                        <div class="summary-row"><span>Weight</span><strong id="sumWeight">0.0 kg</strong></div>
                        <div class="summary-row" id="sumDiscountRow" hidden>
                            <span>Discount <small id="sumDiscountPct"></small></span>
                            <strong class="summary-discount" id="sumDiscount">- Rs 0</strong>
                        </div>
                        <div class="summary-total">
                            <div>
                                <span>Estimated Total</span>
                                <small class="summary-old" id="sumOriginal" hidden></small>
                            </div>
                            <span class="value" id="sumTotal">Rs 0</span>
                        </div>
                        <p class="text-muted-soft mt-3 mb-0" style="font-size:12px">
                            <i class="bi bi-info-circle"></i>
                            Final charge is confirmed by the admin after reviewing your parcel weight and destination.
                        </p>
                        <hr style="border-color:rgba(150,180,230,.15);margin:18px 0">
                        <div class="summary-row"><span>Booking status</span><strong><span class="badge-status status-pending">Pending</span></strong></div>
                        <div class="summary-row"><span>Tracking ID</span><strong>After approval</strong></div>
                        <div class="summary-row"><span>Payment</span><strong id="sumPayment"><?= e(payment_method_label($form['payment_method'])) ?></strong></div>
                    </aside>
                </div>
            </div>
        </form>
    </div>
</section>

<!-- Rates used by the live calculator (display only) -->
<script type="application/json" id="serviceRates"><?= json_encode($rateMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
