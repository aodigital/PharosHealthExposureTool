<?php
    include '../includes/db.php';

    if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'auditor'])) {
        header("Location: ?page=dashboard");
        exit();
    }

    if (!isset($_GET['plan_id']) || !is_numeric($_GET['plan_id'])) {
        echo "Invalid planning ID.";
        exit();
    }

    $plan_id = intval($_GET['plan_id']);

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

        $stmt_user = $conn->prepare("
            SELECT first_name, last_name 
            FROM users 
            WHERE id = :user_id
        ");
        $stmt_user->bindParam(':user_id', $planning['user_id'], PDO::PARAM_INT);
        $stmt_user->execute();
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
        $plan_creator_name = $user_data ? htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) : 'Unknown';

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

        $activity_materials = isset($meta_data['activity_material']) ? explode(',', $meta_data['activity_material']) : [];
        $activity_tasks = isset($meta_data['activity_task']) ? explode(',', $meta_data['activity_task']) : [];
        $activity_tools = isset($meta_data['activity_tool']) ? explode(',', $meta_data['activity_tool']) : [];

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

        $stmt_controls = $conn->prepare("
            SELECT 
                admin_controls_maintenance,
                admin_controls_housekeeping,
                admin_controls_hygene,
                admin_controls_training,
                admin_controls_procedures,
                admin_controls_scheduling,
                admin_controls_barriers,
                admin_controls_enclosures,
                engineering_controls,
                engineering_controls_details,
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

        $activity_count = max(count($activity_materials), count($activity_tasks), count($activity_tools));

        $admin_controls_maintenance  = isset($controls['admin_controls_maintenance']) ? explode(',', $controls['admin_controls_maintenance']) : array_fill(0, $activity_count, '');
        $admin_controls_housekeeping = isset($controls['admin_controls_housekeeping'])  ? explode(',', $controls['admin_controls_housekeeping'])  : array_fill(0, $activity_count, '');
        $admin_controls_hygene       = isset($controls['admin_controls_hygene'])        ? explode(',', $controls['admin_controls_hygene'])        : array_fill(0, $activity_count, '');
        $admin_controls_training     = isset($controls['admin_controls_training'])      ? explode(',', $controls['admin_controls_training'])      : array_fill(0, $activity_count, '');
        $admin_controls_procedures   = isset($controls['admin_controls_procedures'])    ? explode(',', $controls['admin_controls_procedures'])    : array_fill(0, $activity_count, '');
        $admin_controls_scheduling   = isset($controls['admin_controls_scheduling'])    ? explode(',', $controls['admin_controls_scheduling'])    : array_fill(0, $activity_count, '');
        $admin_controls_barriers     = isset($controls['admin_controls_barriers'])      ? explode(',', $controls['admin_controls_barriers'])      : array_fill(0, $activity_count, '');
        $admin_controls_enclosures   = isset($controls['admin_controls_enclosures'])    ? explode(',', $controls['admin_controls_enclosures'])    : array_fill(0, $activity_count, '');

        $engineering_controls_csv = $controls['engineering_controls'] ?? '';
        $engineering_controls_details_csv = $controls['engineering_controls_details'] ?? '';
        $engineering_control_names_csv = $controls['engineering_control_names'] ?? '';

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
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
        exit();
    }

    function fetchSingleName($conn, $table, $id) {
        $stmt = $conn->prepare("SELECT name FROM $table WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() ?: 'N/A';
    }
?>


<style>
    .tabs {
        list-style: none;
        padding: 0;
        margin: 0 0 20px 0;
        display: flex;
        border-bottom: 2px solid #ccc;
    }
    .tabs .tab {
        padding: 10px 20px;
        margin-right: 5px;
        cursor: pointer;
        border: 1px solid #ccc;
        border-bottom: none;
        background: #f1f1f1;
    }
    .tabs .tab.active {
        background: #fff;
        font-weight: bold;
        border-bottom: 2px solid #fff;
    }
</style>

<div class="container">
    <?php include 'sidebar.php'; ?>
    <div class="content">
        <h1>Auditor Verification Tools for <?php echo htmlspecialchars($planning['jobsite_name']); ?></h1>

        <p>This is where users with the admin or auditor role can submit verifications on controls that have been set within exposure plans.</p><br />

        <div id="tabs-container">
            <ul class="tabs">
                <li class="tab active" data-tab="site-details-tab">Site Details</li>
                <li class="tab" data-tab="general-admin-controls-tab">General Admin Controls</li>
                <li class="tab" data-tab="activity-controls-tab">Activity Controls</li>
            </ul>
        </div>

        <div id="site-details-tab">
            <h3>Site & Employer Details</h3>
            <p>The Site Details tab contains general details about the Employer and Job site that the particular verification is related to.</p><br />
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

                    $work_areas = isset($meta_data['work_area']) ? explode(',', $meta_data['work_area']) : [];
                    $durations = isset($meta_data['avg_hr_per_shift']) ? explode(',', $meta_data['avg_hr_per_shift']) : [];
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

        </div>

        <div id="general-admin-controls-tab">
            <h3 class="more_space">General Administrative Controls</h3>

            <p>The "General Admin Controls" tab is where you can submit verifications for the general site based administrative controls.  Select "Verified" or "Not Verified" for each item, optionally add an image and any notes for each item and then click on the "Save Admin Controls Verification" button at the bottom of the tab section, this button will upload your images, save the answers to the database and reload the page.</p><br />

            <table class="summary-table">
                <colgroup>
                    <col style="max-width:50%; width:50%;">
                    <col style="width:1%;">
                    <col style="width:auto;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Administrative Control Question</th>
                        <th>Site Response</th>
                        <th>Verification</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <h5>Inspections &amp; Maintenance</h5>
                            <p>Will you be implementing scheduled inspections and maintenance of engineering controls to ensure they are kept in good working order?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_maintenance[0]) && trim($admin_controls_maintenance[0]) === "1") ? "Yes" : (($admin_controls_maintenance[0] === "0") ? "No" : "Not answered"); ?></td>
                        <td>
                            <label>
                                <input type="radio" name="verif_inspections" value="1" <?php if(isset($verification_data['admin_controls_maintenance']) && trim($verification_data['admin_controls_maintenance']) === "1") echo 'checked'; ?>>
                                Verified
                            </label><br>
                            <label>
                                <input type="radio" name="verif_inspections" value="0" <?php if(isset($verification_data['admin_controls_maintenance']) && trim($verification_data['admin_controls_maintenance']) === "0") echo 'checked'; ?>>
                                Not Verified
                            </label><br>
                            <input type="file" name="verif_inspections_file" accept="image/*"> Image (optional)<br><br>
                            <?php
                                if (!empty($verification_data['admin_controls_maintenance_image'])) {
                                    echo '<img style="max-width:500px;" src="' . htmlspecialchars($verification_data['admin_controls_maintenance_image']) . '" alt="Admin Controls Maintenance Image">';
                                } else {
                                    echo 'No Image Uploaded';
                                }
                            ?>
                            <textarea class="verification-notes-input" name="verif_inspections_notes" placeholder="Enter verification notes..." rows="5"><?php echo isset($verification_data['admin_controls_maintenance_notes']) ? htmlspecialchars($verification_data['admin_controls_maintenance_notes']) : ''; ?></textarea><br>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h5>Housekeeping</h5>
                            <p>At the end of every work shift, will you be cleaning the work area and equipment from accumulated dust?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_housekeeping[0]) && trim($admin_controls_housekeeping[0]) === "1") ? "Yes" : (($admin_controls_housekeeping[0] === "0") ? "No" : "Not answered"); ?></td>
                        <td>
                            <label><input type="radio" name="verif_housekeeping" value="1" <?php if(isset($verification_data['admin_controls_housekeeping']) && trim($verification_data['admin_controls_housekeeping']) === "1") echo 'checked'; ?>> Verified</label><br>
                            <label><input type="radio" name="verif_housekeeping" value="0" <?php if(isset($verification_data['admin_controls_housekeeping']) && trim($verification_data['admin_controls_housekeeping']) === "0") echo 'checked'; ?>> Not Verified</label><br>
                            <input type="file" name="verif_housekeeping_file" accept="image/*"> Image (optional)<br><br>
                            <?php
                                if (!empty($verification_data['admin_controls_housekeeping_image'])) {
                                    echo '<img style="max-width:500px;" src="' . htmlspecialchars($verification_data['admin_controls_housekeeping_image']) . '" alt="Admin Controls Housekeeping Image">';
                                } else {
                                    echo 'No Image Uploaded';
                                }
                            ?>
                            <textarea class="verification-notes-input" name="verif_housekeeping_notes" placeholder="Enter verification notes..." rows="5"><?php echo isset($verification_data['admin_controls_housekeeping_notes']) ? htmlspecialchars($verification_data['admin_controls_housekeeping_notes']) : ''; ?></textarea><br>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h5>Hygiene</h5>
                            <p>At the end of every work shift, will workers and PPE be decontaminated to prevent inadvertent secondary inhalation of RCS dust?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_hygene[0]) && trim($admin_controls_hygene[0]) === "1") ? "Yes" : (($admin_controls_hygene[0] === "0") ? "No" : "Not answered"); ?></td>
                        <td>
                            <label><input type="radio" name="verif_hygiene" value="1" <?php if(isset($verification_data['admin_controls_hygene']) && trim($verification_data['admin_controls_hygene']) === "1") echo 'checked'; ?>> Verified</label><br>
                            <label><input type="radio" name="verif_hygiene" value="0" <?php if(isset($verification_data['admin_controls_hygene']) && trim($verification_data['admin_controls_hygene']) === "0") echo 'checked'; ?>> Not Verified</label><br>
                            <input type="file" name="verif_hygiene_file" accept="image/*"> Image (optional)<br><br>
                            <?php
                                if (!empty($verification_data['admin_controls_hygene_image'])) {
                                    echo '<img style="max-width:500px;" src="' . htmlspecialchars($verification_data['admin_controls_hygene_image']) . '" alt="Admin Controls Hygene Image">';
                                } else {
                                    echo 'No Image Uploaded';
                                }
                            ?>
                            <textarea class="verification-notes-input" name="verif_hygiene_notes" placeholder="Enter verification notes..." rows="5"><?php echo isset($verification_data['admin_controls_hygene_notes']) ? htmlspecialchars($verification_data['admin_controls_hygene_notes']) : ''; ?></textarea><br>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h5>Silica Safety Instruction &amp; Training</h5>
                            <p>Will your workers be instructed and trained in how to safely work within environments where RCS dust exposure is a risk?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_training[0]) && trim($admin_controls_training[0]) === "1") ? "Yes" : (($admin_controls_training[0] === "0") ? "No" : "Not answered"); ?></td>
                        <td>
                            <label><input type="radio" name="verif_training" value="1" <?php if(isset($verification_data['admin_controls_training']) && trim($verification_data['admin_controls_training']) === "1") echo 'checked'; ?>> Verified</label><br>
                            <label><input type="radio" name="verif_training" value="0" <?php if(isset($verification_data['admin_controls_training']) && trim($verification_data['admin_controls_training']) === "0") echo 'checked'; ?>> Not Verified</label><br>
                            <input type="file" name="verif_training_file" accept="image/*"> Image (optional)<br><br>
                            <?php
                                if (!empty($verification_data['admin_controls_training_image'])) {
                                    echo '<img style="max-width:500px;" src="' . htmlspecialchars($verification_data['admin_controls_training_image']) . '" alt="Admin Controls Training Image">';
                                } else {
                                    echo 'No Image Uploaded';
                                }
                            ?>
                            <textarea class="verification-notes-input" name="verif_training_notes" placeholder="Enter verification notes..." rows="5"><?php echo isset($verification_data['admin_controls_training_notes']) ? htmlspecialchars($verification_data['admin_controls_training_notes']) : ''; ?></textarea><br>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h5>Exposure Emergency Preparedness</h5>
                            <p>Will your jobsite be prepared for a RCS dust exposure emergency?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_procedures[0]) && trim($admin_controls_procedures[0]) === "1") ? "Yes" : (($admin_controls_procedures[0] === "0") ? "No" : "Not answered"); ?></td>
                        <td>
                            <label><input type="radio" name="verif_procedures" value="1" <?php if(isset($verification_data['admin_controls_procedures']) && trim($verification_data['admin_controls_procedures']) === "1") echo 'checked'; ?>> Verified</label><br>
                            <label><input type="radio" name="verif_procedures" value="0" <?php if(isset($verification_data['admin_controls_procedures']) && trim($verification_data['admin_controls_procedures']) === "01") echo 'checked'; ?>> Not Verified</label><br>
                            <input type="file" name="verif_procedures_file" accept="image/*"> Image (optional)<br><br>
                            <?php
                                if (!empty($verification_data['admin_controls_procedures_image'])) {
                                    echo '<img style="max-width:500px;" src="' . htmlspecialchars($verification_data['admin_controls_procedures_image']) . '" alt="Admin Controls Procedures Image">';
                                } else {
                                    echo 'No Image Uploaded';
                                }
                            ?>
                            <textarea class="verification-notes-input" name="verif_procedures_notes" placeholder="Enter verification notes..." rows="5"><?php echo isset($verification_data['admin_controls_procedures_notes']) ? htmlspecialchars($verification_data['admin_controls_procedures_notes']) : ''; ?></textarea><br>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h5>Work Shift Scheduling</h5>
                            <p>Will you be scheduling work shifts to limit the amount of time an individual worker is exposed to RCS dust?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_scheduling[0]) && trim($admin_controls_scheduling[0]) === "1") ? "Yes" : (($admin_controls_scheduling[0] === "0") ? "No" : "Not answered"); ?></td>
                        <td>
                            <label><input type="radio" name="verif_scheduling" value="1" <?php if(isset($verification_data['admin_controls_scheduling']) && trim($verification_data['admin_controls_scheduling']) === "1") echo 'checked'; ?>> Verified</label><br>
                            <label><input type="radio" name="verif_scheduling" value="0" <?php if(isset($verification_data['admin_controls_scheduling']) && trim($verification_data['admin_controls_scheduling']) === "0") echo 'checked'; ?>> Not Verified</label><br>
                            <input type="file" name="verif_scheduling_file" accept="image/*"> Image (optional)<br><br>
                            <?php
                                if (!empty($verification_data['admin_controls_scheduling_image'])) {
                                    echo '<img style="max-width:500px;" src="' . htmlspecialchars($verification_data['admin_controls_scheduling_image']) . '" alt="Admin Controls Scheduling Image">';
                                } else {
                                    echo 'No Image Uploaded';
                                }
                            ?>
                            <textarea class="verification-notes-input" name="verif_scheduling_notes" placeholder="Enter verification notes..." rows="5"><?php echo isset($verification_data['admin_controls_scheduling_notes']) ? htmlspecialchars($verification_data['admin_controls_scheduling_notes']) : ''; ?></textarea><br>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h5>Barriers</h5>
                            <p>Will you use a barrier to isolate the work area from the rest of the construction project and to prevent entry by unauthorized workers?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_barriers[0]) && trim($admin_controls_barriers[0]) === "1") ? "Yes" : (($admin_controls_barriers[0] === "0") ? "No" : "Not answered"); ?></td>
                        <td>
                            <label><input type="radio" name="verif_barriers" value="1" <?php if(isset($verification_data['admin_controls_barriers']) && trim($verification_data['admin_controls_barriers']) === "1") echo 'checked'; ?>> Verified</label><br>
                            <label><input type="radio" name="verif_barriers" value="0" <?php if(isset($verification_data['admin_controls_barriers']) && trim($verification_data['admin_controls_barriers']) === "0") echo 'checked'; ?>> Not Verified</label><br>
                            <input type="file" name="verif_barriers_file" accept="image/*"> Image (optional)<br><br>
                            <?php
                                if (!empty($verification_data['admin_controls_barriers_image'])) {
                                    echo '<img style="max-width:500px;" src="' . htmlspecialchars($verification_data['admin_controls_barriers_image']) . '" alt="Admin Controls barriers Image">';
                                } else {
                                    echo 'No Image Uploaded';
                                }
                            ?>                     
                            <textarea class="verification-notes-input" name="verif_barriers_notes" placeholder="Enter verification notes..." rows="5"><?php echo isset($verification_data['admin_controls_barriers_notes']) ? htmlspecialchars($verification_data['admin_controls_barriers_notes']) : ''; ?></textarea><br>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h5>Enclosures</h5>
                            <p>Will you use an enclosure to physically contain the dusty atmosphere?</p>
                        </td>
                        <td><?php echo (isset($admin_controls_enclosures[0]) && trim($admin_controls_enclosures[0]) === "1") ? "Yes" : (($admin_controls_enclosures[0] === "0") ? "No" : "Not answered"); ?></td>
                        <td>
                            <label><input type="radio" name="verif_enclosures" value="1" <?php if(isset($verification_data['admin_controls_enclosures']) && trim($verification_data['admin_controls_enclosures']) === "1") echo 'checked'; ?>> Verified</label><br>
                            <label><input type="radio" name="verif_enclosures" value="0" <?php if(isset($verification_data['admin_controls_enclosures']) && trim($verification_data['admin_controls_enclosures']) === "0") echo 'checked'; ?>> Not Verified</label><br>
                            <input type="file" name="verif_enclosures_file" accept="image/*"> Image (optional)<br><br>
                            <?php
                                if (!empty($verification_data['admin_controls_enclosures_image'])) {
                                    echo '<img style="max-width:500px;" src="' . htmlspecialchars($verification_data['admin_controls_enclosures_image']) . '" alt="Admin Controls Enclosures Image">';
                                } else {
                                    echo 'No Image Uploaded';
                                }
                            ?>
                            <textarea class="verification-notes-input" name="verif_enclosures_notes" placeholder="Enter verification notes..." rows="5"><?php echo isset($verification_data['admin_controls_enclosures_notes']) ? htmlspecialchars($verification_data['admin_controls_enclosures_notes']) : ''; ?></textarea><br>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div style="text-align: center;">
                <button id="save-admin-controls-btn" class="button large">Save Admin Controls Verification</button>
            </div>
        </div>

        <div id="activity-controls-tab">
            <h3>Working Activity Controls</h3>
            <p>The "Activity Controls" tab is where you can submit verifications for the activity based controls.  Select "Verified" or "Not Verified" for each item, optionally add an image and any notes for each item and then click on the "Save Activity Controls Verification" button at the bottom of the tab section, this button will upload your images, save the answers to the database and reload the page.</p><br />

            <?php foreach($work_activities as $index => $activity): ?>
                <h3>Engineering and Administrative Controls for <?php echo $activity; ?></h3>
                <table class="summary-table">
                    <colgroup>
                        <col style="max-width:50%; width:50%;">
                        <col style="width:auto;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>ENGINEERING CONTROLS</th>
                            <th>AUDITOR VERIFICATION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <?php
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
                                    $ec_names_array = [];
                                    if (!empty($engineering_control_names_csv)) {
                                        $ec_names_array = explode(',', $engineering_control_names_csv);
                                    }
                                    $name = trim($ec_names_array[$index] ?? '');
                                    echo '<p>' . htmlspecialchars($name) . '</p>';
                                }
                                ?>
                            </td>
                            <td>
                                <label>
                                    <input type="radio" name="verif_eng_<?php echo $index; ?>" value="1"
                                    <?php
                                    if (isset($activity_eng_verif[$index]) && trim($activity_eng_verif[$index]) === "1") {
                                        echo 'checked';
                                    }
                                    ?>>
                                    Verified
                                </label><br>
                                <label>
                                    <input type="radio" name="verif_eng_<?php echo $index; ?>" value="0"
                                    <?php
                                    if (isset($activity_eng_verif[$index]) && trim($activity_eng_verif[$index]) === "0") {
                                        echo 'checked';
                                    }
                                    ?>>
                                    Not Verified
                                </label><br>
                                <input type="file" name="verif_eng_file_<?php echo $index; ?>" accept="image/*"> Image (optional)<br><br>
                                <?php if (isset($activity_eng_images[$index]) && trim($activity_eng_images[$index]) !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($activity_eng_images[$index]); ?>" alt="Activity Engineering Control Image" style="max-width:100%;">
                                <?php else: ?>
                                    <p>No Image Uploaded</p>
                                <?php endif; ?>
                                <textarea class="verification-notes-input" name="verif_eng_notes_<?php echo $index; ?>" placeholder="Enter verification notes..." rows="5"><?php echo isset($activity_eng_notes_array[$index]) ? htmlspecialchars($activity_eng_notes_array[$index]) : ''; ?></textarea><br>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table class="summary-table">
                    <colgroup>
                        <col style="max-width:50%; width:50%;">
                        <col style="width:auto;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>ADMINISTRATIVE CONTROLS</th>
                            <th>AUDITOR VERIFICATION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <p>Dynamic Administrative Controls for each activity will be listed here with their answers</p>
                            </td>
                            <td>
                                <label>
                                    <input type="radio" name="verif_admin_<?php echo $index; ?>" value="1"
                                    <?php
                                    if (isset($activity_admin_verif[$index]) && trim($activity_admin_verif[$index]) === "1") {
                                        echo 'checked';
                                    }
                                    ?>>
                                    Verified
                                </label><br>
                                <label>
                                    <input type="radio" name="verif_admin_<?php echo $index; ?>" value="0"
                                    <?php
                                    if (isset($activity_admin_verif[$index]) && trim($activity_admin_verif[$index]) === "0") {
                                        echo 'checked';
                                    }
                                    ?>>
                                    Not Verified
                                </label><br>
                                <input type="file" name="verif_admin_file_<?php echo $index; ?>" accept="image/*"> Image (optional)<br><br>
                                <?php if (isset($activity_admin_images[$index]) && trim($activity_admin_images[$index]) !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($activity_admin_images[$index]); ?>" alt="Activity Administrative Control Image" style="max-width:100%;">
                                <?php else: ?>
                                    <p>No Image Uploaded</p>
                                <?php endif; ?>
                                <textarea class="verification-notes-input" name="verif_admin_notes_<?php echo $index; ?>" placeholder="Enter verification notes..." rows="5"><?php echo isset($activity_admin_notes_array[$index]) ? htmlspecialchars($activity_admin_notes_array[$index]) : ''; ?></textarea><br>
                            </td>
                        </tr>
                    </tbody>
                </table>
            <?php endforeach; ?>
            <div style="text-align: center;">
                <button id="save-activity-controls-btn" class="button large">Save Activity Controls Verification</button>
            </div>
        </div>

        <?php
        $savedAuditorSignature = !empty($verification_data['auditor_signature']) ? '../assets/images/signatures/' . $verification_data['auditor_signature'] : '';
        $canvasStyle = 'border: 2px solid #000; background: #fff; display: block; margin-bottom: 10px;';
        if (!empty($savedAuditorSignature)) {
            $canvasStyle = 'border: 2px solid #000; background: url(\'' . htmlspecialchars($savedAuditorSignature) . '\') no-repeat center center; background-size: contain; display: block; margin-bottom: 10px; pointer-events: none;';
        }
        ?>

        <div class="section-separator"></div>

        <p>When all controls have been verified, the "Submit Planning Verification" button will be unlocked.  At this point, the auditor should sign the verification using the appropriate field, save the signature, and then submit the planning verification.</p>
        <div id="signaturesContainer" style="display: flex; gap: 20px;">
            <div style="width:49%;" id="auditor-signing">
                <h3>Auditor Signature</h3>
                <canvas id="signature-pad" width="500" height="150" style="<?php echo $canvasStyle; ?>"></canvas>
                <?php if (empty($savedAuditorSignature)): ?>
                    <button id="clear-signature" class="button small" type="button">Clear</button>
                    <button id="save-signature" class="button small" type="button">Save Signature</button>
                    <input type="hidden" id="signature-data" name="signature_data">
                <?php else: ?>
                    <p>Signed by <?php echo htmlspecialchars($auditor_data['first_name'] . ' ' . $auditor_data['last_name']); ?> on <?php echo htmlspecialchars(date('F j, Y', strtotime($verification_data['verification_date']))); ?></p>
                <?php endif; ?>
            </div>

            <?php
            $admin_controls_maintenance = trim($verification_data['admin_controls_maintenance'] ?? '0');
            $admin_controls_housekeeping = trim($verification_data['admin_controls_housekeeping'] ?? '0');

            $admin_controls_hygene = isset($verification_data['admin_controls_hygene']) 
                ? trim($verification_data['admin_controls_hygene'])
                : (isset($verification_data[' admin_controls_hygene']) ? trim($verification_data[' admin_controls_hygene']) : '0');

            $admin_controls_training = isset($verification_data['admin_controls_training'])
                ? trim($verification_data['admin_controls_training'])
                : (isset($verification_data[' admin_controls_training']) ? trim($verification_data[' admin_controls_training']) : '0');

            $admin_controls_procedures = trim($verification_data['admin_controls_procedures'] ?? '0');
            $admin_controls_scheduling = trim($verification_data['admin_controls_scheduling'] ?? '0');
            $admin_controls_barriers = trim($verification_data['admin_controls_barriers'] ?? '0');
            $auditor_signature_verify = !empty($verification_data['auditor_signature']) ? '1' : '0';
            $isalreadyVerified = trim($planning['verified'] ?? '0');

            $singleVerified = (
                $admin_controls_maintenance === "1" &&
                $admin_controls_housekeeping === "1" &&
                $admin_controls_hygene === "1" &&
                $admin_controls_training === "1" &&
                $admin_controls_procedures === "1" &&
                $admin_controls_scheduling === "1" &&
                $admin_controls_barriers === "1" &&
                $auditor_signature_verify === "1"
            );

            $activity_admin_verified = true;
            if (!empty($activity_admin_verif)) {
                foreach ($activity_admin_verif as $value) {
                    if (trim($value) !== "1") {
                        $activity_admin_verified = false;
                        break;
                    }
                }
            }

            $activity_eng_verified = true;
            if (!empty($activity_eng_verif)) {
                foreach ($activity_eng_verif as $value) {
                    if (trim($value) !== "1") {
                        $activity_eng_verified = false;
                        break;
                    }
                }
            }

            $allVerified = $singleVerified && $activity_admin_verified && $activity_eng_verified && ($isalreadyVerified === "0");
            ?>

            <div id="submit-planning-verification-container" style="text-align: center; margin-top: 20px; width:50%;padding:50px 5%;">
                <label>
                    <input type="checkbox" id="verificationCheckbox">
                    I confirm the verification data submitted is complete and accurate.
                </label>
                <br /><br />
                <button style="width:100%;" id="submit-planning-verification" class="button large" <?php echo $allVerified ? '' : 'disabled'; ?>>
                    <?php echo ($isalreadyVerified === "1") ? "This Plan has already been verified" : "Submit Planning Verification"; ?>
                </button>
            </div>
        </div>

    </div>
</div>


<script type="text/javascript">

document.getElementById('save-admin-controls-btn').addEventListener('click', function(e) {
    e.preventDefault();

    var modal = document.createElement('div');
    modal.id = 'loading-modal';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100%';
    modal.style.height = '100%';
    modal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.zIndex = '9999';

    var content = document.createElement('div');
    content.style.backgroundColor = '#fff';
    content.style.padding = '20px';
    content.style.borderRadius = '5px';
    content.style.textAlign = 'center';

    var spinner = document.createElement('img');
    spinner.src = '../assets/images/spinner.gif';
    spinner.style.width = '50px';
    spinner.style.height = '50px';
    spinner.style.display = 'block';
    spinner.style.margin = '0 auto 10px';

    var text = document.createElement('p');
    text.textContent = "Your data is being saved, the page will be refreshed once completed.";

    content.appendChild(spinner);
    content.appendChild(text);
    modal.appendChild(content);
    document.body.appendChild(modal);

    var formData = new FormData();
    formData.append('plan_id', '<?php echo $plan_id; ?>');
    
    formData.append('verif_inspections', document.querySelector('input[name="verif_inspections"]:checked') ? document.querySelector('input[name="verif_inspections"]:checked').value : '');
    formData.append('verif_inspections_notes', document.querySelector('textarea[name="verif_inspections_notes"]').value);
    if(document.querySelector('input[name="verif_inspections_file"]').files[0]) {
        formData.append('verif_inspections_file', document.querySelector('input[name="verif_inspections_file"]').files[0]);
    }
    
    formData.append('verif_housekeeping', document.querySelector('input[name="verif_housekeeping"]:checked') ? document.querySelector('input[name="verif_housekeeping"]:checked').value : '');
    formData.append('verif_housekeeping_notes', document.querySelector('textarea[name="verif_housekeeping_notes"]').value);
    if(document.querySelector('input[name="verif_housekeeping_file"]').files[0]) {
        formData.append('verif_housekeeping_file', document.querySelector('input[name="verif_housekeeping_file"]').files[0]);
    }
    
    formData.append('verif_hygiene', document.querySelector('input[name="verif_hygiene"]:checked') ? document.querySelector('input[name="verif_hygiene"]:checked').value : '');
    formData.append('verif_hygiene_notes', document.querySelector('textarea[name="verif_hygiene_notes"]').value);
    if(document.querySelector('input[name="verif_hygiene_file"]').files[0]) {
        formData.append('verif_hygiene_file', document.querySelector('input[name="verif_hygiene_file"]').files[0]);
    }
    
    formData.append('verif_training', document.querySelector('input[name="verif_training"]:checked') ? document.querySelector('input[name="verif_training"]:checked').value : '');
    formData.append('verif_training_notes', document.querySelector('textarea[name="verif_training_notes"]').value);
    if(document.querySelector('input[name="verif_training_file"]').files[0]) {
        formData.append('verif_training_file', document.querySelector('input[name="verif_training_file"]').files[0]);
    }
    
    formData.append('verif_procedures', document.querySelector('input[name="verif_procedures"]:checked') ? document.querySelector('input[name="verif_procedures"]:checked').value : '');
    formData.append('verif_procedures_notes', document.querySelector('textarea[name="verif_procedures_notes"]').value);
    if(document.querySelector('input[name="verif_procedures_file"]').files[0]) {
        formData.append('verif_procedures_file', document.querySelector('input[name="verif_procedures_file"]').files[0]);
    }
    
    formData.append('verif_scheduling', document.querySelector('input[name="verif_scheduling"]:checked') ? document.querySelector('input[name="verif_scheduling"]:checked').value : '');
    formData.append('verif_scheduling_notes', document.querySelector('textarea[name="verif_scheduling_notes"]').value);
    if(document.querySelector('input[name="verif_scheduling_file"]').files[0]) {
        formData.append('verif_scheduling_file', document.querySelector('input[name="verif_scheduling_file"]').files[0]);
    }
    
    formData.append('verif_barriers', document.querySelector('input[name="verif_barriers"]:checked') ? document.querySelector('input[name="verif_barriers"]:checked').value : '');
    formData.append('verif_barriers_notes', document.querySelector('textarea[name="verif_barriers_notes"]').value);
    if(document.querySelector('input[name="verif_barriers_file"]').files[0]) {
        formData.append('verif_barriers_file', document.querySelector('input[name="verif_barriers_file"]').files[0]);
    }
    
    formData.append('verif_enclosures', document.querySelector('input[name="verif_enclosures"]:checked') ? document.querySelector('input[name="verif_enclosures"]:checked').value : '');
    formData.append('verif_enclosures_notes', document.querySelector('textarea[name="verif_enclosures_notes"]').value);
    if(document.querySelector('input[name="verif_enclosures_file"]').files[0]) {
        formData.append('verif_enclosures_file', document.querySelector('input[name="verif_enclosures_file"]').files[0]);
    }

    fetch('../ajax/save_admin_controls_verification.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {

        if(document.body.contains(modal)) {
            document.body.removeChild(modal);
        }
        if(data.success) {
            alert('Admin Controls Verification saved successfully.');
        } else {
            alert('Error: ' + data.message);
        }
        window.location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        if(document.body.contains(modal)) {
            document.body.removeChild(modal);
        }
        alert('AJAX error occurred.');
        window.location.reload();
    });
});


document.getElementById('save-activity-controls-btn').addEventListener('click', function(e) {
    console.log("save-activity-controls-btn clicked.");
    e.preventDefault();

    var modal = document.createElement('div');
    modal.id = 'loading-modal';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100%';
    modal.style.height = '100%';
    modal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.zIndex = '9999';

    var content = document.createElement('div');
    content.style.backgroundColor = '#fff';
    content.style.padding = '20px';
    content.style.borderRadius = '5px';
    content.style.textAlign = 'center';

    var spinner = document.createElement('img');
    spinner.src = '../assets/images/spinner.gif';
    spinner.style.width = '50px';
    spinner.style.height = '50px';
    spinner.style.display = 'block';
    spinner.style.margin = '0 auto 10px';

    var text = document.createElement('p');
    text.textContent = "Your data is being saved, the page will be refreshed once completed.";

    content.appendChild(spinner);
    content.appendChild(text);
    modal.appendChild(content);
    document.body.appendChild(modal);

    var formData = new FormData();
    formData.append('plan_id', <?php echo json_encode($plan_id); ?>);

    var activityCount = parseInt("<?php echo $activity_count; ?>", 10);
    var engControls = [];
    var engNotes = [];
    var adminControls = [];
    var adminNotes = [];

    for (var i = 0; i < activityCount; i++) {
        console.log("Processing activity " + i);
        var engInput = document.querySelector('input[name="verif_eng_' + i + '"]:checked');
        var engVal = engInput ? engInput.value : "";
        engControls.push(engVal);
        var engNoteEl = document.querySelector('textarea[name="verif_eng_notes_' + i + '"]');
        var engNote = engNoteEl ? engNoteEl.value : "";
        engNotes.push(engNote);
        var adminInput = document.querySelector('input[name="verif_admin_' + i + '"]:checked');
        var adminVal = adminInput ? adminInput.value : "";
        adminControls.push(adminVal);
        var adminNoteEl = document.querySelector('textarea[name="verif_admin_notes_' + i + '"]');
        var adminNote = adminNoteEl ? adminNoteEl.value : "";
        adminNotes.push(adminNote);

        var engFileInput = document.querySelector('input[name="verif_eng_file_' + i + '"]');
        if (engFileInput && engFileInput.files.length > 0) {
            formData.append('verif_eng_file[' + i + ']', engFileInput.files[0]);
        }
        var adminFileInput = document.querySelector('input[name="verif_admin_file_' + i + '"]');
        if (adminFileInput && adminFileInput.files.length > 0) {
            formData.append('verif_admin_file[' + i + ']', adminFileInput.files[0]);
        }
    }

    formData.append('activity_engineering_controls', engControls.join(","));
    formData.append('activity_engineering_controls_notes', engNotes.join(","));
    formData.append('activity_admin_controls', adminControls.join(","));
    formData.append('activity_admin_controls_notes', adminNotes.join(","));

    fetch('../ajax/save_activity_controls_verification.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(responseData => {
        if(document.body.contains(modal)) {
            document.body.removeChild(modal);
        }
        if (!responseData.success) {
            alert("Error: " + responseData.message);
        } else {
            alert("Activity verification data saved successfully.");
        }
        window.location.reload();
    })
    .catch(error => {
        console.error("AJAX error:", error);
        if(document.body.contains(modal)) {
            document.body.removeChild(modal);
        }
        alert("Failed to save verification data. Please try again.");
        window.location.reload();
    });
});

