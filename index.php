<?php
/**
 * BookMyCourt — Landing Page (Homepage)
 */
require_once __DIR__ . '/bootstrap.php';

$pageTitle       = 'Home';
$pageDescription = 'Book verified badminton courts across Pune with real-time slot availability. No hassle, just play.';
$pageClass       = 'page-home';

// Fetch featured venues (top 6) with avg rating
try {
    $pdo = db();
    $featuredVenues = $pdo->query(
        "SELECT c.id, c.hall_name, c.location, c.price_per_hour, c.num_courts,
                c.facilities, c.opening_time, c.closing_time,
                COALESCE(ROUND(AVG(r.rating)::numeric, 1), 0) AS avg_rating,
                COUNT(r.id) AS review_count,
                COUNT(b.id) AS total_bookings
         FROM courts c
         LEFT JOIN reviews r ON r.venue_id = c.id AND r.is_approved = TRUE
         LEFT JOIN bookings b ON b.venue_id = c.id AND b.status = 'CONFIRMED'
         WHERE c.is_active = TRUE
         GROUP BY c.id
         ORDER BY total_bookings DESC, c.id
         LIMIT 6"
    )->fetchAll();

    // Stats for hero section
    $stats = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM courts WHERE is_active = TRUE) AS venue_count,
            (SELECT COUNT(*) FROM individual_courts ic JOIN courts c ON c.id = ic.venue_id WHERE c.is_active = TRUE) AS court_count,
            (SELECT COUNT(*) FROM users WHERE is_active = TRUE) AS user_count,
            (SELECT COUNT(*) FROM bookings WHERE status = 'CONFIRMED') AS booking_count"
    )->fetch();

} catch (PDOException $e) {
    error_log('[BookMyCourt] index.php error: ' . $e->getMessage());
    $featuredVenues = [];
    $stats = ['venue_count' => 10, 'court_count' => 45, 'user_count' => 0, 'booking_count' => 0];
}

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/home.css">

<!-- ─── HERO SECTION ─────────────────────────────────────────── -->
<section class="hero-section" id="hero">
  <div class="hero-bg">
    <div class="hero-gradient"></div>
    <div class="hero-grid-overlay"></div>
  </div>

  <div class="container">
    <div class="hero-content">

      <!-- Label -->
      <div class="section-label animate-on-scroll">
        <i class="bi bi-geo-alt-fill"></i> Pune's Premier Badminton Platform
      </div>

      <!-- Headline -->
      <h1 class="hero-headline animate-on-scroll delay-1">
        Find your court.<br>
        <span class="hero-headline-accent">Own your game.</span>
      </h1>

      <p class="hero-subtext animate-on-scroll delay-2">
        Book verified badminton courts across Pune with real-time slot validation.
        No double bookings. No phone calls. Just play.
      </p>

      <!-- Quick Search Widget -->
      <div class="hero-search-card animate-on-scroll delay-3">
        <form action="<?php echo BASE_URL; ?>/courts.php" method="GET" class="hero-search-form">
          <div class="search-field-group">
            <div class="search-field">
              <label class="search-field-label"><i class="bi bi-geo-alt"></i> Location</label>
              <select name="location" class="search-field-input">
                <option value="">All Areas</option>
                <option value="Kothrud">Kothrud</option>
                <option value="Baner">Baner</option>
                <option value="Wakad">Wakad</option>
                <option value="Pimple Saudagar">Pimple Saudagar</option>
                <option value="Hinjewadi">Hinjewadi</option>
                <option value="Aundh">Aundh</option>
                <option value="Shivaji Nagar">Shivaji Nagar</option>
                <option value="Hadapsar">Hadapsar</option>
                <option value="Magarpatta">Magarpatta</option>
              </select>
            </div>

            <div class="search-divider"></div>

            <div class="search-field">
              <label class="search-field-label"><i class="bi bi-calendar3"></i> Date</label>
              <input type="date" name="date" class="search-field-input"
                     min="<?php echo date('Y-m-d'); ?>"
                     value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="search-divider"></div>

            <div class="search-field">
              <label class="search-field-label"><i class="bi bi-clock"></i> Time</label>
              <select name="time" class="search-field-input">
                <option value="">Any Time</option>
                <option>6:00-7:00 AM</option>
                <option>7:00-8:00 AM</option>
                <option>8:00-9:00 AM</option>
                <option>9:00-10:00 AM</option>
                <option>10:00-11:00 AM</option>
                <option>4:00-5:00 PM</option>
                <option>5:00-6:00 PM</option>
                <option>6:00-7:00 PM</option>
                <option>7:00-8:00 PM</option>
                <option>8:00-9:00 PM</option>
                <option>9:00-10:00 PM</option>
              </select>
            </div>
          </div>

          <button type="submit" class="btn btn-accent btn-lg hero-search-btn">
            <i class="bi bi-search"></i> Find Courts
          </button>
        </form>
      </div>

      <!-- Stats Row -->
      <div class="hero-stats animate-on-scroll delay-4">
        <div class="hero-stat">
          <span class="hero-stat-value" data-target="<?php echo (int)$stats['venue_count']; ?>">0</span>
          <span class="hero-stat-label">Verified Venues</span>
        </div>
        <div class="hero-stat-divider"></div>
        <div class="hero-stat">
          <span class="hero-stat-value" data-target="<?php echo (int)$stats['court_count']; ?>">0</span>
          <span class="hero-stat-label">Individual Courts</span>
        </div>
        <div class="hero-stat-divider"></div>
        <div class="hero-stat">
          <span class="hero-stat-value">Pune</span>
          <span class="hero-stat-label">Coverage Area</span>
        </div>
        <div class="hero-stat-divider"></div>
        <div class="hero-stat">
          <span class="hero-stat-value">100%</span>
          <span class="hero-stat-label">Secure Bookings</span>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ─── FEATURED VENUES ──────────────────────────────────────── -->
