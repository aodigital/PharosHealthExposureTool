<?php
include '../includes/db.php'; // Assuming this file contains the DB connection
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
$respirator_csv = isset($data['residual_exposure_respirator']) ? $data['residual_exposure_respirator'] : "";
$ppe_csv = isset($data['residual_exposure_ppe']) ? $data['residual_exposure_ppe'] : "";

try {
    // Check if a row with the given planning_id already exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM Exposure_Plannings_Controls WHERE planning_id = :plan_id");
    $stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt->execute();
    $exists = $stmt->fetchColumn() > 0;

    if ($exists) {
        // Update existing row
        $stmt = $conn->prepare("
            UPDATE Exposure_Plannings_Controls
            SET residual_exposure_respirator = :respirator, 
                residual_exposure_ppe = :ppe
            WHERE planning_id = :plan_id
        ");
    } else {
        // Insert new row if one doesn't exist
        $stmt = $conn->prepare("
            INSERT INTO Exposure_Plannings_Controls (planning_id, residual_exposure_respirator, residual_exposure_ppe)
            VALUES (:plan_id, :respirator, :ppe)
        ");
    }

    $stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt->bindParam(':respirator', $respirator_csv, PDO::PARAM_STR);
    $stmt->bindParam(':ppe', $ppe_csv, PDO::PARAM_STR);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
