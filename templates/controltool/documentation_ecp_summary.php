<?php
    include '../includes/db.php';
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
    $stmt_planning = $conn->prepare("
        SELECT jobsite_name, user_id, verified 
        FROM Exposure_Plannings 
        WHERE id = :plan_id
    ");
    $stmt_planning->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt_planning->execute();
    $planning = $stmt_planning->fetch(PDO::FETCH_ASSOC);

    if (!$planning) {
        echo "Error: No valid planning found.";
        exit();
    }

    // Fetch the plan creator's first and last name
    $stmt_user = $conn->prepare("
        SELECT first_name, last_name 
        FROM users 
        WHERE id = :user_id
    ");
    $stmt_user->bindParam(':user_id', $planning['user_id'], PDO::PARAM_INT);
    $stmt_user->execute();
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $plan_creator_name = $user_data ? htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) : 'Unknown';

    // Fetch all meta data from the Exposure_Plannings_Meta table (including signing_date and creator_signature)
    $stmt_meta = $conn->prepare("
        SELECT 
            jobsite_address, jobsite_city, jobsite_region, jobsite_post_code,
            ecp_contact_name, ecp_contact_position, ecp_contact_phone, ecp_contact_email,
            work_area, avg_hr_per_shift, project_start_date, project_end_date, jobsite_sector,
            jobsite_type, project_type, activity_material, activity_task, activity_tool, employer_name,
            signing_date, creator_signature, jobsite_shift_hours, employer_name, employer_address, employer_address_city,
            employer_address_region, employer_address_postal_code, employer_phone, employer_email, employer_website
        FROM Exposure_Plannings_Meta
        WHERE planning_id = :plan_id
    ");
    $stmt_meta->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt_meta->execute();
    $meta_data = $stmt_meta->fetch(PDO::FETCH_ASSOC);
    if (!$meta_data) {
        echo "Error: No metadata found for this planning.";
        exit();
    }

    // Ensure all activity-related data is properly fetched
    $activity_materials = isset($meta_data['activity_material']) ? explode(',', $meta_data['activity_material']) : [];
    $activity_tasks = isset($meta_data['activity_task']) ? explode(',', $meta_data['activity_task']) : [];
    $activity_tools = isset($meta_data['activity_tool']) ? explode(',', $meta_data['activity_tool']) : [];

    // Fetch activity details (material, task, tool)
    $work_activities = [];
    for ($i = 0; $i < max(count($activity_materials), count($activity_tasks), count($activity_tools)); $i++) {
        $material_id = $activity_materials[$i] ?? null;
        $task_id = $activity_tasks[$i] ?? null;
        $tool_id = $activity_tools[$i] ?? null;

        $material_name = $material_id ? fetchSingleName($conn, "Materials", $material_id) : 'N/A';
        $task_name = $task_id ? fetchSingleName($conn, "Tasks", $task_id) : 'N/A';
        $tool_name = $tool_id ? fetchSingleName($conn, "Tools", $tool_id) : 'N/A';

        $work_activities[] = htmlspecialchars("$task_name $material_name with $tool_name");
    }

    // Fetch controls data from Exposure_Plannings_Controls.
    $stmt_controls = $conn->prepare("
        SELECT 
            admin_controls_maintenance, admin_controls_housekeeping, admin_controls_hygene,
            admin_controls_training, admin_controls_procedures, admin_controls_scheduling,
            admin_controls_barriers, admin_controls_enclosures, engineering_controls,
            residual_exposure_respirator, residual_exposure_ppe, engineering_controls_details,
            engineering_controls_notes, engineering_controls_image,
            CASE 
                WHEN engineering_controls IN ('none', 'not_listed') THEN engineering_controls
                ELSE (
                    SELECT GROUP_CONCAT(ec.engineering_control_name ORDER BY ec.engineering_control_id SEPARATOR ',')
                    FROM engineering_controls ec
                    WHERE FIND_IN_SET(ec.engineering_control_id, engineering_controls)
                )
            END AS engineering_control_names
        FROM Exposure_Plannings_Controls
        WHERE planning_id = :plan_id
    ");
    $stmt_controls->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt_controls->execute();
    $controls = $stmt_controls->fetch(PDO::FETCH_ASSOC);

    // Determine number of activities (use meta data counts)
    $activity_count = max(count($activity_materials), count($activity_tasks), count($activity_tools));

    // Build the admin controls arrays
    $admin_controls_maintenance  = isset($controls['admin_controls_maintenance']) ? explode(',', $controls['admin_controls_maintenance']) : array_fill(0, $activity_count, '');
    $admin_controls_housekeeping = isset($controls['admin_controls_housekeeping'])  ? explode(',', $controls['admin_controls_housekeeping'])  : array_fill(0, $activity_count, '');
    $admin_controls_hygene       = isset($controls['admin_controls_hygene'])        ? explode(',', $controls['admin_controls_hygene'])        : array_fill(0, $activity_count, '');
    $admin_controls_training     = isset($controls['admin_controls_training'])      ? explode(',', $controls['admin_controls_training'])      : array_fill(0, $activity_count, '');
    $admin_controls_procedures   = isset($controls['admin_controls_procedures'])    ? explode(',', $controls['admin_controls_procedures'])    : array_fill(0, $activity_count, '');
    $admin_controls_scheduling   = isset($controls['admin_controls_scheduling'])    ? explode(',', $controls['admin_controls_scheduling'])    : array_fill(0, $activity_count, '');
    $admin_controls_barriers     = isset($controls['admin_controls_barriers'])      ? explode(',', $controls['admin_controls_barriers'])      : array_fill(0, $activity_count, '');
    $admin_controls_enclosures   = isset($controls['admin_controls_enclosures'])    ? explode(',', $controls['admin_controls_enclosures'])    : array_fill(0, $activity_count, '');

    // Also build engineering controls CSV and the names CSV (returned by the query)
    $engineering_controls_csv = $controls['engineering_controls'] ?? '';
    $engineering_controls_details_csv = $controls['engineering_controls_details'] ?? '';
    $engineering_control_names_csv = $controls['engineering_control_names'] ?? '';
    $engineering_controls_notes_csv = $controls['engineering_controls_notes'] ?? '';
    $engineering_controls_images_csv = $controls['engineering_controls_image'] ?? '';

    // Fetch all verification data from the Exposure_Plannings_Verification table for this plan
    $stmt_verification = $conn->prepare("
        SELECT 
            auditor_id, 
            admin_controls_maintenance, 
            admin_controls_maintenance_notes, 
            admin_controls_maintenance_image,
            admin_controls_housekeeping, 
            admin_controls_housekeeping_notes, 
            admin_controls_housekeeping_image,
            admin_controls_hygene, 
            admin_controls_hygene_notes, 
            admin_controls_hygene_image, 
            admin_controls_training, 
            admin_controls_training_notes, 
            admin_controls_training_image, 
            admin_controls_procedures, 
            admin_controls_procedures_notes, 
            admin_controls_procedures_image, 
            admin_controls_scheduling,
            admin_controls_scheduling_notes, 
            admin_controls_scheduling_image, 
            admin_controls_barriers, 
            admin_controls_barriers_notes,
            admin_controls_barriers_image, 
            admin_controls_enclosures, 
            admin_controls_enclosures_notes, 
            admin_controls_enclosures_image,
            activity_engineering_controls, 
            activity_engineering_controls_notes, 
            activity_engineering_controls_image, 
            activity_admin_controls,
            activity_admin_controls_notes, 
            activity_admin_controls_image, 
            verification_date, 
            auditor_signature
        FROM Exposure_Plannings_Verification
        WHERE plan_id = :plan_id
    ");
    $stmt_verification->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt_verification->execute();
    $verification_data = $stmt_verification->fetch(PDO::FETCH_ASSOC);

    // Convert the comma-separated verification values into arrays (default to an empty array if not set)
    $activity_admin_verif = isset($verification_data['activity_admin_controls']) 
        ? explode(',', $verification_data['activity_admin_controls']) 
        : array();
    $activity_eng_verif = isset($verification_data['activity_engineering_controls']) 
        ? explode(',', $verification_data['activity_engineering_controls']) 
        : array();

    $activity_eng_notes_array = isset($verification_data['activity_engineering_controls_notes']) 
        ? str_getcsv($verification_data['activity_engineering_controls_notes']) 
        : array();
    $activity_admin_notes_array = isset($verification_data['activity_admin_controls_notes']) 
        ? str_getcsv($verification_data['activity_admin_controls_notes']) 
        : array();

    $activity_eng_images = isset($verification_data['activity_engineering_controls_image'])
        ? array_map('trim', explode(',', $verification_data['activity_engineering_controls_image']))
        : array();

    $activity_admin_images = isset($verification_data['activity_admin_controls_image'])
        ? array_map('trim', explode(',', $verification_data['activity_admin_controls_image']))
        : array();

    // Preload verification notes (for each activity)
    $engineering_controls_notes_array = (strlen(trim($engineering_controls_notes_csv)) > 0)
        ? explode(',', $engineering_controls_notes_csv)
        : array_fill(0, $activity_count, '');

    // Preload saved image filenames (if any)
    $engineering_controls_images_array = (strlen(trim($engineering_controls_images_csv)) > 0)
        ? explode(',', $engineering_controls_images_csv)
        : array_fill(0, $activity_count, '');


    // Now fetch the auditor's first and last name from the users table
    $auditor_data = null;
    if ($verification_data && isset($verification_data['auditor_id'])) {
        $stmt_auditor = $conn->prepare("
            SELECT first_name, last_name
            FROM users
            WHERE id = :auditor_id
        ");
        $stmt_auditor->bindParam(':auditor_id', $verification_data['auditor_id'], PDO::PARAM_INT);
        $stmt_auditor->execute();
        $auditor_data = $stmt_auditor->fetch(PDO::FETCH_ASSOC);
    }

    $avg_hr_per_shift_arr = isset($meta_data['avg_hr_per_shift']) ? explode(',', $meta_data['avg_hr_per_shift']) : [];
    $work_area_arr = isset($meta_data['work_area']) ? explode(',', $meta_data['work_area']) : [];

    $stmt_exposure = $conn->prepare("
        SELECT baseline_exposure 
        FROM exposure_values 
        WHERE task_id = :task_id AND tool_id = :tool_id AND material_id = :material_id
    ");

    // For each activity, fetch the matching baseline_exposure
    $baseline_exposures = [];
    for ($i = 0; $i < $activity_count; $i++) {
        $task_id = isset($activity_tasks[$i]) ? trim($activity_tasks[$i]) : '';
        $tool_id = isset($activity_tools[$i]) ? trim($activity_tools[$i]) : '';
        $material_id = isset($activity_materials[$i]) ? trim($activity_materials[$i]) : '';
        if ($task_id && $tool_id && $material_id) {
            $stmt_exposure->bindValue(':task_id', (int)$task_id, PDO::PARAM_INT);
            $stmt_exposure->bindValue(':tool_id', (int)$tool_id, PDO::PARAM_INT);
            $stmt_exposure->bindValue(':material_id', (int)$material_id, PDO::PARAM_INT);
            $stmt_exposure->execute();
            $row = $stmt_exposure->fetch(PDO::FETCH_ASSOC);
            $baseline_exposures[$i] = ($row && isset($row['baseline_exposure'])) ? (float)$row['baseline_exposure'] : 0.000;
        } else {
            $baseline_exposures[$i] = 0.000;
        }
    }
    // --- END NEW CODE ---
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

/**
 * Helper function to fetch a single name from a table based on an ID.
 */
function fetchSingleName($conn, $table, $id) {
    $stmt = $conn->prepare("SELECT name FROM $table WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchColumn() ?: 'N/A';
}
?>


<h2 class="more_space">Exposure Control Plan (ECP) Summary</h2>

<!-- Tabs Section -->
<div class="ecp-tabs-container">
    <div class="ecp-tabs-content">
        <div id="ecp-tabs-summary" class="ecp-tab active">
            <h3>Employer Details</h3>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Employer Name</th>
                        <th>Employer Address</th>
                        <th>Employer Contact</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo htmlspecialchars($planning['jobsite_name'] ?? 'N/A'); ?></td>
                        <td>
                            <?php echo nl2br(htmlspecialchars(
                                ($meta_data['employer_address'] ?? 'N/A') . "\n" .
                                ($meta_data['employer_address_city'] ?? 'N/A') . "\n" .
                                ($meta_data['employer_address_region'] ?? 'N/A') . ', ' .
                                ($meta_data['employer_address_postal_code'] ?? 'N/A')
                            )); ?>
                        </td>
                        <td>
                            <?php
                            echo nl2br(htmlspecialchars(
                                "Phone: " . ($meta_data['employer_phone'] ?? 'N/A') . "\n" .
                                "Email: " . ($meta_data['employer_email'] ?? 'N/A') . "\n" .
                                "Website: " . ($meta_data['employer_website'] ?? 'N/A')
                            ));
                            ?>
                        </td>

                    </tr>
                </tbody>
            </table>

            <h3>Jobsite Details</h3>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Jobsite Name</th>
                        <th>Jobsite Address</th>
                        <th>Jobsite ECP Details</th>
                        <th>Jobsite Shift Length (hr/day)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo htmlspecialchars($planning['jobsite_name'] ?? 'N/A'); ?></td>
                        <td>
                            <?php echo nl2br(htmlspecialchars(
                                ($meta_data['jobsite_address'] ?? 'N/A') . "\n" .
                                ($meta_data['jobsite_city'] ?? 'N/A') . "\n" .
                                ($meta_data['jobsite_region'] ?? 'N/A') . ', ' .
                                ($meta_data['jobsite_post_code'] ?? 'N/A')
                            )); ?>
                        </td>
                        <td>
                            <?php
                            echo nl2br(htmlspecialchars(
                                "Name: " . ($meta_data['ecp_contact_name'] ?? 'N/A') . "\n" .
                                "Role: " . ($meta_data['ecp_contact_position'] ?? 'N/A') . "\n" .
                                "Phone: " . ($meta_data['ecp_contact_phone'] ?? 'N/A') . "\n" .
                                "Email: " . ($meta_data['ecp_contact_email'] ?? 'N/A')
                            ));
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($meta_data['jobsite_shift_hours'] ?? 'Not Provided'); ?></td>

                    </tr>
                </tbody>
            </table>

            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Working Dates</th>
                        <th>Trade</th>
                        <th>Jobsite Sector</th>
                        <th>Project Type</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <?php 
                            echo htmlspecialchars(
                                ($meta_data['project_start_date'] ?? 'N/A') . ' until ' . 
                                ($meta_data['project_end_date'] ?? 'N/A')
                            ); 
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($meta_data['jobsite_sector'] ?? 'Not Provided'); ?></td>
                        <td><?php echo htmlspecialchars($meta_data['jobsite_type'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($meta_data['project_type'] ?? 'N/A'); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3>Working Activities</h3>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Work Activity</th>
                        <th>Work Area</th>
                        <th>Hours per Shift</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Split work_area and avg_hr_per_shift into arrays
                    $work_areas = isset($meta_data['work_area']) ? explode(',', $meta_data['work_area']) : [];
                    $durations = isset($meta_data['avg_hr_per_shift']) ? explode(',', $meta_data['avg_hr_per_shift']) : [];

                    // Iterate over each work activity and display corresponding details
                    foreach ($work_activities as $index => $activity):
                        $work_area = $work_areas[$index] ?? 'N/A';
                        $duration = $durations[$index] ?? 'N/A';
                    ?>
                        <tr>
                            <td><?php echo $activity; ?></td>
                            <td><?php echo htmlspecialchars($work_area); ?></td>
                            <td><?php echo htmlspecialchars($duration); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3>Exposure Health Risk</h3>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <?php if (!empty($work_activities)): ?>
                                <?php foreach ($work_activities as $activity): ?>
                                    <strong><?php echo $activity; ?></strong>
                                    <br>
                                    <em>Placeholder text for RCS dust exposure risks and mitigation strategies.</em>
                                    <br><br>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No work activities available.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>


            <h3>ECP Purpose</h3>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            This ECP sets out the plan <?php echo htmlspecialchars($meta_data['employer_name'] ?? 'N/A'); ?> will implement to protect workers from hazardous exposure to RCS dust based on information relating to the identified silica process assessed through the Pharos Health Exposure Control Tool, and the site specific details set out herein. A specific ECP is developed for each different kind of silica process identified as needed at <?php echo htmlspecialchars($meta_data['jobsite_address'] ?? 'N/A'); ?>.
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3>Responsibilities</h3>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Employer Responsibilities</th>
                        <th>Supervisor Responsibilities</th>
                        <th>Worker Responsibilities</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            Ensure:
                            <ul>
                                <li>Effective controls are selected, implemented and documented;</li>
                                <li>Materials and resources necessary to fully implement and maintain this ECP are available;</li>
                                <li>Supervisors and workers are silica safety trained;</li>
                                <li>Written records as identified in this ECP are maintained;</li>
                                <li>Annual ECP review (or more if conditions change) is conducted;</li>
                                <li>Co-ordination of a safe work environment for workers.</li>
                            </ul>
                        </td>
                        <td>
                            Ensure:
                            <ul>
                                <li>Copy of ECP available at the jobsite;</li>
                                <li>ECP is distributed and reviewed with workers;</li>
                                <li>Workers are provided with instruction re: work activity hazards & safe work procedures;</li>
                                <li>Controls and equipment as identified in this ECP are inspected;</li>
                                <li>Respirators are fit-tested with results recorded;</li>
                                <li>Work is directed to minimize and control exposure risk.</li>
                            </ul>
                        </td>
                        <td>
                            Ensure:
                            <ul>
                                <li>RCS dust hazards and ECP details are known and understood;</li>
                                <li>PPE is used effectively and safely;</li>
                                <li>Work procedures are followed as per supervisor instructions;</li>
                                <li>Unsafe conditions and acts are reported to supervisor;</li>
                                <li>RCS dust exposure incidents / signs or symptoms of silica illness are reported.</li>
                            </ul>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="section-separator"></div><div class="section-separator"></div>

            <?php
            // Define the exposure limits:
            $combined_limit = 0.025;         // Standard exposure limit (mg/m³)
            $combined_action_limit = 0.0125;   // Action level (mg/m³)

            $combined_exposure = 0;

            for ($i = 0; $i < $activity_count; $i++) {
                // Retrieve baseline exposure (for an 8‑hour shift)
                $baseline = isset($baseline_exposures[$i]) ? $baseline_exposures[$i] : 0.000;
                
                // Use the provided average hours; if missing or zero, default to 8
                $avg_hr = isset($avg_hr_per_shift_arr[$i]) ? floatval(trim($avg_hr_per_shift_arr[$i])) : 8;
                if ($avg_hr <= 0) {
                    $avg_hr = 8;
                }
                
                // Retrieve the work area; default to empty string if not set
                $work_area = isset($work_area_arr[$i]) ? trim($work_area_arr[$i]) : '';

                // Adjust for hours: baseline exposure (8-hour basis) scaled by actual hours
                $activity_exposure = $baseline * ($avg_hr / 8);
                
                // Apply the work area multiplier:
                // Outdoors multiplies by 0.75, Indoors multiplies by 1.5, Restricted Space multiplies by 2.5.
                if (strcasecmp($work_area, 'Outside') === 0) {
                    $activity_exposure *= 0.75;
                } elseif (strcasecmp($work_area, 'Inside') === 0) {
                    $activity_exposure *= 1.5;
                } elseif (strcasecmp($work_area, 'Restricted Space') === 0) {
                    $activity_exposure *= 2.5;
                }
                
                $combined_exposure += $activity_exposure;
            }

            $combined_marker_percentage = ($combined_exposure / $combined_limit) * 100;

            if ($combined_exposure < 0.0125) {
                $combined_risk_text = "Safe Level";
                $combined_risk_class = "risk-safe";
                $combined_limit_status = "Est. Exposure Level within Exposure Limits";
                $combined_action_status = "Est. Exposure Level within Action Limits";
                $combined_risk_rec = "";
            } elseif ($combined_exposure < 0.025) {
                $combined_risk_text = "Dangerous Level";
                $combined_risk_class = "risk-caution";
                $combined_limit_status = "Est. Exposure Level within Exposure Limits";
                $combined_action_status = "Est. Exposure Level exceeds by " . round((($combined_exposure - 0.0125) / 0.0125 * 100)) . "%";
                $combined_risk_rec = "We recommend to proceed with controls as exposure level is Dangerous.";
            } else {
                $combined_risk_text = "Hazardous Level";
                $combined_risk_class = "risk-danger";
                $combined_limit_status = "Est. Exposure Level exceeds by " . round((($combined_exposure - 0.025) / 0.025 * 100)) . "%";
                $combined_action_status = "Est. Exposure Level exceeds by " . round((($combined_exposure - 0.0125) / 0.0125 * 100)) . "%";
                $combined_risk_rec = "We recommend to proceed with controls as exposure level is Hazardous.";
            }
?>


            <div class="exposure-meter-container">
                <h3>Combined Exposure Analysys (No Controls)</h3>
                <div class="meter-wrapper">
                    <div class="meter">
                        <div class="meter-bar">
                            <div id="meter-marker-combined" class="meter-marker" style="left: calc(<?php echo ($combined_marker_percentage > 100 ? 100 : $combined_marker_percentage); ?>% - 25px);"></div>
                        </div>
                    </div>
                    <div class="meter-labels">
                        <span class="green-label">Safe</span>
                        <span class="yellow-label">Caution</span>
                        <span class="red-label">Danger</span>
                    </div>
                </div>
                <p id="meter-reading-combined">Combined Exposure Level: <strong><?php echo number_format($combined_exposure, 4); ?> mg/m³</strong></p>
            </div>

            <div class="exposure-data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Combined Exposure Level (No Controls)</th>
                            <th>Combined Exposure Limit</th>
                            <th>Combined Action Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td id="exposure-level-combined"><?php echo number_format($combined_exposure, 4); ?> mg/m³</td>
                            <td>
                                <?php echo number_format($combined_limit, 3); ?> mg/m³<br>
                                <span id="exposure-limit-status-combined"><?php echo $combined_limit_status; ?></span>
                            </td>
                            <td>
                                <?php echo number_format($combined_action_limit, 3); ?> mg/m³<br>
                                <span id="action-limit-status-combined"><?php echo $combined_action_status; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th colspan="3">Risk Classification</th>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <span id="risk-classification-combined" class="<?php echo $combined_risk_class; ?>"><?php echo $combined_risk_text; ?></span>
                                <?php if (!empty($combined_risk_rec)): ?>
                                    <p id="risk-recommendation-combined"><?php echo $combined_risk_rec; ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>


            <div class="section-separator"></div><div class="section-separator"></div>

            <h3>General Administrative Controls</h3>
            <table class="summary-table">
                <colgroup>
                        <col style="width: 65%;">
                        <col style="width: 10%;">
                        <col style="width: 25%;">
                    </colgroup>
                <thead>
                    <tr>
                        <th>ADMINISTRATIVE CONTROLS</th>
                        <th>SITE RESPONSE</th>
                        <th>VERIFICATION</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <h5>Inspections &amp; Maintenance</h5>
                            <p>Will you be implementing scheduled inspections and maintenance of engineering controls to ensure they are kept in good working order?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_maintenance[0]) && trim($admin_controls_maintenance[0]) === "1") ? "Yes" : "No"; ?></td>
                        <td>
                            <?php
                                if ($verification_data['admin_controls_maintenance'] == 1) {
                                    echo '<span style="color:green;">Verified</span><br />';
                                } else {
                                    echo '<span style="color:orange;">Not Yet Verified</span><br />';
                                }
                                if (!empty($verification_data['admin_controls_maintenance_notes'])) {
                                    echo "<p class='auditor-notes'>" . htmlspecialchars($verification_data['admin_controls_maintenance_notes']) . "</p>";
                                } else {
                                    echo "<p class='auditor-notes'>No verification notes have been added yet</p>";
                                }
                                if (!empty($verification_data['admin_controls_maintenance_image'])) {
                                    echo '<img src="' . htmlspecialchars($verification_data['admin_controls_maintenance_image']) . '" style="max-width:480px;" alt="Verification Image">';
                                } else {
                                    echo '<p>No verification images have been uploaded</p>';
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h5>Housekeeping</h5>
                            <p>At the end of every work shift, will you be cleaning the work area and equipment from accumulated dust?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_housekeeping[0]) && trim($admin_controls_housekeeping[0]) === "1") ? "Yes" : "No"; ?></td>
                        <td>
                            <?php
                                if ($verification_data['admin_controls_housekeeping'] == 1) {
                                    echo '<span style="color:green;">Verified</span>';
                                } else {
                                    echo '<span style="color:orange;">Not Yet Verified</span>';
                                }
                                if (!empty($verification_data['admin_controls_housekeeping_notes'])) {
                                    echo "<p class='auditor-notes'>" . htmlspecialchars($verification_data['admin_controls_housekeeping_notes']) . "</p>";
                                } else {
                                    echo "<p class='auditor-notes'>No verification notes have been added yet</p>";
                                }
                                if (!empty($verification_data['admin_controls_housekeeping_image'])) {
                                    echo '<img src="' . htmlspecialchars($verification_data['admin_controls_housekeeping_image']) . '" style="max-width:480px;" alt="Verification Image">';
                                } else {
                                    echo '<p>No verification images have been uploaded</p>';
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h5>Hygiene</h5>
                            <p>At the end of every work shift, will workers and PPE be decontaminated to prevent inadvertent secondary inhalation of RCS dust?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_hygene[0]) && trim($admin_controls_hygene[0]) === "1") ? "Yes" : "No"; ?></td>
                        <td>
                            <?php
                                if ($verification_data['admin_controls_hygene']  == 1) {
                                    echo '<span style="color:green;">Verified</span>';
                                } else {
                                    echo '<span style="color:orange;">Not Yet Verified</span>';
                                }
                                if (!empty($verification_data['admin_controls_hygene_notes'])) {
                                    echo "<p class='auditor-notes'>" . htmlspecialchars($verification_data['admin_controls_hygene_notes']) . "</p>";
                                } else {
                                    echo "<p class='auditor-notes'>No verification notes have been added yet</p>";
                                }
                                if (!empty($verification_data['admin_controls_hygene_image'])) {
                                    echo '<img src="' . htmlspecialchars($verification_data['admin_controls_hygene_image']) . '" style="max-width:480px;" alt="Verification Image">';
                                } else {
                                    echo '<p>No verification images have been uploaded</p>';
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h5>Silica Safety Instruction &amp; Training</h5>
                            <p>Will your workers be instructed and trained in how to safely work within environments where RCS dust exposure is a risk?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_training[0]) && trim($admin_controls_training[0]) === "1") ? "Yes" : "No"; ?></td>
                        <td>
                            <?php
                                if ($verification_data['admin_controls_training']  == 1) {
                                    echo '<span style="color:green;">Verified</span>';
                                } else {
                                    echo '<span style="color:orange;">Not Yet Verified</span>';
                                }
                                if (!empty($verification_data['admin_controls_training_notes'])) {
                                    echo "<p class='auditor-notes'>" . htmlspecialchars($verification_data['admin_controls_training_notes']) . "</p>";
                                } else {
                                    echo "<p class='auditor-notes'>No verification notes have been added yet</p>";
                                }
                                if (!empty($verification_data['admin_controls_training_image'])) {
                                    echo '<img src="' . htmlspecialchars($verification_data['admin_controls_training_image']) . '" style="max-width:480px;" alt="Verification Image">';
                                } else {
                                    echo '<p>No verification images have been uploaded</p>';
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h5>Exposure Emergency Preparedness</h5>
                            <p>Will your jobsite be prepared for a RCS dust exposure emergency?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_procedures[0]) && trim($admin_controls_procedures[0]) === "1") ? "Yes" : "No"; ?></td>
                        <td>
                            <?php
                                if ($verification_data['admin_controls_procedures']  == 1) {
                                    echo '<span style="color:green;">Verified</span>';
                                } else {
                                    echo '<span style="color:orange;">Not Yet Verified</span>';
                                }
                                if (!empty($verification_data['admin_controls_procedures_notes'])) {
                                    echo "<p class='auditor-notes'>" . htmlspecialchars($verification_data['admin_controls_procedures_notes']) . "</p>";
                                } else {
                                    echo "<p class='auditor-notes'>No verification notes have been added yet</p>";
                                }
                                if (!empty($verification_data['admin_controls_procedures_image'])) {
                                    echo '<img src="' . htmlspecialchars($verification_data['admin_controls_procedures_image']) . '" style="max-width:480px;" alt="Verification Image">';
                                } else {
                                    echo '<p>No verification images have been uploaded</p>';
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h5>Work Shift Scheduling</h5>
                            <p>Will you be scheduling work shifts to limit the amount of time an individual worker is exposed to RCS dust?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_scheduling[0]) && trim($admin_controls_scheduling[0]) === "1") ? "Yes" : "No"; ?></td>
                        <td>
                            <?php
                                if ($verification_data['admin_controls_scheduling'] == 1) {
                                    echo '<span style="color:green;">Verified</span>';
                                } else {
                                    echo '<span style="color:orange;">Not Yet Verified</span>';
                                }
                                if (!empty($verification_data['admin_controls_scheduling_notes'])) {
                                    echo "<p class='auditor-notes'>" . htmlspecialchars($verification_data['admin_controls_scheduling_notes']) . "</p>";
                                } else {
                                    echo "<p class='auditor-notes'>No verification notes have been added yet</p>";
                                }
                                if (!empty($verification_data['admin_controls_scheduling_image'])) {
                                    echo '<img src="' . htmlspecialchars($verification_data['admin_controls_scheduling_image']) . '" style="max-width:480px;" alt="Verification Image">';
                                } else {
                                    echo '<p>No verification images have been uploaded</p>';
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h5>Barriers</h5>
                            <p>Will you use a barrier to isolate the work area from the rest of the construction project and to prevent entry by unauthorized workers?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_barriers[0]) && trim($admin_controls_barriers[0]) === "1") ? "Yes" : "No"; ?></td>
                        <td>
                            <?php
                                if ($verification_data['admin_controls_barriers']  == 1) {
                                    echo '<span style="color:green;">Verified</span>';
                                } else {
                                    echo '<span style="color:orange;">Not Yet Verified</span>';
                                }
                                if (!empty($verification_data['admin_controls_barriers_notes'])) {
                                    echo "<p class='auditor-notes'>" . htmlspecialchars($verification_data['admin_controls_barriers_notes']) . "</p>";
                                } else {
                                    echo "<p class='auditor-notes'>No verification notes have been added yet</p>";
                                }
                                if (!empty($verification_data['admin_controls_barriers_image'])) {
                                    echo '<img src="' . htmlspecialchars($verification_data['admin_controls_barriers_image']) . '" style="max-width:480px;" alt="Verification Image">';
                                } else {
                                    echo '<p>No verification images have been uploaded</p>';
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h5>Enclosures</h5>
                            <p>Will you use an enclosure to physically contain the dusty atmosphere?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_enclosures[0]) && trim($admin_controls_enclosures[0]) === "1") ? "Yes" : "No"; ?></td>
                        <td>
                            <?php
                                if ($verification_data['admin_controls_enclosures']  == 1) {
                                    echo '<span style="color:green;">Verified</span>';
                                } else {
                                    echo '<span style="color:orange;">Not Yet Verified</span>';
                                }
                                if (!empty($verification_data['admin_controls_enclosures_notes'])) {
                                    echo "<p class='auditor-notes'>" . htmlspecialchars($verification_data['admin_controls_enclosures_notes']) . "</p>";
                                } else {
                                    echo "<p class='auditor-notes'>No verification notes have been added yet</p>";
                                }
                                if (!empty($verification_data['admin_controls_enclosures_image'])) {
                                    echo '<img src="' . htmlspecialchars($verification_data['admin_controls_enclosures_image']) . '" style="max-width:480px;" alt="Verification Image">';
                                } else {
                                    echo '<p>No verification images have been uploaded</p>';
                                }
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>


            <?php foreach($work_activities as $index => $activity): ?>
                <h3>Engineering and Administrative Controls for <?php echo htmlspecialchars($activity); ?></h3>

                <table class="summary-table">
                    <colgroup>
                        <col style="width: 75%;">
                        <col style="width: 25%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>ENGINEERING CONTROLS</th>
                            <th>VERIFICATION DETAILS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <?php
                                // For this activity, split the CSV values for engineering controls
                                $ec_value = "";
                                if (!empty($engineering_controls_csv)) {
                                    $ec_array = explode(',', $engineering_controls_csv);
                                    $ec_value = trim($ec_array[$index]);
                                }
                                if ($ec_value === "" || is_null($ec_value)) {
                                    echo '<p>No controls have been selected.</p>';
                                } elseif ($ec_value === "none") {
                                    echo '<p>No engineering control will be used.</p>';
                                } elseif ($ec_value === "not_listed") {
                                    $detail = "";
                                    if (!empty($engineering_controls_details_csv)) {
                                        $detail_array = explode(',', $engineering_controls_details_csv);
                                        $detail = trim($detail_array[$index]);
                                    }
                                    echo '<p>Engineering control not listed' . (!empty($detail) ? " - " . htmlspecialchars($detail) : "") . '</p>';
                                } else {
                                    // Use the pre-fetched names from the query
                                    $ec_names_array = [];
                                    if (!empty($engineering_control_names_csv)) {
                                        $ec_names_array = explode(',', $engineering_control_names_csv);
                                    }
                                    $name = trim($ec_names_array[$index] ?? '');
                                    echo '<h4>' . htmlspecialchars($name) . '</h4>';
                                }
                                if(!empty($engineering_controls_notes_array[$index])) { ?>
                                    <h5>Site Notes</h5>
                                    <p><?php echo $engineering_controls_notes_array[$index] ; ?></p><?php
                                }else {
                                    echo "<p>No notes have been added for this control</p>";
                                }
                                if(!empty($engineering_controls_images_array[$index])) { ?>
                                    <h5>Site Uploaded Image</h5>
                                    <img style="max-width:50%" src="../assets/uploads/images/<?php echo $engineering_controls_images_array[$index] ?>" alt="Engineering Controls verification image for <?php echo htmlspecialchars($activity); ?>" /><?php
                                }else {
                                    echo "<p>No images have been uploaded for this control</p>";
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                // Engineering Verification Status
                                $eng_verif = isset($activity_eng_verif[$index]) ? trim($activity_eng_verif[$index]) : '0';
                                if ($eng_verif == '1') {
                                    echo '<h4 style="color:green;">Verified</h4>';
                                } else {
                                    echo '<h4 style="color:orange;">Not Yet Verified</h4>';
                                }
                                echo '<br>';

                                // Engineering Verification Notes
                                $eng_note = isset($activity_eng_notes_array[$index]) ? trim($activity_eng_notes_array[$index]) : '';
                                if ($eng_note === '') {
                                    echo "<h5>Verification Notes</h5><p class='auditor-notes'>No verification notes have been added yet</p>";
                                } else {
                                    echo "<h5>Verification Notes</h5><p class='auditor-notes'>" . htmlspecialchars($eng_note) . "</p>";
                                }
                                echo '<br>';

                                // Engineering Verification Image
                                $eng_img = isset($activity_eng_images[$index]) ? trim($activity_eng_images[$index]) : '';
                                if ($eng_img === '') {
                                    echo "<h5>Verification Image</h5><p>No verification images have been uploaded</p>";
                                } else {
                                    echo '<h5>Verification Image</h5><img src="' . htmlspecialchars($eng_img) . '" style="max-width:480px;" alt="Verification Image">';
                                }
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table class="summary-table">
                    <colgroup>
                        <col style="width: 75%;">
                        <col style="width: 25%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>ADMINISTRATIVE CONTROLS</th>
                            <th>VERIFICATION DETAILS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <p>Dynamic Administrative Controls for each activity will be listed here with their answers</p>
                            </td>
                            <td>
                                <?php
                                // Administrative Verification Status
                                $admin_verif = isset($activity_admin_verif[$index]) ? trim($activity_admin_verif[$index]) : '0';
                                if ($admin_verif == '1') {
                                    echo '<span style="color:green;">Verified</span>';
                                } else {
                                    echo '<span style="color:orange;">Not Yet Verified</span>';
                                }
                                echo '<br>';

                                // Administrative Verification Notes
                                $admin_note = isset($activity_admin_notes_array[$index]) ? trim($activity_admin_notes_array[$index]) : '';
                                if ($admin_note === '') {
                                    echo "<p class='auditor-notes'>No verification notes have been added yet</p>";
                                } else {
                                    echo "<p class='auditor-notes'>" . htmlspecialchars($admin_note) . "</p>";
                                }
                                echo '<br>';

                                // Administrative Verification Image
                                $admin_img = isset($activity_admin_images[$index]) ? trim($activity_admin_images[$index]) : '';
                                if ($admin_img === '') {
                                    echo "<p>No verification images have been uploaded</p>";
                                } else {
                                    echo '<img src="' . htmlspecialchars($admin_img) . '" style="max-width:480px;" alt="Verification Image">';
                                }
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            <?php endforeach; ?>


            <div class="section-separator"></div>
            <?php 
                $controls_deduction = 17;
                $controlled_combined_exposure = $combined_exposure / $controls_deduction;

                $controlled_combined_marker_percentage = ($controlled_combined_exposure / $combined_limit) * 100;

                if ($controlled_combined_marker_percentage > 100) {
                    $controlled_combined_marker_percentage = 99;
                }

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
                    $controlled_combined_action_status = "Est. Exposure Level exceeds by " . round((($combined_exposure - 0.0125) / 0.0125 * 100)) . "%";
                    $controlled_combined_risk_rec = "We recommend to proceed with controls as exposure level is Dangerous.";
                } else {
                    $controlled_combined_risk_text = "Hazardous Level";
                    $controlled_combined_risk_class = "risk-danger";
                    $controlled_combined_limit_status = "Est. Exposure Level exceeds by " . round((($combined_exposure - 0.025) / 0.025 * 100)) . "%";
                    $controlled_combined_action_status = "Est. Exposure Level exceeds by " . round((($combined_exposure - 0.0125) / 0.0125 * 100)) . "%";
                    $controlled_combined_risk_rec = "We recommend to proceed with controls as exposure level is Hazardous.";
                }
            ?>

            <div class="exposure-meter-container">
                <h3>Combined Exposure Analysys (With Controls)</h3>
                <div class="meter-wrapper">
                    <div class="meter">
                        <div class="meter-bar">
                            <div id="controlled-meter-marker-combined" class="meter-marker" style="left: <?php echo $controlled_combined_marker_percentage; ?>%;"></div>
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
                            <th>Combined Exposure Level (With Controls)</th>
                            <th>Combined Exposure Limit</th>
                            <th>Combined Action Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td id="controlled-exposure-level-combined"><?php echo number_format($controlled_combined_exposure, 4); ?> mg/m³</td>
                            <td>
                                <?php echo number_format($combined_limit, 3); ?> mg/m³<br>
                                <span id="controlled-exposure-limit-status-combined"><?php echo $controlled_combined_limit_status; ?></span>
                            </td>
                            <td>
                                <?php echo number_format($combined_action_limit, 3); ?> mg/m³<br>
                                <span id="controlled-action-limit-status-combined"><?php echo $controlled_combined_action_status; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th colspan="3">Risk Classification</th>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <span id="controlled-risk-classification-combined" class="<?php echo $controlled_combined_risk_class; ?>"><?php echo $controlled_combined_risk_text; ?></span>
                                <?php if (!empty($combined_risk_rec)): ?>
                                    <p id="controlled-risk-recommendation-combined"><?php echo $controlled_combined_risk_rec; ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="section-separator"></div>

            
                <h3>Residual Exposure Control (PPE) for <?php echo $planning['jobsite_name']; ?></h3>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Respirator Usage</th>
                            <th>Required Protection Factor</th>
                            <th>Respirator Type & Filter</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>PROTECTION REQUIRED</td>
                            <td>10</td>
                            <td>
                                Half facepiece, non-powered with P100 filter<br>
                                <em>
                                Please note, the respirator type above is an example of a respirator type that may meet the required protection factor. 
                                Users may elect to use alternate respiratory protection equipment that meets the required protection factor rating. 
                                Any respirator choice must be fitted with an N100, P100 or R100 filter. Respirators and filters must be NIOSH approved.
                                </em>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>WORKER PPE USAGE</th>
                            <th>SITE RESPONSE</th>
                            <th>VERIFICATION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Will workers on the jobsite have respirators available?</td>
                            <td><?php echo ($controls['residual_exposure_respirator'] == '1') ? "Yes" : "No"; ?></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Will workers in the jobsite wear washable or disposable coveralls?</td>
                            <td><?php echo ($controls['residual_exposure_ppe'] == '1') ? "Yes" : "No"; ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

            <div class="section-separator"></div>

            <?php
                if ($controls['residual_exposure_respirator'] == 1 && $controls['residual_exposure_ppe'] == 1) {
                    $ppe_deduction = 20;
                } elseif ($controls['residual_exposure_respirator'] == 1 && $controls['residual_exposure_ppe'] == 0) {
                    $ppe_deduction = 10;
                } elseif ($controls['residual_exposure_respirator'] == 0 && $controls['residual_exposure_ppe'] == 1) {
                    $ppe_deduction = 2;
                } else { // both are 0
                    $ppe_deduction = 1;
                }
                $final_combined_exposure = $controlled_combined_exposure / $ppe_deduction;

                $final_combined_marker_percentage = ($final_combined_exposure / $combined_limit) * 100;

                if ($final_combined_marker_percentage > 100) {
                    $final_combined_marker_percentage = 99;
                }

                if ($final_combined_exposure < 0.0125) {
                    $final_combined_risk_text = "Safe Level";
                    $final_combined_risk_class = "risk-safe";
                    $final_combined_limit_status = "Est. Exposure Level within Exposure Limits";
                    $final_combined_action_status = "Est. Exposure Level within Action Limits";
                    $final_combined_risk_rec = "";
                } elseif ($final_combined_exposure < 0.025) {
                    $final_combined_risk_text = "Dangerous Level";
                    $final_combined_risk_class = "risk-caution";
                    $final_combined_limit_status = "Est. Exposure Level within Exposure Limits";
                    $final_combined_action_status = "Est. Exposure Level exceeds by " . round((($combined_exposure - 0.0125) / 0.0125 * 100)) . "%";
                    $final_combined_risk_rec = "We recommend to proceed with controls as exposure level is Dangerous.";
                } else {
                    $final_combined_risk_text = "Hazardous Level";
                    $final_combined_risk_class = "risk-danger";
                    $final_combined_limit_status = "Est. Exposure Level exceeds by " . round((($combined_exposure - 0.025) / 0.025 * 100)) . "%";
                    $final_combined_action_status = "Est. Exposure Level exceeds by " . round((($combined_exposure - 0.0125) / 0.0125 * 100)) . "%";
                    $final_combined_risk_rec = "We recommend to proceed with controls as exposure level is Hazardous.";
                }
            ?>

            <div class="exposure-meter-container">
                <h3>Final Combined Exposure Analysys</h3>
                <div class="meter-wrapper">
                    <div class="meter">
                        <div class="meter-bar">
                            <div id="final-meter-marker-combined" class="meter-marker" style="left: <?php echo $final_combined_marker_percentage; ?>%;"></div>
                        </div>
                    </div>
                    <div class="meter-labels">
                        <span class="green-label">Safe</span>
                        <span class="yellow-label">Caution</span>
                        <span class="red-label">Danger</span>
                    </div>
                </div>
                <p id="final-meter-reading-combined">Combined Exposure Level: <strong><?php echo number_format($final_combined_exposure, 4); ?> mg/m³</strong></p>
            </div>

            <div class="exposure-data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Combined Exposure Level (Final)</th>
                            <th>Combined Exposure Limit</th>
                            <th>Combined Action Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td id="final-exposure-level-combined"><?php echo number_format($final_combined_exposure, 4); ?> mg/m³</td>
                            <td>
                                <?php echo number_format($final_combined_limit, 3); ?> mg/m³<br>
                                <span id="final-exposure-limit-status-combined"><?php echo $final_combined_limit_status; ?></span>
                            </td>
                            <td>
                                <?php echo number_format($combined_action_limit, 3); ?> mg/m³<br>
                                <span id="final-action-limit-status-combined"><?php echo $final_combined_action_status; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th colspan="3">Risk Classification</th>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <span id="final-risk-classification-combined" class="<?php echo $final_combined_risk_class; ?>"><?php echo $final_combined_risk_text; ?></span>
                                <?php if (!empty($combined_risk_rec)): ?>
                                    <p id="final-risk-recommendation-combined"><?php echo $final_combined_risk_rec; ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="section-separator"></div>

            <h3>Documentation</h3>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>DOCUMENTATION</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            Documents and materials that augment this ECP submitted to ECP Contact;<br>
                            ECP Summary available on jobsite as physical copy. Complete ECP available on jobsite as physical or digital copy;<br>
                            All workers involved must have free access to this ECP and an opportunity to ask questions;<br>
                            All documentation filed at head office for 10 years;<br>
                            ECP must be reviewed at least annually, and updated as needed due to any changes.
                        </td>
                    </tr>
                </tbody>
            </table>

            <div id="signaturesContainer" style="display: flex; gap: 20px;">
                <?php
                    // Assuming $meta_data has been fetched (see your original code)
                    $savedSignature = $meta_data['creator_signature'] ? '../assets/images/signatures/' . $meta_data['creator_signature'] : '';
                    // Set the canvas style. If a signature exists, include pointer-events: none; to disable drawing.
                    $canvasStyle = 'border: 2px solid #000; background: #fff; display: block; margin-bottom: 10px;';
                    if (!empty($savedSignature)) {
                        $canvasStyle = 'border: 2px solid #000; background: url(\'' . htmlspecialchars($savedSignature) . '\') no-repeat center center; background-size: contain; display: block; margin-bottom: 10px; pointer-events: none;';
                    }
                ?>

                <div id="creator-signing" style="flex: 1;">
                    <h3>Plan Creator Signature</h3>
                    <canvas id="signature-pad" width="500" height="150" style="<?php echo $canvasStyle; ?>"></canvas>
                    <?php if (empty($savedSignature)): ?>
                        <button id="clear-signature" class="button small">Clear</button>
                        <button id="save-signature" class="button small">Save Signature</button>
                    <?php endif; ?>

                    <!-- Pre-populate hidden input with the saved signature (if any) -->
                    <input type="hidden" id="signature-data" name="signature_data" value="<?php echo htmlspecialchars($savedSignature); ?>">

                    <?php if (!empty($savedSignature)): ?>
                        <p>Signed by <?php echo $plan_creator_name; ?> on <?php echo date('F j, Y', strtotime($meta_data['signing_date'])); ?></p>
                    <?php endif; ?>
                </div>

                <?php
                    $savedAuditorSignature = !empty($verification_data['auditor_signature']) ? '../assets/images/signatures/' . $verification_data['auditor_signature'] : '';
                    $auditorCanvasStyle = 'border: 2px solid #000; background: #fff; display: block; margin-bottom: 10px;';
                    if (!empty($savedAuditorSignature)) {
                        $auditorCanvasStyle = 'border: 2px solid #000; background: url(\'' . htmlspecialchars($savedAuditorSignature) . '\') no-repeat center center; background-size: contain; display: block; margin-bottom: 10px; pointer-events: none;';
                    }
                ?>

                <div id="auditor-signing" style="flex: 1;">
                    <h3>Auditor Signature</h3>
                    <canvas id="auditor-signature-pad" width="500" height="150" style="<?php echo $auditorCanvasStyle; ?>"></canvas>
                    <?php if (empty($savedAuditorSignature)): ?>
                        <p>This planning has not yet been verified</p>
                    <?php else: ?>
                        <p>Signed by <?php echo htmlspecialchars($auditor_data['first_name'] . ' ' . $auditor_data['last_name']); ?> on <?php echo htmlspecialchars(date('F j, Y', strtotime($verification_data['verification_date']))); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <br />


        </div>

    </div>
