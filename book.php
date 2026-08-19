<?php
/**
 * BookMyCourt — Multi-Step Booking Page
 *
 * Step 1: Select court + date + time slot
 * Step 2: Booking summary
 * Step 3: Razorpay payment
 * Step 4: Confirmation
 *
 * Security:
 * - Auth required
 * - CSRF on form
 * - venue_id and individual_court_id validated against DB
 * - Price NEVER taken from frontend
 */

require_once __DIR__ . '/bootstrap.php';
requireLogin();

$venueId = filter_input(INPUT_GET, 'venue_id', FILTER_VALIDATE_INT);

if (!$venueId || $venueId <= 0) {
    flashSet('error', 'Please select a venue first.');
    redirect(BASE_URL . '/courts.php');
}

try {
    $pdo = db();

    $venueStmt = $pdo->prepare(
        "SELECT * FROM courts WHERE id = ? AND is_active = TRUE"
    );
    $venueStmt->execute([$venueId]);
    $venue = $venueStmt->fetch();

    if (!$venue) {
        flashSet('error', 'Venue not found.');
        redirect(BASE_URL . '/courts.php');
    }

    // Fetch individual courts for this venue
    $courtStmt = $pdo->prepare(
        "SELECT * FROM individual_courts WHERE venue_id = ? AND is_active = TRUE ORDER BY court_number"
    );
    $courtStmt->execute([$venueId]);
    $individualCourts = $courtStmt->fetchAll();

} catch (PDOException $e) {
    error_log('[BookMyCourt] book.php error: ' . $e->getMessage());
    flashSet('error', 'Failed to load venue.');
    redirect(BASE_URL . '/courts.php');
}

$facilities   = array_filter(array_map('trim', explode(',', $venue['facilities'] ?? '')));
$images       = getVenueImages($venueId);
$razorpayKeyId = env('RAZORPAY_KEY_ID', '');
$isTestMode    = (env('RAZORPAY_MODE', 'test') === 'test' || str_contains($razorpayKeyId, 'REPLACE'));

$pageTitle = 'Book — ' . $venue['hall_name'];
$pageClass = 'page-book';

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/court-details.css">

<?php if ($isTestMode): ?>
<div style="background: rgba(217,119,6,0.15); border-bottom: 1px solid rgba(217,119,6,0.3); padding: 10px; text-align: center; font-size: 0.85rem; color: var(--c-warning-light);">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <strong>TEST MODE:</strong> No real payments will be processed. Use Razorpay test card numbers.
  <a href="#" style="color: var(--c-warning-light); text-decoration: underline; margin-left: 8px;" onclick="showTestCards()">See test cards</a>
</div>
<?php endif; ?>

