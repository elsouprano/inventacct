<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Unauthorized.");
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("CSRF token validation failed.");
}

$submission_id = (int)$_POST['submission_id'];
$action = $_POST['action'];

// Verify submission
$stmt = $pdo->prepare("SELECT id, user_id FROM inventory_submissions WHERE id = ?");
$stmt->execute([$submission_id]);
$sub = $stmt->fetch();
if (!$sub) die("Not found.");

if ($action === 'accept') {
    $stmt_upd = $pdo->prepare("UPDATE inventory_submissions SET validity_status = 'valid', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt_upd->execute([$_SESSION['user_id'], $submission_id]);
} elseif ($action === 'reject') {
    $stmt_upd = $pdo->prepare("UPDATE inventory_submissions SET validity_status = 'rejected', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt_upd->execute([$_SESSION['user_id'], $submission_id]);
} elseif ($action === 'resubmit') {
    $stmt_upd = $pdo->prepare("UPDATE inventory_submissions SET validity_status = 'resubmit', is_complete = 0, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt_upd->execute([$_SESSION['user_id'], $submission_id]);
}

header("Location: ../dashboard.php");
exit;
