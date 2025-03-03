<?php
// save_pdf.php

// Include Dompdf's autoloader from the libs folder (adjust the path if needed)
require_once __DIR__ . '../libs/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Set up Dompdf options
$options = new Options();
$options->set('isRemoteEnabled', true);  // Allows loading external stylesheets and images

$dompdf = new Dompdf($options);

// Retrieve the plan id (for example, via GET)
if (!isset($_GET['plan_id'])) {
    die('No plan id provided.');
}
$plan_id = intval($_GET['plan_id']);

// Here you need to generate the HTML content you wish to convert.
// One approach is to create a template file (e.g. ecp_pdf_template.php) that outputs the HTML you want.
// Use output buffering to capture that output:
ob_start();
include 'ecp_pdf_template.php';  // This file should output a complete HTML document
$html = ob_get_clean();

// Load HTML content into Dompdf
$dompdf->loadHtml($html);

// Set paper size and orientation (optional)
$dompdf->setPaper('A4', 'portrait');

// Render the PDF
$dompdf->render();

// Output the generated PDF as a download
$dompdf->stream("ECP_{$plan_id}.pdf", ["Attachment" => true]);
?>
