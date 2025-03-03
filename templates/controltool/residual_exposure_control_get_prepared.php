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

<h2 class="more_space">Now, we’ll control the remaining risk</h2>

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
        <li class="progress-step complete">
            <span class="step-title">Silica Exposure (With Controls)</span>
        </li>
        <li class="progress-step current">
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
    
    <p>
        The last level of control available to protect workers from RCS dust is Personal Protective Equipment (PPE).
        Up until now, the controls have been focused on prevention and proactively controlling exposure risks through
        systems and strategies aimed at minimizing dust and separating workers from emission sources. PPE is a reactive
        control. The purpose of PPE is to protect workers in environments where hazards are present and cannot be avoided
        through proactive efforts.
    </p>
    <p>
        The remaining exposure level helps determine the appropriate respirator type and other PPE to protect workers.
        In general, the higher the remaining exposure level, the more significant (and cumbersome to workers) PPE becomes.
        PPE can be uncomfortable for workers and can make it more difficult to perform work activities.
    </p>
    <p>
        In addition to the remaining exposure level, there are certain PPE that are typical for certain work activities
        and work environments. These include worker decontamination clothing and equipment.
    </p>
    <p>We'll now present the recommended PPE.</p>
    
    <div class="clearfix">
        <a href="javascript:void(0)" class="button secondary left load-step" data-step="silica_exposure_with_controls_exposure_analysis_with_controls">Back</a>
        <a href="javascript:void(0)" class="button save_continue secondary right load-step" data-step="residual_exposure_control_respirators_ppe">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
    </div>

