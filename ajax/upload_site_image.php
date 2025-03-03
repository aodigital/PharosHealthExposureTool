<?php
include '../includes/db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

$user = $_SESSION['user'];
$planning_id = isset($_POST['planning_id']) ? intval($_POST['planning_id']) : null;
if (!$planning_id) {
    echo json_encode(['success' => false, 'message' => 'No valid planning ID provided.']);
    exit();
}

if (!isset($_FILES['jobsite_image']) || $_FILES['jobsite_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit();
}

$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
$originalName = $_FILES['jobsite_image']['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowed_extensions)]);
    exit();
}

$newFilename = 'site_image_planning_' . $planning_id . '.' . $ext;
$destination = '../assets/uploads/images/' . $newFilename;

if (move_uploaded_file($_FILES['jobsite_image']['tmp_name'], $destination)) {
    // Update the database with the new filename
    $stmt = $conn->prepare("UPDATE Exposure_Plannings_Meta SET jobsite_image = :jobsite_image WHERE planning_id = :planning_id");
    $stmt->bindParam(':jobsite_image', $newFilename);
    $stmt->bindParam(':planning_id', $planning_id, PDO::PARAM_INT);
    if ($stmt->execute()) {
         echo json_encode(['success' => true, 'filename' => $newFilename]);
    } else {
         echo json_encode(['success' => false, 'message' => 'File uploaded but failed to update DB.']);
    }
    exit();
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
    exit();
}
?>