<section class="section featured-section">
  <div class="container">

    <div class="section-header animate-on-scroll">
      <div>
        <div class="section-label">Popular Courts</div>
        <h2 class="section-title">Top Venues in Pune</h2>
        <p class="section-subtitle">Browse our most popular badminton halls with verified facilities and live availability.</p>
      </div>
      <a href="<?php echo BASE_URL; ?>/courts.php" class="btn btn-outline">
        View All <i class="bi bi-arrow-right"></i>
      </a>
    </div>

    <div class="venue-grid">
      <?php foreach ($featuredVenues as $i => $venue): ?>
      <?php
        $facilities = array_slice(explode(',', $venue['facilities'] ?? ''), 0, 3);
        $images     = getVenueImages((int)$venue['id']);
        $rating     = (float)$venue['avg_rating'];
      ?>
      <div class="venue-card card animate-on-scroll delay-<?php echo min($i + 1, 4); ?>">

        <!-- Image Carousel -->
        <div class="card-img-wrapper venue-card-carousel" data-venue-id="<?php echo $venue['id']; ?>">
          <?php foreach ($images as $imgIndex => $imgUrl): ?>
          <img src="<?php echo e($imgUrl); ?>"
               class="venue-carousel-img <?php echo $imgIndex === 0 ? 'active' : ''; ?>"
               alt="<?php echo e($venue['hall_name']); ?>"
               loading="<?php echo $i < 3 ? 'eager' : 'lazy'; ?>"
               onerror="this.src='<?php echo BASE_URL; ?>/assets/images/logo.png'">
          <?php endforeach; ?>

          <!-- Overlay -->
          <div class="venue-card-overlay"></div>

          <!-- Price Tag -->
          <div class="venue-price-tag"><?php echo formatPrice((float)$venue['price_per_hour']); ?>/hr</div>

          <!-- Carousel dots -->
          <div class="venue-carousel-dots">
            <?php for ($d = 0; $d < 3; $d++): ?>
            <div class="carousel-dot <?php echo $d === 0 ? 'active' : ''; ?>" data-index="<?php echo $d; ?>"></div>
            <?php endfor; ?>
          </div>
        </div>

        <!-- Card Body -->
        <div class="card-body">
          <!-- Name + Rating -->
          <div class="flex flex-between items-center mb-2">
            <h3 class="card-title" style="margin:0;"><?php echo e($venue['hall_name']); ?></h3>
            <?php if ($rating > 0): ?>
            <div class="star-rating">
              <?php for ($s = 1; $s <= 5; $s++): ?>
              <i class="bi bi-star<?php echo $s <= $rating ? '-fill' : ($s - 0.5 <= $rating ? '-half' : ''); ?> star <?php echo $s > $rating ? 'empty' : ''; ?>"></i>
              <?php endfor; ?>
              <span class="rating-count">(<?php echo (int)$venue['review_count']; ?>)</span>
            </div>
            <?php endif; ?>
          </div>

          <!-- Location -->
          <p class="card-subtitle">
            <i class="bi bi-geo-alt"></i> <?php echo e($venue['location']); ?>
          </p>

          <!-- Info Row -->
          <div class="venue-info-row">
            <span class="venue-info-item">
              <i class="bi bi-grid-3x3-gap"></i>
              <?php echo (int)$venue['num_courts']; ?> Courts
            </span>
            <span class="venue-info-item">
              <i class="bi bi-clock"></i>
              <?php echo date('g A', strtotime($venue['opening_time'])); ?>–<?php echo date('g A', strtotime($venue['closing_time'])); ?>
            </span>
          </div>

          <!-- Facilities -->
          <div class="facility-tags mt-4">
            <?php foreach ($facilities as $f): ?>
            <span class="facility-tag"><i class="bi bi-check-circle-fill"></i><?php echo e(trim($f)); ?></span>
            <?php endforeach; ?>
          </div>

          <!-- CTA -->
          <div class="mt-auto" style="margin-top: 20px;">
            <a href="<?php echo BASE_URL; ?>/court-details.php?id=<?php echo $venue['id']; ?>"
               class="btn btn-accent btn-full">
              <i class="bi bi-calendar-check"></i> View & Book
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center mt-8">
      <a href="<?php echo BASE_URL; ?>/courts.php" class="btn btn-outline btn-lg">
        <i class="bi bi-grid"></i> Browse All <?php echo (int)$stats['venue_count']; ?> Venues
      </a>
    </div>
  </div>
