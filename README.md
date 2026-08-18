# 🏸 BookMyCourt

> A full-stack badminton court booking platform for Pune. Browse venues, select real-time available slots, and pay securely via Razorpay.

**Live Demo:** [your-live-link-here](#) <!-- Replace with your deployed URL -->

---

## 📸 Screenshots

> *(Add screenshots of your homepage, courts page, booking flow, and admin dashboard)*

---

## ✨ Features

| Feature | Details |
|---|---|
| 🔐 Auth | Login / Register with bcrypt password hashing, session management |
| 🏟️ Venue Browsing | Filter by location, price, and facilities |
| 📅 Real-time Availability | Live slot availability per individual court per date |
| 💳 Razorpay Payments | Server-side HMAC-SHA256 signature verification |
| 🛡️ CSRF Protection | Token-per-session, hash_equals comparison |
| ❌ Zero Double-Booking | DB-level `UNIQUE` constraint + `SELECT FOR UPDATE` in transaction |
| 👤 My Bookings | View, filter and cancel bookings (CONFIRMED → CANCELLED + refund flag) |
| 🔔 Notifications | In-app notification system (booking confirmed, cancelled) |
| ⭐ Favorites | Save venues to favorites |
| 🛠️ Admin Panel | Dashboard with metrics, booking management (confirm/cancel), venue revenue |
| 📱 Responsive | Mobile-first, works on all screen sizes |

---

## 🛠️ Tech Stack

- **Backend:** PHP 8.1+ (no framework, procedural + OOP helpers)
- **Database:** PostgreSQL 14+
- **Frontend:** HTML5, CSS3 (custom design system), Vanilla JS
- **Icons:** Bootstrap Icons
- **Payment:** Razorpay (test & live mode)
- **Hosting:** Apache / Nginx + PHP on shared host or VPS

---

## 📁 Project Structure

```
WebContent/
├── index.php              # Homepage (hero, featured venues, how-it-works)
├── courts.php             # All venues with search & filter
├── court-details.php      # Venue detail page with reviews
├── book.php               # Multi-step booking page (select slot → pay)
├── my-bookings.php        # User's booking history (upcoming/completed/cancelled)
├── profile.php            # User profile management
├── login.php              # Login + Registration
├── logout.php             # Secure logout
├── bootstrap.php          # App bootstrap (env, DB, session, helpers)
│
├── admin/
│   ├── dashboard.php      # Admin overview: metrics, recent bookings
│   └── bookings.php       # Full booking management with filters & pagination
│
├── api/
│   ├── availability.php   # GET: slot availability for a court+date
│   ├── booking.php        # POST: create booking + Razorpay order
│   ├── payment-verify.php # POST: verify Razorpay payment signature
│   ├── favorites.php      # Toggle venue favorite
│   └── notifications.php  # Get unread notifications
│
├── config/
│   ├── database.php       # PDO singleton connection
│   └── environment.php    # .env loader + env() helper
│
├── includes/
│   ├── header.php         # HTML head + navbar
│   ├── footer.php         # Footer + JS
│   ├── auth.php           # requireLogin(), requireGuest()
│   ├── admin_auth.php     # requireAdmin()
│   ├── csrf.php           # CSRF token generation + verification
│   └── functions.php      # Utility helpers (formatPrice, notify, etc.)
│
├── database/
│   └── schema.sql         # Full PostgreSQL schema + seed data
│
├── assets/
│   ├── css/               # Stylesheets (base, components, pages)
│   ├── js/                # JavaScript files
│   └── images/            # Court images, logo
│
├── .env.example           # Environment variable template
├── .gitignore             # Ignores .env, logs, vendor
└── README.md              # This file
```

---

## ⚙️ Local Setup

### Prerequisites
- PHP 8.1+ with extensions: `pdo`, `pdo_pgsql`, `curl`, `session`
- PostgreSQL 14+
- Apache or Nginx (with PHP-FPM)
- A Razorpay account (free) for payment testing

### 1. Clone the repository
```bash
git clone https://github.com/YOUR_USERNAME/bookmycourt.git
cd bookmycourt
```

### 2. Configure environment
```bash
cp .env.example .env
```
Edit `.env` with your values:
```ini
APP_URL=http://localhost/BookMyCourt
DB_HOST=localhost
DB_PORT=5432
DB_NAME=badminton_booking
DB_USER=postgres
DB_PASSWORD=your_password
RAZORPAY_KEY_ID=rzp_test_xxxxxxxxxxxx
RAZORPAY_KEY_SECRET=your_razorpay_secret
```

### 3. Set up the database
```bash
psql -U postgres -c "CREATE DATABASE badminton_booking;"
psql -U postgres -d badminton_booking -f database/schema.sql
```
This creates all tables and seeds 10 Pune venues + 45+ individual courts.

### 4. Default Admin Credentials
```
Email:    admin@bookmycourt.com
Password: Admin@123
```
> **Change this immediately after first login!**

### 5. Place files in your web root
For XAMPP/WAMP: copy the `WebContent/` folder to `htdocs/BookMyCourt/`

### 6. Access the app
- Site: `http://localhost/BookMyCourt/`
- Admin: `http://localhost/BookMyCourt/admin/dashboard.php`

---

## 🚀 Deployment

### Option A — Shared Hosting (InfinityFree / Hostinger / etc.)
1. Upload all files via FTP to `public_html/`
2. Create a PostgreSQL database on your host panel
3. Import `database/schema.sql`
4. Create `.env` on the server with production values:
   ```ini
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com
   ```
5. Set file permissions: `644` for PHP files, `755` for directories

### Option B — Railway / Render (Free Tier)
1. Push to GitHub
2. Connect repo to Railway
3. Add a PostgreSQL plugin
4. Set environment variables in the Railway dashboard
5. Set build command to `echo "PHP project"` and start command to `php -S 0.0.0.0:$PORT`

> **Note:** InfinityFree offers free PHP + MySQL hosting. For PostgreSQL specifically, Railway or Supabase (as DB) + Vercel (as PHP host via PHP runtime) work well.

---

## 🔐 Security Features

- **SQL Injection:** 100% parameterized PDO queries — no raw string interpolation
- **XSS:** All output runs through `htmlspecialchars()` / `e()` helper
- **CSRF:** Token-per-session with `hash_equals()` constant-time comparison
- **Double Booking:** `SELECT FOR UPDATE` inside `SERIALIZABLE` transaction + DB `UNIQUE` constraint
- **Payment Fraud:** Razorpay HMAC-SHA256 signature verified server-side before confirming booking
- **Session Security:** `session_regenerate_id(true)` on login, `HttpOnly`, `SameSite=Strict` cookies
- **Admin Auth:** Separate `admins` table; regular users cannot access admin routes

---

## 📊 Database Schema (Key Tables)

```
users           → registered players
admins          → admin accounts
courts          → venues (PDMBA, Raisons, Gravity, etc.)
individual_courts → physical courts within a venue (Court 1, Court 2...)
bookings        → each slot reservation (PENDING → CONFIRMED / CANCELLED)
payments        → Razorpay payment records (PENDING → SUCCESS / FAILED)
notifications   → in-app alerts for users
favorites       → saved venues per user
reviews         → star ratings + text reviews per booking
```

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Commit changes: `git commit -m 'Add your feature'`
4. Push to branch: `git push origin feature/your-feature`
5. Open a Pull Request

---

## 📄 License

MIT License — free to use for personal and educational purposes.

---

## 👤 Author

**Your Name**  
📧 your.email@example.com  
🔗 [LinkedIn](https://linkedin.com/in/yourprofile) | [GitHub](https://github.com/yourusername)
