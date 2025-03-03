<?php
//include "../includes/db.php"; // Assuming this file contains the DB connection

if (!isset($_SESSION["user"])) {
    header("Location: ../index.php?page=login_register");
    exit();
}

$user = $_SESSION["user"];
$plan_id = isset($_GET["plan_id"]) ? intval($_GET["plan_id"]) : null;

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
    $stmt_planning->bindParam(":plan_id", $plan_id, PDO::PARAM_INT);
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
    $stmt_user->bindParam(":user_id", $planning["user_id"], PDO::PARAM_INT);
    $stmt_user->execute();
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $plan_creator_name = $user_data
        ? htmlspecialchars(
            $user_data["first_name"] . " " . $user_data["last_name"],
        )
        : "Unknown";

    $stmt_meta = $conn->prepare("
        SELECT 
            jobsite_address, jobsite_city, jobsite_region, jobsite_post_code,
            ecp_contact_name, ecp_contact_position, ecp_contact_phone, ecp_contact_email,
            work_area, avg_hr_per_shift, project_start_date, project_end_date, jobsite_sector,
            jobsite_type, project_type, activity_material, activity_task, activity_tool, employer_name,
            signing_date, creator_signature, jobsite_shift_hours, employer_name, employer_address, employer_address_city,
            employer_address_region, employer_address_postal_code, employer_phone, employer_email, employer_website,
            ecp_generated, ecp_download_file
        FROM Exposure_Plannings_Meta
        WHERE planning_id = :plan_id
    ");
    $stmt_meta->bindParam(":plan_id", $plan_id, PDO::PARAM_INT);
    $stmt_meta->execute();
    $meta_data = $stmt_meta->fetch(PDO::FETCH_ASSOC);
    if (!$meta_data) {
        echo "Error: No metadata found for this planning.";
        exit();
    }

    $activity_materials = isset($meta_data["activity_material"])
        ? explode(",", $meta_data["activity_material"])
        : [];
    $activity_tasks = isset($meta_data["activity_task"])
        ? explode(",", $meta_data["activity_task"])
        : [];
    $activity_tools = isset($meta_data["activity_tool"])
        ? explode(",", $meta_data["activity_tool"])
        : [];

    $work_activities = [];
    for (
        $i = 0;
        $i <
        max(
            count($activity_materials),
            count($activity_tasks),
            count($activity_tools),
        );
        $i++
    ) {
        $material_id = $activity_materials[$i] ?? null;
        $task_id = $activity_tasks[$i] ?? null;
        $tool_id = $activity_tools[$i] ?? null;

        $material_name = $material_id
            ? fetchSingleName($conn, "Materials", $material_id)
            : "N/A";
        $task_name = $task_id
            ? fetchSingleName($conn, "Tasks", $task_id)
            : "N/A";
        $tool_name = $tool_id
            ? fetchSingleName($conn, "Tools", $tool_id)
            : "N/A";

        $work_activities[] = htmlspecialchars(
            "$task_name $material_name with $tool_name",
        );
    }

    $stmt_controls = $conn->prepare("
        SELECT 
            admin_controls_maintenance, admin_controls_housekeeping, admin_controls_hygene,
            admin_controls_training, admin_controls_procedures, admin_controls_scheduling,
            admin_controls_barriers, admin_controls_enclosures, engineering_controls,
            residual_exposure_respirator, residual_exposure_ppe, engineering_controls_details,
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
    $stmt_controls->bindParam(":plan_id", $plan_id, PDO::PARAM_INT);
    $stmt_controls->execute();
    $controls = $stmt_controls->fetch(PDO::FETCH_ASSOC);

    $activity_count = max(
        count($activity_materials),
        count($activity_tasks),
        count($activity_tools),
    );

    $admin_controls_maintenance = isset($controls["admin_controls_maintenance"])
        ? explode(",", $controls["admin_controls_maintenance"])
        : array_fill(0, $activity_count, "");
    $admin_controls_housekeeping = isset(
        $controls["admin_controls_housekeeping"],
    )
        ? explode(",", $controls["admin_controls_housekeeping"])
        : array_fill(0, $activity_count, "");
    $admin_controls_hygene = isset($controls["admin_controls_hygene"])
        ? explode(",", $controls["admin_controls_hygene"])
        : array_fill(0, $activity_count, "");
    $admin_controls_training = isset($controls["admin_controls_training"])
        ? explode(",", $controls["admin_controls_training"])
        : array_fill(0, $activity_count, "");
    $admin_controls_procedures = isset($controls["admin_controls_procedures"])
        ? explode(",", $controls["admin_controls_procedures"])
        : array_fill(0, $activity_count, "");
    $admin_controls_scheduling = isset($controls["admin_controls_scheduling"])
        ? explode(",", $controls["admin_controls_scheduling"])
        : array_fill(0, $activity_count, "");
    $admin_controls_barriers = isset($controls["admin_controls_barriers"])
        ? explode(",", $controls["admin_controls_barriers"])
        : array_fill(0, $activity_count, "");
    $admin_controls_enclosures = isset($controls["admin_controls_enclosures"])
        ? explode(",", $controls["admin_controls_enclosures"])
        : array_fill(0, $activity_count, "");

    $engineering_controls_csv = $controls["engineering_controls"] ?? "";
    $engineering_controls_details_csv =
        $controls["engineering_controls_details"] ?? "";
    $engineering_control_names_csv =
        $controls["engineering_control_names"] ?? "";

    $stmt_verification = $conn->prepare("
        SELECT 
            auditor_id, 
            admin_controls_maintenance, admin_controls_maintenance_notes, admin_controls_maintenance_image, admin_controls_housekeeping, admin_controls_housekeeping_notes, 
            admin_controls_housekeeping_image, admin_controls_hygene, admin_controls_hygene_notes, admin_controls_hygene_image, admin_controls_training, admin_controls_training_notes, 
            admin_controls_training_image, admin_controls_procedures, admin_controls_procedures_notes, admin_controls_procedures_image, admin_controls_scheduling,
            admin_controls_scheduling_notes, admin_controls_scheduling_image, admin_controls_barriers, admin_controls_barriers_notes, admin_controls_barriers_image, 
            admin_controls_enclosures, admin_controls_enclosures_notes, admin_controls_enclosures_image, activity_engineering_controls, activity_engineering_controls_notes, 
            activity_engineering_controls_image, activity_admin_controls, activity_admin_controls_notes, activity_admin_controls_image, verification_date, auditor_signature
        FROM Exposure_Plannings_Verification
        WHERE plan_id = :plan_id
    ");
    $stmt_verification->bindParam(":plan_id", $plan_id, PDO::PARAM_INT);
    $stmt_verification->execute();
    $verification_data = $stmt_verification->fetch(PDO::FETCH_ASSOC);

    // Convert the comma-separated verification values into arrays (default to an empty array if not set)
    $activity_admin_verif = isset($verification_data["activity_admin_controls"])
        ? explode(",", $verification_data["activity_admin_controls"])
        : [];
    $activity_eng_verif = isset(
        $verification_data["activity_engineering_controls"],
    )
        ? explode(",", $verification_data["activity_engineering_controls"])
        : [];

    $activity_eng_notes_array = isset(
        $verification_data["activity_engineering_controls_notes"],
    )
        ? str_getcsv($verification_data["activity_engineering_controls_notes"])
        : [];
    $activity_admin_notes_array = isset(
        $verification_data["activity_admin_controls_notes"],
    )
        ? str_getcsv($verification_data["activity_admin_controls_notes"])
        : [];

    $activity_eng_images = isset(
        $verification_data["activity_engineering_controls_image"],
    )
        ? array_map(
            "trim",
            explode(
                ",",
                $verification_data["activity_engineering_controls_image"],
            ),
        )
        : [];

    $activity_admin_images = isset(
        $verification_data["activity_admin_controls_image"],
    )
        ? array_map(
            "trim",
            explode(",", $verification_data["activity_admin_controls_image"]),
        )
        : [];

    // Now fetch the auditor's first and last name from the users table
    $auditor_data = null;
    if ($verification_data && isset($verification_data["auditor_id"])) {
        $stmt_auditor = $conn->prepare("
            SELECT first_name, last_name
            FROM users
            WHERE id = :auditor_id
        ");
        $stmt_auditor->bindParam(
            ":auditor_id",
            $verification_data["auditor_id"],
            PDO::PARAM_INT,
        );
        $stmt_auditor->execute();
        $auditor_data = $stmt_auditor->fetch(PDO::FETCH_ASSOC);
    }

    // Retrieve the extra per-activity data from the meta table:
    // Break avg_hr_per_shift into an array (one per activity)
    $avg_hr_per_shift_arr = isset($meta_data["avg_hr_per_shift"])
        ? explode(",", $meta_data["avg_hr_per_shift"])
        : [];
    // Break work_area into an array (one per activity)
    $work_area_arr = isset($meta_data["work_area"])
        ? explode(",", $meta_data["work_area"])
        : [];

    // Prepare a statement to fetch the baseline_exposure for a given activity combination.
    $stmt_exposure = $conn->prepare("
        SELECT baseline_exposure 
        FROM exposure_values 
        WHERE task_id = :task_id AND tool_id = :tool_id AND material_id = :material_id
    ");

    // For each activity, fetch the matching baseline_exposure
    $baseline_exposures = [];
    for ($i = 0; $i < $activity_count; $i++) {
        $task_id = isset($activity_tasks[$i]) ? trim($activity_tasks[$i]) : "";
        $tool_id = isset($activity_tools[$i]) ? trim($activity_tools[$i]) : "";
        $material_id = isset($activity_materials[$i])
            ? trim($activity_materials[$i])
            : "";
        if ($task_id && $tool_id && $material_id) {
            $stmt_exposure->bindValue(
                ":task_id",
                (int) $task_id,
                PDO::PARAM_INT,
            );
            $stmt_exposure->bindValue(
                ":tool_id",
                (int) $tool_id,
                PDO::PARAM_INT,
            );
            $stmt_exposure->bindValue(
                ":material_id",
                (int) $material_id,
                PDO::PARAM_INT,
            );
            $stmt_exposure->execute();
            $row = $stmt_exposure->fetch(PDO::FETCH_ASSOC);
            $baseline_exposures[$i] =
                $row && isset($row["baseline_exposure"])
                    ? (float) $row["baseline_exposure"]
                    : 0.0;
        } else {
            $baseline_exposures[$i] = 0.0;
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

function fetchSingleName($conn, $table, $id)
{
    $stmt = $conn->prepare("SELECT name FROM $table WHERE id = :id");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchColumn() ?: "N/A";
}

?>

<h2 class="more_space">
    Generate ECP for:
    <?php echo htmlspecialchars($planning['jobsite_name'] ?? 'N/A'); ?>
</h2>
<div class="row">
    <!-- Left Column -->
    <div class="large-6 columns">
        <div id="preview-section-contain" class="preview-section">
            <canvas id="pdf-preview-canvas"></canvas>
            <div id="pdf-controls">
                <button id="prev-page">Previous</button>
                <span
                    >Page <span id="current-page">1</span> of
                    <span id="total-pages">?</span></span
                >
                <button id="next-page">Next</button>
            </div>
        </div>
        <!--<script>
            // URL of the PDF file you want to render
            var url = '../assets/documents/ecp_pdf/<?php echo $plan_id ?>_ecp_document.pdf';
            var pdfDoc = null,
                currentPage = 1,
                totalPages = 0;
            // Get the container to determine its width for responsive scaling.
            var container = document.getElementById('preview-section-contain');
            var desiredWidth = container.clientWidth;
            // Function to render a specific page of the PDF.
            function renderPage(pageNum) {
                pdfDoc.getPage(pageNum).then(function(page) {
                    // Get the original viewport at scale 1.
                    var initialViewport = page.getViewport({
                        scale: 1,
                        rotation: 0
                    });
                    // Calculate a new scale so that the page fits the container width.
                    var scale = desiredWidth / initialViewport.width;
                    var viewport = page.getViewport({
                        scale: scale,
                        rotation: 0
                    });
                    var canvas = document.getElementById('pdf-preview-canvas');
                    var context = canvas.getContext('2d');
                    // Set the canvas dimensions to match the PDF page.
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    var renderContext = {
                        canvasContext: context,
                        viewport: viewport
                    };
                    page.render(renderContext);
                    // Update the current page display.
                    document.getElementById('current-page').textContent = pageNum;
                });
            }
            // Load the PDF document.
            pdfjsLib.getDocument(url).promise.then(function(pdf) {
                pdfDoc = pdf;
                totalPages = pdfDoc.numPages;
                document.getElementById('total-pages').textContent = totalPages;
                // Render the first page.
                renderPage(currentPage);
            });
            // Add event listeners for the navigation buttons.
            document.getElementById('prev-page').addEventListener('click', function() {
                if (currentPage <= 1) return;
                currentPage--;
                renderPage(currentPage);
            });
            document.getElementById('next-page').addEventListener('click', function() {
                if (currentPage >= totalPages) return;
                currentPage++;
                renderPage(currentPage);
            });
        </script>-->
    </div>
    <!-- Right Column -->
    <div class="large-6 columns">
        <div
            class="generate-ecp-section"
            style="padding: 15px; margin-top: 10px;text-align: center;"
        >
            
            <br />
            <!--<button id="generate-ecp-doc-btn" class="button large generate-ecp-button">
                <?php //echo ($meta_data['ecp_generated'] == 1) ? 'Regenerate ECP Document' : 'Generate ECP Document'; 
                ?>
            </button>-->
            <?php //if (!empty($meta_data['ecp_download_file'])): 
                ?>
                <p><a style="margin:20px!important; width:100%!important;" href="<?php echo htmlspecialchars($meta_data['ecp_download_file']); ?>" target="_blank" class="button large" download>
                    Download ECP Document
                </a></p>
                <br />
            <?php //endif; 
            ?>
             
        </div>
        <br />

        <div class="jobsite-section">
            <h3>
                <?php echo htmlspecialchars($planning['jobsite_name'] ?? 'N/A'); ?>
            </h3>
            <p>
                <?php echo htmlspecialchars($meta_data['jobsite_address'] ?? 'N/A'); ?>
            </p>
            <p>
                <?php echo htmlspecialchars($meta_data['jobsite_city'] ?? 'N/A') . ', ' . htmlspecialchars($meta_data['jobsite_region'] ?? 'N/A') . ' ' . htmlspecialchars($meta_data['jobsite_post_code'] ?? 'N/A'); ?>
            </p>
        </div>
        <div
            class="dates-section"
            style="
                background-color: #F4F4F9;
                color: #333;
                padding: 15px;
                margin-top: 10px;
            "
        >
            <p>
                Working from
                <?php echo htmlspecialchars($meta_data['project_start_date'] ?? 'N/A') . ' until ' . htmlspecialchars($meta_data['project_end_date'] ?? 'N/A') . ' shifts of ' . htmlspecialchars($meta_data['jobsite_shift_hours'] ?? 'N/A') . ' hours per day.'; ?>
            </p>
        </div>
        <?php 
                // Split work_area and avg_hr_per_shift into arrays
                $work_areas = isset($meta_data['work_area']) ? explode(',', $meta_data['work_area']) : [];
                $durations = isset($meta_data['avg_hr_per_shift']) ? explode(',', $meta_data['avg_hr_per_shift']) : [];

                foreach ($work_activities as $index =>
        $activity): $work_area = $work_area_arr[$index] ?? 'N/A'; $duration =
        $avg_hr_per_shift_arr[$index] ?? 'N/A'; ?>
        <div class="task-section">
            <h3>
                <?php echo $activity; ?>
            </h3>
            <p>
                <?php echo htmlspecialchars($work_area) .  ' for approximately ' . htmlspecialchars($duration) . ' hours per shift.'; ?>
            </p>
        </div>
        <?php endforeach; ?>

        
    </div>
</div>

<!-- Next Steps Section -->
<div class="next-steps-section">
    <p>Next, we'll conclude this exposure control planning project.</p>
    <div class="clearfix">
        <a
            href="javascript:void(0)"
            class="button secondary left load-step"
            data-step="documentation_ecp_summary"
            >Back</a
        >
        <a
            href="javascript:void(0)"
            class="button save_continue secondary right load-step"
            data-step="completed_ecp"
            >Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i
        ></a>
    </div>
</div>

<script>
$(document).ready(function(){
    // Append modal overlay with spinner image and message
    $('body').append(
        '<div id="loading-modal">' +
        '<div class="modal-content">' +
            '<img class="spinner-img" src="../assets/images/spinner.gif" alt="Loading...">' +
            '<p>Your document is being generated, please wait. The preview will update once completed.</p>' +
        '</div>' +
        '</div>'
    );

    // Perform AJAX POST to generate the PDF
    $.ajax({
        url: '../ajax/generate_ecp_document_pdf.php',
        method: 'POST',
        dataType: 'json',
        data: {
            user: '<?php echo $user["id"]; ?>',
            plan_id: <?php echo $plan_id; ?>
        },
        success: function(response) {
            // Remove the modal overlay
            $('#loading-modal').remove();
            
            // Check if the request was successful
            if(response.status === 'success') {
                // Reload only the preview section content after a short delay (ensuring PDF is generated)
                setTimeout(function() {
                    reloadPdfPreview();
                }, 1000); // Delay 1 second to ensure the file is available
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $('#loading-modal').remove();
            alert("An error occurred: " + textStatus);
        }
    });

    function reloadPdfPreview() {
        var planId = '<?php echo $plan_id ?>';
        var url = '../assets/documents/ecp_pdf/' + planId + '_ecp_document.pdf';

        var pdfDoc = null,
            currentPage = 1,
            totalPages = 0;

        var container = document.getElementById('preview-section-contain');
        var desiredWidth = container.clientWidth;

        function renderPage(pageNum) {
            pdfDoc.getPage(pageNum).then(function(page) {
                var initialViewport = page.getViewport({ scale: 1, rotation: 0 });
                var scale = desiredWidth / initialViewport.width;
                var viewport = page.getViewport({ scale: scale, rotation: 0 });

                var canvas = document.getElementById('pdf-preview-canvas');
                var context = canvas.getContext('2d');

                canvas.width = viewport.width;
                canvas.height = viewport.height;

                var renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };

                page.render(renderContext);
                document.getElementById('current-page').textContent = pageNum;
            });
        }

        // Load the PDF document again
        pdfjsLib.getDocument(url).promise.then(function(pdf) {
            pdfDoc = pdf;
            totalPages = pdfDoc.numPages;
            document.getElementById('total-pages').textContent = totalPages;
            renderPage(currentPage);
        });

        // Reattach event listeners for navigation buttons
        document.getElementById('prev-page').addEventListener('click', function() {
            if (currentPage <= 1) return;
            currentPage--;
            renderPage(currentPage);
        });

        document.getElementById('next-page').addEventListener('click', function() {
            if (currentPage >= totalPages) return;
            currentPage++;
            renderPage(currentPage);
        });
    }
});

</script>
