<?php
// Public endpoint — no session guard needed (just dropdown data)
require_once '../../config/db.php';

header('Content-Type: application/json');

$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;

if ($program_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT section_code FROM sections WHERE program_id = ? AND is_active = 1 ORDER BY year_level ASC, section_code ASC");
$stmt->execute([$program_id]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($sections);
