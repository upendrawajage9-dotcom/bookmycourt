<?php
/**
 * BookMyCourt — Courts Discovery Page
 *
 * Search, filter, and browse all venues.
 * Supports: location filter, price filter, sort order, search query.
 */

require_once __DIR__ . '/bootstrap.php';
requireLogin();

$pageTitle       = 'Browse Courts';
$pageDescription = 'Browse all badminton venues in Pune. Filter by location, price, facilities and real-time availability.';
$pageClass       = 'page-courts';

// ─── Filter & Sort Inputs ─────────────────────────────────────
$search   = sanitize($_GET['search']   ?? '');
$location = sanitize($_GET['location'] ?? '');
$maxPrice = filter_input(INPUT_GET, 'max_price', FILTER_VALIDATE_INT) ?: 0;
$sortBy   = sanitize($_GET['sort']     ?? 'recommended');
$date     = sanitize($_GET['date']     ?? date('Y-m-d'));
$time     = sanitize($_GET['time']     ?? '');

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) < strtotime(date('Y-m-d'))) {
    $date = date('Y-m-d');
}

// ─── Build Query ──────────────────────────────────────────────
try {
    $pdo = db();

    $where  = ['c.is_active = TRUE'];
    $params = [];

    if ($search) {
        $where[]  = "(c.hall_name ILIKE ? OR c.location ILIKE ? OR c.description ILIKE ?)";
        $like     = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($location) {
        $where[]  = "c.location ILIKE ?";
        $params[] = '%' . $location . '%';
    }

    if ($maxPrice > 0) {
        $where[]  = "c.price_per_hour <= ?";
        $params[] = $maxPrice;
    }

    $orderClause = match($sortBy) {
        'price_asc'  => 'c.price_per_hour ASC',
        'price_desc' => 'c.price_per_hour DESC',
        'rating'     => 'avg_rating DESC NULLS LAST, c.id ASC',
        default      => 'total_bookings DESC, c.id ASC',
    };

    $sql = "
        SELECT c.id, c.hall_name, c.location, c.address,
               c.price_per_hour, c.num_courts, c.facilities,
               c.opening_time, c.closing_time,
               COALESCE(ROUND(AVG(r.rating)::numeric, 1), 0)  AS avg_rating,
               COUNT(DISTINCT r.id)                            AS review_count,
               COUNT(DISTINCT b.id)                            AS total_bookings
        FROM courts c
        LEFT JOIN reviews r ON r.venue_id = c.id AND r.is_approved = TRUE
        LEFT JOIN bookings b ON b.venue_id = c.id AND b.status = 'CONFIRMED'
        WHERE " . implode(' AND ', $where) . "
        GROUP BY c.id
        ORDER BY $orderClause
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $venues = $stmt->fetchAll();

    // Get distinct locations for filter dropdown
    $locations = $pdo->query(
        "SELECT DISTINCT location FROM courts WHERE is_active = TRUE ORDER BY location"
    )->fetchAll(PDO::FETCH_COLUMN);

    // Price range for filter
    $priceRange = $pdo->query(
        "SELECT MIN(price_per_hour) AS min_price, MAX(price_per_hour) AS max_price FROM courts WHERE is_active = TRUE"
    )->fetch();

} catch (PDOException $e) {
    error_log('[BookMyCourt] courts.php error: ' . $e->getMessage());
    $venues     = [];
    $locations  = [];
    $priceRange = ['min_price' => 0, 'max_price' => 1000];
}

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/courts.css">

<!-- ─── Page Header ───────────────────────────────────────────── -->
<div class="courts-page-header">
  <div class="container">
    <div class="section-label"><i class="bi bi-grid"></i> Venues</div>
    <h1 class="section-title" style="margin-bottom:var(--sp-2);">Select a Badminton Hall</h1>
    <p class="section-subtitle">
      <?php echo count($venues); ?> verified venues across Pune.
      View facilities, pricing and book instantly.
    </p>
  </div>
</div>

<div class="container">
  <div class="courts-layout">

    <!-- ─── Sidebar Filters ──────────────────────────────────── -->
    <aside class="courts-sidebar" id="courtsSidebar">
      <div class="sidebar-card">
        <div class="sidebar-header">
          <h3 class="sidebar-title"><i class="bi bi-funnel-fill"></i> Filters</h3>
          <a href="<?php echo BASE_URL; ?>/courts.php" class="sidebar-clear">Clear all</a>
        </div>

        <form method="GET" id="filterForm">
          <!-- Search -->
          <div class="filter-group">
            <label class="filter-label">Search Venues</label>
            <div class="search-bar">
              <i class="bi bi-search" style="padding: 0 var(--sp-4); color: var(--c-text-muted);"></i>
              <input type="text" name="search" placeholder="Venue name or area..."
                     value="<?php echo e($search); ?>"
                     class="search-bar-input">
            </div>
          </div>

          <!-- Location -->
          <div class="filter-group">
            <label class="filter-label">Location</label>
            <select name="location" class="form-control" onchange="this.form.submit()">
              <option value="">All Locations</option>
              <?php foreach ($locations as $loc): ?>
              <option value="<?php echo e($loc); ?>" <?php echo $location === $loc ? 'selected' : ''; ?>>
                <?php echo e($loc); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Price Range -->
          <div class="filter-group">
            <label class="filter-label">
              Max Price: <span id="priceLabel" class="text-accent">
                <?php echo $maxPrice ? formatPrice($maxPrice) : 'Any'; ?>
              </span>
            </label>
            <input type="range" name="max_price" class="price-slider"
                   min="<?php echo (int)$priceRange['min_price']; ?>"
                   max="<?php echo (int)$priceRange['max_price']; ?>"
                   step="50"
                   value="<?php echo $maxPrice ?: (int)$priceRange['max_price']; ?>"
                   oninput="document.getElementById('priceLabel').textContent = '₹' + this.value">
            <div class="price-range-labels">
              <span><?php echo formatPrice((float)$priceRange['min_price']); ?></span>
              <span><?php echo formatPrice((float)$priceRange['max_price']); ?></span>
            </div>
          </div>

          <!-- Date -->
          <div class="filter-group">
            <label class="filter-label">Available On</label>
            <input type="date" name="date" class="form-control"
                   value="<?php echo e($date); ?>"
                   min="<?php echo date('Y-m-d'); ?>">
          </div>

          <!-- Time -->
          <div class="filter-group">
            <label class="filter-label">Time Slot</label>
            <select name="time" class="form-control">
              <option value="">Any Time</option>
              <?php foreach (['6:00-7:00 AM','7:00-8:00 AM','8:00-9:00 AM','9:00-10:00 AM','10:00-11:00 AM','11:00-12:00 PM','4:00-5:00 PM','5:00-6:00 PM','6:00-7:00 PM','7:00-8:00 PM','8:00-9:00 PM','9:00-10:00 PM'] as $slot): ?>
              <option value="<?php echo e($slot); ?>" <?php echo $time === $slot ? 'selected' : ''; ?>>
                <?php echo e($slot); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Preserve sort -->
          <input type="hidden" name="sort" value="<?php echo e($sortBy); ?>">

          <button type="submit" class="btn btn-accent btn-full" style="margin-top: var(--sp-4);">
            <i class="bi bi-funnel"></i> Apply Filters
          </button>
        </form>
      </div>
    </aside>

    <!-- ─── Main Content ─────────────────────────────────────── -->
    <main class="courts-main">

      <!-- Toolbar -->
      <div class="courts-toolbar">
        <div class="toolbar-count">
          <strong><?php echo count($venues); ?></strong>
          <?php echo count($venues) === 1 ? 'venue' : 'venues'; ?> found
          <?php if ($search || $location || $maxPrice): ?>
          — <span class="text-muted text-sm">Filtered results</span>
          <?php endif; ?>
        </div>

        <div class="toolbar-actions">
          <!-- Mobile filter toggle -->
          <button class="btn btn-outline btn-sm" onclick="toggleSidebar()" id="filterToggle">
            <i class="bi bi-funnel"></i> Filters
          </button>

          <!-- Sort -->
          <select class="form-control" style="width:auto;padding:8px 32px 8px 12px;"
                  onchange="sortVenues(this.value)">
            <option value="recommended" <?php echo $sortBy === 'recommended' ? 'selected' : ''; ?>>Recommended</option>
            <option value="price_asc"   <?php echo $sortBy === 'price_asc'   ? 'selected' : ''; ?>>Price: Low → High</option>
            <option value="price_desc"  <?php echo $sortBy === 'price_desc'  ? 'selected' : ''; ?>>Price: High → Low</option>
            <option value="rating"      <?php echo $sortBy === 'rating'      ? 'selected' : ''; ?>>Highest Rated</option>
          </select>
        </div>
      </div>

      <!-- Venue Grid -->
      <?php if (empty($venues)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-search"></i></div>
        <h3>No venues found</h3>
        <p>Try adjusting your filters or search term to find available courts.</p>
        <a href="<?php echo BASE_URL; ?>/courts.php" class="btn btn-accent">Clear Filters</a>
      </div>

      <?php else: ?>
      <div class="venue-list-grid" id="venueGrid">
        <?php foreach ($venues as $i => $venue): ?>
        <?php
          $facilities = array_filter(array_map('trim', explode(',', $venue['facilities'] ?? '')));
          $rating     = (float) $venue['avg_rating'];
          $images     = getVenueImages((int)$venue['id']);
          $isFav      = false; // TODO: check favorites table
        ?>

        <article class="venue-list-card card animate-on-scroll" data-venue-id="<?php echo $venue['id']; ?>">

          <!-- Image Section -->
          <div class="vlc-image-wrap" data-carousel>
            <?php foreach ($images as $imgIndex => $imgUrl): ?>
            <img src="<?php echo e($imgUrl); ?>"
                 class="vlc-img <?php echo $imgIndex === 0 ? 'active' : ''; ?>"
                 alt="<?php echo e($venue['hall_name']); ?>"
                 loading="<?php echo $i < 4 ? 'eager' : 'lazy'; ?>"
                 onerror="this.src='<?php echo BASE_URL; ?>/assets/images/logo.png'">
            <?php endforeach; ?>

            <div class="venue-card-overlay"></div>
            <div class="vlc-price-tag"><?php echo formatPrice((float)$venue['price_per_hour']); ?>/hr</div>

            <div class="venue-carousel-dots">
              <?php for ($d = 0; $d < 3; $d++): ?>
              <div class="carousel-dot <?php echo $d === 0 ? 'active' : ''; ?>" data-index="<?php echo $d; ?>"></div>
              <?php endfor; ?>
            </div>

            <!-- Favorite button -->
            <button class="vlc-fav-btn" data-venue-id="<?php echo $venue['id']; ?>"
                    title="Add to favorites" onclick="toggleFavorite(this, <?php echo $venue['id']; ?>)">
              <i class="bi bi-heart<?php echo $isFav ? '-fill' : ''; ?>"></i>
            </button>
          </div>

          <!-- Info Section -->
          <div class="vlc-info">
            <div class="vlc-header">
              <div>
                <h2 class="vlc-name"><?php echo e($venue['hall_name']); ?></h2>
                <p class="vlc-location"><i class="bi bi-geo-alt-fill"></i> <?php echo e($venue['location']); ?></p>
              </div>
              <div class="vlc-rating-block">
                <?php if ($rating > 0): ?>
                <div class="vlc-rating-score"><?php echo number_format($rating, 1); ?></div>
                <div class="vlc-rating-stars">
                  <?php for ($s = 1; $s <= 5; $s++): ?>
                  <i class="bi bi-star<?php echo $s <= round($rating) ? '-fill' : ''; ?> star <?php echo $s > round($rating) ? 'empty' : ''; ?>"></i>
                  <?php endfor; ?>
                </div>
                <div class="vlc-rating-count"><?php echo (int)$venue['review_count']; ?> reviews</div>
                <?php else: ?>
                <div style="font-size:0.75rem;color:var(--c-text-muted);">No reviews yet</div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Meta Info -->
            <div class="vlc-meta">
              <span class="vlc-meta-item">
                <i class="bi bi-grid-3x3-gap"></i>
                <?php echo (int)$venue['num_courts']; ?> Courts
              </span>
              <span class="vlc-meta-item">
                <i class="bi bi-clock"></i>
                <?php echo date('g A', strtotime($venue['opening_time'])); ?>–<?php echo date('g A', strtotime($venue['closing_time'])); ?>
              </span>
              <?php if ($venue['total_bookings'] > 0): ?>
              <span class="vlc-meta-item text-accent">
                <i class="bi bi-fire"></i>
                <?php echo (int)$venue['total_bookings']; ?> bookings
              </span>
              <?php endif; ?>
            </div>

            <!-- Facilities -->
            <div class="facility-tags">
              <?php foreach (array_slice(array_values($facilities), 0, 4) as $f): ?>
              <span class="facility-tag">
                <i class="bi bi-check-circle-fill"></i><?php echo e($f); ?>
              </span>
              <?php endforeach; ?>
              <?php if (count($facilities) > 4): ?>
              <span class="facility-tag" style="background:rgba(37,99,235,0.1);border-color:rgba(37,99,235,0.2);color:var(--c-accent-light);">
                +<?php echo count($facilities) - 4; ?> more
              </span>
              <?php endif; ?>
            </div>

            <!-- CTA -->
            <div class="vlc-cta">
              <a href="<?php echo BASE_URL; ?>/court-details.php?id=<?php echo $venue['id']; ?>"
                 class="btn btn-outline btn-sm">
                View Details
              </a>
              <a href="<?php echo BASE_URL; ?>/book.php?venue_id=<?php echo $venue['id']; ?>"
                 class="btn btn-accent btn-sm">
                <i class="bi bi-calendar-check"></i> Book Now
              </a>
            </div>
          </div>

        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </main>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
// Sort redirect
function sortVenues(sortVal) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortVal);
    window.location.href = url.toString();
}

