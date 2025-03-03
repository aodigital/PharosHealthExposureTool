<?php
include '../includes/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$user = $_SESSION['user'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);

if (!$user || !isset($data['plan_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit();
}

$plan_id = intval($data['plan_id']);

try {
    $stmt = $conn->prepare("
        UPDATE Exposure_Plannings_Controls
        SET
            admin_controls_maintenance = :admin_controls_maintenance,
            admin_controls_housekeeping = :admin_controls_housekeeping,
            admin_controls_hygene = :admin_controls_hygene,
            admin_controls_training = :admin_controls_training,
            admin_controls_procedures = :admin_controls_procedures,
            admin_controls_scheduling = :admin_controls_scheduling,
            admin_controls_barriers = :admin_controls_barriers,
            admin_controls_enclosures = :admin_controls_enclosures
        WHERE planning_id = :planning_id
    ");
    
    $stmt->bindParam(':admin_controls_maintenance', $data['admin_controls_maintenance'], PDO::PARAM_STR);
    $stmt->bindParam(':admin_controls_housekeeping', $data['admin_controls_housekeeping'], PDO::PARAM_STR);
    $stmt->bindParam(':admin_controls_hygene', $data['admin_controls_hygene'], PDO::PARAM_STR);
    $stmt->bindParam(':admin_controls_training', $data['admin_controls_training'], PDO::PARAM_STR);
    $stmt->bindParam(':admin_controls_procedures', $data['admin_controls_procedures'], PDO::PARAM_STR);
    $stmt->bindParam(':admin_controls_scheduling', $data['admin_controls_scheduling'], PDO::PARAM_STR);
    $stmt->bindParam(':admin_controls_barriers', $data['admin_controls_barriers'], PDO::PARAM_STR);
    $stmt->bindParam(':admin_controls_enclosures', $data['admin_controls_enclosures'], PDO::PARAM_STR);
    $stmt->bindParam(':planning_id', $plan_id, PDO::PARAM_INT);
    
    $stmt->execute();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