<div class="booking-page container">

  <!-- Breadcrumb -->
  <div class="page-header-breadcrumb mt-4">
    <a href="<?php echo BASE_URL; ?>/courts.php">Courts</a>
    <i class="bi bi-chevron-right" style="font-size:0.75rem;"></i>
    <a href="<?php echo BASE_URL; ?>/court-details.php?id=<?php echo $venueId; ?>"><?php echo e($venue['hall_name']); ?></a>
    <i class="bi bi-chevron-right" style="font-size:0.75rem;"></i>
    <span>Book</span>
  </div>

  <!-- Step Indicator -->
  <div class="booking-steps mb-8 mt-6">
    <div class="booking-step active" id="step1Indicator">
      <div class="step-circle">1</div>
      <div class="step-label">Select Slot</div>
    </div>
    <div class="booking-step" id="step2Indicator">
      <div class="step-circle">2</div>
      <div class="step-label">Summary</div>
    </div>
    <div class="booking-step" id="step3Indicator">
      <div class="step-circle">3</div>
      <div class="step-label">Payment</div>
    </div>
  </div>

  <!-- ─── Step 1: Select Slot ──────────────────────────────────── -->
  <div id="bookStep1">
    <div class="booking-two-col">

      <!-- Venue Info -->
      <div class="booking-venue-card card">
        <div class="card-img-wrapper">
          <img src="<?php echo e($images[0]); ?>" class="card-img"
               alt="<?php echo e($venue['hall_name']); ?>"
               onerror="this.src='<?php echo BASE_URL; ?>/assets/images/logo.png'">
        </div>
        <div class="card-body">
          <h2 class="card-title"><?php echo e($venue['hall_name']); ?></h2>
          <p class="card-subtitle"><i class="bi bi-geo-alt-fill"></i> <?php echo e($venue['location']); ?></p>
          <div class="booking-venue-price"><?php echo formatPrice((float)$venue['price_per_hour']); ?><span>/hr</span></div>
          <div class="facility-tags mt-4">
            <?php foreach (array_slice(array_values($facilities), 0, 3) as $f): ?>
            <span class="facility-tag"><i class="bi bi-check-circle-fill"></i><?php echo e($f); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Selection Form -->
      <div class="booking-selection card">
        <div class="card-body">
          <h3 style="margin-bottom:var(--sp-6);">Select Your Slot</h3>

          <!-- Date picker -->
          <div class="form-group">
            <label class="form-label">
              <i class="bi bi-calendar3" style="color:var(--c-accent-light);"></i> Select Date
            </label>
            <input type="date" id="bookingDate" class="form-control"
                   min="<?php echo date('Y-m-d'); ?>"
                   value="<?php echo date('Y-m-d'); ?>"
                   onchange="loadAvailability()">
          </div>

          <!-- Court Selection -->
          <div class="form-group">
            <label class="form-label">
              <i class="bi bi-grid-3x3-gap" style="color:var(--c-accent-light);"></i> Select Court
            </label>
            <div class="court-grid" id="courtGrid">
              <?php foreach ($individualCourts as $ic): ?>
              <div class="court-cell available"
                   data-id="<?php echo $ic['id']; ?>"
                   data-name="<?php echo e($ic['court_name']); ?>"
                   onclick="selectCourt(this)">
                <i class="bi bi-grid-3x3-gap court-cell-icon"></i>
                <span class="court-cell-label"><?php echo e($ic['court_name']); ?></span>
              </div>
              <?php endforeach; ?>
            </div>
            <div class="court-legend">
              <span class="legend-item"><span class="legend-dot available"></span> Available</span>
              <span class="legend-item"><span class="legend-dot booked"></span> Booked</span>
              <span class="legend-item"><span class="legend-dot selected"></span> Selected</span>
            </div>
          </div>

          <!-- Time Slot Selection -->
          <div class="form-group" id="slotSection">
            <label class="form-label">
              <i class="bi bi-clock" style="color:var(--c-accent-light);"></i> Select Time Slot
            </label>
            <div class="slot-grid" id="slotGrid">
              <p id="slotPlaceholder" class="slot-placeholder">
                <i class="bi bi-hand-index"></i> Select a court above to see available time slots
              </p>
            </div>
          </div>

          <!-- Loading indicator -->
          <div id="availabilityLoader" style="display:none; padding:var(--sp-8);" class="loader">
            <div class="spinner"></div>
            <p>Checking availability...</p>
          </div>

          <!-- Proceed button -->
          <button id="proceedBtn" class="btn btn-accent btn-full btn-lg mt-6"
                  onclick="proceedToSummary()" disabled>
            View Booking Summary <i class="bi bi-arrow-right"></i>
          </button>
        </div>
      </div>

    </div>
  </div>

  <!-- ─── Step 2: Summary ───────────────────────────────────────── -->
  <div id="bookStep2" style="display:none;">
    <div class="booking-two-col">

      <!-- Summary Card -->
      <div class="booking-summary">
        <div class="booking-summary-header">
          <i class="bi bi-receipt"></i> Booking Summary
        </div>
        <div class="booking-summary-row">
          <span class="label">Venue</span>
          <span class="value" id="sumVenue"><?php echo e($venue['hall_name']); ?></span>
        </div>
        <div class="booking-summary-row">
          <span class="label">Court</span>
          <span class="value" id="sumCourt">—</span>
        </div>
        <div class="booking-summary-row">
          <span class="label">Date</span>
          <span class="value" id="sumDate">—</span>
        </div>
        <div class="booking-summary-row">
          <span class="label">Time Slot</span>
          <span class="value" id="sumSlot">—</span>
        </div>
        <div class="booking-summary-row">
          <span class="label">Duration</span>
          <span class="value">1 Hour</span>
        </div>
        <div class="booking-summary-total">
          <span class="label">Total Amount</span>
          <span class="value" id="sumTotal"><?php echo formatPrice((float)$venue['price_per_hour']); ?></span>
        </div>
      </div>

      <!-- Payment Action -->
      <div class="card">
        <div class="card-body">
          <h3 style="margin-bottom:var(--sp-4);">Payment Details</h3>

          <?php if ($isTestMode): ?>
          <div class="alert alert-warning" style="margin-bottom:var(--sp-5);">
            <i class="bi bi-info-circle-fill"></i>
            <div>
              <strong>Test Mode Active</strong><br>
              <small>Use card: 4111 1111 1111 1111 · Expiry: any future date · CVV: any 3 digits</small>
            </div>
          </div>
          <?php endif; ?>

          <div class="payment-method-card">
            <i class="bi bi-credit-card-2-front" style="font-size:1.5rem;color:var(--c-accent-light);"></i>
            <div>
              <div style="font-weight:600;">Razorpay Secure Checkout</div>
              <div style="font-size:0.8rem;color:var(--c-text-muted);">UPI, Cards, Net Banking, Wallets</div>
            </div>
            <i class="bi bi-shield-lock-fill" style="color:var(--c-success-light);margin-left:auto;"></i>
          </div>

          <div class="mt-6">
            <button id="payBtn" class="btn btn-accent btn-full btn-xl" onclick="initiatePayment()">
              <i class="bi bi-lock-fill"></i>
              Pay <?php echo formatPrice((float)$venue['price_per_hour']); ?> Securely
            </button>
          </div>

          <div class="mt-4">
            <button class="btn btn-ghost btn-full" onclick="backToStep1()">
              <i class="bi bi-arrow-left"></i> Change Selection
            </button>
          </div>

          <p class="text-center text-xs mt-4" style="color:var(--c-text-muted);">
            <i class="bi bi-shield-check"></i>
            Payment secured by Razorpay. Booking confirmed only after server-side verification.
          </p>
        </div>
      </div>

    </div>
  </div>

  <!-- ─── Step 3: Confirmation ─────────────────────────────────── -->
  <div id="bookStep3" style="display:none;">
    <div class="confirmation-section animate-on-scroll">
      <div class="confirmation-icon">
        <i class="bi bi-check-circle-fill"></i>
      </div>
      <h2 class="confirmation-title">Booking Confirmed!</h2>
      <p class="confirmation-sub">Your court has been successfully booked and payment verified.</p>

      <div class="booking-summary" style="max-width:500px;margin:var(--sp-6) auto;">
        <div class="booking-summary-header"><i class="bi bi-ticket-perforated"></i> Booking Receipt</div>
        <div class="booking-summary-row">
          <span class="label">Booking ID</span>
          <span class="value font-bold" id="confBookingId">—</span>
        </div>
        <div class="booking-summary-row">
          <span class="label">Venue</span>
          <span class="value" id="confVenue"><?php echo e($venue['hall_name']); ?></span>
        </div>
        <div class="booking-summary-row">
          <span class="label">Court</span>
          <span class="value" id="confCourt">—</span>
        </div>
        <div class="booking-summary-row">
          <span class="label">Date</span>
          <span class="value" id="confDate">—</span>
        </div>
        <div class="booking-summary-row">
          <span class="label">Time</span>
          <span class="value" id="confSlot">—</span>
        </div>
        <div class="booking-summary-total">
          <span class="label">Paid</span>
          <span class="value" id="confAmount"><?php echo formatPrice((float)$venue['price_per_hour']); ?></span>
        </div>
      </div>

      <div class="flex-center gap-4 mt-6" style="flex-wrap:wrap;">
        <a href="<?php echo BASE_URL; ?>/my-bookings.php" class="btn btn-accent btn-lg">
          <i class="bi bi-calendar-check"></i> View My Bookings
        </a>
        <a href="<?php echo BASE_URL; ?>/courts.php" class="btn btn-outline btn-lg">
          <i class="bi bi-grid"></i> Browse More Courts
        </a>
      </div>
    </div>
  </div>