document.getElementById('submit-planning-verification').addEventListener('click', function(e) {
    e.preventDefault();

    var modal = document.createElement('div');
    modal.id = 'loading-modal';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100%';
    modal.style.height = '100%';
    modal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.zIndex = '9999';

    var content = document.createElement('div');
    content.style.backgroundColor = '#fff';
    content.style.padding = '20px';
    content.style.borderRadius = '5px';
    content.style.textAlign = 'center';

    var spinner = document.createElement('img');
    spinner.src = '../assets/images/spinner.gif';
    spinner.style.width = '50px';
    spinner.style.height = '50px';
    spinner.style.display = 'block';
    spinner.style.margin = '0 auto 10px';

    var text = document.createElement('p');
    text.textContent = "Saving final verification data, you will be redirected to the auditor tools page on success";

    content.appendChild(spinner);
    content.appendChild(text);
    modal.appendChild(content);
    document.body.appendChild(modal);

    var planId = <?php echo json_encode($plan_id); ?>;
    var data = { plan_id: planId };

    fetch('../ajax/submit_planning_verification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(responseData => {
        if (document.body.contains(modal)) {
            document.body.removeChild(modal);
        }
        if (responseData.success) {
            window.location.href = "index.php?page=auditor_tools";
        } else {
            alert("Error: " + responseData.message);
            window.location.reload();
        }
    })
    .catch(error => {
        console.error("AJAX error:", error);
        if (document.body.contains(modal)) {
            document.body.removeChild(modal);
        }
        window.location.reload();
    });
});

  const tabs = document.querySelectorAll('.tabs .tab');
  const tabContents = {
    "site-details-tab": document.getElementById("site-details-tab"),
    "general-admin-controls-tab": document.getElementById("general-admin-controls-tab"),
    "activity-controls-tab": document.getElementById("activity-controls-tab")
  };

  for (const key in tabContents) {
    if (tabContents.hasOwnProperty(key)) {
      tabContents[key].style.display = (key === "site-details-tab") ? 'block' : 'none';
    }
  }

  tabs.forEach(tab => {
    tab.addEventListener('click', function() {
      tabs.forEach(t => t.classList.remove('active'));
      for (const key in tabContents) {
        if (tabContents.hasOwnProperty(key)) {
          tabContents[key].style.display = 'none';
        }
      }
      this.classList.add('active');
      const tabId = this.getAttribute('data-tab');
      if(tabContents[tabId]) {
        tabContents[tabId].style.display = 'block';
      }
    });
  });

document.querySelectorAll('.ecp-tabs-link').forEach(link => {
    link.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelectorAll('.ecp-tabs-link').forEach(link => link.classList.remove('active'));
        document.querySelectorAll('.ecp-tab').forEach(tab => tab.classList.remove('active'));
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
    signCtx.strokeStyle = "#000";
    signCtx.lineWidth = 2;
    signCtx.lineCap = "round";
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
    document.getElementById("clear-signature").addEventListener("click", function () {
        signCtx.clearRect(0, 0, signCanvas.width, signCanvas.height);
    });
    document.getElementById("save-signature").addEventListener("click", function () {
        const signatureData = signCanvas.toDataURL("image/png");
        document.getElementById("signature-data").value = signatureData;
        const data = {
            plan_id: <?php echo json_encode($plan_id); ?>,
            signature_data: signatureData
        };
        fetch('../ajax/save_auditor_signature.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if(result.success){
                alert("Signature saved and uploaded!");
                window.location.reload();
            } else {
                alert("Error saving signature: " + result.message);
                window.location.reload();
            }
        })
        .catch(error => {
            console.error("AJAX error:", error);
            alert("AJAX error saving signature.");
            window.location.reload();
        });
    });

})();
</script>
