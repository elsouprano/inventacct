<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

header('Content-Type: application/json');

$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
if ($program_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.id, s.section_code, s.year_level, s.is_active,
           COUNT(u.id) as student_count
    FROM sections s
    LEFT JOIN users u ON u.section = s.section_code 
        AND u.program_id = s.program_id 
        AND u.role = 'student'
    WHERE s.program_id = ?
    GROUP BY s.id
    ORDER BY s.year_level ASC, s.section_code ASC
");
$stmt->execute([$program_id]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($sections);
