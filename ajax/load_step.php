<?php
include '../includes/db.php'; // Assuming this file contains the DB connection
session_start();

if (!isset($_SESSION['user'])) {
    echo "Error: User not logged in.";
    exit();
}

$step = isset($_GET['step']) ? $_GET['step'] : null;
$plan_id = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : null;

if (!$step || !$plan_id) {
    echo "Error: Missing parameters. Step: " . htmlspecialchars($step) . ", Plan ID: " . htmlspecialchars($plan_id);
    exit();
}

switch ($step) {
    case 'welcome':
        include '../templates/controltool/welcome.php';
        break;
    case 'get_prepared':
        include '../templates/controltool/get_prepared.php';
        break;
    case 'employer_details':
        include '../templates/controltool/employer_details.php';
        break;
    case 'silica_process_get_prepared':
        include '../templates/controltool/silica_process_get_prepared.php';
        break;
    case 'silica_process_jobsite_details':
        include '../templates/controltool/silica_process_jobsite_details.php';
        break;
    case 'silica_process_work_activity':
        include '../templates/controltool/silica_process_work_activity.php';
        break;
    case 'silica_process_work_area_duration':
        include '../templates/controltool/silica_process_work_area_duration.php';
        break;
    case 'silica_process_silica_process_summary':
        include '../templates/controltool/silica_process_silica_process_summary.php';
        break;
    case 'silica_exposure_no_controls_get_prepared':
        include '../templates/controltool/silica_exposure_no_controls_get_prepared.php';
        break;
    case 'silica_exposure_no_controls_exposure_analysis':
        include '../templates/controltool/silica_exposure_no_controls_exposure_analysis.php';
        break;
    case 'exposure_control_get_prepared':
        include '../templates/controltool/exposure_control_get_prepared.php';
        break;
    case 'exposure_control_risk_elimination_substitution':
        include '../templates/controltool/exposure_control_risk_elimination_substitution.php';
        break;
    case 'exposure_control_engineering_controls':
        include '../templates/controltool/exposure_control_engineering_controls.php';
        break;
    case 'exposure_control_administrative_controls':
        include '../templates/controltool/exposure_control_administrative_controls.php';
        break;
    case 'exposure_control_exposure_control_summary':
        include '../templates/controltool/exposure_control_exposure_control_summary.php';
        break;
    case 'silica_exposure_with_controls_get_prepared':
        include '../templates/controltool/silica_exposure_with_controls_get_prepared.php';
        break;
    case 'silica_exposure_with_controls_exposure_analysis_with_controls':
        include '../templates/controltool/silica_exposure_with_controls_exposure_analysis_with_controls.php';
        break;
    case 'residual_exposure_control_get_prepared':
        include '../templates/controltool/residual_exposure_control_get_prepared.php';
        break;
    case 'residual_exposure_control_respirators_ppe':
        include '../templates/controltool/residual_exposure_control_respirators_ppe.php';
        break;
    case 'documentation_get_prepared':
        include '../templates/controltool/documentation_get_prepared.php';
        break;
    case 'documentation_ecp_summary':
        include '../templates/controltool/documentation_ecp_summary.php';
        break;
    case 'documentation_generate_ecp':
        include '../templates/controltool/documentation_generate_ecp.php';
        break;
    case 'completed_ecp':
        include '../templates/controltool/completed_ecp.php';
        break;
    default:
        echo "Error: Unknown step.";
        break;
}

?>
