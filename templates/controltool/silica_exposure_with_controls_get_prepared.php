<?php
include '../includes/db.php'; // Assuming this file contains the DB connection
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?page=login_register");
    exit();
}

$user = $_SESSION['user'];
$plan_id = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : null;

if (!$plan_id) {
    echo "Error: No valid plan ID provided.";
    exit();
}
?>

    <h2 class="more_space">Silica Exposure with Controls: Get Prepared</h2>
    

<div class="progress-container">
    <ul class="progress-timeline">
        <li class="progress-step complete">
            <span class="step-title">Introduction</span>
        </li>
        <li class="progress-step complete">
            <span class="step-title">Silica Process</span>
        </li>
        <li class="progress-step complete">
            <span class="step-title">Silica Exposure (No Controls)</span>
        </li>
        <li class="progress-step complete">
            <span class="step-title">Exposure Control</span>
        </li>
        <li class="progress-step current">
            <span class="step-title">Silica Exposure (With Controls)</span>
        </li>
        <li class="progress-step">
            <span class="step-title">Residual Exposure Control</span>
        </li>
        <li class="progress-step">
            <span class="step-title">Documentation</span>
        </li>
        <li class="progress-step">
            <span class="step-title">Conclusion</span>
        </li>
    </ul>
</div>
<p>We’ll now analyze the silica exposure level after applying controls. This is an important step to ensure compliance with exposure limits and safety standards.</p>
<p>Applying controls can reduce silica exposure levels significantly. Let’s calculate the new exposure level and determine compliance with the exposure limit.</p>

<p><strong>Note:</strong> This process may take a moment as calculations are performed.</p>

<div class="clearfix">
        <a href="javascript:void(0)" class="button secondary left load-step" data-step="exposure_control_exposure_control_summary">Back</a>
        <a href="javascript:void(0)" class="button save_continue secondary right load-step" data-step="silica_exposure_with_controls_exposure_analysis_with_controls">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
</div>

