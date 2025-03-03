<?php
include './includes/db.php'; // Assuming this file contains the DB connection
// Get the user ID (ui) and auth token from the query string
$userId = isset($_GET['ui']) ? intval($_GET['ui']) : null;
$authToken = isset($_GET['auth']) ? $_GET['auth'] : null;

if (!$userId || !$authToken) {
    echo "<p class='failureMessage'>Invalid reset link. Please try again.</p>";
    exit();
}

try {
    // Check if the reset token exists for the given user ID
    $stmt = $conn->prepare("SELECT * FROM user_meta WHERE user_id = :user_id AND meta_key = 'pw_reset_token' AND meta_value = :auth_token");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':auth_token', $authToken, PDO::PARAM_STR);
    $stmt->execute();

    $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resetRequest) {
        // Fetch user details
        $userStmt = $conn->prepare("SELECT first_name FROM users WHERE id = :user_id");
        $userStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $userStmt->execute();
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo "<p class='failureMessage'>User not found.</p>";
            exit();
        }
    } else {
        echo "<p class='failureMessage'>Invalid or expired reset token.</p>";
        exit();
    }
} catch (PDOException $e) {
    echo "<p class='failureMessage'>Error: " . $e->getMessage() . "</p>";
    exit();
}
?>

    <div class="reset-password-container">
        <h2>Password Reset</h2>
        <p>Hi <?php echo htmlspecialchars($user['first_name']); ?>,</p>
        <p>Please enter and confirm your new password below:</p>

        <form id="reset-password-form">
            <div class="form-group">
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="button" id="reset-password-button" disabled>Set New Password</button>
        </form>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>

            if (typeof jQuery === "undefined") {
                console.error("jQuery is not loaded.");
            } else {
                console.log("jQuery is loaded.");
            }

            const newPasswordField = $('#new_password');
            const confirmPasswordField = $('#confirm_password');
            const resetButton = $('#reset-password-button');

            function validatePasswords() {
                const newPassword = newPasswordField.val();
                const confirmPassword = confirmPasswordField.val();

                if (newPassword.length > 0 && confirmPassword.length > 0 && newPassword === confirmPassword) {
                    resetButton.prop('disabled', false);
                } else {
                    resetButton.prop('disabled', true);
                }
            }

            newPasswordField.on('input', validatePasswords);
            confirmPasswordField.on('input', validatePasswords);

            resetButton.on('click', function () {
                const newPassword = newPasswordField.val();

                $.ajax({
                    url: '../ajax/reset_password.php',
                    type: 'POST',
                    data: {
                        user_id: <?php echo json_encode($userId); ?>,
                        new_password: newPassword
                    },
                    success: function (response) {
                        alert('Password has been reset successfully.');
                        window.location.href = '?page=dashboard';
                    },
                    error: function () {
                        alert('An error occurred. Please try again.');
                    }
                });
            });
    </script>

