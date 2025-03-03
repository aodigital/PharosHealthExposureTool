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
    // Fetch existing data to pre-select radio buttons
    $stmt_controls = $conn->prepare("SELECT residual_exposure_respirator, residual_exposure_ppe FROM Exposure_Plannings_Controls WHERE planning_id = :plan_id");
    $stmt_controls->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt_controls->execute();
    $controls = $stmt_controls->fetch(PDO::FETCH_ASSOC);

    // Fetch meta data for work activities (still used elsewhere on the page)
    $stmt_meta = $conn->prepare("SELECT * FROM Exposure_Plannings_Meta WHERE planning_id = :plan_id");
    $stmt_meta->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt_meta->execute();
    $meta_data = $stmt_meta->fetch(PDO::FETCH_ASSOC);

    // Parse CSV fields into arrays (if not set, create an empty array)
    $activity_tasks    = isset($meta_data['activity_task'])    ? explode(',', $meta_data['activity_task'])    : array();
    $activity_tools    = isset($meta_data['activity_tool'])    ? explode(',', $meta_data['activity_tool'])    : array();
    $activity_materials = isset($meta_data['activity_material']) ? explode(',', $meta_data['activity_material']) : array();
    $activity_count    = max(count($activity_tasks), count($activity_tools), count($activity_materials), 1);

    // Pre-load saved CSV values for PPE controls.
    // If stored as CSV, take the first element as the overall value.
    $respirator_csv = isset($controls['residual_exposure_respirator']) ? trim($controls['residual_exposure_respirator']) : "";
    $ppe_csv = isset($controls['residual_exposure_ppe']) ? trim($controls['residual_exposure_ppe']) : "";
    $respirator_value = ($respirator_csv !== "") ? (strpos($respirator_csv, ',') !== false ? explode(',', $respirator_csv)[0] : $respirator_csv) : "";
    $ppe_value = ($ppe_csv !== "") ? (strpos($ppe_csv, ',') !== false ? explode(',', $ppe_csv)[0] : $ppe_csv) : "";
    
    // Helper function to retrieve a name from a table by ID.
    function getName($conn, $table, $id) {
        $stmt = $conn->prepare("SELECT name FROM {$table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() ?: 'N/A';
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<h2 class="more_space">Respirators &amp; Other PPE</h2>

<p>Personal Protective Equipment (PPE) is equipment worn by workers to reduce exposure.</p>
<p>Answer these questions below about the PPE controls you have available for this jobsite.</p>

<!-- Display PPE controls only once for the overall jobsite -->
<div class="jobsite-ppe-section">
    <h3>Respirator Selection (Jobsite Overall)</h3>
    <!-- Respirator Selection Table (static example content) -->
    <table class="summary-table">
        <thead>
            <tr>
                <th>Respirator Usage</th>
                <th>Required Protection Factor</th>
                <th>Respirator Type &amp; Filter</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>PROTECTION REQUIRED</td>
                <td>10</td>
                <td>
                    Half facepiece, non powered with P100 filter<br><br>
                    Please note, the respirator type above is an example of a respirator type that may meet the required protection factor. Users may elect to use alternate respiratory protection equipment that meets the required protection factor rating.<br><br>
                    Any respirator choice must be fitted with an N100, P100, or R100 filter. Respirators and filters must be NIOSH approved.
                </td>
            </tr>
        </tbody>
    </table>
    
    <!-- Question about respirators -->
    <h3>Will your workers in the jobsite have respirators available?</h3>
    <div>
        <label>
            <input type="radio" name="respirators_available" value="1" <?php echo ($respirator_value == "1") ? 'checked' : ''; ?>> Yes
        </label>
        <label>
            <input type="radio" name="respirators_available" value="0" <?php echo ($respirator_value === "0") ? 'checked' : ''; ?>> No
        </label>
    </div>
    
    <!-- Other PPE Section -->
    <h3>Other PPE</h3>
    <h4>Washable or Disposable Coveralls</h4>
    <p>Will workers in the jobsite wear washable or disposable coveralls?</p>
    <div>
        <label>
            <input type="radio" name="coveralls_available" value="1" <?php echo ($ppe_value == "1") ? 'checked' : ''; ?>> Yes
        </label>
        <label>
            <input type="radio" name="coveralls_available" value="0" <?php echo ($ppe_value === "0") ? 'checked' : ''; ?>> No
        </label>
    </div>
</div>

<div class="clearfix">
    <a href="javascript:void(0)" class="button secondary left save-and-load-respirators-back" data-step="residual_exposure_control_get_prepared">Back</a>
    <a href="javascript:void(0)" class="button save_continue secondary right save-and-load-respirators-next" data-step="documentation_get_prepared">
        Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i>
    </a>
</div>

<script>
    // When Back or Next is clicked, gather the single overall PPE values and send them via AJAX.
    document.querySelectorAll('.save-and-load-respirators-back, .save-and-load-respirators-next').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Get the single values for the jobsite PPE controls
            let respiratorValue = document.querySelector('input[name="respirators_available"]:checked') ? document.querySelector('input[name="respirators_available"]:checked').value : "";
            let coverallsValue = document.querySelector('input[name="coveralls_available"]:checked') ? document.querySelector('input[name="coveralls_available"]:checked').value : "";
            
            const formData = {
                plan_id: <?php echo $plan_id; ?>,
                residual_exposure_respirator: respiratorValue,
                residual_exposure_ppe: coverallsValue
            };

            // Send data via AJAX
            fetch('../ajax/save_respirators_and_ppe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Error saving data:', data.message);
                }
            })
            .catch(error => console.error('AJAX error:', error));
        });
    });
</script>
