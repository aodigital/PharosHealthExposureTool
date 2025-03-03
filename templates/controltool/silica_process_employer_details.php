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

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

    <h2 class="more_space">Jobsite Details</h2>

    <p>Please provide the details of the jobsite where the silica process will take place. This information is critical for understanding the jobsite context and exposure risks.</p>

    <form id="jobsite-details-form" method="post">
        <input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>">

        <div class="row white_row_no_padding padding_top_text">
            <div class="large-6 medium-6 small-12 columns">
                <div class="row">
                    <div class="large-12 columns">
                        <label>Jobsite Name:</label>
                        <input type="text" name="jobsite_name" value="<?php echo htmlspecialchars($exposure_planning['jobsite_name'] ?? ''); ?>" placeholder="Enter the Jobsite Name"/>
                    </div>

                    <div class="large-12 columns">
                        <label>Jobsite Address:</label>
                        <input type="text" name="jobsite_address" value="<?php echo htmlspecialchars($meta_data['jobsite_address'] ?? ''); ?>" placeholder="Enter the Jobsite Address"/>
                    </div>

                    <div class="large-4 medium-4 small-12 columns">
                        <label>City:</label>
                        <input type="text" name="jobsite_city" value="<?php echo htmlspecialchars($meta_data['jobsite_city'] ?? ''); ?>" placeholder="Enter the City"/>
                    </div>

                    <div class="large-4 medium-4 small-12 columns">
                        <label>Province/Region:</label>
                        <input type="text" name="jobsite_region" value="<?php echo htmlspecialchars($meta_data['jobsite_region'] ?? ''); ?>" placeholder="Enter the Province or Region"/>
                    </div>

                    <div class="large-4 medium-4 small-12 columns">
                        <label>Postal Code:</label>
                        <input type="text" name="jobsite_post_code" value="<?php echo htmlspecialchars($meta_data['jobsite_post_code'] ?? ''); ?>" placeholder="Enter the Postal Code"/>
                    </div>

                    <div class="large-12 columns">
                        <label>Jobsite Sector:</label>
                        <select name="jobsite_type">
                            <option value="">Select Jobsite Sector</option>
                            <option value="Residential" <?php echo ($meta_data['jobsite_type'] ?? '') == 'Residential' ? 'selected' : ''; ?>>Residential</option>
                            <option value="Commercial" <?php echo ($meta_data['jobsite_type'] ?? '') == 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
                            <option value="Industrial" <?php echo ($meta_data['jobsite_type'] ?? '') == 'Industrial' ? 'selected' : ''; ?>>Industrial</option>
                            <option value="Other" <?php echo ($meta_data['jobsite_type'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="large-12 columns">
                        <label>Construction Project Type:</label>
                        <select name="project_type">
                            <option value="">Select Project Type</option>
                            <option value="New Construction" <?php echo ($meta_data['project_type'] ?? '') == 'New Construction' ? 'selected' : ''; ?>>New Construction</option>
                            <option value="Renovation" <?php echo ($meta_data['project_type'] ?? '') == 'Renovation' ? 'selected' : ''; ?>>Renovation</option>
                            <option value="Demolition" <?php echo ($meta_data['project_type'] ?? '') == 'Demolition' ? 'selected' : ''; ?>>Demolition</option>
                            <option value="Other" <?php echo ($meta_data['project_type'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="large-6 medium-6 small-12 columns">
                        <label>Start Date:</label>
                        <input type="date" name="project_start_date" value="<?php echo htmlspecialchars($exposure_planning['project_start_date'] ?? ''); ?>"/>
                    </div>

                    <div class="large-6 medium-6 small-12 columns">
                        <label>End Date:</label>
                        <input type="date" name="project_end_date" value="<?php echo htmlspecialchars($exposure_planning['project_end_date'] ?? ''); ?>"/>
                    </div>
                </div>
            </div>
        </div>

        <div class="clearfix">
            <a href="javascript:void(0)" class="button secondary left load-step-jobsite" data-step="silica_process_get_prepared">Back</a>
            <a href="javascript:void(0)" class="button save_continue secondary right load-step-jobsite" data-step="silica_process_work_activity">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
        </div>
    </form>

    <script type="text/javascript">
        // Function to save Jobsite Details Form Data
        function saveJobsiteDetailsFormData(callback) {
            // Check if the current form is the Jobsite Details form
            if ($('#jobsite-details-form').length === 0) {
                if (callback) callback(); // If the form is not present, proceed without saving
                return;
            }

            // Gather data specific to jobsite form
            const formData = {
                planning_id: window.planId, // This comes from the global variable set by PHP
                jobsite_name: $('input[name="jobsite_name"]').val(),
                jobsite_address: $('input[name="jobsite_address"]').val(),
                jobsite_city: $('input[name="jobsite_city"]').val(),
                jobsite_region: $('input[name="jobsite_region"]').val(),
                jobsite_post_code: $('input[name="jobsite_post_code"]').val(),
                jobsite_type: $('select[name="jobsite_type"]').val(),
                project_type: $('select[name="project_type"]').val(),
                project_start_date: $('input[name="project_start_date"]').val(),
                project_end_date: $('input[name="project_end_date"]').val(),
                jobsite_shift_hours: $('input[name="jobsite_shift_hours"]').val() // New field
            };

            // Send data via AJAX
            $.ajax({
                url: '../ajax/update_jobsite_details.php',
                type: 'POST',
                data: formData,
                success: function (response) {
                    console.log(response); // For debugging purposes
                    if (callback) {
                        callback();
                    }
                },
                error: function () {
                    alert('Failed to save form data. Please try again.');
                }
            });
        }
    </script>
