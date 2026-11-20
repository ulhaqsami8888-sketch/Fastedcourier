<?php
/**
 * About Us - company introduction, mission, vision, team, achievements
 * and the website gallery managed from the admin panel.
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'About Us - Trusted Courier Company in Pakistan';
$pageDesc  = 'Learn about FASTED COURIER - our mission, vision, team and achievements as a trusted courier and logistics company serving Karachi and every city across Pakistan.';
$active    = 'about';

$gallery = $pdo->query('SELECT * FROM Gallery ORDER BY image_id DESC LIMIT 6')->fetchAll();
$team    = split_setting(setting('about_team'));
$awards  = split_setting(setting('about_achievements'));

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero"<?= page_hero_style('about') ?>>
    <div class="container">
        <span class="eyebrow">About FASTED COURIER</span>
        <h1>We Deliver Trust,<br><span>Not Just Parcels.</span></h1>
        <p>Learn who we are, what drives us and why thousands of customers choose FASTED COURIER for their shipments.</p>
        <div class="breadcrumb-line">
            <a href="<?= url('index.php') ?>">Home</a><span class="dot"></span><span>About Us</span>
        </div>
    </div>
</section>

<!-- ================= INTRODUCTION ================= -->
<section class="section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <div class="logo-panel">
                    <img src="<?= e(site_logo_url()) ?>" alt="FASTED COURIER official logo">
                    <p class="logo-caption">Fast Delivery. <span>Nationwide.</span></p>
                </div>
            </div>
            <div class="col-lg-7">
                <span class="eyebrow">Who We Are</span>
                <h2 class="section-title">A Courier Company <span>Built On Reliability</span></h2>
                <p class="about-text"><?= nl2br(e(setting('about_intro'))) ?></p>
                <div class="row g-3 mt-4">
                    <div class="col-sm-6">
                        <div class="achievement"><i class="bi bi-geo-alt"></i> Nationwide pickup &amp; delivery network</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="achievement"><i class="bi bi-shield-check"></i> Secure, insured and verifiable shipments</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="achievement"><i class="bi bi-graph-up-arrow"></i> Transparent, service-wise pricing</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="achievement"><i class="bi bi-clock-history"></i> Real-time tracking updates</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ================= MISSION / VISION ================= -->
<section class="section-tight">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="mv-card">
                    <span class="icon-circle"><i class="bi bi-bullseye"></i></span>
                    <h3>Our Mission</h3>
                    <p><?= nl2br(e(setting('about_mission'))) ?></p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="mv-card">
                    <span class="icon-circle"><i class="bi bi-eye"></i></span>
                    <h3>Our Vision</h3>
                    <p><?= nl2br(e(setting('about_vision'))) ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ================= TEAM / ACHIEVEMENTS ================= -->
<section class="section-tight pt-0">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-head">
                        <h3><i class="bi bi-people"></i> Our Team</h3>
                    </div>
                    <div class="panel-body">
                        <?php foreach ($team as $member): ?>
                            <?php
                            // "Name - Role" format stored in settings
                            $parts = array_map('trim', explode('-', $member, 2));
                            $name  = $parts[0] ?? $member;
                            $role  = $parts[1] ?? 'Team Member';
                            ?>
                            <div class="team-member">
                                <span class="team-avatar"><?= e(strtoupper(substr($name, 0, 1))) ?></span>
                                <div>
                                    <strong><?= e($name) ?></strong>
                                    <small><?= e($role) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-head">
                        <h3><i class="bi bi-trophy"></i> Our Achievements</h3>
                    </div>
                    <div class="panel-body">
                        <div class="d-grid gap-3">
                            <?php foreach ($awards as $award): ?>
                                <div class="achievement"><i class="bi bi-patch-check-fill"></i> <?= e($award) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
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
                <div class="value"><span data-count="98">0</span>%</div>
                <div class="label">On-Time Delivery</div>
            </div>
        </div>
    </div>
</section>

<!-- ================= GALLERY ================= -->
<?php if ($gallery): ?>
<section class="section-tight pt-0">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Website Gallery</span>
            <h2 class="section-title">Inside <span>FASTED COURIER</span></h2>
            <p class="section-sub">A look at our fleet, sorting hubs and the people who keep your parcels moving.</p>
        </div>
        <div class="gallery-grid">
            <?php foreach ($gallery as $image): ?>
                <a class="gallery-item" href="<?= e(url($image['image_path'])) ?>" target="_blank" rel="noopener"
                   data-gallery-item data-caption="<?= e($image['title']) ?>" aria-haspopup="dialog"
                   aria-label="View <?= e($image['title']) ?> larger">
                    <img src="<?= e(url($image['image_path'])) ?>" alt="<?= e($image['title']) ?>" loading="lazy">
                    <span class="zoom-hint"><i class="bi bi-arrows-fullscreen"></i></span>
                    <span class="caption"><?= e($image['title']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($gallery): ?>
<div class="gallery-lightbox" id="galleryLightbox" role="dialog" aria-modal="true"
     aria-label="Gallery image preview" hidden>
    <button type="button" class="lb-close" data-lb-close aria-label="Close preview">
        <i class="bi bi-x-lg"></i>
    </button>
    <button type="button" class="lb-nav prev" data-lb-prev aria-label="Previous image">
        <i class="bi bi-chevron-left"></i>
    </button>
    <button type="button" class="lb-nav next" data-lb-next aria-label="Next image">
        <i class="bi bi-chevron-right"></i>
    </button>
    <figure class="lb-figure">
        <img src="" alt="" data-lb-image>
        <figcaption>
            <span class="lb-caption" data-lb-caption></span>
            <span class="lb-counter" data-lb-counter></span>
        </figcaption>
    </figure>
</div>
<?php endif; ?>

<!-- ================= CTA ================= -->
<section class="section-tight pt-0">
    <div class="container">
        <div class="cta-band">
            <div class="position-relative">
                <h2>Let's move your parcel.</h2>
                <p>Book online in minutes or get in touch with our support team.</p>
            </div>
            <div class="cta-actions">
                <a href="<?= url('book.php') ?>" class="btn btn-navy">Book a Parcel <i class="bi bi-arrow-right"></i></a>
                <a href="<?= url('contact.php') ?>" class="btn btn-outline-dark-custom">Contact Us</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
