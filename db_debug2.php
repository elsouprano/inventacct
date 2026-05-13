<?php
require_once 'config/db.php';
echo $pdo->query('SELECT COUNT(*) FROM inventory_scores')->fetchColumn();
