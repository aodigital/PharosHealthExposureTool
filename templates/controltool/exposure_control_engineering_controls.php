<?php
include '../includes/db.php'; // Assuming this file contains the DB connection

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
    $stmtControls = $conn->prepare("
        SELECT engineering_controls, engineering_controls_details,
               engineering_controls_notes, engineering_controls_image
        FROM Exposure_Plannings_Controls 
        WHERE planning_id = :planning_id
    ");
    $stmtControls->bindParam(':planning_id', $plan_id, PDO::PARAM_INT);
    $stmtControls->execute();
    $controlsResult = $stmtControls->fetch(PDO::FETCH_ASSOC);

    // These CSV strings contain one value per work activity.
    $engineering_controls_csv = $controlsResult['engineering_controls'] ?? '';
    $engineering_controls_details_csv = $controlsResult['engineering_controls_details'] ?? '';
    $engineering_controls_notes_csv = $controlsResult['engineering_controls_notes'] ?? '';
    $engineering_controls_images_csv = $controlsResult['engineering_controls_image'] ?? '';

    // Fetch work activity meta data for this planning
    $stmtMeta = $conn->prepare("
        SELECT activity_task, activity_tool, activity_material 
        FROM Exposure_Plannings_Meta 
        WHERE planning_id = :planning_id
    ");
    $stmtMeta->bindParam(':planning_id', $plan_id, PDO::PARAM_INT);
    $stmtMeta->execute();
    $meta = $stmtMeta->fetch(PDO::FETCH_ASSOC);

    // Explode CSV fields into arrays (if available)
    $activity_tasks    = isset($meta['activity_task']) ? explode(',', $meta['activity_task']) : [];
    $activity_tools    = isset($meta['activity_tool']) ? explode(',', $meta['activity_tool']) : [];
    $activity_materials = isset($meta['activity_material']) ? explode(',', $meta['activity_material']) : [];

    // Determine how many work activities there are
    $activity_count = max(count($activity_tasks), count($activity_tools), count($activity_materials), 1);

    // Helper function to get names for an ID from a table
    function getNameById($conn, $table, $id) {
        $stmt = $conn->prepare("SELECT name FROM {$table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
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

    // Preload engineering controls answers for each work activity.
    $engineering_controls_array = (strlen(trim($engineering_controls_csv)) > 0)
        ? explode(',', $engineering_controls_csv)
        : array_fill(0, $activity_count, '');

    // Preload details (for the "not_listed" option)
    $engineering_controls_details_array = (strlen(trim($engineering_controls_details_csv)) > 0)
        ? explode(',', $engineering_controls_details_csv)
        : array_fill(0, $activity_count, '');

    // Preload verification notes (for each activity)
    $engineering_controls_notes_array = (strlen(trim($engineering_controls_notes_csv)) > 0)
        ? explode(',', $engineering_controls_notes_csv)
        : array_fill(0, $activity_count, '');

    // Preload saved image filenames (if any)
    $engineering_controls_images_array = (strlen(trim($engineering_controls_images_csv)) > 0)
        ? explode(',', $engineering_controls_images_csv)
        : array_fill(0, $activity_count, '');
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<style>
    .hidden {
        display: none;
    }
    .engineering-not-listed-text {
        width: 100%;
        margin-top: 10px;
        padding: 8px;
    }
    .engineering-activity {
        border: 1px solid #ccc;
        margin-bottom: 1em;
        padding: 1em;
    }
    .devNotice {
        font-style: italic;
        color: #555;
    }
</style>

<h2 class="more_space">Engineering Controls</h2>
<p>Engineering controls are engineered methods built into the design of equipment, process, or plant to minimize hazardous exposure.</p>
<h4 class="devNotice">More control options will be added here dynamically based on the task, material, and tools. (Database still being populated manually.)</h4>
<form id="engineering-controls-form">
    <?php for ($i = 0; $i < $activity_count; $i++): ?>
        <div class="engineering-activity" data-index="<?php echo $i; ?>">
            <h3>Engineering Controls for Activity <?php echo $i + 1; ?>:</h3>
            <p>
                <?php 
                echo htmlspecialchars(
                    ($task_names[$i] ?? 'N/A') . ' ' .
                    ($material_names[$i] ?? 'N/A') . ' with ' .
                    ($tool_names[$i] ?? 'N/A')
                );
                ?>
            </p>
            <?php
                // Query matching engineering controls for this activity.
                $task_id = isset($activity_tasks[$i]) ? trim($activity_tasks[$i]) : '';
                $tool_id = isset($activity_tools[$i]) ? trim($activity_tools[$i]) : '';
                $material_id = isset($activity_materials[$i]) ? trim($activity_materials[$i]) : '';
                $control_options = [];
                if (!empty($task_id) && !empty($tool_id) && !empty($material_id)) {
                    $stmtCtrl = $conn->prepare("SELECT engineering_control_id, engineering_control_name FROM engineering_controls WHERE task_id = :task_id AND tool_id = :tool_id AND material_id = :material_id");
                    $stmtCtrl->bindParam(':task_id', $task_id, PDO::PARAM_INT);
                    $stmtCtrl->bindParam(':tool_id', $tool_id, PDO::PARAM_INT);
                    $stmtCtrl->bindParam(':material_id', $material_id, PDO::PARAM_INT);
                    $stmtCtrl->execute();
                    $control_options = $stmtCtrl->fetchAll(PDO::FETCH_ASSOC);
                }
            ?>
            <?php if (!empty($control_options)): ?>
                <div class="form-group">
                    <p>Available Engineering Controls:</p>
                    <?php foreach ($control_options as $control): ?>
                        <label>
                            <input type="radio" name="engineering_control_<?php echo $i; ?>" 
                                   value="<?php echo htmlspecialchars($control['engineering_control_id']); ?>"
                                   <?php echo ($engineering_controls_array[$i] === (string)$control['engineering_control_id']) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($control['engineering_control_name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label>
                    <input type="radio" name="engineering_control_<?php echo $i; ?>" value="not_listed" class="engineering-not-listed-radio" id="engineering-not-listed-<?php echo $i; ?>"
                        <?php echo ($engineering_controls_array[$i] === 'not_listed') ? 'checked' : ''; ?>>
                    Engineering control not listed
                </label>
                <textarea id="engineering-not-listed-text-<?php echo $i; ?>" class="engineering-not-listed-text <?php echo ($engineering_controls_array[$i] === 'not_listed') ? '' : 'hidden'; ?>" 
                          placeholder="Please describe the engineering control..."><?php echo htmlspecialchars($engineering_controls_details_array[$i]); ?></textarea>
            </div>
            <div class="form-group">
                <label>
                    <input type="radio" name="engineering_control_<?php echo $i; ?>" value="none" id="engineering-none-<?php echo $i; ?>"
                        <?php echo ($engineering_controls_array[$i] === 'none') ? 'checked' : ''; ?>>
                    You DO NOT intend to use an engineering control.
                </label>
            </div>
            <div class="sidebar-separator"></div>
            <div class="form-group side-by-side">
                <div class="note-col">
                    <label for="engineering_control_note_<?php echo $i; ?>">Verification Notes</label>
                    <textarea rows="12" style="padding:10px;" name="engineering_control_note_<?php echo $i; ?>" id="engineering_control_note_<?php echo $i; ?>"><?php echo htmlspecialchars($engineering_controls_notes_array[$i]); ?></textarea>
                </div>
                <div class="upload-col">
                    <div class="form-group">
                        <label for="engineering_control_upload_<?php echo $i; ?>">Upload Verification Image</label>
                        <input type="file" name="engineering_control_upload_<?php echo $i; ?>" id="engineering_control_upload_<?php echo $i; ?>" accept="image/*"><br />
                    </div>
                    <?php if (!empty($engineering_controls_images_array[$i])): ?>
                        <div class="form-group">
                            <p>Existing Verification Image:</p>
                            <img src="../assets/uploads/images/<?php echo htmlspecialchars($engineering_controls_images_array[$i]); ?>" alt="Verification Image" style="max-width:100%;">
                        </div>
                    <?php endif; ?>
                </div>
                
            </div>

    </div>
    <?php endfor; ?>
    <br />
</form>


<div class="clearfix">
    <a href="javascript:void(0)" class="button secondary left engineering-controls-back-button" data-step="exposure_control_risk_elimination_substitution">Back</a>
    <a href="javascript:void(0)" class="button save_continue secondary right engineering-controls-next-button" data-step="exposure_control_administrative_controls">Next</a>
</div>

<script type="text/javascript">
    // Function to show the loading modal
    function showLoadingModal() {
        // Create modal element if it doesn't already exist
        if (!document.getElementById('loading-modal')) {
            var modal = document.createElement('div');
            modal.id = 'loading-modal';
            modal.style.position = 'fixed';
            modal.style.top = 0;
            modal.style.left = 0;
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.background = 'rgba(0,0,0,0.6)';
            modal.style.display = 'flex';
            modal.style.alignItems = 'center';
            modal.style.justifyContent = 'center';
            modal.style.zIndex = '9999';
            modal.innerHTML = '<div style="background:#fff;padding:20px;border-radius:5px;text-align:center;">' +
                '<img src="../assets/images/spinner.gif" alt="Loading..." style="width:50px;height:50px;"><br>' +
                '<p>Your data is being saved. Please wait...</p>' +
                '</div>';
            document.body.appendChild(modal);
        }
    }

    // Function to hide the loading modal
    function hideLoadingModal() {
        var modal = document.getElementById('loading-modal');
        if (modal) {
            modal.parentNode.removeChild(modal);
        }
    }

    var activityCount = <?php echo $activity_count; ?>;
    
    // For each activity, toggle the textarea when "not_listed" is chosen.
    for (let i = 0; i < activityCount; i++) {
        (function(index) {
            var notListedRadio = document.getElementById("engineering-not-listed-" + index);
            var textArea = document.getElementById("engineering-not-listed-text-" + index);
            notListedRadio.addEventListener('change', function() {
                textArea.classList.toggle('hidden', !this.checked);
            });
        })(i);
    }
    
    // When the back or next button is clicked, gather all answers and send via AJAX.
    document.querySelectorAll('.engineering-controls-back-button, .engineering-controls-next-button').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Show loading modal while the request is processing
            showLoadingModal();
            
            // Gather radio button values as before.
            var engineeringControlsValues = [];
            var engineeringControlsDetailsValues = [];
            for (let i = 0; i < activityCount; i++) {
                var radios = document.getElementsByName("engineering_control_" + i);
                var value = '';
                for (let j = 0; j < radios.length; j++) {
                    if (radios[j].checked) {
                        value = radios[j].value;
                        break;
                    }
                }
                engineeringControlsValues.push(value);
                if (value === 'not_listed') {
                    var detailText = document.getElementById("engineering-not-listed-text-" + i).value.trim();
                    engineeringControlsDetailsValues.push(detailText);
                } else {
                    engineeringControlsDetailsValues.push('');
                }
            }
            var engineeringControlsCSV = engineeringControlsValues.join(",");
            var engineeringControlsDetailsCSV = engineeringControlsDetailsValues.join(",");
            
            // Create a FormData object.
            var formData = new FormData();
            formData.append('plan_id', <?php echo json_encode($plan_id); ?>);
            formData.append('engineering_controls', engineeringControlsCSV);
            formData.append('engineering_controls_details', engineeringControlsDetailsCSV);
            formData.append('activity_count', activityCount);
            
            // Append file uploads and note fields for each activity.
            for (let i = 0; i < activityCount; i++) {
                // File upload field: e.g., "engineering_control_upload_0"
                var fileInput = document.getElementById("engineering_control_upload_" + i);
                if (fileInput && fileInput.files.length > 0) {
                    formData.append("engineering_control_upload_" + i, fileInput.files[0]);
                }
                // Note field: e.g., "engineering_control_note_" + i
                var noteInput = document.getElementById("engineering_control_note_" + i);
                if (noteInput) {
                    formData.append("engineering_control_note_" + i, noteInput.value);
                }
            }
            
            // Send the FormData via fetch.
            fetch('../ajax/save_engineering_controls.php', {
                method: 'POST',
                body: formData
            })
            .then((response) => response.json())
            .then((data) => {
                hideLoadingModal();
                if (!data.success) {
                    console.error('Error saving engineering controls:', data.message);
                } else {
                    const nextStep = button.getAttribute('data-step');
                }
            })
            .catch((error) => {
                hideLoadingModal();
                console.error('AJAX Error:', error);
            });
        });
    });
</script>
