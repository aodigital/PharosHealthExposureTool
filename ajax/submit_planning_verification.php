<?php
include '../includes/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['plan_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing plan_id parameter.']);
    exit();
}

$plan_id = intval($data['plan_id']);

try {
    $stmt = $conn->prepare("UPDATE Exposure_Plannings SET verified = 1, updated_at = NOW() WHERE id = :plan_id");
    $stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $e->getMessage()]);
    exit();
}
?>
