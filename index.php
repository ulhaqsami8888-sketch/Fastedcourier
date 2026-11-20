<?php
/**
 * Home page - hero slider, tracking widget, features, about, services.
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Best Courier Service in Karachi, Pakistan - Parcel Delivery & Tracking';
$pageDesc  = 'FASTED COURIER offers fast, reliable parcel delivery across Karachi and every city in Pakistan. Book a courier online, track your shipment live and get 24/7 support.';
$active    = 'home';

$homeServices = $pdo->query(
    "SELECT s.*, p.base_price, p.per_kg, p.discount_percent, p.estimated_days
     FROM Services s
     LEFT JOIN Pricing p ON p.service_id = s.service_id
     WHERE s.status = 'Active'
     ORDER BY s.service_id
     LIMIT 4"
)->fetchAll();

// Service name shown (with animation) on each hero slider slide
$heroServices   = $pdo->query("SELECT service_name FROM Services WHERE status = 'Active' ORDER BY service_id")->fetchAll(PDO::FETCH_COLUMN);
$heroServiceFor = static fn(int $slide): string => $heroServices
    ? $heroServices[($slide - 1) % count($heroServices)]
    : 'Courier Services';

include __DIR__ . '/includes/header.php';
?>

<!-- ================= HERO ================= -->
<section class="hero">
    <?php for ($slide = 1; $slide <= HERO_SLIDE_COUNT; $slide++): ?>
        <?php
        $slideSrc   = url(site_slide_path($slide));
        $slideVideo = site_slide_is_video($slide);
        ?>
        <div class="hero-slide slide-<?= $slide ?><?= $slide === 1 ? ' is-active' : '' ?>"
             data-service="<?= e($heroServiceFor($slide)) ?>"
             <?php if (!$slideVideo): ?>style="background-image:url('<?= e($slideSrc) ?>')"<?php endif; ?>>
            <?php if ($slideVideo): ?>
                <video class="hero-video" autoplay muted loop playsinline preload="metadata">
                    <source src="<?= e($slideSrc) ?>" type="<?= e(site_slide_mime($slide)) ?>">
                </video>
            <?php endif; ?>
        </div>
    <?php endfor; ?>

    <div class="container">
        <div class="hero-content">
            <span class="hero-pill"><i class="bi bi-stars"></i> <?= e(setting('hero_pill', 'Welcome to FASTED COURIER')) ?></span>
            <h1 class="hero-title">
                <span class="line-1"><?= e(setting('hero_title_line1', 'Fast Delivery.')) ?></span>
                <span class="line-2"><?= e(setting('hero_title_line2', 'Nationwide.')) ?></span>
            </h1>
            <div class="hero-service-chip hero-chip-in">
                <span class="hero-service-icon"><i class="bi bi-box-seam-fill"></i></span>
                <span class="hero-service-label">Our Service</span>
                <span class="hero-service-name" data-hero-service><?= e($heroServiceFor(1)) ?></span>
            </div>
            <p class="hero-text">
                <?= e(setting('hero_text', 'Book your parcel, track your shipment, and experience reliable courier services — from a single document to full cargo, delivered with care.')) ?>
            </p>
            <div class="hero-actions">
                <a href="<?= url('book.php') ?>" class="btn btn-yellow btn-lg">
                    <i class="bi bi-send-fill"></i> Book a Parcel
                </a>
                <a href="<?= url('track.php') ?>" class="btn btn-outline-light btn-lg">
                    <i class="bi bi-search"></i> Track Shipment
                </a>
            </div>
        </div>
    </div>

    <button class="hero-nav prev" type="button" aria-label="Previous slide"><i class="bi bi-chevron-left"></i></button>
    <button class="hero-nav next" type="button" aria-label="Next slide"><i class="bi bi-chevron-right"></i></button>

    <div class="hero-dots">
        <?php for ($slide = 1; $slide <= HERO_SLIDE_COUNT; $slide++): ?>
            <button type="button" class="<?= $slide === 1 ? 'active' : '' ?>" aria-label="Slide <?= $slide ?>"></button>
        <?php endfor; ?>
    </div>

    <div class="hero-badge">
        <span class="icon"><i class="bi bi-shield-check"></i></span>
        <div>
            <strong>Safe &amp; Secure Deliveries</strong>
            <small>Insured &amp; fully tracked</small>
        </div>
    </div>
</section>

<!-- ================= TRACKING WIDGET ================= -->
<section class="track-section">
    <div class="container">
        <div class="track-card">
            <div class="track-inner">
                <span class="track-icon"><i class="bi bi-geo-alt-fill"></i></span>
                <div class="track-copy">
                    <h3>Track Your Shipment</h3>
                    <p>Enter your Tracking ID to get the latest status</p>
                </div>
                <form class="track-form" action="<?= url('track.php') ?>" method="get">
                    <input type="text" class="track-input" name="tid" required maxlength="30"
                           placeholder="Enter Tracking ID e.g. FC-2026-7K2M9Q" aria-label="Tracking ID">
                    <button class="btn btn-yellow" type="submit"><i class="bi bi-search"></i> Track Now</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php $homeAdVideo = trim(setting('hero_ad_video', '')); ?>
<?php if ($homeAdVideo !== ''): ?>
<!-- ================= HOMEPAGE AD VIDEO ================= -->
<section class="home-ad-section">
    <div class="container">
        <div class="home-ad-frame">
            <video class="home-ad-video" autoplay muted loop playsinline preload="metadata"
                   aria-label="FASTED COURIER promotional video">
                <source src="<?= e(url($homeAdVideo)) ?>" type="<?= e(site_video_mime($homeAdVideo)) ?>">
            </video>
            <button type="button" class="home-ad-sound" id="homeAdSound" aria-label="Turn ad sound on" aria-pressed="false">
                <i class="bi bi-volume-mute-fill"></i><span>Sound off</span>
            </button>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================= FEATURES ================= -->
<section class="features-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <article class="feature-card">
                    <span class="icon-circle"><i class="bi bi-airplane-engines"></i></span>
                    <h3>Express Delivery</h3>
                    <p>Get your parcels delivered faster with priority handling and dedicated routes.</p>
                    <a href="<?= url('services.php') ?>" class="learn-more">Learn More <i class="bi bi-arrow-right"></i></a>
                </article>
            </div>
            <div class="col-md-6 col-lg-3">
                <article class="feature-card">
                    <span class="icon-circle"><i class="bi bi-shield-check"></i></span>
                    <h3>Secure &amp; Safe</h3>
                    <p>Your parcels are in safe hands with insured, verified and fully tracked delivery.</p>
                    <a href="<?= url('about.php') ?>" class="learn-more">Learn More <i class="bi bi-arrow-right"></i></a>
                </article>
            </div>
            <div class="col-md-6 col-lg-3">
                <article class="feature-card">
                    <span class="icon-circle"><i class="bi bi-geo-alt"></i></span>
                    <h3>Pakistan-Wide Network</h3>
                    <p>Door-to-door delivery in every major city and town across Pakistan, from Karachi to Gilgit.</p>
                    <a href="<?= url('services.php') ?>" class="learn-more">Learn More <i class="bi bi-arrow-right"></i></a>
                </article>
            </div>
            <div class="col-md-6 col-lg-3">
                <article class="feature-card">
                    <span class="icon-circle"><i class="bi bi-headset"></i></span>
                    <h3>24/7 Support</h3>
                    <p>We are always here to help you — by phone, email or through our contact form.</p>
                    <a href="<?= url('contact.php') ?>" class="learn-more">Learn More <i class="bi bi-arrow-right"></i></a>
                </article>
            </div>
        </div>
    </div>
</section>

<!-- ================= ABOUT ================= -->
<section class="about-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="about-media">
                    <img src="<?= url('assets/img/about.jpg') ?>" alt="FASTED COURIER delivery network across Pakistan">
                    <div class="experience-badge">
                        <span class="num">10+</span>
                        <span class="txt">Years of<br>Logistics Experience</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="section-head mb-0">
                    <span class="eyebrow">About FASTED COURIER</span>
                    <h2 class="section-title">Your Trusted <span>Courier Partner</span></h2>
                    <p class="section-sub">
                        <?= e(setting('about_intro', 'We are committed to providing fast, secure and reliable courier services across every city in Pakistan.')) ?>
                    </p>
                </div>
                <ul class="check-list">
                    <li><i class="bi bi-check-circle-fill"></i> Real-time shipment tracking with complete history</li>
                    <li><i class="bi bi-check-circle-fill"></i> Verified pickup and delivery network across cities</li>
                    <li><i class="bi bi-check-circle-fill"></i> Transparent service-wise delivery pricing</li>
                </ul>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= url('about.php') ?>" class="btn btn-yellow">Know More <i class="bi bi-arrow-right"></i></a>
                    <a href="<?= url('pricing.php') ?>" class="btn btn-outline-yellow">View Pricing</a>
                </div>
            </div>
        </div>

        <div class="stats-strip">
            <div class="stat-box">
                <div class="value"><span data-count="25000">0</span>+</div>
                <div class="label">Parcels Delivered</div>
            </div>
            <div class="stat-box">
                <div class="value"><span data-count="120">0</span>+</div>
                <div class="label">Cities Covered</div>
            </div>
            <div class="stat-box">
                <div class="value"><span data-count="8500">0</span>+</div>
                <div class="label">Happy Customers</div>
            </div>
            <div class="stat-box">
                <div class="value"><span data-count="10">0</span>+</div>
                <div class="label">Years Experience</div>
            </div>
        </div>
    </div>
</section>

<!-- ================= SERVICES ================= -->
<section class="section">
    <div class="container">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
            <div class="section-head mb-0">
                <span class="eyebrow">Our Services</span>
                <h2 class="section-title">Delivery Options <span>Built Around You</span></h2>
                <p class="section-sub">Choose the service that fits your parcel, budget and timeline.</p>
            </div>
            <a href="<?= url('services.php') ?>" class="btn btn-outline-yellow">
                All Services <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php foreach ($homeServices as $service): ?>
                <div class="col-md-6 col-lg-3">
                    <article class="service-card">
                        <span class="service-icon"><i class="bi <?= e($service['icon']) ?>"></i></span>
                        <h3><?= e($service['service_name']) ?></h3>
                        <p><?= e($service['description']) ?></p>
                        <div class="service-meta">
                            <span class="price">
                                <?php if ($service['base_price'] !== null): ?>
                                    <?php $homeDiscount = (float) ($service['discount_percent'] ?? 0); ?>
                                    From <strong><?= money(apply_discount((float) $service['base_price'], $homeDiscount)) ?></strong>
                                    <?php if ($homeDiscount > 0): ?>
                                        <span class="price-was"><?= money($service['base_price']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted-soft">Rate on request</span>
                                <?php endif; ?>
                            </span>
                            <span class="days"><i class="bi bi-clock"></i> <?= e($service['estimated_days'] ?? 'Flexible') ?></span>
                        </div>
                        <a href="<?= url('book.php?service=' . $service['service_id']) ?>" class="learn-more">
                            Book this service <i class="bi bi-arrow-right"></i>
                        </a>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================= HOW IT WORKS ================= -->
<section class="section-tight">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Simple Process</span>
            <h2 class="section-title">Ship In <span>Three Easy Steps</span></h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <article class="step-card">
                    <span class="step-num">01</span>
                    <h3>Book Your Parcel</h3>
                    <p>Register, add sender and receiver details, choose a service and confirm your booking.</p>
                </article>
            </div>
            <div class="col-md-4">
                <article class="step-card">
                    <span class="step-num">02</span>
                    <h3>We Pick It Up</h3>
                    <p>Our team approves the request, generates your Tracking ID and collects the parcel.</p>
                </article>
            </div>
            <div class="col-md-4">
                <article class="step-card">
                    <span class="step-num">03</span>
                    <h3>Track Till Delivery</h3>
                    <p>Follow every status update online until your parcel is safely delivered and signed for.</p>
                </article>
            </div>
        </div>
    </div>
</section>

<!-- ================= CTA ================= -->
<section class="section-tight pt-0">
    <div class="container">
        <div class="cta-band">
            <div class="position-relative">
                <h2>Ready to send your next parcel?</h2>
                <p>Create an account, book in minutes and track every step of the journey.</p>
            </div>
            <div class="cta-actions">
                <a href="<?= url('book.php') ?>" class="btn btn-navy">Book a Parcel <i class="bi bi-arrow-right"></i></a>
                <a href="<?= url('register.php') ?>" class="btn btn-outline-dark-custom">Create Account</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
