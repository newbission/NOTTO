<?php
// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle Preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database Connection
require_once 'db.php';

// Get POST data
$inputName = $_POST['name'] ?? null;

// Fallback for JSON input if standard POST is empty
if (!$inputName) {
    $json = json_decode(file_get_contents('php://input'), true);
    $inputName = $json['name'] ?? null;
}

if (!$inputName) {
    http_response_code(400);
    echo json_encode(['error' => 'Name parameter is required']);
    exit;
}

$name = trim($inputName);

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, name, status FROM users WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => $name]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        // User exists, return status
        http_response_code(200);
        echo json_encode([
            'message' => 'User already exists',
            'data' => [
                'id' => $existingUser['id'],
                'name' => $existingUser['name'],
                'status' => $existingUser['status']
            ]
        ]);
    } else {
        // User does not exist, insert new user
        $insertStmt = $pdo->prepare("INSERT INTO users (name, status, created_at) VALUES (:name, 'pending', NOW())");
        $insertStmt->execute([':name' => $name]);
        $newId = $pdo->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'message' => 'User created successfully',
            'data' => [
                'id' => $newId,
                'name' => $name,
                'status' => 'pending'
            ]
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>