<?php
include '../includes/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// Check for required data
if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit();
}

$userId = intval($data['user_id']);
$fieldsToUpdate = ['first_name', 'last_name', 'company_name', 'email', 'phone', 'role'];
$updates = [];

foreach ($fieldsToUpdate as $field) {
    if (isset($data[$field])) {
        $updates[$field] = $data[$field];
    }
}

if (empty($updates)) {
    echo json_encode(['success' => false, 'message' => 'No valid fields to update.']);
    exit();
}

// Build the SQL query dynamically
$sqlParts = [];
$params = [':user_id' => $userId];

foreach ($updates as $field => $value) {
    $sqlParts[] = "`$field` = :$field";
    $params[":$field"] = $value;
}

$sql = "UPDATE users SET " . implode(', ', $sqlParts) . " WHERE id = :user_id";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes were made.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
