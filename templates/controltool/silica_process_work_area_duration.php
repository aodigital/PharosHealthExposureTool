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
    // Fetch the exposure planning
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
    // (Note: the joined names here will only return the first set, so we will look up each ID separately)
    $stmt_meta = $conn->prepare("
        SELECT 
            activity_material, activity_task, activity_tool,
            work_area, avg_hr_per_shift
        FROM Exposure_Plannings_Meta
        WHERE planning_id = :plan_id
    ");
    $stmt_meta->bindParam(':plan_id', $plan_id);
    $stmt_meta->execute();
    $meta_data = $stmt_meta->fetch(PDO::FETCH_ASSOC);

    // Parse stored values into arrays
    $activity_materials = isset($meta_data['activity_material']) ? explode(',', $meta_data['activity_material']) : [];
    $activity_tasks     = isset($meta_data['activity_task']) ? explode(',', $meta_data['activity_task']) : [];
    $activity_tools     = isset($meta_data['activity_tool']) ? explode(',', $meta_data['activity_tool']) : [];
    $work_areas         = isset($meta_data['work_area']) ? explode(',', $meta_data['work_area']) : [];
    $durations          = isset($meta_data['avg_hr_per_shift']) ? explode(',', $meta_data['avg_hr_per_shift']) : [];

    // Ensure at least one activity is present
    $activity_count = max(count($activity_materials), count($activity_tasks), count($activity_tools), 1);

    // Helper function to retrieve name by ID
    function getNameById($conn, $table, $id) {
        $stmt = $conn->prepare("SELECT name FROM {$table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() ?: 'N/A';
    }

    // Build arrays of names for each activity
    $material_names = [];
    $task_names = [];
    $tool_names = [];
    for ($i = 0; $i < $activity_count; $i++) {
        $material_id = isset($activity_materials[$i]) ? trim($activity_materials[$i]) : '';
        $task_id     = isset($activity_tasks[$i]) ? trim($activity_tasks[$i]) : '';
        $tool_id     = isset($activity_tools[$i]) ? trim($activity_tools[$i]) : '';

        $material_names[$i] = $material_id ? getNameById($conn, 'Materials', $material_id) : 'N/A';
        $task_names[$i]     = $task_id ? getNameById($conn, 'Tasks', $task_id) : 'N/A';
        $tool_names[$i]     = $tool_id ? getNameById($conn, 'Tools', $tool_id) : 'N/A';
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

    <h2 class="more_space">Work Area & Duration</h2>

    <p><em>Where</em> and <em>how long</em> the work activity takes place can amplify the exposure risk.</p>
    <p>If work is inside for more than 25% of time it is considered inside.  Inside is where a roof and 1 wall is within 4m from activity</p>

    <form id="work-area-duration-form" method="post">
        <input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>">

        <div id="work-areas-container">
            <?php for ($i = 0; $i < $activity_count; $i++): ?>
                <div class="work-area-entry" data-index="<?php echo $i; ?>">
                    <!-- Display Activity Details -->
                    <h4>Activity <?php echo $i + 1; ?>: 
                        <?php 
                        echo htmlspecialchars(
                            ($task_names[$i] ?? 'N/A') . ' ' .
                            ($material_names[$i] ?? 'N/A') . ' with ' .
                            ($tool_names[$i] ?? 'N/A')
                        ); 
                        ?>
                    </h4>

                    <!-- Work Area Dropdown -->
                    <label>Work Area for Activity <?php echo $i + 1; ?>:</label>
                    <select class="work_area" name="work_area_<?php echo $i; ?>">
                        <option value="">Select Work Area</option>
                        <option value="Inside" <?php echo (isset($work_areas[$i]) && trim($work_areas[$i]) == 'Inside') ? 'selected' : ''; ?>>Inside</option>
                        <option value="Outside" <?php echo (isset($work_areas[$i]) && trim($work_areas[$i]) == 'Outside') ? 'selected' : ''; ?>>Outside</option>
                        <option value="Restricted Space" <?php echo (isset($work_areas[$i]) && trim($work_areas[$i]) == 'Restricted Space') ? 'selected' : ''; ?>>
                            Restricted Space (e.g. staircase, tunnel, confined area)
                        </option>
                    </select>

                    <!-- Work Duration Input -->
                    <label>Work Duration per Shift (hours) for Activity <?php echo $i + 1; ?>:</label>
                    <input type="number" class="avg_hr_per_shift" name="avg_hr_per_shift_<?php echo $i; ?>" 
                           value="<?php echo htmlspecialchars($durations[$i] ?? ''); ?>" min="0" placeholder="Enter work duration"/>
                </div>
            <?php endfor; ?>
        </div>

        <button type="button" id="add-work-area" class="button">Add Another Work Area</button>

        <div class="clearfix">
            <a href="javascript:void(0)" class="button secondary left back-button silica-work-area-duration-save" data-step="silica_process_work_activity" id="work-area-duration-back">Back</a>
            <a href="javascript:void(0)" class="button save_continue secondary right continue-button silica-work-area-duration-save" data-step="silica_process_silica_process_summary" id="work-area-duration-continue">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
        </div>
    </form>

    <script>
        // Your JavaScript (if needed)
    </script>
