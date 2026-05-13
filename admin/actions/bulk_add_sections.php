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

$program_id  = isset($_POST['program_id'])  ? (int)$_POST['program_id']  : 0;
$year_level  = isset($_POST['year_level'])  ? (int)$_POST['year_level']  : 0;
$from        = isset($_POST['from'])        ? (int)$_POST['from']        : 1;
$to          = isset($_POST['to'])          ? (int)$_POST['to']          : 0;

if ($program_id <= 0) { echo json_encode(['error' => 'Invalid program.']); exit; }
if ($year_level < 1 || $year_level > 9) { echo json_encode(['error' => 'Invalid year level.']); exit; }
if ($from < 1) { echo json_encode(['error' => 'From must be at least 1.']); exit; }
if ($to < $from) { echo json_encode(['error' => 'To must be >= from.']); exit; }
if (($to - $from + 1) > 30) { echo json_encode(['error' => 'Maximum 30 sections at a time.']); exit; }

$added   = 0;
$skipped = 0;

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT IGNORE INTO sections (section_code, program_id, year_level, is_active) VALUES (?, ?, ?, 1)");
    for ($i = $from; $i <= $to; $i++) {
        $code = "{$year_level}-{$i}";
        $stmt->execute([$code, $program_id, $year_level]);
        if ($stmt->rowCount() > 0) $added++;
        else $skipped++;
    }
    $pdo->commit();
    echo json_encode(['success' => true, 'added' => $added, 'skipped' => $skipped]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Database error.']);
}
