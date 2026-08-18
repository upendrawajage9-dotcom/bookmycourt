<?php
/**
 * BookMyCourt — Legacy My Bookings Redirect
 *
 * This file (my_bookings.php) is kept for backward compatibility.
 * The real bookings page lives at my-bookings.php.
 */

require_once __DIR__ . '/bootstrap.php';
requireLogin();

header('Location: ' . BASE_URL . '/my-bookings.php');
exit();
