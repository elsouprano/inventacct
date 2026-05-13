<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../manage_periods.php");
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['period_error'] = 'Invalid CSRF token.';
    header("Location: ../manage_periods.php");
    exit;
}

$period_id       = (int)($_POST['period_id'] ?? 0);
$new_close_date  = trim($_POST['new_close_date'] ?? '');

if (!$period_id || empty($new_close_date)) {
    $_SESSION['period_error'] = 'Missing required fields.';
    header("Location: ../manage_periods.php");
    exit;
}

// Fetch current close date
$stmt = $pdo->prepare("SELECT close_date FROM inventory_periods WHERE id = ? AND is_active = 1");
$stmt->execute([$period_id]);
$period = $stmt->fetch();

if (!$period) {
    $_SESSION['period_error'] = 'Active period not found.';
    header("Location: ../manage_periods.php");
    exit;
}

if (strtotime($new_close_date) <= strtotime($period['close_date'])) {
    $_SESSION['period_error'] = 'New close date must be later than the current close date (' . date('M d, Y h:i A', strtotime($period['close_date'])) . ').';
    header("Location: ../manage_periods.php");
    exit;
}

$stmt = $pdo->prepare("UPDATE inventory_periods SET close_date = ?, extended_by = ?, extended_at = CURRENT_TIMESTAMP WHERE id = ?");
$stmt->execute([$new_close_date, $_SESSION['user_id'], $period_id]);

$_SESSION['period_success'] = 'Closing date extended to ' . date('M d, Y h:i A', strtotime($new_close_date)) . '.';
header("Location: ../manage_periods.php");
exit;
