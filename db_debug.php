<?php
require_once 'config/db.php';
$stmt = $pdo->query('SELECT * FROM users LIMIT 1');
print_r(array_keys($stmt->fetch(PDO::FETCH_ASSOC)));
