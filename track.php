<?php
/**
 * Track Shipment - public tracking by unique Tracking ID.
 * Shows parcel details, current status, progress and full history.
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Track Your Parcel - Live Shipment Tracking';
$pageDesc  = 'Track your FASTED COURIER parcel online in real time. Enter your Tracking ID to see the current status, location and full delivery history of your shipment.';
$active    = 'track';
$tracking  = strtoupper(trim((string) ($_GET['tid'] ?? '')));
$shipment  = null;
$history   = [];
$notFound  = false;

if ($tracking !== '') {
    $stmt = $pdo->prepare(
        "SELECT sh.*, b.*, s.service_name, s.status AS service_status,
                u.full_name AS customer_name, pay.payment_status
         FROM Shipments sh
         JOIN Bookings b ON b.booking_id = sh.booking_id
         JOIN Services s ON s.service_id = b.service_id
         JOIN Users u ON u.user_id = b.user_id
         LEFT JOIN Payments pay ON pay.booking_id = b.booking_id
         WHERE sh.tracking_id = ?
         LIMIT 1"
    );
    $stmt->execute([$tracking]);
    $shipment = $stmt->fetch();

    if ($shipment) {
        $hist = $pdo->prepare('SELECT * FROM ShipmentHistory WHERE shipment_id = ? ORDER BY updated_at ASC, history_id ASC');
        $hist->execute([$shipment['shipment_id']]);
        $history = $hist->fetchAll();
    } else {
        $notFound = true;
    }
}

// Progress step data
$steps      = ['Approved', 'Picked Up', 'In Transit', 'Delivered'];
$currentIdx = $shipment ? array_search($shipment['current_status'], $steps, true) : -1;

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero compact"<?= page_hero_style('track') ?>>
    <div class="container">
        <span class="eyebrow">Track Shipment</span>
        <h1>Where Is My <span>Parcel?</span></h1>
        <p>Enter the Tracking ID from your booking confirmation to see the latest delivery status.</p>
        <form class="track-form mt-4" action="<?= url('track.php') ?>" method="get" style="max-width:640px">
            <input type="text" class="track-input" name="tid" value="<?= e($tracking) ?>" required
                   maxlength="30" placeholder="Enter Tracking ID e.g. FC-2026-7K2M9Q" aria-label="Tracking ID">
            <button class="btn btn-yellow" type="submit"><i class="bi bi-search"></i> Track Now</button>
        </form>
    </div>
</section>

<section class="track-result">
    <div class="container">
        <?php if ($notFound): ?>
            <div class="form-card track-notfound">
                <i class="bi bi-search"></i>
                <h2 class="display-font mb-2">Shipment Not Found</h2>
                <p class="text-muted-soft mb-3">
                    We could not find a shipment for <strong class="text-yellow"><?= e($tracking) ?></strong>.
                    Please check the Tracking ID and try again. Tracking IDs look like <code>FC-2026-XXXXXX</code>.
                </p>
                <a href="<?= url('contact.php') ?>" class="btn btn-yellow">Contact Support</a>
            </div>
        <?php elseif (!$tracking): ?>
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <div class="form-card">
                        <h2><i class="bi bi-radar"></i> How tracking works</h2>
                        <p class="text-muted-soft">Every approved booking receives a unique Tracking ID. Use it here to view:</p>
                        <div class="row g-3 mt-2">
                            <div class="col-sm-6"><div class="achievement"><i class="bi bi-box-seam"></i> Parcel &amp; service details</div></div>
                            <div class="col-sm-6"><div class="achievement"><i class="bi bi-people"></i> Sender &amp; receiver info</div></div>
                            <div class="col-sm-6"><div class="achievement"><i class="bi bi-signpost-split"></i> Current shipment status</div></div>
                            <div class="col-sm-6"><div class="achievement"><i class="bi bi-clock-history"></i> Complete status history</div></div>
                        </div>
                        <div class="demo-box mt-4">
                            <strong>Demo tracking ID:</strong> <code>FC-2026-7K2M9Q</code> (In Transit) or <code>FC-2026-3P8D5R</code> (Delivered)
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="form-card text-center">
                        <span class="icon-circle mx-auto"><i class="bi bi-geo-alt"></i></span>
                        <h3 class="justify-content-center">Booked a parcel?</h3>
                        <p class="text-muted-soft">You can find the Tracking ID in your bookings dashboard once the parcel is approved.</p>
                        <a href="<?= url('customer/bookings.php') ?>" class="btn btn-yellow w-100">My Bookings</a>
                        <a href="<?= url('book.php') ?>" class="btn btn-navy w-100 mt-2">Book a New Parcel</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <!-- Summary + progress -->
                <div class="col-lg-7">
                    <div class="track-summary">
                        <div class="track-id-line">
                            <div>
                                <span class="label">Tracking Number</span>
                                <div class="value"><?= e($shipment['tracking_id']) ?></div>
                            </div>
                            <div class="text-end">
                                <?= status_badge($shipment['current_status']) ?>
                                <div class="mt-2"><small class="text-muted-soft">Updated <?= pretty_date($shipment['updated_at'], true) ?></small></div>
                            </div>
                        </div>

                        <div class="route-line">
                            <div class="route-point">
                                <small>Sender Address</small>
                                <strong><?= e($shipment['sender_address']) ?></strong>
                            </div>
                            <i class="bi bi-arrow-right route-arrow"></i>
                            <div class="route-point dest">
                                <small>Destination</small>
                                <strong><?= e($shipment['destination']) ?></strong>
                            </div>
                        </div>

                        <div class="progress-track">
                            <?php foreach ($steps as $i => $step): ?>
                                <?php
                                $state = '';
                                if ($currentIdx !== false && $i < $currentIdx) $state = 'done';
                                if ($currentIdx !== false && $i === $currentIdx) $state = 'current';
                                ?>
                                <div class="progress-step <?= $state ?>">
                                    <span class="dot">
                                        <i class="bi <?= $i <= $currentIdx ? 'bi-check' : 'bi-circle' ?>"></i>
                                    </span>
                                    <span><?= e($step) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="detail-grid">
                            <div class="detail-item"><small>Service</small><strong><?= e($shipment['service_name']) ?></strong></div>
                            <div class="detail-item"><small>Booking Date</small><strong><?= pretty_date($shipment['booking_date']) ?></strong></div>
                            <div class="detail-item"><small>Package Type</small><strong><?= e($shipment['package_type']) ?></strong></div>
                            <div class="detail-item"><small>Parcel Weight</small><strong><?= number_format((float) $shipment['weight'], 2) ?> kg</strong></div>
                            <div class="detail-item"><small>Delivery Charge</small><strong><?= money($shipment['delivery_charge']) ?></strong></div>
                            <div class="detail-item"><small>Payment Status</small><strong><?= payment_badge($shipment['payment_status'] ?? 'Pending') ?></strong></div>
                        </div>
                    </div>
                </div>

                <!-- Sender / receiver + history -->
                <div class="col-lg-5">
                    <div class="track-summary mb-4">
                        <h3 class="mb-3" style="font-size:15px"><i class="bi bi-people text-yellow"></i> People &amp; Places</h3>
                        <div class="profile-row"><span>Sender</span><strong><?= e($shipment['sender_name']) ?></strong></div>
                        <div class="profile-row"><span>Sender Phone</span><strong><?= e(mask_phone($shipment['sender_phone'])) ?></strong></div>
                        <div class="profile-row"><span>Receiver</span><strong><?= e($shipment['receiver_name']) ?></strong></div>
                        <div class="profile-row"><span>Receiver Phone</span><strong><?= e(mask_phone($shipment['receiver_phone'])) ?></strong></div>
                        <div class="profile-row"><span>Receiver Address</span><strong><?= e($shipment['receiver_address']) ?></strong></div>
                    </div>

                    <div class="timeline-card">
                        <h3><i class="bi bi-clock-history"></i> Shipment History</h3>
                        <div class="timeline">
                            <?php foreach (array_reverse($history) as $i => $event): ?>
                                <div class="timeline-item<?= $i === 0 ? ' current' : '' ?>">
                                    <strong><?= e($event['status']) ?></strong>
                                    <div class="meta"><?= pretty_date($event['updated_at'], true) ?><?= $event['location'] ? ' • ' . e($event['location']) : '' ?></div>
                                    <?php if (!empty($event['remarks'])): ?>
                                        <div class="remarks"><?= e($event['remarks']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="<?= url('track.php') ?>" class="btn btn-navy"><i class="bi bi-arrow-left"></i> Track Another Parcel</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
