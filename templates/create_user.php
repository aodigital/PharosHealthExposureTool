<?php
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null; // Ensure $user is defined

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    // Option 1: Redirect to login page
    header("Location: ?page=dashboard");
    exit();

    // Option 2: Display an access denied message
    // echo "<p class='failureMessage'>Access denied. You do not have permission to view this page.</p>";
    // exit();
}

// If the user is an admin, proceed with the page content
$user = $_SESSION['user'];
?>
<div class="container">
    <?php include 'sidebar.php'; ?>
    <div class="content">
        <h1>Create New User</h1>
        
        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_user'])) {
            // Get form data
            $first_name = $_POST['first_name'] ?? null;
            $last_name = $_POST['last_name'] ?? null;
            $company_name = $_POST['company_name'] ?? null;
            $email = $_POST['email'];
            $password = $_POST['password'];
            $role = $_POST['role'];
        }
        ?>

        <form method="POST" action="?page=create_user">
            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name">
            </div>
            
            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name">
            </div>
            
            <div class="form-group">
                <label for="company_name">Company Name:</label>
                <input type="text" id="company_name" name="company_name">
            </div>
            
            <div class="form-group">
                <label for="email">Email (Username):</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <button type="submit" name="create_user" class="button">Create User</button>
        </form>
    </div>
</div>
