<?php
session_start();
require_once '../../config/db.php';

// Student-only guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$answers_raw = $_POST['answers'] ?? '';

// Validate JSON
$answers = json_decode($answers_raw, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($answers)) {
    echo json_encode(['success' => false, 'error' => 'Invalid answers data']);
    exit;
}

// Sanitize keys — only allow known safe alphanumeric/underscore keys
$safe_answers = [];
foreach ($answers as $key => $val) {
    $clean_key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
    if ($clean_key === $key && strlen($key) <= 50) {
        $safe_answers[$clean_key] = is_string($val) ? mb_substr($val, 0, 500) : $val;
    }
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        INSERT INTO inventory_drafts (user_id, current_step, answers)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            current_step = VALUES(current_step),
            answers = VALUES(answers),
            last_saved = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$user_id, $step, json_encode($safe_answers)]);

    echo json_encode(['success' => true, 'last_saved' => 'just now']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
