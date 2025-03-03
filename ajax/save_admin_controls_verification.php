<?php
// save_admin_controls_verification.php

header('Content-Type: application/json');
require_once '../includes/db.php';
session_start();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$plan_id = $_POST['plan_id'] ?? '';
if (!$plan_id || !is_numeric($plan_id)) {
    $response['message'] = 'Invalid plan id.';
    echo json_encode($response);
    exit;
}

$fields = [
    'admin_controls_maintenance' => $_POST['verif_inspections'] ?? '',
    'admin_controls_housekeeping' => $_POST['verif_housekeeping'] ?? '',
    'admin_controls_hygene'       => $_POST['verif_hygiene'] ?? '',
    'admin_controls_training'     => $_POST['verif_training'] ?? '',
    'admin_controls_procedures'   => $_POST['verif_procedures'] ?? '',
    'admin_controls_scheduling'   => $_POST['verif_scheduling'] ?? '',
    'admin_controls_barriers'     => $_POST['verif_barriers'] ?? '',
    'admin_controls_enclosures'   => $_POST['verif_enclosures'] ?? '',
    'admin_controls_maintenance_notes' => $_POST['verif_inspections_notes'] ?? '',
    'admin_controls_housekeeping_notes' => $_POST['verif_housekeeping_notes'] ?? '',
    'admin_controls_hygene_notes'       => $_POST['verif_hygiene_notes'] ?? '',
    'admin_controls_training_notes'     => $_POST['verif_training_notes'] ?? '',
    'admin_controls_procedures_notes'   => $_POST['verif_procedures_notes'] ?? '',
    'admin_controls_scheduling_notes'   => $_POST['verif_scheduling_notes'] ?? '',
    'admin_controls_barriers_notes'     => $_POST['verif_barriers_notes'] ?? '',
    'admin_controls_enclosures_notes'   => $_POST['verif_enclosures_notes'] ?? '',
];

$fileFields = [
    'verif_inspections_file' => 'admin_controls_maintenance_image',
    'verif_housekeeping_file' => 'admin_controls_housekeeping_image',
    'verif_hygiene_file'      => 'admin_controls_hygene_image',
    'verif_training_file'     => 'admin_controls_training_image',
    'verif_procedures_file'   => 'admin_controls_procedures_image',
    'verif_scheduling_file'   => 'admin_controls_scheduling_image',
    'verif_barriers_file'     => 'admin_controls_barriers_image',
    'verif_enclosures_file'   => 'admin_controls_enclosures_image',
];

$uploadDir = realpath(__DIR__ . '/../assets/images/auditor-uploads') . '/';

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        $response['message'] = "Upload directory does not exist and could not be created.";
        echo json_encode($response);
        exit;
    }
}

foreach ($fileFields as $fileInput => $dbField) {
    if (isset($_FILES[$fileInput]) && $_FILES[$fileInput]['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES[$fileInput]['tmp_name'];
        $originalName = basename($_FILES[$fileInput]['name']);
        // Generate a unique file name to avoid conflicts
        $newFileName = time() . '_' . uniqid() . '_' . $originalName;
        $destination = $uploadDir . $newFileName;
        
        if (move_uploaded_file($tmpName, $destination)) {
            $fields[$dbField] = 'assets/images/auditor-uploads/' . $newFileName;
        } else {
            $response['message'] = "Failed to move uploaded file for $fileInput.";
            echo json_encode($response);
            exit;
        }
    }
}

$stmt = $conn->prepare("SELECT id FROM Exposure_Plannings_Verification WHERE plan_id = :plan_id");
$stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $updateParts = [];
    foreach ($fields as $key => $value) {
        $updateParts[] = "$key = :$key";
    }
    $sql = "UPDATE Exposure_Plannings_Verification SET " . implode(', ', $updateParts) . " WHERE plan_id = :plan_id";
    $stmtUpdate = $conn->prepare($sql);
    foreach ($fields as $key => $value) {
        $stmtUpdate->bindValue(":$key", $value);
    }
    $stmtUpdate->bindValue(':plan_id', $plan_id, PDO::PARAM_INT);
    if ($stmtUpdate->execute()) {
        $response['success'] = true;
        $response['message'] = 'Record updated successfully.';
    } else {
        $response['message'] = 'Failed to update record.';
    }
} else {
    $columns = array_keys($fields);
    $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
    $sql = "INSERT INTO Exposure_Plannings_Verification (plan_id, " . implode(', ', $columns) . ") VALUES (:plan_id, " . implode(', ', $placeholders) . ")";
    $stmtInsert = $conn->prepare($sql);
    $stmtInsert->bindValue(':plan_id', $plan_id, PDO::PARAM_INT);
    foreach ($fields as $key => $value) {
        $stmtInsert->bindValue(":$key", $value);
    }
    if ($stmtInsert->execute()) {
        $response['success'] = true;
        $response['message'] = 'Record inserted successfully.';
    } else {
        $response['message'] = 'Failed to insert record.';
    }
}

echo json_encode($response);
?>
