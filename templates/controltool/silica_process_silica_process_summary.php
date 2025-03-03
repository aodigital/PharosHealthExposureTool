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
    // Fetch Exposure Planning
    $stmt = $conn->prepare("SELECT * FROM Exposure_Plannings WHERE id = :plan_id AND user_id = :user_id");
    $stmt->bindParam(':plan_id', $plan_id);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $exposure_planning = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exposure_planning) {
        echo "Error: No exposure planning found.";
        exit();
    }

    // Fetch Meta Data
    $stmt_meta = $conn->prepare("SELECT * FROM Exposure_Plannings_Meta WHERE planning_id = :plan_id");
    $stmt_meta->bindParam(':plan_id', $plan_id);
    $stmt_meta->execute();
    $meta_data = $stmt_meta->fetch(PDO::FETCH_ASSOC);

    // Prepare Work Activity Details
    $activity_materials = isset($meta_data['activity_material']) ? explode(',', $meta_data['activity_material']) : [];
    $activity_tasks = isset($meta_data['activity_task']) ? explode(',', $meta_data['activity_task']) : [];
    $activity_tools = isset($meta_data['activity_tool']) ? explode(',', $meta_data['activity_tool']) : [];
    $work_areas = isset($meta_data['work_area']) ? explode(',', $meta_data['work_area']) : [];
    $durations = isset($meta_data['avg_hr_per_shift']) ? explode(',', $meta_data['avg_hr_per_shift']) : [];

    // Fetch names for materials, tasks, and tools
    $work_activities = [];
    for ($i = 0; $i < max(count($activity_materials), count($activity_tasks), count($activity_tools)); $i++) {
        $material_id = $activity_materials[$i] ?? null;
        $task_id = $activity_tasks[$i] ?? null;
        $tool_id = $activity_tools[$i] ?? null;
        $work_area = $work_areas[$i] ?? 'N/A';
        $duration = $durations[$i] ?? 'N/A';

        // Fetch material name
        $material_name = 'N/A';
        if ($material_id) {
            $stmt_material = $conn->prepare("SELECT name FROM Materials WHERE id = :id");
            $stmt_material->bindParam(':id', $material_id);
            $stmt_material->execute();
            $material_name = $stmt_material->fetchColumn() ?: 'N/A';
        }

        // Fetch task name
        $task_name = 'N/A';
        if ($task_id) {
            $stmt_task = $conn->prepare("SELECT name FROM Tasks WHERE id = :id");
            $stmt_task->bindParam(':id', $task_id);
            $stmt_task->execute();
            $task_name = $stmt_task->fetchColumn() ?: 'N/A';
        }

        // Fetch tool name
        $tool_name = 'N/A';
        if ($tool_id) {
            $stmt_tool = $conn->prepare("SELECT name FROM Tools WHERE id = :id");
            $stmt_tool->bindParam(':id', $tool_id);
            $stmt_tool->execute();
            $tool_name = $stmt_tool->fetchColumn() ?: 'N/A';
        }

        // Combine into a single activity description
        $work_activities[] = [
            'description' => "$task_name $material_name with $tool_name",
            'work_area' => htmlspecialchars($work_area),
            'duration' => htmlspecialchars($duration)
        ];
    }

    // Prepare Other Details
    $jobsite_sector = $meta_data['jobsite_type'] ?? 'N/A';
    $project_type = $meta_data['project_type'] ?? 'N/A';

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}


?>


    <h2 class="more_space">Silica Process Summary</h2>

    <p>Review the details of your silica process below. You can edit any step if needed by clicking the respective buttons.</p>

    <!-- Table for large screens -->
    <div class="hide-for-small">
        <table class="summary-table">
            <thead>
                <tr>
                    <th>Work Activity</th>
                    <th>Work Area</th>
                    <th>Duration per Shift (Avg.)</th>
                    <th>Jobsite Sector</th>
                    <th>Project Type</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($work_activities as $activity): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($activity['description']); ?></td>
                        <td><?php echo htmlspecialchars($activity['work_area']); ?></td>
                        <td><?php echo htmlspecialchars($activity['duration']); ?></td>
                        <td><?php echo htmlspecialchars($jobsite_sector); ?></td>
                        <td><?php echo htmlspecialchars($project_type); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Table for small screens -->
    <div class="show-for-small">
        <?php foreach ($work_activities as $activity): ?>
            <table class="summary-table">
                <tbody>
                
                    <tr>
                        <th>Work Activity</th>
                        <td><?php echo htmlspecialchars($activity['description']); ?></td>
                    </tr>
                    <tr>
                        <th>Work Area</th>
                        <td><?php echo htmlspecialchars($activity['work_area']); ?></td>
                    </tr>
                    <tr>
                        <th>Duration per Shift (Avg.)</th>
                        <td><?php echo htmlspecialchars($activity['duration']); ?></td>
                    </tr>
                
                </tbody>
            </table>
        <?php endforeach; ?>
        <table class="summary-table">
            <tbody>
                <tr>
                    <th>Jobsite Sector</th>
                    <td><?php echo htmlspecialchars($jobsite_sector); ?></td>
                </tr>
                <tr>
                    <th>Project Type</th>
                    <td><?php echo htmlspecialchars($project_type); ?></td>
                </tr>
            </tbody>
        </table>

    </div>


    <!-- Edit Buttons -->
    <h6 class="more_space">Do you wish to edit?</h6>
    <div class="button-bar">
        <ul class="button-group">
            <li><a href="javascript:void(0)" class="button small load-step" data-step="silica_process_work_activity">Edit Work Activity</a></li>
            <li><a href="javascript:void(0)" class="button small load-step" data-step="silica_process_work_area_duration">Edit Work Area or Duration</a></li>
            <li><a href="javascript:void(0)" class="button small load-step" data-step="silica_process_jobsite_details">Edit Jobsite Sector or Project Type</a></li>
        </ul>
    </div>

    <hr>

    <!-- Navigation Buttons -->
    <div class="clearfix">
        <a href="javascript:void(0)" class="button secondary left load-step" data-step="silica_process_work_area_duration"><i class="fa fa-caret-left"></i>&nbsp;&nbsp;Back</a>
        <a href="javascript:void(0)" class="button save_continue secondary right load-step" data-step="silica_exposure_no_controls_get_prepared">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
    </div>
