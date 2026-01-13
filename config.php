<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'inventory_system');

// Set default user session (no login required)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'system';
    $_SESSION['full_name'] = 'Ace Inventory';
    $_SESSION['role'] = 'admin';
}

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Check if user is logged in (always true now)
function isLoggedIn() {
    return true;
}

// Check user role (always admin now)
function hasRole($role) {
    return true;
}

// Redirect if not logged in (disabled)
function requireLogin() {
    // No login required - function kept for compatibility
    return;
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}
?>