<?php
        include '../includes/db.php';
        session_start();

        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
                header("Location: ?page=dashboard");
                exit();
        }

        // Get search and order parameters from GET
        $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
        $orderField = isset($_GET['order']) ? trim($_GET['order']) : 'updated_at';
        $direction  = isset($_GET['direction']) ? strtolower(trim($_GET['direction'])) : 'desc';

        // Define allowed order fields and map them to actual columns (ID added)
        $orderMapping = [
                'id'             => 'ep.id',
                'updated_at'     => 'ep.updated_at',
                'jobsite_name'   => 'ep.jobsite_name',
                'company_name'   => 'u.company_name',
                'jobsite_address'=> 'epm.jobsite_address'
        ];
        if (!isset($orderMapping[$orderField])) {
                $orderField = 'updated_at';
        }
        if ($direction !== 'asc' && $direction !== 'desc') {
                $direction = 'desc';
        }
        $orderByClause = " ORDER BY " . $orderMapping[$orderField] . " " . strtoupper($direction) . " ";

        // Build the common WHERE clause if a search term is provided
        $whereClause = "";
        $params = [];
        if ($searchTerm !== '') {
                $whereClause = "WHERE (
                        ep.jobsite_name LIKE :search 
                        OR epm.jobsite_address LIKE :search 
                        OR u.company_name LIKE :search";
                if (is_numeric($searchTerm)) {
                        $whereClause .= " OR ep.id = :searchId";
                        $params[':searchId'] = intval($searchTerm);
                }
                $whereClause .= " )";
                $params[':search'] = '%' . $searchTerm . '%';
        }

        // Set pagination parameters
        $limit = 25;

        // For unverified records
        $pageUnverified = isset($_GET['page_unverified']) && is_numeric($_GET['page_unverified'])
                ? intval($_GET['page_unverified']) : 1;
        if ($pageUnverified < 1) { $pageUnverified = 1; }
        $offsetUnverified = ($pageUnverified - 1) * $limit;

        // For verified records
        $pageVerified = isset($_GET['page_verified']) && is_numeric($_GET['page_verified'])
                ? intval($_GET['page_verified']) : 1;
        if ($pageVerified < 1) { $pageVerified = 1; }
        $offsetVerified = ($pageVerified - 1) * $limit;

        // Query for Unverified Plannings (verified = 0)
        $sqlUnverified = "
                SELECT 
                        ep.id,
                        ep.jobsite_name,
                        ep.updated_at,
                        ep.created_at,
                        ep.verified,
                        u.company_name,
                        epm.jobsite_address,
                        epm.jobsite_city,
                        epm.jobsite_region,
                        epm.jobsite_post_code,
                        GROUP_CONCAT(DISTINCT m.name ORDER BY m.id ASC SEPARATOR ', ') AS materials,
                        GROUP_CONCAT(DISTINCT t.name ORDER BY t.id ASC SEPARATOR ', ') AS tasks,
                        GROUP_CONCAT(DISTINCT tl.name ORDER BY tl.id ASC SEPARATOR ', ') AS tools,
                        MAX(ev.verification_date) AS verification_date,
                        MAX(ev.auditor_signature) AS auditor_signature,
                        CONCAT(MAX(au.first_name), ' ', MAX(au.last_name)) AS auditor_name
                FROM Exposure_Plannings ep
                JOIN users u ON ep.user_id = u.id
                LEFT JOIN Exposure_Plannings_Meta epm ON ep.id = epm.planning_id
                LEFT JOIN Materials m ON FIND_IN_SET(m.id, epm.activity_material)
                LEFT JOIN Tasks t ON FIND_IN_SET(t.id, epm.activity_task)
                LEFT JOIN Tools tl ON FIND_IN_SET(tl.id, epm.activity_tool)
                LEFT JOIN Exposure_Plannings_Verification ev ON ep.id = ev.plan_id
                LEFT JOIN users au ON ev.auditor_id = au.id
                " . ($whereClause ? $whereClause . " AND " : "WHERE ") . " ep.verified = 0
                GROUP BY 
                        ep.id,
                        ep.jobsite_name,
                        ep.updated_at,
                        ep.created_at,
                        u.company_name,
                        ep.verified,
                        epm.jobsite_address,
                        epm.jobsite_city,
                        epm.jobsite_region,
                        epm.jobsite_post_code
                " . $orderByClause . "
                LIMIT :limit OFFSET :offset
        ";

        // Query for Verified Plannings (verified = 1)
        $sqlVerified = "
                SELECT 
                        ep.id,
                        ep.jobsite_name,
                        ep.updated_at,
                        ep.created_at,
                        ep.verified,
                        u.company_name,
                        epm.jobsite_address,
                        epm.jobsite_city,
                        epm.jobsite_region,
                        epm.jobsite_post_code,
                        GROUP_CONCAT(DISTINCT m.name ORDER BY m.id ASC SEPARATOR ', ') AS materials,
                        GROUP_CONCAT(DISTINCT t.name ORDER BY t.id ASC SEPARATOR ', ') AS tasks,
                        GROUP_CONCAT(DISTINCT tl.name ORDER BY tl.id ASC SEPARATOR ', ') AS tools,
                        MAX(ev.verification_date) AS verification_date,
                        MAX(ev.auditor_signature) AS auditor_signature,
                        CONCAT(MAX(au.first_name), ' ', MAX(au.last_name)) AS auditor_name
                FROM Exposure_Plannings ep
                JOIN users u ON ep.user_id = u.id
                LEFT JOIN Exposure_Plannings_Meta epm ON ep.id = epm.planning_id
                LEFT JOIN Materials m ON FIND_IN_SET(m.id, epm.activity_material)
                LEFT JOIN Tasks t ON FIND_IN_SET(t.id, epm.activity_task)
                LEFT JOIN Tools tl ON FIND_IN_SET(tl.id, epm.activity_tool)
                LEFT JOIN Exposure_Plannings_Verification ev ON ep.id = ev.plan_id
                LEFT JOIN users au ON ev.auditor_id = au.id
                " . ($whereClause ? $whereClause . " AND " : "WHERE ") . " ep.verified = 1
                GROUP BY 
                        ep.id,
                        ep.jobsite_name,
                        ep.updated_at,
                        ep.created_at,
                        u.company_name,
                        ep.verified,
                        epm.jobsite_address,
                        epm.jobsite_city,
                        epm.jobsite_region,
                        epm.jobsite_post_code
                " . $orderByClause . "
                LIMIT :limit OFFSET :offset
        ";

        // Execute Unverified Query
        try {
                $stmtUnverified = $conn->prepare($sqlUnverified);
                if (!empty($params)) {
                        foreach ($params as $key => $value) {
                                $stmtUnverified->bindValue($key, $value);
                        }
                }
                $stmtUnverified->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmtUnverified->bindValue(':offset', $offsetUnverified, PDO::PARAM_INT);
                $stmtUnverified->execute();
                $unverified = $stmtUnverified->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
                echo "<p class='failureMessage'>Error fetching unverified planning data: " . htmlspecialchars($e->getMessage()) . "</p>";
                exit();
        }

        // Execute Verified Query
        try {
                $stmtVerified = $conn->prepare($sqlVerified);
                if (!empty($params)) {
                        foreach ($params as $key => $value) {
                                $stmtVerified->bindValue($key, $value);
                        }
                }
                $stmtVerified->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmtVerified->bindValue(':offset', $offsetVerified, PDO::PARAM_INT);
                $stmtVerified->execute();
                $verified = $stmtVerified->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
                echo "<p class='failureMessage'>Error fetching verified planning data: " . htmlspecialchars($e->getMessage()) . "</p>";
                exit();
        }

        // Count total unverified records for pagination (include join on users table)
        $countSqlUnverified = "
                SELECT COUNT(DISTINCT ep.id) as total
                FROM Exposure_Plannings ep
                JOIN users u ON ep.user_id = u.id
                LEFT JOIN Exposure_Plannings_Meta epm ON ep.id = epm.planning_id
                " . ($whereClause ? $whereClause . " AND " : "WHERE ") . " ep.verified = 0
        ";
        try {
                $stmtCountUnverified = $conn->prepare($countSqlUnverified);
                if (!empty($params)) {
                        foreach ($params as $key => $value) {
                                $stmtCountUnverified->bindValue($key, $value);
                        }
                }
                $stmtCountUnverified->execute();
                $totalUnverified = $stmtCountUnverified->fetchColumn();
                $totalPagesUnverified = ceil($totalUnverified / $limit);
        } catch (PDOException $e) {
                echo "<p class='failureMessage'>Error counting unverified records: " . htmlspecialchars($e->getMessage()) . "</p>";
                exit();
        }

        // Count total verified records for pagination (include join on users table)
        $countSqlVerified = "
                SELECT COUNT(DISTINCT ep.id) as total
                FROM Exposure_Plannings ep
                JOIN users u ON ep.user_id = u.id
                LEFT JOIN Exposure_Plannings_Meta epm ON ep.id = epm.planning_id
                " . ($whereClause ? $whereClause . " AND " : "WHERE ") . " ep.verified = 1
        ";
        try {
                $stmtCountVerified = $conn->prepare($countSqlVerified);
                if (!empty($params)) {
                        foreach ($params as $key => $value) {
                                $stmtCountVerified->bindValue($key, $value);
                        }
                }
                $stmtCountVerified->execute();
                $totalVerified = $stmtCountVerified->fetchColumn();
                $totalPagesVerified = ceil($totalVerified / $limit);
        } catch (PDOException $e) {
                echo "<p class='failureMessage'>Error counting verified records: " . htmlspecialchars($e->getMessage()) . "</p>";
                exit();
        }
