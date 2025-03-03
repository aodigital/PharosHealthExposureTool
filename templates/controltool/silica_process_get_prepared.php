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

<h2 class="more_space">Silica Process - Get Prepared</h2>

<div class="progress-container">
    <ul class="progress-timeline">
        <li class="progress-step complete">
            <span class="step-title">Introduction</span>
        </li>
        <li class="progress-step current">
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


    <p>A <strong>silica process</strong> is a process (in this case, a work activity under certain conditions) that results in the release of RCS dust in concentrations likely to exceed the <strong>exposure limit</strong>.</p>

    <p>In addition to the nature of the work activity itself, the scope and circumstances of the work activity (such as the jobsite characteristics, work area environment, and average work shift duration) can also play a role in determining the amount of airborne RCS dust likely to be present.</p>

    <p>We'll now gather the information needed to identify the potential risk, starting with your jobsite details.</p>

    <div class="clearfix">
        <a href="javascript:void(0)" class="button secondary left load-step" data-step="employer_details">Back</a>
        <a href="javascript:void(0)" class="button save_continue secondary right load-step" data-step="silica_process_jobsite_details">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
    </div>

