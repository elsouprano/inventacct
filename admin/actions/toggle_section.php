<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']); exit;
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['error' => 'Invalid CSRF token']); exit;
}

$section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
if ($section_id <= 0) { echo json_encode(['error' => 'Invalid section.']); exit; }

// Fetch current section
$stmt = $pdo->prepare("SELECT s.*, p.code as program_code FROM sections s JOIN programs p ON s.program_id = p.id WHERE s.id = ?");
$stmt->execute([$section_id]);
$section = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$section) { echo json_encode(['error' => 'Section not found.']); exit; }

// If active → check for students before deactivating
if ($section['is_active']) {
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND section = ? AND program_id = ?");
    $stmt_check->execute([$section['section_code'], $section['program_id']]);
    $count = (int)$stmt_check->fetchColumn();
    if ($count > 0) {
        echo json_encode(['error' => "Cannot deactivate — {$count} student(s) are registered in this section."]);
        exit;
    }
}

$new_status = $section['is_active'] ? 0 : 1;
$pdo->prepare("UPDATE sections SET is_active = ? WHERE id = ?")->execute([$new_status, $section_id]);

echo json_encode(['success' => true, 'is_active' => $new_status]);
