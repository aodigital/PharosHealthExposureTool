<?php
include '../includes/db.php';
include '../includes/mailer.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? null;

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit();
}

try {
    // Check if the email exists
    $stmt = $conn->prepare("SELECT id, email, first_name FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Email not found in the system.']);
        exit();
    }

    // Generate a unique token
    $resetToken = bin2hex(random_bytes(16));

    // Save the token in the user_meta table
    $stmt = $conn->prepare("INSERT INTO user_meta (user_id, meta_key, meta_value) VALUES (:user_id, 'pw_reset_token', :reset_token)
                            ON DUPLICATE KEY UPDATE meta_value = :reset_token");
    $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
    $stmt->bindParam(':reset_token', $resetToken, PDO::PARAM_STR);
    $stmt->execute();

    // Send the email with the reset link
    $resetLink = "https://silicatest.nextrack.co.uk/index.php?page=reset_password_page&ui={$user['id']}&auth={$resetToken}";
    $subject = 'Password Reset Instructions';
    $body = "
        <p>Dear {$user['first_name']},</p>
        <p>You requested to reset your password. Please click the link below to reset it:</p>
        <p><a href='{$resetLink}'>{$resetLink}</a></p>
        <p>If you did not request this reset, you can safely ignore this email.</p>
        <p>Thank you,</p>
        <p>Silica Control Tool Team</p>
    ";
    $altBody = "Dear {$user['first_name']},\n\nYou requested to reset your password. Please use the link below:\n\n{$resetLink}\n\nIf you did not request this reset, you can safely ignore this email.";

    // Use the Mailer class
    $mailer = new Mailer();
    $result = $mailer->sendEmail($user['email'], $user['first_name'], $subject, $body, $altBody);

    if ($result === true) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $result]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
