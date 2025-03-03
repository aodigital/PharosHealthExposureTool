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

// Fetch the current exposure planning data using the $plan_id
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

<h2 class="more_space">Get Prepared</h2>

<div class="progress-container">
    <ul class="progress-timeline">
        <li class="progress-step current">
            <span class="step-title">Introduction</span>
        </li>
        <li class="progress-step">
            <span class="step-title">Silica Process</span>
        </li>
        <li class="progress-step">
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



    <p>In this step, we will help you gather all the information you need to effectively begin the exposure control planning process.</p>

    <h3 class="more_space">What You Need to Prepare:</h3>
    <ul class="number_list">
        <li>Review existing company policies and procedures that may impact exposure control.</li>
        <li>Gather information on the jobsite, including the location and any known risks.</li>
        <li>Collect details on the materials or processes that may create potential silica exposure.</li>
        <li>Identify any workers who will be involved and their respective roles.</li>
        <li>Note down any existing safety measures or controls currently in place.</li>
    </ul>

    <p>Once you have gathered this information, you can proceed to the next step, where we will assist you in providing the necessary company details.</p>

    <div class="clearfix">
        <a href="javascript:void(0)" class="button secondary left load-step" data-step="welcome">Back</a>
        <a href="javascript:void(0)" class="button save_continue secondary right load-step" data-step="employer_details">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
    </div>