<?php
include '../includes/db.php';
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Retrieve POST data
$plan_id = $_POST['plan_id'] ?? null;
$work_area = $_POST['work_area'] ?? null;
$avg_hr_per_shift = $_POST['avg_hr_per_shift'] ?? null;

// Log received data for debugging
error_log("Received data: Plan ID = $plan_id, Work Area = $work_area, Duration = $avg_hr_per_shift");

// Validate required fields
if (!$plan_id || $work_area === null || $avg_hr_per_shift === null) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required data']);
    exit();
}

try {
    // Update database
    $stmt = $conn->prepare("
        UPDATE Exposure_Plannings_Meta
        SET work_area = :work_area, avg_hr_per_shift = :avg_hr_per_shift
        WHERE planning_id = :plan_id
    ");
    $stmt->bindParam(':work_area', $work_area);
    $stmt->bindParam(':avg_hr_per_shift', $avg_hr_per_shift);
    $stmt->bindParam(':plan_id', $plan_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Data saved successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update data']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
