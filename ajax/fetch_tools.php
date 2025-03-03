<?php
include '../includes/db.php'; // Assuming this file contains the DB connection

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : null;

    if ($task_id) {
        try {
            $stmt = $conn->prepare("SELECT * FROM Tools WHERE task_id = :task_id");
            $stmt->bindParam(':task_id', $task_id);
            $stmt->execute();
            $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($tools);
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'No valid task ID provided.']);
    }
} else {
    echo json_encode(['error' => 'Invalid request method.']);
}
?>
