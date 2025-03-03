<?php
include '../includes/db.php'; // Assuming this file contains the DB connection
session_start();

// Verify user session is set properly
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?page=login_register");
    exit();
}

// Fetch user data from the session
$user = $_SESSION['user'];
$user_id = $user['id']; // Use 'id' in lowercase to match the database and session key

// Check if plan ID is provided in the URL
$plan_id = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : null;

if (!$plan_id) {
    echo "Error: No valid plan ID provided.";
    exit();
}

try {
    // Fetch the current exposure planning data using the provided plan ID and user ID
    $stmt = $conn->prepare("SELECT * FROM Exposure_Plannings WHERE id = :plan_id AND user_id = :user_id");
    $stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    $exposure_planning = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exposure_planning) {
        echo "Error: No exposure planning found.";
        exit();
    }
    $current_step = $exposure_planning['current_step'] ?? 'welcome';

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <title>Exposure Control Planning - Nextrack</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/dynamic_steps.js"></script> <!-- Script to handle AJAX and step navigation -->
</head>
<body>
    <!-- Header Bar -->
    <?php include 'header.php'; ?>

    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <?php include 'exposure_plan_sidebar.php'; ?>
        </div>

        <!-- Content Area -->
        <div class="content" id="content-area">
            <h2>Welcome to Your Exposure Control Planning Tool</h2>
            <p>Please use the sidebar to navigate through the different steps of your exposure control plan.</p>
        </div>
    </div>
<script>
    window.planId = '<?php echo $plan_id; ?>';
    window.currentStep = '<?php echo $current_step; ?>';
</script>

<script src="../assets/js/dynamic_steps.js"></script>

