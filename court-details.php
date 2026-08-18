<?php
/**
 * BookMyCourt — Court / Venue Details Page
 *
 * Shows full venue info, image gallery, individual courts,
 * availability by date, and booking CTA.
 */

require_once __DIR__ . '/bootstrap.php';
requireLogin();

$venueId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$venueId || $venueId <= 0) {
    flashSet('error', 'Invalid venue.');
    redirect(BASE_URL . '/courts.php');
}

try {
    $pdo = db();

    // ─── Fetch venue ──────────────────────────────────────────
    $stmt = $pdo->prepare(
        "SELECT c.*,
                COALESCE(ROUND(AVG(r.rating)::numeric, 1), 0) AS avg_rating,
                COUNT(DISTINCT r.id) AS review_count
         FROM courts c
         LEFT JOIN reviews r ON r.venue_id = c.id AND r.is_approved = TRUE
         WHERE c.id = ? AND c.is_active = TRUE
         GROUP BY c.id"
    );
    $stmt->execute([$venueId]);
    $venue = $stmt->fetch();

    if (!$venue) {
        flashSet('error', 'Venue not found.');
        redirect(BASE_URL . '/courts.php');
    }

    // ─── Fetch individual courts ──────────────────────────────
    $courtStmt = $pdo->prepare(
        "SELECT * FROM individual_courts
         WHERE venue_id = ? AND is_active = TRUE
         ORDER BY court_number"
    );
    $courtStmt->execute([$venueId]);
    $individualCourts = $courtStmt->fetchAll();

    // ─── Fetch recent reviews ─────────────────────────────────
    $reviewStmt = $pdo->prepare(
        "SELECT r.rating, r.review_text, r.created_at, u.full_name
         FROM reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.venue_id = ? AND r.is_approved = TRUE
         ORDER BY r.created_at DESC
         LIMIT 5"
    );
    $reviewStmt->execute([$venueId]);
    $reviews = $reviewStmt->fetchAll();

    // ─── Check if user has favorited this venue ───────────────
    $favStmt = $pdo->prepare(
        "SELECT id FROM favorites WHERE user_id = ? AND venue_id = ?"
    );
    $favStmt->execute([currentUserId(), $venueId]);
    $isFavorited = (bool) $favStmt->fetch();

} catch (PDOException $e) {
    error_log('[BookMyCourt] court-details.php error: ' . $e->getMessage());
    flashSet('error', 'Failed to load venue details.');
    redirect(BASE_URL . '/courts.php');
}

$pageTitle       = $venue['hall_name'];
$pageDescription = substr($venue['description'] ?? $venue['hall_name'] . ' — Badminton venue in ' . $venue['location'], 0, 155);
$pageClass       = 'page-court-details';
$images          = getVenueImages($venueId);
$facilities      = array_filter(array_map('trim', explode(',', $venue['facilities'] ?? '')));

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/court-details.css">

