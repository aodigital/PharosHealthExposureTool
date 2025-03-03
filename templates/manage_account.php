<?php
$user = $_SESSION['user'];
?>

<div class="container">
    <?php include 'sidebar.php'; ?>
    
    <div class="content account-form-container">
            <h2>Account Information</h2>
            <p>View or edit your account information below.</p>

            <h4>Your Company Information</h4>
            <form method="POST" action="?page=manage_account">
                <label for="company_name">Company Name:</label>
                <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($user['company_name']); ?>" required>

                <label for="address">Address:</label>
                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" required>

                <label for="phone">Phone:</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>" required>

                <label for="website">Website:</label>
                <input type="text" id="website" name="website" value="<?php echo htmlspecialchars($user['website'] ?? ''); ?>">

                <button type="submit" name="save_account" class="button">Save Changes</button>
            </form>
    </div>

</div>