</section>

<!-- ─── HOW IT WORKS ─────────────────────────────────────────── -->
<section class="section how-section">
  <div class="container">
    <div class="text-center animate-on-scroll">
      <div class="section-label">Simple Process</div>
      <h2 class="section-title">Book in 3 Simple Steps</h2>
      <p class="section-subtitle" style="margin: 0 auto;">From browsing to court in under 2 minutes.</p>
    </div>

    <div class="how-grid">
      <div class="how-card animate-on-scroll delay-1">
        <div class="how-number">01</div>
        <div class="how-icon"><i class="bi bi-search"></i></div>
        <h3 class="how-title">Browse Venues</h3>
        <p class="how-desc">Filter by location, price, facilities, and availability. Find the perfect court for your game.</p>
      </div>
      <div class="how-arrow animate-on-scroll delay-1"><i class="bi bi-arrow-right"></i></div>

      <div class="how-card animate-on-scroll delay-2">
        <div class="how-number">02</div>
        <div class="how-icon"><i class="bi bi-calendar-check"></i></div>
        <h3 class="how-title">Select Slot</h3>
        <p class="how-desc">Pick your court, date, and time slot. Live availability shown directly from our database.</p>
      </div>
      <div class="how-arrow animate-on-scroll delay-2"><i class="bi bi-arrow-right"></i></div>

      <div class="how-card animate-on-scroll delay-3">
        <div class="how-number">03</div>
        <div class="how-icon"><i class="bi bi-shield-check"></i></div>
        <h3 class="how-title">Pay & Play</h3>
        <p class="how-desc">Secure online payment. Booking confirmed instantly with a unique ID. No paperwork needed.</p>
      </div>
    </div>
  </div>
</section>

