<?php
include '../includes/db.php'; // Assuming this file contains the DB connection
session_start();

if (!isset($_SESSION['user'])) {
    echo "Error: User not logged in.";
    exit();
}

$step = isset($_POST['step']) ? $_POST['step'] : null;
$plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : null;

if (!$step || !$plan_id) {
    echo "Error: Missing parameters.";
    exit();
}

$user = $_SESSION['user'];

// Update the current step in the database
try {
    $stmt = $conn->prepare("UPDATE Exposure_Plannings SET current_step = :step WHERE id = :plan_id AND user_id = :user_id");
    $stmt->bindParam(':step', $step);
    $stmt->bindParam(':plan_id', $plan_id);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();

    echo "Step updated successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
