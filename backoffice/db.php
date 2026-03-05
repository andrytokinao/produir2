<?php
// db.php - Database connection for backoffice
// Update credentials as needed for your XAMPP environment

$DB_HOST = '127.0.0.1';
$DB_NAME = 'produir2_db';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    // max_allowed_packet is configured in my.ini (64MB)
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}
