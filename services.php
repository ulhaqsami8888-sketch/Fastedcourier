<?php
/**
 * Our Services - available courier services with rates from the database.
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Courier Services - Express, Same Day & Nationwide Delivery';
$pageDesc  = 'Explore FASTED COURIER services: express delivery, same day courier, standard parcels and nationwide cargo with transparent rates from Karachi across Pakistan.';
$active    = 'services';
$selected  = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$services = $pdo->query(
    "SELECT s.*, p.price_id, p.base_price, p.per_kg, p.discount_percent, p.estimated_days
     FROM Services s
     LEFT JOIN Pricing p ON p.service_id = s.service_id
     WHERE s.status = 'Active'
     ORDER BY s.service_id"
)->fetchAll();

// When a single service is opened directly (?id=), give it its own SEO title and description
if ($selected) {
    foreach ($services as $service) {
        if ((int) $service['service_id'] === $selected) {
            $pageTitle = $service['service_name'] . ' - Fast ' . $service['service_name'] . ' in Karachi, Pakistan';
            $pageDesc  = $service['description']
                ? mb_substr($service['description'], 0, 155)
                : 'Book ' . $service['service_name'] . ' with FASTED COURIER. Transparent rates, live tracking and fast delivery from Karachi, Pakistan.';
            break;
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero"<?= page_hero_style('services') ?>>
    <div class="container">
        <span class="eyebrow">Our Services</span>
        <h1>Courier Solutions<br><span>For Every Parcel.</span></h1>
        <p>From everyday documents to bulk cargo, we deliver across every city in Pakistan — with international routes on our roadmap.</p>
        <div class="breadcrumb-line">
            <a href="<?= url('index.php') ?>">Home</a><span class="dot"></span><span>Our Services</span>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Delivery Options</span>
            <h2 class="section-title">What We <span>Offer</span></h2>
            <p class="section-sub">All services include live tracking, careful handling and delivery confirmation.</p>
        </div>

        <div class="row g-4">
            <?php foreach ($services as $service): ?>
                <div class="col-md-6" id="service-<?= (int) $service['service_id'] ?>">
                    <article class="service-card<?= $selected === (int) $service['service_id'] ? ' service-highlight' : '' ?>">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <span class="service-icon mb-0"><i class="bi <?= e($service['icon']) ?>"></i></span>
                            <?php if ($selected === (int) $service['service_id']): ?>
                                <span class="price-tag">Selected</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="mt-3"><?= e($service['service_name']) ?></h3>
                        <p><?= e($service['description']) ?></p>

                        <?php if ($service['base_price'] !== null): ?>
                            <?php $svcDiscount = (float) ($service['discount_percent'] ?? 0); ?>
                            <div class="service-meta">
                                <span class="price">
                                    Base charge <strong><?= money(apply_discount((float) $service['base_price'], $svcDiscount)) ?></strong>
                                    <?php if ($svcDiscount > 0): ?>
                                        <span class="price-was"><?= money($service['base_price']) ?></span>
                                        <span class="discount-pill"><?= rtrim(rtrim(number_format($svcDiscount, 2), '0'), '.') ?>% OFF</span>
                                    <?php endif; ?>
                                </span>
                                <span class="days"><?= money($service['per_kg']) ?> / kg</span>
                            </div>
                            <div class="service-meta border-0 pt-2 mt-0">
                                <span class="days"><i class="bi bi-clock"></i> Estimated <?= e($service['estimated_days']) ?></span>
                                <span class="days"><i class="bi bi-shield-check"></i> Fully tracked</span>
                            </div>
                        <?php endif; ?>

                        <a href="<?= url('book.php?service=' . $service['service_id']) ?>" class="btn btn-yellow w-100 mt-3">
                            Book <?= e($service['service_name']) ?> <i class="bi bi-arrow-right"></i>
                        </a>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pricing-note">
            <i class="bi bi-info-circle-fill"></i>
            <div>
                Delivery charges are calculated as <strong>base price + (per kg rate × parcel weight)</strong>.
                Final charges are confirmed when your booking is approved.
                <a href="<?= url('pricing.php') ?>">See the full price list</a>.
            </div>
        </div>
    </div>
</section>

<!-- ================= SERVICE COMPARISON ================= -->
<section class="section-tight">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Compare</span>
            <h2 class="section-title">Service <span>At A Glance</span></h2>
        </div>
        <div class="table-wrap">
            <div class="table-responsive">
                <table class="table table-dark-custom align-middle">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Base Price</th>
                            <th>Per KG</th>
                            <th>Estimated Time</th>
                            <th>Coverage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td>
                                    <strong class="text-white"><?= e($service['service_name']) ?></strong>
                                </td>
                                <td>
                                    <?php if ($service['base_price'] !== null): ?>
                                        <?php $svcDiscount = (float) ($service['discount_percent'] ?? 0); ?>
                                        <?= money(apply_discount((float) $service['base_price'], $svcDiscount)) ?>
                                        <?php if ($svcDiscount > 0): ?>
                                            <small class="d-block text-muted-soft"><s><?= money($service['base_price']) ?></s></small>
                                        <?php endif; ?>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td><?= $service['per_kg'] !== null ? money($service['per_kg']) : '-' ?></td>
                                <td><?= e($service['estimated_days'] ?? '-') ?></td>
                                <td>
                                    <?= (int) $service['service_id'] === 3
                                        ? 'Same City'
                                        : ((int) $service['service_id'] === 4 ? 'All Pakistan' : 'Nationwide') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<section class="section-tight pt-0">
    <div class="container">
        <div class="cta-band">
            <div class="position-relative">
                <h2>Need help choosing a service?</h2>
                <p>Our support team can recommend the right option for your parcel.</p>
            </div>
            <div class="cta-actions">
                <a href="<?= url('contact.php') ?>" class="btn btn-navy">Talk to Support <i class="bi bi-chat-dots"></i></a>
                <a href="<?= url('book.php') ?>" class="btn btn-outline-dark-custom">Book Now</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