<!-- ─── WHY BOOKMYCOURT ───────────────────────────────────────── -->
<section class="section benefits-section">
  <div class="container">
    <div class="benefits-grid">
      <div class="benefits-text animate-on-scroll">
        <div class="section-label">Why Choose Us</div>
        <h2 class="section-title">The smarter way to book a badminton court</h2>
        <p class="section-subtitle">We built BookMyCourt to solve the chaos of phone-based bookings with a platform that's fast, secure, and reliable.</p>

        <div class="benefit-list">
          <div class="benefit-item">
            <div class="benefit-icon"><i class="bi bi-lightning-fill"></i></div>
            <div>
              <h4>Real-time availability</h4>
              <p>Slot data pulled live from the database. No stale information.</p>
            </div>
          </div>
          <div class="benefit-item">
            <div class="benefit-icon"><i class="bi bi-lock-fill"></i></div>
            <div>
              <h4>Zero double-booking</h4>
              <p>Database-level constraints make concurrent double bookings impossible.</p>
            </div>
          </div>
          <div class="benefit-item">
            <div class="benefit-icon"><i class="bi bi-credit-card"></i></div>
            <div>
              <h4>Secure payments</h4>
              <p>Razorpay-powered payments with server-side signature verification.</p>
            </div>
          </div>
          <div class="benefit-item">
            <div class="benefit-icon"><i class="bi bi-phone"></i></div>
            <div>
              <h4>Mobile-first design</h4>
              <p>Book on any device. Fully responsive from phone to desktop.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="benefits-visual animate-on-scroll delay-2">
        <div class="benefits-card-stack">
          <div class="benefit-floating-card card1">
            <div class="floating-card-icon success"><i class="bi bi-check-circle-fill"></i></div>
            <div>
              <div class="floating-card-title">Booking Confirmed</div>
              <div class="floating-card-sub">Court 3 · 7:00-8:00 PM</div>
            </div>
          </div>
          <div class="benefit-floating-card card2">
            <div class="floating-card-icon accent"><i class="bi bi-shield-check"></i></div>
            <div>
              <div class="floating-card-title">Payment Verified</div>
              <div class="floating-card-sub">₹280 · Secure</div>
            </div>
          </div>
          <div class="benefit-floating-card card3">
            <i class="bi bi-bell-fill" style="color:var(--c-accent-light);font-size:1.2rem;"></i>
            <div>
              <div class="floating-card-title">Reminder</div>
              <div class="floating-card-sub">Your match starts in 1 hour</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ─── CTA SECTION ──────────────────────────────────────────── -->
<section class="cta-section">
  <div class="container">
    <div class="cta-card animate-on-scroll">
      <div class="cta-content">
        <h2 class="cta-headline">Ready to play?</h2>
        <p class="cta-sub">10 venues. 45+ courts. Across Pune. Book your next game now.</p>
        <div class="cta-actions">
          <a href="<?php echo BASE_URL; ?>/courts.php" class="btn btn-accent btn-xl">
            <i class="bi bi-calendar-check"></i> Browse Courts
          </a>
          <?php if (empty($_SESSION['user_id'])): ?>
          <a href="<?php echo BASE_URL; ?>/login.php?tab=signup" class="btn btn-outline btn-xl">
            <i class="bi bi-person-plus"></i> Create Free Account
          </a>
          <?php endif; ?>
        </div>
      </div>
      <div class="cta-decoration">
        <div class="cta-circle"></div>
        <i class="bi bi-award-fill cta-icon"></i>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
// ─── Venue card image carousel ─────────────────────────────────
document.querySelectorAll('.venue-card-carousel').forEach(function(carousel) {
    const images = carousel.querySelectorAll('.venue-carousel-img');
    const dots   = carousel.querySelectorAll('.carousel-dot');
    let current  = 0;
    let timer    = null;

    function goTo(index) {
        images[current].classList.remove('active');
        dots[current] && dots[current].classList.remove('active');
        current = (index + images.length) % images.length;
        images[current].classList.add('active');
        dots[current] && dots[current].classList.add('active');
    }

    function startAuto() {
        timer = setInterval(() => goTo(current + 1), 3000);
    }

    function stopAuto() { clearInterval(timer); }

    dots.forEach(function(dot, i) {
        dot.addEventListener('click', function(e) {
            e.preventDefault();
            stopAuto();
            goTo(i);
            startAuto();
        });
    });

    carousel.addEventListener('mouseenter', stopAuto);
    carousel.addEventListener('mouseleave', startAuto);
    startAuto();
});

// ─── Animated stat counters ────────────────────────────────────
function animateCounter(el) {
    const target = parseInt(el.dataset.target, 10);
    if (isNaN(target)) return;
    const duration = 1500;
    const step     = target / (duration / 16);
    let   current  = 0;
    const timer = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = Math.round(current);
        if (current >= target) clearInterval(timer);
    }, 16);
}

// Only animate counters when visible
const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting && entry.target.dataset.target) {
            animateCounter(entry.target);
            counterObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });

document.querySelectorAll('[data-target]').forEach(el => counterObserver.observe(el));
</script>
