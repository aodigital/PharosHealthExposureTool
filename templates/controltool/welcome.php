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
    // Fetch the exposure planning, allowing access if user is the owner OR has the 'admin' or 'auditor' role
    $stmt = $conn->prepare("
        SELECT * FROM Exposure_Plannings 
        WHERE id = :plan_id 
        AND (user_id = :user_id OR :is_admin_auditor = 1)
    ");
    $is_admin_auditor = ($user['role'] === 'admin' || $user['role'] === 'auditor') ? 1 : 0;

    $stmt->bindParam(':plan_id', $plan_id);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->bindParam(':is_admin_auditor', $is_admin_auditor, PDO::PARAM_INT);

    $stmt->execute();
    $exposure_planning = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exposure_planning) {
        echo "Error: You do not have permission to access this exposure planning.";
        exit();
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

?>

    <h2 class="more_space">Welcome to the Nextrack Silica Control Tool<sup><small class="black">TM</small></sup></h2>

    <p>The purpose of the Nextrack Silica Control Tool<sup><small class="black">TM</small></sup> is to help you protect your workers from hazardous silica dust exposures. As you input information about your planned silica process into the Tool, it will provide you with an exposure level relating to the process, calculate the effect of selected controls on the exposure level, and finally, provide you with an Exposure Control Plan that you can use at the worksite.</p>

    <p>For a quick guide on how to use this Tool, see <a class="tool_orientation" href="#">Nextrack Silica Control Tool<sup><small>TM</small></sup> Orientation</a>.</p>

    <h3 class="more_space">Exposure Health Risks</h3>
    <p>Many work activities that create dust can expose workers to high levels of <a class="rcs_dust" href="#">RCS dust</a>. Breathing in this fine dust can cause serious lung diseases such as silicosis, lung cancer, pulmonary tuberculosis, and chronic pulmonary disease. Exposures may also be related to the development of autoimmune disorders, chronic renal diseases, and other adverse health effects.</p>

    <p>Acute silicosis can occur just weeks or months after a high exposure, and can be fatal. The other delayed health effects can appear years later.</p>

    <p>Each year, more workers in UK workplaces are exposed to RCS dust than to asbestos or lead.</p>

    <p>For more information on the exposure risks, see <a class="exposure_health_risks" href="#">Exposure Health Risks</a>.</p>

    <h3 class="more_space">Purpose of the ECP</h3>
    <p>The <a class="ecp" href="#">Exposure Control Plan (ECP)</a> sets out how the employer will protect workers from hazardous exposure to RCS dust associated with a particular silica process. Required by the <a class="ohsr" href="#">Occupational Health & Safety Regulation (OHSR)</a>, the ECP is used to identify the exposure risk for a particular <a class="silica_process" href="#">silica process</a> and to coordinate and communicate the steps that will be executed to address the risk. A new ECP is required for each different kind of silica process identified as needed at a jobsite.</p>

    <p>To get started with your exposure control planning, click the <strong>Continue</strong> button below:</p>

    <div class="clearfix">
        <a href="javascript:void(0)" class="button save_continue secondary right load-step" data-step="get_prepared">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
    </div>
