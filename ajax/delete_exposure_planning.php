<?php
    include '../includes/db.php'; // Assuming this file contains the DB connection
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (isset($data['id']) && is_numeric($data['id'])) {
            $planningId = intval($data['id']);

            try {
                // Begin transaction
                $conn->beginTransaction();

                // Delete from Exposure_Plannings_Verification (column is "plan_id")
                $stmt_verif = $conn->prepare("DELETE FROM Exposure_Plannings_Verification WHERE plan_id = :plan_id");
                $stmt_verif->bindParam(':plan_id', $planningId, PDO::PARAM_INT);
                $stmt_verif->execute();

                // Delete from Exposure_Plannings_Controls (column is "planning_id")
                $stmt_controls = $conn->prepare("DELETE FROM Exposure_Plannings_Controls WHERE planning_id = :planning_id");
                $stmt_controls->bindParam(':planning_id', $planningId, PDO::PARAM_INT);
                $stmt_controls->execute();

                // Delete from Exposure_Plannings_Meta (column is "planning_id")
                $stmt_meta = $conn->prepare("DELETE FROM Exposure_Plannings_Meta WHERE planning_id = :planning_id");
                $stmt_meta->bindParam(':planning_id', $planningId, PDO::PARAM_INT);
                $stmt_meta->execute();

                // Delete from Exposure_Plannings (column is "id")
                $stmt_planning = $conn->prepare("DELETE FROM Exposure_Plannings WHERE id = :id");
                $stmt_planning->bindParam(':id', $planningId, PDO::PARAM_INT);
                $stmt_planning->execute();

                // Commit transaction
                $conn->commit();

                echo json_encode(['success' => true]);
                exit();
            } catch (PDOException $e) {
                // Rollback transaction on failure
                $conn->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit();
            }
        }
    }

    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
?>
