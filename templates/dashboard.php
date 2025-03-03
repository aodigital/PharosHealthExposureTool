<?php
    $user = $_SESSION['user'];
    try {
        // Use subqueries to retrieve the meta values for each plan.
        $stmt = $conn->prepare("
            SELECT 
                ep.id, 
                ep.jobsite_name, 
                ep.created_at, 
                ep.updated_at, 
                ep.verified,
                (SELECT activity_task FROM Exposure_Plannings_Meta WHERE planning_id = ep.id LIMIT 1) AS activity_task,
                (SELECT activity_tool FROM Exposure_Plannings_Meta WHERE planning_id = ep.id LIMIT 1) AS activity_tool,
                (SELECT activity_material FROM Exposure_Plannings_Meta WHERE planning_id = ep.id LIMIT 1) AS activity_material
            FROM Exposure_Plannings ep
            WHERE ep.user_id = :user_id
            ORDER BY ep.id DESC
        ");
        $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
        $stmt->execute();
        $plannings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process and fetch names for multiple activities
        foreach ($plannings as $index => $planning) {
            $materials = isset($planning['activity_material']) ? explode(',', $planning['activity_material']) : [];
            $tasks = isset($planning['activity_task']) ? explode(',', $planning['activity_task']) : [];
            $tools = isset($planning['activity_tool']) ? explode(',', $planning['activity_tool']) : [];
            
            // Fetch names for materials
            if (!empty($materials)) {
                $placeholders = implode(',', array_fill(0, count($materials), '?'));
                $stmt_materials = $conn->prepare("SELECT name FROM Materials WHERE id IN ($placeholders)");
                $stmt_materials->execute($materials);
                $planning['materials'] = $stmt_materials->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $planning['materials'] = [];
            }
            
            // Fetch names for tasks
            if (!empty($tasks)) {
                $placeholders = implode(',', array_fill(0, count($tasks), '?'));
                $stmt_tasks = $conn->prepare("SELECT name FROM Tasks WHERE id IN ($placeholders)");
                $stmt_tasks->execute($tasks);
                $planning['tasks'] = $stmt_tasks->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $planning['tasks'] = [];
            }
            
            // Fetch names for tools
            if (!empty($tools)) {
                $placeholders = implode(',', array_fill(0, count($tools), '?'));
                $stmt_tools = $conn->prepare("SELECT name FROM Tools WHERE id IN ($placeholders)");
                $stmt_tools->execute($tools);
                $planning['tools'] = $stmt_tools->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $planning['tools'] = [];
            }
            
            // Reassign the updated planning array back to $plannings
            $plannings[$index] = $planning;
        }
    } catch (PDOException $e) {
        echo "Error fetching exposure planning data: " . $e->getMessage();
        exit();
    }
?>
<div class="dashboard">
    <?php include 'sidebar.php'; ?>
    <div class="content">
        <h2>Dashboard</h2>
        <p>Welcome to your dashboard. Here you can manage your exposure control planning.</p>
        
        <div id='dashboard-links-container'>
            <ul id='dashboard-links' style="list-style: none;">
                <br />
                <li id='newPlanningFromScratchLink'><a href='#' class="button">Start new planning from scratch</a></li>
            </ul>
            <div id="scratch_box" style="display: none;">
                <hr>
                <div class="row panel scratch-panel">
                    <div class="large-7 columns">
                        <h5 class="more_space">
                            <span class="text_highlight">Start new</span> planning from scratch
                        </h5>
                        <p>To start new exposure control planning from scratch, click <strong>Start</strong>.</p>
                    </div>
                    <div class="large-5 columns" align="left">
                        <br class="show-for-medium-down">
                        <a href="../includes/create_project.php" class="button large save_continue expand radius start-button">
                            <strong><i class="fa fa-caret-right"></i>&nbsp;&nbsp;Start</strong>
                        </a>
                    </div>
                </div>
                <div class="large-12 columns" align="right">
                    <a href="#" class="recommendation close-link" id="scratch_close">
                        <i class="fa fa-times"></i>&nbsp;Close
                    </a>
                </div>
                <hr>
            </div>
        </div>
        <script src='../assets/js/dashboard.js'></script>  
        
        <h3>Exposure Control Planning</h3>
        <div id="exposure-planning-table-wrapper">
            <table class="exposure-planning-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Work Activity</th>
                        <th>Jobsite</th>
                        <th>Status</th>
                        <th>Action</th>
                        <th>Modified</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plannings as $planning): ?>
                        <tr id="planning-row-<?php echo htmlspecialchars($planning['id']); ?>">
                            <td><?php echo htmlspecialchars($planning['id']); ?></td>
                            <td>
                                <?php
                                // Combine tasks, materials, and tools into a set of "activities"
                                if (!empty($planning['tasks']) && !empty($planning['materials']) && !empty($planning['tools'])) {
                                    $activities = [];
                                    $countMax = max(count($planning['tasks']), count($planning['materials']), count($planning['tools']));
                                    for ($i = 0; $i < $countMax; $i++) {
                                        $task = $planning['tasks'][$i] ?? 'N/A';
                                        $material = $planning['materials'][$i] ?? 'N/A';
                                        $tool = $planning['tools'][$i] ?? 'N/A';
                                        $activities[] = htmlspecialchars("$task $material with $tool");
                                    }
                                    echo implode('<br>', $activities);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($planning['jobsite_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php
                                if ($planning['verified'] == 1) {
                                    echo '<span style="color: green;">Verified</span>';
                                } else {
                                    echo '<span style="color: orange;">Unverified</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="/index.php?page=exposure_planning&plan_id=<?php echo htmlspecialchars($planning['id']); ?>" class="button small">Open</a>
                                <button class="button small delete-planning" data-id="<?php echo htmlspecialchars($planning['id']); ?>">Delete</button>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($planning['updated_at'] ? $planning['updated_at'] : $planning['created_at']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', () => {
                // Add event listener for delete buttons
                document.querySelectorAll('.delete-planning').forEach(button => {
                    button.addEventListener('click', function () {
                        const planningId = this.getAttribute('data-id');
                        if (confirm('Are you sure you want to delete this planning?')) {
                            fetch('../ajax/delete_exposure_planning.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ id: planningId }),
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const row = document.getElementById(`planning-row-${planningId}`);
                                    if (row) row.remove();
                                } else {
                                    alert('Failed to delete the planning. Please try again.');
                                }
                            })
                            .catch(error => {
                                console.error('Error deleting planning:', error);
                                alert('An error occurred while trying to delete the planning.');
                            });
                        }
                    });
                });
            });
        </script>
        
    </div>
</div>
