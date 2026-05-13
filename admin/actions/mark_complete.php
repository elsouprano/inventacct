<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }
    
    $user_id = $_POST['user_id'] ?? null;
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT id, is_complete FROM inventory_submissions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $submission = $stmt->fetch();
        
        if ($submission) {
            $new_status = $submission['is_complete'] ? 0 : 1;
            $update = $pdo->prepare("UPDATE inventory_submissions SET is_complete = ?, manually_marked = 1 WHERE id = ?");
            $update->execute([$new_status, $submission['id']]);
        } else {
            $insert = $pdo->prepare("INSERT INTO inventory_submissions (user_id, is_complete, manually_marked) VALUES (?, 1, 1)");
            $insert->execute([$user_id]);
        }
    }
}

// Redirect back to dashboard or student view
$redirect = $_SERVER['HTTP_REFERER'] ?? '../dashboard.php';
header("Location: $redirect");
exit;
