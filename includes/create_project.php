<?php
include 'db.php'; // Assuming this file contains the DB connection
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?page=login_register");
    exit();
}

$user = $_SESSION['user'];

// Debug: Check if the user ID exists in the session data
if (!isset($user['id']) || empty($user['id'])) {
    echo "Error: User ID is not set or is empty.";
    var_dump($_SESSION);
    exit();
}

$user_id = $user['id'];

try {
    // Insert a new planning entry into the Exposure_Plannings table
    $stmt = $conn->prepare("INSERT INTO Exposure_Plannings (user_id, created_at) VALUES (:user_id, NOW())");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Get the newly generated plan ID
    $new_plan_id = $conn->lastInsertId();

    // Insert a corresponding entry in the Exposure_Planning_Meta table
    $stmt_meta = $conn->prepare("INSERT INTO Exposure_Plannings_Meta (planning_id) VALUES (:planning_id)");
    $stmt_meta->bindParam(':planning_id', $new_plan_id, PDO::PARAM_INT);
    $stmt_meta->execute();

    // Insert a corresponding entry in the Exposure_Planning Controls table
    $stmt_meta = $conn->prepare("INSERT INTO Exposure_Plannings_Controls (planning_id) VALUES (:planning_id)");
    $stmt_meta->bindParam(':planning_id', $new_plan_id, PDO::PARAM_INT);
    $stmt_meta->execute();

    // Insert a corresponding entry in the Exposure_Planning Verification table
    $stmt_meta = $conn->prepare("INSERT INTO Exposure_Plannings_Verification (plan_id) VALUES (:planning_id)");
    $stmt_meta->bindParam(':planning_id', $new_plan_id, PDO::PARAM_INT);
    $stmt_meta->execute();

    // Redirect to the exposure planning page with the new plan ID
    header("Location: ../index.php?page=exposure_planning&plan_id=" . $new_plan_id);
    exit();

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