</div><!-- /.booking-page -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Razorpay SDK (only loaded when key is configured) -->
<?php if (!str_contains($razorpayKeyId, 'REPLACE')): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<?php endif; ?>

<script>
// ─── State ─────────────────────────────────────────────────────
const VENUE_ID   = <?php echo $venueId; ?>;
const PRICE      = <?php echo (float)$venue['price_per_hour']; ?>;
const BASE_URL   = '<?php echo BASE_URL; ?>';
const CSRF_TOKEN = '<?php echo csrfToken(); ?>';
const TEST_MODE  = <?php echo $isTestMode ? 'true' : 'false'; ?>;
const RZP_KEY    = '<?php echo e($razorpayKeyId); ?>';

let selectedCourtId   = null;
let selectedCourtName = null;
let selectedSlot      = null;
let currentBookingId  = null;

// ─── Step 1: Court selection ────────────────────────────────────
function selectCourt(cell) {
    if (cell.classList.contains('booked')) return;

    document.querySelectorAll('.court-cell').forEach(c => c.classList.remove('selected'));
    cell.classList.add('selected');
    selectedCourtId   = cell.dataset.id;
    selectedCourtName = cell.dataset.name;
    selectedSlot      = null;

    loadAvailability();
}

// ─── Load availability from API ─────────────────────────────────
async function loadAvailability() {
    if (!selectedCourtId) return;

    const date = document.getElementById('bookingDate').value;
    if (!date) return;

    const loader   = document.getElementById('availabilityLoader');
    const slotGrid = document.getElementById('slotGrid');

    loader.style.display = 'flex';
    slotGrid.innerHTML = '';
    selectedSlot = null;
    updateProceedBtn();

    try {
        // Update court availability dots in background
        fetch(`${BASE_URL}/api/availability.php?venue_id=${VENUE_ID}&date=${date}`)
            .then(r => r.json())
            .then(data => {
                if (data && data.courts) {
                    data.courts.forEach(court => {
                        const cell = document.querySelector(`.court-cell[data-id="${court.id}"]`);
                        if (cell && !cell.classList.contains('selected')) {
                            cell.classList.toggle('available', !court.is_fully_booked);
                            cell.classList.toggle('booked', court.is_fully_booked);
                        }
                    });
                }
            })
            .catch(err => console.warn('Court availability status warning:', err));

        // Load time slots for selected court
        const res = await fetch(`${BASE_URL}/api/availability.php?venue_id=${VENUE_ID}&date=${date}&individual_court_id=${selectedCourtId}`);
        const data = await res.json().catch(() => null);

        loader.style.display = 'none';

        if (!res.ok || !data || data.error) {
            const msg = data?.message || `Failed to load slots (Status ${res.status})`;
            console.error('Availability API error:', msg);
            slotGrid.innerHTML = `<p class="slot-placeholder" style="color:var(--c-danger-light);"><i class="bi bi-exclamation-circle"></i> ${msg}</p>`;
            showToast(msg, 'error');
            return;
        }

        if (!data.slots || data.slots.length === 0) {
            slotGrid.innerHTML = '<p class="slot-placeholder"><i class="bi bi-calendar-x"></i> No time slots available for this date.</p>';
            return;
        }

        slotGrid.innerHTML = data.slots.map(slot => `
            <div class="slot-cell ${slot.is_booked ? 'booked' : 'available'}"
                 data-slot="${slot.slot.replace(/"/g, '&quot;')}"
                 ${slot.is_booked ? '' : `onclick="selectSlot(this, '${slot.slot.replace(/'/g, "\\\'")}')"`}>
                ${slot.slot}
                ${slot.is_booked ? '<div style="font-size:0.65rem;margin-top:2px;">Booked</div>' : ''}
            </div>
        `).join('');

    } catch (err) {
        console.error('Slot fetch error:', err);
        loader.style.display = 'none';
        slotGrid.innerHTML = '<p class="slot-placeholder" style="color:var(--c-danger-light);"><i class="bi bi-wifi-off"></i> Failed to load availability. Please try again.</p>';
        showToast('Failed to load availability. Please try again.', 'error');
    }
}

function selectSlot(cell, slot) {
    document.querySelectorAll('.slot-cell').forEach(c => c.classList.remove('selected'));
    cell.classList.add('selected');
    selectedSlot = slot;
    updateProceedBtn();
}

function updateProceedBtn() {
    const btn = document.getElementById('proceedBtn');
    btn.disabled = !(selectedCourtId && selectedSlot);
}

// ─── Step 2: Summary ────────────────────────────────────────────
function proceedToSummary() {
    if (!selectedCourtId || !selectedSlot) return;

    const date = document.getElementById('bookingDate').value;
    const dateFormatted = new Date(date + 'T00:00:00').toLocaleDateString('en-IN', {
        day: 'numeric', month: 'long', year: 'numeric', weekday: 'long'
    });

    document.getElementById('sumCourt').textContent = selectedCourtName;
    document.getElementById('sumDate').textContent  = dateFormatted;
    document.getElementById('sumSlot').textContent  = selectedSlot;

    document.getElementById('bookStep1').style.display = 'none';
    document.getElementById('bookStep2').style.display = 'block';

    setStepState(2);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function backToStep1() {
    document.getElementById('bookStep2').style.display = 'none';
    document.getElementById('bookStep1').style.display = 'block';
    setStepState(1);
}

function setStepState(step) {
    [1, 2, 3].forEach(s => {
        const el = document.getElementById(`step${s}Indicator`);
        el.classList.remove('active', 'done');
        if (s < step)  el.classList.add('done');
        if (s === step) el.classList.add('active');
    });
}

// ─── Step 3: Payment ─────────────────────────────────────────────
async function initiatePayment() {
    const btn  = document.getElementById('payBtn');
    btn.disabled = true;
    btn.classList.add('btn-loading');
    btn.textContent = 'Creating booking...';

    const date = document.getElementById('bookingDate').value;

    try {
        const resp = await fetch(`${BASE_URL}/api/booking.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                csrf_token:           CSRF_TOKEN,
                venue_id:             VENUE_ID,
                individual_court_id:  selectedCourtId,
                booking_date:         date,
                time_slot:            selectedSlot,
            })
        });

        const data = await resp.json();

        if (data.error) {
            showToast(data.message, 'error');
            btn.disabled = false;
            btn.classList.remove('btn-loading');
            btn.innerHTML = '<i class="bi bi-lock-fill"></i> Pay ₹' + PRICE + ' Securely';

            if (data.code === 'SLOT_TAKEN') {
                loadAvailability(); // Refresh availability
            }
            return;
        }

        currentBookingId = data.booking_id;

        if (data.test_mode || !window.Razorpay) {
            // Test mode: simulate payment
            await simulateTestPayment(data);
        } else {
            // Real Razorpay checkout
            openRazorpay(data);
        }

    } catch (err) {
        console.error(err);
        showToast('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.classList.remove('btn-loading');
        btn.innerHTML = '<i class="bi bi-lock-fill"></i> Pay ₹' + PRICE + ' Securely';
    }
}

