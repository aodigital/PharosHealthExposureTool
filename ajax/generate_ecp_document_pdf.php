<?php
// generate_pdf.php

// Include the Dompdf autoloader (adjust the path as needed)
require_once '../libs/dompdf/autoload.inc.php';
use Dompdf\Dompdf;

// Include the DB connection file (adjust the path as needed)
include '../includes/db.php';

// Set the content type to JSON for the AJAX response.
header('Content-Type: application/json');

// Retrieve parameters from GET or POST
$user   = $_REQUEST['user'] ?? null;
$plan_id = $_REQUEST['plan_id'] ?? null;

if (!$user || !$plan_id) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Missing required parameters: user and/or plan_id.'
    ]);
    exit;
}

// Define the target folder for PDFs relative to this file.
$targetFolder = __DIR__ . '/../assets/documents/ecp_pdf/';
if (!is_dir($targetFolder)) {
    mkdir($targetFolder, 0755, true);
}

// Create the PDF file name.
$pdfFileName = "{$plan_id}_ecp_document.pdf";
$pdfFilePath = $targetFolder . $pdfFileName;

// Capture HTML from your template file. 
// (Do not attempt to call Dompdf methods here.)
ob_start();
include '../templates/ecp_document_template.php';
$html = ob_get_clean();

// Instantiate Dompdf
$dompdf = new Dompdf();

// Enable remote content so that external CSS/JS/images are loaded.
$dompdf->set_option('isRemoteEnabled', true);

// (Optional) Set the base path to your site’s root so relative URLs are resolved.
$dompdf->setBasePath('https://silicatool.pharoshealth.co/');

// Load the HTML into Dompdf.
$dompdf->loadHtml($html);

// Set the paper size to A4 and orientation to portrait.
$dompdf->setPaper('A4', 'portrait');

// Render the HTML as a PDF.
$dompdf->render();

// Get the generated PDF output.
$pdfOutput = $dompdf->output();

// Save the PDF file to the target folder. file_put_contents() will overwrite an existing file.
if (file_put_contents($pdfFilePath, $pdfOutput) === false) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Failed to write PDF file.'
    ]);
    exit;
}

// Now update the database in the Exposure_Plannings_Meta table for this plan_id.
try {
    $updateStmt = $conn->prepare("UPDATE Exposure_Plannings_Meta 
        SET ecp_generated = 1, ecp_download_file = :file 
        WHERE planning_id = :plan_id");
    // Use the relative URL to the document (adjust as needed)
    $downloadFile = '../assets/documents/ecp_pdf/' . $pdfFileName;
    $updateStmt->bindParam(':file', $downloadFile);
    $updateStmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $updateStmt->execute();
} catch (PDOException $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'PDF generated but failed to update database: ' . $e->getMessage()
    ]);
    exit;
}

// Return a success JSON response.
echo json_encode([
    'status'  => 'success',
    'message' => 'PDF generated and database updated successfully.',
    'file'    => $pdfFileName
]);
