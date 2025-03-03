<?php
include '../includes/db.php'; // Assuming this file contains the DB connection
session_start();

if (!isset($_SESSION['user'])) {
    echo "Error: User not logged in.";
    exit();
}

// Get the user and planning ID
$user = $_SESSION['user'];
$planning_id = isset($_POST['planning_id']) ? intval($_POST['planning_id']) : null;

if (!$planning_id) {
    echo "Error: No valid planning ID provided.";
    exit();
}

// Prepare data from POST request
$jobsite_name = $_POST['jobsite_name'] ?? '';
$jobsite_address = $_POST['jobsite_address'] ?? '';
$jobsite_city = $_POST['jobsite_city'] ?? '';
$jobsite_region = $_POST['jobsite_region'] ?? '';
$jobsite_post_code = $_POST['jobsite_post_code'] ?? '';
$jobsite_type = $_POST['jobsite_type'] ?? '';
$project_type = $_POST['project_type'] ?? '';
$project_start_date = $_POST['project_start_date'] ?? null;
$project_end_date = $_POST['project_end_date'] ?? null;
$jobsite_shift_hours = $_POST['jobsite_shift_hours'] ?? '';  // New field

try {
    // Update Exposure_Plannings table for jobsite name
    $stmt = $conn->prepare("
        UPDATE Exposure_Plannings 
        SET 
            jobsite_name = :jobsite_name
        WHERE id = :planning_id AND user_id = :user_id
    ");
    $stmt->bindParam(':planning_id', $planning_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
    $stmt->bindParam(':jobsite_name', $jobsite_name);
    $stmt->execute();

    // Update Exposure_Plannings_Meta table for remaining jobsite details
    $stmt_meta = $conn->prepare("
        UPDATE Exposure_Plannings_Meta
        SET
            jobsite_address = :jobsite_address,
            jobsite_city = :jobsite_city,
            jobsite_region = :jobsite_region,
            jobsite_post_code = :jobsite_post_code,
            jobsite_type = :jobsite_type,
            project_type = :project_type,
            project_start_date = :project_start_date,
            project_end_date = :project_end_date,
            jobsite_shift_hours = :jobsite_shift_hours
        WHERE planning_id = :planning_id
    ");

    $stmt_meta->bindParam(':planning_id', $planning_id, PDO::PARAM_INT);
    $stmt_meta->bindParam(':jobsite_address', $jobsite_address);
    $stmt_meta->bindParam(':jobsite_city', $jobsite_city);
    $stmt_meta->bindParam(':jobsite_region', $jobsite_region);
    $stmt_meta->bindParam(':jobsite_post_code', $jobsite_post_code);
    $stmt_meta->bindParam(':jobsite_type', $jobsite_type);
    $stmt_meta->bindParam(':project_type', $project_type);
    $stmt_meta->bindParam(':project_start_date', $project_start_date);
    $stmt_meta->bindParam(':project_end_date', $project_end_date);
    $stmt_meta->bindParam(':jobsite_shift_hours', $jobsite_shift_hours);
    $stmt_meta->execute();

    echo "Data saved successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
