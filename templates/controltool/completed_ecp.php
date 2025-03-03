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
        SELECT ep.id, ep.jobsite_name, ep.created_at, ep.updated_at,
               em.activity_task, em.activity_tool, em.activity_material,
               em.work_area, em.project_start_date, em.project_end_date, em.avg_hr_per_shift,
               t.name AS task_name, tl.name AS tool_name, m.name AS material_name
        FROM Exposure_Plannings ep
        LEFT JOIN Exposure_Plannings_Meta em ON ep.id = em.planning_id
        LEFT JOIN Tasks t ON em.activity_task = t.id
        LEFT JOIN Tools tl ON em.activity_tool = tl.id
        LEFT JOIN Materials m ON em.activity_material = m.id
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
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

    <h2>Your exposure control planning is complete.</h2>
    <p>
        <strong>ECP Revisions?</strong><br>
        This exposure control planning project is saved online to your Dashboard, in the Exposure Control Planning listing under:<br>
        Project #: <?php echo htmlspecialchars($data['id'] ?? 'N/A'); ?><br>
        <?php echo htmlspecialchars(($data['task_name'] ?? 'N/A') . ' ' . ($data['material_name'] ?? 'N/A') . ' with a ' . ($data['tool_name'] ?? 'N/A')); ?><br>
        <?php echo htmlspecialchars($data['work_area'] ?? 'N/A'); ?> for approximately <?php echo htmlspecialchars($data['avg_hr_per_shift'] ?? 'N/A'); ?> hour work shifts.<br>
        Jobsite Name: <?php echo htmlspecialchars($data['jobsite_name'] ?? 'N/A'); ?>
    </p>
    <p>
        You can make revisions to this exposure control planning project at any time. From your Dashboard, (1) open the project; (2) using the left-side menu, navigate to the information that requires updating; (3) make the revision; and (4) click Continue. If you require new documentation as a result of revisions, (5) click on Documentation > Generate ECP in the left-side menu.
    </p>
    <p>
        Thank you for using the Nextrack Silica Control Tool!<br>
        - Nextrack Silica Control Team
    </p>

    <!-- Feedback Section -->
    <div class="feedback-section">
        <div class="feedback-column-left">
            <h3>Feedback</h3>
            <p>
                Paul, this Nextrack Silica Control Tool is a work in progress, and your suggestions and comments would greatly help the Tool evolve.
            </p>
            <p>Please do provide us with feedback!</p>
        </div>
        <div class="feedback-column-right">
            <form action="" method="post">
                <label for="feedback-name">Name:</label>
                <input type="text" id="feedback-name" name="name" required>
                
                <label for="feedback-company">Company:</label>
                <input type="text" id="feedback-company" name="company">
                
                <label for="feedback-email">Email:</label>
                <input type="email" id="feedback-email" name="email" required>
                
                <label for="feedback-message">Message:</label>
                <textarea id="feedback-message" name="message" rows="5" required></textarea>
                
                <button type="submit" class="button">Submit Feedback</button>
            </form>
        </div>
    </div>

    <!-- Navigation Buttons -->
    <div class="clearfix">
        <a href="javascript:void(0)" class="button secondary left load-step" data-step="documentation_generate_ecp">Back</a>
        <a href="index.php?page=dashboard" class="button save_continue secondary right">Return To Dashboard</a>
        <a href="logout.php" class="button save_continue secondary right logout">Save and Log Out</a>
    </div>