// Mobile filter sidebar toggle
function toggleSidebar() {
    document.getElementById('courtsSidebar').classList.toggle('open');
}

// Image carousels on venue cards
document.querySelectorAll('[data-carousel]').forEach(function(carousel) {
    const images = carousel.querySelectorAll('.vlc-img');
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

    dots.forEach((dot, i) => dot.addEventListener('click', (e) => {
        e.stopPropagation();
        clearInterval(timer);
        goTo(i);
        timer = setInterval(() => goTo(current + 1), 3200);
    }));

    timer = setInterval(() => goTo(current + 1), 3200);
    carousel.addEventListener('mouseenter', () => clearInterval(timer));
    carousel.addEventListener('mouseleave', () => { timer = setInterval(() => goTo(current + 1), 3200); });
});

// Favorites (requires login — handled server side if not logged in)
function toggleFavorite(btn, venueId) {
    const icon = btn.querySelector('i');
    const isFav = icon.classList.contains('bi-heart-fill');

    fetch('<?php echo BASE_URL; ?>/api/favorites.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${isFav ? 'remove' : 'add'}&venue_id=${venueId}&csrf_token=<?php echo csrfToken(); ?>`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.error) {
            icon.classList.toggle('bi-heart', isFav);
            icon.classList.toggle('bi-heart-fill', !isFav);
            btn.style.color = isFav ? '' : 'var(--c-danger-light)';
            showToast(isFav ? 'Removed from favorites' : 'Added to favorites', isFav ? 'info' : 'success');
        }
    })
    .catch(() => showToast('Could not update favorites', 'error'));
}

// Price slider auto-submit
let priceTimer;
document.querySelector('.price-slider')?.addEventListener('input', function() {
    clearTimeout(priceTimer);
    priceTimer = setTimeout(() => document.getElementById('filterForm').submit(), 800);
});
</script>
