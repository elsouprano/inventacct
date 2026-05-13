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

$program_id   = isset($_POST['program_id'])   ? (int)$_POST['program_id']   : 0;
$section_code = trim($_POST['section_code'] ?? '');

if ($program_id <= 0) {
    echo json_encode(['error' => 'Invalid program.']); exit;
}
if (!preg_match('/^\d+-\d+$/', $section_code)) {
    echo json_encode(['error' => 'Invalid format. Must be X-Y (e.g. 3-1).']); exit;
}

$year_level = (int)explode('-', $section_code)[0];

try {
    $stmt = $pdo->prepare("INSERT INTO sections (section_code, program_id, year_level, is_active) VALUES (?, ?, ?, 1)");
    $stmt->execute([$section_code, $program_id, $year_level]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'section_code' => $section_code, 'year_level' => $year_level]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['error' => "Section '$section_code' already exists for this program."]);
    } else {
        echo json_encode(['error' => 'Database error.']);
    }
}
