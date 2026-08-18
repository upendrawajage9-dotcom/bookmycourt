<?php
/**
 * BookMyCourt — Login & Registration
 *
 * Security:
 * - Passwords hashed with password_hash(PASSWORD_BCRYPT)
 * - CSRF token on both forms
 * - Parameterized queries (PDO) — no SQL injection possible
 * - Session regeneration after login
 * - Input validation server-side
 * - No display of raw errors
 */

require_once __DIR__ . '/bootstrap.php';

// Redirect already-logged-in users
requireGuest();

$errors  = [];
$success = '';
$activeTab = 'login';

// Pre-fill tab from query string (e.g. login.php?tab=signup)
if (isset($_GET['tab']) && $_GET['tab'] === 'signup') {
    $activeTab = 'signup';
}

// Admin message from redirect
$adminMsg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'admin_required') {
    $adminMsg = 'Admin access required. Please log in with admin credentials.';
}

/* ─────────────────────────────────────────────────────────────
   POST: Login
   ───────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    csrfVerify();
    $action = $_POST['action'];

    /* ── LOGIN ── */
    if ($action === 'login') {
        $activeTab  = 'login';
        $credential = sanitize($_POST['username'] ?? '');
        $password   = $_POST['password'] ?? '';

        if (empty($credential)) {
            $errors['username'] = 'Please enter your email or phone number.';
        }
        if (empty($password)) {
            $errors['password'] = 'Please enter your password.';
        }

        if (empty($errors)) {
            $pdo = db();

            // ─ Check admin first ─────────────────────────────────
            $stmt = $pdo->prepare(
                "SELECT id, name, email, password_hash, is_active FROM admins WHERE email = ?"
            );
            $stmt->execute([$credential]);
            $admin = $stmt->fetch();

            if ($admin && $admin['is_active'] && password_verify($password, $admin['password_hash'])) {
                // Admin login success
                session_regenerate_id(true);
                $_SESSION['admin_id']   = (int) $admin['id'];
                $_SESSION['admin']      = true;
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email']= $admin['email'];

                header('Location: ' . BASE_URL . '/admin/dashboard.php');
                exit();
            }

            // ─ Check user ─────────────────────────────────────────
            $stmt = $pdo->prepare(
                "SELECT id, full_name, email, phone, password_hash, is_active
                 FROM users
                 WHERE (email = ? OR phone = ?)"
            );
            $stmt->execute([$credential, $credential]);
            $user = $stmt->fetch();

            if ($user) {
                if (!$user['is_active']) {
                    $errors['general'] = 'Your account has been deactivated. Please contact support.';
                } elseif (!password_verify($password, $user['password_hash'])) {
                    $errors['general'] = 'Incorrect email/phone or password.';
                } else {
                    // User login success
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = (int) $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_email']= $user['email'];
                    $_SESSION['user_phone']= $user['phone'];

                    // Redirect to originally intended page, or courts
                    $redirectTo = $_SESSION['redirect_after_login'] ?? (BASE_URL . '/courts.php');
                    unset($_SESSION['redirect_after_login']);

                    header('Location: ' . $redirectTo);
                    exit();
                }
            } else {
                $errors['general'] = 'No account found with that email or phone number.';
            }
        }

    /* ── SIGNUP ── */
    } elseif ($action === 'signup') {
        $activeTab = 'signup';

        $fullName = sanitize($_POST['fullName'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $phone    = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirmPassword'] ?? '';

        // Validation
        if (empty($fullName) || strlen($fullName) < 2) {
            $errors['fullName'] = 'Please enter your full name (at least 2 characters).';
        }
        if (!empty($email) && !isValidEmail($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if (!isValidPhone($phone)) {
            $errors['phone'] = 'Please enter a valid 10-digit Indian phone number.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors['confirmPassword'] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $pdo = db();

            // Check for duplicate email/phone
            $check = $pdo->prepare(
                "SELECT id FROM users WHERE phone = ? OR (email = ? AND email != '')"
            );
            $check->execute([$phone, $email]);

            if ($check->fetch()) {
                $errors['general'] = 'An account with this phone or email already exists. Please log in instead.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                $insert = $pdo->prepare(
                    "INSERT INTO users (full_name, email, phone, password_hash) VALUES (?, ?, ?, ?)"
                );
                $insert->execute([
                    $fullName,
                    $email ?: null,
                    $phone,
                    $hash,
                ]);

                $success   = 'Account created successfully! You can now log in.';
                $activeTab = 'login';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — BookMyCourt</title>
<meta name="description" content="Login or create your BookMyCourt account to book badminton courts across Pune.">
<meta name="robots" content="noindex"><!-- Auth pages should not be indexed -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/base.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
<style>
/* ─── Auth Page Layout ─────────────────────────────────────── */
body { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background:
    radial-gradient(ellipse at 20% 30%, rgba(37,99,235,0.15) 0%, transparent 50%),
    radial-gradient(ellipse at 80% 70%, rgba(22,163,74,0.08) 0%, transparent 50%),
    var(--c-bg);
  z-index: -1;
}

/* Hide the nav spacer on auth pages */
.nav-spacer { display: none; }

.auth-wrapper {
  width: 100%;
  max-width: 960px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  background: var(--c-surface);
  border: 1px solid var(--c-border);
  border-radius: var(--r-xl);
  overflow: hidden;
  box-shadow: 0 25px 80px rgba(0,0,0,0.7);
  animation: fadeInUp 0.5s ease;
}

/* Left Panel */
.auth-panel-left {
  background:
    linear-gradient(135deg, rgba(37,99,235,0.3) 0%, rgba(22,163,74,0.1) 100%),
    var(--c-surface-2);
  border-right: 1px solid var(--c-border);
  padding: 48px 40px;
  display: flex;
  flex-direction: column;
}
.auth-logo {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 40px;
}
.auth-logo img { width: 44px; height: 44px; object-fit: contain; border-radius: 8px; }
.auth-logo-text {
  font-size: 1.125rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  background: linear-gradient(135deg, var(--c-accent-light), #60a5fa);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.auth-hero-headline {
  font-size: clamp(1.5rem, 2.5vw, 2rem);
  font-weight: 800;
  color: var(--c-text-bright);
  margin-bottom: 12px;
  line-height: 1.2;
}
.auth-hero-tagline {
  font-size: 0.9rem;
  color: var(--c-text-muted);
  line-height: 1.7;
  margin-bottom: 36px;
}

.auth-feature-list { display: flex; flex-direction: column; gap: 16px; }
.auth-feature {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  font-size: 0.875rem;
  color: var(--c-text-dim);
  line-height: 1.5;
}
.feature-icon {
  width: 28px; height: 28px;
  border-radius: 8px;
  background: rgba(37,99,235,0.15);
  border: 1px solid rgba(37,99,235,0.25);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.875rem;
  color: var(--c-accent-light);
  flex-shrink: 0;
  margin-top: 1px;
}

.auth-stats {
  display: flex;
  gap: 24px;
  margin-top: auto;
  padding-top: 36px;
  border-top: 1px solid var(--c-border);
}
.auth-stat-value {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--c-text-bright);
}
.auth-stat-label { font-size: 0.75rem; color: var(--c-text-muted); margin-top: 2px; }

/* Right Panel */
.auth-panel-right {
  padding: 48px 44px;
  display: flex;
  flex-direction: column;
}

/* Tab toggle */
.auth-tabs {
  display: flex;
  background: var(--c-surface-2);
  border-radius: var(--r-full);
  padding: 4px;
  margin-bottom: 36px;
  border: 1px solid var(--c-border);
}
.auth-tab-btn {
  flex: 1;
  padding: 10px 0;
  border: none;
  background: transparent;
  border-radius: var(--r-full);
  font-family: var(--font-main);
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--c-text-muted);
  cursor: pointer;
  transition: all var(--t-fast);
}
.auth-tab-btn.active {
  background: var(--c-accent);
  color: white;
  box-shadow: 0 4px 16px var(--c-accent-glow);
}

.auth-form-title { font-size: 1.375rem; font-weight: 700; margin-bottom: 6px; }
.auth-form-subtitle { font-size: 0.875rem; color: var(--c-text-muted); margin-bottom: 28px; }

.auth-form { display: none; }
.auth-form.active { display: block; animation: fadeInUp 0.3s ease; }

.pw-strength {
  display: flex;
  gap: 4px;
  margin-top: 6px;
}
.pw-bar {
  flex: 1;
  height: 3px;
  border-radius: var(--r-full);
  background: var(--c-border);
  transition: background var(--t-fast);
}
.pw-bar.weak   { background: var(--c-danger-light); }
.pw-bar.medium { background: var(--c-warning-light); }
.pw-bar.strong { background: var(--c-success-light); }
.pw-label { font-size: 0.75rem; color: var(--c-text-muted); margin-top: 4px; }

@media (max-width: 768px) {
  .auth-wrapper { grid-template-columns: 1fr; max-width: 480px; }
  .auth-panel-left { display: none; }
  .auth-panel-right { padding: 36px 28px; }
}
</style>
</head>
<body>

<div class="auth-wrapper">

  <!-- Left Panel -->
  <div class="auth-panel-left">
    <div class="auth-logo">
      <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="BookMyCourt">
      <span class="auth-logo-text">BookMyCourt</span>
    </div>

    <h1 class="auth-hero-headline">Find your court.<br>Own your game.</h1>
    <p class="auth-hero-tagline">
      Book verified badminton courts across Pune with real-time slot availability — no hassle, just play.
    </p>

    <div class="auth-feature-list">
      <div class="auth-feature">
        <div class="feature-icon"><i class="bi bi-lightning-fill"></i></div>
        Instant booking with live slot availability — no phone calls needed.
      </div>
      <div class="auth-feature">
        <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
        Clash-free scheduling with database-level booking protection.
      </div>
      <div class="auth-feature">
        <div class="feature-icon"><i class="bi bi-calendar-check"></i></div>
        Track all your upcoming and past bookings from one account.
      </div>
      <div class="auth-feature">
        <div class="feature-icon"><i class="bi bi-geo-alt-fill"></i></div>
        10 verified badminton venues across Pune, all on one platform.
      </div>
    </div>

    <div class="auth-stats">
      <div>
        <div class="auth-stat-value">10+</div>
        <div class="auth-stat-label">Venues</div>
      </div>
      <div>
        <div class="auth-stat-value">45+</div>
        <div class="auth-stat-label">Courts</div>
      </div>
      <div>
        <div class="auth-stat-value">Pune</div>
        <div class="auth-stat-label">Coverage</div>
      </div>
    </div>
  </div>

  <!-- Right Panel -->
  <div class="auth-panel-right">

    <!-- Tab Toggle -->
    <div class="auth-tabs" role="tablist">
      <button id="loginTabBtn"  class="auth-tab-btn <?php echo $activeTab === 'login'  ? 'active' : ''; ?>"
              onclick="switchTab('login')"  role="tab" aria-selected="<?php echo $activeTab === 'login' ? 'true' : 'false'; ?>">
        Login
      </button>
      <button id="signupTabBtn" class="auth-tab-btn <?php echo $activeTab === 'signup' ? 'active' : ''; ?>"
              onclick="switchTab('signup')" role="tab" aria-selected="<?php echo $activeTab === 'signup' ? 'true' : 'false'; ?>">
        Create Account
      </button>
    </div>

    <?php if ($adminMsg): ?>
    <div class="alert alert-warning mb-4">
      <i class="bi bi-shield-exclamation"></i>
      <?php echo e($adminMsg); ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">
      <i class="bi bi-check-circle-fill"></i>
      <?php echo e($success); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors['general'])): ?>
    <div class="alert alert-error">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <?php echo e($errors['general']); ?>
    </div>
    <?php endif; ?>

    <!-- ── LOGIN FORM ── -->
    <div id="loginForm" class="auth-form <?php echo $activeTab === 'login' ? 'active' : ''; ?>">
      <h2 class="auth-form-title">Welcome back</h2>
      <p class="auth-form-subtitle">Log in to manage your badminton bookings.</p>

      <form method="POST" id="loginFormEl" novalidate>
        <?php csrfField(); ?>
        <input type="hidden" name="action" value="login">

        <div class="form-group">
          <label class="form-label" for="loginUsername">Email or Phone Number</label>
          <div class="input-icon-wrapper">
            <i class="bi bi-person"></i>
            <input type="text" id="loginUsername" name="username" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>"
                   placeholder="Enter email or phone" autocomplete="username" required>
          </div>
          <?php if (!empty($errors['username'])): ?>
          <div class="form-error"><i class="bi bi-exclamation-circle"></i> <?php echo e($errors['username']); ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="loginPassword">Password</label>
          <div class="input-icon-wrapper">
            <i class="bi bi-lock"></i>
            <input type="password" id="loginPassword" name="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                   placeholder="Enter your password" autocomplete="current-password" required>
          </div>
          <?php if (!empty($errors['password'])): ?>
          <div class="form-error"><i class="bi bi-exclamation-circle"></i> <?php echo e($errors['password']); ?></div>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-accent btn-full btn-lg" style="margin-top: 8px;">
          <i class="bi bi-box-arrow-in-right"></i> Login
        </button>

        <p class="text-center text-sm mt-4" style="color: var(--c-text-muted);">
          New here?
          <a href="#" onclick="switchTab('signup'); return false;" style="color: var(--c-accent-light);">Create a free account</a>
        </p>
      </form>
    </div>

    <!-- ── SIGNUP FORM ── -->
    <div id="signupForm" class="auth-form <?php echo $activeTab === 'signup' ? 'active' : ''; ?>">
      <h2 class="auth-form-title">Create your account</h2>
      <p class="auth-form-subtitle">Join BookMyCourt and start booking courts in seconds.</p>

      <form method="POST" id="signupFormEl" novalidate>
        <?php csrfField(); ?>
        <input type="hidden" name="action" value="signup">

        <div class="form-group">
          <label class="form-label" for="fullName">Full Name</label>
          <div class="input-icon-wrapper">
            <i class="bi bi-person-circle"></i>
            <input type="text" id="fullName" name="fullName" class="form-control <?php echo isset($errors['fullName']) ? 'is-invalid' : ''; ?>"
                   placeholder="Your full name" autocomplete="name" required
                   value="<?php echo e($_POST['fullName'] ?? ''); ?>">
          </div>
          <?php if (!empty($errors['fullName'])): ?>
          <div class="form-error"><i class="bi bi-exclamation-circle"></i> <?php echo e($errors['fullName']); ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Email <span style="color:var(--c-text-muted);font-weight:400;">(optional)</span></label>
          <div class="input-icon-wrapper">
            <i class="bi bi-envelope"></i>
            <input type="email" id="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                   placeholder="your@email.com" autocomplete="email"
                   value="<?php echo e($_POST['email'] ?? ''); ?>">
          </div>
          <?php if (!empty($errors['email'])): ?>
          <div class="form-error"><i class="bi bi-exclamation-circle"></i> <?php echo e($errors['email']); ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="phone">Phone Number <span style="color:var(--c-danger-light)">*</span></label>
          <div class="input-icon-wrapper">
            <i class="bi bi-telephone"></i>
            <input type="tel" id="phone" name="phone" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                   placeholder="10-digit mobile number" autocomplete="tel" required
                   value="<?php echo e($_POST['phone'] ?? ''); ?>">
          </div>
          <?php if (!empty($errors['phone'])): ?>
          <div class="form-error"><i class="bi bi-exclamation-circle"></i> <?php echo e($errors['phone']); ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password <span style="color:var(--c-danger-light)">*</span></label>
          <div class="input-icon-wrapper">
            <i class="bi bi-lock"></i>
            <input type="password" id="password" name="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                   placeholder="At least 8 characters" autocomplete="new-password" required minlength="8"
                   oninput="checkPasswordStrength(this.value)">
          </div>
          <!-- Password strength indicator -->
          <div class="pw-strength" id="pwStrength">
            <div class="pw-bar" id="bar1"></div>
            <div class="pw-bar" id="bar2"></div>
            <div class="pw-bar" id="bar3"></div>
            <div class="pw-bar" id="bar4"></div>
          </div>
          <div class="pw-label" id="pwLabel"></div>
          <?php if (!empty($errors['password'])): ?>
          <div class="form-error"><i class="bi bi-exclamation-circle"></i> <?php echo e($errors['password']); ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="confirmPassword">Confirm Password <span style="color:var(--c-danger-light)">*</span></label>
          <div class="input-icon-wrapper">
            <i class="bi bi-lock-fill"></i>
            <input type="password" id="confirmPassword" name="confirmPassword" class="form-control <?php echo isset($errors['confirmPassword']) ? 'is-invalid' : ''; ?>"
                   placeholder="Repeat your password" autocomplete="new-password" required>
          </div>
          <?php if (!empty($errors['confirmPassword'])): ?>
          <div class="form-error"><i class="bi bi-exclamation-circle"></i> <?php echo e($errors['confirmPassword']); ?></div>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-success btn-full btn-lg" style="margin-top: 8px;">
          <i class="bi bi-person-check"></i> Create Account
        </button>

        <p class="text-center text-sm mt-4" style="color: var(--c-text-muted);">
          Already have an account?
          <a href="#" onclick="switchTab('login'); return false;" style="color: var(--c-accent-light);">Log in here</a>
        </p>
      </form>
    </div>

  </div><!-- /.auth-panel-right -->
</div><!-- /.auth-wrapper -->

<script>
function switchTab(tab) {
    const loginForm   = document.getElementById('loginForm');
    const signupForm  = document.getElementById('signupForm');
    const loginBtn    = document.getElementById('loginTabBtn');
    const signupBtn   = document.getElementById('signupTabBtn');

    if (tab === 'login') {
        loginForm.classList.add('active');
        signupForm.classList.remove('active');
        loginBtn.classList.add('active');
        signupBtn.classList.remove('active');
        loginBtn.setAttribute('aria-selected', 'true');
        signupBtn.setAttribute('aria-selected', 'false');
    } else {
        signupForm.classList.add('active');
        loginForm.classList.remove('active');
        signupBtn.classList.add('active');
        loginBtn.classList.remove('active');
        signupBtn.setAttribute('aria-selected', 'true');
        loginBtn.setAttribute('aria-selected', 'false');
    }
}

// Password strength meter
function checkPasswordStrength(value) {
    const bars  = [document.getElementById('bar1'), document.getElementById('bar2'),
                   document.getElementById('bar3'), document.getElementById('bar4')];
    const label = document.getElementById('pwLabel');
    const levels = [
        { re: /.{1,}/, level: 0 },
        { re: /.{8,}/, level: 1 },
        { re: /(?=.*[a-z])(?=.*[A-Z])/, level: 2 },
        { re: /(?=.*\d)/, level: 3 },
        { re: /(?=.*[!@#$%^&*])/, level: 4 },
    ];

    let strength = 0;
    if (value.length >= 8) strength++;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) strength++;
    if (/\d/.test(value)) strength++;
    if (/[!@#$%^&*_\-]/.test(value)) strength++;

    const classes = ['', 'weak', 'medium', 'medium', 'strong'];
    const labels  = ['', 'Weak', 'Moderate', 'Good', 'Strong'];
    const colors  = ['', 'var(--c-danger-light)', 'var(--c-warning-light)', 'var(--c-warning-light)', 'var(--c-success-light)'];

    bars.forEach((bar, i) => {
        bar.className = 'pw-bar' + (i < strength ? ' ' + (classes[strength] || '') : '');
        bar.style.background = i < strength ? colors[strength] || '' : '';
    });

    label.textContent = value.length ? (labels[strength] || 'Strong') : '';
    label.style.color = colors[strength] || '';
}
</script>
</body>
</html>
