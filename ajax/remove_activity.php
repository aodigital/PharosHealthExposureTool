<?php
include '../includes/db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

$user = $_SESSION['user'];
$input = json_decode(file_get_contents("php://input"), true);
if (!isset($input['plan_id']) || !isset($input['activity_index'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit();
}

$plan_id = intval($input['plan_id']);
$activity_index = intval($input['activity_index']);

// Verify that the planning belongs to the user.
$stmt = $conn->prepare("SELECT id FROM Exposure_Plannings WHERE id = :plan_id AND user_id = :user_id");
$stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
$stmt->execute();
if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode(['success' => false, 'message' => 'Planning not found or access denied.']);
    exit();
}

// Retrieve current activity CSV fields from Exposure_Plannings_Meta.
$stmtMeta = $conn->prepare("SELECT activity_task, activity_tool, activity_material FROM Exposure_Plannings_Meta WHERE planning_id = :plan_id");
$stmtMeta->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
$stmtMeta->execute();
$meta = $stmtMeta->fetch(PDO::FETCH_ASSOC);
if (!$meta) {
    echo json_encode(['success' => false, 'message' => 'No meta data found for planning.']);
    exit();
}

// Retrieve elimination control CSV from Exposure_Plannings_Controls.
$stmtCtrl = $conn->prepare("SELECT elimination_control FROM Exposure_Plannings_Controls WHERE planning_id = :plan_id");
$stmtCtrl->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
$stmtCtrl->execute();
$ctrl = $stmtCtrl->fetch(PDO::FETCH_ASSOC);
$elimination_control_csv = $ctrl['elimination_control'] ?? '';

// Split CSV strings into arrays.
$activity_tasks = strlen(trim($meta['activity_task'])) ? explode(',', $meta['activity_task']) : [];
$activity_tools = strlen(trim($meta['activity_tool'])) ? explode(',', $meta['activity_tool']) : [];
$activity_materials = strlen(trim($meta['activity_material'])) ? explode(',', $meta['activity_material']) : [];
$elimination_answers = strlen(trim($elimination_control_csv)) ? explode(',', $elimination_control_csv) : [];

// Validate activity_index.
$max = max(count($activity_tasks), count($activity_tools), count($activity_materials));
if ($activity_index < 0 || $activity_index >= $max) {
    echo json_encode(['success' => false, 'message' => 'Invalid activity index.']);
    exit();
}

// Remove the selected activity from each array.
if (isset($activity_tasks[$activity_index])) {
    array_splice($activity_tasks, $activity_index, 1);
}
if (isset($activity_tools[$activity_index])) {
    array_splice($activity_tools, $activity_index, 1);
}
if (isset($activity_materials[$activity_index])) {
    array_splice($activity_materials, $activity_index, 1);
}
if (isset($elimination_answers[$activity_index])) {
    array_splice($elimination_answers, $activity_index, 1);
}

// Rebuild CSV strings.
$new_tasks_csv = implode(',', $activity_tasks);
$new_tools_csv = implode(',', $activity_tools);
$new_materials_csv = implode(',', $activity_materials);
$new_elimination_csv = implode(',', $elimination_answers);

// Update the Exposure_Plannings_Meta table.
$stmtUpdateMeta = $conn->prepare("UPDATE Exposure_Plannings_Meta SET activity_task = :tasks, activity_tool = :tools, activity_material = :materials WHERE planning_id = :plan_id");
$stmtUpdateMeta->bindParam(':tasks', $new_tasks_csv);
$stmtUpdateMeta->bindParam(':tools', $new_tools_csv);
$stmtUpdateMeta->bindParam(':materials', $new_materials_csv);
$stmtUpdateMeta->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
$stmtUpdateMeta->execute();

// Update the Exposure_Plannings_Controls table.
$stmtUpdateCtrl = $conn->prepare("UPDATE Exposure_Plannings_Controls SET elimination_control = :elimination WHERE planning_id = :plan_id");
$stmtUpdateCtrl->bindParam(':elimination', $new_elimination_csv);
$stmtUpdateCtrl->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
$stmtUpdateCtrl->execute();

echo json_encode(['success' => true, 'message' => 'Activity removed successfully.']);
exit();
?>
