<?php
include_once 'db.php';

function registerUser($first_name, $last_name, $company_name, $email, $password, $role = 'user') {
    global $conn;
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Default role handling: Ensure role is either 'user' or 'admin'
    if ($role !== 'admin') {
        $role = 'user';
    }

    try {
        // Insert into database with the role field
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, company_name, email, password_hash, role) VALUES (:first_name, :last_name, :company_name, :email, :password_hash, :role)");
        
        // Bind parameters
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':company_name', $company_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->bindParam(':role', $role);

        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function loginUser($email, $password) {
    global $conn;
    
    // Updated query to also fetch the 'role' column
    $stmt = $conn->prepare("SELECT id, first_name, last_name, company_name, email, role, password_hash FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    } else {
        return false;
    }
}


function updateUserInfo($userId, $company_name, $address, $phone, $email, $website)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
            UPDATE users 
            SET company_name = :company_name, address = :address, phone = :phone, email = :email, website = :website
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $userId);
        $stmt->bindParam(':company_name', $company_name);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':website', $website);

        return $stmt->execute();
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
        return false;
    }
}
?>
