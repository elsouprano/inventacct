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

$label      = trim($_POST['label'] ?? '');
$open_date  = trim($_POST['open_date'] ?? '');
$close_date = trim($_POST['close_date'] ?? '');

if (empty($label) || empty($open_date) || empty($close_date)) {
    $_SESSION['period_error'] = 'All fields are required.';
    header("Location: ../manage_periods.php");
    exit;
}

if (strtotime($close_date) <= strtotime($open_date)) {
    $_SESSION['period_error'] = 'Close date must be after open date.';
    header("Location: ../manage_periods.php");
    exit;
}

// Enforce one active period at a time
$existing = $pdo->query("SELECT id FROM inventory_periods WHERE is_active = 1 LIMIT 1")->fetch();
if ($existing) {
    $_SESSION['period_error'] = 'An active period already exists. Please deactivate it first.';
    header("Location: ../manage_periods.php");
    exit;
}

$stmt = $pdo->prepare("INSERT INTO inventory_periods (label, open_date, close_date, is_active, created_by) VALUES (?, ?, ?, 1, ?)");
$stmt->execute([$label, $open_date, $close_date, $_SESSION['user_id']]);

$_SESSION['period_success'] = "Period '$label' created successfully.";
header("Location: ../manage_periods.php");
exit;
