<?php
include '../includes/db.php'; // Assuming this file contains the DB connection
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?page=login_register");
    exit();
}

// Check if material_id is provided
if (isset($_POST['material_id'])) {
    $material_id = intval($_POST['material_id']);

    try {
        // Prepare the statement to get tasks associated with the selected material
        $stmt = $conn->prepare("SELECT id, name FROM Tasks WHERE material_id = :material_id");
        $stmt->bindParam(':material_id', $material_id);
        $stmt->execute();
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Return tasks as JSON
        header('Content-Type: application/json');
        echo json_encode($tasks);

    } catch (PDOException $e) {
        // Handle error
        echo json_encode([]);
    }
} else {
    // No material ID provided
    echo json_encode([]);
}
?>