function openRazorpay(orderData) {
    const options = {
        key:         RZP_KEY,
        amount:      orderData.amount,
        currency:    orderData.currency,
        name:        'BookMyCourt',
        description: `${selectedCourtName} · ${selectedSlot}`,
        order_id:    orderData.order_id,
        image:       `${BASE_URL}/assets/images/logo.png`,
        theme:       { color: '#2563eb' },
        handler: async function(response) {
            await verifyPayment(
                orderData.booking_id,
                response.razorpay_order_id,
                response.razorpay_payment_id,
                response.razorpay_signature
            );
        },
        modal: {
            ondismiss: function() {
                showToast('Payment cancelled. Your booking is on hold for a few minutes.', 'warning');
                const btn = document.getElementById('payBtn');
                btn.disabled = false;
                btn.classList.remove('btn-loading');
                btn.innerHTML = '<i class="bi bi-lock-fill"></i> Pay ₹' + PRICE + ' Securely';
            }
        },
        prefill: {
            name:  '<?php echo e($_SESSION['user_name']); ?>',
            email: '<?php echo e($_SESSION['user_email'] ?? ''); ?>',
            contact: '<?php echo e($_SESSION['user_phone'] ?? ''); ?>',
        }
    };

    const rzp = new Razorpay(options);
    rzp.open();
}

