<?php
require_once 'config/db.php';

echo "1. inventory_scores (LIMIT 10):\n";
$scores = $pdo->query("SELECT * FROM inventory_scores LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
print_r($scores);

echo "\n2. inventory_answers (LIMIT 30):\n";
$answers = $pdo->query("SELECT section, question_key, answer_value FROM inventory_answers WHERE submission_id = (SELECT MAX(id) FROM inventory_submissions) LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
print_r($answers);

echo "\n3. student/inventory.php relevant section:\n";
$content = file_get_contents('student/inventory.php');
$start = strpos($content, 'require_once \'../config/scoring.php\';');
if ($start !== false) {
    echo substr($content, $start - 200, 500);
}

echo "\n4. config/scoring.php keys vs inventory_answers keys:\n";
echo "Scoring expects: learning_q1, erq_q1, cat_q1, dass_q1, ars_q1, ffmq_q1\n";
echo "Answers contain: ";
$keys = $pdo->query("SELECT DISTINCT question_key FROM inventory_answers LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
echo implode(", ", $keys) . "\n";
