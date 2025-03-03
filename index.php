<?php
include 'includes/user.php';
session_start();
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$page = isset($_GET['page']) ? $_GET['page'] : 'login_register';
$plan_id = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : null;
function getAdminOption($name) {
    global $pdo; // Use the PDO instance from db.php

    try {
        $stmt = $pdo->prepare("SELECT `value` FROM admin_options WHERE `name` = :name LIMIT 1");
        $stmt->execute(['name' => $name]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : false;
    } catch (PDOException $e) {
        // Log the error without exposing it to the user
        error_log("Database Error in getAdminOption: " . $e->getMessage());
        return false;
    }
}
include 'templates/header.php';

switch ($page) {
    case 'dashboard':
        if (isset($_SESSION['user'])) {
            include 'templates/dashboard.php';
        } else {
            header("Location: ?page=login_register");
            exit();
        }
        break;

    case 'manage_account':
        if (isset($_SESSION['user'])) {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_account'])) {
                // Update user information
                $company_name = $_POST['company_name'];
                $address = $_POST['address'];
                $phone = $_POST['phone'];
                $email = $_POST['email'];
                $website = $_POST['website'];

                // Call function to save updated user information
                if (updateUserInfo($_SESSION['user']['id'], $company_name, $address, $phone, $email, $website)) {
                    // Update the session with new user data
                    $_SESSION['user']['company_name'] = $company_name;
                    $_SESSION['user']['address'] = $address;
                    $_SESSION['user']['phone'] = $phone;
                    $_SESSION['user']['email'] = $email;
                    $_SESSION['user']['website'] = $website;

                    echo "<p class='sucessMessage'>Account information updated successfully.</p>";
                } else {
                    echo "<p class='failureMessage'>Error: Could not update account information.</p>";
                }
            }
            include 'templates/manage_account.php';
        } else {
            header("Location: ?page=login_register");
            exit();
        }
        break;

    case 'create_user':
        if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_user'])) {
                // Get form data
                $first_name = $_POST['first_name'] ?? null;
                $last_name = $_POST['last_name'] ?? null;
                $company_name = $_POST['company_name'] ?? null;
                $email = $_POST['email'];
                $password = $_POST['password'];
                $role = $_POST['role'];

                // Call function to create new user
                if (registerUser($first_name, $last_name, $company_name, $email, $password, $role)) {
                    echo "<p class='sucessMessage'>New user created successfully.</p>";
                } else {
                    echo "<p class='failureMessage'>Error: Could not create new user.</p>";
                }
            }
            include 'templates/create_user.php';
        } else {
            header("Location: ?page=dashboard");
            exit();
        }
        break;

    case 'manage_users':
        if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
            include 'templates/manage_users.php';
        } else {
            header("Location: ?page=dashboard");
            exit();
        }
        break;

    case 'auditor_tools':
        if (isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin', 'auditor'])) {
            include 'templates/auditor_tools.php';
        } else {
            header("Location: ?page=dashboard");
            exit();
        }
        break;

    case 'auditor_verification':
        if (isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin', 'auditor'])) {
            include 'templates/auditor_verification.php';
        } else {
            header("Location: ?page=dashboard");
            exit();
        }
        break;

    case 'exposure_planning':
        if (isset($_SESSION['user'])) {
            if ($plan_id) {
                include 'templates/exposure_planning.php';
            } else {
                // If no plan ID is provided, redirect to create a new project
                header("Location: includes/create_project.php");
                exit();
            }
        } else {
            header("Location: ?page=login_register");
            exit();
        }
        break;

    case 'login_register':
    default:
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (isset($_POST['register'])) {
                // Registration logic
                $first_name = $_POST['first_name'];
                $last_name = $_POST['last_name'];
                $company_name = $_POST['company_name'];
                $email = $_POST['email'];
                $password = $_POST['password'];
                if (registerUser($first_name, $last_name, $company_name, $email, $password)) {
                    echo "<p class='sucessMessage'>User registered successfully.</p>";
                } else {
                    echo "<p class='failureMessage'>Error: Could not register user.</p>";
                }
            }

            if (isset($_POST['login'])) {
                // Login logic
                $email = $_POST['email'];
                $password = $_POST['password'];
                $user = loginUser($email, $password);
                if ($user) {
                    $_SESSION['user'] = $user;
                    header("Location: ?page=dashboard");
                    exit();
                } else {
                    echo "<p class='failureMessage'>Invalid login credentials.</p>";
                }
            }
        }

        if (!isset($_SESSION['user'])) {
        ?>
        <div class="auth-container">
            <div class="auth-header">
                <h1>Welcome to the Pharos Health Exposure Control Tool</h1>
                <p>Please register or log in to get started.</p>
            </div>

            <!-- Tab Links -->
            <div class="tab-links">
                <button class="tab-button active" onclick="openTab(event, 'register')">Register</button>
                <button class="tab-button" onclick="openTab(event, 'login')">Login</button>
            </div>

            <!-- Registration Form -->
            <div id="register" class="tab-content active">
                <form method="POST">
                    <div class="auth-form-group">
                        <label for="first_name">First Name:</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="auth-form-group">
                        <label for="last_name">Last Name:</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                    <div class="auth-form-group">
                        <label for="company_name">Company Name:</label>
                        <input type="text" id="company_name" name="company_name" required>
                    </div>
                    <div class="auth-form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="auth-form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" name="register" class="auth-button">Register</button>
                </form>
            </div>

            <!-- Login Form -->
            <div id="login" class="tab-content">
                <form method="POST">
                    <div class="auth-form-group">
                        <label for="login_email">Email:</label>
                        <input type="email" id="login_email" name="email" required>
                    </div>
                    <div class="auth-form-group">
                        <label for="login_password">Password:</label>
                        <input type="password" id="login_password" name="password" required>
                    </div>
                    <button type="submit" name="login" class="auth-button">Login</button>
                </form>
                <div id="forgotten-password-section">
                    <a href="javascript:void(0)" id="forgotten-password-link">Forgotten Password?</a>
                    <div id="forgotten-password-input" style="display: none;">
                        <input type="email" id="forgotten-password-email" placeholder="Enter your email address" required>
                        <button id="forgotten-password-button" disabled>Reset Password</button>
                    </div>
                </div>

            </div>
        </div>
        <script>
            const forgottenPasswordLink = document.getElementById('forgotten-password-link');
            const forgottenPasswordInput = document.getElementById('forgotten-password-input');
            const forgottenPasswordEmail = document.getElementById('forgotten-password-email');
            const forgottenPasswordButton = document.getElementById('forgotten-password-button');

            // Show forgotten password input when the link is clicked
            forgottenPasswordLink.addEventListener('click', () => {
                forgottenPasswordInput.style.display = 'block';
            });

            // Enable the reset button when a valid email is entered
            forgottenPasswordEmail.addEventListener('input', () => {
                if (forgottenPasswordEmail.value.trim() !== '') {
                    forgottenPasswordButton.disabled = false;
                } else {
                    forgottenPasswordButton.disabled = true;
                }
            });

            // Handle the reset password button click
            forgottenPasswordButton.addEventListener('click', () => {
                const email = forgottenPasswordEmail.value.trim();
                if (!email) {
                    alert('Please enter a valid email address.');
                    return;
                }

                // Send the reset request via AJAX
                fetch('./ajax/reset_password_email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ email: email }),
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Password reset instructions have been sent to your email.');
                            forgottenPasswordInput.style.display = 'none';
                            forgottenPasswordEmail.value = '';
                            forgottenPasswordButton.disabled = true;
                        } else {
                            alert(data.message || 'Email Send Error. Please try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An unknown error occurred. Please try again.');
                    });
            });
        </script>
        <?php
        }else {
            header("Location: ?page=dashboard");    
        }
        break;

        case 'reset_password_page':
        if (!isset($_SESSION['user'])) {
            include 'templates/reset_password_page.php';
        } else {
            header("Location: ?page=login_register");
            exit();
        }
        break;
}

include 'templates/footer.php';
?>
