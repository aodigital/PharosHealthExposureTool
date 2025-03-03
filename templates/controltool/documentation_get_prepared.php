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




    <h2 class="more_space">Now, we’ll document your exposure control planning</h2>

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
            <li class="progress-step complete">
                <span class="step-title">Residual Exposure Control</span>
            </li>
            <li class="progress-step current">
                <span class="step-title">Documentation</span>
            </li>
            <li class="progress-step">
                <span class="step-title">Conclusion</span>
            </li>
        </ul>
    </div>

     <!-- Informational Content -->
    <div class="documentation-content">
        <p>
            <strong>The most important document</strong> for your exposure controlling planning is your Exposure Control Plan (ECP).
            This document will be used to co-ordinate and communicate what is to be implemented to keep your workers (and everyone
            on the jobsite) protected from RCS dust. Your ECP is also evidence of the steps taken in your exposure control planning and
            will need to be made available to show a WorkSafeBC occupational safety or hygiene officer during a jobsite inspection.
        </p>

        <h3>ECP Distribution</h3>
        <p>
            It is required to share a copy of your ECP with others involved in the planning and implementation of the construction
            project at the jobsite.
        </p>
        <p>
            A printed copy of your ECP Summary should be at the jobsite, at all times. Your complete ECP document can be available
            for viewing at the jobsite as a printed or digital copy. All workers involved must have free access to the ECP. Workers must
            have the opportunity to ask questions and seek clarifications. The employer should ensure that all workers understand the
            information contained in the ECP and have a means to verify the information.
        </p>

        <h3>ECP Review</h3>
        <p>
            Your complete ECP must be reviewed at least annually and updated as needed due to any changes. ECP reviews and updates
            must be done in consultation with the joint health and safety committee and/or management and worker representatives, as
            applicable.
        </p>

        <h3>ECP Record Keeping</h3>
        <p>
            It is required to have a copy of your ECP at your head office for 10 years.
        </p>
        <p>
            In addition to your ECP, you should keep any ECP support documentation and materials. These include (but not necessarily
            limited to):
        </p>
        <ul>
            <li>Respiratory Protection Program</li>
            <li>Inspection Records</li>
            <li>Instruction & Training Records</li>
            <li>Fit-test Records</li>
            <li>Accident/Incident Investigation Reports</li>
            <li>Air Sampling Tests (as may be determined to be required)</li>
        </ul>
        <p>
            Furthermore, the employer must keep on file for at least 10 years all documentation relating to existing monitoring data
            that the employer relies on as evidence of equivalent operations.
        </p>
        <p>
            Documents and materials referable to the ECP should be submitted to the employer's ECP contact for record-keeping
            purposes.
        </p>

        <p>
            We'll now summarize your plan for exposure control. If there are any changes necessary, you will have an opportunity to do
            so before we generate your ECP document.
        </p>
    </div>

    <!-- Navigation Buttons -->
    <div class="clearfix">
        <a href="javascript:void(0)" class="button secondary left load-step" data-step="residual_exposure_control_respirators_ppe">Back</a>
        <a href="javascript:void(0)" class="button save_continue secondary right load-step" data-step="documentation_ecp_summary">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
    </div>

