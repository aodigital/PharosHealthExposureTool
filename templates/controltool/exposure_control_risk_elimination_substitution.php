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
    $stmt = $conn->prepare("
        SELECT 
            ep.id, ep.jobsite_name, ep.created_at, ep.updated_at,
            em.activity_task, em.activity_tool, em.activity_material,
            em.work_area, em.project_start_date, em.project_end_date, em.avg_hr_per_shift,
            t.name AS joined_task_name, 
            tl.name AS joined_tool_name, 
            m.name AS joined_material_name,
            ec.elimination_control
        FROM Exposure_Plannings ep
        LEFT JOIN Exposure_Plannings_Meta em ON ep.id = em.planning_id
        LEFT JOIN Tasks t ON em.activity_task = t.id
        LEFT JOIN Tools tl ON em.activity_tool = tl.id
        LEFT JOIN Materials m ON em.activity_material = m.id
        LEFT JOIN Exposure_Plannings_Controls ec ON ep.id = ec.planning_id
        WHERE ep.id = :planning_id AND ep.user_id = :user_id
    ");
    $stmt->bindParam(':planning_id', $plan_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        echo "Error: No data found for this planning.";
        exit();
    }
    
    // Helper function to look up a name given an ID in a table.
    function getNameById($conn, $table, $id) {
        $stmt = $conn->prepare("SELECT name FROM {$table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() ?: 'N/A';
    }
    
    // Split the stored CSV values for each work activity.
    $activity_tasks    = isset($data['activity_task']) ? explode(',', $data['activity_task']) : [];
    $activity_tools    = isset($data['activity_tool']) ? explode(',', $data['activity_tool']) : [];
    $activity_materials = isset($data['activity_material']) ? explode(',', $data['activity_material']) : [];
    
    // Determine how many activities there are.
    $activity_count = max(count($activity_tasks), count($activity_tools), count($activity_materials), 1);
    
    // Build arrays of names for each activity by doing individual lookups.
    $task_names = [];
    $tool_names = [];
    $material_names = [];
    for ($i = 0; $i < $activity_count; $i++) {
        $task_id     = isset($activity_tasks[$i])    ? trim($activity_tasks[$i])    : '';
        $tool_id     = isset($activity_tools[$i])    ? trim($activity_tools[$i])    : '';
        $material_id = isset($activity_materials[$i]) ? trim($activity_materials[$i]) : '';
        
        $task_names[$i]     = $task_id ? getNameById($conn, 'Tasks', $task_id)     : 'N/A';
        $tool_names[$i]     = $tool_id ? getNameById($conn, 'Tools', $tool_id)     : 'N/A';
        $material_names[$i] = $material_id ? getNameById($conn, 'Materials', $material_id) : 'N/A';
    }
    
    // Get any previously saved elimination answers (CSV string) and split into an array.
    // (If none exist, default to an empty string for each activity.)
    $elimination_control = $data['elimination_control'] ?? '';
    $elimination_answers = strlen(trim($elimination_control)) ? explode(',', $elimination_control) : array_fill(0, $activity_count, "");
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<style>
    .hidden { display: none; }
    .elimination-question { border: 1px solid #ccc; margin-bottom: 1em; padding: 1em; }
    .elimination-tabs-container button { margin-right: .5em; }
    .active { font-weight: bold; }
</style>

<h2 class="more_space">Risk Elimination & Substitution</h2>
<p>
    Please answer the question below about exposure elimination and substitution.
    If you're not sure how to answer, click INFORMATION for guidelines and tips.
    Also, you can click YES to see details. You can always change your answer to NO later.
</p>
<p><strong>Elimination and Substitution</strong></p>

<!-- Tab Buttons -->
<div class="elimination-tabs-container">
    <button class="elimination-tab-link active" data-section="question">Question</button>
    <button class="elimination-tab-link" data-section="information">Information</button>
</div>

<!-- Tab Content -->
<div class="elimination-tabs-content">
    <div id="elimination-tab-question" class="elimination-tab active">
        <?php for ($i = 0; $i < $activity_count; $i++): ?>
            <div class="elimination-question" data-index="<?php echo $i; ?>">
                <h3>Elimination Question for Activity <strong>
                        <?php 
                            echo htmlspecialchars(
                                ($task_names[$i] ?? 'N/A') . ' ' .
                                ($material_names[$i] ?? 'N/A') . ' with a ' .
                                ($tool_names[$i] ?? 'N/A')
                            );
                        ?>
                </strong></h3>
                <p>
                    Can you eliminate the need for 
                    <strong>
                        <?php 
                            echo htmlspecialchars(
                                ($task_names[$i] ?? 'N/A') . ' ' .
                                ($material_names[$i] ?? 'N/A') . ' with a ' .
                                ($tool_names[$i] ?? 'N/A')
                            );
                        ?>
                    </strong>?
                </p>
                <form>
                    <div class="form-group">
                        <label>
                            <input type="radio" name="elimination_<?php echo $i; ?>" value="1" id="elimination-yes-<?php echo $i; ?>">
                            Yes
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="radio" name="elimination_<?php echo $i; ?>" value="0" id="elimination-no-<?php echo $i; ?>">
                            No
                        </label>
                    </div>
                    <div id="elimination-yes-message-<?php echo $i; ?>" class="hidden">
                        <p>
                            If you can eliminate this work activity, this exposure control planning project is no longer necessary.
                            <a href="" class="button verification-remove-activity-button" data-index="<?php echo $i; ?>">Remove Activity</a>
                        </p>
                    </div>
                </form>
            </div>
        <?php endfor; ?>
    </div>
    
    <div id="elimination-tab-information" class="elimination-tab">
        <p>
            Elimination &amp; substitution is the removal of the hazard by eliminating the silica process,
            or the replacement of the hazard by substituting the material or equipment with less RCS dust producing alternatives.
        </p>
        <p>
            Elimination is the most effective way to control a risk because the hazard is no longer present.
            It is the preferred way to control a hazard and should be used whenever reasonably possible.
        </p>
    </div>
</div>

<div class="clearfix">
    <a href="javascript:void(0)" class="button secondary left elimination-back-button" data-step="exposure_control_get_prepared">Back</a>
    <a href="javascript:void(0)" class="button save_continue secondary right elimination-next-button" data-step="exposure_control_engineering_controls">Next</a>
</div>

<script type="text/javascript">
    // Number of work activities
    var activityCount = <?php echo $activity_count; ?>;
    
    // Preloaded answers from PHP (an array of strings, "1" or "0")
    var eliminationAnswers = <?php echo json_encode($elimination_answers); ?>;
    
    // Attach event listeners for each activity's radio buttons to show/hide the yes-message.
    for (let i = 0; i < activityCount; i++) {
        (function(index) {
            var yesRadio = document.getElementById("elimination-yes-" + index);
            var noRadio  = document.getElementById("elimination-no-" + index);
            var yesMessage = document.getElementById("elimination-yes-message-" + index);
            
            function toggleEliminationMessage() {
                if (yesRadio.checked) {
                    yesMessage.classList.remove("hidden");
                } else {
                    yesMessage.classList.add("hidden");
                }
            }
            
            yesRadio.addEventListener("change", toggleEliminationMessage);
            noRadio.addEventListener("change", toggleEliminationMessage);
            
            // Preload saved value (if any)
            if (eliminationAnswers[index] === "1") {
                yesRadio.checked = true;
            } else if (eliminationAnswers[index] === "0") {
                noRadio.checked = true;
            }
            toggleEliminationMessage();
        })(i);
    }
    
    // Attach click event to the Remove Activity buttons.
    document.querySelectorAll('.verification-remove-activity-button').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            var index = this.getAttribute('data-index');
            if (!confirm("Are you sure you want to remove this activity?")) {
                return;
            }
            // AJAX call to remove the activity from the planning.
            fetch("../ajax/remove_activity.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    plan_id: <?php echo json_encode($plan_id); ?>,
                    activity_index: index
                }),
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    // Remove the corresponding elimination question block from the DOM.
                    var elem = document.querySelector('.elimination-question[data-index="' + index + '"]');
                    if (elem) {
                        elem.parentNode.removeChild(elem);
                    }
                } else {
                    console.error("Error removing activity: " + data.message);
                }
            })
            .catch(function(error) {
                console.error("AJAX Error:", error);
            });
        });
    });
    
    // Tab switching for "Question" and "Information"
    document.querySelectorAll('.elimination-tab-link').forEach(function(tabLink) {
        tabLink.addEventListener('click', function() {
            document.querySelectorAll('.elimination-tab-link').forEach(function(link) {
                link.classList.remove('active');
            });
            document.querySelectorAll('.elimination-tab').forEach(function(tab) {
                tab.classList.remove('active');
            });
            var targetSection = tabLink.getAttribute('data-section');
            tabLink.classList.add('active');
            document.getElementById('elimination-tab-' + targetSection).classList.add('active');
        });
    });
    
    // Attach click events for the back and next buttons to save all elimination answers.
    document.querySelectorAll(".elimination-back-button, .elimination-next-button").forEach(function(button) {
        button.addEventListener("click", function(e) {
            var eliminationValues = [];
            for (let i = 0; i < activityCount; i++) {
                let yesRadio = document.getElementById("elimination-yes-" + i);
                let noRadio  = document.getElementById("elimination-no-" + i);
                if (yesRadio.checked) {
                    eliminationValues.push("1");
                } else if (noRadio.checked) {
                    eliminationValues.push("0");
                } else {
                    eliminationValues.push(""); // Not answered
                }
            }
            // Join the answers into a CSV string
            var eliminationValueCSV = eliminationValues.join(",");
            
            // Perform the AJAX request to save the CSV string of answers
            fetch("../ajax/save_elimination_control.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    plan_id: <?php echo json_encode($plan_id); ?>,
                    elimination_control: eliminationValueCSV
                }),
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (!data.success) {
                    console.error("Error saving elimination control:", data.message);
                }
            })
            .catch(function(error) {
                console.error("AJAX Error:", error);
            });
        });
    });
</script>
