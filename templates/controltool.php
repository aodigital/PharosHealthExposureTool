<?php
include 'includes/user.php';
include 'includes/planning.php'; // Include planning-related functions
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: index.php?page=login_register");
    exit();
}

$user = $_SESSION['user'];
$planning_id = isset($_GET['planning_id']) ? intval($_GET['planning_id']) : null;

if (!$planning_id) {
    // Create a new Exposure_Planning entry if there is no planning ID passed in
    $planning_id = createNewExposurePlanning($user['ID']);

    if ($planning_id) {
        createExposurePlanningMeta($planning_id);

        // Redirect to the new planning instance page
        header("Location: controltool.php?planning_id=" . $planning_id);
        exit();
    } else {
        echo "Error: Could not create a new exposure planning.";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
    <title>Control Tool</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/dynamic_steps.js"></script> <!-- JavaScript for dynamic step handling -->
</head>
<body>
    <!-- Header Bar -->
    <?php include 'templates/header.php'; ?>

    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <aside class="left-off-canvas-menu">
                <div class="panel panel_left_column">
                    <div class="padding_left_column">
                        <div class="row">
                            <div class="large-12 columns">
                                <br>
                                <h3 class="alot_less_space">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </h3>
                                <p><br><strong><?php echo htmlspecialchars($user['company_name']); ?></strong><br><br></p>
                            </div>
                        </div>
                    </div>
                    <ul id="controltool-sidebar" class="side-nav">
                        <li class="active_section active_heading">
                            <a href="javascript:void(0)" onclick="loadStep('introduction');">
                                <i class="fa fa-map-marker fa-fw"></i>&nbsp;INTRODUCTION
                            </a>
                            <ul class="side-nav" style="display: block">
                                <li class="divider"></li>
                                <li class="active active_section">
                                    <a href="javascript:void(0)" onclick="loadStep('welcome');" data-step="welcome">
                                        <i class="fa fa-circle fa-fw"></i>&nbsp;Welcome
                                    </a>
                                </li>
                                <li class="divider"></li>
                                <li class="active_section locked">
                                    <a href="javascript:void(0)" onclick="loadStep('get_prepared');" data-step="get_prepared">
                                        <i class="fa fa-circle fa-fw"></i>&nbsp;Get prepared
                                    </a>
                                </li>
                                <li class="divider"></li>
                                <li class="active_section locked">
                                    <a href="javascript:void(0)" onclick="loadStep('employer_details');" data-step="employer_details">
                                        <i class="fa fa-circle fa-fw"></i>&nbsp;Employer Details
                                    </a>
                                </li>
                                <li class="divider"></li>
                            </ul>
                        </li>
                        <!-- Repeat similar sections for other steps -->
                        <li class="heading locked">
                            <a href="javascript:void(0)">
                                <i class="fa fa-lock fa-fw"></i>&nbsp;SILICA PROCESS
                            </a>
                            <ul class="side-nav" style="display: none">
                                <li class="divider"></li>
                                <li class="active_section locked">
                                    <a href="javascript:void(0)" onclick="loadStep('jobsite_details');" data-step="jobsite_details">
                                        <i class="fa fa-circle fa-fw"></i>&nbsp;Jobsite details
                                    </a>
                                </li>
                                <!-- More items can be added here -->
                                <li class="divider"></li>
                            </ul>
                        </li>
                        <!-- Add remaining sections here as per the original sidebar structure -->
                    </ul>
                </div>
            </aside>
        </div>

        <!-- Content Area -->
        <div class="content" id="control-content">
            <!-- Initial content will be loaded here dynamically -->
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Load the first step, which is 'welcome'
            loadStep('welcome', '<?php echo $planning_id; ?>');

            // Function to load step content dynamically
            function loadStep(step, planningId) {
                $.ajax({
                    type: 'GET',
                    url: 'ajax/load_step.php',
                    data: { step: step, planning_id: planningId },
                    success: function(response) {
                        $('#control-content').html(response);
                    }
                });
            }

            // Handle clicking on sidebar links to load different steps dynamically
            $(document).on('click', '.sidebar-link', function(e) {
                e.preventDefault();
                var step = $(this).data('step');
                loadStep(step, '<?php echo $planning_id; ?>');
            });
        });
    </script>
</body>
</html>
