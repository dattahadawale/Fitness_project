<?php
/**
 * Public Landing Page
 * Gym Management System - Apex Fitness Club
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$db = getDB();

// Fetch active membership plans
$plans = [];
try {
    $plansStmt = $db->query("SELECT * FROM membership_plans WHERE status = 'active' ORDER BY price ASC");
    $plans = $plansStmt->fetchAll();
} catch (Exception $e) {
}

// Fetch active trainers
$trainers = [];
try {
    $trainersStmt = $db->query("SELECT * FROM trainers WHERE status = 'active' ORDER BY id ASC LIMIT 3");
    $trainers = $trainersStmt->fetchAll();
} catch (Exception $e) {
}

// Gym contact details shown in the Contact section — edit these three lines.
$gymAddress = 'Gokhalenagar, Pune - 411016';
$gymPhone = '+91 93596 03384';
$gymEmail = 'dattahadawale9322@gmail.com';

// Keep what the visitor typed if the inquiry form failed validation
$inquiryOld = $_SESSION['inquiry_old'] ?? [];
unset($_SESSION['inquiry_old']);

$pageTitle = 'Apex Fitness Club | Build Your Elite Physique';
$bodyClass = 'landing-body';
include __DIR__ . '/includes/header.php';
?>

<!-- Public Navigation Header -->
<header class="landing-header">
    <div class="sidebar-brand" style="border: none; padding: 0; height: auto;">
        <div class="brand-icon">⚡</div>
        <div class="brand-text">APEX<span>FITNESS</span></div>
    </div>

    <ul class="landing-nav-links">
        <li><a href="#about">About</a></li>
        <li><a href="#services">Services</a></li>
        <li><a href="#plans">Memberships</a></li>
        <li><a href="#trainers">Coaches</a></li>
        <li><a href="#contact">Contact</a></li>
    </ul>

    <div style="display: flex; align-items: center; gap: 12px;">
        <?php if (is_logged_in()): ?>
            <?php $dashboardUrl = has_role('admin') ? 'admin/dashboard.php' : 'member/dashboard.php'; ?>
            <a href="<?= base_url($dashboardUrl) ?>" class="btn btn-primary btn-sm">My Dashboard &rarr;</a>
        <?php else: ?>
            <a href="<?= base_url('login.php') ?>" class="btn btn-secondary btn-sm">Sign In</a>
            <a href="<?= base_url('register.php') ?>" class="btn btn-primary btn-sm">Join Apex</a>
        <?php endif; ?>
    </div>
</header>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-content">
        <div class="hero-pill">
            <span>🔥</span> Unleash Your True Potential
        </div>
        <h1 class="hero-title">
            TRANSFORM YOUR BODY.<br>
            <span>FORGE YOUR LEGACY.</span>
        </h1>
        <p class="hero-desc">
            State-of-the-art strength equipment, certified elite coaches, science-based workout regimens, and an
            empowering athletic community.
        </p>
        <div class="hero-actions">
            <a href="<?= base_url('register.php') ?>" class="btn btn-primary btn-lg">Start Free Assessment &rarr;</a>
            <a href="#plans" class="btn btn-secondary btn-lg">Explore Plans</a>
        </div>
    </div>
</section>

<!-- About Section -->
<section class="section" id="about">
    <div class="section-title-wrap">
        <span class="section-badge">ABOUT APEX FITNESS</span>
        <h2 class="section-title">Built For Results, Driven By Passion</h2>
        <p class="section-desc">We combine Olympic-grade strength training gear with personalized coaching to deliver
            remarkable physical transformations.</p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card kpi-orange">
            <div class="kpi-details">
                <span class="kpi-title">Members Strong</span>
                <span class="kpi-value">1,200+</span>
                <span class="kpi-subtext">Active athletes community</span>
            </div>
            <div class="kpi-icon">🏋️</div>
        </div>
        <div class="kpi-card kpi-green">
            <div class="kpi-details">
                <span class="kpi-title">Certified Coaches</span>
                <span class="kpi-value">15+</span>
                <span class="kpi-subtext">Expert fitness instructors</span>
            </div>
            <div class="kpi-icon">🏆</div>
        </div>
        <div class="kpi-card kpi-blue">
            <div class="kpi-details">
                <span class="kpi-title">Training Area</span>
                <span class="kpi-value">18,000</span>
                <span class="kpi-subtext">Sq ft state-of-the-art gym</span>
            </div>
            <div class="kpi-icon">📍</div>
        </div>
        <div class="kpi-card kpi-amber">
            <div class="kpi-details">
                <span class="kpi-title">Success Rate</span>
                <span class="kpi-value">98.4%</span>
                <span class="kpi-subtext">Goal achievement index</span>
            </div>
            <div class="kpi-icon">⭐</div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="section" id="services"
    style="background: rgba(24, 29, 38, 0.4); border-radius: var(--radius-xl); border: 1px solid var(--border-color);">
    <div class="section-title-wrap">
        <span class="section-badge">OUR PROGRAMMING</span>
        <h2 class="section-title">World-Class Training Disciplines</h2>
        <p class="section-desc">Engineered for all fitness levels—from first-time gym goers to seasoned powerlifters.
        </p>
    </div>

    <div class="services-grid">
        <div class="service-card">
            <div class="service-icon">💪</div>
            <h3 class="service-title">Heavy Iron & Hypertrophy</h3>
            <p style="color: var(--text-muted); font-size: 14px; line-height: 1.6;">
                Free weights, calibrated Olympic plates, power cages, and premium selectorized machines for maximum
                muscle building.
            </p>
        </div>
        <div class="service-card">
            <div class="service-icon">⚡</div>
            <h3 class="service-title">HIIT & Metabolic Conditioning</h3>
            <p style="color: var(--text-muted); font-size: 14px; line-height: 1.6;">
                High-energy group workouts designed to torch body fat, enhance VO2 max, and elevate stamina.
            </p>
        </div>
        <div class="service-card">
            <div class="service-icon">🎯</div>
            <h3 class="service-title">1-on-1 Personal Coaching</h3>
            <p style="color: var(--text-muted); font-size: 14px; line-height: 1.6;">
                Tailored periodization, form checks, postural analysis, and accountability with certified trainers.
            </p>
        </div>
        <div class="service-card">
            <div class="service-icon">🥗</div>
            <h3 class="service-title">Nutrition & Recovery Lounge</h3>
            <p style="color: var(--text-muted); font-size: 14px; line-height: 1.6;">
                Custom macro plans, post-workout protein shakes, dry sauna, and cold plunge therapy.
            </p>
        </div>
    </div>
</section>

<!-- Membership Plans Section -->
<section class="section" id="plans">
    <div class="section-title-wrap">
        <span class="section-badge">MEMBERSHIP TIERS</span>
        <h2 class="section-title">Transparent, Flexible Pricing</h2>
        <p class="section-desc">Choose a plan that fits your lifestyle. No hidden initiation fees or cancellation
            penalties.</p>
    </div>

    <div class="plans-grid">
        <?php foreach ($plans as $index => $plan): ?>
            <?php $isFeatured = ($index === 1 || stripos($plan['plan_name'], 'pro') !== false); ?>
            <div class="pricing-card <?= $isFeatured ? 'featured' : '' ?>">
                <?php if ($isFeatured): ?>
                    <div class="featured-badge">MOST POPULAR</div>
                <?php endif; ?>
                <h3 class="plan-name"><?= e($plan['plan_name']) ?></h3>
                <div class="plan-price">
                    <?= format_currency($plan['price']) ?>
                    <span>/ <?= (int) $plan['duration_days'] ?> Days</span>
                </div>
                <p style="color: var(--text-dim); font-size: 13px; min-height: 38px;">
                    <?= e($plan['description'] ?: 'Complete gym access with free locker and orientation.') ?>
                </p>

                <ul class="plan-features">
                    <li>Full Gym Floor & Equipment Access</li>
                    <li>Digital Workout Log & Tracking</li>
                    <li>Locker & Shower Access</li>
                    <?php if ($plan['duration_days'] >= 90): ?>
                        <li>Complimentary Personal Training Session</li>
                        <li>Body Composition & Macro Analysis</li>
                    <?php endif; ?>
                    <?php if ($plan['duration_days'] >= 180): ?>
                        <li>Unlimited Sauna & Steam Bath</li>
                        <li>Guest Passes (2 per month)</li>
                    <?php endif; ?>
                </ul>

                <?php if (is_logged_in() && has_role('member')): ?>
                    <form action="<?= base_url('actions/phonepe-actions.php') ?>" method="POST"
                        data-loading="Connecting to PhonePe...">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="start_checkout">
                        <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                        <button type="submit" class="btn <?= $isFeatured ? 'btn-primary' : 'btn-secondary' ?>"
                            style="width: 100%;">
                            Pay Now with PhonePe &rarr;
                        </button>
                    </form>
                <?php elseif (is_logged_in()): ?>
                    <a href="<?= base_url('login.php') ?>" class="btn <?= $isFeatured ? 'btn-primary' : 'btn-secondary' ?>"
                        style="width: 100%;">
                        Sign in as a member to pay &rarr;
                    </a>
                <?php else: ?>
                    <a href="<?= base_url('register.php?plan_id=' . (int) $plan['id']) ?>"
                        class="btn <?= $isFeatured ? 'btn-primary' : 'btn-secondary' ?>" style="width: 100%;">
                        Pay Now with PhonePe &rarr;
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Trainers Section -->
<section class="section" id="trainers">
    <div class="section-title-wrap">
        <span class="section-badge">OUR EXPERT COACHES</span>
        <h2 class="section-title">Meet Your Fitness Mentors</h2>
        <p class="section-desc">Dedicated professionals committed to guiding your form, nutrition, and mindset.</p>
    </div>

    <div class="trainers-grid">
        <?php foreach ($trainers as $trainer): ?>
            <div class="trainer-card">
                <div class="trainer-avatar">
                    <?= strtoupper(substr($trainer['name'], 0, 1)) ?>
                </div>
                <h4 class="trainer-name"><?= e($trainer['name']) ?></h4>
                <div class="trainer-spec"><?= e($trainer['specialization']) ?></div>
                <div style="font-size: 12px; color: var(--text-dim); margin-top: 4px;">
                    <?= (int) $trainer['experience_years'] ?>+ Years Experience
                </div>
                <p class="trainer-bio"><?= e($trainer['bio']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Contact & Footer Section -->
<section class="section" id="contact" style="border-top: 1px solid var(--border-color); padding-bottom: 40px;">
    <div
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 40px; margin-bottom: 60px;">
        <div>
            <div class="sidebar-brand" style="border: none; padding: 0; margin-bottom: 16px;">
                <div class="brand-icon">⚡</div>
                <div class="brand-text">APEX<span>FITNESS</span></div>
            </div>
            <p style="color: var(--text-muted); font-size: 14px; line-height: 1.6; max-width: 340px;">
                Apex Fitness Club is dedicated to building strength, endurance, and lifelong healthy habits with
                state-of-the-art facilities.
            </p>
        </div>

        <div>
            <h4 style="color: #ffffff; font-size: 16px; margin-bottom: 16px;">Operating Hours</h4>
            <p style="color: var(--text-muted); font-size: 14px; line-height: 1.8;">
                <strong>Monday – Friday:</strong> 05:00 AM – 11:00 PM<br>
                <strong>Saturday:</strong> 06:00 AM – 10:00 PM<br>
                <strong>Sunday:</strong> 07:00 AM – 08:00 PM
            </p>
        </div>

        <div>
            <h4 style="color: #ffffff; font-size: 16px; margin-bottom: 16px;">Gym Location & Contact</h4>
            <p style="color: var(--text-muted); font-size: 14px; line-height: 1.8;">
                📍 <?= e($gymAddress) ?><br>
                📞 <?= e($gymPhone) ?><br>
                ✉️ <?= e($gymEmail) ?>
            </p>
        </div>
    </div>

    <!-- Inquiry Form -->
    <div id="inquiry"
        style="max-width: 640px; margin: 0 auto 60px; background: var(--bg-card, rgba(24, 29, 38, 0.6)); border: 1px solid var(--border-color); border-radius: var(--radius-xl); padding: 32px;">
        <h3 style="color: #ffffff; font-size: 22px; margin-bottom: 6px;">Send Us an Inquiry</h3>
        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">Leave your details and our team will
            get back to you.</p>

        <form action="<?= base_url('actions/inquiry-actions.php') ?>" method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="submit_inquiry">
            <!-- Honeypot: real visitors never see or fill this -->
            <div style="position: absolute; left: -9999px;" aria-hidden="true">
                <input type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-group">
                <label class="form-label" for="inq_name">Name <span class="required">*</span></label>
                <input type="text" id="inq_name" name="name" class="form-control" maxlength="120"
                    placeholder="Your full name" value="<?= e($inquiryOld['name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="inq_address">Address <span class="required">*</span></label>
                <input type="text" id="inq_address" name="address" class="form-control" maxlength="255"
                    placeholder="Your address" value="<?= e($inquiryOld['address'] ?? '') ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="inq_mobile">Mobile No. <span class="required">*</span></label>
                    <input type="tel" id="inq_mobile" name="mobile" class="form-control" maxlength="25"
                        placeholder="+91 98765 43210" value="<?= e($inquiryOld['mobile'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="inq_email">Email <span class="required">*</span></label>
                    <input type="email" id="inq_email" name="email" class="form-control" maxlength="190"
                        placeholder="name@example.com" value="<?= e($inquiryOld['email'] ?? '') ?>" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Inquiry &rarr;</button>
        </form>
    </div>

    <div
        style="text-align: center; border-top: 1px solid var(--border-color); padding-top: 24px; color: var(--text-dim); font-size: 13px;">
        &copy; <?= date('Y') ?> Apex Fitness Club. Built with core PHP, HTML5, CSS3, JavaScript, and MySQL. All rights
        reserved.
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>