<div class="container">

  <!-- ─── Breadcrumb ─────────────────────────────────────────── -->
  <div class="page-header-breadcrumb" style="margin-top: var(--sp-6);">
    <a href="<?php echo BASE_URL; ?>/courts.php"><i class="bi bi-grid"></i> Courts</a>
    <i class="bi bi-chevron-right" style="font-size:0.75rem;"></i>
    <span><?php echo e($venue['hall_name']); ?></span>
  </div>

  <!-- ─── Layout ─────────────────────────────────────────────── -->
  <div class="details-layout">

    <!-- ─── LEFT: Gallery + Info ─────────────────────────────── -->
    <div class="details-left">

      <!-- Gallery -->
      <div class="gallery-section animate-on-scroll">
        <div class="gallery-main" id="galleryMain">
          <img src="<?php echo e($images[0]); ?>" alt="<?php echo e($venue['hall_name']); ?>"
               id="galleryMainImg" loading="eager"
               onerror="this.src='<?php echo BASE_URL; ?>/assets/images/logo.png'">
        </div>
        <div class="gallery-thumbs">
          <?php foreach ($images as $i => $imgUrl): ?>
          <div class="gallery-thumb <?php echo $i === 0 ? 'active' : ''; ?>"
               onclick="switchGallery(<?php echo $i; ?>, '<?php echo e($imgUrl); ?>')"
               data-index="<?php echo $i; ?>">
            <img src="<?php echo e($imgUrl); ?>"
                 alt="View <?php echo $i + 1; ?>"
                 loading="lazy"
                 onerror="this.src='<?php echo BASE_URL; ?>/assets/images/logo.png'">
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Venue Info -->
      <div class="venue-detail-card card animate-on-scroll">
        <div class="card-body">

          <!-- Name + Rating -->
          <div class="flex flex-between items-center mb-4">
            <div>
              <h1 class="venue-detail-name"><?php echo e($venue['hall_name']); ?></h1>
              <p class="venue-detail-location">
                <i class="bi bi-geo-alt-fill"></i> <?php echo e($venue['location']); ?>
                <?php if ($venue['address']): ?>
                · <?php echo e($venue['address']); ?>
                <?php endif; ?>
              </p>
            </div>
            <div style="text-align:center;">
              <?php if ((float)$venue['avg_rating'] > 0): ?>
              <div class="rating-big"><?php echo number_format((float)$venue['avg_rating'], 1); ?></div>
              <div class="star-rating" style="justify-content:center;">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                <i class="bi bi-star<?php echo $s <= round((float)$venue['avg_rating']) ? '-fill' : ''; ?> star <?php echo $s > round((float)$venue['avg_rating']) ? 'empty' : ''; ?>"></i>
                <?php endfor; ?>
              </div>
              <div class="rating-count"><?php echo (int)$venue['review_count']; ?> reviews</div>
              <?php else: ?>
              <span style="font-size:0.8rem;color:var(--c-text-muted);">No reviews yet</span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Description -->
          <?php if ($venue['description']): ?>
          <p class="venue-description"><?php echo nl2br(e($venue['description'])); ?></p>
          <?php endif; ?>

          <!-- Info Grid -->
          <div class="info-grid">
            <div class="info-item">
              <i class="bi bi-grid-3x3-gap info-icon"></i>
              <div>
                <div class="info-label">Total Courts</div>
                <div class="info-value"><?php echo (int)$venue['num_courts']; ?> Courts</div>
              </div>
            </div>
            <div class="info-item">
              <i class="bi bi-currency-rupee info-icon"></i>
              <div>
                <div class="info-label">Price per Hour</div>
                <div class="info-value" style="color:var(--c-success-light);"><?php echo formatPrice((float)$venue['price_per_hour']); ?>/hr</div>
              </div>
            </div>
            <div class="info-item">
              <i class="bi bi-clock info-icon"></i>
              <div>
                <div class="info-label">Opening Hours</div>
                <div class="info-value">
                  <?php echo date('g:i A', strtotime($venue['opening_time'])); ?>
                  –
                  <?php echo date('g:i A', strtotime($venue['closing_time'])); ?>
                </div>
              </div>
            </div>
            <div class="info-item">
              <i class="bi bi-geo-alt info-icon"></i>
              <div>
                <div class="info-label">Area</div>
                <div class="info-value"><?php echo e($venue['location']); ?></div>
              </div>
            </div>
          </div>

          <!-- Facilities -->
          <div class="detail-section">
            <h3 class="detail-section-title"><i class="bi bi-check-all"></i> Facilities</h3>
            <div class="facility-tags">
              <?php foreach ($facilities as $f): ?>
              <span class="facility-tag"><i class="bi bi-check-circle-fill"></i><?php echo e($f); ?></span>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Rules -->
          <?php if ($venue['rules']): ?>
          <div class="detail-section">
            <h3 class="detail-section-title"><i class="bi bi-exclamation-circle"></i> Court Rules</h3>
            <p class="rules-text"><?php echo nl2br(e($venue['rules'])); ?></p>
          </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- Reviews -->
      <?php if (!empty($reviews)): ?>
      <div class="reviews-section card animate-on-scroll">
        <div class="card-body">
          <h3 class="detail-section-title" style="margin-bottom:var(--sp-5);">
            <i class="bi bi-chat-quote"></i> Player Reviews
          </h3>
          <div class="reviews-list">
            <?php foreach ($reviews as $review): ?>
            <div class="review-item">
              <div class="review-header">
                <div class="review-avatar">
                  <?php echo strtoupper(substr($review['full_name'], 0, 1)); ?>
                </div>
                <div>
                  <div class="review-author"><?php echo e($review['full_name']); ?></div>
                  <div class="review-date"><?php echo formatDate($review['created_at']); ?></div>
                </div>
                <div class="star-rating" style="margin-left:auto;">
                  <?php for ($s = 1; $s <= 5; $s++): ?>
                  <i class="bi bi-star<?php echo $s <= $review['rating'] ? '-fill' : ''; ?> star <?php echo $s > $review['rating'] ? 'empty' : ''; ?>"></i>
                  <?php endfor; ?>
                </div>
              </div>
              <?php if ($review['review_text']): ?>
              <p class="review-text"><?php echo e($review['review_text']); ?></p>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- ─── RIGHT: Booking Panel ──────────────────────────────── -->
    <div class="details-right">
      <div class="booking-panel card" style="position:sticky;top:80px;">
        <div class="card-body">

          <!-- Price -->
          <div class="booking-panel-price">
            <span class="bpp-currency">₹</span>
            <span class="bpp-amount"><?php echo number_format((float)$venue['price_per_hour'], 0); ?></span>
            <span class="bpp-unit">/hr per court</span>
          </div>

          <!-- Favorite -->
          <button id="favBtn" class="btn <?php echo $isFavorited ? 'btn-danger' : 'btn-outline'; ?> btn-full mb-4"
                  onclick="toggleVenueFavorite(<?php echo $venueId; ?>)">
            <i class="bi bi-heart<?php echo $isFavorited ? '-fill' : ''; ?>"></i>
            <?php echo $isFavorited ? 'Saved to Favorites' : 'Save to Favorites'; ?>
          </button>

          <div class="divider"></div>

          <!-- Quick Book -->
          <h4 style="margin-bottom:var(--sp-4);">Quick Book</h4>
          <a href="<?php echo BASE_URL; ?>/book.php?venue_id=<?php echo $venueId; ?>"
             class="btn btn-accent btn-full btn-lg">
            <i class="bi bi-calendar-plus"></i> Book a Court
          </a>

          <div class="divider"></div>

          <!-- Courts at this venue -->
          <h4 style="margin-bottom:var(--sp-4);">Available Courts</h4>
          <div class="panel-courts-list">
            <?php foreach ($individualCourts as $ic): ?>
            <div class="panel-court-item">
              <i class="bi bi-hexagon-fill" style="color:var(--c-accent);font-size:0.75rem;"></i>
              <span><?php echo e($ic['court_name']); ?></span>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="divider"></div>

          <!-- Opening hours -->
          <div class="panel-hours">
            <div class="panel-hours-title"><i class="bi bi-clock"></i> Today's Hours</div>
            <div class="panel-hours-time">
              <?php echo date('g:i A', strtotime($venue['opening_time'])); ?>
              –
              <?php echo date('g:i A', strtotime($venue['closing_time'])); ?>
            </div>
          </div>

        </div>
      </div>
    </div>

  </div><!-- /.details-layout -->
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
// Gallery image switcher
function switchGallery(index, url) {
    document.getElementById('galleryMainImg').src = url;
    document.querySelectorAll('.gallery-thumb').forEach((t, i) => {
        t.classList.toggle('active', i === index);
    });
}

// Favorite toggle
function toggleVenueFavorite(venueId) {
    const btn  = document.getElementById('favBtn');
    const icon = btn.querySelector('i');
    const isFav = icon.classList.contains('bi-heart-fill');

    btn.disabled = true;

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
            btn.classList.toggle('btn-outline', isFav);
            btn.classList.toggle('btn-danger', !isFav);
            btn.querySelector('i').nextSibling.textContent = isFav ? ' Save to Favorites' : ' Saved to Favorites';
            showToast(isFav ? 'Removed from favorites' : 'Added to favorites!', isFav ? 'info' : 'success');
        }
    })
    .catch(() => showToast('Could not update favorites', 'error'))
    .finally(() => { btn.disabled = false; });
}
</script>
