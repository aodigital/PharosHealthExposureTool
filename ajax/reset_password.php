<?php
include '../includes/db.php';
session_start(); // Ensure session is started

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : null;

    if (!$userId || !$newPassword) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
        exit();
    }

    // Hash the new password
    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

    try {
        // Update user's password
        $stmt = $conn->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :user_id");
        $stmt->bindParam(':password_hash', $passwordHash, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

        $updateSuccess = $stmt->execute();

        // Determine success accurately
        if ($updateSuccess) {
            // Remove the reset token
            $deleteStmt = $conn->prepare("DELETE FROM user_meta WHERE user_id = :user_id AND meta_key = 'pw_reset_token'");
            $deleteStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $deleteStmt->execute();

            // Fetch user details to set the session
            $userStmt = $conn->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE id = :user_id");
            $userStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $userStmt->execute();
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Set the login session
                $_SESSION['user'] = $user;

                echo json_encode(['success' => true, 'redirect' => '/index.php?page=dashboard']);
            } else {
                echo json_encode(['success' => false, 'message' => 'User session could not be established.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Password update failed, no changes made.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
