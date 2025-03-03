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

// Ensure the exposure planning exists
try {
    $stmt = $conn->prepare("SELECT * FROM Exposure_Plannings WHERE id = :plan_id AND user_id = :user_id");
    $stmt->bindParam(':plan_id', $plan_id);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $exposure_planning = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exposure_planning) {
        echo "Error: No exposure planning found.";
        exit();
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<h2 class="more_space">Now, we'll calculate the Silica Exposure (No Controls)</h2>

<div class="progress-container">
    <ul class="progress-timeline">
        <li class="progress-step complete">
            <span class="step-title">Introduction</span>
        </li>
        <li class="progress-step complete">
            <span class="step-title">Silica Process</span>
        </li>
        <li class="progress-step current">
            <span class="step-title">Silica Exposure (No Controls)</span>
        </li>
        <li class="progress-step">
            <span class="step-title">Exposure Control</span>
        </li>
        <li class="progress-step">
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
    
    <p><strong>The next step</strong> is to estimate the silica process' <a class="exposure_level" href="#">exposure level</a>, and determine where this level falls in relation to the <a class="exposure_limit" href="#">exposure limit</a>. If the exposure level is <em>above</em> the exposure limit of 0.025 mg/m<sup>3</sup>, then the silica process must be controlled, and an ECP must be developed and implemented to address the risk.</p>

    <p>We'll now perform the calculations and present the results.</p>

    <p><strong>NOTE:</strong> This may take up to a minute to calculate.</p>

    <hr>

    <div class="clearfix">
        <a href="javascript:void(0)" class="button secondary left load-step" data-step="silica_process_silica_process_summary">Back</a>
        <a href="javascript:void(0)" class="button save_continue secondary right load-step" data-step="silica_exposure_no_controls_exposure_analysis">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
    </div>

