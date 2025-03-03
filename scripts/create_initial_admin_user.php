<?php
include '../includes/db.php';

function createAdminUser($first_name, $last_name, $company_name, $email, $password) {
    global $conn;

    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $role = 'admin';

    try {
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, company_name, email, password_hash, role) VALUES (:first_name, :last_name, :company_name, :email, :password_hash, :role)");
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':company_name', $company_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->bindParam(':role', $role);
        $stmt->execute();
        echo "Admin user created successfully.";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

// Create the initial admin user
createAdminUser('Adam', 'Langley', 'AO Digital', 'adam@aodigital.com.au', 'Ab64307685!');
?>
