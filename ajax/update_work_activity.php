<?php
include '../includes/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = $_SESSION['user'] ?? null;
    $planning_id = isset($_POST['planning_id']) ? intval($_POST['planning_id']) : null;
    $activities = isset($_POST['activities']) ? json_decode($_POST['activities'], true) : [];

    if (!$user || !$planning_id || empty($activities)) {
        echo json_encode(['error' => 'Invalid user, planning ID, or activities.']);
        exit();
    }

    try {
        // Prepare data to save as comma-separated strings
        $activity_materials = [];
        $activity_tasks = [];
        $activity_tools = [];

        foreach ($activities as $activity) {
            $activity_materials[] = $activity['material'];
            $activity_tasks[] = $activity['task'];
            $activity_tools[] = $activity['tool'];
        }

        $materials_string = implode(',', $activity_materials);
        $tasks_string = implode(',', $activity_tasks);
        $tools_string = implode(',', $activity_tools);

        // Update the database
        $stmt = $conn->prepare("
            UPDATE Exposure_Plannings_Meta 
            SET activity_material = :materials, 
                activity_task = :tasks, 
                activity_tool = :tools 
            WHERE planning_id = :planning_id
        ");
        $stmt->bindParam(':materials', $materials_string);
        $stmt->bindParam(':tasks', $tasks_string);
        $stmt->bindParam(':tools', $tools_string);
        $stmt->bindParam(':planning_id', $planning_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to update activities.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}


?>
