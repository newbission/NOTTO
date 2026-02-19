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

// Get POST JSON data
$rawData = file_get_contents('php://input');
$updates = json_decode($rawData, true);

if (!is_array($updates)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// Begin Bulk Update Transaction
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE users SET numbers = :numbers, updated_at = NOW() WHERE name = :name");

    $updatedCount = 0;
    $errors = [];

    foreach ($updates as $item) {
        $name = $item['name'] ?? null;
        $numbers = $item['numbers'] ?? null;

        if ($name && $numbers !== null) {
            // Encode numbers to JSON string if it's an array, or allow string
            $numbersJson = is_array($numbers) || is_object($numbers) ? json_encode($numbers) : $numbers;

            try {
                $stmt->execute([
                    ':numbers' => $numbersJson,
                    ':name' => $name
                ]);
                if ($stmt->rowCount() > 0) {
                    $updatedCount++;
                }
            } catch (Exception $rowEx) {
                // Log specific row error if needed, but for bulk usually we want all or nothing or just report.
                // Here we continue for best effort or fail? 
                // DB transactions usually imply all-or-nothing. Let's throw to rollback if critical, 
                // but usually for bulk updates via API we might want to know which failed.
                // However, user asked for "Logic to Bulk Update". Transaction is safest.
                throw $rowEx;
            }
        }
    }

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'message' => 'Bulk update processed',
        'updated_count' => $updatedCount
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Bulk update failed: ' . $e->getMessage()]);
}
?>