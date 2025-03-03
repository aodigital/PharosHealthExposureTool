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

// Fetch the current exposure planning data using the $plan_id
try {
    $stmt = $conn->prepare("SELECT * FROM Exposure_Plannings WHERE id = :plan_id AND user_id = :user_id");
    $stmt->bindParam(':plan_id', $plan_id);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $exposure_planning = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exposure_planning) {
        echo "Error: No exposure planning found.";
        exit();
    }

    // Fetch Exposure_Plannings_Meta data
    $stmt_meta = $conn->prepare("SELECT * FROM Exposure_Plannings_Meta WHERE planning_id = :plan_id");
    $stmt_meta->bindParam(':plan_id', $plan_id);
    $stmt_meta->execute();
    $meta_data = $stmt_meta->fetch(PDO::FETCH_ASSOC);

    // Parse saved values into arrays
    $activity_materials = isset($meta_data['activity_material']) ? explode(',', $meta_data['activity_material']) : [];
    $activity_tasks = isset($meta_data['activity_task']) ? explode(',', $meta_data['activity_task']) : [];
    $activity_tools = isset($meta_data['activity_tool']) ? explode(',', $meta_data['activity_tool']) : [];

    // Fetch Materials (used for dropdowns)
    $stmt_materials = $conn->prepare("SELECT * FROM Materials");
    $stmt_materials->execute();
    $materials_list = $stmt_materials->fetchAll(PDO::FETCH_ASSOC);

    // Determine max number of activities.
    $maxActivities = max(count($activity_materials), count($activity_tasks), count($activity_tools));
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<h2 class="more_space">Work Activity</h2>

<p>The <strong>work activity</strong> is the combination of <em>material</em>, <em>task</em>, and <em>tool</em> that will be performed on the average working day</p>

