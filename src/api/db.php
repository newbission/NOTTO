<?php
// Centralized Database Connection for NOTTO

// 1. Try to get config from Environment Variables (Docker / Local Dev)
// 2. Fallback to hardcoded values (Shared Hosting / Dothome)
//    IMPORTANT: Change these fallback values to match your Dothome DB credentials!

$host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'notto_db';      // e.g., 'newbission' on Dothome
$username = getenv('DB_USER') ?: 'notto_user';    // e.g., 'newbission'
$password = getenv('DB_PASSWORD') ?: 'notto_password'; // Your DB Password

try {
    $dsn = "mysql:host=$host;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // Return standard error format if connection fails
    // Note: On production, you might want to hide $e->getMessage()
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
?>