<?php

$host = '127.0.0.1';
$db   = 'crm_ticket';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  
    @file_put_contents(__DIR__ . '/../storage/logs/db_connect.log', date('c') . ' DB connect error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    
    http_response_code(500);
    echo "Database connection error. Check server logs.";
    exit;
}