<form id="work-activity-form" method="post">
    <input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>">

    <div id="work-activities-container">
        <?php 
        for ($i = 0; $i < $maxActivities; $i++): 
            $selected_material = $activity_materials[$i] ?? null;
            $selected_task = $activity_tasks[$i] ?? null;
            $selected_tool = $activity_tools[$i] ?? null;
        ?>
            <div class="work-activity" data-activity-index="<?php echo $i; ?>">
                <div class="fourColumns">
                    <label>Material:</label>
                    <select name="material_<?php echo $i + 1; ?>" class="material-dropdown">
                        <option value="">Select Material</option>
                        <?php foreach ($materials_list as $material): ?>
                            <option value="<?php echo $material['id']; ?>" <?php echo ($selected_material == $material['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($material['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fourColumns">
                    <label>Task:</label>
                    <select name="task_<?php echo $i + 1; ?>" class="task-dropdown" <?php echo !$selected_material ? 'disabled' : ''; ?>>
                        <option value="">Select Task</option>
                        <?php if ($selected_material): ?>
                            <?php
                            $stmt_tasks = $conn->prepare("SELECT * FROM Tasks WHERE material_id = :material_id");
                            $stmt_tasks->bindParam(':material_id', $selected_material);
                            $stmt_tasks->execute();
                            $task_options = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <?php foreach ($task_options as $task): ?>
                                <option value="<?php echo $task['id']; ?>" <?php echo ($selected_task == $task['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($task['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="fourColumns">
                    <label>Tool:</label>
                    <select name="tool_<?php echo $i + 1; ?>" class="tool-dropdown" <?php echo !$selected_task ? 'disabled' : ''; ?>>
                        <option value="">Select Tool</option>
                        <?php if ($selected_task): ?>
                            <?php
                            $stmt_tools = $conn->prepare("SELECT * FROM Tools WHERE task_id = :task_id");
                            $stmt_tools->bindParam(':task_id', $selected_task);
                            $stmt_tools->execute();
                            $tool_options = $stmt_tools->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <?php foreach ($tool_options as $tool): ?>
                                <option value="<?php echo $tool['id']; ?>" <?php echo ($selected_tool == $tool['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tool['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <!-- Remove button added beside each activity -->
                <div class="fourColumns remove-activity-container" style="margin-top: 1.5em;">
                    <button type="button" class="button remove-activity-button" data-index="<?php echo $i; ?>">Remove Activity</button>
                </div>
            </div>
        <?php endfor; ?>
    </div>

    <button id="add-activity-button" type="button" class="button clearfix">Add Another Activity</button>

    <div class="clearfix">
        <a href="javascript:void(0)" class="button secondary left back-button silica-process-work-activity-save" data-step="silica_process_jobsite_details" id="work-activity-back-button">Back</a>
        <a href="javascript:void(0)" class="button save_continue secondary right continue-button silica-process-work-activity-save" data-step="silica_process_work_area_duration" id="work-activity-continue-button">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
    </div> 
</form>

<script>
$(document).ready(function () {
    // Event delegation for material dropdowns
    $(document).on('change', '.material-dropdown', function () {
        const $row = $(this).closest('.work-activity');
        const materialId = $(this).val();

        const $taskDropdown = $row.find('.task-dropdown');
        const $toolDropdown = $row.find('.tool-dropdown');
        $taskDropdown.empty().append('<option value="">Select Task</option>').prop('disabled', true);
        $toolDropdown.empty().append('<option value="">Select Tool</option>').prop('disabled', true);

        if (materialId) {
            $.ajax({
                url: '../ajax/fetch_tasks.php',
                type: 'POST',
                data: { material_id: materialId },
                dataType: 'json',
                success: function (tasks) {
                    tasks.forEach(task => {
                        $taskDropdown.append(`<option value="${task.id}">${task.name}</option>`);
                    });
                    $taskDropdown.prop('disabled', false);
                },
                error: function (xhr) {
                    console.error(xhr.responseText);
                },
            });
        }
    });

    $(document).on('change', '.task-dropdown', function () {
        const $row = $(this).closest('.work-activity');
        const taskId = $(this).val();

        const $toolDropdown = $row.find('.tool-dropdown');
        $toolDropdown.empty().append('<option value="">Select Tool</option>').prop('disabled', true);

        if (taskId) {
            $.ajax({
                url: '../ajax/fetch_tools.php',
                type: 'POST',
                data: { task_id: taskId },
                dataType: 'json',
                success: function (tools) {
                    tools.forEach(tool => {
                        $toolDropdown.append(`<option value="${tool.id}">${tool.name}</option>`);
                    });
                    $toolDropdown.prop('disabled', false);
                },
                error: function (xhr) {
                    console.error(xhr.responseText);
                },
            });
        }
    });

    // Add new activity row
    let activityCount = <?php echo $maxActivities; ?>;
    $('#add-activity-button').click(function () {
        const newIndex = activityCount; // 0-based index for new row
        activityCount++;
        const newRow = `
        <div class="work-activity" data-activity-index="${newIndex}">
            <div class="fourColumns">
                <label>Material:</label>
                <select name="material_${newIndex + 1}" class="material-dropdown">
                    <option value="">Select Material</option>
                    <?php foreach ($materials_list as $material): ?>
                        <option value="<?php echo $material['id']; ?>">
                            <?php echo htmlspecialchars($material['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fourColumns">
                <label>Task:</label>
                <select name="task_${newIndex + 1}" class="task-dropdown" disabled>
                    <option value="">Select Task</option>
                </select>
            </div>
            <div class="fourColumns">
                <label>Tool:</label>
                <select name="tool_${newIndex + 1}" class="tool-dropdown" disabled>
                    <option value="">Select Tool</option>
                </select>
            </div>
            <div class="fourColumns remove-activity-container" style="margin-top: 1.5em;">
                <button type="button" class="button remove-activity-button" data-index="${newIndex}">Remove Activity</button>
            </div>
        </div>`;
        $('#work-activities-container').append(newRow);
    });

    // Remove activity via AJAX and update the DOM.
    $(document).off('click', '.remove-activity-button').on('click', '.remove-activity-button', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation(); // Prevent duplicate events.
        const $activityRow = $(this).closest('.work-activity');
        const index = $(this).data('index');
        if (!confirm("Are you sure you want to remove this activity?")) {
            return;
        }
        $.ajax({
            url: '../ajax/remove_activity.php',
            type: 'POST',
            data: JSON.stringify({ plan_id: <?php echo json_encode($plan_id); ?>, activity_index: index }),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $activityRow.remove();
                } else {
                    alert("Error removing activity: " + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert("AJAX error: " + error);
            }
        });
    });

});
</script>
