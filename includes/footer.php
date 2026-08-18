<?php
/**
 * BookMyCourt — Shared HTML Footer
 *
 * Include at the bottom of every user-facing page.
 * Also outputs shared JS (nav interactions, animations, toast system).
 */
?>

<!-- ─── Footer ──────────────────────────────────────────────── -->
<footer class="site-footer">
  <div class="footer-container">

    <div class="footer-grid">

      <!-- Brand Column -->
      <div class="footer-brand-col">
        <div class="footer-brand">
          <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="BookMyCourt" class="footer-logo">
          <span class="footer-brand-name">BookMyCourt</span>
        </div>
        <p class="footer-tagline">
          Book verified badminton courts across Pune with real-time slot availability. No hassle, just play.
        </p>
        <div class="footer-social">
          <a href="#" class="social-link" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
          <a href="#" class="social-link" aria-label="Twitter/X"><i class="bi bi-twitter-x"></i></a>
          <a href="#" class="social-link" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="footer-col">
        <h4 class="footer-heading">Quick Links</h4>
        <ul class="footer-links">
          <li><a href="<?php echo BASE_URL; ?>/index.php">Home</a></li>
          <li><a href="<?php echo BASE_URL; ?>/courts.php">Browse Courts</a></li>
          <?php if (!empty($_SESSION['user_id'])): ?>
          <li><a href="<?php echo BASE_URL; ?>/my-bookings.php">My Bookings</a></li>
          <li><a href="<?php echo BASE_URL; ?>/profile.php">Profile</a></li>
          <?php else: ?>
          <li><a href="<?php echo BASE_URL; ?>/login.php">Login</a></li>
          <li><a href="<?php echo BASE_URL; ?>/login.php?tab=signup">Register</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Venues -->
      <div class="footer-col">
        <h4 class="footer-heading">Popular Venues</h4>
        <ul class="footer-links">
          <li><a href="<?php echo BASE_URL; ?>/court-details.php?id=1">PDMBA Sports Complex</a></li>
          <li><a href="<?php echo BASE_URL; ?>/court-details.php?id=3">Gravity Badminton Complex</a></li>
          <li><a href="<?php echo BASE_URL; ?>/court-details.php?id=5">Infinity Badminton Arena</a></li>
          <li><a href="<?php echo BASE_URL; ?>/court-details.php?id=9">Supernova Arena</a></li>
        </ul>
      </div>

      <!-- Contact -->
      <div class="footer-col">
        <h4 class="footer-heading">Contact</h4>
        <ul class="footer-links footer-contact">
          <li><i class="bi bi-geo-alt"></i> Pune, Maharashtra, India</li>
          <li><i class="bi bi-envelope"></i> support@bookmycourt.in</li>
          <li><i class="bi bi-telephone"></i> +91 98765 43210</li>
        </ul>
      </div>

    </div><!-- /.footer-grid -->

    <div class="footer-bottom">
      <p>&copy; <?php echo date('Y'); ?> BookMyCourt. Built with ♥ for Pune's badminton community.</p>
      <p class="footer-disclaimer">
        Prices and availability are subject to change. Please verify at the venue.
      </p>
    </div>

  </div>
</footer>

<!-- ─── Toast Notification System ───────────────────────────── -->
<div id="toastContainer" class="toast-container" aria-live="polite"></div>

<!-- ─── Shared JavaScript ────────────────────────────────────── -->
<script>
// ─ Navigation ─────────────────────────────────────────────────
function toggleMobileNav() {
    const nav = document.getElementById('navLinks');
    const hamburger = document.getElementById('navHamburger');
    nav.classList.toggle('open');
    hamburger.classList.toggle('open');
}

function toggleDropdown() {
    const menu = document.getElementById('dropdownMenu');
    menu.classList.toggle('open');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('userDropdown');
    if (dropdown && !dropdown.contains(e.target)) {
        const menu = document.getElementById('dropdownMenu');
        if (menu) menu.classList.remove('open');
    }
});

// ─ Navbar scroll effect ────────────────────────────────────────
window.addEventListener('scroll', function() {
    const nav = document.getElementById('siteNav');
    if (nav) {
        nav.classList.toggle('scrolled', window.scrollY > 20);
    }
});

// ─ Toast System ───────────────────────────────────────────────
function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    const icons = { success: 'bi-check-circle-fill', error: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill', warning: 'bi-exclamation-circle-fill' };
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<i class="bi ${icons[type] || icons.info}"></i><span>${message}</span><button onclick="this.parentElement.remove()" class="toast-close"><i class="bi bi-x"></i></button>`;
    container.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => toast.classList.add('show'));

    // Auto remove
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ─ Flash auto-dismiss ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const flashContainer = document.getElementById('flashContainer');
    if (flashContainer) {
        setTimeout(() => {
            flashContainer.style.opacity = '0';
            flashContainer.style.transition = 'opacity 0.4s';
            setTimeout(() => flashContainer.remove(), 400);
        }, 5000);
    }

    // Animate elements on scroll (Intersection Observer)
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.animate-on-scroll').forEach(el => observer.observe(el));
    }

    // Respect prefers-reduced-motion
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.documentElement.classList.add('reduced-motion');
    }
});
</script>
</body>
</html>
