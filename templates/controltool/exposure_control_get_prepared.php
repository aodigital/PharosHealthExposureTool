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

try {
    // Fetch exposure planning data
    $stmt = $conn->prepare("SELECT * FROM Exposure_Plannings WHERE id = :plan_id AND user_id = :user_id");
    $stmt->bindParam(':plan_id', $plan_id);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $exposure_planning = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exposure_planning) {
        echo "Error: No exposure planning found.";
        exit();
    }

    // Fetch meta data
    $stmt_meta = $conn->prepare("SELECT * FROM Exposure_Plannings_Meta WHERE planning_id = :plan_id");
    $stmt_meta->bindParam(':plan_id', $plan_id);
    $stmt_meta->execute();
    $meta_data = $stmt_meta->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<h2 class="more_space">Now, we’ll control the risk</h2>

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
        <li class="progress-step current">
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
    
    <p>Identify the appropriate controls required to eliminate or minimize the RCS dust exposure. The controls should follow the <strong>Hierarchy of Controls</strong>.</p>

    <p><strong>The next step</strong> is to identify the appropriate controls required to eliminate or minimize the RCS dust exposure based on the <a href="#">Hierarchy of Controls</a>.</p>
    <ul>
        <li><a href="#">Elimination & Substitution</a></li>
        <li><a href="#">Engineering Controls</a></li>
        <li><a href="#">Administrative Controls</a></li>
        <li><a href="#">Personal Protective Equipment (PPE)</a></li>
    </ul>

    <div class="clearfix">
        <a href="javascript:void(0)" class="button secondary left load-step" data-step="silica_exposure_no_controls_exposure_analysis">Back</a>
        <a href="javascript:void(0)" class="button save_continue secondary right load-step" data-step="exposure_control_risk_elimination_substitution">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
    </div>

