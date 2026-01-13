<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../model/Core/Database.php';

$pdo = Model\Core\Database::getInstance()->getConnection();

echo "=== PATIENTS TABLE STRUCTURE ===\n";
$stmt = $pdo->query('DESCRIBE patients');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
    echo $row['Field'] . ' - ' . $row['Type'] . "\n"; 
}

echo "\n=== ENCOUNTERS TABLE STRUCTURE ===\n";
$stmt = $pdo->query('DESCRIBE encounters');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
    echo $row['Field'] . ' - ' . $row['Type'] . "\n"; 
}

echo "\n=== SAMPLE PATIENT ===\n";
$stmt = $pdo->query('SELECT * FROM patients LIMIT 1');
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if ($patient) {
    print_r($patient);
} else {
    echo "No patients found\n";
}
