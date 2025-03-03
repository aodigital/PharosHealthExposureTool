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

    // Parse CSV fields into arrays
    $activity_tasks     = isset($meta_data['activity_task'])    ? explode(',', $meta_data['activity_task'])    : array();
    $activity_tools     = isset($meta_data['activity_tool'])    ? explode(',', $meta_data['activity_tool'])    : array();
    $activity_materials = isset($meta_data['activity_material'])  ? explode(',', $meta_data['activity_material']) : array();
    $activity_count     = max(count($activity_tasks), count($activity_tools), count($activity_materials), 1);

    // Additional fields:
    // Jobsite shift hours is assumed to be a single value
    $jobsite_shift_hours = isset($meta_data['jobsite_shift_hours']) ? $meta_data['jobsite_shift_hours'] : 0;

    // Comma-separated average hours per shift (one per activity)
    $avg_hr_per_shift_arr = isset($meta_data['avg_hr_per_shift']) ? explode(',', $meta_data['avg_hr_per_shift']) : array();

    // Comma-separated work area strings (one per activity)
    $work_area_arr = isset($meta_data['work_area']) ? explode(',', $meta_data['work_area']) : array();

    // Helper function: Look up a name given an ID from a table
    function getName($conn, $table, $id) {
        $stmt = $conn->prepare("SELECT name FROM {$table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() ?: 'N/A';
    }
    
    // Prepare a statement for retrieving the baseline exposure for a given activity combination.
    $stmt_exposure = $conn->prepare("SELECT baseline_exposure FROM exposure_values WHERE task_id = :task_id AND tool_id = :tool_id AND material_id = :material_id");
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<h2 class="more_space">Exposure Analysis (with Controls)</h2>

<!-- Tabs Section -->
<div class="exposure-controls-tabs-container">
    <ul class="exposure-controls-tabs-links">
        <li class="exposure-controls-tabs-link active" data-tab="results">
            <a href="#">Results</a>
        </li>
        <li class="exposure-controls-tabs-link" data-tab="details">
            <a href="#">Details</a>
        </li>
    </ul>
    <div class="exposure-controls-tabs-content">
        <div id="exposure-controls-tab-results" class="exposure-controls-tab active">
            <h3>ACTIVITY EXPOSURE LEVELS WITH CONTROLS</h3>
            <?php 
            $combined_exposure = 0;
            // Loop through each work activity and output the complete controlled exposure analysis section
            for ($i = 0; $i < $activity_count; $i++):
                // Retrieve IDs for this activity
                $task    = isset($activity_tasks[$i]) ? trim($activity_tasks[$i]) : '';
                $tool    = isset($activity_tools[$i]) ? trim($activity_tools[$i]) : '';
                $material= isset($activity_materials[$i]) ? trim($activity_materials[$i]) : '';

                // Get the display names using the helper function
                $task_name     = $task ? getName($conn, 'Tasks', (int)$task) : 'N/A';
                $tool_name     = $tool ? getName($conn, 'Tools', (int)$tool) : 'N/A';
                $material_name = $material ? getName($conn, 'Materials', (int)$material) : 'N/A';
                $activity_text = $task_name . ' ' . $material_name . ' with a ' . $tool_name;
                
                // Retrieve the baseline exposure for this activity combination
                $baseline_exposure = 0;
                if ($task && $tool && $material) {
                    $stmt_exposure->bindValue(':task_id', (int)$task, PDO::PARAM_INT);
                    $stmt_exposure->bindValue(':tool_id', (int)$tool, PDO::PARAM_INT);
                    $stmt_exposure->bindValue(':material_id', (int)$material, PDO::PARAM_INT);
                    $stmt_exposure->execute();
                    $result = $stmt_exposure->fetch(PDO::FETCH_ASSOC);
                    if ($result && isset($result['baseline_exposure'])) {
                        $baseline_exposure = (float)$result['baseline_exposure'];
                    }
                }
                // If no baseline exposure is found, set it to 0.000
                if ($baseline_exposure <= 0) {
                    $baseline_exposure = 0.000;
                }
                
                // Retrieve the additional per-activity values:
                // Average hours per shift for this activity (default to 0 if not set)
                $avg_hr = isset($avg_hr_per_shift_arr[$i]) ? floatval(trim($avg_hr_per_shift_arr[$i])) : 0;
                // Work area for this activity (default to empty string)
                $work_area = isset($work_area_arr[$i]) ? trim($work_area_arr[$i]) : '';

                // Adjust the exposure based on the hours relative to an 8-hour baseline
                $adjusted_exposure = $baseline_exposure * ($avg_hr / 8);

                // Further adjust based on work area:
                // "Outside" => 75%, "Inside" => 150%, "Restricted Space" => 250%
                if (strcasecmp($work_area, 'Outside') === 0) {
                    $adjusted_exposure *= 0.75;
                } elseif (strcasecmp($work_area, 'Inside') === 0) {
                    $adjusted_exposure *= 1.5;
                } elseif (strcasecmp($work_area, 'Restricted Space') === 0) {
                    $adjusted_exposure *= 2.5;
                }
                $controlled_exposure = $adjusted_exposure / 17;
            

                $controlled_combined_exposure += $controlled_exposure;
                
                // Calculate marker percentage (using 0.025 mg/m³ as reference)
                $marker_percentage = ($controlled_exposure / 0.025) * 100;
                // Use a separate variable for the marker's left position (capped at 100%)
                $marker_left = ($marker_percentage > 100) ? 99 : $marker_percentage;
                
                // Compute risk classification based on the adjusted exposure value
                if ($controlled_exposure < 0.0125) {
                    $risk_text = "Safe Level";
                    $risk_class = "risk-safe";
                    $limit_status = "Est. Exposure Level within Exposure Limits";
                    $action_status = "Est. Exposure Level within Action Limits";
                    $risk_rec = "";
                } elseif ($controlled_exposure < 0.025) {
                    $risk_text = "Dangerous Level";
                    $risk_class = "risk-caution";
                    $limit_status = "Est. Exposure Level within Exposure Limits";
                    $action_status = "Est. Exposure Level exceeds by " . round((($controlled_exposure - 0.0125) / 0.0125 * 100)) . "%";
                    $risk_rec = "We recommend to proceed with controls as exposure level is Dangerous.";
                } else {
                    $risk_text = "Hazardous Level";
                    $risk_class = "risk-danger";
                    $limit_status = "Est. Exposure Level exceeds by " . round((($controlled_exposure - 0.025) / 0.025 * 100)) . "%";
                    $action_status = "Est. Exposure Level exceeds by " . round((($controlled_exposure - 0.0125) / 0.0125 * 100)) . "%";
                    $risk_rec = "We recommend to proceed with controls as exposure level is Hazardous.";
                }
            ?>
            <div class="exposure-meter-container">
                <h3>Exposure Level Meter for <?php echo htmlspecialchars($activity_text); ?></h3>
                <!-- Optionally, output avg_hr and work_area for debugging or further use -->
                <p>Average Hours/Shift: <?php echo $avg_hr; ?> &mdash; Work Area: <?php echo htmlspecialchars($work_area); ?></p>
                <div class="meter-wrapper">
                    <div class="meter">
                        <div class="meter-bar">
                            <div id="meter-marker-<?php echo $i; ?>" class="meter-marker" style="left: <?php echo $marker_left; ?>%;border:5px solid black;"></div>
                        </div>
                    </div>
                    <div class="meter-labels">
                        <span class="green-label">Safe</span>
                        <span class="yellow-label">Caution</span>
                        <span class="red-label">Danger</span>
                    </div>
                </div>
                <p id="meter-reading-<?php echo $i; ?>">Current Exposure Level: <strong><?php echo number_format($controlled_exposure, 4); ?> mg/m³</strong></p>
            </div>

            <div class="exposure-data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Est. Exposure Level with Engineering Controls</th>
                            <th>Exposure Limit</th>
                            <th>Action Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td id="exposure-level-<?php echo $i; ?>"><?php echo number_format($controlled_exposure, 4); ?> mg/m³</td>
                            <td>
                                0.025 mg/m³<br>
                                <span id="exposure-limit-status-<?php echo $i; ?>"><?php echo $limit_status; ?></span>
                            </td>
                            <td>
                                0.0125 mg/m³<br>
                                <span id="action-limit-status-<?php echo $i; ?>"><?php echo $action_status; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th colspan="3">Risk Classification</th>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <span id="risk-classification-<?php echo $i; ?>" class="<?php echo $risk_class; ?>"><?php echo $risk_text; ?></span>
                                <?php if (!empty($risk_rec)): ?>
                                    <p id="risk-recommendation-<?php echo $i; ?>"><?php echo $risk_rec; ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endfor; ?>

            <h3>COMBINED EXPOSURE LEVELS WITH CONTROLS</h3>

            <?php 
            // Combined Exposure Section:
            // The combined exposure is the sum of the adjusted exposures from each activity.
            $controlled_combined_marker_percentage = ($controlled_combined_exposure / 0.025) * 100;
            if ($controlled_combined_exposure < 0.0125) {
                $controlled_combined_risk_text = "Safe Level";
                $controlled_combined_risk_class = "risk-safe";
                $controlled_combined_limit_status = "Est. Exposure Level within Exposure Limits";
                $controlled_combined_action_status = "Est. Exposure Level within Action Limits";
                $controlled_combined_risk_rec = "";
            } elseif ($controlled_combined_exposure < 0.025) {
                $controlled_combined_risk_text = "Dangerous Level";
                $controlled_combined_risk_class = "risk-caution";
                $controlled_combined_limit_status = "Est. Exposure Level within Exposure Limits";
                $controlled_combined_action_status = "Est. Exposure Level exceeds by " . round((($controlled_combined_exposure - 0.0125) / 0.0125 * 100)) . "%";
                $controlled_combined_risk_rec = "We recommend to proceed with controls as exposure level is Dangerous.";
            } else {
                $controlled_combined_risk_text = "Hazardous Level";
                $controlled_combined_risk_class = "risk-danger";
                $controlled_combined_limit_status = "Est. Exposure Level exceeds by " . round((($controlled_combined_exposure - 0.025) / 0.025 * 100)) . "%";
                $controlled_combined_action_status = "Est. Exposure Level exceeds by " . round((($controlled_combined_exposure - 0.0125) / 0.0125 * 100)) . "%";
                $controlled_combined_risk_rec = "We recommend to proceed with controls as exposure level is Hazardous.";
            }
            ?>

            <!-- Combined Exposure Meter and Data Table -->
            <div class="exposure-meter-container">
                <h3>Exposure Level Meter for Combined Exposure Rating with Controls)</h3>
                <!-- Optionally output the jobsite shift hours -->
                <p>Jobsite Shift Hours: <?php echo htmlspecialchars($jobsite_shift_hours); ?></p>
                <div class="meter-wrapper">
                    <div class="meter">
                        <div class="meter-bar">
                            <div id="controlled-meter-marker-combined" class="meter-marker" style="left: <?php echo ($controlled_combined_marker_percentage > 100 ? 99 : $controlled_combined_marker_percentage); ?>%;"></div>
                        </div>
                    </div>
                    <div class="meter-labels">
                        <span class="green-label">Safe</span>
                        <span class="yellow-label">Caution</span>
                        <span class="red-label">Danger</span>
                    </div>
                </div>
                <p id="controlled-meter-reading-combined">Combined Exposure Level: <strong><?php echo number_format($controlled_combined_exposure, 4); ?> mg/m³</strong></p>
            </div>

            <div class="exposure-data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Est. Exposure Level (No Controls)</th>
                            <th>Exposure Limit</th>
                            <th>Action Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td id="controlled-exposure-level-combined"><?php echo number_format($controlled_combined_exposure, 4); ?> mg/m³</td>
                            <td>
                                0.025 mg/m³<br>
                                <span id="controlled-exposure-limit-status-combined"><?php echo $controlled_combined_limit_status; ?></span>
                            </td>
                            <td>
                                0.0125 mg/m³<br>
                                <span id="controlled-action-limit-status-combined"><?php echo $controlled_combined_action_status; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th colspan="3">Risk Classification</th>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <span id="controlled-risk-classification-combined" class="<?php echo $controlled_combined_risk_class; ?>"><?php echo $controlled_combined_risk_text; ?></span>
                                <?php if (!empty($controlled_combined_risk_rec)): ?>
                                    <p id="controlled-risk-recommendation-combined"><?php echo $controlled_combined_risk_rec; ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <div id="exposure-controls-tab-details" class="exposure-controls-tab">
            <p>Details content goes here.</p>
        </div>
    </div>
