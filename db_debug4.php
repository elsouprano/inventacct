<?php
require_once 'config/db.php';
$stmt = $pdo->query('SELECT submission_id, section, question_key FROM inventory_answers ORDER BY id DESC LIMIT 50');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
