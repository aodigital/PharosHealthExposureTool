<?php
include '../includes/db.php'; // DB connection
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$user = $_SESSION['user'] ?? null;
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

if (!isset($_POST['plan_id']) || !isset($_POST['activity_count'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit();
}

$plan_id = intval($_POST['plan_id']);
$activity_count = intval($_POST['activity_count']);

$engineering_controls = $_POST['engineering_controls'] ?? '';
$engineering_controls_details = $_POST['engineering_controls_details'] ?? '';

try {
    $stmt = $conn->prepare("SELECT engineering_controls_image, engineering_controls_notes 
                            FROM Exposure_Plannings_Controls 
                            WHERE planning_id = :planning_id");
    $stmt->bindParam(':planning_id', $plan_id, PDO::PARAM_INT);
    $stmt->execute();
    $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
    exit();
}

$oldImagesCSV = $oldData['engineering_controls_image'] ?? '';
$oldNotesCSV = $oldData['engineering_controls_notes'] ?? '';
$oldImagesArray = !empty(trim($oldImagesCSV)) ? explode(',', $oldImagesCSV) : array_fill(0, $activity_count, '');
$oldNotesArray = !empty(trim($oldNotesCSV)) ? explode(',', $oldNotesCSV) : array_fill(0, $activity_count, '');

$uploadDir = '/var/www/silicatool.pharoshealth.co/public/assets/uploads/images/';

$newImagesArray = [];
$newNotesArray = [];

for ($i = 0; $i < $activity_count; $i++) {
    $fileKey = "engineering_control_upload_$i";
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES[$fileKey]['tmp_name'];
        $originalName = $_FILES[$fileKey]['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $newFileName = "engineering_control_{$i}_{$plan_id}." . $extension;
        $destination = $uploadDir . $newFileName;
        if (move_uploaded_file($tmpName, $destination)) {
            $newImagesArray[$i] = $newFileName;
        } else {
            $newImagesArray[$i] = $oldImagesArray[$i] ?? '';
        }
    } else {
        $newImagesArray[$i] = $oldImagesArray[$i] ?? '';
    }

    $noteKey = "engineering_control_note_$i";
    if (isset($_POST[$noteKey]) && trim($_POST[$noteKey]) !== '') {
        $newNotesArray[$i] = trim($_POST[$noteKey]);
    } else {
        $newNotesArray[$i] = $oldNotesArray[$i] ?? '';
    }
}

$newImagesCSV = implode(',', $newImagesArray);
$newNotesCSV = implode(',', $newNotesArray);

try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM Exposure_Plannings_Controls WHERE planning_id = :planning_id");
    $stmt->bindParam(':planning_id', $plan_id, PDO::PARAM_INT);
    $stmt->execute();
    $rowExists = $stmt->fetchColumn() > 0;

    if ($rowExists) {
        $stmt = $conn->prepare("
            UPDATE Exposure_Plannings_Controls
            SET engineering_controls = :engineering_controls, 
                engineering_controls_details = :engineering_controls_details,
                engineering_controls_image = :engineering_controls_image,
                engineering_controls_notes = :engineering_controls_notes
            WHERE planning_id = :planning_id
        ");
    } else {
        $stmt = $conn->prepare("
            INSERT INTO Exposure_Plannings_Controls
            (planning_id, engineering_controls, engineering_controls_details, engineering_controls_image, engineering_controls_notes)
            VALUES (:planning_id, :engineering_controls, :engineering_controls_details, :engineering_controls_image, :engineering_controls_notes)
        ");
    }
    $stmt->bindParam(':planning_id', $plan_id, PDO::PARAM_INT);
    $stmt->bindParam(':engineering_controls', $engineering_controls, PDO::PARAM_STR);
    $stmt->bindParam(':engineering_controls_details', $engineering_controls_details, PDO::PARAM_STR);
    $stmt->bindParam(':engineering_controls_image', $newImagesCSV, PDO::PARAM_STR);
    $stmt->bindParam(':engineering_controls_notes', $newNotesCSV, PDO::PARAM_STR);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
