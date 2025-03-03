<?php
include '../includes/db.php';
header('Content-Type: application/json');

// Decode the JSON payload
$data = json_decode(file_get_contents('php://input'), true);

// Check for required data
if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit();
}

$userId = intval($data['user_id']);

try {
    // Delete the user from the database
    $stmt = $conn->prepare("DELETE FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found or already deleted.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

