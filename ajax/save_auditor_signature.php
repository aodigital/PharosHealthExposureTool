<?php
include '../includes/db.php';
session_start();

// Verify that the user is logged in.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

$user = $_SESSION['user'];
// Assuming the user’s ID is stored as $user['id']
$auditor_id = $user['id'];

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['plan_id']) || !isset($data['signature_data'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
    exit();
}

$plan_id = intval($data['plan_id']);
$signatureData = $data['signature_data'];

// Remove the data URI scheme prefix if present
if (strpos($signatureData, 'data:image/png;base64,') === 0) {
    $signatureData = substr($signatureData, strpos($signatureData, ',') + 1);
}
$signatureData = str_replace(' ', '+', $signatureData);
$imageData = base64_decode($signatureData);

if ($imageData === false) {
    echo json_encode(['success' => false, 'message' => 'Base64 decode failed.']);
    exit();
}

// Create filename using plan_id and current date in MM_DD_YYYY format
$dateString = date('m_d_Y');
$filename = $plan_id . '_' . $dateString . '_auditor.png';

// Set the target directory – ensure the directory is writable by the web server!
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

// Save the relative path (adjusted as requested)
$relativePath = $filename;

try {
    $stmt = $conn->prepare("UPDATE Exposure_Plannings_Verification 
                            SET verification_date = NOW(), 
                                auditor_signature = :auditor_signature, 
                                auditor_id = :auditor_id 
                            WHERE plan_id = :plan_id");
    $stmt->bindParam(':auditor_signature', $relativePath, PDO::PARAM_STR);
    $stmt->bindParam(':auditor_id', $auditor_id, PDO::PARAM_INT);
    $stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt->execute();
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $e->getMessage()]);
    exit();
}

echo json_encode(['success' => true]);
?>