</div>

<!-- Navigation Buttons -->
<div class="clearfix">
    <a href="javascript:void(0)" class="button secondary left load-step" data-step="documentation_get_prepared">Back</a>
    <a href="javascript:void(0)" class="button save_continue secondary right load-step" data-step="documentation_generate_ecp">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
</div>

<script type="text/javascript">
// Tab functionality
document.querySelectorAll('.ecp-tabs-link').forEach(link => {
    link.addEventListener('click', function (e) {
        e.preventDefault();

        // Remove 'active' class from all links and tab content
        document.querySelectorAll('.ecp-tabs-link').forEach(link => link.classList.remove('active'));
        document.querySelectorAll('.ecp-tab').forEach(tab => tab.classList.remove('active'));

        // Add 'active' class to the clicked link and corresponding tab
        this.classList.add('active');
        const targetTab = this.getAttribute('data-tab');
        document.getElementById(`ecp-tabs-${targetTab}`).classList.add('active');
    });
});

(function(){
    const signCanvas = document.getElementById("signature-pad");
    if (!signCanvas) return;
    const signCtx = signCanvas.getContext("2d");
    let isDrawing = false;

    // Set up canvas properties
    signCtx.strokeStyle = "#000";
    signCtx.lineWidth = 2;
    signCtx.lineCap = "round";

    // Mouse events for drawing
    signCanvas.addEventListener("mousedown", function(e) {
        isDrawing = true;
        signCtx.beginPath();
        signCtx.moveTo(e.offsetX, e.offsetY);
    });

    signCanvas.addEventListener("mousemove", function(e) {
        if (!isDrawing) return;
        signCtx.lineTo(e.offsetX, e.offsetY);
        signCtx.stroke();
    });

    signCanvas.addEventListener("mouseup", function() {
        isDrawing = false;
    });

    signCanvas.addEventListener("mouseleave", function() {
        isDrawing = false;
    });

    // Touch events for mobile devices
    signCanvas.addEventListener("touchstart", function(e) {
        e.preventDefault();
        const rect = signCanvas.getBoundingClientRect();
        const touch = e.touches[0];
        isDrawing = true;
        signCtx.beginPath();
        signCtx.moveTo(touch.clientX - rect.left, touch.clientY - rect.top);
    });

    signCanvas.addEventListener("touchmove", function(e) {
        e.preventDefault();
        if (!isDrawing) return;
        const rect = signCanvas.getBoundingClientRect();
        const touch = e.touches[0];
        signCtx.lineTo(touch.clientX - rect.left, touch.clientY - rect.top);
        signCtx.stroke();
    });

    signCanvas.addEventListener("touchend", function() {
        isDrawing = false;
    });

    // Clear signature
    document.getElementById("clear-signature").addEventListener("click", function () {
        signCtx.clearRect(0, 0, signCanvas.width, signCanvas.height);
    });

    // Save signature and trigger AJAX to store the signature image, update signing date and path.
    document.getElementById("save-signature").addEventListener("click", function () {
        const signatureData = signCanvas.toDataURL("image/png");
        document.getElementById("signature-data").value = signatureData;
        
        const data = {
            plan_id: <?php echo json_encode($plan_id); ?>,
            signature_data: signatureData
        };

        fetch('../ajax/save_supervisor_signature.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if(result.success){
                alert("Document has been successfully signed, the page will now reload.");
                window.location.reload();
            } else {
                alert("Error saving signature: " + result.message);
            }
        })
        .catch(error => {
            console.error("AJAX error:", error);
            alert("Error saving signature.");
        });
    });
})();



</script>
