<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'API endpoint is accessible',
    'path' => $_SERVER['REQUEST_URI'],
    'method' => $_SERVER['REQUEST_METHOD']
]);
?>