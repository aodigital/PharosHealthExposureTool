<?php
include '../includes/db.php'; // DB connection
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

    // Fetch control data (stored as CSV strings)
    $stmt_controls = $conn->prepare("SELECT * FROM Exposure_Plannings_Controls WHERE planning_id = :plan_id");
    $stmt_controls->bindParam(':plan_id', $plan_id);
    $stmt_controls->execute();
    $controls = $stmt_controls->fetch(PDO::FETCH_ASSOC);

    // Fetch meta data (work activity identifiers)
    $stmt_details = $conn->prepare("SELECT activity_task, activity_tool, activity_material FROM Exposure_Plannings_Meta WHERE planning_id = :plan_id");
    $stmt_details->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt_details->execute();
    $meta = $stmt_details->fetch(PDO::FETCH_ASSOC);

    // Explode the meta fields into arrays (if not set, default to empty arrays)
    $activity_tasks    = isset($meta['activity_task'])    ? explode(',', $meta['activity_task'])    : [];
    $activity_tools    = isset($meta['activity_tool'])    ? explode(',', $meta['activity_tool'])    : [];
    $activity_materials = isset($meta['activity_material']) ? explode(',', $meta['activity_material']) : [];
    $activity_count = max(count($activity_tasks), count($activity_tools), count($activity_materials), 1);

    // Helper function to look up a name given an ID from a table
    function getNameById($conn, $table, $id) {
        $stmt = $conn->prepare("SELECT name FROM {$table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() ?: 'N/A';
    }

    // Helper for engineering controls: look up control name by its numeric ID.
    function getEngineeringControlName($conn, $controlId) {
        $stmt = $conn->prepare("SELECT engineering_control_name FROM engineering_controls WHERE engineering_control_id = :id");
        $stmt->bindParam(':id', $controlId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() ?: 'N/A';
    }

    // Build arrays of names for each work activity
    $task_names = [];
    $tool_names = [];
    $material_names = [];
    for ($i = 0; $i < $activity_count; $i++) {
        $task_id     = isset($activity_tasks[$i]) ? trim($activity_tasks[$i]) : '';
        $tool_id     = isset($activity_tools[$i]) ? trim($activity_tools[$i]) : '';
        $material_id = isset($activity_materials[$i]) ? trim($activity_materials[$i]) : '';
        $task_names[$i]     = $task_id ? getNameById($conn, 'Tasks', $task_id) : 'N/A';
        $tool_names[$i]     = $tool_id ? getNameById($conn, 'Tools', $tool_id) : 'N/A';
        $material_names[$i] = $material_id ? getNameById($conn, 'Materials', $material_id) : 'N/A';
    }

    // For summary display, assume the engineering_controls and engineering_controls_details
    // columns store CSV strings. If a saved value is not set, treat it as no control selected.
    $engineering_controls_csv = $controls['engineering_controls'] ?? '';
    $engineering_controls_details_csv = $controls['engineering_controls_details'] ?? '';
    
    // The Administrative Controls arrays are assumed to be loaded as before (unchanged).
    $admin_controls_maintenance  = isset($controls['admin_controls_maintenance'])  ? explode(',', $controls['admin_controls_maintenance'])  : array_fill(0, $activity_count, '');
    $admin_controls_housekeeping = isset($controls['admin_controls_housekeeping']) ? explode(',', $controls['admin_controls_housekeeping']) : array_fill(0, $activity_count, '');
    $admin_controls_hygene       = isset($controls['admin_controls_hygene'])       ? explode(',', $controls['admin_controls_hygene'])       : array_fill(0, $activity_count, '');
    $admin_controls_training     = isset($controls['admin_controls_training'])     ? explode(',', $controls['admin_controls_training'])     : array_fill(0, $activity_count, '');
    $admin_controls_procedures   = isset($controls['admin_controls_procedures'])   ? explode(',', $controls['admin_controls_procedures'])   : array_fill(0, $activity_count, '');
    $admin_controls_scheduling   = isset($controls['admin_controls_scheduling'])   ? explode(',', $controls['admin_controls_scheduling'])   : array_fill(0, $activity_count, '');
    $admin_controls_barriers     = isset($controls['admin_controls_barriers'])     ? explode(',', $controls['admin_controls_barriers'])     : array_fill(0, $activity_count, '');
    $admin_controls_enclosures   = isset($controls['admin_controls_enclosures'])   ? explode(',', $controls['admin_controls_enclosures'])   : array_fill(0, $activity_count, '');
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<style>
    .summary-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 1em;
    }
    .summary-table th, .summary-table td {
        border: 1px solid #ccc;
        padding: 8px;
    }
    .activity-section {
        margin-bottom: 2em;
    }
</style>

<h2 class="more_space">Exposure Control Summary</h2>

<!-- ELIMINATION CONTROLS -->
<h3>ELIMINATION CONTROLS</h3>
<p>If yes has been answered for any of the elimination questions, then the activity can be removed from the planning</p>
<table class="summary-table">
    <thead>
        <tr>
            <th>Activity</th>
            <th>Control Question</th>
            <th>Response</th>
        </tr>
    </thead>
    <tbody>
    <?php for($i = 0; $i < $activity_count; $i++): ?>
        <tr>
            <td>
                Activity <?php echo $i+1; ?>:
                <?php echo htmlspecialchars($task_names[$i] . ' ' . $material_names[$i] . ' with ' . $tool_names[$i]); ?>
            </td>
            <td>Can you eliminate the need for this work activity?</td>
            <td>
                <?php 
                echo isset($controls['elimination_control']) 
                    ? (explode(',', $controls['elimination_control'])[$i] == "1" ? "Yes" : "No")
                    : "N/A";
                ?>
            </td>
        </tr>
    <?php endfor; ?>
    </tbody>
</table>

<!-- ENGINEERING CONTROLS -->
<h3>ENGINEERING CONTROLS</h3>
<?php for($i = 0; $i < $activity_count; $i++): ?>
<div class="activity-section">
    <h4>
        Activity <?php echo $i+1; ?>:
        <?php echo htmlspecialchars($task_names[$i] . ' ' . $material_names[$i] . ' with ' . $tool_names[$i]); ?>
    </h4>
    <table class="summary-table">
        <thead>
            <tr>
                <th>Control</th>
                <th>Response</th>
                <th>Auditor Verified?</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Engineering Control Selected</td>
                <td>
                    <?php
                    // Get the saved value for engineering control for this activity.
                    $ec_value = "";
                    if (!empty($engineering_controls_csv)) {
                        $ec_array = explode(',', $engineering_controls_csv);
                        $ec_value = trim($ec_array[$i]);
                    }
                    
                    if ($ec_value === "" || is_null($ec_value)) {
                        echo "No controls have been selected.";
                    } elseif ($ec_value === "none") {
                        echo "No engineering control will be used.";
                    } elseif ($ec_value === "not_listed") {
                        $detail = "";
                        if (!empty($engineering_controls_details_csv)) {
                            $detail_array = explode(',', $engineering_controls_details_csv);
                            $detail = trim($detail_array[$i]);
                        }
                        echo "Engineering control not listed" . (!empty($detail) ? " - " . htmlspecialchars($detail) : "");
                    } elseif (is_numeric($ec_value)) {
                        echo htmlspecialchars(getEngineeringControlName($conn, $ec_value));
                    } else {
                        // Fallback for any unexpected value.
                        echo htmlspecialchars($ec_value);
                    }
                    ?>
                </td>
                <td>Yes/No</td>
            </tr>
            <tr>
                <td>Additional Details</td>
                <td>
                    <?php 
                    echo !empty($engineering_controls_details_csv) 
                        ? htmlspecialchars(trim(explode(',', $engineering_controls_details_csv)[$i])) 
                        : 'N/A'; 
                    ?>
                </td>
                <td>Yes/No</td>
            </tr>
        </tbody>
    </table>
</div>
<?php endfor; ?>

<!-- ADMINISTRATIVE CONTROLS (Unchanged from your original code) -->
<h3>ADMINISTRATIVE CONTROLS</h3>
<h4>General Administrative Controls</h4>
<table class="summary-table">
    <thead>
        <tr>
            <th>Administrative Control</th>
            <th>Response</th>
            <th>Auditor Verified?</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><h5>Inspections &amp; Maintenance</h5>
            <p>Will you be implementing scheduled inspections and maintenance of engineering controls to ensure they are kept in good working order?</p></td>
            <td><?php echo (isset($admin_controls_maintenance[0]) && $admin_controls_maintenance[0] == "1") ? "Yes" : "No"; ?></td>
            <td>Yes/No</td>
        </tr>
        <tr>
            <td><h5>Housekeeping</h5>
            <p>At the end of every work shift, will you be cleaning the work area and equipment from accumulated dust?</p></td>
            <td><?php echo (isset($admin_controls_housekeeping[0]) && $admin_controls_housekeeping[0] == "1") ? "Yes" : "No"; ?></td>
            <td>Yes/No</td>
        </tr>
        <tr>
            <td><h5>Hygiene</h5>
            <p>At the end of every work shift, will workers and PPE be decontaminated to prevent inadvertent secondary inhalation of RCS dust?</p></td>
            <td><?php echo (isset($admin_controls_hygene[0]) && $admin_controls_hygene[0] == "1") ? "Yes" : "No"; ?></td>
            <td>Yes/No</td>
        </tr>
        <tr>
            <td><h5>Silica Safety Instruction &amp; Training</h5>
            <p>Will your workers be instructed and trained in how to safely work within environments where RCS dust exposure is a risk?</p></td>
            <td><?php echo (isset($admin_controls_training[0]) && $admin_controls_training[0] == "1") ? "Yes" : "No"; ?></td>
            <td>Yes/No</td>
        </tr>
        <tr>
            <td><h5>Exposure Emergency Preparedness</h5>
            <p>Will your jobsite be prepared for a RCS dust exposure emergency?</p></td>
            <td><?php echo (isset($admin_controls_procedures[0]) && $admin_controls_procedures[0] == "1") ? "Yes" : "No"; ?></td>
            <td>Yes/No</td>
        </tr>
        <tr>
            <td><h5>Work Shift Scheduling</h5>
            <p>Will you be scheduling work shifts to limit the amount of time an individual worker is exposed to RCS dust?</p></td>
            <td><?php echo (isset($admin_controls_scheduling[0]) && $admin_controls_scheduling[0] == "1") ? "Yes" : "No"; ?></td>
            <td>Yes/No</td>
        </tr>
        <tr>
            <td><h5>Barriers</h5>
            <p>Will you use a barrier to isolate the work area from the rest of the construction project and to prevent entry by unauthorized workers?</p></td>
            <td><?php echo (isset($admin_controls_barriers[0]) && $admin_controls_barriers[0] == "1") ? "Yes" : "No"; ?></td>
            <td>Yes/No</td>
        </tr>
        <tr>
            <td><h5>Enclosures</h5>
            <p>Will you use an enclosure to physically contain the dusty atmosphere?</p></td>
            <td><?php echo (isset($admin_controls_enclosures[0]) && $admin_controls_enclosures[0] == "1") ? "Yes" : "No"; ?></td>
            <td>Yes/No</td>
        </tr>
    </tbody>
</table>

<!-- Loop Over Each Activity with New (Placeholder) Tables for Administrative Controls -->
<?php for($i = 0; $i < $activity_count; $i++): ?>
<div class="activity-section">
    <h4>
        Activity <?php echo $i+1; ?>:
        <?php echo htmlspecialchars($task_names[$i] . ' ' . $material_names[$i] . ' with ' . $tool_names[$i]); ?>
    </h4>
    <table class="summary-table">
        <thead>
            <tr>
                <th>Administrative Control</th>
                <th>Response</th>
                <th>Auditor Verified?</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <h5>Administrative Control 1</h5>
                    <p>Placeholder text for per activity administrative controls to be added</p>
                </td>
                <td>Yes/No</td>
                <td>Yes/No</td>
            </tr>
            <tr>
                <td>
                    <h5>Administrative Control 2</h5>
                    <p>Placeholder text for per activity administrative controls to be added</p>
                </td>
                <td>Yes/No</td>
                <td>Yes/No</td>
            </tr>
        </tbody>
    </table>
</div>
<?php endfor; ?>

<p>Are there any controls to add or delete?</p>
<div class="button-group-inline">
    <a href="javascript:void(0)" class="button small load-step" data-step="exposure_control_engineering_controls">Edit Engineering Controls</a>
    <a href="javascript:void(0)" class="button small load-step" data-step="exposure_control_administrative_controls">Edit Administrative Controls</a>
</div>

<div class="clearfix">
    <a href="javascript:void(0)" class="button secondary left load-step" data-step="exposure_control_administrative_controls">Back</a>
    <a href="javascript:void(0)" class="button save_continue secondary right load-step" data-step="silica_exposure_with_controls_get_prepared">
        Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i>
    </a>
</div>
