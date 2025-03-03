<?php
include '../includes/db.php'; // Assuming this file contains the DB connection
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$user = $_SESSION['user'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);

if (!$user || !isset($data['plan_id']) || !isset($data['elimination_control'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit();
}

$plan_id = intval($data['plan_id']);
// Now elimination_control is a CSV string, e.g. "1,0,1"
$elimination_control = $data['elimination_control'];

try {
    // Check if a row with the given planning_id already exists
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM Exposure_Plannings_Controls 
        WHERE planning_id = :planning_id
    ");
    $stmt->bindParam(':planning_id', $plan_id, PDO::PARAM_INT);
    $stmt->execute();
    $rowExists = $stmt->fetchColumn() > 0;

    if ($rowExists) {
        // Update the existing row
        $stmt = $conn->prepare("
            UPDATE Exposure_Plannings_Controls
            SET elimination_control = :elimination_control
            WHERE planning_id = :planning_id
        ");
        $stmt->bindParam(':planning_id', $plan_id, PDO::PARAM_INT);
        $stmt->bindParam(':elimination_control', $elimination_control, PDO::PARAM_STR);
        $stmt->execute();
    } else {
        // Insert a new row if it doesn't exist
        $stmt = $conn->prepare("
            INSERT INTO Exposure_Plannings_Controls (planning_id, elimination_control)
            VALUES (:planning_id, :elimination_control)
        ");
        $stmt->bindParam(':planning_id', $plan_id, PDO::PARAM_INT);
        $stmt->bindParam(':elimination_control', $elimination_control, PDO::PARAM_STR);
        $stmt->execute();
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
