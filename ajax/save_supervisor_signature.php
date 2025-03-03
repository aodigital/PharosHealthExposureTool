<?php
include '../includes/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['plan_id']) || !isset($data['signature_data'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
    exit();
}

$plan_id = intval($data['plan_id']);
$signatureData = $data['signature_data'];

if (strpos($signatureData, 'data:image/png;base64,') === 0) {
    $signatureData = substr($signatureData, strpos($signatureData, ',') + 1);
}
$signatureData = str_replace(' ', '+', $signatureData);
$imageData = base64_decode($signatureData);

if ($imageData === false) {
    echo json_encode(['success' => false, 'message' => 'Base64 decode failed.']);
    exit();
}

$dateString = date('m_d_Y');
$filename = $plan_id . '_' . $dateString . '_creator.png';
$targetDir = '/var/www/silicatool.pharoshealth.co/public/assets/images/signatures/';

if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create target directory.']);
        exit();
    }
}

$targetPath = $targetDir . $filename;

$result = file_put_contents($targetPath, $imageData);
if ($result === false) {
    $error = error_get_last();
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to write image file. ' . ($error['message'] ?? '')
    ]);
    exit();
}

$savedFilename = $filename;

try {
    $stmt = $conn->prepare("UPDATE Exposure_Plannings_Meta SET signing_date = NOW(), creator_signature = :creator_signature WHERE planning_id = :plan_id");
    $stmt->bindParam(':creator_signature', $savedFilename, PDO::PARAM_STR);
    $stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt->execute();
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $e->getMessage()]);
    exit();
}

echo json_encode(['success' => true]);
?>
