<?php
require_once 'config/db.php';
require_once 'config/scoring.php';

$stmt = $pdo->query("SELECT MAX(id) FROM inventory_submissions");
$id = $stmt->fetchColumn();
echo "Submission ID: $id\n";
try {
    computeAndSaveScores($pdo, $id);
    echo "Scores computed successfully.\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
