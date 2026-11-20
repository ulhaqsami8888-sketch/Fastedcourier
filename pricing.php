<?php
/**
 * Pricing page - service wise delivery charges and sample estimates.
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Courier Charges & Delivery Rates in Pakistan';
$pageDesc  = 'Check FASTED COURIER pricing: per-kg courier rates, base charges and estimated delivery times for local and nationwide parcel delivery across Pakistan.';
$active    = 'pricing';

$rates = $pdo->query(
    "SELECT s.service_id, s.service_name, s.description, s.icon,
            p.base_price, p.per_kg, p.discount_percent, p.estimated_days
     FROM Services s
     JOIN Pricing p ON p.service_id = s.service_id
     WHERE s.status = 'Active'
     ORDER BY p.base_price"
)->fetchAll();

$percent_label = static function (float $value): string {
    return rtrim(rtrim(number_format($value, 2), '0'), '.');
};

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero"<?= page_hero_style('pricing') ?>>
    <div class="container">
        <span class="eyebrow">Pricing</span>
        <h1>Clear Rates.<br><span>No Surprises.</span></h1>
        <p>Pick a service and know exactly what you will pay before booking. Every rate includes tracking and careful handling.</p>
        <div class="breadcrumb-line">
            <a href="<?= url('index.php') ?>">Home</a><span class="dot"></span><span>Pricing</span>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Delivery Charges</span>
            <h2 class="section-title">Choose Your <span>Delivery Plan</span></h2>
            <p class="section-sub">Charges are calculated as base price plus the per kilogram rate for your parcel weight. Any active discount is applied automatically.</p>
        </div>

        <div class="row g-4 justify-content-center">
            <?php $index = 0; foreach ($rates as $rate): $index++; ?>
                <?php
                $discount = (float) $rate['discount_percent'];
                $netBase  = apply_discount((float) $rate['base_price'], $discount);
                ?>
                <div class="col-md-6 col-xl-3">
                    <article class="price-card<?= (int) $rate['service_id'] === 2 ? ' featured' : '' ?>">
                        <?php if ((int) $rate['service_id'] === 2): ?>
                            <span class="price-tag">Most Popular</span>
                        <?php endif; ?>
                        <span class="service-icon"><i class="bi <?= e($rate['icon']) ?>"></i></span>
                        <h3><?= e($rate['service_name']) ?></h3>
                        <p class="desc"><?= e($rate['description']) ?></p>
                        <div class="price-amount">
                            <span class="rs">Rs</span>
                            <span class="amount"><?= number_format($netBase, 0) ?></span>
                            <span class="unit">base</span>
                            <?php if ($discount > 0): ?>
                                <span class="price-off">-<?= e($percent_label($discount)) ?>%</span>
                                <span class="price-was"><?= money($rate['base_price']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="price-days"><i class="bi bi-plus-circle"></i> <?= money($rate['per_kg']) ?> per kg &nbsp;•&nbsp; <?= e($rate['estimated_days']) ?></p>
                        <?php if ($discount > 0): ?>
                            <p class="price-save"><i class="bi bi-tag-fill"></i> <?= e($percent_label($discount)) ?>% off applied on every parcel</p>
                        <?php endif; ?>
                        <ul class="price-list">
                            <li><i class="bi bi-check2"></i> Real-time tracking</li>
                            <li><i class="bi bi-check2"></i> Delivery confirmation</li>
                            <li><i class="bi bi-check2"></i> Secure handling</li>
                            <li><i class="bi bi-check2"></i> Email &amp; phone support</li>
                        </ul>
                        <a href="<?= url('book.php?service=' . $rate['service_id']) ?>" class="btn <?= (int) $rate['service_id'] === 2 ? 'btn-yellow' : 'btn-navy' ?> w-100">
                            Book Now <i class="bi bi-arrow-right"></i>
                        </a>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pricing-note">
            <i class="bi bi-shield-check"></i>
            <div>
                <strong>Transparent pricing:</strong> the price you see while booking is calculated from the live rate list.
                Any active percentage discount is applied automatically to the total. If a booking is rejected, no delivery charge is applied.
                Deliveries to remote northern areas and small towns may take one extra day.
            </div>
        </div>
    </div>
</section>

<!-- ================= SAMPLE ESTIMATES ================= -->
<section class="section-tight">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Quick Estimate</span>
            <h2 class="section-title">Sample <span>Charges</span></h2>
            <p class="section-sub">Estimated delivery charges for common parcel weights.</p>
        </div>
        <div class="table-wrap">
            <div class="table-responsive">
                <table class="table table-dark-custom align-middle">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Discount</th>
                            <th>Up to 1 KG</th>
                            <th>Up to 5 KG</th>
                            <th>Up to 10 KG</th>
                            <th>Delivery Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rates as $rate): ?>
                            <?php $discount = (float) $rate['discount_percent']; ?>
                            <tr>
                                <td><strong class="text-white"><?= e($rate['service_name']) ?></strong></td>
                                <td>
                                    <?php if ($discount > 0): ?>
                                        <span class="discount-pill"><?= e($percent_label($discount)) ?>% OFF</span>
                                    <?php else: ?>
                                        <span class="text-muted-soft">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ([1, 5, 10] as $kg): ?>
                                    <td>
                                        <?= money(calculate_charge((float) $rate['base_price'], (float) $rate['per_kg'], $kg, $discount)) ?>
                                        <?php if ($discount > 0): ?>
                                            <small class="d-block text-muted-soft"><s><?= money(calculate_charge((float) $rate['base_price'], (float) $rate['per_kg'], $kg)) ?></s></small>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td><?= e($rate['estimated_days']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-muted-soft mt-3 mb-0" style="font-size:12.5px">
            * Estimates only. The exact charge is shown in your booking summary and confirmed on approval.
        </p>
    </div>
</section>

<section class="section-tight pt-0">
    <div class="container">
        <div class="cta-band">
            <div class="position-relative">
                <h2>Know your rate? Book now.</h2>
                <p>Create a booking in less than two minutes.</p>
            </div>
            <div class="cta-actions">
                <a href="<?= url('book.php') ?>" class="btn btn-navy">Book a Parcel <i class="bi bi-arrow-right"></i></a>
                <a href="<?= url('track.php') ?>" class="btn btn-outline-dark-custom">Track a Shipment</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