?>
<div class="container">
        <?php include 'sidebar.php'; ?>
        <div class="content">
                <h2>Auditor Tools</h2>
                <p>This page is where site auditors can verify controls, leave additional notes, recommendations, and more.</p>

                <!-- Search and Ordering Form -->
                <form method="GET" action="" style="width: 100%;">
                        <input type="hidden" name="page" value="auditor_tools" />
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                                <div style="flex: 1; display: flex; gap: 10px;">
                                        <div style="flex: 1;">
                                                <label for="search" style="display: block; margin-bottom: 5px;">Search:</label>
                                                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search for ID, Jobsite, Address or Company" style="width: 100%;">
                                        </div>
                                        <div style="flex: 1;">
                                                <label for="order" style="display: block; margin-bottom: 5px;">Order by:</label>
                                                <select id="order" name="order" style="width: 100%;">
                                                        <option value="id" <?php echo ($orderField === 'id') ? 'selected' : ''; ?>>ID</option>
                                                        <option value="updated_at" <?php echo ($orderField === 'updated_at') ? 'selected' : ''; ?>>Last Updated</option>
                                                        <option value="jobsite_name" <?php echo ($orderField === 'jobsite_name') ? 'selected' : ''; ?>>Jobsite Name</option>
                                                        <option value="company_name" <?php echo ($orderField === 'company_name') ? 'selected' : ''; ?>>Company Name</option>
                                                        <option value="jobsite_address" <?php echo ($orderField === 'jobsite_address') ? 'selected' : ''; ?>>Jobsite Address</option>
                                                </select>
                                        </div>
                                        <div style="flex: 1;">
                                                <label for="direction" style="display: block; margin-bottom: 5px;">Direction:</label>
                                                <select id="direction" name="direction" style="width: 100%;">
                                                        <option value="desc" <?php echo ($direction === 'desc') ? 'selected' : ''; ?>>Descending</option>
                                                        <option value="asc" <?php echo ($direction === 'asc') ? 'selected' : ''; ?>>Ascending</option>
                                                </select>
                                        </div>
                                </div>
                                <div style="align-self: flex-end; margin-bottom: 12px;">
                                        <button class="button" type="submit">Apply Search Options</button>
                                </div>
                        </div>
                </form>
                
                <!-- Tab Navigation -->
                <ul class="tabs">
                        <li class="tab active" data-tab="unverified-tab">Unverified Plannings</li>
                        <li class="tab" data-tab="verified-tab">Verified Plannings</li>
                </ul>

                <!-- Tab Content -->
                <div id="tab-contents">
                        <!-- Unverified Tab -->
                        <div id="unverified-tab" class="tab-content" style="display: block;">
                                <h3>Exposure Control Planning - Unverified</h3>
                                <?php if (empty($unverified)): ?>
                                        <p>No unverified planning records.</p>
                                <?php else: ?>
                                        <div id="exposure-planning-table-wrapper">
                                                <table class="exposure-planning-table">
                                                        <thead>
                                                                <tr>
                                                                        <th>ID</th>
                                                                        <th>Company Name</th>
                                                                        <th>Jobsite</th>
                                                                        <th>Work Activity</th>
                                                                        <th>Auditor Verified?</th>
                                                                        <th>Action</th>
                                                                        <th>Modified</th>
                                                                </tr>
                                                        </thead>
                                                        <tbody>
                                                                <?php foreach ($unverified as $planning): ?>
                                                                        <tr id="planning-row-<?php echo htmlspecialchars($planning['id']); ?>">
                                                                                <td><?php echo htmlspecialchars($planning['id']); ?></td>
                                                                                <td><?php echo htmlspecialchars($planning['company_name'] ?? 'Company name not yet set'); ?></td>
                                                                                <td>
                                                                                        <strong><?php echo htmlspecialchars($planning['jobsite_name'] ?? 'Jobsite name not yet set'); ?></strong><br />
                                                                                        <?php echo htmlspecialchars($planning['jobsite_address'] ?? 'Jobsite address not yet provided'); ?><br />
                                                                                        <?php echo htmlspecialchars($planning['jobsite_city'] ?? ''); ?>&nbsp;
                                                                                        <?php echo htmlspecialchars($planning['jobsite_region'] ?? ''); ?>&nbsp;
                                                                                        <?php echo htmlspecialchars($planning['jobsite_post_code'] ?? ''); ?>
                                                                                </td>
                                                                                <td>
                                                                                        <?php
                                                                                        if (!empty($planning['tasks']) && !empty($planning['materials']) && !empty($planning['tools'])) {
                                                                                                $activities = [];
                                                                                                $taskList = explode(', ', $planning['tasks']);
                                                                                                $materialList = explode(', ', $planning['materials']);
                                                                                                $toolList = explode(', ', $planning['tools']);
                                                                                                for ($i = 0; $i < max(count($taskList), count($materialList), count($toolList)); $i++) {
                                                                                                        $task = $taskList[$i] ?? 'Task not set';
                                                                                                        $material = $materialList[$i] ?? 'Material not set';
                                                                                                        $tool = $toolList[$i] ?? 'Tool not set';
                                                                                                        $activities[] = htmlspecialchars("$task $material with $tool");
                                                                                                }
                                                                                                echo implode('<br>', $activities);
                                                                                        } else {
                                                                                                echo 'No working activities have been added yet.';
                                                                                        }
                                                                                        ?>
                                                                                </td>
                                                                                <td>
                                                                                        <?php
                                                                                        echo ($planning['verified'] == 1)
                                                                                                ? '<span style="color: green;">Verified</span><br />' 
                                                                                                        . htmlspecialchars(date('F j, Y', strtotime($planning['verification_date']))) 
                                                                                                        . '<br />by ' . htmlspecialchars($planning['auditor_name'])
                                                                                                : '<span style="color: orange;">Not Verified</span>';
                                                                                        ?>
                                                                                </td>
                                                                                <td>
                                                                                        <a href="/index.php?page=auditor_verification&plan_id=<?php echo htmlspecialchars($planning['id']); ?>" class="button small">
                                                                                                <?php echo ($planning['verified'] == 1) ? 'Review' : 'Verify'; ?>
                                                                                        </a>
                                                                                </td>
                                                                                <td>
                                                                                        <?php echo htmlspecialchars(
                                                                                                $planning['updated_at'] ? $planning['updated_at'] : $planning['created_at']
                                                                                        ); ?>
                                                                                </td>
                                                                        </tr>
                                                                <?php endforeach; ?>
                                                        </tbody>
                                                </table>
                                        </div>
                                <?php endif; ?>
                                <br />
                                <!-- Pagination for Unverified -->
                                <?php if ($totalPagesUnverified > 1): ?>
                                        <div class="pagination">
                                                <?php if ($pageUnverified > 1): ?>
                                                        <a href="?page=auditor_tools&page_unverified=<?php echo $pageUnverified - 1; ?>&search=<?php echo urlencode($searchTerm); ?>&order=<?php echo urlencode($orderField); ?>&direction=<?php echo urlencode($direction); ?>">Previous</a>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = 1; $i <= $totalPagesUnverified; $i++): ?>
                                                        <a href="?page=auditor_tools&page_unverified=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&order=<?php echo urlencode($orderField); ?>&direction=<?php echo urlencode($direction); ?>"
                                                                <?php if ($i == $pageUnverified) echo 'class="active"'; ?>>
                                                                <?php echo $i; ?>
                                                        </a>
                                                <?php endfor; ?>
                                                
                                                <?php if ($pageUnverified < $totalPagesUnverified): ?>
                                                        <a href="?page=auditor_tools&page_unverified=<?php echo $pageUnverified + 1; ?>&search=<?php echo urlencode($searchTerm); ?>&order=<?php echo urlencode($orderField); ?>&direction=<?php echo urlencode($direction); ?>">Next</a>
                                                <?php endif; ?>
                                        </div>
                                <?php endif; ?>
                        </div>
                        
                        <!-- Verified Tab -->
                        <div id="verified-tab" class="tab-content" style="display: none;">
                                <h3>Exposure Control Planning - Verified</h3>
                                <?php if (empty($verified)): ?>
                                        <p>No verified planning records.</p>
                                <?php else: ?>
                                        <div id="exposure-planning-table-wrapper-verified">
                                                <table class="exposure-planning-table">
                                                        <thead>
                                                                <tr>
                                                                        <th>ID</th>
                                                                        <th>Company Name</th>
                                                                        <th>Jobsite</th>
                                                                        <th>Work Activity</th>
                                                                        <th>Auditor Verified?</th>
                                                                        <th>Action</th>
                                                                        <th>Modified</th>
                                                                </tr>
                                                        </thead>
                                                        <tbody>
                                                                <?php foreach ($verified as $planning): ?>
                                                                        <tr id="planning-row-<?php echo htmlspecialchars($planning['id']); ?>">
                                                                                <td><?php echo htmlspecialchars($planning['id']); ?></td>
                                                                                <td><?php echo htmlspecialchars($planning['company_name'] ?? 'N/A'); ?></td>
                                                                                <td>
                                                                                        <strong><?php echo htmlspecialchars($planning['jobsite_name'] ?? 'N/A'); ?></strong><br />
                                                                                        <?php echo htmlspecialchars($planning['jobsite_address'] ?? 'N/A'); ?><br />
                                                                                        <?php echo htmlspecialchars($planning['jobsite_city'] ?? ''); ?>&nbsp;
                                                                                        <?php echo htmlspecialchars($planning['jobsite_region'] ?? ''); ?>&nbsp;
                                                                                        <?php echo htmlspecialchars($planning['jobsite_post_code'] ?? ''); ?>
                                                                                </td>
                                                                                <td>
                                                                                        <?php
                                                                                        if (!empty($planning['tasks']) && !empty($planning['materials']) && !empty($planning['tools'])) {
                                                                                                $activities = [];
                                                                                                $taskList = explode(', ', $planning['tasks']);
                                                                                                $materialList = explode(', ', $planning['materials']);
                                                                                                $toolList = explode(', ', $planning['tools']);
                                                                                                for ($i = 0; $i < max(count($taskList), count($materialList), count($toolList)); $i++) {
                                                                                                        $task = $taskList[$i] ?? 'Task not set';
                                                                                                        $material = $materialList[$i] ?? 'Material not set';
                                                                                                        $tool = $toolList[$i] ?? 'Tool not set';
                                                                                                        $activities[] = htmlspecialchars("$task $material with $tool");
                                                                                                }
                                                                                                echo implode('<br>', $activities);
                                                                                        } else {
                                                                                                echo 'No working activities have been added yet.';
                                                                                        }
                                                                                        ?>
                                                                                </td>
                                                                                <td>
                                                                                        <?php
                                                                                        echo ($planning['verified'] == 1)
                                                                                                ? '<span style="color: green;">Verified</span><br />' 
                                                                                                        . htmlspecialchars(date('F j, Y', strtotime($planning['verification_date']))) 
                                                                                                        . '<br />by ' . htmlspecialchars($planning['auditor_name'])
                                                                                                : '<span style="color: orange;">Not Verified</span>';
                                                                                        ?>
                                                                                </td>
                                                                                <td>
                                                                                        <a href="/index.php?page=auditor_verification&plan_id=<?php echo htmlspecialchars($planning['id']); ?>" class="button small">
                                                                                                <?php echo ($planning['verified'] == 1) ? 'Review' : 'Verify'; ?>
                                                                                        </a>
                                                                                </td>
                                                                                <td>
                                                                                        <?php echo htmlspecialchars(
                                                                                                $planning['updated_at'] ? $planning['updated_at'] : $planning['created_at']
                                                                                        ); ?>
                                                                                </td>
                                                                        </tr>
                                                                <?php endforeach; ?>
                                                        </tbody>
                                                </table>
                                        </div>
                                <?php endif; ?>
                                <br />
                                <!-- Pagination for Verified -->
                                <?php if ($totalPagesVerified > 1): ?>
                                        <div class="pagination">
                                                <?php if ($pageVerified > 1): ?>
                                                        <a href="?page=auditor_tools&page_verified=<?php echo $pageVerified - 1; ?>&search=<?php echo urlencode($searchTerm); ?>&order=<?php echo urlencode($orderField); ?>&direction=<?php echo urlencode($direction); ?>">Previous</a>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = 1; $i <= $totalPagesVerified; $i++): ?>
                                                        <a href="?page=auditor_tools&page_verified=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&order=<?php echo urlencode($orderField); ?>&direction=<?php echo urlencode($direction); ?>"
                                                                <?php if ($i == $pageVerified) echo 'class="active"'; ?>>
                                                                <?php echo $i; ?>
                                                        </a>
                                                <?php endfor; ?>
                                                
                                                <?php if ($pageVerified < $totalPagesVerified): ?>
                                                        <a href="?page=auditor_tools&page_verified=<?php echo $pageVerified + 1; ?>&search=<?php echo urlencode($searchTerm); ?>&order=<?php echo urlencode($orderField); ?>&direction=<?php echo urlencode($direction); ?>">Next</a>
                                                <?php endif; ?>
                                        </div>
                                <?php endif; ?>
                        </div>
                </div>
        </div>
</div>

<style>
    .pagination a {
        margin: 0 5px;
        text-decoration: none;
        padding: 5px 10px;
        border: 1px solid #ccc;
    }
    .pagination a.active {
        background: #333;
        color: #fff;
        border-color: #333;
    }
</style>

<script>
    // Using the same tab functionality as your other working page
    const tabs = document.querySelectorAll('.tabs .tab');
    const tabContents = {
        "unverified-tab": document.getElementById("unverified-tab"),
        "verified-tab": document.getElementById("verified-tab")
    };

    // Initially show only the active tab content (Unverified)
    for (const key in tabContents) {
        if (Object.hasOwnProperty.call(tabContents, key)) {
            tabContents[key].style.display = (key === "unverified-tab") ? 'block' : 'none';
        }
    }

    // Add click event listeners to each tab
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            for (const key in tabContents) {
                if (Object.hasOwnProperty.call(tabContents, key)) {
                    tabContents[key].style.display = 'none';
                }
            }
            this.classList.add('active');
            const tabId = this.getAttribute('data-tab');
            if (tabContents[tabId]) {
                tabContents[tabId].style.display = 'block';
            }
        });
    });
</script>
