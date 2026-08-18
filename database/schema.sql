-- =============================================================
-- BookMyCourt — PostgreSQL Database Schema
-- Version: 2.0 (Production-grade restructure)
-- =============================================================
-- 
-- Run this file once against your badminton_booking database:
--   psql -U postgres -d badminton_booking -f schema.sql
--
-- The schema uses IF NOT EXISTS / IF EXISTS guards so it is safe
-- to re-run without destroying existing data.
-- =============================================================

-- ─────────────────────────────────────────────────────────────
-- 0. EXTENSIONS
-- ─────────────────────────────────────────────────────────────
CREATE EXTENSION IF NOT EXISTS pgcrypto;   -- for gen_random_uuid() if needed

-- ─────────────────────────────────────────────────────────────
-- 1. USERS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id              SERIAL PRIMARY KEY,
    full_name       VARCHAR(120) NOT NULL,
    email           VARCHAR(180) UNIQUE,
    phone           VARCHAR(15)  UNIQUE NOT NULL,
    password_hash   TEXT         NOT NULL,        -- bcrypt hash (password_hash in PHP)
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- Ensure email uniqueness is non-null when provided
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email) WHERE email IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone);

-- ─────────────────────────────────────────────────────────────
-- 2. ADMINS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admins (
    id            SERIAL PRIMARY KEY,
    name          VARCHAR(120) NOT NULL,
    email         VARCHAR(180) UNIQUE NOT NULL,
    password_hash TEXT         NOT NULL,           -- bcrypt hash
    is_active     BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- ─────────────────────────────────────────────────────────────
-- 3. COURTS (Venues)
-- ─────────────────────────────────────────────────────────────
-- Represents a physical badminton venue/hall.
CREATE TABLE IF NOT EXISTS courts (
    id              SERIAL PRIMARY KEY,
    hall_name       VARCHAR(200)    NOT NULL,
    location        VARCHAR(200)    NOT NULL,
    address         TEXT,
    description     TEXT,
    price_per_hour  NUMERIC(10, 2)  NOT NULL CHECK (price_per_hour > 0),
    num_courts      SMALLINT        NOT NULL DEFAULT 1 CHECK (num_courts > 0),
    facilities      TEXT,           -- comma-separated list
    opening_time    TIME            NOT NULL DEFAULT '06:00:00',
    closing_time    TIME            NOT NULL DEFAULT '22:00:00',
    rules           TEXT,
    is_active       BOOLEAN         NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_courts_active ON courts(is_active);

-- ─────────────────────────────────────────────────────────────
-- 4. INDIVIDUAL COURTS
-- ─────────────────────────────────────────────────────────────
-- Each physical court within a venue gets its own row.
-- This allows precise per-court availability tracking.
CREATE TABLE IF NOT EXISTS individual_courts (
    id            SERIAL PRIMARY KEY,
    venue_id      INTEGER      NOT NULL REFERENCES courts(id) ON DELETE CASCADE,
    court_name    VARCHAR(50)  NOT NULL,     -- e.g. "Court 1"
    court_number  SMALLINT     NOT NULL,     -- 1, 2, 3, ...
    is_active     BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    UNIQUE (venue_id, court_number)
);

CREATE INDEX IF NOT EXISTS idx_individual_courts_venue ON individual_courts(venue_id);

-- ─────────────────────────────────────────────────────────────
-- 5. BOOKINGS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bookings (
    id                  SERIAL PRIMARY KEY,
    user_id             INTEGER         NOT NULL REFERENCES users(id),
    venue_id            INTEGER         NOT NULL REFERENCES courts(id),
    individual_court_id INTEGER         NOT NULL REFERENCES individual_courts(id),
    booking_date        DATE            NOT NULL,
    time_slot           VARCHAR(30)     NOT NULL,   -- e.g. "6:00-7:00 AM"
    total_price         NUMERIC(10, 2)  NOT NULL,
    status              VARCHAR(20)     NOT NULL DEFAULT 'PENDING'
                            CHECK (status IN ('PENDING','CONFIRMED','CANCELLED','COMPLETED')),
    cancellation_reason TEXT,
    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),

    -- Core uniqueness: same physical court cannot be booked twice in same slot
    -- DEFERRABLE INITIALLY IMMEDIATE allows checking inside transactions
    CONSTRAINT uq_booking_slot
        UNIQUE (individual_court_id, booking_date, time_slot)
        DEFERRABLE INITIALLY IMMEDIATE
);

CREATE INDEX IF NOT EXISTS idx_bookings_user    ON bookings(user_id);
CREATE INDEX IF NOT EXISTS idx_bookings_venue   ON bookings(venue_id);
CREATE INDEX IF NOT EXISTS idx_bookings_date    ON bookings(booking_date);
CREATE INDEX IF NOT EXISTS idx_bookings_status  ON bookings(status);
CREATE INDEX IF NOT EXISTS idx_bookings_created ON bookings(created_at);

-- ─────────────────────────────────────────────────────────────
-- 6. PAYMENTS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payments (
    id                  SERIAL PRIMARY KEY,
    booking_id          INTEGER         NOT NULL REFERENCES bookings(id),
    gateway             VARCHAR(50)     NOT NULL DEFAULT 'razorpay',
    razorpay_order_id   VARCHAR(100)    UNIQUE,
    razorpay_payment_id VARCHAR(100)    UNIQUE,
    razorpay_signature  TEXT,
    amount              NUMERIC(10, 2)  NOT NULL,
    currency            CHAR(3)         NOT NULL DEFAULT 'INR',
    payment_method      VARCHAR(50),
    status              VARCHAR(20)     NOT NULL DEFAULT 'PENDING'
                            CHECK (status IN ('PENDING','SUCCESS','FAILED','REFUNDED')),
    failure_reason      TEXT,
    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_payments_booking ON payments(booking_id);
CREATE INDEX IF NOT EXISTS idx_payments_order   ON payments(razorpay_order_id);
CREATE INDEX IF NOT EXISTS idx_payments_status  ON payments(status);

-- ─────────────────────────────────────────────────────────────
-- 7. NOTIFICATIONS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title      VARCHAR(200) NOT NULL,
    message    TEXT         NOT NULL,
    type       VARCHAR(30)  NOT NULL DEFAULT 'info'
                   CHECK (type IN ('info','success','warning','error')),
    is_read    BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notifications_user_unread
    ON notifications(user_id, is_read) WHERE is_read = FALSE;

-- ─────────────────────────────────────────────────────────────
-- 8. FAVORITES
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS favorites (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    venue_id   INTEGER     NOT NULL REFERENCES courts(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, venue_id)
);

CREATE INDEX IF NOT EXISTS idx_favorites_user  ON favorites(user_id);
CREATE INDEX IF NOT EXISTS idx_favorites_venue ON favorites(venue_id);

-- ─────────────────────────────────────────────────────────────
-- 9. REVIEWS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reviews (
    id          SERIAL PRIMARY KEY,
    user_id     INTEGER      NOT NULL REFERENCES users(id),
    venue_id    INTEGER      NOT NULL REFERENCES courts(id),
    booking_id  INTEGER      NOT NULL REFERENCES bookings(id),
    rating      SMALLINT     NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text TEXT,
    is_approved BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    UNIQUE (booking_id)                              -- one review per booking
);

CREATE INDEX IF NOT EXISTS idx_reviews_venue ON reviews(venue_id, is_approved);
CREATE INDEX IF NOT EXISTS idx_reviews_user  ON reviews(user_id);

-- ─────────────────────────────────────────────────────────────
-- 10. UPDATED_AT TRIGGERS (auto-update timestamp on row change)
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$
DECLARE
    t TEXT;
BEGIN
    FOREACH t IN ARRAY ARRAY['users','courts','bookings','payments']
    LOOP
        EXECUTE format(
            'DROP TRIGGER IF EXISTS trg_updated_%1$s ON %1$s;
             CREATE TRIGGER trg_updated_%1$s
             BEFORE UPDATE ON %1$s
             FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();',
            t
        );
    END LOOP;
END;
$$;

-- =============================================================
-- SEED DATA — Run only on a fresh database
-- =============================================================

-- ─── Default Admin ────────────────────────────────────────────
-- Password: Admin@123  (bcrypt hash generated in PHP)
-- Change immediately after first login.
INSERT INTO admins (name, email, password_hash)
VALUES (
    'BookMyCourt Admin',
    'admin@bookmycourt.com',
    '$2y$12$8K1p/a0dR1xqM3u4C5y6OexbFR8YE9z1/L.Uve3FWBZxYmLGpMpiq'
)
ON CONFLICT (email) DO NOTHING;

-- ─── Venues (Courts) ─────────────────────────────────────────
INSERT INTO courts (id, hall_name, location, address, description, price_per_hour, num_courts, facilities, opening_time, closing_time, rules) VALUES

(1, 'PDMBA Sports Complex',
 'Kothrud, Pune',
 'Plot 14, Kothrud, Pune - 411038',
 'A premium multi-court badminton facility in Kothrud with professional-grade wooden flooring, bright LED lighting, and international shuttlecocks provided. Suitable for beginners, intermediate players, and tournaments.',
 250, 9,
 'Wooden Flooring,LED Lighting,Changing Rooms,Parking,Cafeteria,Shuttlecocks Provided',
 '06:00', '22:00',
 'Sports shoes mandatory. No food or drinks inside courts. Booking must be cancelled 2 hours in advance for a refund. Personal shuttlecocks allowed.'),

(2, 'Raisons Badminton Academy',
 'Baner, Pune',
 'Near Baner Road, Baner, Pune - 411045',
 'A focused badminton academy in Baner providing professional coaching alongside court rentals. Synthetic mat flooring ensures consistent ball bounce and player safety.',
 300, 3,
 'Synthetic Flooring,Coaching Available,Equipment Rental,Air Conditioning,Locker Rooms',
 '06:00', '21:00',
 'Academy members get priority booking. Non-slip sports shoes required. No outside food allowed.'),

(3, 'Gravity Badminton Complex',
 'Wakad, Pune',
 'Wakad Chowk, Wakad, Pune - 411057',
 'One of Pune''s largest badminton complexes in Wakad, featuring 8 international-standard courts. State-of-the-art facilities with ample parking and a sports lounge.',
 280, 8,
 'International Courts,Spectator Seating,Sports Lounge,Ample Parking,First Aid',
 '05:30', '23:00',
 'Advance booking mandatory for weekends. No smoking. Players must wear sport-appropriate attire.'),

(4, 'All Stars Badminton Academy',
 'Pimple Saudagar, Pune',
 'Near Pimple Saudagar Road, Pune - 411027',
 'All Stars is a well-established badminton academy offering both training and court rental. Known for its clean facilities and professional atmosphere. Courts are maintained to international standards.',
 320, 8,
 'Professional Coaching,Synthetic Mat,Air Cooled,Changing Room,Parking,Water Purifier',
 '06:00', '22:00',
 'Respect other players. Use non-marking shoes only. Cancel at least 2 hours before the slot.'),

(5, 'Infinity Badminton Arena',
 'Hinjewadi, Pune',
 'Phase 1, Hinjewadi, Pune - 411057',
 'Conveniently located near IT hubs in Hinjewadi, Infinity Arena is popular among professionals. Offers flexible early morning and late evening slots.',
 350, 4,
 'Air Conditioning,Wooden Flooring,Locker Rooms,Coaching,Cafeteria,CCTV',
 '06:00', '22:30',
 'Courts are reserved for 5 minutes past booking time. No outside food or drinks.'),

(6, 'SportyGen Badminton Arena',
 'Aundh, Pune',
 'Near Aundh Road, Aundh, Pune - 411007',
 'SportyGen is a modern, well-lit badminton arena in Aundh. The venue hosts corporate tournaments and training camps in addition to regular court bookings.',
 260, 6,
 'LED Courts,Shower Facility,Equipment Rental,Parking,Air Cooled,Online Booking',
 '06:00', '22:00',
 'Please inform reception if you are late. Maintain court hygiene. Coaching sessions available on request.'),

(7, 'Galaxy Badminton Centre',
 'Shivaji Nagar, Pune',
 'Near FC Road, Shivaji Nagar, Pune - 411005',
 'Galaxy Badminton Centre is centrally located in Shivaji Nagar, making it accessible from most parts of Pune. Perfect for casual players and competitive training alike.',
 275, 4,
 'Wooden Flooring,Changing Rooms,Parking,Cafeteria,Sports Shop',
 '06:00', '21:30',
 'No pets allowed. Proper sports attire mandatory. Children under 10 must be accompanied by an adult.'),

(8, 'AD Badminton Academy',
 'Hadapsar, Pune',
 'Near Hadapsar Industrial Estate, Pune - 411013',
 'AD Badminton Academy in Hadapsar offers professional coaching programs and court rentals. Known for producing competitive players, it maintains high standards of court upkeep.',
 240, 2,
 'Professional Coaching,Synthetic Flooring,Equipment Rental,Changing Room',
 '07:00', '21:00',
 'Academy rules apply. Respect coaching schedules. Booking changes must be made 3 hours in advance.'),

(9, 'Supernova Badminton Arena',
 'Magarpatta, Pune',
 'Magarpatta City, Hadapsar, Pune - 411028',
 'Supernova is a premium sports destination in Magarpatta City, surrounded by tech parks. Offers a relaxed yet professional environment for after-work play.',
 330, 3,
 'Air Conditioning,Wooden Flooring,Cafeteria,Locker Rooms,Wi-Fi,CCTV',
 '06:30', '22:30',
 'Corporate booking packages available. Members get 15% discount. No outside food permitted.'),

(10, 'MatchPoint Pimple Saudagar',
 'Pimple Saudagar, Pune',
 'Pimple Saudagar, Pune - 411027',
 'MatchPoint is a boutique badminton venue known for its intimate atmosphere and highly maintained courts. Ideal for focused training and small group sessions.',
 290, 3,
 'Synthetic Flooring,LED Lighting,Parking,Water Station,Coaching',
 '06:00', '21:30',
 'Maximum 4 players per court. Slots are strictly 1 hour each. Punctuality is appreciated.')

ON CONFLICT (id) DO UPDATE SET
    hall_name      = EXCLUDED.hall_name,
    location       = EXCLUDED.location,
    address        = EXCLUDED.address,
    description    = EXCLUDED.description,
    price_per_hour = EXCLUDED.price_per_hour,
    num_courts     = EXCLUDED.num_courts,
    facilities     = EXCLUDED.facilities,
    opening_time   = EXCLUDED.opening_time,
    closing_time   = EXCLUDED.closing_time,
    rules          = EXCLUDED.rules;

-- Reset sequence after explicit id inserts
SELECT setval('courts_id_seq', (SELECT MAX(id) FROM courts));

-- ─── Individual Courts ────────────────────────────────────────
-- Delete old individual courts and recreate (safe for fresh installs)
DELETE FROM individual_courts;

-- PDMBA Sports Complex: 9 courts
INSERT INTO individual_courts (venue_id, court_name, court_number)
SELECT 1, 'Court ' || n, n FROM generate_series(1, 9) AS n;

-- Raisons Badminton Academy: 3 courts
INSERT INTO individual_courts (venue_id, court_name, court_number)
SELECT 2, 'Court ' || n, n FROM generate_series(1, 3) AS n;

-- Gravity Badminton Complex: 8 courts
INSERT INTO individual_courts (venue_id, court_name, court_number)
SELECT 3, 'Court ' || n, n FROM generate_series(1, 8) AS n;

-- All Stars Badminton Academy: 8 courts
INSERT INTO individual_courts (venue_id, court_name, court_number)
SELECT 4, 'Court ' || n, n FROM generate_series(1, 8) AS n;

-- Infinity Badminton Arena: 4 courts
INSERT INTO individual_courts (venue_id, court_name, court_number)
SELECT 5, 'Court ' || n, n FROM generate_series(1, 4) AS n;

-- SportyGen: 6 courts
INSERT INTO individual_courts (venue_id, court_name, court_number)
SELECT 6, 'Court ' || n, n FROM generate_series(1, 6) AS n;

-- Galaxy: 4 courts
INSERT INTO individual_courts (venue_id, court_name, court_number)
SELECT 7, 'Court ' || n, n FROM generate_series(1, 4) AS n;

-- AD Academy: 2 courts
INSERT INTO individual_courts (venue_id, court_name, court_number)
SELECT 8, 'Court ' || n, n FROM generate_series(1, 2) AS n;

-- Supernova: 3 courts
INSERT INTO individual_courts (venue_id, court_name, court_number)
SELECT 9, 'Court ' || n, n FROM generate_series(1, 3) AS n;

-- MatchPoint: 3 courts
INSERT INTO individual_courts (venue_id, court_name, court_number)
SELECT 10, 'Court ' || n, n FROM generate_series(1, 3) AS n;

-- ─── Verify ───────────────────────────────────────────────────
-- Uncomment to check seeding:
-- SELECT c.hall_name, COUNT(ic.id) AS court_count
-- FROM courts c LEFT JOIN individual_courts ic ON c.id = ic.venue_id
-- GROUP BY c.id, c.hall_name ORDER BY c.id;