async function simulateTestPayment(orderData) {
    // TEST MODE: Simulate a successful payment with fake IDs
    showToast('Test mode: Simulating payment verification...', 'info');
    await new Promise(r => setTimeout(r, 1500));

    await verifyPayment(
        orderData.booking_id,
        orderData.order_id,
        'pay_TEST_' + Math.random().toString(36).substr(2, 14).toUpperCase(),
        'sig_TEST_' + Math.random().toString(36).substr(2, 32)
    );
}

async function verifyPayment(bookingId, orderId, paymentId, signature) {
    try {
        const resp = await fetch(`${BASE_URL}/api/payment-verify.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                csrf_token:           CSRF_TOKEN,
                booking_id:           bookingId,
                razorpay_order_id:    orderId,
                razorpay_payment_id:  paymentId,
                razorpay_signature:   signature,
            })
        });

        const data = await resp.json();

        if (data.error) {
            showToast(data.message, 'error');
            return;
        }

        // ─ Show confirmation ─
        const date = document.getElementById('bookingDate').value;
        const dateFormatted = new Date(date + 'T00:00:00').toLocaleDateString('en-IN', {
            day: 'numeric', month: 'long', year: 'numeric'
        });

        document.getElementById('confBookingId').textContent = data.booking_ref;
        document.getElementById('confCourt').textContent     = selectedCourtName;
        document.getElementById('confDate').textContent      = dateFormatted;
        document.getElementById('confSlot').textContent      = selectedSlot;

        document.getElementById('bookStep2').style.display = 'none';
        document.getElementById('bookStep3').style.display = 'block';
        setStepState(3);

        showToast('Booking confirmed! 🎉', 'success', 6000);
        window.scrollTo({ top: 0, behavior: 'smooth' });

    } catch (err) {
        console.error(err);
        showToast('Verification failed. Please contact support.', 'error');
    }
}

// ─── Test cards modal ────────────────────────────────────────────
function showTestCards() {
    const html = `
        <div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:16px;padding:24px;max-width:400px;margin:100px auto;position:relative;">
            <h3 style="margin-bottom:16px;">Razorpay Test Cards</h3>
            <table style="width:100%;font-size:0.875rem;border-collapse:collapse;">
                <tr><th style="text-align:left;color:var(--c-text-muted);padding:6px 0;border-bottom:1px solid var(--c-border);">Card No</th><th style="text-align:left;color:var(--c-text-muted);padding:6px 8px;border-bottom:1px solid var(--c-border);">Type</th></tr>
                <tr><td style="padding:6px 0;font-family:monospace;">4111 1111 1111 1111</td><td style="padding:6px 8px;">Visa (Success)</td></tr>
                <tr><td style="padding:6px 0;font-family:monospace;">5267 3181 8797 5449</td><td style="padding:6px 8px;">Mastercard</td></tr>
                <tr><td style="padding:6px 0;font-family:monospace;">4111 1111 1111 1111</td><td style="padding:6px 8px;">Fail on payment</td></tr>
            </table>
            <p style="font-size:0.8rem;color:var(--c-text-muted);margin-top:12px;">Expiry: any future date · CVV: any 3 digits</p>
            <button onclick="this.closest('[style*=fixed]').remove()" style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--c-text-muted);cursor:pointer;font-size:1.2rem;">×</button>
        </div>
    `;
    const modal = document.createElement('div');
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;overflow-y:auto;';
    modal.innerHTML = html;
    modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
    document.body.appendChild(modal);
}

// Load initial availability (for today)
document.addEventListener('DOMContentLoaded', function() {
    // Ensure date picker has today's local date as default
    const dateInput = document.getElementById('bookingDate');
    const localToday = new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD in local time
    if (dateInput) {
        if (!dateInput.value || dateInput.value < localToday) {
            dateInput.value = localToday;
        }
        dateInput.min = localToday;
    }

    // Auto-select first available court on page load
    const firstAvailableCourt = document.querySelector('.court-cell.available') || document.querySelector('.court-cell');
    if (firstAvailableCourt) {
        selectCourt(firstAvailableCourt);
    }
});
</script>

<style>
.booking-two-col {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: var(--sp-6);
  align-items: start;
}
.booking-venue-card { position: sticky; top: 80px; }
.booking-venue-price {
  font-size: 2rem;
  font-weight: 800;
  color: var(--c-success-light);
  margin: var(--sp-3) 0 0;
}
.booking-venue-price span { font-size: 1rem; color: var(--c-text-muted); font-weight: 500; }

.payment-method-card {
  display: flex;
  align-items: center;
  gap: var(--sp-4);
  background: var(--c-surface-2);
  border: 1px solid var(--c-border);
  border-radius: var(--r-lg);
  padding: var(--sp-4) var(--sp-5);
}

.court-legend {
  display: flex;
  gap: var(--sp-5);
  margin-top: var(--sp-3);
  font-size: 0.75rem;
  color: var(--c-text-muted);
}
.legend-item { display: flex; align-items: center; gap: 5px; }
.legend-dot {
  width: 12px; height: 12px;
  border-radius: 4px;
  border: 2px solid;
}
.legend-dot.available { background: var(--c-success-bg); border-color: rgba(22,163,74,0.4); }
.legend-dot.booked    { background: var(--c-surface); border-color: var(--c-border); opacity: 0.4; }
.legend-dot.selected  { background: rgba(37,99,235,0.2); border-color: var(--c-accent); }

.confirmation-section { text-align: center; padding: var(--sp-12) 0; }
.confirmation-icon {
  font-size: 5rem;
  color: var(--c-success-light);
  margin-bottom: var(--sp-5);
  animation: fadeInUp 0.5s ease;
}
.confirmation-title { font-size: 2rem; font-weight: 800; margin-bottom: var(--sp-3); }
.confirmation-sub   { font-size: 1rem; color: var(--c-text-muted); margin-bottom: var(--sp-6); }

/* ─── Date picker icon fix for dark mode ─────────────────────── */
input[type="date"].form-control {
  color-scheme: dark;
  position: relative;
}
input[type="date"]::-webkit-calendar-picker-indicator {
  filter: invert(0.7) sepia(0.2) saturate(3) hue-rotate(180deg);
  cursor: pointer;
  opacity: 0.8;
  transition: opacity 0.2s ease;
}
input[type="date"]::-webkit-calendar-picker-indicator:hover {
  opacity: 1;
}

/* ─── Slot placeholder message ───────────────────────────────── */
.slot-placeholder {
  grid-column: 1 / -1;
  text-align: center;
  color: var(--c-text-muted);
  font-size: 0.875rem;
  padding: var(--sp-6) var(--sp-4);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--sp-2);
  margin: 0;
}
.slot-placeholder i {
  font-size: 1rem;
  color: var(--c-accent-light);
}

@media (max-width: 768px) {
  .booking-two-col { grid-template-columns: 1fr; }
  .booking-venue-card { position: static; }
}
</style>
