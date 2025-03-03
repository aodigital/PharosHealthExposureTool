<?php 
    include '../includes/db.php'; 

    if (!isset($_SESSION['user'])) {
        header("Location: ../index.php?page=login_register");
        exit();
    }

    $user   = $_SESSION['user'];
    $plan_id = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : null;
    if (!$plan_id) {
        echo "Error: No valid plan ID provided.";
        exit();
    }

    try {
        $stmtPlanning = $conn->prepare("SELECT * FROM Exposure_Plannings WHERE id = :plan_id AND user_id = :user_id");
        $stmtPlanning->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
        $stmtPlanning->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
        $stmtPlanning->execute();
        $exposure_planning = $stmtPlanning->fetch(PDO::FETCH_ASSOC);
        if (!$exposure_planning) {
            echo "Error: No exposure planning found.";
            exit();
        }

        $stmtControls = $conn->prepare("SELECT * FROM Exposure_Plannings_Controls WHERE planning_id = :plan_id");
        $stmtControls->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
        $stmtControls->execute();
        $controls = $stmtControls->fetch(PDO::FETCH_ASSOC);

        $maintenance_general = isset($controls['admin_controls_maintenance']) ? $controls['admin_controls_maintenance'] : "0";
        $housekeeping_general = isset($controls['admin_controls_housekeeping']) ? $controls['admin_controls_housekeeping'] : "0";
        $hygene_general       = isset($controls['admin_controls_hygene']) ? $controls['admin_controls_hygene'] : "0";
        $training_general     = isset($controls['admin_controls_training']) ? $controls['admin_controls_training'] : "0";
        $procedures_general   = isset($controls['admin_controls_procedures']) ? $controls['admin_controls_procedures'] : "0";
        $scheduling_general   = isset($controls['admin_controls_scheduling']) ? $controls['admin_controls_scheduling'] : "0";
        $barriers_general     = isset($controls['admin_controls_barriers']) ? $controls['admin_controls_barriers'] : "0";
        $enclosures_general   = isset($controls['admin_controls_enclosures']) ? $controls['admin_controls_enclosures'] : "0";

        $stmtMeta = $conn->prepare("SELECT activity_task, activity_tool, activity_material FROM Exposure_Plannings_Meta WHERE planning_id = :plan_id");
        $stmtMeta->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
        $stmtMeta->execute();
        $meta = $stmtMeta->fetch(PDO::FETCH_ASSOC);

        $activity_tasks    = isset($meta['activity_task']) ? explode(',', $meta['activity_task']) : [];
        $activity_tools    = isset($meta['activity_tool']) ? explode(',', $meta['activity_tool']) : [];
        $activity_materials = isset($meta['activity_material']) ? explode(',', $meta['activity_material']) : [];
        $activity_count    = max(count($activity_tasks), count($activity_tools), count($activity_materials), 1);

        function getNameById($conn, $table, $id) {
            $stmt = $conn->prepare("SELECT name FROM {$table} WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchColumn() ?: 'N/A';
        }

        $task_names     = [];
        $tool_names     = [];
        $material_names = [];
        for ($i = 0; $i < $activity_count; $i++) {
            $task_id     = isset($activity_tasks[$i]) ? trim($activity_tasks[$i]) : '';
            $tool_id     = isset($activity_tools[$i]) ? trim($activity_tools[$i]) : '';
            $material_id = isset($activity_materials[$i]) ? trim($activity_materials[$i]) : '';
            $task_names[$i]     = $task_id ? getNameById($conn, 'Tasks', $task_id) : 'N/A';
            $tool_names[$i]     = $tool_id ? getNameById($conn, 'Tools', $tool_id) : 'N/A';
            $material_names[$i] = $material_id ? getNameById($conn, 'Materials', $material_id) : 'N/A';
        }

        function getAdminControlArray($field, $activity_count, $controls) {
            if (isset($controls[$field]) && strlen(trim($controls[$field])) > 0) {
                return explode(',', $controls[$field]);
            } else {
                return array_fill(0, $activity_count, "0");
            }
        }

        $admin_controls_maintenance_array = getAdminControlArray('admin_controls_maintenance', $activity_count, $controls);
        $admin_controls_housekeeping_array  = getAdminControlArray('admin_controls_housekeeping',  $activity_count, $controls);
        $admin_controls_hygene_array        = getAdminControlArray('admin_controls_hygene',        $activity_count, $controls);
        $admin_controls_training_array      = getAdminControlArray('admin_controls_training',      $activity_count, $controls);
        $admin_controls_procedures_array    = getAdminControlArray('admin_controls_procedures',    $activity_count, $controls);
        $admin_controls_scheduling_array    = getAdminControlArray('admin_controls_scheduling',    $activity_count, $controls);
        $admin_controls_barriers_array      = getAdminControlArray('admin_controls_barriers',      $activity_count, $controls);
        $admin_controls_enclosures_array    = getAdminControlArray('admin_controls_enclosures',    $activity_count, $controls);

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
        exit();
    }
?>
    <style>
        .admin-activity {
            border: 1px solid #ccc;
            margin-bottom: 1em;
            padding: 1em;
        }
        .form-group { margin-bottom: 1em; }
    </style>

    <h2 class="more_space">Administrative Controls</h2>
    <p>Administrative controls are work practices and policies planned and implemented with the goal to reduce the risk of RCS dust exposure.</p>
    <p>Answer the questions below for each work activity.</p>

<div class="admin-general">
    <h3>Jobsite Administrative Controls</h3>
    <!-- 1. Inspections & Maintenance -->
    <div class="form-group">
        <label>
            <strong>1. Inspections & Maintenance</strong>
            <p>Will you be implementing scheduled inspections and maintenance of engineering controls to ensure they are kept in good working order?</p>
        </label>
        <div>
            <label>
                <input type="radio" name="admin_controls_maintenance_general" value="1" <?php echo ($maintenance_general === "1") ? "checked" : ""; ?>> Yes
            </label>
            <label>
                <input type="radio" name="admin_controls_maintenance_general" value="0" <?php echo ($maintenance_general === "0") ? "checked" : ""; ?>> No
            </label>
        </div>
        <!-- New fields for this question -->
        <div class="form-group">
            <label for="admin_controls_maintenance_upload">Upload Verification Image</label>
            <input type="file" name="admin_controls_maintenance_upload" id="admin_controls_maintenance_upload" accept="image/*">
        </div>
        <div class="form-group">
            <label for="admin_controls_maintenance_note">Verification Notes</label>
            <textarea name="admin_controls_maintenance_note" id="admin_controls_maintenance_note"></textarea>
        </div>
    </div>

    <!-- 2. Housekeeping -->
    <div class="form-group">
        <label>
            <strong>2. Housekeeping</strong>
            <p>At the end of every work shift, will you be cleaning the work area and equipment from accumulated dust?</p>
        </label>
        <div>
            <label>
                <input type="radio" name="admin_controls_housekeeping_general" value="1" <?php echo ($housekeeping_general === "1") ? "checked" : ""; ?>> Yes
            </label>
            <label>
                <input type="radio" name="admin_controls_housekeeping_general" value="0" <?php echo ($housekeeping_general === "0") ? "checked" : ""; ?>> No
            </label>
        </div>
        <!-- New fields -->
        <div class="form-group">
            <label for="admin_controls_housekeeping_upload">Upload Verification Image</label>
            <input type="file" name="admin_controls_housekeeping_upload" id="admin_controls_housekeeping_upload" accept="image/*">
        </div>
        <div class="form-group">
            <label for="admin_controls_housekeeping_note">Verification Notes</label>
            <textarea name="admin_controls_housekeeping_note" id="admin_controls_housekeeping_note"></textarea>
        </div>
    </div>

    <!-- 3. Hygiene -->
    <div class="form-group">
        <label>
            <strong>3. Hygiene</strong>
            <p>At the end of every work shift, will workers and PPE be decontaminated to prevent inadvertent secondary inhalation of RCS dust?</p>
        </label>
        <div>
            <label>
                <input type="radio" name="admin_controls_hygene_general" value="1" <?php echo ($hygene_general === "1") ? "checked" : ""; ?>> Yes
            </label>
            <label>
                <input type="radio" name="admin_controls_hygene_general" value="0" <?php echo ($hygene_general === "0") ? "checked" : ""; ?>> No
            </label>
        </div>
        <!-- New fields -->
        <div class="form-group">
            <label for="admin_controls_hygene_upload">Upload Verification Image</label>
            <input type="file" name="admin_controls_hygene_upload" id="admin_controls_hygene_upload" accept="image/*">
        </div>
        <div class="form-group">
            <label for="admin_controls_hygene_note">Verification Notes</label>
            <textarea name="admin_controls_hygene_note" id="admin_controls_hygene_note"></textarea>
        </div>
    </div>

    <!-- 4. Silica Safety Instruction & Training -->
    <div class="form-group">
        <label>
            <strong>4. Silica Safety Instruction & Training</strong>
            <p>Will your workers be instructed and trained in how to safely work within environments where RCS dust exposure is a risk?</p>
        </label>
        <div>
            <label>
                <input type="radio" name="admin_controls_training_general" value="1" <?php echo ($training_general === "1") ? "checked" : ""; ?>> Yes
            </label>
            <label>
                <input type="radio" name="admin_controls_training_general" value="0" <?php echo ($training_general === "0") ? "checked" : ""; ?>> No
            </label>
        </div>
        <!-- New fields -->
        <div class="form-group">
            <label for="admin_controls_training_upload">Upload Verification Image</label>
            <input type="file" name="admin_controls_training_upload" id="admin_controls_training_upload" accept="image/*">
        </div>
        <div class="form-group">
            <label for="admin_controls_training_note">Verification Notes</label>
            <textarea name="admin_controls_training_note" id="admin_controls_training_note"></textarea>
        </div>
    </div>

    <!-- 5. Exposure Emergency Preparedness -->
    <div class="form-group">
        <label>
            <strong>5. Exposure Emergency Preparedness</strong>
            <p>Will your jobsite be prepared for a RCS dust exposure emergency?</p>
        </label>
        <div>
            <label>
                <input type="radio" name="admin_controls_procedures_general" value="1" <?php echo ($procedures_general === "1") ? "checked" : ""; ?>> Yes
            </label>
            <label>
                <input type="radio" name="admin_controls_procedures_general" value="0" <?php echo ($procedures_general === "0") ? "checked" : ""; ?>> No
            </label>
        </div>
        <!-- New fields -->
        <div class="form-group">
            <label for="admin_controls_procedures_upload">Upload Verification Image</label>
            <input type="file" name="admin_controls_procedures_upload" id="admin_controls_procedures_upload" accept="image/*">
        </div>
        <div class="form-group">
            <label for="admin_controls_procedures_note">Verification Notes</label>
            <textarea name="admin_controls_procedures_note" id="admin_controls_procedures_note"></textarea>
        </div>
    </div>

    <!-- 6. Work Shift Scheduling -->
    <div class="form-group">
        <label>
            <strong>6. Work Shift Scheduling</strong>
            <p>Will you be scheduling work shifts to limit the amount of time an individual worker is exposed to RCS dust?</p>
        </label>
        <div>
            <label>
                <input type="radio" name="admin_controls_scheduling_general" value="1" <?php echo ($scheduling_general === "1") ? "checked" : ""; ?>> Yes
            </label>
            <label>
                <input type="radio" name="admin_controls_scheduling_general" value="0" <?php echo ($scheduling_general === "0") ? "checked" : ""; ?>> No
            </label>
        </div>
        <!-- New fields -->
        <div class="form-group">
            <label for="admin_controls_scheduling_upload">Upload Verification Image</label>
            <input type="file" name="admin_controls_scheduling_upload" id="admin_controls_scheduling_upload" accept="image/*">
        </div>
        <div class="form-group">
            <label for="admin_controls_scheduling_note">Verification Notes</label>
            <textarea name="admin_controls_scheduling_note" id="admin_controls_scheduling_note"></textarea>
        </div>
    </div>

    <!-- 7. Barriers -->
    <div class="form-group">
        <label>
            <strong>7. Barriers</strong>
            <p>Will you use a barrier to isolate the work area from the rest of the construction project and to prevent entry by unauthorized workers?</p>
        </label>
        <div>
            <label>
                <input type="radio" name="admin_controls_barriers_general" value="1" <?php echo ($barriers_general === "1") ? "checked" : ""; ?>> Yes
            </label>
            <label>
                <input type="radio" name="admin_controls_barriers_general" value="0" <?php echo ($barriers_general === "0") ? "checked" : ""; ?>> No
            </label>
        </div>
        <!-- New fields -->
        <div class="form-group">
            <label for="admin_controls_barriers_upload">Upload Verification Image</label>
            <input type="file" name="admin_controls_barriers_upload" id="admin_controls_barriers_upload" accept="image/*">
        </div>
        <div class="form-group">
            <label for="admin_controls_barriers_note">Verification Notes</label>
            <textarea name="admin_controls_barriers_note" id="admin_controls_barriers_note"></textarea>
        </div>
    </div>

    <!-- 8. Enclosures -->
    <div class="form-group">
        <label>
            <strong>8. Enclosures</strong>
            <p>Will you use an enclosure to physically contain the dusty atmosphere?</p>
        </label>
        <div>
            <label>
                <input type="radio" name="admin_controls_enclosures_general" value="1" <?php echo ($enclosures_general === "1") ? "checked" : ""; ?>> Yes
            </label>
            <label>
                <input type="radio" name="admin_controls_enclosures_general" value="0" <?php echo ($enclosures_general === "0") ? "checked" : ""; ?>> No
            </label>
        </div>
        <!-- New fields -->
        <div class="form-group">
            <label for="admin_controls_enclosures_upload">Upload Verification Image</label>
            <input type="file" name="admin_controls_enclosures_upload" id="admin_controls_enclosures_upload" accept="image/*">
        </div>
        <div class="form-group">
            <label for="admin_controls_enclosures_note">Verification Notes</label>
            <textarea name="admin_controls_enclosures_note" id="admin_controls_enclosures_note"></textarea>
        </div>
    </div>
</div>

<div class="clearfix">
    <a href="javascript:void(0)" class="button secondary left load-step-administrative-controls-back" data-step="exposure_control_engineering_controls">Back</a>
    <a href="javascript:void(0)" class="button save_continue secondary right load-step-administrative-controls-next" data-step="exposure_control_exposure_control_summary">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
</div>


    <script>
    document.querySelectorAll('.load-step-administrative-controls-back, .load-step-administrative-controls-next').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();

            const maintenance   = document.querySelector('input[name="admin_controls_maintenance_general"]:checked');
            const housekeeping  = document.querySelector('input[name="admin_controls_housekeeping_general"]:checked');
            const hygene        = document.querySelector('input[name="admin_controls_hygene_general"]:checked');
            const training      = document.querySelector('input[name="admin_controls_training_general"]:checked');
            const procedures    = document.querySelector('input[name="admin_controls_procedures_general"]:checked');
            const scheduling    = document.querySelector('input[name="admin_controls_scheduling_general"]:checked');
            const barriers      = document.querySelector('input[name="admin_controls_barriers_general"]:checked');
            const enclosures    = document.querySelector('input[name="admin_controls_enclosures_general"]:checked');

            var data = {
                plan_id: <?php echo json_encode($plan_id); ?>,
                admin_controls_maintenance:   maintenance ? maintenance.value : "0",
                admin_controls_housekeeping:  housekeeping ? housekeeping.value : "0",
                admin_controls_hygene:        hygene ? hygene.value : "0",
                admin_controls_training:      training ? training.value : "0",
                admin_controls_procedures:    procedures ? procedures.value : "0",
                admin_controls_scheduling:    scheduling ? scheduling.value : "0",
                admin_controls_barriers:      barriers ? barriers.value : "0",
                admin_controls_enclosures:    enclosures ? enclosures.value : "0"
            };

            fetch('../ajax/save_admin_controls.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    console.error('Error saving data:', result.message);
                } else {
                    const nextStep = button.getAttribute('data-step');
                }
            })
            .catch(error => console.error('AJAX error:', error));
        });
    });
    </script>