</div>

<!-- Engineering Controls Section -->
<div class="controls-section">
    <p>Do you wish to change Engineering Controls and run the analysis again?</p>
    <a href="javascript:void(0)" class="button small load-step" data-step="exposure_control_engineering_controls">Change Engineering Controls</a>
</div>

<!-- Next Step Section -->
<div class="next-step-section">
    <p>Next, we'll present the appropriate PPE controls available to additionally control the risk.</p>
</div>

<!-- Navigation Buttons -->
<div class="clearfix">
    <a href="javascript:void(0)" class="button secondary left load-step" data-step="silica_exposure_with_controls_get_prepared">Back</a>
    <a href="javascript:void(0)" class="button save_continue secondary right load-step" data-step="residual_exposure_control_get_prepared">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
</div>

<script>
    document.querySelectorAll('.exposure-controls-tabs-link').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.exposure-controls-tabs-link').forEach(link => link.classList.remove('active'));
            document.querySelectorAll('.exposure-controls-tab').forEach(tab => tab.classList.remove('active'));
            this.classList.add('active');
            const targetTab = this.getAttribute('data-tab');
            document.getElementById(`exposure-controls-tab-${targetTab}`).classList.add('active');
        });
    });
    // No additional JS calculation is performed since the values are computed in PHP.
</script>
