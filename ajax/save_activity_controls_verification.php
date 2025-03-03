<?php
// save_activity_controls_verification.php

header('Content-Type: application/json');
require_once '../includes/db.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}
$user = $_SESSION['user'];

$plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
if (!$plan_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid plan id.']);
    exit();
}

$activity_engineering_controls       = $_POST['activity_engineering_controls'] ?? '';
$activity_engineering_controls_notes = $_POST['activity_engineering_controls_notes'] ?? '';
$activity_admin_controls             = $_POST['activity_admin_controls'] ?? '';
$activity_admin_controls_notes       = $_POST['activity_admin_controls_notes'] ?? '';

$uploadDir = realpath(__DIR__ . '/../assets/images/auditor-uploads') . '/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        echo json_encode(['success' => false, 'message' => 'Upload directory does not exist and could not be created.']);
        exit;
    }
}

function processFileUploadsIndexed($inputName, $uploadDir) {
    $paths = [];
    if(isset($_FILES[$inputName])) {
        foreach($_FILES[$inputName]['name'] as $index => $name) {
            if($_FILES[$inputName]['error'][$index] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES[$inputName]['tmp_name'][$index];
                $originalName = basename($_FILES[$inputName]['name'][$index]);
                $newFileName = time() . '_' . uniqid() . '_' . $originalName;
                $destination = $uploadDir . $newFileName;
                if(move_uploaded_file($tmpName, $destination)) {
                    $paths[$index] = 'assets/images/auditor-uploads/' . $newFileName;
                }
            }
        }
    }
    return $paths; // returns an associative array: index => file path
}

$new_eng_images   = processFileUploadsIndexed('verif_eng_file', $uploadDir);
$new_admin_images = processFileUploadsIndexed('verif_admin_file', $uploadDir);

$stmt = $conn->prepare("SELECT id, activity_engineering_controls_image, activity_admin_controls_image FROM Exposure_Plannings_Verification WHERE plan_id = :plan_id");
$stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
$stmt->execute();
$existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
$engControlsArray = explode(",", $activity_engineering_controls);
$activityCount = count($engControlsArray);

$existing_eng_images   = ($existingRecord && !empty($existingRecord['activity_engineering_controls_image']))
                          ? explode(",", $existingRecord['activity_engineering_controls_image'])
                          : [];
$existing_admin_images = ($existingRecord && !empty($existingRecord['activity_admin_controls_image']))
                          ? explode(",", $existingRecord['activity_admin_controls_image'])
                          : [];

$final_eng_images = [];
$final_admin_images = [];
for($i = 0; $i < $activityCount; $i++){
    if(isset($new_eng_images[$i]) && !empty($new_eng_images[$i])){
        $final_eng_images[$i] = $new_eng_images[$i];
    } elseif(isset($existing_eng_images[$i]) && !empty($existing_eng_images[$i])){
        $final_eng_images[$i] = $existing_eng_images[$i];
    } else {
        $final_eng_images[$i] = ""; // or you could skip adding it.
    }
    if(isset($new_admin_images[$i]) && !empty($new_admin_images[$i])){
        $final_admin_images[$i] = $new_admin_images[$i];
    } elseif(isset($existing_admin_images[$i]) && !empty($existing_admin_images[$i])){
        $final_admin_images[$i] = $existing_admin_images[$i];
    } else {
        $final_admin_images[$i] = "";
    }
}

$final_eng_images_str   = implode(",", $final_eng_images);
$final_admin_images_str = implode(",", $final_admin_images);

$fields = [
    'activity_engineering_controls'       => $activity_engineering_controls,
    'activity_engineering_controls_notes' => $activity_engineering_controls_notes,
    'activity_engineering_controls_image' => $final_eng_images_str,
    'activity_admin_controls'             => $activity_admin_controls,
    'activity_admin_controls_notes'       => $activity_admin_controls_notes,
    'activity_admin_controls_image'       => $final_admin_images_str,
];

if($existingRecord){
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
        echo json_encode(['success' => true, 'message' => 'Record updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update record.']);
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
        echo json_encode(['success' => true, 'message' => 'Record inserted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to insert record.']);
    }
}
?>
