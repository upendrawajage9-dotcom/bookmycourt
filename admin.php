<?php
/**
 * BookMyCourt — Legacy Admin Redirect
 *
 * This file is kept for backward compatibility.
 * The real admin panel lives at /admin/dashboard.php
 */

require_once __DIR__ . '/bootstrap.php';
requireAdmin();

header('Location: ' . BASE_URL . '/admin/dashboard.php');
exit();

