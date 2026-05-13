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

// Get current status
$stmt = $pdo->prepare("SELECT user_id, risk_reviewed FROM inventory_submissions WHERE id = ?");
$stmt->execute([$submission_id]);
$sub = $stmt->fetch();
if (!$sub) die("Not found.");

if ($sub['risk_reviewed']) {
    $stmt_upd = $pdo->prepare("UPDATE inventory_submissions SET risk_reviewed = 0, risk_reviewed_by = NULL, risk_reviewed_at = NULL WHERE id = ?");
    $stmt_upd->execute([$submission_id]);
} else {
    $stmt_upd = $pdo->prepare("UPDATE inventory_submissions SET risk_reviewed = 1, risk_reviewed_by = ?, risk_reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt_upd->execute([$_SESSION['user_id'], $submission_id]);
}

header("Location: ../student_view.php?id=" . $sub['user_id']);
exit;
