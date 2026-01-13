<?php
require_once __DIR__ . '/../includes/config.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
$stmt = $pdo->query('DESCRIBE User');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . PHP_EOL;
}
